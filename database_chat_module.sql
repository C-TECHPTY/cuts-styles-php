USE `cuts_styles_db`;

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `service_chats` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `servicio_id` INT UNSIGNED NOT NULL,
  `cliente_id` INT UNSIGNED NOT NULL,
  `barbero_id` INT UNSIGNED NOT NULL,
  `status` ENUM('open','closed','blocked','expired') NOT NULL DEFAULT 'open',
  `allow_free_text` TINYINT(1) NOT NULL DEFAULT 0,
  `opened_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `closed_at` DATETIME DEFAULT NULL,
  `closed_reason` VARCHAR(100) DEFAULT NULL,
  `last_message_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_service_chats_servicio` (`servicio_id`),
  KEY `idx_service_chats_status` (`status`),
  CONSTRAINT `fk_service_chats_servicio`
    FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_service_chats_cliente`
    FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_service_chats_barbero`
    FOREIGN KEY (`barbero_id`) REFERENCES `barberos` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `service_chat_messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `chat_id` INT UNSIGNED NOT NULL,
  `sender_user_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `sender_role` ENUM('cliente','barbero','system') NOT NULL,
  `message_type` ENUM('quick_reply','free_text','system') NOT NULL DEFAULT 'quick_reply',
  `preset_key` VARCHAR(100) DEFAULT NULL,
  `message_text` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_service_chat_messages_chat` (`chat_id`),
  KEY `idx_service_chat_messages_sender` (`sender_user_id`,`created_at`),
  CONSTRAINT `fk_service_chat_messages_chat`
    FOREIGN KEY (`chat_id`) REFERENCES `service_chats` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `service_chat_reports` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `chat_id` INT UNSIGNED NOT NULL,
  `servicio_id` INT UNSIGNED NOT NULL,
  `reporter_user_id` INT UNSIGNED NOT NULL,
  `reporter_role` ENUM('cliente','barbero') NOT NULL,
  `reported_user_id` INT UNSIGNED NOT NULL,
  `reason` VARCHAR(120) NOT NULL,
  `details` TEXT DEFAULT NULL,
  `status` ENUM('open','reviewed','dismissed') NOT NULL DEFAULT 'open',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_service_chat_reports_chat` (`chat_id`),
  KEY `idx_service_chat_reports_servicio` (`servicio_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `barber_client_blocks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `barbero_id` INT UNSIGNED NOT NULL,
  `cliente_id` INT UNSIGNED NOT NULL,
  `reason` VARCHAR(255) DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_barber_client_blocks_pair` (`barbero_id`,`cliente_id`),
  KEY `idx_barber_client_blocks_active` (`active`),
  CONSTRAINT `fk_barber_client_blocks_barbero`
    FOREIGN KEY (`barbero_id`) REFERENCES `barberos` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_barber_client_blocks_cliente`
    FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `client_behavior_flags` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cliente_id` INT UNSIGNED NOT NULL,
  `cancellation_count` INT NOT NULL DEFAULT 0,
  `abusive_reports_count` INT NOT NULL DEFAULT 0,
  `spam_incidents_count` INT NOT NULL DEFAULT 0,
  `score` INT NOT NULL DEFAULT 100,
  `is_chat_restricted` TINYINT(1) NOT NULL DEFAULT 0,
  `is_service_restricted` TINYINT(1) NOT NULL DEFAULT 0,
  `restricted_until` DATETIME DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_client_behavior_flags_cliente` (`cliente_id`),
  CONSTRAINT `fk_client_behavior_flags_cliente`
    FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `client_behavior_events` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cliente_id` INT UNSIGNED NOT NULL,
  `servicio_id` INT UNSIGNED DEFAULT NULL,
  `event_type` VARCHAR(80) NOT NULL,
  `score_delta` INT NOT NULL DEFAULT 0,
  `details` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_client_behavior_events_cliente` (`cliente_id`,`created_at`),
  KEY `idx_client_behavior_events_servicio` (`servicio_id`),
  CONSTRAINT `fk_client_behavior_events_cliente`
    FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_client_behavior_events_servicio`
    FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `service_chat_incidents` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `servicio_id` INT UNSIGNED NOT NULL,
  `chat_id` INT UNSIGNED NOT NULL,
  `actor_user_id` INT UNSIGNED NOT NULL,
  `incident_type` VARCHAR(80) NOT NULL,
  `details` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_service_chat_incidents_servicio` (`servicio_id`,`created_at`),
  KEY `idx_service_chat_incidents_chat` (`chat_id`),
  CONSTRAINT `fk_service_chat_incidents_servicio`
    FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_service_chat_incidents_chat`
    FOREIGN KEY (`chat_id`) REFERENCES `service_chats` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

