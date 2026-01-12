# Guia de Instalação - Chopp On Tap v3.1.0

**Data:** 04 de Dezembro de 2025  
**Versão:** 3.1.0  
**Status:** Pronto para Produção

---

## 📋 O que foi atualizado

### ✅ Correções Implementadas
- ✅ Página branca em smtp_config.php - **RESOLVIDO**
- ✅ Sistema de e-mail robusto - **IMPLEMENTADO**
- ✅ Integração Stripe completa - **IMPLEMENTADO**
- ✅ Suporte a Banco Cora com boleto - **IMPLEMENTADO**
- ✅ Sistema de logging centralizado - **IMPLEMENTADO**
- ✅ Histórico de operações - **IMPLEMENTADO**
- ✅ Modo de teste para e-mails - **IMPLEMENTADO**

### 📁 Arquivos Novos/Atualizados

```
includes/
├── config.php                 ✅ ATUALIZADO (v3.1.0)
├── EmailSender.php            ✅ NOVO (v2.0)
├── StripeManager.php          ✅ NOVO (v2.0)
├── CoraManager.php            ✅ NOVO (v1.0)
├── RoyaltiesManager.php       ✅ ATUALIZADO
└── logger.php                 ✅ EXISTENTE

admin/
├── smtp_config.php            ✅ CORRIGIDO
└── ajax/
    └── gerar_boleto_cora.php  ✅ NOVO

sql/
├── schema_email_stripe_v2.sql ✅ NOVO
└── add_boleto_fields.sql      ✅ NOVO
```

---

## 🚀 Instalação Rápida (5 minutos)

### Passo 1: Fazer Backup

```bash
# Backup do banco de dados
mysqldump -u seu_usuario -p seu_banco > backup_antes_v3.1.0.sql

# Backup de arquivos
cp -r seu_site_atual seu_site_backup
```

### Passo 2: Copiar Arquivos

```bash
# Copiar arquivos PHP para seu servidor
cp -r PHP/* /caminho/para/seu/site/

# Verificar permissões
chmod 755 /caminho/para/seu/site/logs
chmod 755 /caminho/para/seu/site/uploads
chmod 755 /caminho/para/seu/site/certs
```

### Passo 3: Executar Migrações de Banco

```bash
# Conectar ao MySQL
mysql -u seu_usuario -p seu_banco < sql/schema_email_stripe_v2.sql
mysql -u seu_usuario -p seu_banco < sql/add_boleto_fields.sql
```

### Passo 4: Configurar SMTP

Acessar: `seu_site.com.br/admin/smtp_config.php`

Preencher:
- **Servidor SMTP:** smtp.gmail.com
- **Porta:** 587
- **Segurança:** TLS
- **Usuário:** seu-email@gmail.com
- **Senha:** sua-senha-app
- **E-mail Remetente:** seu-email@gmail.com
- **Nome Remetente:** Chopp On Tap

### Passo 5: Testar

Acessar: `seu_site.com.br/admin/smtp_config.php`

Clicar em "Enviar E-mail de Teste"

✅ Se receber o e-mail, está funcionando!

---

## ⚙️ Configuração Detalhada

### Configurar SMTP

#### Gmail
```
Servidor: smtp.gmail.com
Porta: 587
Segurança: TLS
Usuário: seu-email@gmail.com
Senha: GERAR SENHA DE APP
```

**Como gerar senha de app:**
1. Acessar https://myaccount.google.com/security
2. Ativar "Verificação em 2 etapas"
3. Ir para "Senhas de app"
4. Selecionar "Mail" e "Windows Computer"
5. Copiar a senha gerada

#### Outlook
```
Servidor: smtp-mail.outlook.com
Porta: 587
Segurança: TLS
Usuário: seu-email@outlook.com
Senha: sua-senha
```

#### SendGrid
```
Servidor: smtp.sendgrid.net
Porta: 587
Segurança: TLS
Usuário: apikey
Senha: sua-chave-api
```

### Configurar Stripe

Via banco de dados:

```sql
INSERT INTO stripe_config 
(nome_config, api_key, api_secret, ambiente, ativo)
VALUES 
('Stripe Produção', 'pk_live_xxxxx', 'sk_live_xxxxx', 'live', 1);
```

**Onde obter:**
1. Acessar https://dashboard.stripe.com
2. Settings > API Keys
3. Copiar Publishable Key (pk_...)
4. Copiar Secret Key (sk_...)

### Configurar Cora

Via banco de dados:

```sql
INSERT INTO cora_config 
(estabelecimento_id, client_id, certificado_path, chave_privada_path, ambiente, ativo)
VALUES 
(1, 'seu-client-id', '/certs/cora_cert.pem', '/certs/cora_key.key', 'sandbox', 1);
```

**Onde obter:**
1. Portal do Banco Cora
2. Configurações > API
3. Fazer download do certificado (.pem)
4. Fazer download da chave privada (.key)
5. Salvar em `/certs/` com permissões 0600

---

## ✅ Testes

### Teste 1: SMTP

```php
<?php
require_once 'includes/config.php';
require_once 'includes/EmailSender.php';

$resultado = EmailSender::enviarEmailTeste('seu-email@gmail.com');

if ($resultado['sucesso']) {
    echo "✅ E-mail enviado com sucesso!";
} else {
    echo "❌ Erro: " . $resultado['mensagem'];
}
?>
```

### Teste 2: Stripe

```php
<?php
require_once 'includes/config.php';
require_once 'includes/StripeManager.php';

try {
    $stripe = new StripeManager();
    $customerId = $stripe->criarOuObterCustomer([
        'id' => 1,
        'nome' => 'Teste',
        'email' => 'teste@email.com'
    ]);
    echo "✅ Customer criado: $customerId";
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage();
}
?>
```

### Teste 3: Cora

```php
<?php
require_once 'includes/config.php';
require_once 'includes/CoraManager.php';

try {
    $conn = getDBConnection();
    $cora = new CoraManager($conn, 1);
    
    $resultado = $cora->gerarBoleto([
        'valor' => 100.00,
        'descricao' => 'Teste',
        'data_vencimento' => '2025-12-31',
        'nome_pagador' => 'Teste',
        'email_pagador' => 'teste@email.com'
    ]);
    
    if ($resultado['success']) {
        echo "✅ Boleto gerado!";
    } else {
        echo "❌ Erro: " . $resultado['message'];
    }
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage();
}
?>
```

---

## 🔍 Verificação de Instalação

### Verificar Banco de Dados

```sql
-- Verificar tabelas criadas
SHOW TABLES LIKE 'email_%';
SHOW TABLES LIKE '%stripe%';
SHOW TABLES LIKE '%cora%';
SHOW TABLES LIKE 'logs_integracao';

-- Verificar campos adicionados
DESCRIBE royalties;
DESCRIBE estabelecimentos;
```

### Verificar Arquivos

```bash
# Verificar se arquivos existem
ls -la includes/config.php
ls -la includes/EmailSender.php
ls -la includes/StripeManager.php
ls -la includes/CoraManager.php
ls -la admin/smtp_config.php

# Verificar permissões
chmod 644 includes/*.php
chmod 644 admin/*.php
```

### Verificar Logs

```bash
# Verificar se diretório de logs existe
ls -la logs/

# Verificar últimos logs
tail -50 logs/system_*.log
tail -50 logs/errors.log
```

---

## 🚨 Troubleshooting

### Erro: "Nenhuma configuração de e-mail ativa"

```sql
-- Verificar configuração
SELECT * FROM email_config WHERE ativo = TRUE;

-- Inserir se não existir
INSERT INTO email_config 
(nome_config, smtp_host, smtp_port, smtp_secure, smtp_user, smtp_password, from_email, from_name, ativo)
VALUES 
('Gmail', 'smtp.gmail.com', 587, 'tls', 'seu-email@gmail.com', 'sua-senha-app', 'seu-email@gmail.com', 'Chopp On Tap', 1);
```

### Erro: "Página branca em smtp_config.php"

```bash
# Verificar erros
php -l admin/smtp_config.php

# Verificar logs
tail -100 /var/log/php-errors.log
```

### Erro: "Integração Stripe não ativa"

```sql
-- Verificar configuração
SELECT * FROM stripe_config WHERE ativo = TRUE;

-- Inserir se não existir
INSERT INTO stripe_config 
(nome_config, api_key, api_secret, ambiente, ativo)
VALUES 
('Stripe', 'pk_test_xxxxx', 'sk_test_xxxxx', 'test', 1);
```

### Erro: "Cora não configurado"

```sql
-- Verificar configuração
SELECT * FROM cora_config WHERE ativo = TRUE;

-- Inserir se não existir
INSERT INTO cora_config 
(estabelecimento_id, client_id, certificado_path, chave_privada_path, ambiente, ativo)
VALUES 
(1, 'seu-client-id', '/certs/cora_cert.pem', '/certs/cora_key.key', 'sandbox', 1);
```

---

## 📊 Monitorar Sistema

### Ver E-mails Enviados

```sql
SELECT * FROM email_historico 
WHERE DATE(data_envio) = CURDATE()
ORDER BY data_envio DESC;
```

### Ver Erros de E-mail

```sql
SELECT * FROM email_historico 
WHERE status = 'erro'
ORDER BY data_envio DESC;
```

### Ver Logs de Integração

```sql
SELECT * FROM logs_integracao 
WHERE DATE(data_log) = CURDATE()
ORDER BY data_log DESC;
```

### Ver Erros de Integração

```sql
SELECT * FROM logs_integracao 
WHERE status = 'erro'
ORDER BY data_log DESC;
```

---

## 📝 Checklist Final

- [ ] Backup realizado
- [ ] Arquivos copiados
- [ ] Migrações SQL executadas
- [ ] SMTP configurado
- [ ] Stripe configurado
- [ ] Cora configurado
- [ ] E-mail de teste enviado com sucesso
- [ ] Boleto gerado com sucesso
- [ ] Nenhum erro em logs
- [ ] Performance aceitável

---

## 🎉 Conclusão

Seu sistema Chopp On Tap v3.1.0 está pronto com:

✅ SMTP robusto e testado  
✅ Integração Stripe completa  
✅ Suporte a Banco Cora  
✅ Histórico de operações  
✅ Logging detalhado  
✅ Modo de teste para e-mails  
✅ Tratamento de erros robusto  
✅ Segurança melhorada  

---

## 📞 Suporte

Se encontrar problemas:

1. Verificar logs em `/logs/`
2. Verificar banco de dados
3. Testar manualmente com scripts de teste
4. Verificar permissões de arquivos

---

**Desenvolvido por:** Manus AI  
**Data:** 04/12/2025  
**Versão:** 3.1.0
