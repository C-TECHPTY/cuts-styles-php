-- Fase 1: configuracion global incremental para nuevas extensiones del sistema.
-- Seguro para bases existentes: no borra datos ni reemplaza tablas actuales.

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
('zone_allow_multi_sector_barber', '1', NOW()),
('notifications_realtime_enabled', '1', NOW()),
('notifications_sound_enabled', '1', NOW()),
('notifications_vibration_enabled', '1', NOW()),
('notifications_zone_request_alerts', '1', NOW()),
('notifications_prepare_push', '0', NOW()),
('trust_reports_enabled', '1', NOW()),
('trust_barber_blocks_enabled', '1', NOW()),
('trust_behavior_score_enabled', '1', NOW()),
('trust_fraud_watch_enabled', '0', NOW()),
('trust_payment_disputes_enabled', '0', NOW()),
('pwa_install_enabled', '1', NOW()),
('pwa_offline_enabled', '1', NOW()),
('ui_mobile_compact_enabled', '1', NOW()),
('admin_config_panels_enabled', '1', NOW()),
('admin_dashboard_show_finance', '1', NOW()),
('admin_dashboard_show_loyalty', '1', NOW()),
('admin_dashboard_show_incidents', '1', NOW())
ON DUPLICATE KEY UPDATE
    setting_value = VALUES(setting_value),
    updated_at = VALUES(updated_at);
