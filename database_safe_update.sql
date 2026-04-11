CREATE DATABASE IF NOT EXISTS `cuts_styles_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `cuts_styles_db`;

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `nombre` VARCHAR(150) NOT NULL,
  `telefono` VARCHAR(30) DEFAULT NULL,
  `direccion` VARCHAR(255) DEFAULT NULL,
  `rol` ENUM('admin','cliente','barbero') NOT NULL DEFAULT 'cliente',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `clientes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `puntos` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_clientes_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `barberos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `especialidad` VARCHAR(120) DEFAULT NULL,
  `experiencia` INT NOT NULL DEFAULT 0,
  `descripcion` TEXT DEFAULT NULL,
  `tarifa_hora` DECIMAL(10,2) DEFAULT NULL,
  `verificacion_status` ENUM('pendiente','en_revision','verificado','rechazado') NOT NULL DEFAULT 'pendiente',
  `comentario_verificacion` TEXT DEFAULT NULL,
  `is_available` TINYINT(1) NOT NULL DEFAULT 1,
  `calificacion_promedio` DECIMAL(3,2) DEFAULT 0.00,
  `total_servicios` INT NOT NULL DEFAULT 0,
  `latitud` DECIMAL(10,7) DEFAULT NULL,
  `longitud` DECIMAL(10,7) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_barberos_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `servicios` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cliente_id` INT UNSIGNED NOT NULL,
  `barbero_id` INT UNSIGNED DEFAULT NULL,
  `tipo` VARCHAR(120) NOT NULL,
  `notas` TEXT DEFAULT NULL,
  `estado` ENUM('pendiente','aceptado','en_proceso','completado','cancelado') NOT NULL DEFAULT 'pendiente',
  `fecha_solicitud` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_aceptacion` DATETIME DEFAULT NULL,
  `fecha_fin` DATETIME DEFAULT NULL,
  `tiempo_estimado` INT DEFAULT NULL,
  `tiempo_real` INT DEFAULT NULL,
  `comentario_barbero` TEXT DEFAULT NULL,
  `calificacion` TINYINT UNSIGNED DEFAULT NULL,
  `precio_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `productos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(150) NOT NULL,
  `descripcion` TEXT DEFAULT NULL,
  `precio` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `descuento` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `en_oferta` TINYINT(1) NOT NULL DEFAULT 0,
  `stock` INT NOT NULL DEFAULT 0,
  `categoria` VARCHAR(100) DEFAULT NULL,
  `destacado` TINYINT(1) NOT NULL DEFAULT 0,
  `estado` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `producto_imagenes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `producto_id` INT UNSIGNED NOT NULL,
  `imagen_url` VARCHAR(255) NOT NULL,
  `orden` INT NOT NULL DEFAULT 1,
  `es_principal` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pedidos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cliente_id` INT UNSIGNED NOT NULL,
  `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `direccion_entrega` VARCHAR(255) NOT NULL,
  `metodo_pago` ENUM('mercadopago','efectivo','transferencia') NOT NULL DEFAULT 'efectivo',
  `estado` ENUM('pendiente','pagado','cancelado') NOT NULL DEFAULT 'pendiente',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pedido_detalles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pedido_id` INT UNSIGNED NOT NULL,
  `producto_id` INT UNSIGNED NOT NULL,
  `cantidad` INT NOT NULL DEFAULT 1,
  `precio_unitario` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `documentos_verificacion` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `barbero_id` INT UNSIGNED NOT NULL,
  `tipo` ENUM('cedula','selfie','certificado','otro') NOT NULL,
  `ruta_archivo` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `recompensas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(150) NOT NULL,
  `descripcion` TEXT DEFAULT NULL,
  `puntos_requeridos` INT NOT NULL,
  `stock` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `canjes_recompensas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cliente_id` INT UNSIGNED NOT NULL,
  `recompensa_id` INT UNSIGNED NOT NULL,
  `puntos_usados` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `puntos_historial` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cliente_id` INT UNSIGNED NOT NULL,
  `servicio_id` INT UNSIGNED DEFAULT NULL,
  `puntos` INT NOT NULL,
  `tipo` ENUM('ganado','canjeado','ajuste') NOT NULL DEFAULT 'ganado',
  `descripcion` VARCHAR(255) DEFAULT NULL,
  `fecha` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP PROCEDURE IF EXISTS `add_column_if_missing`;
DELIMITER $$
CREATE PROCEDURE `add_column_if_missing`(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND COLUMN_NAME = p_column
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$
DELIMITER ;

CALL `add_column_if_missing`('users', 'telefono', 'VARCHAR(30) DEFAULT NULL');
CALL `add_column_if_missing`('users', 'direccion', 'VARCHAR(255) DEFAULT NULL');
CALL `add_column_if_missing`('users', 'rol', 'ENUM(''admin'',''cliente'',''barbero'') NOT NULL DEFAULT ''cliente''');
CALL `add_column_if_missing`('users', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
CALL `add_column_if_missing`('users', 'last_login', 'DATETIME DEFAULT NULL');
CALL `add_column_if_missing`('users', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
CALL `add_column_if_missing`('users', 'updated_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

CALL `add_column_if_missing`('clientes', 'puntos', 'INT NOT NULL DEFAULT 0');
CALL `add_column_if_missing`('clientes', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
CALL `add_column_if_missing`('clientes', 'updated_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

CALL `add_column_if_missing`('barberos', 'especialidad', 'VARCHAR(120) DEFAULT NULL');
CALL `add_column_if_missing`('barberos', 'experiencia', 'INT NOT NULL DEFAULT 0');
CALL `add_column_if_missing`('barberos', 'descripcion', 'TEXT DEFAULT NULL');
CALL `add_column_if_missing`('barberos', 'tarifa_hora', 'DECIMAL(10,2) DEFAULT NULL');
CALL `add_column_if_missing`('barberos', 'verificacion_status', 'ENUM(''pendiente'',''en_revision'',''verificado'',''rechazado'') NOT NULL DEFAULT ''pendiente''');
CALL `add_column_if_missing`('barberos', 'comentario_verificacion', 'TEXT DEFAULT NULL');
CALL `add_column_if_missing`('barberos', 'is_available', 'TINYINT(1) NOT NULL DEFAULT 1');
CALL `add_column_if_missing`('barberos', 'calificacion_promedio', 'DECIMAL(3,2) DEFAULT 0.00');
CALL `add_column_if_missing`('barberos', 'total_servicios', 'INT NOT NULL DEFAULT 0');
CALL `add_column_if_missing`('barberos', 'latitud', 'DECIMAL(10,7) DEFAULT NULL');
CALL `add_column_if_missing`('barberos', 'longitud', 'DECIMAL(10,7) DEFAULT NULL');
CALL `add_column_if_missing`('barberos', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
CALL `add_column_if_missing`('barberos', 'updated_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

CALL `add_column_if_missing`('servicios', 'barbero_id', 'INT UNSIGNED DEFAULT NULL');
CALL `add_column_if_missing`('servicios', 'notas', 'TEXT DEFAULT NULL');
CALL `add_column_if_missing`('servicios', 'estado', 'ENUM(''pendiente'',''aceptado'',''en_proceso'',''completado'',''cancelado'') NOT NULL DEFAULT ''pendiente''');
CALL `add_column_if_missing`('servicios', 'fecha_solicitud', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
CALL `add_column_if_missing`('servicios', 'fecha_aceptacion', 'DATETIME DEFAULT NULL');
CALL `add_column_if_missing`('servicios', 'fecha_fin', 'DATETIME DEFAULT NULL');
CALL `add_column_if_missing`('servicios', 'tiempo_estimado', 'INT DEFAULT NULL');
CALL `add_column_if_missing`('servicios', 'tiempo_real', 'INT DEFAULT NULL');
CALL `add_column_if_missing`('servicios', 'comentario_barbero', 'TEXT DEFAULT NULL');
CALL `add_column_if_missing`('servicios', 'calificacion', 'TINYINT UNSIGNED DEFAULT NULL');
CALL `add_column_if_missing`('servicios', 'precio_total', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');

CALL `add_column_if_missing`('productos', 'descuento', 'DECIMAL(5,2) NOT NULL DEFAULT 0.00');
CALL `add_column_if_missing`('productos', 'en_oferta', 'TINYINT(1) NOT NULL DEFAULT 0');
CALL `add_column_if_missing`('productos', 'categoria', 'VARCHAR(100) DEFAULT NULL');
CALL `add_column_if_missing`('productos', 'destacado', 'TINYINT(1) NOT NULL DEFAULT 0');
CALL `add_column_if_missing`('productos', 'estado', 'ENUM(''activo'',''inactivo'') NOT NULL DEFAULT ''activo''');
CALL `add_column_if_missing`('productos', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
CALL `add_column_if_missing`('productos', 'updated_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

CALL `add_column_if_missing`('producto_imagenes', 'orden', 'INT NOT NULL DEFAULT 1');
CALL `add_column_if_missing`('producto_imagenes', 'es_principal', 'TINYINT(1) NOT NULL DEFAULT 0');
CALL `add_column_if_missing`('producto_imagenes', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');

CALL `add_column_if_missing`('pedidos', 'direccion_entrega', 'VARCHAR(255) NOT NULL DEFAULT ''''');
CALL `add_column_if_missing`('pedidos', 'metodo_pago', 'ENUM(''mercadopago'',''efectivo'',''transferencia'') NOT NULL DEFAULT ''efectivo''');
CALL `add_column_if_missing`('pedidos', 'estado', 'ENUM(''pendiente'',''pagado'',''cancelado'') NOT NULL DEFAULT ''pendiente''');
CALL `add_column_if_missing`('pedidos', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
CALL `add_column_if_missing`('pedidos', 'updated_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

CALL `add_column_if_missing`('pedido_detalles', 'cantidad', 'INT NOT NULL DEFAULT 1');
CALL `add_column_if_missing`('pedido_detalles', 'precio_unitario', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');

CALL `add_column_if_missing`('documentos_verificacion', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
CALL `add_column_if_missing`('recompensas', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
CALL `add_column_if_missing`('recompensas', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
CALL `add_column_if_missing`('recompensas', 'updated_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
CALL `add_column_if_missing`('canjes_recompensas', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
CALL `add_column_if_missing`('puntos_historial', 'tipo', 'ENUM(''ganado'',''canjeado'',''ajuste'') NOT NULL DEFAULT ''ganado''');
CALL `add_column_if_missing`('puntos_historial', 'descripcion', 'VARCHAR(255) DEFAULT NULL');
CALL `add_column_if_missing`('puntos_historial', 'fecha', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');

DROP PROCEDURE IF EXISTS `add_column_if_missing`;

UPDATE `users` SET `rol` = 'admin' WHERE `email` = 'admin@cutsstyles.com' AND (`rol` IS NULL OR `rol` = '');
UPDATE `users` SET `rol` = 'cliente' WHERE `email` = 'cliente@test.com' AND (`rol` IS NULL OR `rol` = '');
UPDATE `users` SET `rol` = 'barbero' WHERE `email` = 'barbero@test.com' AND (`rol` IS NULL OR `rol` = '');
UPDATE `users` SET `is_active` = 1 WHERE `is_active` IS NULL;

INSERT INTO `users` (`email`, `password_hash`, `nombre`, `telefono`, `direccion`, `rol`, `is_active`)
SELECT * FROM (
  SELECT 'admin@cutsstyles.com', '$2y$10$4w.UoBYiTsN.6jW2zAN/te/cyv.NCaXwyUf9qEft.hCrbVpipFS7y', 'Super Admin', '3000000000', 'Panel Administrativo', 'admin', 1
) AS tmp
WHERE NOT EXISTS (
  SELECT 1 FROM `users` WHERE `email` = 'admin@cutsstyles.com'
);

INSERT INTO `users` (`email`, `password_hash`, `nombre`, `telefono`, `direccion`, `rol`, `is_active`)
SELECT * FROM (
  SELECT 'cliente@test.com', '$2y$10$nbWEd5zRZL68etg2gSrv8e3HQF0vaXy5ACsAjJaLNjp/Vp6jxpLq6', 'Cliente Demo', '3001111111', 'Calle 123 #45-67', 'cliente', 1
) AS tmp
WHERE NOT EXISTS (
  SELECT 1 FROM `users` WHERE `email` = 'cliente@test.com'
);

INSERT INTO `users` (`email`, `password_hash`, `nombre`, `telefono`, `direccion`, `rol`, `is_active`)
SELECT * FROM (
  SELECT 'barbero@test.com', '$2y$10$GJxH1B3huIJiLrH3CNgrDuitzbNaczPs3MEbkfI0HtQFmAKnEUilq', 'Barbero Demo', '3002222222', 'Cra 10 #20-30', 'barbero', 1
) AS tmp
WHERE NOT EXISTS (
  SELECT 1 FROM `users` WHERE `email` = 'barbero@test.com'
);

INSERT INTO `clientes` (`user_id`, `puntos`)
SELECT `u`.`id`, 120
FROM `users` `u`
LEFT JOIN `clientes` `c` ON `c`.`user_id` = `u`.`id`
WHERE `u`.`email` = 'cliente@test.com'
  AND `c`.`id` IS NULL;

INSERT INTO `barberos` (
  `user_id`, `especialidad`, `experiencia`, `descripcion`, `tarifa_hora`,
  `verificacion_status`, `comentario_verificacion`, `is_available`, `calificacion_promedio`, `total_servicios`
)
SELECT `u`.`id`, 'Fade y barba', 5, 'Barbero de prueba para el panel administrativo.', 25.00,
       'verificado', 'Aprobado por el sistema', 1, 4.80, 12
FROM `users` `u`
LEFT JOIN `barberos` `b` ON `b`.`user_id` = `u`.`id`
WHERE `u`.`email` = 'barbero@test.com'
  AND `b`.`id` IS NULL;

INSERT INTO `recompensas` (`nombre`, `descripcion`, `puntos_requeridos`, `stock`, `is_active`)
SELECT * FROM (
  SELECT 'Descuento 10%', 'Descuento del 10% en tu proximo servicio.', 50, 100, 1
) AS tmp
WHERE NOT EXISTS (
  SELECT 1 FROM `recompensas` WHERE `nombre` = 'Descuento 10%'
);

INSERT INTO `recompensas` (`nombre`, `descripcion`, `puntos_requeridos`, `stock`, `is_active`)
SELECT * FROM (
  SELECT 'Corte Gratis', 'Canjea un corte de cabello sin costo.', 150, 20, 1
) AS tmp
WHERE NOT EXISTS (
  SELECT 1 FROM `recompensas` WHERE `nombre` = 'Corte Gratis'
);

INSERT INTO `recompensas` (`nombre`, `descripcion`, `puntos_requeridos`, `stock`, `is_active`)
SELECT * FROM (
  SELECT 'Producto Gratis', 'Llevate un producto de la tienda.', 200, 10, 1
) AS tmp
WHERE NOT EXISTS (
  SELECT 1 FROM `recompensas` WHERE `nombre` = 'Producto Gratis'
);

