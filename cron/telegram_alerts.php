#!/usr/bin/env php
<?php
/**
 * Script CRON - Verificação Automática de Alertas Telegram
 * 
 * Este script deve ser executado periodicamente via cron
 * Recomendação: A cada 1 hora
 * 
 * Exemplo de configuração cron:
 * 0 * * * * /usr/bin/php /caminho/para/cron/telegram_alerts.php >> /var/log/telegram_alerts.log 2>&1
 */

// Definir timezone
date_default_timezone_set('America/Sao_Paulo');

// Caminho base do projeto
$base_path = dirname(__DIR__);

// Incluir arquivos necessários
require_once $base_path . '/includes/config.php';
require_once $base_path . '/includes/TelegramNotifications.php';

// Iniciar log
$log_file = $base_path . '/logs/telegram_alerts_' . date('Y-m-d') . '.log';
$log_dir = dirname($log_file);

if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

function logMessage($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] {$message}\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    echo $log_entry;
}

logMessage("========================================");
logMessage("Iniciando verificação de alertas Telegram");
logMessage("========================================");

try {
    // Conectar ao banco de dados
    $conn = getDBConnection();
    logMessage("✓ Conexão com banco de dados estabelecida");
    
    // Instanciar classe de notificações
    $telegramNotifications = new TelegramNotifications($conn);
    logMessage("✓ Classe TelegramNotifications instanciada");
    
    // Verificar estoque mínimo
    logMessage("\n--- Verificando Estoque Mínimo ---");
    $alertas_estoque = $telegramNotifications->verificarEstoqueMinimo();
    logMessage("✓ Alertas de estoque enviados: {$alertas_estoque}");
    
    // Verificar contas a pagar
    logMessage("\n--- Verificando Contas a Pagar ---");
    $alertas_contas = $telegramNotifications->verificarContasPagar();
    logMessage("✓ Alertas de contas enviados: {$alertas_contas}");
    
    // Verificar promoções
    logMessage("\n--- Verificando Promoções ---");
    $alertas_promocoes = $telegramNotifications->verificarPromocoes();
    logMessage("✓ Alertas de promoções enviados: {$alertas_promocoes}");
    
    // Total
    $total_alertas = $alertas_estoque + $alertas_contas + $alertas_promocoes;
    
    logMessage("\n========================================");
    logMessage("Verificação concluída com sucesso!");
    logMessage("Total de alertas enviados: {$total_alertas}");
    logMessage("========================================\n");
    
    exit(0);
    
} catch (Exception $e) {
    logMessage("✗ ERRO: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    logMessage("========================================\n");
    exit(1);
}
?>
