-- ============================================
-- MÓDULO DE PROMOÇÕES - CHOPP ON TAP v3.2
-- ============================================

-- Tabela principal de promoções
CREATE TABLE IF NOT EXISTS `promocoes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `estabelecimento_id` BIGINT UNSIGNED NOT NULL,
  `nome` VARCHAR(255) NOT NULL,
  `descricao` TEXT NULL,
  `data_inicio` DATETIME NOT NULL,
  `data_fim` DATETIME NOT NULL,
  `tipo_regra` ENUM('todos', 'cupom', 'cashback') NOT NULL DEFAULT 'todos',
  `cupons` TEXT NULL COMMENT 'Cupons separados por vírgula, ex: #cupom24h,#cupomNatal',
  `cashback_valor` DECIMAL(10,2) NULL COMMENT 'Valor em cashback necessário',
  `cashback_ml` INT NULL COMMENT 'ML liberados por cashback',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=Ativa, 0=Inativa',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_estabelecimento` (`estabelecimento_id`),
  INDEX `idx_datas` (`data_inicio`, `data_fim`),
  INDEX `idx_status` (`status`),
  FOREIGN KEY (`estabelecimento_id`) REFERENCES `estabelecimentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de bebidas vinculadas às promoções
CREATE TABLE IF NOT EXISTS `promocao_bebidas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `promocao_id` BIGINT UNSIGNED NOT NULL,
  `bebida_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_promocao_bebida` (`promocao_id`, `bebida_id`),
  INDEX `idx_promocao` (`promocao_id`),
  INDEX `idx_bebida` (`bebida_id`),
  FOREIGN KEY (`promocao_id`) REFERENCES `promocoes` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`bebida_id`) REFERENCES `bebidas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de log de uso de promoções
CREATE TABLE IF NOT EXISTS `promocao_uso` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `promocao_id` BIGINT UNSIGNED NOT NULL,
  `bebida_id` BIGINT UNSIGNED NOT NULL,
  `pedido_id` BIGINT UNSIGNED NULL,
  `cupom_usado` VARCHAR(100) NULL,
  `cashback_usado` DECIMAL(10,2) NULL,
  `ml_liberado` INT NULL,
  `valor_original` DECIMAL(10,2) NOT NULL,
  `valor_promocional` DECIMAL(10,2) NOT NULL,
  `desconto_aplicado` DECIMAL(10,2) NOT NULL,
  `usado_em` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_promocao` (`promocao_id`),
  INDEX `idx_bebida` (`bebida_id`),
  INDEX `idx_pedido` (`pedido_id`),
  INDEX `idx_data` (`usado_em`),
  FOREIGN KEY (`promocao_id`) REFERENCES `promocoes` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`bebida_id`) REFERENCES `bebidas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adicionar página de promoções ao sistema de permissões
INSERT INTO `system_pages` 
(`page_key`, `page_name`, `page_url`, `page_icon`, `page_category`, `admin_only`, `status`) 
VALUES 
('promocoes', 'Promoções', 'admin/promocoes.php', 'fas fa-tags', 'Operacional', 0, 1)
ON DUPLICATE KEY UPDATE 
`page_name` = VALUES(`page_name`),
`page_url` = VALUES(`page_url`),
`page_icon` = VALUES(`page_icon`),
`page_category` = VALUES(`page_category`);

-- Criar permissões padrão para a página de promoções
-- Admin Geral (type = 1)
INSERT INTO `user_permissions` (`user_id`, `page_id`, `can_view`, `can_create`, `can_edit`, `can_delete`)
SELECT u.id, sp.id, 1, 1, 1, 1
FROM `users` u
CROSS JOIN `system_pages` sp
WHERE u.type = 1 
AND sp.page_key = 'promocoes'
AND NOT EXISTS (
    SELECT 1 FROM `user_permissions` up 
    WHERE up.user_id = u.id AND up.page_id = sp.id
);

-- Gerente (type = 2)
INSERT INTO `user_permissions` (`user_id`, `page_id`, `can_view`, `can_create`, `can_edit`, `can_delete`)
SELECT u.id, sp.id, 1, 1, 1, 0
FROM `users` u
CROSS JOIN `system_pages` sp
WHERE u.type = 2 
AND sp.page_key = 'promocoes'
AND NOT EXISTS (
    SELECT 1 FROM `user_permissions` up 
    WHERE up.user_id = u.id AND up.page_id = sp.id
);

-- Operador (type = 3)
INSERT INTO `user_permissions` (`user_id`, `page_id`, `can_view`, `can_create`, `can_edit`, `can_delete`)
SELECT u.id, sp.id, 1, 0, 1, 0
FROM `users` u
CROSS JOIN `system_pages` sp
WHERE u.type = 3 
AND sp.page_key = 'promocoes'
AND NOT EXISTS (
    SELECT 1 FROM `user_permissions` up 
    WHERE up.user_id = u.id AND up.page_id = sp.id
);

-- Visualizador (type = 4)
INSERT INTO `user_permissions` (`user_id`, `page_id`, `can_view`, `can_create`, `can_edit`, `can_delete`)
SELECT u.id, sp.id, 1, 0, 0, 0
FROM `users` u
CROSS JOIN `system_pages` sp
WHERE u.type = 4 
AND sp.page_key = 'promocoes'
AND NOT EXISTS (
    SELECT 1 FROM `user_permissions` up 
    WHERE up.user_id = u.id AND up.page_id = sp.id
);

-- ============================================
-- DADOS DE EXEMPLO (OPCIONAL)
-- ============================================

-- Exemplo 1: Happy Hour
-- INSERT INTO `promocoes` 
-- (`estabelecimento_id`, `nome`, `descricao`, `data_inicio`, `data_fim`, `tipo_regra`, `status`)
-- VALUES 
-- (1, 'Happy Hour', 'Desconto especial no happy hour para bebidas selecionadas', 
--  '2025-01-01 17:00:00', '2025-12-31 19:00:00', 'todos', 1);

-- Exemplo 2: Cupom de Natal
-- INSERT INTO `promocoes` 
-- (`estabelecimento_id`, `nome`, `descricao`, `data_inicio`, `data_fim`, `tipo_regra`, `cupons`, `status`)
-- VALUES 
-- (1, 'Promoção de Natal', 'Use o cupom #cupomNatal para ganhar desconto', 
--  '2025-12-01 00:00:00', '2025-12-31 23:59:59', 'cupom', '#cupomNatal,#natal2025', 1);

-- Exemplo 3: Cashback
-- INSERT INTO `promocoes` 
-- (`estabelecimento_id`, `nome`, `descricao`, `data_inicio`, `data_fim`, `tipo_regra`, 
--  `cashback_valor`, `cashback_ml`, `status`)
-- VALUES 
-- (1, 'Troca de Cashback', 'A cada 100 em cashback, ganhe 100ML grátis', 
--  '2025-01-01 00:00:00', '2025-12-31 23:59:59', 'cashback', 100.00, 100, 1);

-- ============================================
-- FIM DO SCRIPT
-- ============================================
