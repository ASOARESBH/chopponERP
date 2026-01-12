#!/usr/bin/env php
<?php
/**
 * Script CRON - Verificação Automática de Alertas por E-mail
 * 
 * Este script deve ser executado periodicamente via cron
 * Recomendação: A cada 1 hora
 * 
 * Exemplo de configuração cron:
 * 0 * * * * /usr/bin/php /caminho/para/cron/email_alerts.php >> /var/log/email_alerts.log 2>&1
 */

// Definir timezone
date_default_timezone_set('America/Sao_Paulo');

// Caminho base do projeto
$base_path = dirname(__DIR__);

// Incluir arquivos necessários
require_once $base_path . '/includes/config.php';
require_once $base_path . '/includes/EmailNotifications.php';

// Iniciar log
$log_file = $base_path . '/logs/email_alerts_' . date('Y-m-d') . '.log';
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
logMessage("Iniciando verificação de alertas por E-mail");
logMessage("========================================");

try {
    // Conectar ao banco de dados
    $conn = getDBConnection();
    logMessage("✓ Conexão com banco de dados estabelecida");
    
    // Instanciar classe de notificações
    $emailNotifications = new EmailNotifications($conn);
    logMessage("✓ Classe EmailNotifications instanciada");
    
    // Verificar estoque mínimo
    logMessage("\n--- Verificando Estoque Mínimo ---");
    $alertas_estoque = $emailNotifications->verificarEstoqueMinimo();
    logMessage("✓ Alertas de estoque enviados: {$alertas_estoque}");
    
    // Verificar contas a pagar
    logMessage("\n--- Verificando Contas a Pagar ---");
    $alertas_contas = $emailNotifications->verificarContasPagar();
    logMessage("✓ Alertas de contas enviados: {$alertas_contas}");
    
    // Verificar royalties
    logMessage("\n--- Verificando Royalties ---");
    $alertas_royalties = $emailNotifications->verificarRoyalties();
    logMessage("✓ Alertas de royalties enviados: {$alertas_royalties}");
    
    // Verificar promoções
    logMessage("\n--- Verificando Promoções ---");
    $alertas_promocoes = $emailNotifications->verificarPromocoes();
    logMessage("✓ Alertas de promoções enviados: {$alertas_promocoes}");
    
    // Total
    $total_alertas = $alertas_estoque + $alertas_contas + $alertas_royalties + $alertas_promocoes;
    
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
