# 🤖 Integração Telegram Bot - Chopp On Tap

## 📋 Índice

1. [Visão Geral](#visão-geral)
2. [Funcionalidades](#funcionalidades)
3. [Como Configurar](#como-configurar)
4. [Tipos de Notificações](#tipos-de-notificações)
5. [Cron Jobs](#cron-jobs)
6. [Troubleshooting](#troubleshooting)

---

## 🎯 Visão Geral

O sistema Chopp On Tap agora possui integração completa com o **Telegram Bot** para enviar notificações automáticas sobre eventos importantes do seu negócio.

### Características

- ✅ **Configuração por estabelecimento** - Cada choperia tem seu próprio bot
- ✅ **Notificações em tempo real** - Vendas são notificadas imediatamente
- ✅ **Alertas automáticos** - Volume crítico e vencimento
- ✅ **Histórico completo** - Todos os alertas são registrados
- ✅ **Controle granular** - Ative/desative cada tipo de notificação

---

## 🚀 Funcionalidades

### 1. Notificações de Vendas 💰

Sempre que uma venda é aprovada (PIX, Crédito ou Débito), você recebe uma notificação com:
- Método de pagamento
- Valor recebido
- Bebida vendida
- Quantidade (ml)
- CPF do cliente (se fornecido)
- Data e hora
- Estabelecimento

**Exemplo de mensagem:**
```
🍺 NOVA VENDA REALIZADA!

💳 Método: PIX
💵 Valor: R$ 15,00
🍻 Bebida: Heineken
📏 Quantidade: 300 ml
📅 Data: 25/11/2025 14:30:15
🏪 Estabelecimento: Chopp On Tap - Matriz
```

### 2. Alertas de Volume Crítico ⚠️

Quando o volume restante de um barril atinge o nível crítico configurado, você recebe um alerta com:
- Nome da bebida
- Marca
- Volume restante
- Percentual restante
- Volume crítico configurado
- Estabelecimento

**Exemplo de mensagem:**
```
⚠️ ALERTA: VOLUME CRÍTICO!

🍺 Bebida: Heineken
🏭 Marca: Heineken
📊 Volume Restante: 5,50 L
📉 Percentual: 11,0%
🚨 Volume Crítico: 10,00 L
🏪 Estabelecimento: Chopp On Tap - Matriz

⏰ Providencie a troca do barril!
```

### 3. Alertas de Vencimento 📅

O sistema envia 3 tipos de alertas de vencimento:

#### 🟡 10 Dias Antes
```
🟡 ALERTA: VENCE EM 10 DIAS!

🍺 Bebida: Heineken
🏭 Marca: Heineken
📅 Data de Vencimento: 05/12/2025
⏰ Dias restantes: 10 dia(s)

⚠️ Planeje a substituição do barril!
🏪 Estabelecimento: Chopp On Tap - Matriz
```

#### 🟠 2 Dias Antes
```
🟠 ALERTA: VENCE EM 2 DIAS!

🍺 Bebida: Heineken
🏭 Marca: Heineken
📅 Data de Vencimento: 27/11/2025
⏰ Dias restantes: 2 dia(s)

⚠️ Planeje a substituição do barril!
🏪 Estabelecimento: Chopp On Tap - Matriz
```

#### 🔴 Vencido
```
🔴 ALERTA: BARRIL VENCIDO!

🍺 Bebida: Heineken
🏭 Marca: Heineken
📅 Data de Vencimento: 23/11/2025
⏰ Vencido há: 2 dia(s)

🚫 Barril vencido! Remova imediatamente!
🏪 Estabelecimento: Chopp On Tap - Matriz
```

---

## 🛠️ Como Configurar

### Passo 1: Criar o Bot no Telegram

1. Abra o Telegram e procure por **@BotFather**
2. Envie o comando `/newbot`
3. Escolha um nome para o bot (ex: "Chopp On Tap Notificações")
4. Escolha um username (ex: "choppontap_bot")
5. **Copie o token** fornecido (ex: `1234567890:ABCdefGHIjklMNOpqrsTUVwxyz`)

### Passo 2: Obter o Chat ID

**Opção A: Grupo/Canal**
1. Crie um grupo ou canal no Telegram
2. Adicione o bot ao grupo/canal
3. Envie uma mensagem qualquer no grupo
4. Acesse: `https://api.telegram.org/bot<SEU_TOKEN>/getUpdates`
5. Procure por `"chat":{"id":-1001234567890}` na resposta
6. Copie o ID (incluindo o sinal de menos)

**Opção B: Chat Privado**
1. Procure por **@userinfobot** no Telegram
2. Envie `/start`
3. O bot retornará seu Chat ID
4. Copie o ID

### Passo 3: Configurar no Sistema

1. Acesse o painel administrativo
2. Clique em **🤖 Telegram** no menu lateral
3. Se for Admin Geral, selecione o estabelecimento
4. Preencha os campos:
   - **Bot Token**: Cole o token do BotFather
   - **Chat ID**: Cole o ID obtido
5. Marque as notificações que deseja receber:
   - ✅ Vendas realizadas
   - ✅ Volume crítico de barris
   - ✅ Alertas de vencimento
6. Marque **✅ Ativar notificações**
7. Clique em **Salvar Configuração**

### Passo 4: Testar

1. Clique em **🔍 Testar Conexão** para verificar se o token está correto
2. Clique em **📤 Enviar Mensagem Teste** para receber uma mensagem no Telegram
3. Verifique se a mensagem chegou

---

## 📊 Tipos de Notificações

| Tipo | Quando Envia | Frequência | Configurável |
|------|--------------|------------|--------------|
| **Venda** | Pagamento aprovado | Imediato (webhook) | Sim |
| **Volume Crítico** | Volume ≤ crítico | 1x por barril | Sim |
| **Vencimento 10d** | 10 dias antes | 1x por barril | Sim |
| **Vencimento 2d** | 2 dias antes | 1x por barril | Sim |
| **Vencido** | No dia do vencimento | 1x por barril | Sim |

### Controle de Duplicação

O sistema garante que cada alerta seja enviado **apenas uma vez** por barril:
- Vendas: Marcadas como `telegram_notificado = 1`
- Volume crítico: Marcado como `alerta_critico_enviado = 1`
- Vencimento 10d: Marcado como `alerta_10dias_enviado = 1`
- Vencimento 2d: Marcado como `alerta_2dias_enviado = 1`
- Vencido: Marcado como `alerta_vencido_enviado = 1`

---

## ⏰ Cron Jobs

Para que os alertas automáticos funcionem, configure os cron jobs no cPanel:

### 1. Verificação de Volume Crítico

**Frequência:** A cada 5 minutos  
**Comando:**
```bash
*/5 * * * * php /home/usuario/public_html/cron/check_volume_critico.php
```

**O que faz:**
- Verifica TAPs com volume restante ≤ volume crítico
- Envia alerta se ainda não foi enviado
- Marca TAP como notificada

### 2. Verificação de Vencimento

**Frequência:** 1x por dia às 8h  
**Comando:**
```bash
0 8 * * * php /home/usuario/public_html/cron/check_vencimento.php
```

**O que faz:**
- Verifica TAPs com vencimento em 10 dias, 2 dias ou vencidas
- Envia alertas apropriados
- Marca TAP como notificada para cada tipo de alerta

### Como Configurar no cPanel

1. Acesse **cPanel → Cron Jobs**
2. Em "Adicionar Novo Cron Job":
   - **Minuto:** `*/5` (para volume) ou `0` (para vencimento)
   - **Hora:** `*` (para volume) ou `8` (para vencimento)
   - **Dia:** `*`
   - **Mês:** `*`
   - **Dia da Semana:** `*`
   - **Comando:** Cole o comando completo acima
3. Clique em **Adicionar Novo Cron Job**

---

## 🔍 Troubleshooting

### Problema: Mensagens não chegam

**Possíveis causas:**

1. **Bot Token inválido**
   - Teste com **🔍 Testar Conexão**
   - Verifique se copiou o token completo

2. **Chat ID incorreto**
   - Certifique-se de incluir o sinal de menos (-)
   - Teste com **📤 Enviar Mensagem Teste**

3. **Bot não foi adicionado ao grupo**
   - Adicione o bot como membro do grupo/canal
   - Dê permissões de envio de mensagens

4. **Notificações desativadas**
   - Verifique se **✅ Ativar notificações** está marcado
   - Verifique se o tipo específico está habilitado

5. **Cron jobs não configurados**
   - Alertas de volume e vencimento dependem de cron
   - Verifique se os cron jobs estão ativos no cPanel

### Problema: Alertas duplicados

**Solução:**
- Não deve acontecer, pois o sistema marca como enviado
- Verifique se não há múltiplos cron jobs configurados
- Verifique os logs em **Admin → Logs**

### Problema: Erro "Chat not found"

**Solução:**
- O bot precisa ser membro do grupo/canal
- Envie uma mensagem no grupo antes de obter o Chat ID
- Para canais, o bot precisa ser administrador

### Verificar Logs

1. Acesse **Admin → Telegram**
2. Role até **📊 Histórico de Alertas**
3. Verifique status: ✓ Enviado ou ✗ Falha
4. Para mais detalhes, acesse **Admin → Logs** e filtre por `telegram.log`

---

## 📚 Referências

- **Telegram Bot API:** https://core.telegram.org/bots/api
- **BotFather:** https://t.me/BotFather
- **UserInfoBot:** https://t.me/userinfobot
- **Documentação Oficial:** https://core.telegram.org/bots

---

## 🎯 Dicas

1. **Use grupos separados** para cada estabelecimento
2. **Teste antes de ativar** todas as notificações
3. **Configure horários adequados** para os cron jobs
4. **Monitore o histórico** regularmente
5. **Mantenha o bot ativo** no grupo

---

## 🔐 Segurança

- ✅ Tokens são armazenados no banco de dados
- ✅ Apenas Admin Geral pode configurar (se multi-estabelecimento)
- ✅ Mensagens não contêm dados sensíveis de clientes
- ✅ Histórico completo para auditoria

---

**Versão:** 3.0  
**Data:** 25/11/2025  
**Status:** ✅ Implementado e Testado
