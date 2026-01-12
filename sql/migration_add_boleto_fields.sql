-- ============================================
-- Migration: Adicionar campos de boleto Cora
-- Versão: 1.0
-- Data: 2025-12-05
-- ============================================

-- Verificar se os campos já existem antes de adicionar
SET @dbname = DATABASE();
SET @tablename = 'royalties';

-- Adicionar campo boleto_id se não existir
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'boleto_id');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `royalties` ADD COLUMN `boleto_id` VARCHAR(255) NULL DEFAULT NULL COMMENT ''ID do boleto gerado na API Cora'' AFTER `payment_link_id`',
    'SELECT ''Campo boleto_id já existe'' AS resultado');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar campo boleto_url se não existir
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'boleto_url');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `royalties` ADD COLUMN `boleto_url` TEXT NULL DEFAULT NULL COMMENT ''URL do PDF do boleto'' AFTER `boleto_id`',
    'SELECT ''Campo boleto_url já existe'' AS resultado');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar campo boleto_linha_digitavel se não existir
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'boleto_linha_digitavel');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `royalties` ADD COLUMN `boleto_linha_digitavel` TEXT NULL DEFAULT NULL COMMENT ''Linha digitável do boleto'' AFTER `boleto_url`',
    'SELECT ''Campo boleto_linha_digitavel já existe'' AS resultado');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar campo boleto_codigo_barras se não existir
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'boleto_codigo_barras');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `royalties` ADD COLUMN `boleto_codigo_barras` TEXT NULL DEFAULT NULL COMMENT ''Código de barras do boleto'' AFTER `boleto_linha_digitavel`',
    'SELECT ''Campo boleto_codigo_barras já existe'' AS resultado');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar campo boleto_data_emissao se não existir
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'boleto_data_emissao');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `royalties` ADD COLUMN `boleto_data_emissao` TIMESTAMP NULL DEFAULT NULL COMMENT ''Data de emissão do boleto'' AFTER `boleto_codigo_barras`',
    'SELECT ''Campo boleto_data_emissao já existe'' AS resultado');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar índice no boleto_id se não existir
SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = 'idx_boleto_id');

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE `royalties` ADD INDEX `idx_boleto_id` (`boleto_id`)',
    'SELECT ''Índice idx_boleto_id já existe'' AS resultado');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- Fim da Migration
-- ============================================

SELECT 'Migration concluída com sucesso!' AS resultado;
