-- ============================================
-- CHOPP ON TAP - Database Structure
-- Database: inlaud99_choppontap
-- ============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET FOREIGN_KEY_CHECKS = 0;

-- Limpar tabelas existentes (cuidado: apaga todos os dados!)
DROP TABLE IF EXISTS `order`;
DROP TABLE IF EXISTS `tap`;
DROP TABLE IF EXISTS `user_estabelecimento`;
DROP TABLE IF EXISTS `bebidas`;
DROP TABLE IF EXISTS `payment`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `estabelecimentos`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- Table: estabelecimentos
-- ============================================
CREATE TABLE IF NOT EXISTS `estabelecimentos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `document` VARCHAR(255) NOT NULL,
  `address` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(255) NOT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: users
-- ============================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `type` INT NOT NULL DEFAULT 4,
  `remember_token` VARCHAR(100) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: user_estabelecimento
-- ============================================
CREATE TABLE IF NOT EXISTS `user_estabelecimento` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `estabelecimento_id` BIGINT UNSIGNED NOT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_estabelecimento_user_id_foreign` (`user_id`),
  KEY `user_estabelecimento_estabelecimento_id_foreign` (`estabelecimento_id`),
  CONSTRAINT `user_estabelecimento_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_estabelecimento_estabelecimento_id_foreign` FOREIGN KEY (`estabelecimento_id`) REFERENCES `estabelecimentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: bebidas
-- ============================================
CREATE TABLE IF NOT EXISTS `bebidas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `estabelecimento_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `ibu` VARCHAR(255) NOT NULL,
  `alcool` DOUBLE NOT NULL,
  `brand` VARCHAR(255) NOT NULL,
  `type` VARCHAR(255) NOT NULL,
  `value` DOUBLE NOT NULL,
  `promotional_value` DOUBLE NOT NULL,
  `image` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `bebidas_estabelecimento_id_foreign` (`estabelecimento_id`),
  CONSTRAINT `bebidas_estabelecimento_id_foreign` FOREIGN KEY (`estabelecimento_id`) REFERENCES `estabelecimentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: tap
-- ============================================
CREATE TABLE IF NOT EXISTS `tap` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bebida_id` BIGINT UNSIGNED NOT NULL,
  `estabelecimento_id` BIGINT UNSIGNED NOT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `volume` DOUBLE NOT NULL,
  `android_id` VARCHAR(255) NOT NULL,
  `pairing_code` VARCHAR(255) NULL DEFAULT NULL,
  `vencimento` DATE NOT NULL,
  `volume_consumido` DOUBLE NOT NULL DEFAULT 0,
  `volume_critico` DOUBLE NOT NULL,
  `reader_id` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tap_bebida_id_foreign` (`bebida_id`),
  KEY `tap_estabelecimento_id_foreign` (`estabelecimento_id`),
  CONSTRAINT `tap_bebida_id_foreign` FOREIGN KEY (`bebida_id`) REFERENCES `bebidas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tap_estabelecimento_id_foreign` FOREIGN KEY (`estabelecimento_id`) REFERENCES `estabelecimentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: order
-- ============================================
CREATE TABLE IF NOT EXISTS `order` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tap_id` BIGINT UNSIGNED NOT NULL,
  `bebida_id` BIGINT UNSIGNED NOT NULL,
  `estabelecimento_id` BIGINT UNSIGNED NOT NULL,
  `method` VARCHAR(255) NOT NULL,
  `valor` DOUBLE(8,2) NOT NULL,
  `descricao` VARCHAR(255) NOT NULL,
  `quantidade` INT NOT NULL,
  `status_liberacao` VARCHAR(255) NOT NULL DEFAULT 'PENDING',
  `qtd_liberada` INT NOT NULL DEFAULT 0,
  `cpf` VARCHAR(255) NOT NULL,
  `response` TEXT NULL DEFAULT NULL,
  `checkout_id` VARCHAR(255) NULL DEFAULT NULL,
  `checkout_status` VARCHAR(255) NULL DEFAULT NULL,
  `pix_code` TEXT NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `order_tap_id_foreign` (`tap_id`),
  KEY `order_bebida_id_foreign` (`bebida_id`),
  KEY `order_estabelecimento_id_foreign` (`estabelecimento_id`),
  CONSTRAINT `order_tap_id_foreign` FOREIGN KEY (`tap_id`) REFERENCES `tap` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_bebida_id_foreign` FOREIGN KEY (`bebida_id`) REFERENCES `bebidas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_estabelecimento_id_foreign` FOREIGN KEY (`estabelecimento_id`) REFERENCES `estabelecimentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: payment
-- ============================================
CREATE TABLE IF NOT EXISTS `payment` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `token_sumup` VARCHAR(255) NOT NULL,
  `pix` TINYINT(1) NULL DEFAULT 1,
  `credit` TINYINT(1) NULL DEFAULT 1,
  `debit` TINYINT(1) NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Initial Data
-- ============================================

-- Admin User (usando INSERT IGNORE para evitar duplicação)
-- Senha: Admin259087@
-- Hash gerado com bcrypt custo 12, compatível com PHP password_verify()
INSERT IGNORE INTO `users` (`id`, `name`, `email`, `password`, `type`, `created_at`, `updated_at`) VALUES
(1, 'Administrador', 'choppon24h@gmail.com', '$2y$12$0WtTRckkCnL3IiFtG8qKH.h7wqCPYQkfktIlJC6Ry2iYNKz1K7Lty', 1, NOW(), NOW());

-- Default Payment Configuration
INSERT IGNORE INTO `payment` (`id`, `token_sumup`, `pix`, `credit`, `debit`, `created_at`, `updated_at`) VALUES
(1, 'sup_sk_8vNpSEJPVudqJrWPdUlomuE3EfVofw1bL', 1, 1, 1, NOW(), NOW());

-- Default Estabelecimento
INSERT IGNORE INTO `estabelecimentos` (`id`, `name`, `document`, `address`, `phone`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Chopp On Tap', '00000000000000', 'Endereço Principal', '(00) 00000-0000', 1, NOW(), NOW());
