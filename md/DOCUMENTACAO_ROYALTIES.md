# Implementação do Módulo de Royalties e Integração com Banco Cora

**Autor:** Manus AI
**Data:** 01 de Dezembro de 2025

## 1. Visão Geral

Este documento detalha a implementação do novo módulo de **Royalties** no sistema de gestão PHP. A funcionalidade foi desenvolvida para permitir o lançamento manual de cobranças de royalties, com cálculo automático, geração de boletos via integração direta com a API do **Banco Cora** e a criação automática de uma despesa correspondente no módulo de **Contas a Pagar**.

## 2. Estrutura de Arquivos

Os seguintes arquivos foram criados ou modificados para suportar a nova funcionalidade:

| Caminho do Arquivo                               | Descrição                                                                                                  |
| ------------------------------------------------ | ---------------------------------------------------------------------------------------------------------- |
| `admin/financeiro_royalties.php`                 | **[Novo]** Página principal do módulo, contendo o formulário de lançamento e a listagem das cobranças.          |
| `admin/ajax/get_boleto_royalty.php`              | **[Novo]** Endpoint AJAX para buscar e exibir os detalhes de um boleto gerado.                               |
| `includes/cora_api.php`                          | **[Novo]** Classe PHP dedicada para encapsular a comunicação com a API do Banco Cora.                       |
| `includes/header.php`                            | **[Modificado]** Adicionado o link para o submenu "Royalties" dentro do menu "Financeiro".                 |
| `database_royalties.sql`                         | **[Novo]** Script SQL para criar as novas tabelas `royalties` e `royalties_historico` no banco de dados. |
| `cora_config.example.php`                        | **[Novo]** Arquivo de exemplo para configuração das credenciais da API Cora.                               |
| `certs/.gitkeep`                                 | **[Novo]** Diretório criado para armazenar os arquivos de certificado e chave da API Cora.                 |

## 3. Funcionalidades Implementadas

### 3.1. Lançamento Manual de Royalties

A página `financeiro_royalties.php` apresenta uma interface para o lançamento manual de cobranças. O formulário inclui os seguintes campos:

- **Período Inicial e Final:** Define o intervalo de tempo da cobrança.
- **Descrição:** Um texto descritivo para a cobrança (ex: "Royalties de Dezembro/2025").
- **Valor do Faturamento Bruto:** O valor total faturado pelo estabelecimento no período.
- **Estabelecimento:** Seleção do estabelecimento a ser cobrado (disponível apenas para Administradores Gerais).

O sistema calcula automaticamente o valor dos royalties, aplicando um percentual fixo de **7%** sobre o faturamento bruto informado. Ao salvar, um novo registro é criado na tabela `royalties`.

### 3.2. Integração com API do Banco Cora

Após o cadastro de um royalty com status "Pendente", o usuário pode gerar o boleto de cobrança. Este processo realiza as seguintes ações:

1.  **Autenticação:** A classe `CoraAPI` se autentica na API Cora utilizando o fluxo *Client Credentials* com certificados mTLS, obtendo um token de acesso.
2.  **Construção da Requisição:** Os dados do royalty e do estabelecimento são formatados em uma estrutura JSON, conforme a documentação da API Cora.
3.  **Emissão do Boleto:** A requisição é enviada para o endpoint de emissão de boletos da Cora. Um `Idempotency-Key` (UUID v4) é gerado para garantir que a mesma cobrança não seja processada em duplicidade.
4.  **Armazenamento dos Dados:** Se a emissão for bem-sucedida, o sistema armazena o ID do boleto, a linha digitável, o código de barras e o QR Code do Pix na tabela `royalties` e atualiza o status para "Boleto Gerado".

### 3.3. Geração de Contas a Pagar

Simultaneamente à geração do boleto, o sistema cria automaticamente um novo registro na tabela `contas_pagar`. Esta conta a pagar é vinculada ao estabelecimento cobrado e contém os detalhes do boleto (valor, data de vencimento, código de barras), facilitando o controle financeiro interno.

## 4. Credenciais para Integração com Banco Cora

Para que a integração com o Banco Cora funcione, é **imprescindível** que você configure as credenciais corretamente. Abaixo estão os dados necessários e como obtê-los.

> **Nota Importante:** A integração direta com a API da Cora exige a assinatura do plano **CoraPro**.

| Credencial Requerida     | Descrição                                                                                                                                                                                             |
| ------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Client ID**            | Um identificador único para sua aplicação.                                                                                                                                                            |
| **Certificado Digital**    | Um arquivo com extensão `.pem`, utilizado para autenticação segura (mTLS).                                                                                                                            |
| **Chave Privada**        | Um arquivo com extensão `.key`, correspondente ao seu certificado digital.                                                                                                                            |
| **Ambiente**             | Define se as operações serão realizadas no ambiente de testes (`stage`) ou no ambiente real (`production`). Cada ambiente possui seu próprio conjunto de credenciais. |

### Como Obter e Configurar as Credenciais

1.  **Solicitação na Cora:** Acesse sua conta no aplicativo Cora ou no Cora Web, navegue até a seção de **Integrações (API)** e solicite as credenciais para a **Integração Direta**. Faça o download do seu **Certificado** e da **Chave Privada**.
2.  **Armazenamento Seguro:** Crie um diretório chamado `certs` na raiz do projeto e salve os arquivos `certificate.pem` e `private-key.key` dentro dele.
3.  **Configuração no Sistema:** Renomeie o arquivo `cora_config.example.php` para `cora_config.php`. Abra este novo arquivo e preencha o `CORA_CLIENT_ID` e verifique se os caminhos para os arquivos de certificado estão corretos. Defina o `CORA_ENVIRONMENT` como `stage` para testes ou `production` para operações reais.
4.  **Inclusão no Código:** O arquivo `financeiro_royalties.php` foi programado para carregar estas configurações. Certifique-se de que o arquivo `cora_config.php` **não seja versionado no Git** para evitar a exposição de suas credenciais.

---

Com estas etapas, o módulo de Royalties estará totalmente funcional e integrado ao Banco Cora.
