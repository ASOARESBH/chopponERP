# 📱 Sistema de Alertas Automáticos via Telegram

**Versão**: 2.0  
**Data**: 2025-12-05

---

## 1. Visão Geral

Sistema completo de notificações automáticas via Telegram para alertar sobre eventos críticos do sistema:

- 📦 **Estoque Mínimo Atingido**
- 💳 **Contas a Pagar Vencendo**
- 🎉 **Promoções Expirando**
- 💰 Vendas realizadas (já existente)
- ⚠️ Volume crítico de barris (já existente)

---

## 2. Instalação

### Passo 1: Aplicar Migration no Banco de Dados

Execute o arquivo SQL para adicionar os novos campos e tabelas:

```bash
mysql -u seu_usuario -p seu_banco < sql/add_telegram_alerts.sql
```

Ou importe via phpMyAdmin.

### Passo 2: Configurar o Cron Job

Adicione a seguinte linha ao crontab para executar a cada hora:

```bash
crontab -e
```

Adicione:

```
0 * * * * /usr/bin/php /caminho/completo/cron/telegram_alerts.php >> /var/log/telegram_alerts.log 2>&1
```

**Recomendações de frequência:**
- **A cada 1 hora**: Ideal para a maioria dos casos
- **A cada 30 minutos**: Para operações críticas
- **A cada 6 horas**: Para ambientes de baixo volume

### Passo 3: Configurar no Painel Administrativo

1. Acesse **Integrações > Telegram**
2. Preencha:
   - **Bot Token** (obtenha em @BotFather)
   - **Chat ID** (obtenha em @userinfobot)
3. Marque os alertas desejados:
   - ✅ Estoque mínimo atingido
   - ✅ Contas a pagar vencendo
   - ✅ Promoções expirando
4. Configure:
   - **Dias antes do vencimento**: Quantos dias antes alertar (padrão: 3)
   - **Dias após vencimento**: Quantos dias após alertar (padrão: 2)
5. Clique em **Salvar Configuração**

---

## 3. Funcionalidades

### 3.1. Alerta de Estoque Mínimo

**Quando é enviado:**
- Quando o estoque atual de um produto ≤ estoque mínimo
- Apenas uma vez por dia por produto

**Exemplo de mensagem:**

```
⚠️ ALERTA DE ESTOQUE

📍 Estabelecimento: CHOPP ON - JABOTICATUBAS
📦 Produto: Barril Heineken 30L
🔢 Código: BAR-HEI-30
📊 Estoque Atual: 2 unidades
⚡ Estoque Mínimo: 5 unidades
📈 Repor: 3 unidades

⏰ 05/12/2025 14:30
```

### 3.2. Alerta de Contas a Pagar

**Quando é enviado:**
- X dias antes do vencimento (configurável)
- Até Y dias após o vencimento (configurável)
- Apenas uma vez por dia por conta

**Exemplo de mensagem:**

```
🔴 ALERTA DE CONTA A PAGAR

📍 Estabelecimento: CHOPP ON - JABOTICATUBAS
📄 Descrição: Fornecimento de Barris
🏢 Fornecedor: Cervejaria ABC
💰 Valor: R$ 5.420,00
📅 Vencimento: 07/12/2025
⚠️ Vence em 2 dias

⏰ 05/12/2025 14:30
```

### 3.3. Alerta de Promoções Expirando

**Quando é enviado:**
- X dias antes da data fim (configurável)
- Apenas uma vez por dia por promoção

**Exemplo de mensagem:**

```
🟡 ALERTA DE PROMOÇÃO

📍 Estabelecimento: CHOPP ON - JABOTICATUBAS
🎉 Promoção: Black Friday Chopp
📝 Descrição: 50% de desconto em todos os barris
💸 Desconto: 50%
📅 Data Fim: 08/12/2025
⚠️ Expira em 3 dias

⏰ 05/12/2025 14:30
```

---

## 4. Tabelas do Banco de Dados

### 4.1. telegram_config (atualizada)

Novos campos adicionados:

| Campo | Tipo | Descrição |
|:---|:---|:---|
| `notificar_estoque_minimo` | TINYINT(1) | Ativar alertas de estoque |
| `notificar_contas_pagar` | TINYINT(1) | Ativar alertas de contas |
| `notificar_promocoes` | TINYINT(1) | Ativar alertas de promoções |
| `dias_antes_vencimento` | INT | Dias antes para alertar |
| `dias_apos_vencimento` | INT | Dias após para alertar |

### 4.2. telegram_notifications_log (nova)

Registra todas as notificações enviadas:

| Campo | Descrição |
|:---|:---|
| `id` | ID único |
| `estabelecimento_id` | Estabelecimento relacionado |
| `tipo` | Tipo de alerta |
| `referencia_id` | ID do produto/conta/promoção |
| `mensagem` | Mensagem enviada |
| `status` | enviado / erro / pendente |
| `enviado_em` | Data/hora do envio |

### 4.3. telegram_alerts_sent (nova)

Controla alertas já enviados (evita duplicatas):

| Campo | Descrição |
|:---|:---|
| `estabelecimento_id` | Estabelecimento |
| `tipo` | Tipo de alerta |
| `referencia_id` | ID do registro |
| `data_envio` | Data do envio |

---

## 5. Classe TelegramNotifications

### Métodos Principais

```php
// Verificar estoque mínimo
$alertas = $telegram->verificarEstoqueMinimo();

// Verificar contas a pagar
$alertas = $telegram->verificarContasPagar();

// Verificar promoções
$alertas = $telegram->verificarPromocoes();

// Executar todas as verificações
$total = $telegram->executarTodasVerificacoes();

// Obter estatísticas
$stats = $telegram->obterEstatisticas($estabelecimentoId, $dias = 7);
```

---

## 6. Logs

### Localização

Os logs são salvos em:

```
/caminho/do/projeto/logs/telegram_alerts_YYYY-MM-DD.log
```

### Exemplo de Log

```
[2025-12-05 14:30:00] ========================================
[2025-12-05 14:30:00] Iniciando verificação de alertas Telegram
[2025-12-05 14:30:00] ========================================
[2025-12-05 14:30:01] ✓ Conexão com banco de dados estabelecida
[2025-12-05 14:30:01] ✓ Classe TelegramNotifications instanciada
[2025-12-05 14:30:01] 
--- Verificando Estoque Mínimo ---
[2025-12-05 14:30:02] ✓ Alertas de estoque enviados: 3
[2025-12-05 14:30:02] 
--- Verificando Contas a Pagar ---
[2025-12-05 14:30:03] ✓ Alertas de contas enviados: 2
[2025-12-05 14:30:03] 
--- Verificando Promoções ---
[2025-12-05 14:30:04] ✓ Alertas de promoções enviados: 1
[2025-12-05 14:30:04] 
========================================
[2025-12-05 14:30:04] Verificação concluída com sucesso!
[2025-12-05 14:30:04] Total de alertas enviados: 6
[2025-12-05 14:30:04] ========================================
```

---

## 7. Testes

### Testar Manualmente

Execute o script diretamente:

```bash
php /caminho/completo/cron/telegram_alerts.php
```

### Verificar Logs

```bash
tail -f /var/log/telegram_alerts.log
```

### Consultar Notificações Enviadas

```sql
SELECT * FROM telegram_notifications_log 
WHERE DATE(created_at) = CURDATE()
ORDER BY created_at DESC;
```

---

## 8. Solução de Problemas

### Alertas não estão sendo enviados

1. Verifique se o cron está rodando:
   ```bash
   crontab -l
   ```

2. Verifique os logs:
   ```bash
   tail -50 /var/log/telegram_alerts.log
   ```

3. Verifique se as notificações estão ativas no painel

4. Teste a conexão com o bot no painel Telegram

### Alertas duplicados

O sistema já possui controle de duplicatas. Cada alerta é enviado apenas **uma vez por dia** por item.

### Bot não responde

1. Verifique se o Bot Token está correto
2. Teste a conexão no painel administrativo
3. Verifique se o bot foi bloqueado no Telegram

---

## 9. Personalização

### Alterar Frequência do Cron

Edite o crontab:

```bash
# A cada 30 minutos
*/30 * * * * /usr/bin/php /caminho/cron/telegram_alerts.php

# A cada 6 horas
0 */6 * * * /usr/bin/php /caminho/cron/telegram_alerts.php

# Apenas em horário comercial (8h às 18h)
0 8-18 * * * /usr/bin/php /caminho/cron/telegram_alerts.php
```

### Customizar Mensagens

Edite os métodos em `includes/TelegramNotifications.php`:

- `montarMensagemEstoque()`
- `montarMensagemConta()`
- `montarMensagemPromocao()`

---

**Fim da Documentação**
