-- ============================================
-- Migration: Ajustar tabela cora_config para API Cora
-- Conforme documentação oficial da Cora
-- Versão: 1.0
-- Data: 2025-12-05
-- ============================================

SET @dbname = DATABASE();
SET @tablename = 'cora_config';

-- Adicionar campo client_secret se não existir
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'client_secret');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `cora_config` ADD COLUMN `client_secret` VARCHAR(500) NULL DEFAULT NULL COMMENT ''Client Secret da API Cora'' AFTER `client_id`',
    'SELECT ''Campo client_secret já existe'' AS resultado');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Renomear cora_client_id para client_id se existir
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'cora_client_id');

SET @sql = IF(@col_exists > 0,
    'ALTER TABLE `cora_config` CHANGE `cora_client_id` `client_id` VARCHAR(255) NOT NULL',
    'SELECT ''Campo cora_client_id não existe ou já foi renomeado'' AS resultado');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Tornar certificate_path e private_key_path opcionais (NULL)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'certificate_path');

SET @sql = IF(@col_exists > 0,
    'ALTER TABLE `cora_config` MODIFY `certificate_path` VARCHAR(500) NULL DEFAULT NULL',
    'SELECT ''Campo certificate_path não existe'' AS resultado');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'private_key_path');

SET @sql = IF(@col_exists > 0,
    'ALTER TABLE `cora_config` MODIFY `private_key_path` VARCHAR(500) NULL DEFAULT NULL',
    'SELECT ''Campo private_key_path não existe'' AS resultado');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Renomear campos antigos se existirem
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'cora_certificate_path');

SET @sql = IF(@col_exists > 0,
    'ALTER TABLE `cora_config` CHANGE `cora_certificate_path` `certificate_path` VARCHAR(500) NULL DEFAULT NULL',
    'SELECT ''Campo cora_certificate_path não existe'' AS resultado');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'cora_private_key_path');

SET @sql = IF(@col_exists > 0,
    'ALTER TABLE `cora_config` CHANGE `cora_private_key_path` `private_key_path` VARCHAR(500) NULL DEFAULT NULL',
    'SELECT ''Campo cora_private_key_path não existe'' AS resultado');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- Fim da Migration
-- ============================================

SELECT 'Migration concluída! Agora a tabela cora_config está conforme a documentação oficial da API Cora.' AS resultado;
