-- ============================================
-- ADICIONAR CONFIGURAÇÃO SMTP E NOVOS ALERTAS POR E-MAIL
-- ============================================

-- Criar tabela de configuração SMTP
CREATE TABLE IF NOT EXISTS `smtp_config` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `estabelecimento_id` BIGINT UNSIGNED NOT NULL,
  `smtp_host` VARCHAR(255) NOT NULL COMMENT 'Servidor SMTP (ex: smtp.gmail.com)',
  `smtp_port` INT NOT NULL DEFAULT 587 COMMENT 'Porta SMTP (587 para TLS, 465 para SSL)',
  `smtp_secure` ENUM('tls', 'ssl', 'none') NOT NULL DEFAULT 'tls' COMMENT 'Tipo de criptografia',
  `smtp_username` VARCHAR(255) NOT NULL COMMENT 'Usuário SMTP',
  `smtp_password` VARCHAR(255) NOT NULL COMMENT 'Senha SMTP (criptografada)',
  `from_email` VARCHAR(255) NOT NULL COMMENT 'E-mail remetente',
  `from_name` VARCHAR(255) NOT NULL COMMENT 'Nome do remetente',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Ativo/Inativo',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `smtp_config_estabelecimento_unique` (`estabelecimento_id`),
  CONSTRAINT `smtp_config_estabelecimento_foreign` FOREIGN KEY (`estabelecimento_id`) REFERENCES `estabelecimentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adicionar novos campos na tabela email_config
ALTER TABLE `email_config` 
ADD COLUMN `notificar_estoque_minimo` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Notificar estoque mínimo' AFTER `notificar_contas_pagar`,
ADD COLUMN `notificar_royalties` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Notificar royalties vencendo' AFTER `notificar_estoque_minimo`,
ADD COLUMN `notificar_promocoes` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Notificar promoções expirando' AFTER `notificar_royalties`,
ADD COLUMN `notificar_taps` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Notificar TAPs com problemas' AFTER `notificar_promocoes`,
ADD COLUMN `dias_antes_vencimento` INT NOT NULL DEFAULT 3 COMMENT 'Dias antes do vencimento para alertar' AFTER `dias_antes_contas_pagar`,
ADD COLUMN `dias_apos_vencimento` INT NOT NULL DEFAULT 2 COMMENT 'Dias após vencimento para alertar' AFTER `dias_antes_vencimento`;

-- Criar tabela de log de e-mails enviados
CREATE TABLE IF NOT EXISTS `email_notifications_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `estabelecimento_id` BIGINT UNSIGNED NOT NULL,
  `tipo` ENUM('estoque_minimo', 'conta_pagar', 'royalty', 'promocao', 'tap', 'venda', 'volume_critico', 'outro') NOT NULL,
  `referencia_id` BIGINT UNSIGNED NULL COMMENT 'ID do registro relacionado',
  `destinatario` VARCHAR(255) NOT NULL COMMENT 'E-mail do destinatário',
  `assunto` VARCHAR(255) NOT NULL,
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
  CONSTRAINT `email_log_estabelecimento_foreign` FOREIGN KEY (`estabelecimento_id`) REFERENCES `estabelecimentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Criar tabela de controle de alertas já enviados por e-mail
CREATE TABLE IF NOT EXISTS `email_alerts_sent` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `estabelecimento_id` BIGINT UNSIGNED NOT NULL,
  `tipo` ENUM('estoque_minimo', 'conta_pagar', 'royalty', 'promocao', 'tap') NOT NULL,
  `referencia_id` BIGINT UNSIGNED NOT NULL COMMENT 'ID do registro',
  `data_envio` DATE NOT NULL COMMENT 'Data do último envio',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_email_alert` (`estabelecimento_id`, `tipo`, `referencia_id`, `data_envio`),
  INDEX `idx_estabelecimento` (`estabelecimento_id`),
  INDEX `idx_tipo_ref` (`tipo`, `referencia_id`),
  CONSTRAINT `email_alerts_estabelecimento_foreign` FOREIGN KEY (`estabelecimento_id`) REFERENCES `estabelecimentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- FIM DO SCRIPT
-- ============================================
