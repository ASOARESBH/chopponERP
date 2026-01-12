# Sistema de Logs de Debug - CHOPPONv1

## Localização dos Logs

Os logs são salvos automaticamente neste diretório com o seguinte padrão:

- **Logs de Debug**: `debug_YYYY-MM-DD.log` (ex: `debug_2024-12-14.log`)
- **Erros PHP**: `php_errors.log`

## Como Visualizar os Logs

### Via Terminal (SSH)

```bash
# Ver log do dia atual
tail -f /var/www/html/CHOPPONv1/logs/debug_$(date +%Y-%m-%d).log

# Ver últimas 100 linhas
tail -n 100 /var/www/html/CHOPPONv1/logs/debug_$(date +%Y-%m-%d).log

# Buscar por erro específico
grep "ERROR" /var/www/html/CHOPPONv1/logs/debug_$(date +%Y-%m-%d).log

# Ver erros PHP
tail -f /var/www/html/CHOPPONv1/logs/php_errors.log
```

### Via FTP/SFTP

1. Conecte-se ao servidor via FTP/SFTP
2. Navegue até `/var/www/html/CHOPPONv1/logs/`
3. Baixe o arquivo `debug_YYYY-MM-DD.log` do dia desejado
4. Abra com editor de texto

## Estrutura dos Logs

Cada entrada de log contém:

```
[TIMESTAMP] [NÍVEL] [ARQUIVO:LINHA] Mensagem
{
    "contexto": "dados adicionais em JSON"
}
--------------------------------------------------------------------------------
```

### Níveis de Log

- **INFO**: Informações gerais de fluxo
- **DEBUG**: Detalhes técnicos para debug
- **WARNING**: Avisos que não impedem execução
- **ERROR**: Erros que impedem funcionamento
- **SQL**: Queries SQL executadas
- **SQL_ERROR**: Erros em queries SQL
- **PHP_ERROR**: Erros do PHP
- **EXCEPTION**: Exceções capturadas
- **FATAL_ERROR**: Erros fatais que param execução

## Páginas com Log Ativo

✅ **mercadopago_config.php** - Configuração do Mercado Pago
✅ **royalty_selecionar_pagamento.php** - Seleção de método de pagamento

## Limpeza Automática

Logs com mais de 7 dias são automaticamente removidos.

Para alterar este período, edite `DebugLogger.php`:

```php
DebugLogger::cleanOldLogs(7); // Número de dias
```

## Desabilitar Logs

Para desabilitar temporariamente os logs (produção):

```php
DebugLogger::setEnabled(false);
```

## Exemplo de Análise

### Problema: Página em branco

1. Acesse o log do dia: `debug_2024-12-14.log`
2. Procure por `FATAL_ERROR` ou `EXCEPTION`
3. Verifique o arquivo e linha indicados
4. Analise o contexto JSON para detalhes

### Problema: Query SQL falhando

1. Procure por `SQL_ERROR` no log
2. Veja a query completa e parâmetros
3. Verifique a mensagem de erro do MySQL
4. Teste a query manualmente no phpMyAdmin

## Suporte

Se precisar de ajuda para interpretar os logs, envie:

1. O arquivo de log completo do dia
2. Descrição do problema
3. Passos para reproduzir o erro
