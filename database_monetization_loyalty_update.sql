-- Extensiones incrementales para monetizacion, trial, comisiones, puntos y base de pagos retenidos.
-- Compatible hacia atras: no reemplaza tablas existentes ni elimina flujo actual.

CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_settings (setting_key, setting_value, updated_at) VALUES
('monetization_enabled', '0', NOW()),
('barber_subscription_enabled', '0', NOW()),
('barber_subscription_monthly_price', '0', NOW()),
('barber_subscription_annual_price', '0', NOW()),
('barber_commission_enabled', '0', NOW()),
('barber_commission_percentage', '0', NOW()),
('barber_commission_cap', '', NOW()),
('barber_free_mode_enabled', '1', NOW()),
('barber_trial_enabled', '0', NOW()),
('barber_trial_days', '15', NOW()),
('loyalty_enabled', '1', NOW()),
('loyalty_points_per_service', '10', NOW()),
('loyalty_points_per_currency', '0', NOW()),
('loyalty_reward_points', '100', NOW()),
('loyalty_reward_value', '10', NOW()),
('future_payment_hold_enabled', '0', NOW()),
('future_default_payment_status', 'pending', NOW())
ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at);

CREATE TABLE IF NOT EXISTS barber_monetization_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barber_id INT(10) UNSIGNED NOT NULL UNIQUE,
    status ENUM('free', 'trial', 'active', 'expired', 'cancelled') NOT NULL DEFAULT 'free',
    trial_start_date DATETIME NULL,
    trial_end_date DATETIME NULL,
    trial_used TINYINT(1) NOT NULL DEFAULT 0,
    subscription_started_at DATETIME NULL,
    subscription_ends_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_barber_monetization_profile_barber FOREIGN KEY (barber_id) REFERENCES barberos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS barber_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barber_id INT(10) UNSIGNED NOT NULL,
    plan_type ENUM('monthly', 'annual') NOT NULL DEFAULT 'monthly',
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('pending', 'active', 'expired', 'cancelled') NOT NULL DEFAULT 'pending',
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_barber_subscription_barber FOREIGN KEY (barber_id) REFERENCES barberos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_commissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT(10) UNSIGNED NOT NULL UNIQUE,
    barber_id INT(10) UNSIGNED NULL,
    cliente_id INT(10) UNSIGNED NULL,
    service_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    applied_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    commission_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    platform_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    barber_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
    monetization_enabled_snapshot TINYINT(1) NOT NULL DEFAULT 0,
    commission_enabled_snapshot TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_service_commission_service FOREIGN KEY (service_id) REFERENCES servicios(id) ON DELETE CASCADE,
    CONSTRAINT fk_service_commission_barber FOREIGN KEY (barber_id) REFERENCES barberos(id) ON DELETE SET NULL,
    CONSTRAINT fk_service_commission_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_payment_states (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT(10) UNSIGNED NOT NULL UNIQUE,
    payment_status ENUM('pending', 'held', 'released', 'refunded', 'disputed') NOT NULL DEFAULT 'pending',
    gross_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    held_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    released_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    refunded_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    disputed_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
    notes VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_service_payment_state_service FOREIGN KEY (service_id) REFERENCES servicios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS puntos_historial (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT(10) UNSIGNED NOT NULL,
    servicio_id INT(10) UNSIGNED NULL,
    recompensa_id INT(10) UNSIGNED NULL,
    tipo_movimiento ENUM('earned', 'redeemed', 'expired') NOT NULL DEFAULT 'earned',
    puntos INT NOT NULL DEFAULT 0,
    descripcion VARCHAR(255) NULL,
    balance_despues INT NULL,
    fecha DATETIME NOT NULL,
    CONSTRAINT fk_puntos_historial_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE puntos_historial
    ADD COLUMN IF NOT EXISTS recompensa_id INT NULL AFTER servicio_id,
    ADD COLUMN IF NOT EXISTS tipo_movimiento ENUM('earned', 'redeemed', 'expired') NOT NULL DEFAULT 'earned' AFTER recompensa_id,
    ADD COLUMN IF NOT EXISTS descripcion VARCHAR(255) NULL AFTER puntos,
    ADD COLUMN IF NOT EXISTS balance_despues INT NULL AFTER descripcion;
