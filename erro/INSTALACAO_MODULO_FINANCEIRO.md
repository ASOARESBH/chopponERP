# Instalação do Módulo Financeiro - Chopp On Tap

## Descrição

Este módulo adiciona funcionalidades financeiras ao sistema Chopp On Tap, incluindo:

- **Taxas de Juros**: Cadastro de formas de pagamento (PIX, Crédito, Débito) com taxas personalizadas por bandeira
- **Contas a Pagar**: Gerenciamento completo de contas com notificações automáticas via Telegram

## Passo 1: Atualizar Banco de Dados

Execute o script SQL para criar as novas tabelas:

```bash
mysql -u SEU_USUARIO -p SEU_BANCO < database_financeiro.sql
```

Ou acesse o phpMyAdmin e execute o conteúdo do arquivo `database_financeiro.sql`.

### Tabelas Criadas

1. **formas_pagamento**: Armazena formas de pagamento com taxas
2. **contas_pagar**: Armazena contas a pagar
3. **historico_notificacoes_contas**: Histórico de notificações enviadas

### Alterações em Tabelas Existentes

- Adicionado campo `forma_pagamento_id` na tabela `order` para relacionar vendas com formas de pagamento
- Adicionado campo `taxa_aplicada` na tabela `order` para registrar taxa aplicada

## Passo 2: Verificar Arquivos Criados

Certifique-se de que os seguintes arquivos foram criados:

### Páginas Admin
- `/admin/financeiro_taxas.php` - Gerenciamento de taxas de juros
- `/admin/financeiro_contas.php` - Gerenciamento de contas a pagar

### Scripts CRON
- `/cron/notificar_contas_vencer.php` - Script para notificações automáticas

## Passo 3: Configurar CRON Job

Para receber notificações automáticas de contas a vencer, configure o CRON:

```bash
# Editar crontab
crontab -e

# Adicionar linha (executa diariamente às 08:00)
0 8 * * * /usr/bin/php /caminho/completo/para/cron/notificar_contas_vencer.php >> /caminho/para/logs/contas_vencer.log 2>&1
```

**Importante**: Substitua `/caminho/completo/para/` pelo caminho real do seu projeto.

### Testar Script Manualmente

```bash
php /caminho/para/cron/notificar_contas_vencer.php
```

## Passo 4: Configurar Telegram (Obrigatório para Notificações)

As notificações de contas a vencer usam a mesma configuração de Telegram já existente no sistema.

1. Acesse **Admin → Telegram**
2. Configure o Bot Token e Chat ID do seu estabelecimento
3. Ative as notificações

### Como Obter Bot Token e Chat ID

Consulte o arquivo `TELEGRAM_INTEGRATION.md` para instruções detalhadas.

## Passo 5: Atualizar Menu de Navegação

O menu "Financeiro" será adicionado automaticamente ao sidebar. Certifique-se de que o arquivo `/includes/header.php` foi atualizado.

## Funcionalidades

### 1. Taxas de Juros (Formas de Pagamento)

**Acesso**: Admin → Financeiro → Taxas de Juros

**Recursos**:
- Cadastro de formas de pagamento: PIX, Crédito, Débito
- Definição de bandeiras (Mastercard, Visa, Elo, etc.)
- Taxa percentual e taxa fixa por forma de pagamento
- Ativar/desativar formas de pagamento
- Relatórios de taxas aplicadas nas vendas

**Uso**:
1. Cadastre as formas de pagamento aceitas
2. Defina as taxas cobradas pela operadora (ex: SumUp)
3. O sistema calculará automaticamente o valor líquido das vendas

### 2. Contas a Pagar

**Acesso**: Admin → Financeiro → Contas a Pagar

**Recursos**:
- Cadastro de contas com descrição, tipo, valor e vencimento
- Armazenamento de código de barras e link de pagamento
- Filtros por status e período
- Marcar contas como pagas
- Dashboard com resumo financeiro
- Notificações automáticas via Telegram

**Notificações Telegram**:
- **3 dias antes**: Lembrete de conta próxima ao vencimento
- **No dia**: Alerta de conta vencendo hoje
- **Após vencimento**: Alerta de conta vencida

**Informações Enviadas**:
- Descrição da conta
- Tipo (Água, Luz, Aluguel, etc.)
- Valor
- Data de vencimento
- Código de barras (se cadastrado)
- Link de pagamento (se cadastrado)

## Permissões

### Admin Geral
- Acesso total a todos os estabelecimentos
- Cadastro e edição de taxas
- Cadastro e edição de contas
- Visualização de relatórios consolidados

### Admin Estabelecimento / Gerente
- Acesso apenas ao seu estabelecimento
- Cadastro e edição de taxas
- Cadastro e edição de contas
- Visualização de relatórios do estabelecimento

### Operador
- Visualização apenas (sem permissão de edição)

## Relatórios Disponíveis

### Taxas de Juros
- Formas de pagamento cadastradas
- Taxas por bandeira
- Comparativo de taxas

### Contas a Pagar
- Contas pendentes
- Contas vencidas
- Contas pagas no período
- Total a pagar no mês
- Histórico de pagamentos

## Integração com Vendas

O sistema relaciona automaticamente as vendas (tabela `order`) com as formas de pagamento cadastradas:

- Campo `forma_pagamento_id`: Relaciona a venda com a forma de pagamento
- Campo `taxa_aplicada`: Armazena o valor da taxa aplicada na transação

Isso permite:
- Calcular o valor líquido recebido
- Gerar relatórios de taxas pagas
- Análise de custo por forma de pagamento

## Manutenção

### Limpeza de Dados Antigos

Para manter o banco de dados organizado, você pode excluir contas pagas antigas:

```sql
-- Excluir contas pagas há mais de 1 ano
DELETE FROM contas_pagar 
WHERE status = 'pago' 
AND data_pagamento < DATE_SUB(CURDATE(), INTERVAL 1 YEAR);
```

### Backup

Sempre faça backup antes de executar operações de limpeza:

```bash
mysqldump -u usuario -p banco_de_dados contas_pagar > backup_contas_pagar.sql
```

## Troubleshooting

### Notificações não estão sendo enviadas

1. Verifique se o CRON está configurado corretamente
2. Verifique se o Telegram está configurado no sistema
3. Execute o script manualmente para ver erros:
   ```bash
   php /caminho/para/cron/notificar_contas_vencer.php
   ```
4. Verifique os logs em `/logs/`

### Erro ao cadastrar forma de pagamento

- Verifique se o estabelecimento está ativo
- Verifique se não há duplicação (mesmo tipo + bandeira)
- Verifique permissões do usuário

### Erro ao cadastrar conta

- Verifique se a data de vencimento é válida
- Verifique se o valor está no formato correto
- Verifique se o estabelecimento está ativo

## Suporte

Para dúvidas ou problemas:
- Consulte a documentação completa em `/docs/`
- Verifique os logs do sistema em `/logs/`
- Entre em contato com o suporte técnico

## Versão

- **Módulo Financeiro**: v1.0.0
- **Compatível com**: Chopp On Tap v3.0+
- **Data**: Novembro 2025
