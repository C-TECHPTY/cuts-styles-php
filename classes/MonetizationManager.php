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

    public function refreshBarberStatuses(): void {
        if (!$this->hasTable('barber_monetization_profiles')) {
            return;
        }

        try {
            $this->conn->exec("UPDATE barber_monetization_profiles
                               SET status = 'expired', updated_at = NOW()
                               WHERE status = 'trial'
                                 AND trial_end_date IS NOT NULL
                                 AND trial_end_date < NOW()");
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
            $stmt = $this->conn->prepare("UPDATE barber_monetization_profiles
                                          SET status = 'expired', updated_at = NOW()
                                          WHERE barber_id = :barber_id
                                            AND status = 'trial'
                                            AND trial_end_date IS NOT NULL
                                            AND trial_end_date < NOW()");
            $stmt->bindValue(':barber_id', $barberoId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('No se pudo refrescar estado de un barbero: ' . $e->getMessage(), __FILE__, __LINE__);
            }
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
}
