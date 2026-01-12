# 🍺 Chopp On Tap - Sistema de Gestão v3.0

## 🎉 Novidades da Versão 3.0

### 🤖 Integração Telegram Bot
- ✅ Notificações de vendas em tempo real
- ✅ Alertas de volume crítico
- ✅ Alertas de vencimento (10 dias, 2 dias, vencido)
- ✅ Configuração por estabelecimento
- ✅ Histórico completo de alertas

### 🏪 Multi-Estabelecimento Aprimorado
- ✅ Cada estabelecimento cria suas próprias bebidas
- ✅ Usuários vinculados a estabelecimentos específicos
- ✅ Admin Geral vê tudo, usuários veem apenas seu estabelecimento
- ✅ Controle granular de permissões

### 🚰 Controle de TAPs
- ✅ Apenas Admin Geral pode cadastrar TAPs
- ✅ TAPs aparecem para o estabelecimento vinculado
- ✅ Alertas automáticos de volume e vencimento

---

## 📋 Funcionalidades Principais

### Gestão de Estabelecimentos
- Cadastro de múltiplas choperias
- Vinculação de usuários
- Configuração individual de Telegram

### Gestão de Bebidas
- Cadastro por estabelecimento
- Upload de imagens
- Controle de preços e promoções
- IBU, teor alcoólico, marca e tipo

### Gestão de TAPs
- Cadastro de torneiras automáticas
- Controle de volume e vencimento
- Integração com leitora SumUp
- Pairing code para Bluetooth
- Alertas automáticos

### Gestão de Vendas
- Pagamento via PIX, Crédito e Débito
- Integração com SumUp
- Webhook para atualização de status
- Notificação Telegram automática
- Relatórios completos

### Gestão de Usuários
- 4 níveis de acesso:
  - **Admin Geral (1):** Acesso total
  - **Gerente (2):** Gestão do estabelecimento
  - **Operador (3):** Operações básicas
  - **Visualizador (4):** Apenas visualização

### Sistema de Logs
- 8 tipos de logs diferentes
- Visualizador integrado
- Rotação automática
- Download e limpeza

### Telegram Bot
- Configuração simples via painel
- Teste de conexão e envio
- Controle de notificações
- Histórico de alertas

---

## 🚀 Instalação

### Requisitos

- **Servidor:** Apache com PHP 7.4+
- **Banco de Dados:** MySQL 5.7+
- **Extensões PHP:** PDO, PDO_MySQL, cURL, JSON, GD
- **Telegram:** Bot criado no @BotFather (opcional)

### Passo a Passo

1. **Upload dos Arquivos**
   ```bash
   # Fazer upload de todos os arquivos para public_html
   ```

2. **Importar Banco de Dados**
   - Acesse phpMyAdmin
   - Selecione o banco `inlaud99_choppontap`
   - Importe o arquivo `database.sql`

3. **Configurar Permissões**
   ```bash
   chmod 755 logs/
   chmod 755 uploads/
   chmod 755 uploads/bebidas/
   chmod 755 cron/
   ```

4. **Configurar Cron Jobs** (Opcional - para Telegram)
   
   **Volume Crítico (a cada 5 minutos):**
   ```
   */5 * * * * php /home/inlaud99/public_html/cron/check_volume_critico.php
   ```
   
   **Vencimento (1x por dia às 8h):**
   ```
   0 8 * * * php /home/inlaud99/public_html/cron/check_vencimento.php
   ```

5. **Acessar o Sistema**
   ```
   URL: https://ochoppoficial.com.br/
   Email: choppon24h@gmail.com
   Senha: Admin259087@
   ```

6. **Configurar Telegram** (Opcional)
   - Acesse **Admin → Telegram**
   - Siga as instruções em `TELEGRAM_INTEGRATION.md`

---

## 📁 Estrutura de Arquivos

```
choppon_new/
├── admin/                  # Painel administrativo
│   ├── dashboard.php       # Dashboard principal
│   ├── bebidas.php         # Gestão de bebidas
│   ├── taps.php            # Gestão de TAPs
│   ├── pagamentos.php      # Gestão de pagamentos
│   ├── pedidos.php         # Relatório de pedidos
│   ├── usuarios.php        # Gestão de usuários
│   ├── estabelecimentos.php # Gestão de estabelecimentos
│   ├── telegram.php        # Configuração Telegram (NOVO)
│   ├── logs.php            # Visualizador de logs
│   └── logout.php          # Logout
├── api/                    # API REST
│   ├── login.php           # Autenticação
│   ├── verify_tap.php      # Verificar TAP
│   ├── create_order.php    # Criar pedido
│   ├── verify_checkout.php # Verificar checkout
│   ├── cancel_order.php    # Cancelar pedido
│   ├── webhook.php         # Webhook SumUp (ATUALIZADO)
│   └── ...
├── assets/                 # Assets estáticos
│   ├── css/
│   ├── js/
│   └── images/
├── cron/                   # Cron jobs (NOVO)
│   ├── check_volume_critico.php
│   └── check_vencimento.php
├── includes/               # Bibliotecas PHP
│   ├── config.php          # Configurações
│   ├── auth.php            # Autenticação
│   ├── logger.php          # Sistema de logs
│   ├── sumup.php           # Integração SumUp
│   ├── telegram.php        # Integração Telegram (NOVO)
│   ├── header.php          # Header HTML
│   └── footer.php          # Footer HTML
├── logs/                   # Logs do sistema
├── uploads/                # Uploads
│   └── bebidas/            # Imagens de bebidas
├── database.sql            # Script SQL (ATUALIZADO)
├── index.php               # Página de login
├── README.md               # Este arquivo
├── TELEGRAM_INTEGRATION.md # Documentação Telegram (NOVO)
└── API_DOCUMENTATION.md    # Documentação da API
```

---

## 🔐 Credenciais Padrão

### Sistema
- **Email:** choppon24h@gmail.com
- **Senha:** Admin259087@
- **Tipo:** Admin Geral (1)

### Banco de Dados
- **Host:** localhost
- **Banco:** inlaud99_choppontap
- **Usuário:** inlaud99_admin
- **Senha:** Admin259087@

### SumUp
- **Token:** sup_sk_8vNpSEJPVudqJrWPdUlomuE3EfVofw1bL
- **Webhook:** https://ochoppoficial.com.br/api/webhook.php

---

## 🎯 Fluxo de Uso

### 1. Cliente no App Android
1. Abre o app e conecta via Bluetooth
2. Seleciona volume desejado (100ml, 300ml, 500ml, 700ml)
3. Escolhe método de pagamento (PIX, Crédito, Débito)
4. Aguarda aprovação do pagamento
5. Chopp é liberado automaticamente

### 2. Sistema Web
1. Recebe webhook da SumUp
2. Atualiza status do pedido
3. **Envia notificação Telegram** (NOVO)
4. Libera chopp via API
5. Atualiza volume consumido

### 3. Alertas Automáticos (NOVO)
1. **Cron de Volume:** Verifica a cada 5 minutos
2. **Cron de Vencimento:** Verifica diariamente às 8h
3. Envia alertas via Telegram
4. Registra no histórico

---

## 📊 Níveis de Acesso

| Nível | Nome | Permissões |
|-------|------|------------|
| **1** | Admin Geral | Tudo + Cadastrar TAPs + Ver todos estabelecimentos |
| **2** | Gerente | Gestão completa do seu estabelecimento |
| **3** | Operador | Criar bebidas, ver relatórios |
| **4** | Visualizador | Apenas visualização |

---

## 🤖 Telegram Bot

### Configuração Rápida

1. Criar bot no @BotFather
2. Obter Chat ID do grupo
3. Configurar no painel
4. Testar conexão
5. Ativar notificações

### Tipos de Notificações

- **💰 Vendas:** Imediato via webhook
- **⚠️ Volume Crítico:** Via cron (5 em 5 min)
- **📅 Vencimento:** Via cron (1x por dia)

**Documentação completa:** `TELEGRAM_INTEGRATION.md`

---

## 🔧 Manutenção

### Logs

Acesse **Admin → Logs** para visualizar:
- `auth.log` - Tentativas de login
- `api.log` - Requisições da API
- `webhook.log` - Webhooks recebidos
- `telegram.log` - Mensagens Telegram (NOVO)
- `cron.log` - Execução de cron jobs (NOVO)
- `errors.log` - Erros PHP
- `debug.log` - Debug geral
- `security.log` - Eventos de segurança

### Backup

**Banco de Dados:**
```bash
mysqldump -u inlaud99_admin -p inlaud99_choppontap > backup.sql
```

**Arquivos:**
```bash
tar -czf backup_files.tar.gz uploads/ logs/
```

### Atualização

1. Fazer backup completo
2. Substituir arquivos (exceto `includes/config.php`)
3. Executar migrations se houver
4. Testar funcionalidades

---

## 🐛 Troubleshooting

### Sistema não carrega CSS
- Verificar `SITE_URL` em `includes/config.php`
- Verificar permissões da pasta `assets/`
- Limpar cache do navegador

### Login não funciona
- Verificar hash da senha no banco
- Executar `update_password.php`
- Verificar logs em `logs/auth.log`

### Telegram não envia
- Testar conexão no painel
- Verificar bot adicionado ao grupo
- Verificar cron jobs configurados
- Ver logs em `logs/telegram.log`

### Webhook não atualiza
- Verificar URL configurada na SumUp
- Verificar logs em `logs/webhook.log`
- Testar endpoint manualmente

---

## 📞 Suporte

**Documentação:**
- `README.md` - Este arquivo
- `TELEGRAM_INTEGRATION.md` - Integração Telegram
- `API_DOCUMENTATION.md` - Documentação da API
- `INSTALACAO_RAPIDA.md` - Guia de instalação
- `SOLUCAO_PROBLEMAS.md` - Troubleshooting

**Logs:**
- Acesse **Admin → Logs** para diagnóstico
- Todos os eventos são registrados

---

## 📝 Changelog

### v3.0 (25/11/2025)
- ✅ Integração completa com Telegram Bot
- ✅ Notificações de vendas em tempo real
- ✅ Alertas de volume crítico
- ✅ Alertas de vencimento (10d, 2d, vencido)
- ✅ Cron jobs para verificações automáticas
- ✅ Painel de configuração Telegram
- ✅ Histórico de alertas
- ✅ Controle de TAPs apenas para Admin
- ✅ Melhorias no multi-estabelecimento

### v2.0.3 (25/11/2025)
- ✅ Detecção automática de URL
- ✅ Sistema completo de logs
- ✅ Correção de autenticação

### v2.0 (25/11/2025)
- ✅ Migração de Laravel para PHP procedural
- ✅ Sistema multi-estabelecimento
- ✅ Integração SumUp mantida
- ✅ API REST completa

---

## 🎉 Conclusão

O **Chopp On Tap v3.0** é um sistema completo de gestão de choperias autônomas com:
- ✅ Multi-estabelecimento
- ✅ Integração SumUp
- ✅ Notificações Telegram
- ✅ Alertas automáticos
- ✅ API REST
- ✅ Logs completos
- ✅ Interface responsiva

**Pronto para produção!** 🍺

---

**Versão:** 3.0  
**Data:** 25/11/2025  
**Status:** ✅ Completo e Testado
