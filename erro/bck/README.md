# Sistema Chopp On Tap - PHP Procedural

Sistema de gerenciamento de choperias autônomas com integração SumUp e controle de fluxo.

## Características

- **PHP Procedural Simples**: Sem frameworks, fácil de manter
- **HTML Estático**: Interface responsiva e clean
- **MySQL**: Banco de dados relacional
- **Integração SumUp**: PIX, Crédito e Débito
- **API REST**: Comunicação com app Android (controladora de fluxo)
- **Webhooks**: Atualização automática de status de pagamento

## Requisitos

- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- Extensões PHP: pdo_mysql, curl, json, gd
- Servidor web: Apache ou Nginx

## Instalação no HostGator

### 1. Upload dos Arquivos

Faça upload de todos os arquivos para o diretório `public_html` ou subdiretório desejado.

### 2. Configurar Banco de Dados

Edite o arquivo `includes/config.php` e configure as credenciais:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'inlaud99_choppontap');
define('DB_USER', 'inlaud99_admin');
define('DB_PASS', 'Admin259087@');
```

### 3. Importar Banco de Dados

Acesse o **phpMyAdmin** no painel do HostGator e:

1. Selecione o banco de dados `inlaud99_choppontap`
2. Clique em **Importar**
3. Selecione o arquivo `database.sql`
4. Clique em **Executar**

### 4. Configurar Permissões

Certifique-se de que as seguintes pastas tenham permissão de escrita (755 ou 777):

```bash
chmod 755 uploads/
chmod 755 uploads/bebidas/
chmod 755 logs/
```

### 5. Configurar Token SumUp

1. Acesse o sistema com o usuário admin: `choppon24h@gmail.com` / `Admin259087@`
2. Vá em **Pagamentos**
3. Insira o token da API SumUp
4. Configure os métodos de pagamento habilitados

### 6. Configurar Webhook SumUp

No painel da SumUp, configure o webhook para:

```
https://seudominio.com.br/api/webhook.php
```

## Estrutura de Diretórios

```
choppon_new/
├── index.php                 # Página de login
├── admin/                    # Área administrativa
│   ├── dashboard.php
│   ├── bebidas.php
│   ├── taps.php
│   ├── pagamentos.php
│   ├── pedidos.php
│   ├── usuarios.php
│   ├── estabelecimentos.php
│   ├── logout.php
│   └── ajax/                 # Endpoints AJAX
├── api/                      # API REST
│   ├── login.php
│   ├── validate_token.php
│   ├── verify_tap.php
│   ├── create_order.php
│   ├── verify_checkout.php
│   ├── liquido_liberado.php
│   ├── liberacao.php
│   ├── cancel_order.php
│   ├── bebidas.php
│   ├── taps.php
│   └── webhook.php
├── includes/                 # Arquivos de configuração
│   ├── config.php
│   ├── auth.php
│   ├── sumup.php
│   ├── jwt.php
│   ├── header.php
│   └── footer.php
├── assets/                   # CSS, JS e imagens
│   ├── css/
│   ├── js/
│   └── images/
├── uploads/                  # Upload de imagens
│   └── bebidas/
├── logs/                     # Logs do sistema
└── database.sql              # Script de criação do banco

```

## Credenciais Padrão

**Administrador Geral:**
- Email: `choppon24h@gmail.com`
- Senha: `Admin259087@`

## API REST

### Autenticação

Todas as requisições da API (exceto login) devem incluir o header:

```
Token: <jwt_token>
```

### Endpoints Disponíveis

#### POST /api/login.php
Login e geração de token JWT

**Request:**
```json
{
  "email": "choppon24h@gmail.com",
  "password": "Admin259087@"
}
```

**Response:**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "user": {
    "id": 1,
    "name": "Admin",
    "email": "choppon24h@gmail.com",
    "type": 1
  },
  "isAdmin": true
}
```

#### POST /api/verify_tap.php
Verificar informações da TAP

**Request:**
```json
{
  "android_id": "ABC123"
}
```

**Response:**
```json
{
  "image": "https://...",
  "preco": 5.00,
  "bebida": "Heineken",
  "volume": 45.5,
  "cartao": true
}
```

#### POST /api/create_order.php
Criar novo pedido

**Request:**
```json
{
  "valor": 5.00,
  "descricao": "Heineken 100ml",
  "android_id": "ABC123",
  "payment_method": "pix",
  "quantidade": 100,
  "cpf": "12345678900"
}
```

**Response (PIX):**
```json
{
  "checkout_id": "abc-123",
  "qr_code": "data:image/png;base64,..."
}
```

#### POST /api/verify_checkout.php
Verificar se pagamento foi aprovado

**Request:**
```json
{
  "android_id": "ABC123",
  "checkout_id": "abc-123"
}
```

**Response:**
```json
{
  "status": "success"
}
```

#### POST /api/liquido_liberado.php
Atualizar volume consumido

**Request:**
```json
{
  "android_id": "ABC123",
  "qtd_ml": 100
}
```

#### POST /api/liberacao.php?action=iniciada
Marcar início da liberação

**Request:**
```json
{
  "checkout_id": "abc-123"
}
```

#### POST /api/liberacao.php?action=finalizada
Marcar fim da liberação

**Request:**
```json
{
  "checkout_id": "abc-123",
  "qtd_ml": 100
}
```

#### POST /api/cancel_order.php
Cancelar pedido

**Request:**
```json
{
  "checkout_id": "abc-123"
}
```

## Integração SumUp

O sistema integra com a API SumUp para processar pagamentos:

- **PIX**: Gera QR Code dinâmico
- **Cartão**: Utiliza leitora pareada com a TAP
- **Webhook**: Recebe notificações de status

### Configuração

1. Obtenha o token da API SumUp
2. Configure no painel administrativo em **Pagamentos**
3. Para usar cartão, configure o `pairing_code` em cada TAP

## Níveis de Usuário

1. **Admin Geral**: Acesso total ao sistema
2. **Admin Estabelecimento**: Gerencia um ou mais estabelecimentos
3. **Gerente Estabelecimento**: Visualiza e gerencia estabelecimento
4. **Operador**: Acesso limitado

## Suporte

Para dúvidas ou problemas, entre em contato através de choppon24h@gmail.com
