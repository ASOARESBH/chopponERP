-- ========================================
-- Tabela de Configuração de E-mail
-- ========================================

CREATE TABLE IF NOT EXISTS email_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_config VARCHAR(100) NOT NULL COMMENT 'Nome da configuração',
    smtp_host VARCHAR(255) NOT NULL COMMENT 'Servidor SMTP (ex: smtp.gmail.com)',
    smtp_port INT NOT NULL DEFAULT 587 COMMENT 'Porta SMTP (587 para TLS, 465 para SSL)',
    smtp_secure ENUM('tls', 'ssl', 'none') DEFAULT 'tls' COMMENT 'Tipo de criptografia',
    smtp_user VARCHAR(255) NOT NULL COMMENT 'Usuário SMTP (e-mail)',
    smtp_password VARCHAR(255) NOT NULL COMMENT 'Senha SMTP',
    from_email VARCHAR(255) NOT NULL COMMENT 'E-mail remetente',
    from_name VARCHAR(255) NOT NULL COMMENT 'Nome do remetente',
    reply_to_email VARCHAR(255) COMMENT 'E-mail para resposta',
    ativo BOOLEAN DEFAULT TRUE COMMENT 'Configuração ativa',
    testar_envio BOOLEAN DEFAULT FALSE COMMENT 'Modo de teste',
    email_teste VARCHAR(255) COMMENT 'E-mail para testes',
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Tabela de Templates de E-mail
-- ========================================

CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(100) NOT NULL UNIQUE COMMENT 'Código único do template',
    nome VARCHAR(255) NOT NULL COMMENT 'Nome descritivo do template',
    descricao TEXT COMMENT 'Descrição do propósito do template',
    assunto VARCHAR(500) NOT NULL COMMENT 'Assunto do e-mail (aceita variáveis)',
    corpo_html TEXT NOT NULL COMMENT 'Corpo do e-mail em HTML (aceita variáveis)',
    corpo_texto TEXT COMMENT 'Corpo do e-mail em texto puro (fallback)',
    variaveis_disponiveis TEXT COMMENT 'JSON com lista de variáveis disponíveis',
    ativo BOOLEAN DEFAULT TRUE COMMENT 'Template ativo',
    enviar_automatico BOOLEAN DEFAULT FALSE COMMENT 'Enviar automaticamente quando evento ocorrer',
    dias_antecedencia INT DEFAULT 0 COMMENT 'Dias de antecedência para envio (para alertas)',
    destinatarios_padrao TEXT COMMENT 'E-mails padrão separados por vírgula',
    categoria ENUM('alerta', 'notificacao', 'relatorio', 'cobranca', 'sistema') DEFAULT 'alerta',
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_codigo (codigo),
    INDEX idx_ativo (ativo),
    INDEX idx_categoria (categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Tabela de Histórico de E-mails Enviados
-- ========================================

CREATE TABLE IF NOT EXISTS email_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT COMMENT 'ID do template usado',
    destinatario VARCHAR(255) NOT NULL COMMENT 'E-mail do destinatário',
    destinatario_nome VARCHAR(255) COMMENT 'Nome do destinatário',
    assunto VARCHAR(500) NOT NULL COMMENT 'Assunto do e-mail enviado',
    corpo_html LONGTEXT COMMENT 'Corpo HTML enviado',
    status ENUM('enviado', 'erro', 'pendente') DEFAULT 'pendente',
    mensagem_erro TEXT COMMENT 'Mensagem de erro se houver',
    referencia_tipo VARCHAR(100) COMMENT 'Tipo da entidade relacionada (royalty, conta_pagar, etc)',
    referencia_id INT COMMENT 'ID da entidade relacionada',
    data_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_destinatario (destinatario),
    INDEX idx_data_envio (data_envio),
    INDEX idx_referencia (referencia_tipo, referencia_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Tabela de Configuração Stripe
-- ========================================

CREATE TABLE IF NOT EXISTS stripe_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_config VARCHAR(100) NOT NULL COMMENT 'Nome da configuração',
    api_key VARCHAR(255) NOT NULL COMMENT 'Chave pública Stripe',
    api_secret VARCHAR(255) NOT NULL COMMENT 'Chave secreta Stripe',
    webhook_secret VARCHAR(255) COMMENT 'Webhook secret para validação',
    ambiente VARCHAR(50) DEFAULT 'test' COMMENT 'test ou live',
    ativo BOOLEAN DEFAULT TRUE COMMENT 'Configuração ativa',
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Adicionar campos Stripe em estabelecimentos
-- ========================================

ALTER TABLE estabelecimentos 
ADD COLUMN IF NOT EXISTS stripe_customer_id VARCHAR(255) NULL AFTER email_alerta,
ADD INDEX IF NOT EXISTS idx_stripe_customer (stripe_customer_id);

-- ========================================
-- Adicionar campos de histórico de pagamento em royalties
-- ========================================

ALTER TABLE royalties 
ADD COLUMN IF NOT EXISTS stripe_invoice_id VARCHAR(255) UNIQUE AFTER payment_link_id,
ADD COLUMN IF NOT EXISTS stripe_customer_id VARCHAR(255) AFTER stripe_invoice_id,
ADD COLUMN IF NOT EXISTS payment_intent_id VARCHAR(255) AFTER stripe_customer_id,
ADD COLUMN IF NOT EXISTS hosted_invoice_url VARCHAR(500) AFTER payment_link_url,
ADD COLUMN IF NOT EXISTS email_enviado_em DATETIME AFTER data_envio_email,
ADD COLUMN IF NOT EXISTS tentativas_envio INT DEFAULT 0 AFTER email_enviado_em,
ADD INDEX IF NOT EXISTS idx_stripe_invoice (stripe_invoice_id),
ADD INDEX IF NOT EXISTS idx_payment_intent (payment_intent_id);

-- ========================================
-- Tabela de Logs de Integração
-- ========================================

CREATE TABLE IF NOT EXISTS logs_integracao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(50) NOT NULL COMMENT 'stripe, cora, email, etc',
    acao VARCHAR(100) NOT NULL COMMENT 'criar_fatura, enviar_email, etc',
    status VARCHAR(50) NOT NULL COMMENT 'sucesso, erro, pendente',
    mensagem TEXT,
    dados_enviados LONGTEXT COMMENT 'JSON dos dados enviados',
    resposta_api LONGTEXT COMMENT 'JSON da resposta da API',
    tempo_execucao DECIMAL(10, 4) COMMENT 'Tempo em segundos',
    referencia_tipo VARCHAR(100) COMMENT 'Tipo da entidade (royalty, conta_pagar, etc)',
    referencia_id INT COMMENT 'ID da entidade',
    data_log TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tipo (tipo),
    INDEX idx_acao (acao),
    INDEX idx_status (status),
    INDEX idx_data (data_log),
    INDEX idx_referencia (referencia_tipo, referencia_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Adicionar coluna de origem em contas_pagar
-- ========================================

ALTER TABLE contas_pagar 
ADD COLUMN IF NOT EXISTS origem VARCHAR(100) DEFAULT 'manual' AFTER boleto_url,
ADD COLUMN IF NOT EXISTS stripe_invoice_id VARCHAR(255) AFTER origem,
ADD COLUMN IF NOT EXISTS email_enviado BOOLEAN DEFAULT FALSE AFTER stripe_invoice_id,
ADD INDEX IF NOT EXISTS idx_origem (origem),
ADD INDEX IF NOT EXISTS idx_stripe_invoice (stripe_invoice_id);

-- ========================================
-- Inserir templates padrão de e-mail
-- ========================================

INSERT IGNORE INTO email_templates (codigo, nome, descricao, assunto, corpo_html, corpo_texto, categoria, ativo)
VALUES 
(
    'royalties_boleto_gerado',
    'Boleto de Royalties Gerado',
    'Notificação quando um boleto de royalties é gerado',
    'Seu boleto de royalties foi gerado - {{periodo}}',
    '<html><body><h2>Boleto de Royalties Gerado</h2><p>Olá {{nome}},</p><p>Seu boleto de royalties foi gerado com sucesso!</p><p><strong>Período:</strong> {{periodo}}</p><p><strong>Valor:</strong> {{valor}}</p><p><strong>Vencimento:</strong> {{vencimento}}</p><p><a href="{{boleto_url}}">Clique aqui para visualizar o boleto</a></p></body></html>',
    'Boleto de Royalties Gerado\n\nOlá {{nome}},\n\nSeu boleto foi gerado!\n\nPeríodo: {{periodo}}\nValor: {{valor}}\nVencimento: {{vencimento}}\n\nURL: {{boleto_url}}',
    'cobranca',
    TRUE
),
(
    'royalties_link_pagamento',
    'Link de Pagamento de Royalties',
    'Notificação com link de pagamento de royalties',
    'Link de pagamento de royalties - {{periodo}}',
    '<html><body><h2>Link de Pagamento Disponível</h2><p>Olá {{nome}},</p><p>Seu link de pagamento está pronto!</p><p><strong>Período:</strong> {{periodo}}</p><p><strong>Valor:</strong> {{valor}}</p><p><a href="{{payment_link}}">Clique para pagar</a></p></body></html>',
    'Link de Pagamento Disponível\n\nOlá {{nome}},\n\nPeríodo: {{periodo}}\nValor: {{valor}}\n\nLink: {{payment_link}}',
    'cobranca',
    TRUE
),
(
    'royalties_pagamento_confirmado',
    'Pagamento de Royalties Confirmado',
    'Notificação de confirmação de pagamento',
    'Pagamento de royalties confirmado - {{periodo}}',
    '<html><body><h2>Pagamento Confirmado</h2><p>Olá {{nome}},</p><p>Seu pagamento foi confirmado com sucesso!</p><p><strong>Período:</strong> {{periodo}}</p><p><strong>Valor:</strong> {{valor}}</p><p><strong>Data:</strong> {{data_pagamento}}</p></body></html>',
    'Pagamento Confirmado\n\nOlá {{nome}},\n\nPeríodo: {{periodo}}\nValor: {{valor}}\nData: {{data_pagamento}}',
    'notificacao',
    TRUE
);

-- ========================================
-- Índices adicionais para performance
-- ========================================

ALTER TABLE royalties ADD INDEX IF NOT EXISTS idx_status_tipo (status, tipo_cobranca);
ALTER TABLE royalties ADD INDEX IF NOT EXISTS idx_estabelecimento_status (estabelecimento_id, status);
ALTER TABLE email_historico ADD INDEX IF NOT EXISTS idx_referencia_completa (referencia_tipo, referencia_id, status);
