-- =====================================================
-- Script SQL - Módulo de Clientes e Cashback
-- CHOPPONv1 v3.1.0
-- =====================================================

-- Tabela de Clientes
CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estabelecimento_id INT NOT NULL,
    nome VARCHAR(255) NOT NULL,
    cpf VARCHAR(14) NOT NULL,
    email VARCHAR(255),
    telefone VARCHAR(20),
    endereco_rua VARCHAR(255),
    endereco_numero VARCHAR(20),
    endereco_complemento VARCHAR(100),
    endereco_bairro VARCHAR(100),
    endereco_cidade VARCHAR(100),
    endereco_estado VARCHAR(2),
    endereco_cep VARCHAR(10),
    data_nascimento DATE,
    pontos_cashback DECIMAL(10,2) DEFAULT 0.00,
    total_consumido DECIMAL(10,2) DEFAULT 0.00,
    status TINYINT(1) DEFAULT 1 COMMENT '1=Ativo, 0=Inativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_cpf_estabelecimento (cpf, estabelecimento_id),
    INDEX idx_estabelecimento (estabelecimento_id),
    INDEX idx_cpf (cpf),
    INDEX idx_email (email),
    INDEX idx_status (status),
    FOREIGN KEY (estabelecimento_id) REFERENCES estabelecimentos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Histórico de Consumo dos Clientes
CREATE TABLE IF NOT EXISTS clientes_consumo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    estabelecimento_id INT NOT NULL,
    pedido_id INT,
    bebida_id INT,
    bebida_nome VARCHAR(255) NOT NULL,
    quantidade DECIMAL(10,3) NOT NULL,
    valor_unitario DECIMAL(10,2) NOT NULL,
    valor_total DECIMAL(10,2) NOT NULL,
    pontos_ganhos DECIMAL(10,2) DEFAULT 0.00,
    data_consumo TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cliente (cliente_id),
    INDEX idx_estabelecimento (estabelecimento_id),
    INDEX idx_pedido (pedido_id),
    INDEX idx_data (data_consumo),
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (estabelecimento_id) REFERENCES estabelecimentos(id) ON DELETE CASCADE,
    FOREIGN KEY (bebida_id) REFERENCES bebidas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Regras de Cashback
CREATE TABLE IF NOT EXISTS cashback_regras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estabelecimento_id INT NOT NULL,
    nome VARCHAR(255) NOT NULL,
    descricao TEXT,
    tipo_regra ENUM('percentual', 'valor_fixo', 'pontos_por_real') DEFAULT 'percentual',
    valor_regra DECIMAL(10,2) NOT NULL COMMENT 'Percentual (ex: 5.00 = 5%), Valor fixo ou Pontos por real',
    valor_minimo DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Valor mínimo para aplicar a regra',
    valor_maximo DECIMAL(10,2) DEFAULT NULL COMMENT 'Valor máximo de cashback por transação',
    dias_semana VARCHAR(50) DEFAULT NULL COMMENT 'JSON array com dias da semana (0=Dom, 6=Sáb)',
    hora_inicio TIME DEFAULT NULL,
    hora_fim TIME DEFAULT NULL,
    data_inicio DATE DEFAULT NULL,
    data_fim DATE DEFAULT NULL,
    bebidas_especificas TEXT COMMENT 'JSON array com IDs de bebidas específicas',
    multiplicador DECIMAL(10,2) DEFAULT 1.00 COMMENT 'Multiplicador de pontos (ex: 2.00 = dobro)',
    ativo TINYINT(1) DEFAULT 1,
    prioridade INT DEFAULT 0 COMMENT 'Maior prioridade = aplicada primeiro',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_estabelecimento (estabelecimento_id),
    INDEX idx_ativo (ativo),
    INDEX idx_prioridade (prioridade),
    FOREIGN KEY (estabelecimento_id) REFERENCES estabelecimentos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Histórico de Cashback (Créditos e Resgates)
CREATE TABLE IF NOT EXISTS cashback_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    estabelecimento_id INT NOT NULL,
    tipo ENUM('credito', 'resgate', 'ajuste', 'expiracao') NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    saldo_anterior DECIMAL(10,2) NOT NULL,
    saldo_atual DECIMAL(10,2) NOT NULL,
    descricao TEXT,
    consumo_id INT DEFAULT NULL COMMENT 'ID do consumo que gerou o crédito',
    regra_id INT DEFAULT NULL COMMENT 'ID da regra aplicada',
    user_id INT DEFAULT NULL COMMENT 'Usuário que fez o ajuste/resgate',
    data_operacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cliente (cliente_id),
    INDEX idx_estabelecimento (estabelecimento_id),
    INDEX idx_tipo (tipo),
    INDEX idx_data (data_operacao),
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (estabelecimento_id) REFERENCES estabelecimentos(id) ON DELETE CASCADE,
    FOREIGN KEY (consumo_id) REFERENCES clientes_consumo(id) ON DELETE SET NULL,
    FOREIGN KEY (regra_id) REFERENCES cashback_regras(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Configurações de Cashback por Estabelecimento
CREATE TABLE IF NOT EXISTS cashback_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estabelecimento_id INT NOT NULL UNIQUE,
    ativo TINYINT(1) DEFAULT 1,
    permite_resgate TINYINT(1) DEFAULT 1,
    valor_minimo_resgate DECIMAL(10,2) DEFAULT 10.00,
    pontos_expiram TINYINT(1) DEFAULT 0,
    dias_expiracao INT DEFAULT 365 COMMENT 'Dias até expiração dos pontos',
    mensagem_boas_vindas TEXT,
    mensagem_resgate TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (estabelecimento_id) REFERENCES estabelecimentos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir regra padrão de cashback (5% de volta)
INSERT INTO cashback_regras (estabelecimento_id, nome, descricao, tipo_regra, valor_regra, ativo, prioridade)
SELECT 
    id,
    'Cashback Padrão 5%',
    'Ganhe 5% de volta em pontos em todas as compras',
    'percentual',
    5.00,
    1,
    0
FROM estabelecimentos
WHERE NOT EXISTS (
    SELECT 1 FROM cashback_regras WHERE estabelecimento_id = estabelecimentos.id
);

-- Inserir configuração padrão de cashback
INSERT INTO cashback_config (estabelecimento_id, ativo, permite_resgate, valor_minimo_resgate)
SELECT 
    id,
    1,
    1,
    10.00
FROM estabelecimentos
WHERE NOT EXISTS (
    SELECT 1 FROM cashback_config WHERE estabelecimento_id = estabelecimentos.id
);

-- =====================================================
-- Views úteis
-- =====================================================

-- View de Clientes com Estatísticas
CREATE OR REPLACE VIEW vw_clientes_stats AS
SELECT 
    c.*,
    COUNT(DISTINCT cc.id) as total_consumos,
    COALESCE(SUM(cc.valor_total), 0) as total_gasto,
    COALESCE(SUM(cc.pontos_ganhos), 0) as total_pontos_ganhos,
    MAX(cc.data_consumo) as ultima_compra,
    DATEDIFF(NOW(), MAX(cc.data_consumo)) as dias_sem_comprar
FROM clientes c
LEFT JOIN clientes_consumo cc ON c.id = cc.cliente_id
GROUP BY c.id;

-- View de Ranking de Clientes
CREATE OR REPLACE VIEW vw_clientes_ranking AS
SELECT 
    c.id,
    c.nome,
    c.email,
    c.pontos_cashback,
    c.total_consumido,
    COUNT(DISTINCT cc.id) as total_compras,
    RANK() OVER (PARTITION BY c.estabelecimento_id ORDER BY c.total_consumido DESC) as ranking_consumo,
    RANK() OVER (PARTITION BY c.estabelecimento_id ORDER BY c.pontos_cashback DESC) as ranking_pontos
FROM clientes c
LEFT JOIN clientes_consumo cc ON c.id = cc.cliente_id
WHERE c.status = 1
GROUP BY c.id;

-- =====================================================
-- Triggers
-- =====================================================

-- Trigger para atualizar total consumido do cliente
DELIMITER $$
CREATE TRIGGER trg_clientes_consumo_after_insert
AFTER INSERT ON clientes_consumo
FOR EACH ROW
BEGIN
    UPDATE clientes 
    SET total_consumido = total_consumido + NEW.valor_total
    WHERE id = NEW.cliente_id;
END$$
DELIMITER ;

-- Trigger para registrar histórico de cashback
DELIMITER $$
CREATE TRIGGER trg_cashback_historico_after_insert
AFTER INSERT ON cashback_historico
FOR EACH ROW
BEGIN
    UPDATE clientes 
    SET pontos_cashback = NEW.saldo_atual
    WHERE id = NEW.cliente_id;
END$$
DELIMITER ;

-- =====================================================
-- Índices adicionais para performance
-- =====================================================

ALTER TABLE clientes ADD INDEX idx_nome (nome);
ALTER TABLE clientes ADD INDEX idx_pontos (pontos_cashback);
ALTER TABLE clientes ADD INDEX idx_created (created_at);
ALTER TABLE clientes_consumo ADD INDEX idx_bebida_nome (bebida_nome);
ALTER TABLE clientes_consumo ADD INDEX idx_valor (valor_total);

-- =====================================================
-- Comentários nas tabelas
-- =====================================================

ALTER TABLE clientes COMMENT = 'Cadastro de clientes do estabelecimento';
ALTER TABLE clientes_consumo COMMENT = 'Histórico de consumo dos clientes';
ALTER TABLE cashback_regras COMMENT = 'Regras de cashback configuráveis';
ALTER TABLE cashback_historico COMMENT = 'Histórico de movimentações de cashback';
ALTER TABLE cashback_config COMMENT = 'Configurações gerais de cashback por estabelecimento';

-- =====================================================
-- Fim do script
-- =====================================================
