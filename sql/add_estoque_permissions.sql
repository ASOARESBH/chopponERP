-- ============================================
-- ADICIONAR PERMISSÕES DOS NOVOS MÓDULOS
-- Módulos: Estoque e Fornecedores
-- ============================================

-- Inserir novas páginas do sistema
INSERT INTO `system_pages` (`page_key`, `page_name`, `page_url`, `page_icon`, `page_category`, `admin_only`) VALUES
('estoque_produtos', 'Estoque - Produtos', 'admin/estoque_produtos.php', 'fas fa-box', 'Estoque', 0),
('estoque_visao', 'Estoque - Visão Geral', 'admin/estoque_visao.php', 'fas fa-warehouse', 'Estoque', 0),
('estoque_movimentacoes', 'Estoque - Movimentações', 'admin/estoque_movimentacoes.php', 'fas fa-exchange-alt', 'Estoque', 0),
('estoque_relatorios', 'Estoque - Relatórios', 'admin/estoque_relatorios.php', 'fas fa-chart-bar', 'Estoque', 0),
('fornecedores', 'Fornecedores', 'admin/fornecedores.php', 'fas fa-truck', 'Estoque', 0),
('financeiro_royalties', 'Royalties', 'admin/financeiro_royalties.php', 'fas fa-crown', 'Financeiro', 0),
('financeiro_faturamento', 'Faturamento', 'admin/financeiro_faturamento.php', 'fas fa-file-invoice', 'Financeiro', 0),
('promocoes', 'Promoções', 'admin/promocoes.php', 'fas fa-tags', 'Marketing', 0),
('permissoes', 'Permissões', 'admin/permissoes.php', 'fas fa-user-lock', 'Administração', 1),
('stripe_config', 'Stripe Pagamentos', 'admin/stripe_config.php', 'fab fa-stripe', 'Administração', 1),
('cora_config', 'Banco Cora', 'admin/cora_config.php', 'fas fa-university', 'Administração', 1)
ON DUPLICATE KEY UPDATE 
  `page_name` = VALUES(`page_name`),
  `page_url` = VALUES(`page_url`),
  `page_icon` = VALUES(`page_icon`),
  `page_category` = VALUES(`page_category`),
  `admin_only` = VALUES(`admin_only`);

-- Criar permissões padrão para Admin Geral (type = 1)
-- Admin Geral tem acesso total a todas as páginas
INSERT INTO `user_permissions` (`user_id`, `page_id`, `can_view`, `can_create`, `can_edit`, `can_delete`)
SELECT u.id, p.id, 1, 1, 1, 1
FROM `users` u
CROSS JOIN `system_pages` p
WHERE u.type = 1
  AND p.page_key IN (
    'estoque_produtos', 'estoque_visao', 'estoque_movimentacoes', 'estoque_relatorios',
    'fornecedores', 'financeiro_royalties', 'financeiro_faturamento', 'promocoes',
    'permissoes', 'stripe_config', 'cora_config'
  )
ON DUPLICATE KEY UPDATE 
  `can_view` = 1, 
  `can_create` = 1, 
  `can_edit` = 1, 
  `can_delete` = 1;

-- Criar permissões padrão para Gerentes (type = 2)
-- Gerentes têm acesso a módulos de Estoque e Financeiro (exceto admin_only)
INSERT INTO `user_permissions` (`user_id`, `page_id`, `can_view`, `can_create`, `can_edit`, `can_delete`)
SELECT u.id, p.id, 1, 1, 1, 1
FROM `users` u
CROSS JOIN `system_pages` p
WHERE u.type = 2 
  AND p.admin_only = 0
  AND p.page_key IN (
    'estoque_produtos', 'estoque_visao', 'estoque_movimentacoes', 'estoque_relatorios',
    'fornecedores', 'financeiro_royalties', 'financeiro_faturamento', 'promocoes'
  )
ON DUPLICATE KEY UPDATE 
  `can_view` = 1, 
  `can_create` = 1, 
  `can_edit` = 1, 
  `can_delete` = 1;

-- Criar permissões padrão para Operadores (type = 3)
-- Operadores têm acesso a Estoque (visualizar, criar e editar, mas não excluir)
INSERT INTO `user_permissions` (`user_id`, `page_id`, `can_view`, `can_create`, `can_edit`, `can_delete`)
SELECT u.id, p.id, 1, 1, 1, 0
FROM `users` u
CROSS JOIN `system_pages` p
WHERE u.type = 3 
  AND p.admin_only = 0
  AND p.page_key IN (
    'estoque_produtos', 'estoque_visao', 'estoque_movimentacoes', 'estoque_relatorios',
    'fornecedores'
  )
ON DUPLICATE KEY UPDATE 
  `can_view` = 1, 
  `can_create` = 1, 
  `can_edit` = 1, 
  `can_delete` = 0;

-- Criar permissões padrão para Visualizadores (type = 4)
-- Visualizadores têm acesso apenas para visualizar estoque
INSERT INTO `user_permissions` (`user_id`, `page_id`, `can_view`, `can_create`, `can_edit`, `can_delete`)
SELECT u.id, p.id, 1, 0, 0, 0
FROM `users` u
CROSS JOIN `system_pages` p
WHERE u.type = 4 
  AND p.admin_only = 0
  AND p.page_key IN ('estoque_visao', 'estoque_relatorios')
ON DUPLICATE KEY UPDATE 
  `can_view` = 1, 
  `can_create` = 0, 
  `can_edit` = 0, 
  `can_delete` = 0;

-- ============================================
-- FIM DO SCRIPT
-- ============================================
