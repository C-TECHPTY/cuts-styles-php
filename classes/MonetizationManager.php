<?php
// classes/MonetizationManager.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/SystemSettings.php';

class MonetizationManager {
    private PDO $conn;
    private SystemSettings $settings;
    private array $tableCache = [];

    public function __construct(?PDO $conn = null) {
        if ($conn instanceof PDO) {
            $this->conn = $conn;
        } else {
            $database = new Database();
            $this->conn = $database->getConnection();
        }

        $this->settings = new SystemSettings($this->conn);
    }

    public function initializeBarberProfile(int $barberoId): bool {
        if ($barberoId <= 0 || !$this->hasTable('barber_monetization_profiles')) {
            return false;
        }

        try {
            $check = $this->conn->prepare("SELECT id FROM barber_monetization_profiles WHERE barber_id = :barber_id LIMIT 1");
            $check->bindValue(':barber_id', $barberoId, PDO::PARAM_INT);
            $check->execute();
            if ($check->fetch(PDO::FETCH_ASSOC)) {
                $this->refreshBarberStatus($barberoId);
                return true;
            }

            $trialEnabled = $this->settings->getBool('barber_trial_enabled', false);
            $trialDays = $this->settings->getInt('barber_trial_days', 15);
            $freeModeEnabled = $this->settings->getBool('barber_free_mode_enabled', true);

            $status = $freeModeEnabled ? 'free' : 'expired';
            $trialStart = null;
            $trialEnd = null;
            $trialUsed = 0;

            if ($trialEnabled && in_array($trialDays, [15, 30], true)) {
                $status = 'trial';
                $trialStart = date('Y-m-d H:i:s');
                $trialEnd = date('Y-m-d H:i:s', strtotime('+' . $trialDays . ' days'));
                $trialUsed = 1;
            }

            $sql = "INSERT INTO barber_monetization_profiles
                    (barber_id, status, trial_start_date, trial_end_date, trial_used, created_at, updated_at)
                    VALUES (:barber_id, :status, :trial_start_date, :trial_end_date, :trial_used, NOW(), NOW())";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':barber_id', $barberoId, PDO::PARAM_INT);
            $stmt->bindValue(':status', $status);
            $stmt->bindValue(':trial_start_date', $trialStart);
            $stmt->bindValue(':trial_end_date', $trialEnd);
            $stmt->bindValue(':trial_used', $trialUsed, PDO::PARAM_INT);
            $stmt->execute();
            return true;
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('No se pudo inicializar monetizacion del barbero: ' . $e->getMessage(), __FILE__, __LINE__);
            }
            return false;
        }
    }

    public function initializeAllBarberProfiles(): void {
        if (!$this->hasTable('barber_monetization_profiles') || !$this->hasTable('barberos')) {
            return;
        }

        try {
            $stmt = $this->conn->query("SELECT id FROM barberos");
            $barbers = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($barbers as $barber) {
                $barberoId = (int) ($barber['id'] ?? 0);
                if ($barberoId > 0) {
                    $this->initializeBarberProfile($barberoId);
                }
            }
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('No se pudieron inicializar perfiles monetizables existentes: ' . $e->getMessage(), __FILE__, __LINE__);
            }
        }
    }

    public function refreshBarberStatuses(): void {
        if (!$this->hasTable('barber_monetization_profiles')) {
            return;
        }

        try {
            if ($this->hasTable('barber_subscriptions')) {
                $this->conn->exec("UPDATE barber_subscriptions
                                   SET status = 'expired', updated_at = NOW()
                                   WHERE status = 'active'
                                     AND ends_at IS NOT NULL
                                     AND ends_at < NOW()");

                $this->conn->exec("UPDATE barber_monetization_profiles bmp
                                   JOIN (
                                        SELECT barber_id, MAX(ends_at) AS latest_end
                                        FROM barber_subscriptions
                                        WHERE status = 'active'
                                        GROUP BY barber_id
                                   ) bs ON bs.barber_id = bmp.barber_id
                                   SET bmp.status = 'active',
                                       bmp.subscription_ends_at = bs.latest_end,
                                       bmp.subscription_started_at = COALESCE(bmp.subscription_started_at, NOW()),
                                       bmp.cancelled_at = NULL,
                                       bmp.updated_at = NOW()");
            }

            $this->conn->exec("UPDATE barber_monetization_profiles
                               SET status = 'expired', updated_at = NOW()
                               WHERE status = 'trial'
                                 AND trial_end_date IS NOT NULL
                                 AND trial_end_date < NOW()");

            $this->conn->exec("UPDATE barber_monetization_profiles
                               SET status = 'expired', updated_at = NOW()
                               WHERE status = 'active'
                                 AND subscription_ends_at IS NOT NULL
                                 AND subscription_ends_at < NOW()");
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('No se pudieron refrescar estados de trial: ' . $e->getMessage(), __FILE__, __LINE__);
            }
        }
    }

    public function refreshBarberStatus(int $barberoId): void {
        if ($barberoId <= 0 || !$this->hasTable('barber_monetization_profiles')) {
            return;
        }

        try {
            if ($this->hasTable('barber_subscriptions')) {
                $expireSubscriptions = $this->conn->prepare("UPDATE barber_subscriptions
                                          SET status = 'expired', updated_at = NOW()
                                          WHERE barber_id = :barber_id
                                            AND status = 'active'
                                            AND ends_at IS NOT NULL
                                            AND ends_at < NOW()");
                $expireSubscriptions->bindValue(':barber_id', $barberoId, PDO::PARAM_INT);
                $expireSubscriptions->execute();

                $activeSubscription = $this->conn->prepare("SELECT starts_at, ends_at
                    FROM barber_subscriptions
                    WHERE barber_id = :barber_id
                      AND status = 'active'
                    ORDER BY ends_at DESC, id DESC
                    LIMIT 1");
                $activeSubscription->bindValue(':barber_id', $barberoId, PDO::PARAM_INT);
                $activeSubscription->execute();
                $activeRow = $activeSubscription->fetch(PDO::FETCH_ASSOC) ?: null;

                if ($activeRow) {
                    $activate = $this->conn->prepare("UPDATE barber_monetization_profiles
                        SET status = 'active',
                            subscription_started_at = :started_at,
                            subscription_ends_at = :ends_at,
                            cancelled_at = NULL,
                            updated_at = NOW()
                        WHERE barber_id = :barber_id");
                    $activate->bindValue(':started_at', $activeRow['starts_at']);
                    $activate->bindValue(':ends_at', $activeRow['ends_at']);
                    $activate->bindValue(':barber_id', $barberoId, PDO::PARAM_INT);
                    $activate->execute();
                    return;
                }
            }

            $stmt = $this->conn->prepare("UPDATE barber_monetization_profiles
                                          SET status = 'expired', updated_at = NOW()
                                          WHERE barber_id = :barber_id
                                            AND status = 'trial'
                                            AND trial_end_date IS NOT NULL
                                            AND trial_end_date < NOW()");
            $stmt->bindValue(':barber_id', $barberoId, PDO::PARAM_INT);
            $stmt->execute();

            $stmt2 = $this->conn->prepare("UPDATE barber_monetization_profiles
                                           SET status = 'expired', updated_at = NOW()
                                           WHERE barber_id = :barber_id
                                             AND status = 'active'
                                             AND subscription_ends_at IS NOT NULL
                                             AND subscription_ends_at < NOW()");
            $stmt2->bindValue(':barber_id', $barberoId, PDO::PARAM_INT);
            $stmt2->execute();
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('No se pudo refrescar estado de un barbero: ' . $e->getMessage(), __FILE__, __LINE__);
            }
        }
    }

    public function getBarberProfile(int $barberoId): array {
        $defaults = [
            'exists' => false,
            'status' => 'free',
            'trial_start_date' => null,
            'trial_end_date' => null,
            'trial_used' => 0,
            'subscription_started_at' => null,
            'subscription_ends_at' => null,
            'cancelled_at' => null,
            'plan_type' => null,
            'latest_subscription_status' => null,
            'subscription_amount' => 0.0,
            'monetization_enabled' => $this->settings->getBool('monetization_enabled', false),
            'subscription_enabled' => $this->settings->getBool('barber_subscription_enabled', false),
            'free_mode_enabled' => $this->settings->getBool('barber_free_mode_enabled', true),
            'trial_enabled' => $this->settings->getBool('barber_trial_enabled', false),
            'monthly_price' => $this->settings->getFloat('barber_subscription_monthly_price', 0.0),
            'annual_price' => $this->settings->getFloat('barber_subscription_annual_price', 0.0),
            'commission_enabled' => $this->settings->getBool('barber_commission_enabled', false),
            'commission_percentage' => $this->settings->getFloat('barber_commission_percentage', 0.0),
            'can_accept_services' => true,
            'restriction_reason' => '',
        ];

        if ($barberoId <= 0 || !$this->hasTable('barber_monetization_profiles')) {
            return $defaults;
        }

        $this->initializeBarberProfile($barberoId);
        $this->refreshBarberStatus($barberoId);

        try {
            $stmt = $this->conn->prepare("SELECT *
                FROM barber_monetization_profiles
                WHERE barber_id = :barber_id
                LIMIT 1");
            $stmt->bindValue(':barber_id', $barberoId, PDO::PARAM_INT);
            $stmt->execute();
            $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $subscriptionData = [];
            if ($this->hasTable('barber_subscriptions')) {
                $subStmt = $this->conn->prepare("SELECT plan_type, status, amount, starts_at, ends_at
                    FROM barber_subscriptions
                    WHERE barber_id = :barber_id
                    ORDER BY created_at DESC, id DESC
                    LIMIT 1");
                $subStmt->bindValue(':barber_id', $barberoId, PDO::PARAM_INT);
                $subStmt->execute();
                $subscriptionData = $subStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            }

            $result = array_merge($defaults, $profile, [
                'exists' => !empty($profile),
                'plan_type' => $subscriptionData['plan_type'] ?? null,
                'latest_subscription_status' => $subscriptionData['status'] ?? null,
                'subscription_amount' => (float) ($subscriptionData['amount'] ?? 0),
            ]);

            $gate = $this->evaluateAccessPolicy($result);
            $result['can_accept_services'] = $gate['allowed'];
            $result['restriction_reason'] = $gate['reason'];

            return $result;
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('No se pudo obtener el perfil monetizable: ' . $e->getMessage(), __FILE__, __LINE__);
            }
            return $defaults;
        }
    }

    public function canBarberAcceptServices(int $barberoId, ?string &$reason = null): bool {
        $profile = $this->getBarberProfile($barberoId);
        $reason = $profile['restriction_reason'] ?? '';
        return !empty($profile['can_accept_services']);
    }

    public function activateSubscription(int $barberoId, string $planType, ?string $startAt = null): array {
        if ($barberoId <= 0) {
            return ['success' => false, 'message' => 'Barbero invalido.'];
        }

        if (!$this->hasTable('barber_subscriptions') || !$this->hasTable('barber_monetization_profiles')) {
            return ['success' => false, 'message' => 'La migracion de monetizacion no esta disponible.'];
        }

        if (!in_array($planType, ['monthly', 'annual'], true)) {
            return ['success' => false, 'message' => 'Plan de suscripcion invalido.'];
        }

        $priceKey = $planType === 'annual' ? 'barber_subscription_annual_price' : 'barber_subscription_monthly_price';
        $amount = max(0, $this->settings->getFloat($priceKey, 0.0));
        $start = $startAt ?: date('Y-m-d H:i:s');
        $end = date('Y-m-d H:i:s', strtotime($planType === 'annual' ? '+1 year' : '+1 month', strtotime($start)));

        try {
            $this->initializeBarberProfile($barberoId);
            $this->conn->beginTransaction();

            $expireCurrent = $this->conn->prepare("UPDATE barber_subscriptions
                SET status = 'expired', updated_at = NOW()
                WHERE barber_id = :barber_id
                  AND status = 'active'");
            $expireCurrent->bindValue(':barber_id', $barberoId, PDO::PARAM_INT);
            $expireCurrent->execute();

            $insertSubscription = $this->conn->prepare("INSERT INTO barber_subscriptions
                (barber_id, plan_type, amount, status, starts_at, ends_at, created_at, updated_at)
                VALUES
                (:barber_id, :plan_type, :amount, 'active', :starts_at, :ends_at, NOW(), NOW())");
            $insertSubscription->bindValue(':barber_id', $barberoId, PDO::PARAM_INT);
            $insertSubscription->bindValue(':plan_type', $planType);
            $insertSubscription->bindValue(':amount', $amount);
            $insertSubscription->bindValue(':starts_at', $start);
            $insertSubscription->bindValue(':ends_at', $end);
            $insertSubscription->execute();

            $updateProfile = $this->conn->prepare("UPDATE barber_monetization_profiles
                SET status = 'active',
                    subscription_started_at = :starts_at,
                    subscription_ends_at = :ends_at,
                    cancelled_at = NULL,
                    updated_at = NOW()
                WHERE barber_id = :barber_id");
            $updateProfile->bindValue(':starts_at', $start);
            $updateProfile->bindValue(':ends_at', $end);
            $updateProfile->bindValue(':barber_id', $barberoId, PDO::PARAM_INT);
            $updateProfile->execute();

            $this->conn->commit();
            return ['success' => true, 'message' => 'Suscripcion activada correctamente.'];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            if (function_exists('logError')) {
                logError('No se pudo activar la suscripcion: ' . $e->getMessage(), __FILE__, __LINE__);
            }
            return ['success' => false, 'message' => 'No se pudo activar la suscripcion.'];
        }
    }

    public function cancelActiveSubscription(int $barberoId): array {
        if ($barberoId <= 0) {
            return ['success' => false, 'message' => 'Barbero invalido.'];
        }

        if (!$this->hasTable('barber_subscriptions') || !$this->hasTable('barber_monetization_profiles')) {
            return ['success' => false, 'message' => 'La migracion de monetizacion no esta disponible.'];
        }

        try {
            $this->conn->beginTransaction();

            $cancelSubscription = $this->conn->prepare("UPDATE barber_subscriptions
                SET status = 'cancelled', updated_at = NOW()
                WHERE barber_id = :barber_id
                  AND status = 'active'");
            $cancelSubscription->bindValue(':barber_id', $barberoId, PDO::PARAM_INT);
            $cancelSubscription->execute();

            $profile = $this->getBarberProfile($barberoId);
            $newStatus = !empty($profile['free_mode_enabled']) ? 'free' : 'cancelled';

            $cancelProfile = $this->conn->prepare("UPDATE barber_monetization_profiles
                SET status = :status,
                    cancelled_at = NOW(),
                    updated_at = NOW()
                WHERE barber_id = :barber_id");
            $cancelProfile->bindValue(':status', $newStatus);
            $cancelProfile->bindValue(':barber_id', $barberoId, PDO::PARAM_INT);
            $cancelProfile->execute();

            $this->conn->commit();
            return ['success' => true, 'message' => 'Suscripcion cancelada correctamente.'];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            if (function_exists('logError')) {
                logError('No se pudo cancelar la suscripcion: ' . $e->getMessage(), __FILE__, __LINE__);
            }
            return ['success' => false, 'message' => 'No se pudo cancelar la suscripcion.'];
        }
    }

    public function getBarberCommissionSummary(int $barberoId): array {
        $summary = [
            'services_total' => 0,
            'service_amount_total' => 0.0,
            'commission_total' => 0.0,
            'barber_amount_total' => 0.0,
            'commission_month' => 0.0,
        ];

        if ($barberoId <= 0 || !$this->hasTable('service_commissions')) {
            return $summary;
        }

        try {
            $stmt = $this->conn->prepare("SELECT
                    COUNT(*) AS services_total,
                    COALESCE(SUM(service_amount), 0) AS service_amount_total,
                    COALESCE(SUM(commission_amount), 0) AS commission_total,
                    COALESCE(SUM(barber_amount), 0) AS barber_amount_total,
                    COALESCE(SUM(CASE WHEN YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE()) THEN commission_amount ELSE 0 END), 0) AS commission_month
                FROM service_commissions
                WHERE barber_id = :barber_id");
            $stmt->bindValue(':barber_id', $barberoId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            return [
                'services_total' => (int) ($row['services_total'] ?? 0),
                'service_amount_total' => (float) ($row['service_amount_total'] ?? 0),
                'commission_total' => (float) ($row['commission_total'] ?? 0),
                'barber_amount_total' => (float) ($row['barber_amount_total'] ?? 0),
                'commission_month' => (float) ($row['commission_month'] ?? 0),
            ];
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('No se pudo obtener resumen de comisiones: ' . $e->getMessage(), __FILE__, __LINE__);
            }
            return $summary;
        }
    }

    public function registerCompletedService(int $serviceId): bool {
        if ($serviceId <= 0) {
            return false;
        }

        if (!$this->hasTable('service_commissions') || !$this->hasTable('service_payment_states')) {
            return false;
        }

        try {
            $existing = $this->conn->prepare("SELECT id FROM service_commissions WHERE service_id = :service_id LIMIT 1");
            $existing->bindValue(':service_id', $serviceId, PDO::PARAM_INT);
            $existing->execute();
            if ($existing->fetch(PDO::FETCH_ASSOC)) {
                return true;
            }

            $service = $this->getServiceFinancialContext($serviceId);
            if (!$service || ($service['estado'] ?? '') !== 'completado') {
                return false;
            }

            $barberoId = (int) ($service['barbero_id'] ?? 0);
            if ($barberoId > 0) {
                $this->initializeBarberProfile($barberoId);
                $this->refreshBarberStatus($barberoId);
            }

            $grossAmount = (float) ($service['precio_total'] ?? 0);
            $monetizationEnabled = $this->settings->getBool('monetization_enabled', false);
            $commissionEnabled = $this->settings->getBool('barber_commission_enabled', false);
            $appliedPercentage = ($monetizationEnabled && $commissionEnabled) ? max(0, $this->settings->getFloat('barber_commission_percentage', 0.0)) : 0.0;
            $cap = trim($this->settings->get('barber_commission_cap', ''));
            $commissionAmount = round($grossAmount * ($appliedPercentage / 100), 2);
            if ($cap !== '') {
                $commissionAmount = min($commissionAmount, (float) $cap);
            }
            $commissionAmount = max(0, $commissionAmount);
            $platformAmount = $commissionAmount;
            $barberAmount = round(max(0, $grossAmount - $platformAmount), 2);

            $this->conn->beginTransaction();

            $insertCommission = $this->conn->prepare(
                "INSERT INTO service_commissions
                (service_id, barber_id, cliente_id, service_amount, applied_percentage, commission_amount, platform_amount, barber_amount, currency, monetization_enabled_snapshot, commission_enabled_snapshot, created_at, updated_at)
                VALUES
                (:service_id, :barber_id, :cliente_id, :service_amount, :applied_percentage, :commission_amount, :platform_amount, :barber_amount, 'USD', :monetization_enabled_snapshot, :commission_enabled_snapshot, NOW(), NOW())"
            );
            $insertCommission->bindValue(':service_id', $serviceId, PDO::PARAM_INT);
            $insertCommission->bindValue(':barber_id', $barberoId ?: null);
            $insertCommission->bindValue(':cliente_id', (int) ($service['cliente_id'] ?? 0), PDO::PARAM_INT);
            $insertCommission->bindValue(':service_amount', $grossAmount);
            $insertCommission->bindValue(':applied_percentage', $appliedPercentage);
            $insertCommission->bindValue(':commission_amount', $commissionAmount);
            $insertCommission->bindValue(':platform_amount', $platformAmount);
            $insertCommission->bindValue(':barber_amount', $barberAmount);
            $insertCommission->bindValue(':monetization_enabled_snapshot', $monetizationEnabled ? 1 : 0, PDO::PARAM_INT);
            $insertCommission->bindValue(':commission_enabled_snapshot', $commissionEnabled ? 1 : 0, PDO::PARAM_INT);
            $insertCommission->execute();

            $paymentStatus = $this->settings->get('future_default_payment_status', 'pending');
            if (!in_array($paymentStatus, ['pending', 'held', 'released', 'refunded', 'disputed'], true)) {
                $paymentStatus = 'pending';
            }

            $insertPaymentState = $this->conn->prepare(
                "INSERT INTO service_payment_states
                (service_id, payment_status, gross_amount, held_amount, released_amount, refunded_amount, disputed_amount, currency, notes, created_at, updated_at)
                VALUES
                (:service_id, :payment_status, :gross_amount, 0, 0, 0, 0, 'USD', :notes, NOW(), NOW())
                ON DUPLICATE KEY UPDATE updated_at = NOW()"
            );
            $insertPaymentState->bindValue(':service_id', $serviceId, PDO::PARAM_INT);
            $insertPaymentState->bindValue(':payment_status', $paymentStatus);
            $insertPaymentState->bindValue(':gross_amount', $grossAmount);
            $insertPaymentState->bindValue(':notes', 'Base preparada para futura retencion/liberacion de pagos');
            $insertPaymentState->execute();

            $this->conn->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            if (function_exists('logError')) {
                logError('No se pudo registrar la monetizacion del servicio: ' . $e->getMessage(), __FILE__, __LINE__);
            }
            return false;
        }
    }

    public function hasTable(string $table): bool {
        if (array_key_exists($table, $this->tableCache)) {
            return $this->tableCache[$table];
        }

        try {
            $stmt = $this->conn->query("SHOW TABLES LIKE " . $this->conn->quote($table));
            $this->tableCache[$table] = $stmt && $stmt->fetchColumn() !== false;
        } catch (Throwable $e) {
            $this->tableCache[$table] = false;
        }

        return $this->tableCache[$table];
    }

    private function getServiceFinancialContext(int $serviceId): ?array {
        $sql = "SELECT id, cliente_id, barbero_id, estado, COALESCE(precio_total, 0) AS precio_total
                FROM servicios
                WHERE id = :service_id
                LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':service_id', $serviceId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    private function evaluateAccessPolicy(array $profile): array {
        $monetizationEnabled = !empty($profile['monetization_enabled']);
        $subscriptionEnabled = !empty($profile['subscription_enabled']);
        $freeModeEnabled = !empty($profile['free_mode_enabled']);
        $status = (string) ($profile['status'] ?? 'free');

        if (!$monetizationEnabled || !$subscriptionEnabled) {
            return ['allowed' => true, 'reason' => ''];
        }

        if (in_array($status, ['active', 'trial'], true)) {
            return ['allowed' => true, 'reason' => ''];
        }

        if ($status === 'free' && $freeModeEnabled) {
            return ['allowed' => true, 'reason' => ''];
        }

        return [
            'allowed' => false,
            'reason' => 'Tu cuenta necesita una suscripcion activa o un periodo trial vigente para aceptar nuevos servicios.',
        ];
    }
}
