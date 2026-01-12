-- Tabela de configuração do Stripe
CREATE TABLE IF NOT EXISTS `stripe_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `estabelecimento_id` int(11) DEFAULT NULL,
  `stripe_publishable_key` varchar(255) DEFAULT NULL,
  `stripe_secret_key` varchar(255) NOT NULL,
  `stripe_webhook_secret` varchar(255) DEFAULT NULL,
  `modo` enum('test','live') DEFAULT 'test',
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `estabelecimento_id` (`estabelecimento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de configuração do Banco Cora
CREATE TABLE IF NOT EXISTS `cora_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `estabelecimento_id` int(11) DEFAULT NULL,
  `client_id` varchar(255) NOT NULL,
  `certificate_path` varchar(500) NOT NULL,
  `private_key_path` varchar(500) NOT NULL,
  `environment` enum('stage','production') DEFAULT 'stage',
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `estabelecimento_id` (`estabelecimento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
