# 🚀 Documentação - Sistema de Royalties Reescrito (v4.0)

## Visão Geral

Este documento detalha a nova implementação do sistema de royalties, que foi **completamente reescrito do zero** para ser mais robusto, seguro e funcional. A nova arquitetura é modular e fácil de manter.

---

## 📂 Estrutura de Arquivos

| Arquivo | Localização | Descrição |
|---|---|---|
| `financeiro_royalties.php` | `/admin/` | **Página principal reescrita.** Contém backend, frontend, formulário, listagem e modais. |
| `RoyaltiesManager.php` | `/includes/` | **Classe principal de lógica.** Gerencia criação, validação, geração de links e e-mails. |
| `EmailTemplate.php` | `/includes/` | **Templates de e-mail.** Gera HTML profissional para cobrança e confirmação. |
| `royalties_actions.php` | `/admin/ajax/` | **Ações AJAX.** Processa requisições como gerar link, enviar e-mail, buscar dados, etc. |
| `get_estabelecimento_email.php` | `/admin/ajax/` | **Busca dados do estabelecimento.** Retorna CNPJ e e-mail. |
| `RoyaltiesLogger.php` | `/includes/` | **Sistema de logs.** Registra todas as ações e erros. |
| `logs_viewer.php` | `/admin/` | **Visualizador de logs.** Interface para ver logs em tempo real. |

---

## ✨ Funcionalidades Implementadas

### 1. **Formulário de Lançamento Inteligente**
- **Cálculo automático** de 7% de royalties em tempo real.
- **Preenchimento automático** de CNPJ e e-mail ao selecionar estabelecimento.
- **Validação completa** de campos (período, valores, e-mails).
- **Máscara de moeda** (R$) no campo de faturamento.
- **Data de vencimento padrão** (30 dias).

### 2. **Tela de Conferência Pré-Geração**
- Antes de gerar o link, um modal exibe **todos os dados para conferência**.
- **Preview do e-mail** que será enviado ao cliente.
- **Preview do link Stripe** que será gerado.
- Botões para **Gerar Link**, **Enviar E-mail** ou **Gerar & Enviar Tudo**.

### 3. **Integração Robusta com Stripe**
- Geração de **Payment Links** com métodos de pagamento customizáveis (Boleto+PIX, Cartão+PIX, Todos).
- **Metadados** enviados ao Stripe para vincular pagamento ao royalty.
- **Webhook** para atualização automática de status para "Pago".

### 4. **Sistema de E-mail Profissional**
- **Templates HTML** modernos e responsivos.
- Envio para **múltiplos destinatários** (principal + adicionais).
- E-mails de **cobrança** e **confirmação de pagamento**.

### 5. **Listagem com Filtros e Ações**
- **Filtros** por estabelecimento, status e período.
- **Badges de status** coloridos (Pendente, Link Gerado, Enviado, Pago, Cancelado).
- **Ações rápidas:** Visualizar, Gerar Link, Reenviar E-mail, Cancelar.

### 6. **Criação Automática de Contas a Pagar**
- Ao gerar um link de pagamento, uma **conta a pagar é criada automaticamente** para o estabelecimento.
- O valor é **protegido** e não pode ser alterado.
- O link de pagamento é **vinculado** à conta a pagar.

### 7. **Sistema de Logs Completo**
- **Logs detalhados** para Royalties, Stripe e Cora.
- **Visualizador de logs** em tempo real no painel admin.
- Facilita a **identificação de erros** e o debug.

---

## 🚀 Como Instalar

1. **Faça backup** dos arquivos antigos.
2. **Copie os novos arquivos** para seus respectivos diretórios:
   - `financeiro_royalties.php` → `/admin/`
   - `RoyaltiesManager.php` → `/includes/`
   - `EmailTemplate.php` → `/includes/`
   - `royalties_actions.php` → `/admin/ajax/`
3. **Execute o script SQL** para atualizar o banco de dados (se necessário).
4. **Limpe o cache** do PHP (OPcache) e do navegador (Ctrl + F5).
5. **Teste** criando um novo royalty.

---

## 💡 Vantagens da Nova Arquitetura

- **Código Limpo e Organizado:** Lógica de negócio separada da apresentação.
- **Fácil Manutenção:** Alterações são feitas em arquivos específicos sem impactar o resto.
- **Segurança:** Uso de prepared statements e validação de dados.
- **Escalabilidade:** Fácil de adicionar novas funcionalidades ou integrações.
- **Robustez:** Tratamento de erros com logs detalhados.

Esta reescrita garante um sistema de royalties muito mais confiável e profissional. ✨
