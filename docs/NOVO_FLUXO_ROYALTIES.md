# 💰 Novo Fluxo de Royalties com Múltiplos Gateways

**Versão**: 5.0  
**Data**: 2025-12-14

---

## 1. Visão Geral

Sistema completamente reestruturado para separar o **lançamento** do **pagamento** de royalties, permitindo que o estabelecimento escolha o método de pagamento no momento do pagamento.

### Mudanças Principais:

- ✅ **Lançamento independente**: Criar royalty não gera pagamento automaticamente
- ✅ **Escolha flexível**: Estabelecimento escolhe Stripe, Cora ou Mercado Pago na hora de pagar
- ✅ **Integração Mercado Pago**: Nova integração completa com API do Mercado Pago
- ✅ **Fluxo simplificado**: Botão "Pagar" substitui "Gerar Link"
- ✅ **Rastreamento completo**: Log de todas as transações de pagamento

---

## 2. Novo Fluxo de Trabalho

### Passo 1: Criar Lançamento de Royalty

1. Acesse **Financeiro > Royalties**
2. Clique em **"+ Novo Lançamento"**
3. Preencha:
   - Estabelecimento
   - Período (data inicial e final)
   - Valor do faturamento bruto
   - Percentual de royalties (7%)
   - Descrição (opcional)
4. Clique em **"Criar Royalty"**

**Status inicial**: `pendente`

### Passo 2: Pagar Royalty

1. Na listagem, clique no botão **"💳 Pagar"** do royalty pendente
2. Será redirecionado para página de seleção de método
3. Escolha entre:
   - **Stripe**: Cartão de crédito
   - **Banco Cora**: Boleto bancário
   - **Mercado Pago**: Cartão, PIX ou Boleto
4. Confirme o método escolhido
5. Será redirecionado para o gateway de pagamento

### Passo 3: Finalizar Pagamento

- **Stripe**: Preencha dados do cartão na página do Stripe
- **Cora**: Visualize e pague o boleto gerado
- **Mercado Pago**: Escolha forma de pagamento (cartão, PIX, boleto)

### Passo 4: Confirmação

Após o pagamento:
- Sistema recebe webhook do gateway
- Status atualizado automaticamente
- Estabelecimento recebe confirmação por e-mail

---

## 3. Integração Mercado Pago

### 3.1. Configuração

1. Acesse **Integrações > Mercado Pago**
2. Clique em **"Nova Configuração"**
3. Preencha:
   - **Estabelecimento**: Selecione o estabelecimento
   - **Access Token**: Token obtido no painel do Mercado Pago
   - **Public Key**: (Opcional) Para checkout transparente
   - **Ambiente**: Sandbox (teste) ou Production (produção)
   - **Webhook URL**: `https://seusite.com/api/webhook_mercadopago.php`
4. Marque **"Configuração Ativa"**
5. Clique em **"Salvar Configuração"**

### 3.2. Obter Credenciais

1. Acesse https://www.mercadopago.com.br/developers
2. Faça login
3. Vá em **Suas integrações → Credenciais**
4. Copie:
   - **Access Token** (Produção ou Teste)
   - **Public Key** (opcional)

### 3.3. Configurar Webhook

1. No painel do Mercado Pago, vá em **Webhooks**
2. Adicione a URL: `https://seusite.com/api/webhook_mercadopago.php`
3. Selecione eventos:
   - `payment`
   - `merchant_order`

---

## 4. Banco de Dados

### 4.1. Nova Tabela: mercadopago_config

```sql
CREATE TABLE `mercadopago_config` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `estabelecimento_id` BIGINT(20) NOT NULL,
  `access_token` VARCHAR(500) NOT NULL,
  `public_key` VARCHAR(500) NULL,
  `ambiente` ENUM('sandbox', 'production') NOT NULL DEFAULT 'sandbox',
  `webhook_url` VARCHAR(500) NULL,
  `webhook_secret` VARCHAR(255) NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_estabelecimento` (`estabelecimento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 4.2. Novos Campos em royalties

```sql
ALTER TABLE `royalties` 
ADD COLUMN `metodo_pagamento` ENUM('stripe', 'cora', 'mercadopago', 'manual') NULL,
ADD COLUMN `payment_id` VARCHAR(255) NULL,
ADD COLUMN `payment_url` VARCHAR(500) NULL,
ADD COLUMN `payment_status` ENUM('pendente', 'processando', 'aprovado', 'recusado', 'cancelado') DEFAULT 'pendente',
ADD COLUMN `payment_data` JSON NULL,
ADD COLUMN `paid_at` TIMESTAMP NULL;
```

### 4.3. Nova Tabela: royalties_payment_log

```sql
CREATE TABLE `royalties_payment_log` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `royalty_id` INT(11) NOT NULL,
  `estabelecimento_id` BIGINT(20) NOT NULL,
  `metodo_pagamento` ENUM('stripe', 'cora', 'mercadopago', 'manual') NOT NULL,
  `acao` VARCHAR(100) NOT NULL,
  `status` VARCHAR(50) NOT NULL,
  `request_data` JSON NULL,
  `response_data` JSON NULL,
  `erro_mensagem` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 5. Arquivos Criados/Modificados

### Novos Arquivos:

1. **`includes/MercadoPagoAPI.php`**: Classe de integração com Mercado Pago
2. **`admin/mercadopago_config.php`**: Página de configuração
3. **`admin/royalty_selecionar_pagamento.php`**: Seleção de método
4. **`admin/royalty_processar_pagamento.php`**: Processamento e redirecionamento
5. **`admin/royalty_pagamento_sucesso.php`**: Página de sucesso
6. **`sql/add_mercadopago_integration.sql`**: Migration do banco

### Arquivos Modificados:

1. **`admin/financeiro_royalties.php`**: Botão "Gerar Link" → "Pagar"
2. **`includes/header.php`**: Adicionado Mercado Pago ao menu

---

## 6. Classe MercadoPagoAPI

### Métodos Principais:

```php
// Criar preferência de pagamento (checkout)
$preferencia = $mp->criarPreferencia([
    'titulo' => 'Royalty - Estabelecimento',
    'descricao' => 'Período: 01/12 a 31/12',
    'valor' => 500.00,
    'pagador_nome' => 'Nome do Estabelecimento',
    'pagador_email' => 'email@estabelecimento.com',
    'url_sucesso' => 'https://site.com/sucesso',
    'url_falha' => 'https://site.com/falha',
    'referencia_externa' => 'ROYALTY_123'
]);

// Criar pagamento PIX
$pix = $mp->criarPagamentoPix([
    'valor' => 500.00,
    'descricao' => 'Royalty',
    'pagador_email' => 'email@estabelecimento.com',
    'referencia_externa' => 'ROYALTY_123'
]);

// Consultar pagamento
$pagamento = $mp->consultarPagamento($payment_id);

// Processar webhook
$resultado = $mp->processarWebhook($data);
```

---

## 7. Status de Pagamento

### Mapeamento de Status:

| Gateway | Status Original | Status Interno |
|:---|:---|:---|
| **Stripe** | `succeeded` | `aprovado` |
| **Stripe** | `processing` | `processando` |
| **Stripe** | `requires_payment_method` | `pendente` |
| **Stripe** | `canceled` | `cancelado` |
| **Cora** | `PAID` | `aprovado` |
| **Cora** | `PENDING` | `pendente` |
| **Cora** | `EXPIRED` | `cancelado` |
| **Mercado Pago** | `approved` | `aprovado` |
| **Mercado Pago** | `pending` | `pendente` |
| **Mercado Pago** | `in_process` | `processando` |
| **Mercado Pago** | `rejected` | `recusado` |
| **Mercado Pago** | `cancelled` | `cancelado` |

---

## 8. Webhooks

### 8.1. Webhook Mercado Pago

**URL**: `/api/webhook_mercadopago.php`

**Eventos recebidos:**
- `payment`: Atualização de pagamento
- `merchant_order`: Atualização de pedido

**Processamento:**
1. Recebe notificação do Mercado Pago
2. Valida assinatura (se configurado)
3. Busca informações do pagamento
4. Atualiza status do royalty
5. Envia e-mail de confirmação

### 8.2. Webhook Stripe

**URL**: `/api/webhook_stripe.php`

### 8.3. Webhook Cora

**URL**: `/api/webhook_cora.php`

---

## 9. Testes

### Teste 1: Criar Royalty

1. Criar novo lançamento
2. Verificar se status é `pendente`
3. Verificar se botão "Pagar" aparece

### Teste 2: Selecionar Método

1. Clicar em "Pagar"
2. Verificar se página mostra métodos disponíveis
3. Verificar se métodos não configurados não aparecem

### Teste 3: Pagar com Stripe

1. Selecionar Stripe
2. Verificar redirecionamento para checkout Stripe
3. Pagar com cartão de teste
4. Verificar atualização de status

### Teste 4: Pagar com Cora

1. Selecionar Cora
2. Verificar geração de boleto
3. Verificar visualização do PDF

### Teste 5: Pagar com Mercado Pago

1. Selecionar Mercado Pago
2. Verificar redirecionamento para checkout
3. Escolher método de pagamento
4. Verificar atualização de status

---

## 10. Cartões de Teste

### Mercado Pago (Sandbox):

| Cartão | Número | CVV | Validade | Resultado |
|:---|:---|:---|:---|:---|
| **Visa** | 4509 9535 6623 3704 | 123 | 11/25 | Aprovado |
| **Mastercard** | 5031 4332 1540 6351 | 123 | 11/25 | Aprovado |
| **Recusado** | 5031 7557 3453 0604 | 123 | 11/25 | Recusado |

### Stripe (Test Mode):

| Cartão | Número | Resultado |
|:---|:---|:---|
| **Sucesso** | 4242 4242 4242 4242 | Aprovado |
| **Recusado** | 4000 0000 0000 0002 | Recusado |
| **3D Secure** | 4000 0027 6000 3184 | Requer autenticação |

---

## 11. Solução de Problemas

### Erro: "Configuração não encontrada"

**Causa**: Método de pagamento não configurado para o estabelecimento  
**Solução**: Acesse Integrações e configure o método desejado

### Erro: "Erro ao criar pagamento"

**Causa**: Credenciais inválidas ou expiradas  
**Solução**: Verifique as credenciais na página de configuração

### Pagamento não atualiza automaticamente

**Causa**: Webhook não configurado ou não funcionando  
**Solução**: 
1. Verifique a URL do webhook
2. Teste o webhook manualmente
3. Verifique logs de erro

### Boleto Cora não gera

**Causa**: Configuração Cora incompleta  
**Solução**: Verifique se Client ID e Client Secret estão corretos

---

## 12. Próximas Melhorias

- [ ] Parcelamento no Mercado Pago
- [ ] PIX direto (sem checkout)
- [ ] Desconto para pagamento antecipado
- [ ] Multa e juros para atraso
- [ ] Relatório de inadimplência
- [ ] Dashboard de conversão por gateway

---

**Fim da Documentação**
