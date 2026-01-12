# 💰 Módulo Financeiro - Chopp On Tap

## Visão Geral

O **Módulo Financeiro** adiciona funcionalidades completas de gestão financeira ao sistema Chopp On Tap, permitindo controle de taxas de pagamento e contas a pagar com notificações automáticas via Telegram.

## 🎯 Funcionalidades

### 1. Taxas de Juros (Formas de Pagamento)

Gerencie todas as formas de pagamento aceitas pelo seu estabelecimento com controle completo de taxas:

- **PIX**: Configure taxas para pagamentos via PIX
- **Crédito**: Cadastre bandeiras (Mastercard, Visa, Elo, etc.) com taxas específicas
- **Débito**: Configure taxas por bandeira de débito

**Recursos:**
- Taxa percentual (ex: 2,5% sobre o valor)
- Taxa fixa (ex: R$ 0,50 por transação)
- Ativar/desativar formas de pagamento
- Relacionamento automático com vendas
- Cálculo de valor líquido recebido

### 2. Contas a Pagar

Sistema completo de gestão de contas a pagar com notificações inteligentes:

**Cadastro de Contas:**
- Descrição detalhada
- Tipo (Água, Luz, Aluguel, Fornecedor, etc.)
- Valor
- Data de vencimento
- Código de barras
- Link de pagamento
- Observações

**Recursos:**
- Dashboard com resumo financeiro
- Filtros por status e período
- Marcar contas como pagas
- Histórico completo de pagamentos
- Alertas de vencimento

**Notificações Telegram:**
- ⏰ **3 dias antes**: Lembrete de vencimento próximo
- 🔔 **No dia**: Alerta de conta vencendo hoje
- 🚨 **Após vencimento**: Alerta urgente de conta vencida

## 📁 Estrutura de Arquivos

```
PHP/
├── admin/
│   ├── financeiro_taxas.php      # Gerenciamento de taxas
│   └── financeiro_contas.php     # Gerenciamento de contas
├── cron/
│   └── notificar_contas_vencer.php  # Script de notificações
├── includes/
│   └── header.php                # Atualizado com menu Financeiro
├── assets/
│   ├── css/style.css            # Estilos do submenu
│   └── js/main.js               # JavaScript do submenu
├── database_financeiro.sql       # Script de instalação do BD
├── install_financeiro.php        # Instalador automático
├── INSTALACAO_MODULO_FINANCEIRO.md  # Documentação detalhada
└── MODULO_FINANCEIRO_README.md   # Este arquivo
```

## 🗄️ Estrutura do Banco de Dados

### Tabelas Criadas

1. **formas_pagamento**
   - Armazena formas de pagamento com taxas
   - Relacionamento com estabelecimentos
   - Suporte a múltiplas bandeiras

2. **contas_pagar**
   - Cadastro completo de contas
   - Status (pendente, pago, vencido, cancelado)
   - Dados de pagamento

3. **historico_notificacoes_contas**
   - Histórico de todas as notificações enviadas
   - Rastreamento de sucesso/falha

### Alterações em Tabelas Existentes

- **order**: Adicionados campos `forma_pagamento_id` e `taxa_aplicada`

## 🚀 Instalação Rápida

### Opção 1: Instalador Automático (Recomendado)

```bash
# Via navegador
http://seu-dominio.com/install_financeiro.php

# Via linha de comando
php install_financeiro.php
```

### Opção 2: Instalação Manual

```bash
# 1. Executar SQL
mysql -u usuario -p banco < database_financeiro.sql

# 2. Configurar CRON
crontab -e
# Adicionar: 0 8 * * * /usr/bin/php /caminho/para/cron/notificar_contas_vencer.php

# 3. Acessar o sistema
```

## 📊 Como Usar

### Taxas de Juros

1. Acesse **Financeiro → Taxas de Juros**
2. Clique em **"Nova Forma de Pagamento"**
3. Selecione o tipo (PIX, Crédito ou Débito)
4. Para Crédito/Débito, escolha a bandeira
5. Configure as taxas (percentual e/ou fixa)
6. Salve

**Exemplo de Configuração:**
- **PIX**: 0% taxa (sem custo)
- **Crédito Mastercard**: 2,5% + R$ 0,00
- **Débito Visa**: 1,5% + R$ 0,00

### Contas a Pagar

1. Acesse **Financeiro → Contas a Pagar**
2. Clique em **"Nova Conta"**
3. Preencha os dados:
   - Descrição: "Conta de Luz - Dezembro/2025"
   - Tipo: "Luz"
   - Valor: R$ 350,00
   - Vencimento: 10/12/2025
   - Código de Barras: (opcional)
   - Link de Pagamento: (opcional)
4. Salve

**Gerenciamento:**
- Use filtros para visualizar contas por status
- Clique em "👁️" para ver detalhes completos
- Clique em "💰 Pagar" para marcar como paga
- Clique em "✏️" para editar
- Clique em "🗑️" para excluir

## 🤖 Notificações Telegram

### Configuração

1. Acesse **Admin → Telegram**
2. Configure Bot Token e Chat ID
3. Ative as notificações

### Mensagens Enviadas

**Exemplo de Notificação (Vencimento Hoje):**
```
🔔 CONTAS VENCENDO HOJE

📅 Data: 26/11/2025
🏪 Estabelecimento: Chopp On Tap - Matriz

━━━━━━━━━━━━━━━━━━━━
📋 Conta de Luz - Novembro/2025
🏷️ Tipo: Luz
💰 Valor: R$ 350,00
📆 Vencimento: 26/11/2025
📊 Código de Barras:
34191.79001 01043.510047 91020.150008 1 96610000035000

⚠️ Atenção: Estas contas vencem HOJE!
```

## 📈 Relatórios e Análises

### Dashboard de Contas

O sistema exibe automaticamente:
- 💰 Total de contas pendentes
- ⚠️ Total de contas vencidas
- ✅ Total de contas pagas no período

### Integração com Vendas

Todas as vendas (tabela `order`) são automaticamente relacionadas com:
- Forma de pagamento utilizada
- Taxa aplicada na transação
- Valor líquido recebido

Isso permite análises como:
- Custo total por forma de pagamento
- Comparativo de taxas entre bandeiras
- Valor líquido vs. valor bruto

## 🔧 Manutenção

### CRON Job

O script de notificações deve ser executado diariamente:

```bash
# Configuração recomendada (08:00 da manhã)
0 8 * * * /usr/bin/php /caminho/para/cron/notificar_contas_vencer.php >> /var/log/contas_vencer.log 2>&1
```

### Teste Manual

```bash
# Executar manualmente para testar
php /caminho/para/cron/notificar_contas_vencer.php
```

### Limpeza de Dados Antigos

```sql
-- Excluir contas pagas há mais de 1 ano
DELETE FROM contas_pagar 
WHERE status = 'pago' 
AND data_pagamento < DATE_SUB(CURDATE(), INTERVAL 1 YEAR);

-- Limpar histórico de notificações antigas
DELETE FROM historico_notificacoes_contas 
WHERE created_at < DATE_SUB(CURDATE(), INTERVAL 6 MONTH);
```

## 🔐 Permissões

### Admin Geral
✅ Acesso a todos os estabelecimentos  
✅ Cadastro e edição de taxas  
✅ Cadastro e edição de contas  
✅ Relatórios consolidados  

### Admin Estabelecimento / Gerente
✅ Acesso ao seu estabelecimento  
✅ Cadastro e edição de taxas  
✅ Cadastro e edição de contas  
✅ Relatórios do estabelecimento  

### Operador
👁️ Visualização apenas (sem edição)

## 🆘 Troubleshooting

### Notificações não estão sendo enviadas

1. Verifique se o CRON está configurado:
   ```bash
   crontab -l | grep notificar_contas
   ```

2. Execute manualmente para ver erros:
   ```bash
   php /caminho/para/cron/notificar_contas_vencer.php
   ```

3. Verifique configuração do Telegram em **Admin → Telegram**

4. Verifique logs do sistema em `/logs/`

### Erro ao cadastrar forma de pagamento

- ✓ Estabelecimento está ativo?
- ✓ Não há duplicação (tipo + bandeira)?
- ✓ Usuário tem permissão?

### Menu Financeiro não aparece

1. Limpe cache do navegador (Ctrl + F5)
2. Verifique se o arquivo `includes/header.php` foi atualizado
3. Verifique se os arquivos CSS e JS foram atualizados

## 📞 Suporte

- 📖 Documentação completa: `INSTALACAO_MODULO_FINANCEIRO.md`
- 📋 Logs do sistema: `/logs/`
- 🐛 Relatório de bugs: Entre em contato com o suporte técnico

## 📝 Changelog

### v1.0.0 (Novembro 2025)
- ✨ Lançamento inicial do módulo
- ✨ Gestão de taxas de juros por forma de pagamento
- ✨ Sistema completo de contas a pagar
- ✨ Notificações automáticas via Telegram
- ✨ Integração com sistema de vendas
- ✨ Dashboard financeiro
- ✨ Relatórios e filtros avançados

## 📄 Licença

Este módulo é parte do sistema **Chopp On Tap** e segue a mesma licença do sistema principal.

---

**Desenvolvido para:** Chopp On Tap v3.0+  
**Versão do Módulo:** 1.0.0  
**Data:** Novembro 2025  
**Compatibilidade:** PHP 7.4+, MySQL 5.7+
