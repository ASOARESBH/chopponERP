-- ============================================
-- SISTEMA DE PERMISSÕES POR PÁGINA
-- Chopp On Tap - v3.1
-- ============================================

-- Tabela de páginas do sistema
CREATE TABLE IF NOT EXISTS `system_pages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_key` VARCHAR(100) NOT NULL COMMENT 'Identificador único da página',
  `page_name` VARCHAR(255) NOT NULL COMMENT 'Nome amigável da página',
  `page_url` VARCHAR(255) NOT NULL COMMENT 'URL da página',
  `page_icon` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Ícone Font Awesome',
  `page_category` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Categoria (ex: Operacional, Financeiro)',
  `admin_only` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=Exclusivo Admin Geral',
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `system_pages_page_key_unique` (`page_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de permissões de usuários
CREATE TABLE IF NOT EXISTS `user_permissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `page_id` BIGINT UNSIGNED NOT NULL,
  `can_view` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Pode visualizar',
  `can_create` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Pode criar',
  `can_edit` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Pode editar',
  `can_delete` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Pode excluir',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_permissions_unique` (`user_id`, `page_id`),
  KEY `user_permissions_user_id_foreign` (`user_id`),
  KEY `user_permissions_page_id_foreign` (`page_id`),
  CONSTRAINT `user_permissions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_permissions_page_id_foreign` FOREIGN KEY (`page_id`) REFERENCES `system_pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir páginas do sistema
INSERT INTO `system_pages` (`page_key`, `page_name`, `page_url`, `page_icon`, `page_category`, `admin_only`) VALUES
('dashboard', 'Dashboard', 'admin/dashboard.php', 'fas fa-chart-line', 'Geral', 0),
('bebidas', 'Bebidas', 'admin/bebidas.php', 'fas fa-beer', 'Operacional', 0),
('taps', 'TAPs', 'admin/taps.php', 'fas fa-faucet', 'Operacional', 0),
('pagamentos', 'Pagamentos', 'admin/pagamentos.php', 'fas fa-credit-card', 'Financeiro', 0),
('pedidos', 'Pedidos', 'admin/pedidos.php', 'fas fa-shopping-cart', 'Operacional', 0),
('usuarios', 'Usuários', 'admin/usuarios.php', 'fas fa-users', 'Administração', 0),
('estabelecimentos', 'Estabelecimentos', 'admin/estabelecimentos.php', 'fas fa-store', 'Administração', 0),
('financeiro_taxas', 'Taxas de Juros', 'admin/financeiro_taxas.php', 'fas fa-percentage', 'Financeiro', 0),
('financeiro_contas', 'Contas a Pagar', 'admin/financeiro_contas.php', 'fas fa-file-invoice-dollar', 'Financeiro', 0),
('logs', 'Logs do Sistema', 'admin/logs.php', 'fas fa-file-alt', 'Administração', 1),
('email_config', 'Configuração de E-mail', 'admin/email_config.php', 'fas fa-envelope', 'Administração', 1),
('telegram', 'Telegram', 'admin/telegram.php', 'fab fa-telegram', 'Administração', 1);

-- Criar permissões padrão para Admin Geral (type = 1)
-- Admin Geral tem acesso total a todas as páginas
INSERT INTO `user_permissions` (`user_id`, `page_id`, `can_view`, `can_create`, `can_edit`, `can_delete`)
SELECT u.id, p.id, 1, 1, 1, 1
FROM `users` u
CROSS JOIN `system_pages` p
WHERE u.type = 1
ON DUPLICATE KEY UPDATE 
  `can_view` = 1, 
  `can_create` = 1, 
  `can_edit` = 1, 
  `can_delete` = 1;

-- Criar permissões padrão para Gerentes (type = 2)
-- Gerentes têm acesso a páginas operacionais e financeiras (exceto admin_only)
INSERT INTO `user_permissions` (`user_id`, `page_id`, `can_view`, `can_create`, `can_edit`, `can_delete`)
SELECT u.id, p.id, 1, 1, 1, 0
FROM `users` u
CROSS JOIN `system_pages` p
WHERE u.type = 2 AND p.admin_only = 0
ON DUPLICATE KEY UPDATE 
  `can_view` = 1, 
  `can_create` = 1, 
  `can_edit` = 1, 
  `can_delete` = 0;

-- Criar permissões padrão para Operadores (type = 3)
-- Operadores têm acesso apenas a páginas operacionais (visualizar e editar)
INSERT INTO `user_permissions` (`user_id`, `page_id`, `can_view`, `can_create`, `can_edit`, `can_delete`)
SELECT u.id, p.id, 1, 0, 1, 0
FROM `users` u
CROSS JOIN `system_pages` p
WHERE u.type = 3 AND p.admin_only = 0 AND p.page_category IN ('Operacional', 'Geral')
ON DUPLICATE KEY UPDATE 
  `can_view` = 1, 
  `can_create` = 0, 
  `can_edit` = 1, 
  `can_delete` = 0;

-- Criar permissões padrão para Visualizadores (type = 4)
-- Visualizadores têm acesso apenas para visualizar (sem criar, editar ou excluir)
INSERT INTO `user_permissions` (`user_id`, `page_id`, `can_view`, `can_create`, `can_edit`, `can_delete`)
SELECT u.id, p.id, 1, 0, 0, 0
FROM `users` u
CROSS JOIN `system_pages` p
WHERE u.type = 4 AND p.admin_only = 0 AND p.page_key IN ('dashboard', 'bebidas', 'taps', 'pedidos')
ON DUPLICATE KEY UPDATE 
  `can_view` = 1, 
  `can_create` = 0, 
  `can_edit` = 0, 
  `can_delete` = 0;

-- ============================================
-- FIM DO SCRIPT
-- ============================================
