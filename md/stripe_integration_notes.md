# Integração com Stripe API - Faturas (Invoices)

## Informações Coletadas da Documentação Oficial

### Endpoint para Criar Fatura
**POST** `https://api.stripe.com/v1/invoices`

### Parâmetros Obrigatórios
- `customer` (string) - ID do cliente no Stripe (obrigatório, a menos que from_invoice seja fornecido)

### Parâmetros Importantes
- `collection_method` (enum) - Método de cobrança:
  - `charge_automatically` - Cobra automaticamente
  - `send_invoice` - Envia fatura para pagamento manual (USAR ESTE)
  
- `auto_advance` (boolean) - Se true, a Stripe automaticamente finaliza a fatura
- `automatic_tax` (object) - Configurações para cálculo automático de impostos
- `days_until_due` (integer) - Número de dias até o vencimento (para send_invoice)
- `due_date` (timestamp) - Data de vencimento específica

### Fluxo de Criação de Fatura

1. **Criar Cliente** (se não existir)
   - POST `/v1/customers`
   - Parâmetros: email, name, phone, address

2. **Criar Item de Fatura** (Invoice Item)
   - POST `/v1/invoiceitems`
   - Parâmetros: customer, amount, currency, description

3. **Criar Fatura**
   - POST `/v1/invoices`
   - Parâmetros: customer, collection_method='send_invoice', days_until_due

4. **Finalizar Fatura**
   - POST `/v1/invoices/{invoice_id}/finalize`
   - Retorna: hosted_invoice_url, invoice_pdf

5. **Enviar Fatura por E-mail**
   - POST `/v1/invoices/{invoice_id}/send`

### Resposta da API (Campos Importantes)

```json
{
  "id": "in_xxxxx",
  "object": "invoice",
  "customer": "cus_xxxxx",
  "status": "open",
  "hosted_invoice_url": "https://invoice.stripe.com/i/xxxxx",
  "invoice_pdf": "https://pay.stripe.com/invoice/xxxxx/pdf",
  "number": "XXXX-XXXX",
  "amount_due": 1000,
  "currency": "brl",
  "due_date": 1234567890,
  "payment_intent": "pi_xxxxx"
}
```

### Webhooks Importantes

- `invoice.paid` - Fatura foi paga
- `invoice.payment_failed` - Pagamento falhou
- `invoice.finalized` - Fatura foi finalizada
- `invoice.sent` - Fatura foi enviada

### Credenciais Necessárias

1. **Stripe Public Key** (pk_test_xxx ou pk_live_xxx)
2. **Stripe Secret Key** (sk_test_xxx ou sk_live_xxx)
3. **Webhook Secret** (whsec_xxx) - Para validar webhooks

### Biblioteca PHP

```bash
composer require stripe/stripe-php
```

### Exemplo de Código PHP

```php
require_once('vendor/autoload.php');

\Stripe\Stripe::setApiKey('sk_test_xxx');

// Criar cliente
$customer = \Stripe\Customer::create([
  'email' => 'cliente@example.com',
  'name' => 'Nome do Cliente'
]);

// Criar item de fatura
$invoiceItem = \Stripe\InvoiceItem::create([
  'customer' => $customer->id,
  'amount' => 1000, // em centavos
  'currency' => 'brl',
  'description' => 'Royalties - Dezembro 2025'
]);

// Criar fatura
$invoice = \Stripe\Invoice::create([
  'customer' => $customer->id,
  'collection_method' => 'send_invoice',
  'days_until_due' => 30
]);

// Finalizar fatura
$invoice->finalize();

// Enviar por e-mail
$invoice->sendInvoice();
```

### Campos Obrigatórios para Sistema

1. **Stripe Public Key** - Para frontend (se necessário)
2. **Stripe Secret Key** - Para backend (OBRIGATÓRIO)
3. **Webhook Secret** - Para validar webhooks (OBRIGATÓRIO)
4. **Modo** - test ou live
5. **Dados do Cliente**:
   - E-mail (OBRIGATÓRIO)
   - Nome
   - Telefone
   - Endereço (opcional)
