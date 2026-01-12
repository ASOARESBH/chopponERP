-- ============================================
-- CHOPP ON TAP - Módulo Financeiro
-- Adiciona funcionalidades de Taxa de Juros e Contas a Pagar
-- ============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- ============================================
-- Table: formas_pagamento
-- Cadastro de formas de pagamento com taxas
-- ============================================
CREATE TABLE IF NOT EXISTS `formas_pagamento` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `estabelecimento_id` BIGINT UNSIGNED NOT NULL,
  `tipo` ENUM('pix', 'credito', 'debito') NOT NULL,
  `bandeira` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Mastercard, Visa, Elo, etc (apenas para crédito/débito)',
  `taxa_percentual` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Taxa em percentual (ex: 2.50 para 2,5%)',
  `taxa_fixa` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Taxa fixa em reais',
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `formas_pagamento_estabelecimento_id_foreign` (`estabelecimento_id`),
  KEY `formas_pagamento_tipo_index` (`tipo`),
  CONSTRAINT `formas_pagamento_estabelecimento_id_foreign` FOREIGN KEY (`estabelecimento_id`) REFERENCES `estabelecimentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: contas_pagar
-- Cadastro de contas a pagar
-- ============================================
CREATE TABLE IF NOT EXISTS `contas_pagar` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `estabelecimento_id` BIGINT UNSIGNED NOT NULL,
  `descricao` VARCHAR(255) NOT NULL,
  `tipo` VARCHAR(100) NOT NULL COMMENT 'Água, Luz, Aluguel, Fornecedor, etc',
  `valor` DECIMAL(10,2) NOT NULL,
  `data_vencimento` DATE NOT NULL,
  `codigo_barras` TEXT NULL DEFAULT NULL,
  `link_pagamento` TEXT NULL DEFAULT NULL,
  `status` ENUM('pendente', 'pago', 'vencido', 'cancelado') NOT NULL DEFAULT 'pendente',
  `data_pagamento` DATE NULL DEFAULT NULL,
  `valor_pago` DECIMAL(10,2) NULL DEFAULT NULL,
  `observacoes` TEXT NULL DEFAULT NULL,
  `notificacao_enviada` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `contas_pagar_estabelecimento_id_foreign` (`estabelecimento_id`),
  KEY `contas_pagar_data_vencimento_index` (`data_vencimento`),
  KEY `contas_pagar_status_index` (`status`),
  CONSTRAINT `contas_pagar_estabelecimento_id_foreign` FOREIGN KEY (`estabelecimento_id`) REFERENCES `estabelecimentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: historico_notificacoes_contas
-- Histórico de notificações enviadas sobre contas
-- ============================================
CREATE TABLE IF NOT EXISTS `historico_notificacoes_contas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conta_id` BIGINT UNSIGNED NOT NULL,
  `estabelecimento_id` BIGINT UNSIGNED NOT NULL,
  `tipo_notificacao` VARCHAR(50) NOT NULL COMMENT 'vencimento_hoje, vencimento_proximo, conta_vencida',
  `mensagem` TEXT NOT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'enviado' COMMENT 'enviado, erro',
  `response` TEXT NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `historico_notificacoes_contas_conta_id_foreign` (`conta_id`),
  KEY `historico_notificacoes_contas_estabelecimento_id_foreign` (`estabelecimento_id`),
  CONSTRAINT `historico_notificacoes_contas_conta_id_foreign` FOREIGN KEY (`conta_id`) REFERENCES `contas_pagar` (`id`) ON DELETE CASCADE,
  CONSTRAINT `historico_notificacoes_contas_estabelecimento_id_foreign` FOREIGN KEY (`estabelecimento_id`) REFERENCES `estabelecimentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Adicionar campo na tabela order para relacionar com forma de pagamento
-- ============================================
ALTER TABLE `order` 
ADD COLUMN `forma_pagamento_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `method`,
ADD COLUMN `taxa_aplicada` DECIMAL(10,2) NULL DEFAULT NULL COMMENT 'Taxa aplicada na transação',
ADD KEY `order_forma_pagamento_id_foreign` (`forma_pagamento_id`),
ADD CONSTRAINT `order_forma_pagamento_id_foreign` FOREIGN KEY (`forma_pagamento_id`) REFERENCES `formas_pagamento` (`id`) ON DELETE SET NULL;

-- ============================================
-- View para contas a vencer
-- ============================================
CREATE OR REPLACE VIEW `view_contas_vencer` AS
SELECT 
    c.id,
    c.descricao,
    c.tipo,
    c.valor,
    c.data_vencimento,
    c.codigo_barras,
    c.link_pagamento,
    c.status,
    c.notificacao_enviada,
    DATEDIFF(c.data_vencimento, CURDATE()) as dias_para_vencer,
    CASE
        WHEN CURDATE() > c.data_vencimento AND c.status = 'pendente' THEN 'vencida'
        WHEN DATEDIFF(c.data_vencimento, CURDATE()) = 0 AND c.status = 'pendente' THEN 'vence_hoje'
        WHEN DATEDIFF(c.data_vencimento, CURDATE()) <= 3 AND c.status = 'pendente' THEN 'vence_proximo'
        ELSE 'ok'
    END as situacao_vencimento,
    e.id as estabelecimento_id,
    e.name as estabelecimento_nome,
    c.created_at,
    c.updated_at
FROM contas_pagar c
INNER JOIN estabelecimentos e ON c.estabelecimento_id = e.id
WHERE c.status IN ('pendente', 'vencido');

-- ============================================
-- Índices adicionais para performance
-- ============================================
CREATE INDEX idx_contas_pagar_tipo ON `contas_pagar`(`tipo`);
CREATE INDEX idx_historico_notificacoes_created_at ON `historico_notificacoes_contas`(`created_at`);

-- ============================================
-- Comentários nas tabelas
-- ============================================
ALTER TABLE `formas_pagamento` COMMENT = 'Formas de pagamento com taxas por estabelecimento';
ALTER TABLE `contas_pagar` COMMENT = 'Contas a pagar por estabelecimento';
ALTER TABLE `historico_notificacoes_contas` COMMENT = 'Histórico de notificações de contas a pagar';

-- ============================================
-- Dados iniciais de exemplo (PIX sem taxa)
-- ============================================
INSERT INTO `formas_pagamento` (`estabelecimento_id`, `tipo`, `bandeira`, `taxa_percentual`, `taxa_fixa`, `ativo`) VALUES
(1, 'pix', NULL, 0.00, 0.00, 1);

-- ============================================
-- Fim do Script
-- ============================================
