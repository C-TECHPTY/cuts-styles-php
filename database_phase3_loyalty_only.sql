-- Fase 3: sistema de puntos y fidelizacion para clientes.
-- Compatible con el sistema actual y sin tocar monetizacion, zonas o notificaciones.

CREATE TABLE IF NOT EXISTS recompensas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT DEFAULT NULL,
    puntos_requeridos INT NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS canjes_recompensas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cliente_id INT UNSIGNED NOT NULL,
    recompensa_id INT UNSIGNED NOT NULL,
    puntos_usados INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS puntos_historial (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cliente_id INT UNSIGNED NOT NULL,
    servicio_id INT UNSIGNED DEFAULT NULL,
    recompensa_id INT UNSIGNED DEFAULT NULL,
    tipo_movimiento ENUM('earned','redeemed','expired','adjustment') NOT NULL DEFAULT 'earned',
    puntos INT NOT NULL DEFAULT 0,
    descripcion VARCHAR(255) DEFAULT NULL,
    balance_despues INT DEFAULT NULL,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE puntos_historial
    ADD COLUMN IF NOT EXISTS recompensa_id INT NULL AFTER servicio_id,
    ADD COLUMN IF NOT EXISTS tipo_movimiento ENUM('earned','redeemed','expired','adjustment') NOT NULL DEFAULT 'earned' AFTER recompensa_id,
    ADD COLUMN IF NOT EXISTS descripcion VARCHAR(255) NULL AFTER puntos,
    ADD COLUMN IF NOT EXISTS balance_despues INT NULL AFTER descripcion;
