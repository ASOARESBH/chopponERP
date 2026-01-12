# 📧 Sistema de Alertas Automáticos por E-mail

**Versão**: 2.0  
**Data**: 2025-12-13

---

## 1. Visão Geral

Sistema completo de notificações automáticas por e-mail via SMTP para alertar sobre eventos críticos do sistema:

- 📦 **Estoque Mínimo Atingido**
- 💳 **Contas a Pagar Vencendo**
- 👑 **Royalties Vencendo**
- 🎉 **Promoções Expirando**
- 🚧 **TAPs com Problemas**
- 💰 Vendas realizadas (já existente)
- ⚠️ Volume crítico de barris (já existente)

---

## 2. Instalação

### Passo 1: Aplicar Migrations no Banco de Dados

Execute os arquivos SQL para adicionar os novos campos e tabelas:

```bash
mysql -u seu_usuario -p seu_banco < sql/add_email_smtp_alerts.sql
```

Ou importe via phpMyAdmin.

### Passo 2: Configurar SMTP

1. Acesse **Integrações > Config. SMTP**
2. Preencha:
   - **Servidor SMTP** (ex: smtp.gmail.com)
   - **Porta** (587 para TLS, 465 para SSL)
   - **Usuário SMTP**
   - **Senha SMTP**
   - **E-mail Remetente**
   - **Nome do Remetente**
3. Clique em **Salvar Configuração SMTP**

**Provedores Comuns:**
- **Gmail**: smtp.gmail.com:587 (TLS) - Use senha de app
- **Outlook**: smtp.office365.com:587 (TLS)
- **SendGrid**: smtp.sendgrid.net:587 (TLS)

### Passo 3: Configurar Alertas

1. Acesse **Integrações > Config. E-mail**
2. Selecione o estabelecimento
3. Preencha o **E-mail para Alertas**
4. Marque os alertas desejados:
   - ✅ Estoque mínimo atingido
   - ✅ Contas a pagar vencendo
   - ✅ Royalties vencendo
   - ✅ Promoções expirando
   - ✅ TAPs com problemas
5. Configure:
   - **Dias antes do vencimento**: Quantos dias antes alertar (padrão: 3)
   - **Dias após vencimento**: Quantos dias após alertar (padrão: 2)
6. Clique em **Salvar Configuração**

### Passo 4: Testar Envio

1. Na mesma página, role até **"Testar Envio de E-mail"**
2. Digite seu e-mail
3. Clique em **"Enviar Teste"**
4. Verifique sua caixa de entrada

### Passo 5: Configurar o Cron Job

Adicione a seguinte linha ao crontab para executar a cada hora:

```bash
crontab -e
```

Adicione:

```
0 * * * * /usr/bin/php /caminho/completo/cron/email_alerts.php >> /var/log/email_alerts.log 2>&1
```

**Recomendações de frequência:**
- **A cada 1 hora**: Ideal para a maioria dos casos
- **A cada 30 minutos**: Para operações críticas
- **A cada 6 horas**: Para ambientes de baixo volume

---

## 3. Funcionalidades

### 3.1. Alerta de Estoque Mínimo

**Quando é enviado:**
- Quando o estoque atual de um produto ≤ estoque mínimo
- Apenas uma vez por dia por produto

**Exemplo de e-mail:**

```
Assunto: ⚠️ Alerta: Estoque Mínimo - Barril Heineken 30L

Estabelecimento: CHOPP ON - JABOTICATUBAS
Produto: Barril Heineken 30L
Código: BAR-HEI-30

Estoque Atual: 2 unidades
Estoque Mínimo: 5 unidades
Quantidade a Repor: 3 unidades
```

### 3.2. Alerta de Contas a Pagar

**Quando é enviado:**
- X dias antes do vencimento (configurável)
- Até Y dias após o vencimento (configurável)
- Apenas uma vez por dia por conta

**Exemplo de e-mail:**

```
Assunto: 💳 Alerta: Conta a Pagar - Fornecimento de Barris

Estabelecimento: CHOPP ON - JABOTICATUBAS
Descrição: Fornecimento de Barris
Fornecedor: Cervejaria ABC

Valor: R$ 5.420,00
Vencimento: 07/12/2025
⚠️ Vence em 2 dias
```

### 3.3. Alerta de Royalties

**Quando é enviado:**
- X dias antes do vencimento (configurável)
- Até Y dias após o vencimento (configurável)
- Apenas uma vez por dia por royalty

**Exemplo de e-mail:**

```
Assunto: 👑 Alerta: Royalty Vencendo

Estabelecimento: CHOPP ON - JABOTICATUBAS
Período: 01/12/2025 a 31/12/2025

Valor: R$ 3.500,00
Vencimento: 10/12/2025
⚠️ Vence em 3 dias
```

### 3.4. Alerta de Promoções Expirando

**Quando é enviado:**
- X dias antes da data fim (configurável)
- Apenas uma vez por dia por promoção

**Exemplo de e-mail:**

```
Assunto: 🎉 Alerta: Promoção Expirando - Black Friday Chopp

Estabelecimento: CHOPP ON - JABOTICATUBAS
Promoção: Black Friday Chopp
Desconto: 50%

Data Fim: 08/12/2025
⚠️ Expira em 3 dias
```

---

## 4. Tabelas do Banco de Dados

### 4.1. smtp_config (nova)

Armazena configurações SMTP por estabelecimento:

| Campo | Tipo | Descrição |
|:---|:---|:---|
| `estabelecimento_id` | BIGINT | ID do estabelecimento |
| `smtp_host` | VARCHAR(255) | Servidor SMTP |
| `smtp_port` | INT | Porta SMTP |
| `smtp_secure` | ENUM | tls, ssl ou none |
| `smtp_username` | VARCHAR(255) | Usuário SMTP |
| `smtp_password` | VARCHAR(255) | Senha SMTP |
| `from_email` | VARCHAR(255) | E-mail remetente |
| `from_name` | VARCHAR(255) | Nome do remetente |
| `status` | TINYINT(1) | Ativo/Inativo |

### 4.2. email_config (atualizada)

Novos campos adicionados:

| Campo | Tipo | Descrição |
|:---|:---|:---|
| `notificar_estoque_minimo` | TINYINT(1) | Ativar alertas de estoque |
| `notificar_royalties` | TINYINT(1) | Ativar alertas de royalties |
| `notificar_promocoes` | TINYINT(1) | Ativar alertas de promoções |
| `notificar_taps` | TINYINT(1) | Ativar alertas de TAPs |
| `dias_antes_vencimento` | INT | Dias antes para alertar |
| `dias_apos_vencimento` | INT | Dias após para alertar |

### 4.3. email_notifications_log (nova)

Registra todas as notificações enviadas:

| Campo | Descrição |
|:---|:---|
| `id` | ID único |
| `estabelecimento_id` | Estabelecimento relacionado |
| `tipo` | Tipo de alerta |
| `referencia_id` | ID do produto/conta/promoção |
| `destinatario` | E-mail do destinatário |
| `assunto` | Assunto do e-mail |
| `mensagem` | Conteúdo HTML |
| `status` | enviado / erro / pendente |
| `erro_mensagem` | Mensagem de erro (se houver) |
| `enviado_em` | Data/hora do envio |

### 4.4. email_alerts_sent (nova)

Controla alertas já enviados (evita duplicatas):

| Campo | Descrição |
|:---|:---|
| `estabelecimento_id` | Estabelecimento |
| `tipo` | Tipo de alerta |
| `referencia_id` | ID do registro |
| `data_envio` | Data do envio |

---

## 5. Classe EmailNotifications

### Métodos Principais

```php
// Verificar estoque mínimo
$alertas = $email->verificarEstoqueMinimo();

// Verificar contas a pagar
$alertas = $email->verificarContasPagar();

// Verificar royalties
$alertas = $email->verificarRoyalties();

// Verificar promoções
$alertas = $email->verificarPromocoes();

// Executar todas as verificações
$total = $email->executarTodasVerificacoes();

// Enviar e-mail de teste
$sucesso = $email->enviarEmailTeste($estabelecimentoId, $destinatario);
```

---

## 6. Logs

### Localização

Os logs são salvos em:

```
/caminho/do/projeto/logs/email_alerts_YYYY-MM-DD.log
```

### Exemplo de Log

```
[2025-12-13 14:30:00] ========================================
[2025-12-13 14:30:00] Iniciando verificação de alertas por E-mail
[2025-12-13 14:30:00] ========================================
[2025-12-13 14:30:01] ✓ Conexão com banco de dados estabelecida
[2025-12-13 14:30:01] ✓ Classe EmailNotifications instanciada
[2025-12-13 14:30:01] 
--- Verificando Estoque Mínimo ---
[2025-12-13 14:30:02] ✓ Alertas de estoque enviados: 3
[2025-12-13 14:30:02] 
--- Verificando Contas a Pagar ---
[2025-12-13 14:30:03] ✓ Alertas de contas enviados: 2
[2025-12-13 14:30:03] 
--- Verificando Royalties ---
[2025-12-13 14:30:04] ✓ Alertas de royalties enviados: 1
[2025-12-13 14:30:04] 
--- Verificando Promoções ---
[2025-12-13 14:30:05] ✓ Alertas de promoções enviados: 1
[2025-12-13 14:30:05] 
========================================
[2025-12-13 14:30:05] Verificação concluída com sucesso!
[2025-12-13 14:30:05] Total de alertas enviados: 7
[2025-12-13 14:30:05] ========================================
```

---

## 7. Testes

### Testar Manualmente

Execute o script diretamente:

```bash
php /caminho/completo/cron/email_alerts.php
```

### Verificar Logs

```bash
tail -f /var/log/email_alerts.log
```

### Consultar Notificações Enviadas

```sql
SELECT * FROM email_notifications_log 
WHERE DATE(created_at) = CURDATE()
ORDER BY created_at DESC;
```

### Testar SMTP

Use a funcionalidade de teste na página de configuração:
1. Acesse **Integrações > Config. E-mail**
2. Role até **"Testar Envio de E-mail"**
3. Digite seu e-mail e clique em **"Enviar Teste"**

---

## 8. Solução de Problemas

### E-mails não estão sendo enviados

1. Verifique se o cron está rodando:
   ```bash
   crontab -l
   ```

2. Verifique os logs:
   ```bash
   tail -50 /var/log/email_alerts.log
   ```

3. Verifique se as notificações estão ativas no painel

4. Teste a conexão SMTP no painel

5. Verifique a tabela `email_notifications_log` para erros:
   ```sql
   SELECT * FROM email_notifications_log 
   WHERE status = 'erro' 
   ORDER BY created_at DESC LIMIT 10;
   ```

### Alertas duplicados

O sistema já possui controle de duplicatas. Cada alerta é enviado apenas **uma vez por dia** por item.

### Erro de autenticação SMTP

**Gmail:**
- Ative a verificação em 2 etapas
- Gere uma "Senha de App" em: https://myaccount.google.com/apppasswords
- Use a senha de app no lugar da senha normal

**Outlook:**
- Verifique se a conta permite SMTP
- Use a senha normal da conta

### E-mails caindo no spam

1. Configure SPF, DKIM e DMARC no seu domínio
2. Use um serviço de e-mail transacional (SendGrid, Mailgun)
3. Verifique se o IP do servidor não está em blacklist

---

## 9. Personalização

### Alterar Frequência do Cron

Edite o crontab:

```bash
# A cada 30 minutos
*/30 * * * * /usr/bin/php /caminho/cron/email_alerts.php

# A cada 6 horas
0 */6 * * * /usr/bin/php /caminho/cron/email_alerts.php

# Apenas em horário comercial (8h às 18h)
0 8-18 * * * /usr/bin/php /caminho/cron/email_alerts.php
```

### Customizar Templates de E-mail

Edite os métodos em `includes/EmailNotifications.php`:

- `montarEmailEstoque()`
- `montarEmailConta()`
- `montarEmailRoyalty()`
- `montarEmailPromocao()`
- `montarTemplateEmail()` (template base)

### Adicionar Novos Tipos de Alerta

1. Adicione o campo na tabela `email_config`
2. Adicione o checkbox na página `email_config.php`
3. Crie o método de verificação em `EmailNotifications.php`
4. Adicione a chamada no script `cron/email_alerts.php`

---

## 10. Integração com Telegram

O sistema possui integração paralela com Telegram. Você pode ativar ambos simultaneamente:

- **E-mail**: Alertas detalhados com HTML formatado
- **Telegram**: Alertas rápidos e instantâneos

Configure ambos em **Integrações** para máxima cobertura!

---

**Fim da Documentação**
