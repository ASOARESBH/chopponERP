# Implementação do Módulo de Integrações (Stripe e Cora)

**Autor:** Manus AI
**Data:** 01 de Dezembro de 2025

## 1. Visão Geral

Este documento detalha a implementação do novo módulo de **Integrações**, que centraliza as configurações de APIs externas, e a integração do **Stripe Pagamentos** no sistema de gestão PHP. A funcionalidade permite a geração de faturas via Stripe como alternativa aos boletos do Banco Cora, com baixa automática de pagamentos via webhook.

## 2. Estrutura de Arquivos

| Caminho do Arquivo | Descrição |
|---|---|
| `admin/stripe_config.php` | **[Novo]** Página para cadastrar e gerenciar as credenciais da API Stripe. |
| `admin/cora_config.php` | **[Novo]** Página para cadastrar e gerenciar as credenciais da API do Banco Cora. |
| `admin/financeiro_royalties.php` | **[Modificado]** Adicionado campo para selecionar o método de cobrança (Cora ou Stripe) e lógica para gerar faturas via Stripe. |
| `includes/stripe_api.php` | **[Novo]** Classe PHP para encapsular a comunicação com a API do Stripe. |
| `includes/header.php` | **[Modificado]** Criado o menu "Integrações" e movido os submenus existentes para dentro dele, adicionando os novos submenus de configuração. |
| `webhook/stripe_webhook.php` | **[Novo]** Endpoint para receber e processar eventos do Stripe, como a confirmação de pagamentos. |
| `database_integracoes.sql` | **[Novo]** Script SQL para criar as tabelas `stripe_config`, `cora_config`, `stripe_invoices`, `stripe_webhooks` e modificar a tabela `royalties`. |

## 3. Funcionalidades Implementadas

### 3.1. Menu de Integrações

Foi criado um novo menu principal chamado **Integrações**, acessível apenas por Administradores Gerais. Este menu agora agrupa as seguintes configurações:

- **Config. E-mail**
- **Telegram**
- **Stripe Pagamentos (Novo)**
- **Banco Cora (Novo)**

### 3.2. Configuração de APIs

As novas páginas `stripe_config.php` e `cora_config.php` permitem que o administrador cadastre as credenciais de API para cada estabelecimento. Isso centraliza a gestão e facilita a manutenção.

#### Credenciais do Stripe

- **Publishable Key:** Chave pública do Stripe.
- **Secret Key:** Chave secreta do Stripe.
- **Webhook Signing Secret:** Chave para validar os eventos recebidos do webhook.

#### Credenciais do Banco Cora

- **Client ID:** Identificador do cliente na Cora.
- **Certificado Digital (.pem):** Arquivo de certificado para autenticação.
- **Chave Privada (.key):** Arquivo de chave privada para autenticação.

### 3.3. Geração de Faturas via Stripe

No lançamento de um novo royalty, o usuário agora pode escolher o **Tipo de Cobrança**:

- **Banco Cora (Padrão):** Gera um boleto com QR Code PIX, como já funcionava.
- **Stripe:** Gera uma fatura online e a envia por e-mail para o cliente.

Ao selecionar **Stripe**, o sistema executa o seguinte fluxo:

1.  **Criação do Cliente:** O sistema verifica se o cliente (estabelecimento) já existe no Stripe pelo e-mail. Se não, um novo cliente é criado.
2.  **Criação do Item:** Um item de fatura é criado com a descrição e o valor do royalty.
3.  **Criação da Fatura:** Uma fatura é gerada e associada ao cliente e ao item.
4.  **Finalização e Envio:** A fatura é finalizada e enviada automaticamente por e-mail para o cliente. A fatura hospedada pela Stripe permite que o cliente pague usando diversas formas de pagamento (cartão de crédito, boleto, PIX, etc.).

### 3.4. Baixa Automática de Pagamentos (Webhook)

Foi implementado um endpoint de webhook (`webhook/stripe_webhook.php`) que recebe notificações de eventos do Stripe.

- **URL do Webhook:** `https://[seu-dominio]/webhook/stripe_webhook.php`

Quando uma fatura é paga, o Stripe envia um evento `invoice.paid` para este endpoint. O sistema então realiza as seguintes ações:

1.  **Validação do Evento:** Verifica a assinatura do evento para garantir que ele veio do Stripe (implementação básica, recomendada validação completa em produção).
2.  **Busca da Fatura:** Localiza a fatura correspondente no banco de dados do sistema.
3.  **Atualização de Status:**
    - Atualiza o status da fatura na tabela `stripe_invoices` para **"pago"**.
    - Atualiza o status do royalty na tabela `royalties` para **"pago"**.
    - Atualiza o status da conta na tabela `contas_pagar` para **"pago"**.
4.  **Registro no Histórico:** Adiciona um registro no histórico do royalty informando que o pagamento foi confirmado via webhook.

## 4. Instruções de Instalação e Configuração

1.  **Banco de Dados:** Execute o script `database_integracoes.sql` no seu banco de dados para criar as novas tabelas e adicionar as colunas necessárias.

2.  **Arquivos:** Envie todos os novos arquivos e os arquivos modificados para o seu servidor.

3.  **Credenciais do Stripe:**
    - Acesse o [Dashboard do Stripe](https://dashboard.stripe.com).
    - Vá em **Developers → API keys** e copie a **Publishable Key** e a **Secret Key**.
    - Vá em **Developers → Webhooks** e clique em **Add endpoint**.
    - No campo **Endpoint URL**, insira `https://[seu-dominio]/webhook/stripe_webhook.php`.
    - Em **Select events**, selecione `invoice.paid`, `invoice.payment_failed` e `invoice.finalized`.
    - Após criar o endpoint, copie o **Signing secret**.
    - No sistema, vá em **Integrações → Stripe Pagamentos** e cadastre as credenciais.

4.  **Credenciais do Banco Cora:**
    - Obtenha o **Client ID**, o **Certificado** e a **Chave Privada** no aplicativo ou site do Banco Cora.
    - No sistema, vá em **Integrações → Banco Cora** e cadastre as credenciais.

5.  **Teste:**
    - Crie um novo lançamento de royalty e selecione **Stripe** como tipo de cobrança.
    - Gere a fatura e verifique se o e-mail é recebido.
    - Use os dados de cartão de teste do Stripe para pagar a fatura.
    - Verifique se o status do royalty e da conta a pagar são atualizados automaticamente para "pago".

---

Com estas etapas, o módulo de integrações e a cobrança via Stripe estarão totalmente funcionais.
