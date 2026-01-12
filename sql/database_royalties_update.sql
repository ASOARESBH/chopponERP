-- Atualização do Módulo de Royalties
-- Adiciona novos campos para e-mail, data de vencimento, forma de pagamento e link de pagamento

-- Adicionar campos na tabela royalties
ALTER TABLE royalties 
ADD COLUMN IF NOT EXISTS email_cobranca VARCHAR(255) NULL COMMENT 'E-mail para envio da cobrança',
ADD COLUMN IF NOT EXISTS emails_adicionais TEXT NULL COMMENT 'E-mails adicionais separados por vírgula',
ADD COLUMN IF NOT EXISTS data_vencimento DATE NULL COMMENT 'Data de vencimento customizada',
ADD COLUMN IF NOT EXISTS forma_pagamento VARCHAR(50) NULL COMMENT 'Forma de pagamento selecionada',
ADD COLUMN IF NOT EXISTS payment_link_id VARCHAR(255) NULL COMMENT 'ID do Payment Link no Stripe',
ADD COLUMN IF NOT EXISTS payment_link_url TEXT NULL COMMENT 'URL do Payment Link',
ADD COLUMN IF NOT EXISTS link_enviado_em DATETIME NULL COMMENT 'Data/hora do envio do link por e-mail';

-- Adicionar campos na tabela contas_pagar
ALTER TABLE contas_pagar 
ADD COLUMN IF NOT EXISTS royalty_id INT NULL COMMENT 'ID do royalty vinculado',
ADD COLUMN IF NOT EXISTS payment_link_url TEXT NULL COMMENT 'Link de pagamento',
ADD COLUMN IF NOT EXISTS valor_protegido BOOLEAN DEFAULT FALSE COMMENT 'Se TRUE, franqueado não pode alterar o valor',
ADD COLUMN IF NOT EXISTS origem VARCHAR(50) NULL COMMENT 'Origem da conta (ex: royalties, manual)';

-- Adicionar índice para melhor performance
ALTER TABLE contas_pagar ADD INDEX IF NOT EXISTS idx_royalty_id (royalty_id);

-- Adicionar constraint de chave estrangeira (opcional, se não existir)
-- ALTER TABLE contas_pagar 
-- ADD CONSTRAINT fk_contas_pagar_royalty 
-- FOREIGN KEY (royalty_id) REFERENCES royalties(id) ON DELETE SET NULL;

-- Atualizar registros existentes
UPDATE contas_pagar 
SET origem = 'royalties', valor_protegido = TRUE
WHERE observacoes LIKE '%Boleto ID Cora%' OR observacoes LIKE '%Fatura Stripe%';
