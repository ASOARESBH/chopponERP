# Stripe Payment Links - Notas de Implementação

## Visão Geral

Payment Links são URLs compartilháveis que levam os clientes a uma página de pagamento hospedada pelo Stripe. Diferente das Invoices, os Payment Links podem ser usados múltiplas vezes e não exigem criação de cliente.

## Endpoint da API

**POST** `https://api.stripe.com/v1/payment_links`

## Parâmetros Principais

### Obrigatórios
- `line_items` (array) - Itens sendo vendidos (até 20 itens)
  - `price` (string) - ID do preço no Stripe
  - `quantity` (integer) - Quantidade

### Opcionais Importantes
- `metadata` (object) - Metadados personalizados
- `after_completion` (object) - O que acontece após pagamento
- `allow_promotion_codes` (boolean) - Permite códigos promocionais
- `automatic_tax` (object) - Cálculo automático de impostos
- `currency` (string) - Moeda (ex: 'brl')
- `payment_method_types` (array) - Tipos de pagamento aceitos

## Resposta da API

```json
{
  "id": "plink_xxxxx",
  "object": "payment_link",
  "active": true,
  "url": "https://buy.stripe.com/test_xxxxx",
  "currency": "brl",
  "metadata": {}
}
```

## Diferença entre Payment Links e Invoices

| Característica | Payment Links | Invoices |
|---|---|---|
| **Uso** | Pode ser usado múltiplas vezes | Uso único |
| **Cliente** | Não requer cliente cadastrado | Requer cliente |
| **E-mail** | Não envia automaticamente | Envia automaticamente |
| **Rastreamento** | Via checkout sessions | Via invoice object |
| **Personalização** | Limitada | Alta |

## Fluxo de Implementação para Royalties

1. **Criar Price no Stripe** (uma vez por valor único)
   - POST `/v1/prices`
   - Parâmetros: `unit_amount`, `currency`, `product_data`

2. **Criar Payment Link**
   - POST `/v1/payment_links`
   - Usar o price_id criado
   - Adicionar metadata com royalty_id

3. **Compartilhar URL**
   - Copiar `payment_link.url`
   - Enviar por e-mail

4. **Webhook para Confirmação**
   - Evento: `checkout.session.completed`
   - Verificar metadata para identificar royalty

## Alternativa: Criar Price On-the-fly

Para valores dinâmicos (como royalties que mudam), podemos criar um Price temporário:

```php
// Criar Price
$price = $stripe->request('/prices', 'POST', [
    'unit_amount' => $amount * 100, // em centavos
    'currency' => 'brl',
    'product_data' => [
        'name' => 'Royalties - Descrição'
    ]
]);

// Criar Payment Link
$payment_link = $stripe->request('/payment_links', 'POST', [
    'line_items' => [
        [
            'price' => $price['id'],
            'quantity' => 1
        ]
    ],
    'metadata' => [
        'royalty_id' => $royalty_id
    ]
]);
```

## Webhook para Payment Links

Evento principal: `checkout.session.completed`

```json
{
  "type": "checkout.session.completed",
  "data": {
    "object": {
      "id": "cs_xxxxx",
      "payment_link": "plink_xxxxx",
      "payment_status": "paid",
      "metadata": {
        "royalty_id": "123"
      }
    }
  }
}
```
