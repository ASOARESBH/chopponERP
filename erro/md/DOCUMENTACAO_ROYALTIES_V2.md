> # Atualização do Módulo de Royalties e Contas a Pagar
> 
> **Versão:** 2.0
> **Data:** 2025-12-04
> 
> ## 1. Visão Geral
> 
> Esta atualização robustece o módulo de **Royalties**, automatizando a geração de cobranças via **Stripe Payment Links** e a criação de **Contas a Pagar** para os franqueados. O objetivo é simplificar o fluxo de cobrança, oferecer mais opções de pagamento e garantir a integridade dos dados financeiros.
> 
> ## 2. Novas Funcionalidades e Correções
> 
> ### Módulo de Royalties (`financeiro_royalties.php`)
> 
> O formulário de "Novo Lançamento" foi completamente redesenhado para incluir as seguintes melhorias:
> 
> - **Cálculo de Royalties Corrigido:** O valor dos royalties (7%) agora é calculado e exibido corretamente em tempo real, e o valor bruto é devidamente processado no backend.
> - **Data de Vencimento:** Adicionado campo `Data de Vencimento` para definir um prazo de pagamento customizado.
> - **E-mails de Cobrança:**
>   - **E-mail Principal:** Campo obrigatório para o envio da cobrança.
>   - **E-mails Adicionais:** Campo opcional para incluir outros destinatários (separados por vírgula).
> - **Seleção de Pagamento Dinâmica:**
>   - **Tipo de Cobrança:** Permite escolher entre `Stripe` e `Banco Cora`.
>   - **Forma de Pagamento:** As opções são atualizadas dinamicamente:
>     - **Stripe:** Fatura (Invoice) ou Link de Pagamento (Payment Link).
>     - **Cora:** Boleto + PIX.
> 
> ### Automação com Stripe Payment Links
> 
> Ao salvar um royalty com a opção **Stripe + Link de Pagamento**, o sistema executa as seguintes ações automaticamente:
> 
> 1. **Gera um Payment Link** via API do Stripe, criando um "Price" dinâmico para o valor exato do royalty.
> 2. **Envia um E-mail Automático** para o e-mail principal e os adicionais, contendo o link de pagamento e os detalhes da cobrança.
> 3. **Cria uma Conta a Pagar** para o estabelecimento, já com o link de pagamento anexado.
> 
> ### Módulo de Contas a Pagar (`financeiro_contas.php`)
> 
> - **Link de Pagamento Visível:** Contas geradas via royalties agora exibem um botão **"🔗 Link"** que abre o link de pagamento do Stripe em uma nova aba.
> - **Proteção de Valores:**
>   - O valor de uma conta a pagar originada de um royalty é **protegido**.
>   - Franqueados (usuários não-admin) **não podem editar** o valor dessas contas, garantindo a integridade da cobrança.
>   - O botão de edição é substituído por um ícone de cadeado `🔒` para indicar a proteção.
> 
> ## 3. Atualizações no Banco de Dados
> 
> Para suportar as novas funcionalidades, execute o script `database_royalties_update.sql` que realiza as seguintes alterações:
> 
> - **Tabela `royalties`:**
>   - `email_cobranca`
>   - `emails_adicionais`
>   - `data_vencimento`
>   - `forma_pagamento`
>   - `payment_link_id`
>   - `payment_link_url`
>   - `link_enviado_em`
> 
> - **Tabela `contas_pagar`:**
>   - `royalty_id`
>   - `payment_link_url`
>   - `valor_protegido`
>   - `origem`
> 
> ## 4. Arquivos Modificados
> 
> - `/admin/financeiro_royalties.php` (grandes alterações no formulário e processamento)
> - `/admin/financeiro_contas.php` (proteção de valores e exibição do link)
> - `/includes/stripe_api.php` (adicionado método para criar Payment Links)
> - `/admin/ajax/get_estabelecimento_email.php` (novo arquivo para buscar e-mail)
> - `database_royalties_update.sql` (novo script de atualização do banco)
> 
> ## 5. Instruções de Uso
> 
> 1. **Aplique as atualizações** do banco de dados executando o script `database_royalties_update.sql`.
> 2. **Substitua os arquivos** modificados no seu sistema.
> 3. **Limpe o cache** do seu navegador para garantir que as alterações de JavaScript sejam carregadas.
> 4. **Teste o fluxo:**
>    - Crie um novo lançamento de royalty.
>    - Selecione **Stripe** e **Link de Pagamento**.
>    - Verifique se o e-mail foi enviado e se a conta a pagar foi criada corretamente com o link e o valor protegido.
