# Integração API Banco Cora - Emissão de Boletos

## Credenciais Necessárias

Para integrar com a API do Banco Cora, você precisará obter as seguintes credenciais através do aplicativo Cora ou Cora Web:

1. **Client ID** (ex: int-hash)
2. **Certificado Digital** (arquivo .pem)
3. **Private Key** (arquivo .key)

**IMPORTANTE:** Cada ambiente (Stage/Produção) possui seu próprio conjunto de credenciais.

## Fluxo de Autenticação

### 1. Obter Token de Acesso (Client Credentials)

**Endpoint de Autenticação:**
- **Stage:** `https://matls-clients.api.stage.cora.com.br/token`
- **Produção:** `https://matls-clients.api.cora.com.br/token`

**Método:** POST

**Headers:**
- Content-Type: application/x-www-form-urlencoded

**Parâmetros:**
- grant_type: client_credentials
- client_id: {seu_client_id}

**Certificados mTLS:**
- Certificado: certificate.pem
- Private Key: private-key.key

**Resposta:**
```json
{
  "access_token": "eyJhbGciOiJSUzI1NiIs...",
  "expires_in": 86400,
  "token_type": "Bearer",
  "scope": "offline_access"
}
```

## Emissão de Boleto Registrado

### Endpoint
- **Stage:** `https://api.stage.cora.com.br/v2/invoices/`
- **Produção:** `https://api.cora.com.br/v2/invoices/`

**Método:** POST

### Headers Obrigatórios
- `Authorization: Bearer {access_token}`
- `Idempotency-Key: {uuid}` (UUID único para evitar duplicação)
- `Content-Type: application/json`
- `Accept: application/json`

### Estrutura da Requisição

```json
{
  "code": "meu_id_interno",
  "customer": {
    "name": "Nome do Cliente",
    "email": "cliente@email.com",
    "document": {
      "identity": "12345678901234",
      "type": "CNPJ"
    },
    "address": {
      "street": "Rua Exemplo",
      "number": "123",
      "district": "Bairro",
      "city": "Cidade",
      "state": "SP",
      "complement": "Complemento",
      "zip_code": "12345678"
    }
  },
  "services": [
    {
      "name": "Royalties",
      "description": "Cobrança de Royalties - Período XX/XX/XXXX a XX/XX/XXXX",
      "amount": 1000
    }
  ],
  "payment_terms": {
    "due_date": "2024-12-31",
    "fine": {
      "amount": 100
    },
    "interest": {
      "amount": 50
    }
  },
  "payment_forms": ["BANK_SLIP", "PIX"]
}
```

### Campos Importantes

**Valores Monetários:**
- Todos os valores são em **centavos** (inteiros)
- Exemplo: R$ 10,01 = 1001

**Customer (Obrigatório):**
- name: Nome do cliente (máx 60 caracteres)
- email: Email do cliente (opcional, máx 60 caracteres)
- document.identity: CPF ou CNPJ (apenas números)
- document.type: "CPF" ou "CNPJ"
- address: Endereço completo (opcional)

**Services (Obrigatório):**
- Lista de serviços/produtos
- name: Nome do serviço
- description: Descrição (máx 100 caracteres)
- amount: Valor em centavos

**Payment Terms (Obrigatório):**
- due_date: Data de vencimento (formato AAAA-MM-DD)
- fine: Multa (opcional)
  - amount: Valor fixo em centavos OU
  - rate: Percentual (ex: 2.00 para 2%)
- interest: Juros (opcional)
  - amount: Valor fixo em centavos OU
  - rate: Percentual mensal
- discount: Desconto (opcional)

**Payment Forms:**
- "BANK_SLIP": Apenas boleto
- ["BANK_SLIP", "PIX"]: Boleto com QR Code Pix

### Resposta da API

```json
{
  "id": "uuid-do-boleto",
  "status": "PENDING",
  "created_at": "2024-12-01T10:00:00Z",
  "total_amount": 1000,
  "total_paid": 0,
  "code": "meu_id_interno",
  "customer": {...},
  "services": [...],
  "payment_terms": {...},
  "payment_options": {
    "bank_slip": {
      "digitable_line": "linha digitável do boleto",
      "barcode": "código de barras"
    },
    "pix": {
      "qr_code": "código QR Code Pix",
      "emv": "string EMV do Pix"
    }
  },
  "payments": []
}
```

## Status do Boleto

- **PENDING**: Aguardando pagamento
- **PAID**: Pago
- **CANCELLED**: Cancelado
- **EXPIRED**: Vencido

## Erros Comuns

- **401 Unauthorized**: Token inválido ou expirado
- **400 Bad Request**: 
  - Idempotency-Key inválido (deve ser UUID)
  - Valor menor que R$ 5,00 (500 centavos)
  - Data de vencimento anterior à data atual
  - Campos obrigatórios faltando
- **415 Unsupported Media Type**: Falta Content-Type: application/json

## Observações

1. Token de acesso expira em 86400 segundos (24 horas)
2. Valor mínimo do boleto: R$ 5,00
3. Para gerar QR Code Pix, é necessário ter chave Pix cadastrada
4. Pagamento via Pix cancela automaticamente o boleto
5. Idempotency-Key deve ser único para cada requisição (usar UUID v4)
