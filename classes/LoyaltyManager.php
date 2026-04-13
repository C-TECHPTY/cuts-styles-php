<?php
// classes/LoyaltyManager.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/SystemSettings.php';

class LoyaltyManager {
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

    public function awardCompletedServicePoints(int $serviceId): int {
        if ($serviceId <= 0 || !$this->settings->getBool('loyalty_enabled', true)) {
            return 0;
        }

        if (!$this->hasTable('puntos_historial')) {
            return 0;
        }

        try {
            $existing = $this->conn->prepare("SELECT id FROM puntos_historial WHERE servicio_id = :service_id AND tipo_movimiento = 'earned' LIMIT 1");
            $existing->bindValue(':service_id', $serviceId, PDO::PARAM_INT);
            $existing->execute();
            if ($existing->fetch(PDO::FETCH_ASSOC)) {
                return 0;
            }

            $service = $this->getServiceContext($serviceId);
            if (!$service || ($service['estado'] ?? '') !== 'completado') {
                return 0;
            }

            $basePoints = max(0, $this->settings->getInt('loyalty_points_per_service', 10));
            $pointsPerCurrency = max(0, $this->settings->getFloat('loyalty_points_per_currency', 0));
            $amountPoints = 0;
            if ($pointsPerCurrency > 0) {
                $amountPoints = (int) floor(((float) ($service['precio_total'] ?? 0)) * $pointsPerCurrency);
            }

            $totalPoints = $basePoints + $amountPoints;
            if ($totalPoints <= 0) {
                return 0;
            }

            $this->conn->beginTransaction();

            $update = $this->conn->prepare("UPDATE clientes SET puntos = puntos + :points WHERE id = :cliente_id");
            $update->bindValue(':points', $totalPoints, PDO::PARAM_INT);
            $update->bindValue(':cliente_id', (int) $service['cliente_id'], PDO::PARAM_INT);
            $update->execute();

            $balanceStmt = $this->conn->prepare("SELECT puntos FROM clientes WHERE id = :cliente_id LIMIT 1");
            $balanceStmt->bindValue(':cliente_id', (int) $service['cliente_id'], PDO::PARAM_INT);
            $balanceStmt->execute();
            $balance = (int) ($balanceStmt->fetchColumn() ?: 0);

            $description = sprintf(
                'Puntos por servicio completado%s',
                $amountPoints > 0 ? ' (incluye componente por monto)' : ''
            );

            $history = $this->conn->prepare(
                "INSERT INTO puntos_historial
                (cliente_id, servicio_id, tipo_movimiento, puntos, descripcion, balance_despues, fecha)
                VALUES
                (:cliente_id, :servicio_id, 'earned', :puntos, :descripcion, :balance_despues, NOW())"
            );
            $history->bindValue(':cliente_id', (int) $service['cliente_id'], PDO::PARAM_INT);
            $history->bindValue(':servicio_id', $serviceId, PDO::PARAM_INT);
            $history->bindValue(':puntos', $totalPoints, PDO::PARAM_INT);
            $history->bindValue(':descripcion', $description);
            $history->bindValue(':balance_despues', $balance, PDO::PARAM_INT);
            $history->execute();

            $this->conn->commit();
            return $totalPoints;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            if (function_exists('logError')) {
                logError('No se pudieron asignar puntos: ' . $e->getMessage(), __FILE__, __LINE__);
            }
            return 0;
        }
    }

    public function registerRedeem(int $clienteId, int $recompensaId, int $pointsUsed): bool {
        if ($clienteId <= 0 || $pointsUsed <= 0 || !$this->hasTable('puntos_historial')) {
            return false;
        }

        try {
            $balanceStmt = $this->conn->prepare("SELECT puntos FROM clientes WHERE id = :cliente_id LIMIT 1");
            $balanceStmt->bindValue(':cliente_id', $clienteId, PDO::PARAM_INT);
            $balanceStmt->execute();
            $balance = (int) ($balanceStmt->fetchColumn() ?: 0);

            $stmt = $this->conn->prepare(
                "INSERT INTO puntos_historial
                (cliente_id, recompensa_id, tipo_movimiento, puntos, descripcion, balance_despues, fecha)
                VALUES
                (:cliente_id, :recompensa_id, 'redeemed', :puntos, :descripcion, :balance_despues, NOW())"
            );
            $stmt->bindValue(':cliente_id', $clienteId, PDO::PARAM_INT);
            $stmt->bindValue(':recompensa_id', $recompensaId, PDO::PARAM_INT);
            $stmt->bindValue(':puntos', -1 * abs($pointsUsed), PDO::PARAM_INT);
            $stmt->bindValue(':descripcion', 'Canje de recompensa');
            $stmt->bindValue(':balance_despues', $balance, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('No se pudo registrar canje de puntos: ' . $e->getMessage(), __FILE__, __LINE__);
            }
            return false;
        }
    }

    public function registerManualAdjustment(int $clienteId, int $pointsDelta, string $description = 'Ajuste manual de puntos'): bool {
        if ($clienteId <= 0 || $pointsDelta === 0 || !$this->hasTable('puntos_historial')) {
            return false;
        }

        try {
            $balanceStmt = $this->conn->prepare("SELECT puntos FROM clientes WHERE id = :cliente_id LIMIT 1");
            $balanceStmt->bindValue(':cliente_id', $clienteId, PDO::PARAM_INT);
            $balanceStmt->execute();
            $balance = (int) ($balanceStmt->fetchColumn() ?: 0);

            $stmt = $this->conn->prepare(
                "INSERT INTO puntos_historial
                (cliente_id, tipo_movimiento, puntos, descripcion, balance_despues, fecha)
                VALUES
                (:cliente_id, 'adjustment', :puntos, :descripcion, :balance_despues, NOW())"
            );
            $stmt->bindValue(':cliente_id', $clienteId, PDO::PARAM_INT);
            $stmt->bindValue(':puntos', $pointsDelta, PDO::PARAM_INT);
            $stmt->bindValue(':descripcion', $description);
            $stmt->bindValue(':balance_despues', $balance, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('No se pudo registrar ajuste manual de puntos: ' . $e->getMessage(), __FILE__, __LINE__);
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

    private function getServiceContext(int $serviceId): ?array {
        $stmt = $this->conn->prepare("SELECT id, cliente_id, estado, COALESCE(precio_total, 0) AS precio_total FROM servicios WHERE id = :service_id LIMIT 1");
        $stmt->bindValue(':service_id', $serviceId, PDO::PARAM_INT);
        $stmt->execute();
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
        return $service ?: null;
    }
}
