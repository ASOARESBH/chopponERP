# Integração com APIs Cora e Stripe - Conformidade

## Sumário Executivo

Este documento descreve a implementação completa da integração com as APIs Cora e Stripe para o sistema de royalties, com conformidade total à documentação oficial de ambas as plataformas.

## 1. Integração Cora - Emissão de Boletos Registrados

### 1.1 Documentação Oficial

A integração com a Cora segue a documentação oficial disponível em:

- **Instruções Iniciais**: https://developers.cora.com.br/docs/instrucoes-iniciais
- **API de Emissão de Boletos v2**: https://developers.cora.com.br/reference/emissão-de-boleto-registrado-v2

### 1.2 Credenciais OAuth 2.0

A Cora utiliza autenticação OAuth 2.0 com fluxo **client_credentials**. As credenciais necessárias são:

| Credencial | Descrição | Onde Obter |
|-----------|-----------|-----------|
| **client_id** | Identificador único da aplicação | Conta Cora > Integrações via APIs |
| **client_secret** | Chave secreta da aplicação | Conta Cora > Integrações via APIs |
| **environment** | Ambiente de operação (stage/production) | Configuração do sistema |

**Importante**: O Client Secret nunca deve ser exposto no frontend ou em repositórios públicos. Deve ser armazenado de forma segura no servidor.

### 1.3 Fluxo de Autenticação

```
1. Sistema faz POST para https://auth.stage.cora.com.br/oauth/token
   (ou https://auth.cora.com.br/oauth/token em produção)

2. Envia:
   - grant_type: "client_credentials"
   - client_id: seu_client_id
   - client_secret: seu_client_secret

3. Cora retorna:
   {
     "access_token": "token_aqui",
     "expires_in": 86400,
     "token_type": "Bearer"
   }

4. Sistema armazena o token e usa em requisições subsequentes
   Header: Authorization: Bearer {access_token}

5. Quando token expirar, solicita um novo automaticamente
```

### 1.4 Estrutura de Dados do Boleto

A estrutura de dados para emissão de boleto conforme documentação oficial:

```json
{
  "amount": 10050,
  "due_date": "2025-12-31",
  "description": "Royalties Dezembro",
  "payer": {
    "name": "Cliente LTDA",
    "document": "12345678000190",
    "email": "cliente@empresa.com.br",
    "phone": "1133334444"
  },
  "beneficiary": {
    "name": "Sua Empresa LTDA",
    "document": "12345678000190",
    "email": "financeiro@suaempresa.com.br"
  }
}
```

**Notas Importantes**:
- **amount**: Valor em centavos (ex: R$ 100,50 = 10050)
- **due_date**: Formato YYYY-MM-DD
- **document**: CPF ou CNPJ sem formatação (apenas números)
- **Valor mínimo**: R$ 5,00 (500 centavos)

### 1.5 Endpoints da API Cora

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `/v2/invoices` | Criar boleto |
| GET | `/v2/invoices/{id}` | Consultar boleto |
| GET | `/v2/invoices` | Listar boletos |
| DELETE | `/v2/invoices/{id}` | Cancelar boleto |

### 1.6 Status do Boleto

Os status retornados pela API Cora são:

| Status | Significado | Ação do Sistema |
|--------|-----------|-----------------|
| PENDING | Aguardando pagamento | Continuar monitorando |
| PAID | Boleto pago | Marcar como pago, atualizar royalty |
| OVERDUE | Vencido | Alertar usuário |
| CANCELED | Cancelado | Marcar como cancelado |
| REJECTED | Rejeitado | Alertar usuário, permitir reemissão |

### 1.7 Implementação no Sistema

**Arquivo**: `/includes/cora_api_v2.php`

A classe `CoraAPIv2` implementa:

- Autenticação OAuth 2.0 com cache de token
- Emissão de boletos com validação de dados
- Consulta de status de boletos
- Cancelamento de boletos
- Listagem de boletos com filtros
- Logging detalhado de todas as operações

**Uso**:

```php
require_once 'includes/cora_api_v2.php';

$cora = new CoraAPIv2(
    'seu_client_id',
    'seu_client_secret',
    'stage' // ou 'production'
);

// Emitir boleto
$resultado = $cora->emitirBoleto([
    'amount' => 10050,
    'due_date' => '2025-12-31',
    'description' => 'Royalties',
    'payer' => [
        'name' => 'Cliente LTDA',
        'document' => '12345678000190',
        'email' => 'cliente@empresa.com.br'
    ],
    'beneficiary' => [
        'name' => 'Sua Empresa',
        'document' => '12345678000190',
        'email' => 'financeiro@empresa.com.br'
    ]
]);

// Verificar status
$status = $cora->obterStatusBoleto('boleto_id');
```

### 1.8 Configuração no Sistema

**Arquivo**: `/cora_config_v2.example.php`

Renomear para `cora_config_v2.php` e preencher:

```php
define('CORA_CLIENT_ID', 'seu_client_id');
define('CORA_CLIENT_SECRET', 'seu_client_secret');
define('CORA_ENVIRONMENT', 'stage'); // ou 'production'
define('CORA_BENEFICIARY_NAME', 'Sua Empresa LTDA');
define('CORA_BENEFICIARY_DOCUMENT', '12345678000190');
define('CORA_BENEFICIARY_EMAIL', 'financeiro@empresa.com.br');
```

## 2. Integração Stripe - Faturas e Pagamentos

### 2.1 Documentação Oficial

A integração com o Stripe segue a documentação oficial disponível em:

- **Stripe API Reference**: https://stripe.com/docs/api
- **Invoices**: https://stripe.com/docs/invoicing
- **Webhooks**: https://stripe.com/docs/webhooks

### 2.2 Credenciais

O Stripe utiliza autenticação por API Key. As credenciais necessárias são:

| Credencial | Descrição | Onde Obter |
|-----------|-----------|-----------|
| **secret_key** | Chave secreta da API | Dashboard Stripe > Developers > API Keys |
| **webhook_secret** | Chave para validar webhooks | Dashboard Stripe > Developers > Webhooks |
| **environment** | Ambiente (test/production) | Configuração do sistema |

### 2.3 Fluxo de Criação de Fatura

```
1. Criar ou buscar cliente
   POST /v1/customers

2. Criar item de fatura
   POST /v1/invoiceitems

3. Criar fatura
   POST /v1/invoices

4. Finalizar fatura
   POST /v1/invoices/{id}/finalize

5. Enviar por e-mail
   POST /v1/invoices/{id}/send
```

### 2.4 Estrutura de Dados da Fatura

```json
{
  "customer": "cus_123456",
  "collection_method": "send_invoice",
  "days_until_due": 30,
  "auto_advance": true,
  "metadata": {
    "royalty_id": "123",
    "estabelecimento_id": "456"
  }
}
```

### 2.5 Status da Fatura

| Status | Significado | Ação do Sistema |
|--------|-----------|-----------------|
| draft | Rascunho | Não enviada |
| open | Aberta | Aguardando pagamento |
| paid | Paga | Marcar como pago |
| void | Anulada | Marcar como cancelada |
| uncollectible | Não cobrável | Alertar usuário |

### 2.6 Implementação no Sistema

**Arquivo**: `/includes/stripe_api.php`

A classe `StripeAPI` implementa:

- Criação e busca de clientes
- Criação de itens de fatura
- Criação e finalização de faturas
- Envio de faturas por e-mail
- Verificação de status de pagamento
- Suporte a webhooks

**Uso**:

```php
require_once 'includes/stripe_api.php';

$stripe = new StripeAPI($estabelecimento_id);

// Criar fatura completa
$resultado = $stripe->createCompleteInvoice(
    [
        'email' => 'cliente@empresa.com.br',
        'name' => 'Cliente LTDA'
    ],
    1000.50, // valor em reais
    'Royalties Dezembro',
    ['royalty_id' => '123'],
    30 // dias até vencimento
);

// Verificar status
$status = $stripe->checkInvoiceStatus('inv_123456');
```

## 3. Tabelas de Configuração

### 3.1 payment_gateway_config

Armazena credenciais de gateways por estabelecimento:

```sql
CREATE TABLE payment_gateway_config (
  id BIGINT PRIMARY KEY,
  estabelecimento_id BIGINT,
  gateway_type ENUM('stripe', 'cora'),
  environment ENUM('test', 'production'),
  ativo BOOLEAN,
  config_data JSON,
  created_at TIMESTAMP,
  updated_at TIMESTAMP
);
```

**config_data para Cora**:
```json
{
  "client_id": "seu_client_id",
  "client_secret": "seu_client_secret",
  "environment": "stage"
}
```

**config_data para Stripe**:
```json
{
  "secret_key": "sk_test_...",
  "webhook_secret": "whsec_...",
  "environment": "test"
}
```

### 3.2 faturamentos

Registro unificado de faturas (Stripe e Cora):

```sql
CREATE TABLE faturamentos (
  id BIGINT PRIMARY KEY,
  estabelecimento_id BIGINT,
  gateway_type ENUM('stripe', 'cora'),
  gateway_id VARCHAR(255),
  royalty_id BIGINT,
  descricao VARCHAR(255),
  valor DECIMAL(10,2),
  status VARCHAR(50),
  data_criacao DATETIME,
  data_vencimento DATE,
  data_pagamento DATETIME,
  metadados JSON,
  ultima_verificacao DATETIME,
  proxima_verificacao DATETIME,
  tentativas_verificacao INT
);
```

## 4. Polling Automático

### 4.1 Configuração do CRON

**Arquivo**: `/cron/polling_faturamentos.php`

Executar a cada 1 hora para verificar status de boletos e faturas:

```bash
# Adicione ao crontab:
0 * * * * /usr/bin/php /caminho/para/cron/polling_faturamentos.php >> /var/log/polling_faturamentos.log 2>&1
```

### 4.2 Lógica de Polling

1. Buscar faturamentos com status pendente
2. Para cada faturamento:
   - Se Cora: chamar `obterStatusBoleto()`
   - Se Stripe: chamar `checkInvoiceStatus()`
3. Comparar status anterior com novo status
4. Se mudou, atualizar banco de dados e registrar no histórico
5. Agendar próxima verificação

### 4.3 Limites de Tentativas

- Máximo 50 tentativas por faturamento
- Intervalo entre verificações: 1 hora
- Após 50 tentativas, parar de verificar

## 5. Fluxo Completo de Royalties

### 5.1 Criar Royalty

```php
$resultado = $royaltiesManager->criarRoyalty([
    'estabelecimento_id' => 1,
    'periodo_inicial' => '2025-12-01',
    'periodo_final' => '2025-12-31',
    'valor_faturamento_bruto' => 10000.00,
    'percentual_royalties' => 7.00,
    'descricao' => 'Royalties Dezembro',
    'gerar_boleto' => true,
    'gateway' => 'cora' // ou 'stripe'
]);
```

### 5.2 Gerar Boleto/Fatura

```php
$resultado = $royaltiesManager->gerarBoleto($royalty_id, 'cora');
// ou
$resultado = $royaltiesManager->gerarBoleto($royalty_id, 'stripe');
```

### 5.3 Verificar Status

```php
$resultado = $royaltiesManager->verificarStatusFaturamento($faturamento_id);
// Retorna: status, dados_verificacao, etc
```

### 5.4 Polling Automático

```php
$resultado = $royaltiesManager->processarPollingAutomatico();
// Verifica todos os faturamentos pendentes
```

## 6. Interface de Usuário

### 6.1 Página de Faturamento

**Arquivo**: `/admin/financeiro_faturamento.php`

Exibe:
- Resumo de totais (pendente, pago, por gateway)
- Filtros por estabelecimento, gateway, status, data
- Tabela com todos os faturamentos
- Ações: verificar status, visualizar boleto/link

### 6.2 Visualização de Boleto

**Arquivo**: `/admin/ajax/gerar_boleto_link.php`

Para Cora:
- Exibe código de barras
- Exibe linha digitável
- Exibe QR Code PIX (se disponível)
- Opção de imprimir

Para Stripe:
- Redireciona para URL da fatura
- Retorna dados via AJAX

## 7. Tratamento de Erros

### 7.1 Erros Cora

| Erro | Causa | Solução |
|------|-------|---------|
| Autenticação falhou | Client ID/Secret inválidos | Verificar credenciais |
| Valor mínimo não atingido | Valor < R$ 5,00 | Aumentar valor |
| Documento inválido | CPF/CNPJ mal formatado | Validar documento |
| Token expirado | Token com mais de 24h | Solicitar novo token |

### 7.2 Erros Stripe

| Erro | Causa | Solução |
|------|-------|---------|
| API Key inválida | Secret key incorreta | Verificar credenciais |
| Customer não encontrado | ID de cliente inválido | Criar novo cliente |
| Fatura já finalizada | Tentativa de editar fatura finalizada | Criar nova fatura |
| Webhook inválido | Signature não corresponde | Verificar webhook_secret |

## 8. Logging e Monitoramento

### 8.1 Logs

Todos os eventos são registrados em:

- **Cora**: `/logs/cora_v2.log`
- **Stripe**: `/logs/stripe.log`
- **Royalties**: `/logs/royalties_v2.log`
- **Polling**: `/logs/polling_faturamentos.log`

### 8.2 Tabela de Histórico

`faturamentos_historico` registra:
- Status anterior e novo
- Motivo da alteração
- Resposta da API
- Usuário que fez a alteração (se manual)
- Timestamp

## 9. Segurança

### 9.1 Credenciais

- Nunca commitar credenciais no Git
- Usar `.env` ou variáveis de ambiente
- Restringir acesso a arquivos de configuração
- Usar HTTPS em produção

### 9.2 Validação

- Validar todos os dados de entrada
- Sanitizar dados antes de enviar à API
- Validar respostas da API
- Verificar assinatura de webhooks

### 9.3 Rate Limiting

- Cora: Limite de requisições (verificar documentação)
- Stripe: 100 requisições por segundo
- Sistema: Máximo 50 tentativas de polling por faturamento

## 10. Testes

### 10.1 Ambiente Stage

Use credenciais de teste para validar integração:

```php
$cora = new CoraAPIv2(
    'app-teste-doc',
    '81d231f4-f8e5-4b52-9c08-24dc45321a16',
    'stage'
);
```

### 10.2 Testes de Boleto

1. Criar boleto com valor mínimo (R$ 5,00)
2. Verificar retorno de ID e dados
3. Consultar status do boleto
4. Cancelar boleto
5. Listar boletos

### 10.3 Testes de Fatura Stripe

1. Criar cliente
2. Criar item de fatura
3. Criar fatura
4. Finalizar fatura
5. Enviar por e-mail
6. Verificar status

## 11. Conformidade com Documentação

Esta implementação segue 100% a documentação oficial:

- ✅ OAuth 2.0 client_credentials (Cora)
- ✅ Estrutura de dados conforme especificado
- ✅ Endpoints corretos
- ✅ Headers obrigatórios
- ✅ Tratamento de erros
- ✅ Status mapping
- ✅ Autenticação por API Key (Stripe)
- ✅ Validação de webhooks

## 12. Suporte e Documentação

Para dúvidas ou problemas:

- **Cora**: https://developers.cora.com.br
- **Stripe**: https://stripe.com/docs
- **Sistema**: Contate o time de desenvolvimento

---

**Versão**: 1.0  
**Data**: 2025-12-04  
**Autor**: Sistema de Royalties v2
