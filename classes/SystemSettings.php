<?php
// classes/SystemSettings.php
require_once __DIR__ . '/../config/database.php';

class SystemSettings {
    private PDO $conn;
    private static ?array $cache = null;
    private static ?bool $tableExists = null;

    public const DEFAULTS = [
        'monetization_enabled' => '0',
        'barber_subscription_enabled' => '0',
        'barber_subscription_monthly_price' => '0',
        'barber_subscription_annual_price' => '0',
        'barber_commission_enabled' => '0',
        'barber_commission_percentage' => '0',
        'barber_commission_cap' => '',
        'barber_free_mode_enabled' => '1',
        'barber_trial_enabled' => '0',
        'barber_trial_days' => '15',
        'loyalty_enabled' => '1',
        'loyalty_points_per_service' => '10',
        'loyalty_points_per_currency' => '0',
        'loyalty_reward_points' => '100',
        'loyalty_reward_value' => '10',
        'future_payment_hold_enabled' => '0',
        'future_default_payment_status' => 'pending',
    ];

    public function __construct(?PDO $conn = null) {
        if ($conn instanceof PDO) {
            $this->conn = $conn;
            return;
        }

        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function getAll(): array {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $settings = self::DEFAULTS;
        if (!$this->hasSettingsTable()) {
            self::$cache = $settings;
            return self::$cache;
        }

        try {
            $stmt = $this->conn->query("SELECT setting_key, setting_value FROM system_settings");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $settings[$row['setting_key']] = (string) ($row['setting_value'] ?? '');
            }
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('No se pudieron leer system_settings: ' . $e->getMessage(), __FILE__, __LINE__);
            }
        }

        self::$cache = $settings;
        return self::$cache;
    }

    public function get(string $key, $default = null): string {
        $settings = $this->getAll();
        if (array_key_exists($key, $settings)) {
            return (string) $settings[$key];
        }

        return $default !== null ? (string) $default : '';
    }

    public function getBool(string $key, bool $default = false): bool {
        return filter_var($this->get($key, $default ? '1' : '0'), FILTER_VALIDATE_BOOLEAN);
    }

    public function getInt(string $key, int $default = 0): int {
        return (int) $this->get($key, (string) $default);
    }

    public function getFloat(string $key, float $default = 0.0): float {
        return (float) $this->get($key, (string) $default);
    }

    public function setMany(array $values): bool {
        if (!$this->hasSettingsTable()) {
            return false;
        }

        $allowed = array_keys(self::DEFAULTS);
        $payload = [];
        foreach ($values as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $payload[$key] = is_scalar($value) ? (string) $value : '';
            }
        }

        if ($payload === []) {
            return true;
        }

        try {
            $this->conn->beginTransaction();
            $sql = "INSERT INTO system_settings (setting_key, setting_value, updated_at)
                    VALUES (:setting_key, :setting_value, NOW())
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()";
            $stmt = $this->conn->prepare($sql);

            foreach ($payload as $key => $value) {
                $stmt->bindValue(':setting_key', $key);
                $stmt->bindValue(':setting_value', $value);
                $stmt->execute();
            }

            $this->conn->commit();
            self::$cache = null;
            return true;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            if (function_exists('logError')) {
                logError('No se pudieron guardar system_settings: ' . $e->getMessage(), __FILE__, __LINE__);
            }
            return false;
        }
    }

    public function hasSettingsTable(): bool {
        if (self::$tableExists !== null) {
            return self::$tableExists;
        }

        try {
            $stmt = $this->conn->query("SHOW TABLES LIKE 'system_settings'");
            self::$tableExists = $stmt && $stmt->fetchColumn() !== false;
        } catch (Throwable $e) {
            self::$tableExists = false;
        }

        return self::$tableExists;
    }
}
