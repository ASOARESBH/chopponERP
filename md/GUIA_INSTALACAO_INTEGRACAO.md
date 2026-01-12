# Guia de Instalação - Integração Cora e Stripe

## Índice

1. [Pré-requisitos](#pré-requisitos)
2. [Instalação do Banco de Dados](#instalação-do-banco-de-dados)
3. [Configuração da Cora](#configuração-da-cora)
4. [Configuração do Stripe](#configuração-do-stripe)
5. [Configuração do CRON](#configuração-do-cron)
6. [Testes](#testes)
7. [Troubleshooting](#troubleshooting)

## Pré-requisitos

- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- cURL habilitado
- Acesso a contas Cora e Stripe
- Acesso ao servidor via SSH ou painel de controle

## Instalação do Banco de Dados

### 1. Criar Tabelas

Execute o script SQL para criar as tabelas necessárias:

```bash
mysql -u seu_usuario -p seu_banco < /caminho/para/sql/payment_gateway_config.sql
```

Ou via phpMyAdmin:

1. Acesse phpMyAdmin
2. Selecione seu banco de dados
3. Vá em "Importar"
4. Selecione o arquivo `sql/payment_gateway_config.sql`
5. Clique em "Executar"

**Tabelas criadas**:
- `payment_gateway_config` - Configuração de gateways por estabelecimento
- `faturamentos` - Registro unificado de faturas
- `faturamentos_historico` - Histórico de alterações

### 2. Verificar Tabelas

```sql
-- Verificar se as tabelas foram criadas
SHOW TABLES LIKE 'payment_gateway%';
SHOW TABLES LIKE 'faturamentos%';

-- Verificar estrutura
DESCRIBE payment_gateway_config;
DESCRIBE faturamentos;
DESCRIBE faturamentos_historico;
```

## Configuração da Cora

### 1. Obter Credenciais

1. Acesse sua conta Cora em https://cora.com.br
2. Vá em **Conta > Integrações via APIs**
3. Clique em **Criar Integração** ou **Nova Aplicação**
4. Preencha os dados:
   - **Nome da Aplicação**: Ex: "Sistema de Royalties"
   - **Descrição**: Ex: "Integração para emissão de boletos"
5. Clique em **Gerar Credenciais**
6. Copie o **Client ID** e **Client Secret**

### 2. Criar Arquivo de Configuração

```bash
# Copiar arquivo de exemplo
cp /caminho/para/cora_config_v2.example.php /caminho/para/cora_config_v2.php

# Editar arquivo
nano /caminho/para/cora_config_v2.php
```

### 3. Preencher Credenciais

Edite `cora_config_v2.php`:

```php
// Credenciais obtidas em Conta > Integrações via APIs
define('CORA_CLIENT_ID', 'seu_client_id_aqui');
define('CORA_CLIENT_SECRET', 'seu_client_secret_aqui');

// Ambiente: 'stage' para testes, 'production' para produção
define('CORA_ENVIRONMENT', 'stage');

// Dados do beneficiário (sua empresa)
define('CORA_BENEFICIARY_NAME', 'Sua Empresa LTDA');
define('CORA_BENEFICIARY_DOCUMENT', '12345678000190'); // CNPJ sem formatação
define('CORA_BENEFICIARY_EMAIL', 'financeiro@suaempresa.com.br');
```

### 4. Proteger Arquivo

```bash
# Restringir permissões (somente leitura para o proprietário)
chmod 600 /caminho/para/cora_config_v2.php

# Adicionar ao .gitignore
echo "cora_config_v2.php" >> /caminho/para/.gitignore
```

### 5. Adicionar ao Banco de Dados

Insira a configuração para cada estabelecimento:

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
    'cora',
    'stage',
    1,
    JSON_OBJECT(
        'client_id', 'seu_client_id',
        'client_secret', 'seu_client_secret',
        'environment', 'stage'
    ),
    NOW()
);
```

## Configuração do Stripe

### 1. Obter Credenciais

1. Acesse seu Dashboard Stripe em https://dashboard.stripe.com
2. Vá em **Developers > API Keys**
3. Copie a **Secret Key** (começa com `sk_`)
4. Vá em **Developers > Webhooks**
5. Clique em **Adicionar Endpoint**
6. Configure:
   - **URL**: `https://seu-dominio.com.br/webhook/stripe`
   - **Eventos**: Selecione `invoice.payment_succeeded`, `invoice.payment_failed`
7. Copie o **Signing Secret** (começa com `whsec_`)

### 2. Criar Arquivo de Configuração

```bash
# Copiar arquivo de exemplo (se existir)
cp /caminho/para/stripe_config.example.php /caminho/para/stripe_config.php

# Ou criar novo
nano /caminho/para/stripe_config.php
```

### 3. Preencher Credenciais

```php
<?php
// Stripe API Keys
define('STRIPE_SECRET_KEY', 'sk_test_seu_secret_key_aqui');
define('STRIPE_WEBHOOK_SECRET', 'whsec_seu_webhook_secret_aqui');
define('STRIPE_MODE', 'test'); // 'test' ou 'live'
?>
```

### 4. Proteger Arquivo

```bash
chmod 600 /caminho/para/stripe_config.php
echo "stripe_config.php" >> /caminho/para/.gitignore
```

### 5. Adicionar ao Banco de Dados

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
        'secret_key', 'sk_test_...',
        'webhook_secret', 'whsec_...',
        'environment', 'test'
    ),
    NOW()
);
```

## Configuração do CRON

### 1. Criar Script de Polling

O arquivo `/cron/polling_faturamentos.php` já está criado. Ele verifica status de boletos e faturas a cada hora.

### 2. Agendar no CRON

#### Opção 1: Via SSH

```bash
# Abrir crontab
crontab -e

# Adicionar linha:
0 * * * * /usr/bin/php /caminho/para/cron/polling_faturamentos.php >> /var/log/polling_faturamentos.log 2>&1

# Salvar (Ctrl+X, depois Y, depois Enter)
```

#### Opção 2: Via cPanel

1. Acesse cPanel
2. Vá em **Cron Jobs**
3. Clique em **Adicionar Cron Job**
4. Configure:
   - **Comum**: Custom
   - **Minuto**: `0`
   - **Hora**: `*` (a cada hora)
   - **Dia do Mês**: `*`
   - **Mês**: `*`
   - **Dia da Semana**: `*`
   - **Comando**: `/usr/bin/php /caminho/para/cron/polling_faturamentos.php`
5. Clique em **Adicionar Cron Job**

#### Opção 3: Via Webhook (sem acesso ao CRON)

Se não tiver acesso ao CRON, pode usar um serviço externo:

1. Acesse https://cron-job.org
2. Crie uma conta
3. Adicione novo cron job:
   - **URL**: `https://seu-dominio.com.br/cron/polling_faturamentos.php?token=seu_token_secreto`
   - **Intervalo**: A cada 1 hora
4. Defina um token seguro em `CRON_TOKEN` no arquivo

### 3. Verificar Logs

```bash
# Ver últimas linhas do log
tail -f /var/log/polling_faturamentos.log

# Ou no servidor
tail -f /caminho/para/logs/polling_faturamentos.log
```

## Testes

### 1. Testar Integração Cora

Crie um arquivo de teste:

```php
<?php
require_once 'includes/config.php';
require_once 'includes/cora_api_v2.php';

$cora = new CoraAPIv2(
    'seu_client_id',
    'seu_client_secret',
    'stage'
);

// Testar emissão de boleto
$resultado = $cora->emitirBoleto([
    'amount' => 50000, // R$ 500,00
    'due_date' => '2025-12-31',
    'description' => 'Teste de Boleto',
    'payer' => [
        'name' => 'Cliente Teste',
        'document' => '12345678000190',
        'email' => 'teste@example.com'
    ],
    'beneficiary' => [
        'name' => 'Sua Empresa',
        'document' => '12345678000190',
        'email' => 'financeiro@empresa.com.br'
    ]
]);

echo '<pre>';
print_r($resultado);
echo '</pre>';
?>
```

Acesse via navegador e verifique se o boleto foi criado.

### 2. Testar Integração Stripe

```php
<?php
require_once 'includes/config.php';
require_once 'includes/stripe_api.php';

$stripe = new StripeAPI(1); // ID do estabelecimento

// Testar criação de fatura
$resultado = $stripe->createCompleteInvoice(
    [
        'email' => 'cliente@example.com',
        'name' => 'Cliente Teste'
    ],
    500.00, // R$ 500,00
    'Teste de Fatura',
    ['test' => true],
    30
);

echo '<pre>';
print_r($resultado);
echo '</pre>';
?>
```

### 3. Testar Polling

```bash
# Executar manualmente
php /caminho/para/cron/polling_faturamentos.php

# Ou via curl
curl -X GET "https://seu-dominio.com.br/cron/polling_faturamentos.php?token=seu_token_secreto"
```

### 4. Verificar Banco de Dados

```sql
-- Verificar configurações
SELECT * FROM payment_gateway_config;

-- Verificar faturamentos
SELECT * FROM faturamentos;

-- Verificar histórico
SELECT * FROM faturamentos_historico;
```

## Troubleshooting

### Erro: "Autenticação falhou"

**Causa**: Client ID ou Client Secret inválidos

**Solução**:
1. Verifique as credenciais em Conta > Integrações via APIs
2. Copie novamente (sem espaços em branco)
3. Atualize o arquivo de configuração

### Erro: "Valor mínimo não atingido"

**Causa**: Tentativa de criar boleto com valor < R$ 5,00

**Solução**:
1. Aumente o valor para pelo menos R$ 5,00
2. Verifique se o valor está em centavos (100 = R$ 1,00)

### Erro: "Documento inválido"

**Causa**: CPF ou CNPJ mal formatado

**Solução**:
1. Use apenas números (sem formatação)
2. CPF: 11 dígitos
3. CNPJ: 14 dígitos

### Erro: "Token expirado"

**Causa**: Token de autenticação expirou

**Solução**:
1. Sistema solicita novo token automaticamente
2. Se persistir, verifique credenciais

### Erro: "API Key inválida" (Stripe)

**Causa**: Secret Key incorreta

**Solução**:
1. Verifique se está usando Secret Key (começa com `sk_`)
2. Não use Publishable Key
3. Copie novamente do Dashboard

### Polling não funciona

**Causa**: CRON não está executando

**Solução**:
1. Verifique se CRON está habilitado no servidor
2. Teste manualmente: `php /caminho/para/cron/polling_faturamentos.php`
3. Verifique logs: `/var/log/polling_faturamentos.log`
4. Use webhook alternativo se CRON não disponível

### Logs não aparecem

**Causa**: Permissões de arquivo

**Solução**:
```bash
# Criar diretório de logs
mkdir -p /caminho/para/logs

# Dar permissão de escrita
chmod 755 /caminho/para/logs
```

## Próximos Passos

1. ✅ Instalar banco de dados
2. ✅ Configurar Cora
3. ✅ Configurar Stripe
4. ✅ Agendar CRON
5. ✅ Testar integrações
6. Acessar `/admin/financeiro_faturamento.php` para visualizar faturamentos
7. Criar royalties e gerar boletos/faturas
8. Monitorar polling automático

## Suporte

Para dúvidas ou problemas:

- Documentação Cora: https://developers.cora.com.br
- Documentação Stripe: https://stripe.com/docs
- Logs do sistema: `/logs/`

---

**Versão**: 1.0  
**Data**: 2025-12-04
