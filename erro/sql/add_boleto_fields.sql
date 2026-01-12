-- ========================================
-- Adicionar campos de boleto na tabela royalties
-- ========================================

ALTER TABLE royalties ADD COLUMN IF NOT EXISTS boleto_cora_id VARCHAR(255) AFTER payment_link_id;
ALTER TABLE royalties ADD COLUMN IF NOT EXISTS boleto_linha_digitavel VARCHAR(255) AFTER boleto_cora_id;
ALTER TABLE royalties ADD COLUMN IF NOT EXISTS boleto_codigo_barras VARCHAR(255) AFTER boleto_linha_digitavel;
ALTER TABLE royalties ADD COLUMN IF NOT EXISTS boleto_qrcode_pix LONGTEXT AFTER boleto_codigo_barras;
ALTER TABLE royalties ADD COLUMN IF NOT EXISTS boleto_url VARCHAR(500) AFTER boleto_qrcode_pix;
ALTER TABLE royalties ADD COLUMN IF NOT EXISTS boleto_data_vencimento DATE AFTER boleto_url;

-- ========================================
-- Adicionar coluna de tipo de cobrança se não existir
-- ========================================

ALTER TABLE royalties ADD COLUMN IF NOT EXISTS tipo_cobranca VARCHAR(50) DEFAULT 'stripe' AFTER forma_pagamento;

-- ========================================
-- Adicionar índices para melhor performance
-- ========================================

ALTER TABLE royalties ADD INDEX IF NOT EXISTS idx_boleto_cora_id (boleto_cora_id);
ALTER TABLE royalties ADD INDEX IF NOT EXISTS idx_tipo_cobranca (tipo_cobranca);
ALTER TABLE royalties ADD INDEX IF NOT EXISTS idx_status_tipo (status, tipo_cobranca);

-- ========================================
-- Criar tabela de logs de boletos se não existir
-- ========================================

CREATE TABLE IF NOT EXISTS boletos_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    royalty_id INT NOT NULL,
    cora_id VARCHAR(255),
    acao VARCHAR(100) NOT NULL,
    status_anterior VARCHAR(50),
    status_novo VARCHAR(50),
    detalhes LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (royalty_id) REFERENCES royalties(id) ON DELETE CASCADE,
    INDEX idx_royalty_id (royalty_id),
    INDEX idx_created_at (created_at)
);

-- ========================================
-- Adicionar coluna de origem em contas_pagar se não existir
-- ========================================

ALTER TABLE contas_pagar ADD COLUMN IF NOT EXISTS boleto_url VARCHAR(500) AFTER payment_link_url;
ALTER TABLE contas_pagar ADD COLUMN IF NOT EXISTS origem VARCHAR(100) DEFAULT 'manual' AFTER boleto_url;

-- ========================================
-- Criar índice para melhor performance em contas_pagar
-- ========================================

ALTER TABLE contas_pagar ADD INDEX IF NOT EXISTS idx_origem (origem);
ALTER TABLE contas_pagar ADD INDEX IF NOT EXISTS idx_royalty_id (royalty_id);
