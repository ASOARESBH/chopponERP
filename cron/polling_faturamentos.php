<?php
/**
 * CRON Job - Polling Automático de Faturamentos
 * 
 * Executa a cada 1 hora para verificar status de boletos e faturas
 * 
 * INSTALAÇÃO NO CRON:
 * 
 * 1. Abra o crontab:
 *    crontab -e
 * 
 * 2. Adicione a linha:
 *    0 * * * * /usr/bin/php /caminho/para/cron/polling_faturamentos.php >> /var/log/polling_faturamentos.log 2>&1
 * 
 * 3. Salve e saia
 * 
 * ALTERNATIVA COM WGET:
 *    0 * * * * wget -q -O - https://seu-dominio.com.br/cron/polling_faturamentos.php?token=seu_token_secreto
 */

// Configurações
define('DEBUG_MODE', true);
define('POLLING_INTERVAL', 3600); // 1 hora em segundos

// Inicializar
$start_time = microtime(true);
$log_file = __DIR__ . '/../logs/polling_faturamentos.log';

// Função de log
function log_message($message, $level = 'INFO') {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] [{$level}] {$message}\n";
    
    // Criar arquivo de log se não existir
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    
    if (DEBUG_MODE) {
        echo $log_entry;
    }
}

try {
    log_message('=== INICIANDO POLLING AUTOMÁTICO ===');
    
    // Incluir configurações e classes
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../includes/RoyaltiesManagerV2.php';
    
    // Validar token se fornecido via GET (para segurança)
    if (isset($_GET['token'])) {
        $expected_token = defined('CRON_TOKEN') ? CRON_TOKEN : '';
        if (empty($expected_token) || $_GET['token'] !== $expected_token) {
            log_message('Acesso negado: token inválido', 'ERROR');
            http_response_code(403);
            die('Acesso negado');
        }
    }
    
    // Conectar ao banco de dados
    $conn = getDBConnection();
    log_message('Conectado ao banco de dados');
    
    // Inicializar gerenciador de royalties
    $royaltiesManager = new RoyaltiesManagerV2($conn);
    log_message('RoyaltiesManagerV2 inicializado');
    
    // Executar polling automático
    $resultado = $royaltiesManager->processarPollingAutomatico();
    
    if ($resultado['success']) {
        log_message(
            "Polling concluído com sucesso: {$resultado['verificados']} verificados, {$resultado['atualizados']} atualizados",
            'SUCCESS'
        );
    } else {
        log_message('Erro no polling: ' . $resultado['error'], 'ERROR');
    }
    
    // Calcular tempo de execução
    $execution_time = round((microtime(true) - $start_time) * 1000, 2);
    log_message("Tempo de execução: {$execution_time}ms");
    log_message('=== POLLING AUTOMÁTICO CONCLUÍDO ===');
    
    // Retornar JSON se for requisição AJAX
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $resultado['success'],
            'verificados' => $resultado['verificados'] ?? 0,
            'atualizados' => $resultado['atualizados'] ?? 0,
            'tempo_execucao_ms' => $execution_time,
            'message' => $resultado['error'] ?? 'Polling concluído com sucesso'
        ]);
    }
    
} catch (Exception $e) {
    log_message('Exceção: ' . $e->getMessage(), 'FATAL');
    log_message('Stack trace: ' . $e->getTraceAsString(), 'DEBUG');
    
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
?>
