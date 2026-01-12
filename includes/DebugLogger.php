<?php
/**
 * DebugLogger - Sistema de LOG centralizado para debug
 * 
 * Permite rastrear erros, queries SQL, e fluxo de execução
 * Logs são salvos em /logs/debug_YYYY-MM-DD.log
 */

class DebugLogger {
    private static $logDir = __DIR__ . '/../logs';
    private static $enabled = true;
    
    /**
     * Inicializa o sistema de log
     */
    public static function init() {
        // Criar diretório de logs se não existir
        if (!file_exists(self::$logDir)) {
            mkdir(self::$logDir, 0755, true);
        }
        
        // Configurar tratamento de erros PHP
        error_reporting(E_ALL);
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
        ini_set('error_log', self::$logDir . '/php_errors.log');
        
        // Registrar handler de erros
        set_error_handler([self::class, 'errorHandler']);
        set_exception_handler([self::class, 'exceptionHandler']);
        register_shutdown_function([self::class, 'shutdownHandler']);
    }
    
    /**
     * Log de informação geral
     */
    public static function info($message, $context = []) {
        self::log('INFO', $message, $context);
    }
    
    /**
     * Log de warning
     */
    public static function warning($message, $context = []) {
        self::log('WARNING', $message, $context);
    }
    
    /**
     * Log de erro
     */
    public static function error($message, $context = []) {
        self::log('ERROR', $message, $context);
    }
    
    /**
     * Log de query SQL
     */
    public static function sql($query, $params = [], $error = null) {
        $context = [
            'query' => $query,
            'params' => $params
        ];
        
        if ($error) {
            $context['error'] = $error;
            self::log('SQL_ERROR', 'Erro na query SQL', $context);
        } else {
            self::log('SQL', 'Query executada', $context);
        }
    }
    
    /**
     * Log de debug detalhado
     */
    public static function debug($message, $data = null) {
        $context = [];
        if ($data !== null) {
            $context['data'] = $data;
        }
        self::log('DEBUG', $message, $context);
    }
    
    /**
     * Método principal de log
     */
    private static function log($level, $message, $context = []) {
        if (!self::$enabled) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logFile = self::$logDir . '/debug_' . date('Y-m-d') . '.log';
        
        // Obter informações do caller
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = isset($backtrace[2]) ? $backtrace[2] : $backtrace[1];
        $file = isset($caller['file']) ? basename($caller['file']) : 'unknown';
        $line = isset($caller['line']) ? $caller['line'] : '?';
        
        // Formatar mensagem
        $logMessage = sprintf(
            "[%s] [%s] [%s:%s] %s",
            $timestamp,
            $level,
            $file,
            $line,
            $message
        );
        
        // Adicionar contexto se existir
        if (!empty($context)) {
            $logMessage .= "\n" . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        
        $logMessage .= "\n" . str_repeat('-', 80) . "\n";
        
        // Escrever no arquivo
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * Handler de erros PHP
     */
    public static function errorHandler($errno, $errstr, $errfile, $errline) {
        $errorTypes = [
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSE ERROR',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE ERROR',
            E_CORE_WARNING => 'CORE WARNING',
            E_COMPILE_ERROR => 'COMPILE ERROR',
            E_COMPILE_WARNING => 'COMPILE WARNING',
            E_USER_ERROR => 'USER ERROR',
            E_USER_WARNING => 'USER WARNING',
            E_USER_NOTICE => 'USER NOTICE',
            E_STRICT => 'STRICT',
            E_RECOVERABLE_ERROR => 'RECOVERABLE ERROR',
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER DEPRECATED'
        ];
        
        $errorType = isset($errorTypes[$errno]) ? $errorTypes[$errno] : 'UNKNOWN';
        
        self::log('PHP_ERROR', "[$errorType] $errstr", [
            'file' => $errfile,
            'line' => $errline,
            'errno' => $errno
        ]);
        
        return false; // Permite que o handler padrão também processe
    }
    
    /**
     * Handler de exceções
     */
    public static function exceptionHandler($exception) {
        self::log('EXCEPTION', $exception->getMessage(), [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
    
    /**
     * Handler de shutdown (captura erros fatais)
     */
    public static function shutdownHandler() {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            self::log('FATAL_ERROR', $error['message'], [
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => $error['type']
            ]);
        }
    }
    
    /**
     * Habilitar/desabilitar logs
     */
    public static function setEnabled($enabled) {
        self::$enabled = $enabled;
    }
    
    /**
     * Limpar logs antigos (mais de X dias)
     */
    public static function cleanOldLogs($days = 7) {
        $files = glob(self::$logDir . '/debug_*.log');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= 60 * 60 * 24 * $days) {
                    unlink($file);
                }
            }
        }
    }
}

// Inicializar automaticamente
DebugLogger::init();
