-- Fase 4: matching por zona y alertas relacionadas.
-- Extension incremental compatible: no reemplaza flujo actual de servicios.

CREATE TABLE IF NOT EXISTS barber_zone_preferences (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    barbero_id INT UNSIGNED NOT NULL,
    zone_name VARCHAR(120) DEFAULT NULL,
    sectors_csv VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_barber_zone_preferences_barbero (barbero_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_zone_assignments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    servicio_id INT UNSIGNED NOT NULL,
    zone_name VARCHAR(120) DEFAULT NULL,
    sector_name VARCHAR(120) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_service_zone_assignments_servicio (servicio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS zone_alert_logs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    barbero_id INT UNSIGNED NOT NULL,
    servicio_id INT UNSIGNED NOT NULL,
    alert_type VARCHAR(60) NOT NULL DEFAULT 'pending_request_in_zone',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_zone_alert_logs_barbero (barbero_id, created_at),
    KEY idx_zone_alert_logs_servicio (servicio_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_settings (setting_key, setting_value, updated_at) VALUES
('zone_matching_enabled', '0', NOW()),
('zone_matching_mode', 'preferred', NOW()),
('zone_require_service_zone', '0', NOW()),
('zone_allow_multi_sector_barber', '1', NOW())
ON DUPLICATE KEY UPDATE
    setting_value = VALUES(setting_value),
    updated_at = VALUES(updated_at);
