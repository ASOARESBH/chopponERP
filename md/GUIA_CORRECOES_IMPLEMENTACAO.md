# Guia de Correções - Integração Cora e Stripe

**Data**: 2025-12-04  
**Versão**: 1.1 - Corrigida  
**Status**: Pronto para Implementação

---

## 📋 Problemas Corrigidos

### 1. ❌ Erro: Tabela 'payment_gateway_config' não existe

**Solução**: Criar script de setup automático

**Como fazer:**

1. Acesse: `https://seu-dominio.com.br/admin/setup_payment_gateway.php`
2. Clique em "🚀 Executar Setup Agora"
3. O sistema criará automaticamente as tabelas:
   - `payment_gateway_config`
   - `faturamentos`
   - `faturamentos_historico`

**Ou manualmente via phpMyAdmin:**

1. Acesse phpMyAdmin
2. Selecione seu banco de dados
3. Vá em "Importar"
4. Selecione o arquivo `sql/payment_gateway_config.sql`
5. Clique em "Executar"

---

### 2. ❌ Menu Financeiro não mostra Faturamento

**Solução**: Menu atualizado automaticamente

**Verificação:**

1. Acesse o painel administrativo
2. Vá em "Financeiro"
3. Você deve ver agora:
   - Taxas de Juros
   - Contas a Pagar
   - Royalties
   - **Faturamento** ← NOVO

---

### 3. ❌ Royalties não gera boleto Cora

**Solução**: Integração completa com RoyaltiesManagerV3

**Como funciona agora:**

1. Ao criar um royalty selecionando "Banco Cora"
2. O sistema automaticamente:
   - Valida os dados
   - Emite boleto via API Cora
   - Cria registro em `faturamentos`
   - Atualiza status do royalty
   - Registra no histórico

**Fluxo Completo:**

```
Criar Royalty (Cora)
    ↓
Validar dados
    ↓
Inserir em royalties
    ↓
Emitir boleto Cora (OAuth 2.0)
    ↓
Criar registro em faturamentos
    ↓
Atualizar status para "link_gerado"
    ↓
Sucesso!
```

---

## 🚀 Passos para Implementar as Correções

### Passo 1: Copiar Novos Arquivos

Via FileZilla, copie os seguintes arquivos:

```
admin/setup_payment_gateway.php      → /seu_dominio/admin/
includes/RoyaltiesManagerV3.php      → /seu_dominio/includes/
```

### Passo 2: Executar Setup

1. Acesse: `https://seu-dominio.com.br/admin/setup_payment_gateway.php`
2. Clique em "🚀 Executar Setup Agora"
3. Aguarde a mensagem de sucesso

### Passo 3: Configurar Credenciais Cora

1. Renomeie `cora_config_v2.example.php` para `cora_config_v2.php`
2. Edite com suas credenciais:

```php
define('CORA_CLIENT_ID', 'seu_client_id');
define('CORA_CLIENT_SECRET', 'seu_client_secret');
define('CORA_ENVIRONMENT', 'stage'); // ou 'production'
define('CORA_BENEFICIARY_NAME', 'Sua Empresa LTDA');
define('CORA_BENEFICIARY_DOCUMENT', '12345678000190');
define('CORA_BENEFICIARY_EMAIL', 'financeiro@empresa.com.br');
```

3. Salve o arquivo
4. Via SSH: `chmod 600 cora_config_v2.php`

### Passo 4: Configurar Credenciais Stripe

Via phpMyAdmin, execute:

```sql
INSERT INTO payment_gateway_config (
    estabelecimento_id,
    gateway_type,
    environment,
    ativo,
    config_data,
    created_at
) VALUES (
    1,
    'stripe',
    'test',
    1,
    JSON_OBJECT(
        'secret_key', 'sk_test_seu_secret_key',
        'webhook_secret', 'whsec_seu_webhook_secret',
        'environment', 'test'
    ),
    NOW()
);
```

### Passo 5: Agendar CRON

Via cPanel ou SSH:

```bash
0 * * * * /usr/bin/php /seu_dominio/cron/polling_faturamentos.php
```

---

## ✅ Testar Implementação

### Teste 1: Criar Royalty com Boleto Cora

1. Acesse: `https://seu-dominio.com.br/admin/financeiro_royalties.php`
2. Clique em "+ Novo Lançamento"
3. Preencha os dados:
   - **Estabelecimento**: Selecione um
   - **Período**: 01/12/2025 a 31/12/2025
   - **Descrição**: Royalties Dezembro
   - **Valor Faturamento**: R$ 1.000,00
   - **Tipo de Cobrança**: **Banco Cora** ← IMPORTANTE
   - **E-mail**: email@empresa.com.br
   - **Data Vencimento**: 31/01/2026
4. Clique em "Criar Royalty"
5. Verifique se:
   - Royalty foi criado com status "link_gerado"
   - Boleto foi emitido (verifique logs)
   - Registro aparece em Faturamento

### Teste 2: Visualizar Boleto

1. Acesse: `https://seu-dominio.com.br/admin/financeiro_faturamento.php`
2. Você deve ver o faturamento criado
3. Clique no ícone de boleto
4. Verifique:
   - Código de barras
   - Linha digitável
   - QR Code PIX (se disponível)

### Teste 3: Criar Royalty com Fatura Stripe

1. Acesse: `https://seu-dominio.com.br/admin/financeiro_royalties.php`
2. Clique em "+ Novo Lançamento"
3. Preencha os dados (igual ao teste 1)
4. **Tipo de Cobrança**: Stripe
5. Clique em "Criar Royalty"
6. Verifique se fatura foi criada no Stripe

### Teste 4: Polling Automático

1. Execute manualmente:
   ```bash
   php /seu_dominio/cron/polling_faturamentos.php
   ```

2. Verifique logs:
   ```bash
   tail -f /seu_dominio/logs/polling_faturamentos.log
   ```

3. Verifique se status foi atualizado no banco

---

## 📁 Arquivos Modificados/Criados

### Criados:
- `admin/setup_payment_gateway.php` - Setup automático
- `includes/RoyaltiesManagerV3.php` - Gerenciador com Cora
- `GUIA_CORRECOES_IMPLEMENTACAO.md` - Este arquivo

### Modificados:
- `includes/header.php` - Adicionado link de Faturamento
- `admin/financeiro_royalties.php` - Usa RoyaltiesManagerV3

### Já Existentes (não modificados):
- `includes/cora_api_v2.php` - API Cora
- `admin/financeiro_faturamento.php` - Página de faturamento
- `admin/ajax/gerar_boleto_link.php` - Visualização de boletos
- `cron/polling_faturamentos.php` - Polling automático
- `sql/payment_gateway_config.sql` - Script SQL

---

## 🔍 Verificação de Instalação

### Verificar Banco de Dados

```sql
-- Verificar se tabelas existem
SHOW TABLES LIKE 'payment_gateway%';
SHOW TABLES LIKE 'faturamentos%';

-- Verificar estrutura
DESCRIBE payment_gateway_config;
DESCRIBE faturamentos;
DESCRIBE faturamentos_historico;

-- Verificar dados
SELECT * FROM payment_gateway_config;
SELECT * FROM faturamentos;
```

### Verificar Arquivos

```bash
# Verificar se arquivos existem
ls -la /seu_dominio/admin/setup_payment_gateway.php
ls -la /seu_dominio/includes/RoyaltiesManagerV3.php
ls -la /seu_dominio/cora_config_v2.php

# Verificar permissões
stat /seu_dominio/cora_config_v2.php
```

### Verificar Logs

```bash
# Logs de Cora
tail -f /seu_dominio/logs/cora_v2.log

# Logs de Royalties
tail -f /seu_dominio/logs/royalties_v2.log

# Logs de Polling
tail -f /seu_dominio/logs/polling_faturamentos.log
```

---

## 🐛 Troubleshooting

### Erro: "Cora não está configurado"

**Causa**: Arquivo `cora_config_v2.php` não existe ou credenciais vazias

**Solução**:
1. Renomeie `cora_config_v2.example.php` para `cora_config_v2.php`
2. Preencha as credenciais corretamente
3. Verifique se `CORA_CLIENT_ID` e `CORA_CLIENT_SECRET` estão preenchidos

### Erro: "Tabela 'faturamentos' não existe"

**Causa**: Setup não foi executado

**Solução**:
1. Acesse `https://seu-dominio.com.br/admin/setup_payment_gateway.php`
2. Clique em "🚀 Executar Setup Agora"
3. Verifique se as tabelas foram criadas

### Erro: "Autenticação falhou" ao emitir boleto

**Causa**: Client ID ou Client Secret inválidos

**Solução**:
1. Verifique as credenciais em Conta Cora > Integrações via APIs
2. Copie novamente (sem espaços em branco)
3. Atualize `cora_config_v2.php`

### Royalty criado mas boleto não gerado

**Causa**: Erro na integração Cora

**Solução**:
1. Verifique logs: `/logs/cora_v2.log`
2. Verifique se credenciais estão corretas
3. Verifique se valor é >= R$ 5,00
4. Verifique se documento (CNPJ) é válido

### Faturamento não aparece

**Causa**: Registro não foi criado

**Solução**:
1. Verifique se royalty foi criado com status "link_gerado"
2. Verifique banco de dados: `SELECT * FROM faturamentos`
3. Verifique logs de erro

---

## 📞 Suporte

### Documentação
- **Cora**: https://developers.cora.com.br
- **Stripe**: https://stripe.com/docs
- **Sistema**: Veja arquivos em `/md/`

### Logs
- **Cora**: `/logs/cora_v2.log`
- **Royalties**: `/logs/royalties_v2.log`
- **Polling**: `/logs/polling_faturamentos.log`

---

## 📊 Resumo das Mudanças

| Item | Antes | Depois |
|------|-------|--------|
| Menu Financeiro | 3 opções | 4 opções (+ Faturamento) |
| Criar Royalty | Sem boleto automático | Com boleto automático |
| Integração Cora | Não integrada | Totalmente integrada |
| Tabelas | Não existiam | Criadas automaticamente |
| Faturamento | Não visível | Visível e gerenciável |

---

## ✨ Próximos Passos

1. ✅ Copiar arquivos
2. ✅ Executar setup
3. ✅ Configurar credenciais
4. ✅ Testar integração
5. ✅ Agendar CRON
6. ✅ Treinar usuários
7. ✅ Monitorar logs

---

**Versão**: 1.1  
**Data**: 2025-12-04  
**Status**: Pronto para Implementação
