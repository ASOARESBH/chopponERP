-- ============================================
-- ADICIONAR ALERTAS AUTOMÁTICOS NO TELEGRAM
-- ============================================

-- Adicionar novos campos na tabela telegram_config
ALTER TABLE `telegram_config` 
ADD COLUMN `notificar_estoque_minimo` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Notificar quando estoque atingir mínimo' AFTER `notificar_vencimento`,
ADD COLUMN `notificar_contas_pagar` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Notificar contas a pagar vencendo' AFTER `notificar_estoque_minimo`,
ADD COLUMN `notificar_promocoes` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Notificar promoções expirando' AFTER `notificar_contas_pagar`,
ADD COLUMN `dias_antes_vencimento` INT NOT NULL DEFAULT 3 COMMENT 'Dias antes do vencimento para alertar' AFTER `notificar_promocoes`,
ADD COLUMN `dias_apos_vencimento` INT NOT NULL DEFAULT 2 COMMENT 'Dias após vencimento para alertar' AFTER `dias_antes_vencimento`;

-- Criar tabela de log de notificações enviadas
CREATE TABLE IF NOT EXISTS `telegram_notifications_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `estabelecimento_id` BIGINT UNSIGNED NOT NULL,
  `tipo` ENUM('estoque_minimo', 'conta_pagar', 'promocao', 'venda', 'volume_critico', 'outro') NOT NULL,
  `referencia_id` BIGINT UNSIGNED NULL COMMENT 'ID do registro relacionado (produto, conta, promoção)',
  `mensagem` TEXT NOT NULL,
  `status` ENUM('enviado', 'erro', 'pendente') NOT NULL DEFAULT 'pendente',
  `erro_mensagem` TEXT NULL,
  `enviado_em` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_estabelecimento` (`estabelecimento_id`),
  INDEX `idx_tipo` (`tipo`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created_at` (`created_at`),
  CONSTRAINT `telegram_log_estabelecimento_foreign` FOREIGN KEY (`estabelecimento_id`) REFERENCES `estabelecimentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Criar tabela de controle de alertas já enviados (para evitar duplicatas)
CREATE TABLE IF NOT EXISTS `telegram_alerts_sent` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `estabelecimento_id` BIGINT UNSIGNED NOT NULL,
  `tipo` ENUM('estoque_minimo', 'conta_pagar', 'promocao') NOT NULL,
  `referencia_id` BIGINT UNSIGNED NOT NULL COMMENT 'ID do produto, conta ou promoção',
  `data_envio` DATE NOT NULL COMMENT 'Data do último envio',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_alert` (`estabelecimento_id`, `tipo`, `referencia_id`, `data_envio`),
  INDEX `idx_estabelecimento` (`estabelecimento_id`),
  INDEX `idx_tipo_ref` (`tipo`, `referencia_id`),
  CONSTRAINT `telegram_alerts_estabelecimento_foreign` FOREIGN KEY (`estabelecimento_id`) REFERENCES `estabelecimentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- FIM DO SCRIPT
-- ============================================
