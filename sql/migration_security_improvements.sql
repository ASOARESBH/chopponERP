-- ============================================
-- Migration: Security Improvements
-- Versão: 2.0
-- Data: 2025-12-05
-- ============================================

-- Adicionar campo idempotency_key na tabela order
ALTER TABLE `order` 
ADD COLUMN `idempotency_key` VARCHAR(64) NULL DEFAULT NULL AFTER `cpf`,
ADD INDEX `idx_idempotency` (`idempotency_key`);

-- Criar tabela de blacklist de JWT
CREATE TABLE IF NOT EXISTS `jwt_blacklist` (
    `jti` VARCHAR(32) PRIMARY KEY,
    `expires_at` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Criar tabela de refresh tokens (opcional, para rastreamento)
CREATE TABLE IF NOT EXISTS `refresh_tokens` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `jti` VARCHAR(32) NOT NULL,
    `expires_at` INT NOT NULL,
    `revoked` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_jti` (`jti`),
    INDEX `idx_expires` (`expires_at`),
    CONSTRAINT `fk_refresh_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adicionar índices para melhorar performance
ALTER TABLE `order` 
ADD INDEX `idx_checkout_status` (`checkout_status`),
ADD INDEX `idx_created_at` (`created_at`);

ALTER TABLE `tap` 
ADD INDEX `idx_android_id` (`android_id`),
ADD INDEX `idx_status` (`status`);

-- Limpar tokens expirados automaticamente (evento)
CREATE EVENT IF NOT EXISTS `cleanup_jwt_blacklist`
ON SCHEDULE EVERY 1 HOUR
DO
DELETE FROM `jwt_blacklist` WHERE `expires_at` < UNIX_TIMESTAMP();

-- Limpar refresh tokens expirados
CREATE EVENT IF NOT EXISTS `cleanup_refresh_tokens`
ON SCHEDULE EVERY 1 DAY
DO
DELETE FROM `refresh_tokens` WHERE `expires_at` < UNIX_TIMESTAMP();

-- ============================================
-- Fim da Migration
-- ============================================
