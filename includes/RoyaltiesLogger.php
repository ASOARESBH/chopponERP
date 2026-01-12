<?php
/**
 * Classe para gerenciar logs de Royalties, Stripe e Cora
 */
class RoyaltiesLogger {
    private $log_dir;
    private $log_file;
    
    public function __construct($module = 'royalties') {
        $this->log_dir = __DIR__ . '/../logs';
        
        // Criar diretório de logs se não existir
        if (!file_exists($this->log_dir)) {
            mkdir($this->log_dir, 0755, true);
        }
        
        // Definir arquivo de log baseado no módulo
        $this->log_file = $this->log_dir . '/' . $module . '_' . date('Y-m') . '.log';
    }
    
    /**
     * Registrar log de informação
     */
    public function info($message, $context = []) {
        $this->log('INFO', $message, $context);
    }
    
    /**
     * Registrar log de erro
     */
    public function error($message, $context = []) {
        $this->log('ERROR', $message, $context);
    }
    
    /**
     * Registrar log de warning
     */
    public function warning($message, $context = []) {
        $this->log('WARNING', $message, $context);
    }
    
    /**
     * Registrar log de debug
     */
    public function debug($message, $context = []) {
        $this->log('DEBUG', $message, $context);
    }
    
    /**
     * Registrar log de sucesso
     */
    public function success($message, $context = []) {
        $this->log('SUCCESS', $message, $context);
    }
    
    /**
     * Método principal de log
     */
    private function log($level, $message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'N/A';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
        
        // Formatar contexto
        $context_str = '';
        if (!empty($context)) {
            $context_str = ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        
        // Formatar linha de log
        $log_line = sprintf(
            "[%s] [%s] [User: %s] [IP: %s] %s%s\n",
            $timestamp,
            $level,
            $user_id,
            $ip,
            $message,
            $context_str
        );
        
        // Escrever no arquivo
        file_put_contents($this->log_file, $log_line, FILE_APPEND | LOCK_EX);
        
        // Se for erro, também registrar no error_log do PHP
        if ($level === 'ERROR') {
            error_log("[ROYALTIES] $message");
        }
    }
    
    /**
     * Registrar início de operação
     */
    public function startOperation($operation_name, $data = []) {
        $this->info("=== INÍCIO: $operation_name ===", $data);
    }
    
    /**
     * Registrar fim de operação
     */
    public function endOperation($operation_name, $success = true, $data = []) {
        $status = $success ? 'SUCESSO' : 'FALHA';
        $this->info("=== FIM: $operation_name [$status] ===", $data);
    }
    
    /**
     * Registrar requisição HTTP
     */
    public function logHttpRequest($method, $url, $headers = [], $body = null) {
        $this->debug("HTTP Request: $method $url", [
            'headers' => $headers,
            'body' => $body
        ]);
    }
    
    /**
     * Registrar resposta HTTP
     */
    public function logHttpResponse($status_code, $body = null) {
        $level = ($status_code >= 200 && $status_code < 300) ? 'DEBUG' : 'ERROR';
        $this->log($level, "HTTP Response: $status_code", [
            'body' => $body
        ]);
    }
    
    /**
     * Ler últimas linhas do log
     */
    public function readLast($lines = 100) {
        if (!file_exists($this->log_file)) {
            return [];
        }
        
        $file = new SplFileObject($this->log_file, 'r');
        $file->seek(PHP_INT_MAX);
        $last_line = $file->key();
        $start_line = max(0, $last_line - $lines);
        
        $log_lines = [];
        $file->seek($start_line);
        while (!$file->eof()) {
            $line = $file->current();
            if (!empty(trim($line))) {
                $log_lines[] = $line;
            }
            $file->next();
        }
        
        return $log_lines;
    }
}
?>
