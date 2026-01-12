-- Tabela de Configuração SMTP
CREATE TABLE IF NOT EXISTS smtp_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    host VARCHAR(255) NOT NULL COMMENT 'Servidor SMTP (ex: smtp.gmail.com)',
    port INT NOT NULL DEFAULT 587 COMMENT 'Porta SMTP (587 para TLS, 465 para SSL)',
    username VARCHAR(255) NOT NULL COMMENT 'Usuário/E-mail SMTP',
    password VARCHAR(255) NOT NULL COMMENT 'Senha SMTP (criptografada)',
    from_email VARCHAR(255) NOT NULL COMMENT 'E-mail remetente',
    from_name VARCHAR(255) NOT NULL COMMENT 'Nome do remetente',
    encryption VARCHAR(10) DEFAULT 'tls' COMMENT 'Tipo de criptografia (tls, ssl, none)',
    ativo BOOLEAN DEFAULT TRUE COMMENT 'Configuração ativa',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir configuração padrão (exemplo)
INSERT INTO smtp_config (host, port, username, password, from_email, from_name, encryption, ativo) 
VALUES ('smtp.gmail.com', 587, 'seu-email@gmail.com', '', 'seu-email@gmail.com', 'Sistema Chopp ON', 'tls', FALSE)
ON DUPLICATE KEY UPDATE id=id;
