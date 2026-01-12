# Integração Cora e Stripe - Pacote Completo

## 📦 Conteúdo do Pacote

Este pacote contém todos os arquivos necessários para integrar Cora (emissão de boletos) e Stripe (faturas) ao sistema de royalties.

### Estrutura de Diretórios

```
/
├── includes/
│   ├── cora_api_v2.php              # API Cora OAuth 2.0
│   └── RoyaltiesManagerV2.php       # Gerenciador de royalties
├── admin/
│   ├── financeiro_faturamento.php   # Página de faturamento
│   └── ajax/
│       └── gerar_boleto_link.php    # API para visualizar boletos
├── cron/
│   └── polling_faturamentos.php     # Script de polling automático
├── sql/
│   └── payment_gateway_config.sql   # Script de banco de dados
├── md/
│   ├── INTEGRACAO_APIS_CONFORMIDADE.md
│   ├── GUIA_INSTALACAO_INTEGRACAO.md
│   └── RESUMO_IMPLEMENTACAO.md
├── cora_config_v2.example.php       # Configuração Cora (exemplo)
├── IMPLEMENTACAO_COMPLETA.md        # Índice de arquivos
└── README.md                        # Este arquivo
```

## 🚀 Instruções de Instalação via FileZilla

### 1. Extrair o Arquivo ZIP

Extraia o arquivo ZIP em seu computador.

### 2. Abrir FileZilla

- Abra o FileZilla
- Conecte-se ao seu servidor FTP/SFTP

### 3. Copiar Arquivos

**Copie os arquivos para as seguintes localizações:**

#### Arquivos de Includes
```
includes/cora_api_v2.php              → /seu_dominio/includes/
includes/RoyaltiesManagerV2.php       → /seu_dominio/includes/
```

#### Arquivos de Admin
```
admin/financeiro_faturamento.php      → /seu_dominio/admin/
admin/ajax/gerar_boleto_link.php      → /seu_dominio/admin/ajax/
```

#### Arquivos de CRON
```
cron/polling_faturamentos.php         → /seu_dominio/cron/
```

#### Arquivo de Configuração
```
cora_config_v2.example.php            → /seu_dominio/
```

#### Arquivo de Banco de Dados
```
sql/payment_gateway_config.sql        → Salve em seu computador (usará via phpMyAdmin)
```

#### Documentação
```
md/INTEGRACAO_APIS_CONFORMIDADE.md    → /seu_dominio/md/
md/GUIA_INSTALACAO_INTEGRACAO.md      → /seu_dominio/md/
md/RESUMO_IMPLEMENTACAO.md            → /seu_dominio/md/
```

### 4. Configurar Permissões (via SSH ou cPanel)

```bash
# Restringir permissões do arquivo de configuração
chmod 600 /seu_dominio/cora_config_v2.php

# Garantir permissão de escrita para logs
chmod 755 /seu_dominio/logs
```

## 📋 Próximas Etapas

### 1. Instalar Banco de Dados

1. Acesse phpMyAdmin
2. Selecione seu banco de dados
3. Vá em "Importar"
4. Selecione o arquivo `sql/payment_gateway_config.sql`
5. Clique em "Executar"

### 2. Configurar Credenciais Cora

1. Renomeie `cora_config_v2.example.php` para `cora_config_v2.php`
2. Edite o arquivo com suas credenciais:
   - Obtenha em: Conta Cora > Integrações via APIs
   - Preencha: `CORA_CLIENT_ID` e `CORA_CLIENT_SECRET`

### 3. Configurar Credenciais Stripe

1. Insira as credenciais no banco de dados:
   ```sql
   INSERT INTO payment_gateway_config (
       estabelecimento_id,
       gateway_type,
       environment,
       ativo,
       config_data
   ) VALUES (
       1,
       'stripe',
       'test',
       1,
       JSON_OBJECT(
           'secret_key', 'sk_test_...',
           'webhook_secret', 'whsec_...',
           'environment', 'test'
       )
   );
   ```

### 4. Agendar CRON

**Via cPanel:**
1. Vá em "Cron Jobs"
2. Clique em "Adicionar Cron Job"
3. Configure:
   - Minuto: `0`
   - Hora: `*` (a cada hora)
   - Comando: `/usr/bin/php /seu_dominio/cron/polling_faturamentos.php`

**Via SSH:**
```bash
crontab -e
# Adicione: 0 * * * * /usr/bin/php /seu_dominio/cron/polling_faturamentos.php
```

## 📖 Documentação

Leia os arquivos de documentação para entender melhor:

- **INTEGRACAO_APIS_CONFORMIDADE.md** - Documentação técnica completa
- **GUIA_INSTALACAO_INTEGRACAO.md** - Guia detalhado de instalação
- **RESUMO_IMPLEMENTACAO.md** - Resumo executivo
- **IMPLEMENTACAO_COMPLETA.md** - Índice de arquivos

## ✅ Verificação de Instalação

Após instalar, verifique:

1. **Banco de Dados**
   ```sql
   SHOW TABLES LIKE 'payment_gateway%';
   SHOW TABLES LIKE 'faturamentos%';
   ```

2. **Arquivos**
   - Verifique se todos os arquivos foram copiados
   - Verifique permissões (especialmente `cora_config_v2.php`)

3. **Configuração**
   - Verifique se credenciais foram inseridas
   - Verifique se CRON foi agendado

4. **Testes**
   - Acesse `/admin/financeiro_faturamento.php`
   - Tente criar um royalty com boleto Cora
   - Tente criar um royalty com fatura Stripe

## 🔒 Segurança

**IMPORTANTE:**

- Nunca commite `cora_config_v2.php` com credenciais reais no Git
- Adicione ao `.gitignore`: `cora_config_v2.php`
- Use HTTPS em produção
- Mantenha `cora_config_v2.php` com permissões 600 (somente leitura para proprietário)
- Nunca exponha `client_secret` ou `secret_key` no frontend

## 📞 Suporte

Para dúvidas:

- **Documentação Cora**: https://developers.cora.com.br
- **Documentação Stripe**: https://stripe.com/docs
- **Documentação Sistema**: Veja os arquivos em `/md/`

## 🔄 Atualizações

Para atualizar no futuro:

1. Extraia a nova versão do ZIP
2. Copie apenas os arquivos que mudaram
3. Mantenha `cora_config_v2.php` intacto (com suas credenciais)
4. Teste em ambiente de staging antes de produção

---

**Versão**: 1.0  
**Data**: 2025-12-04  
**Status**: Pronto para Produção
