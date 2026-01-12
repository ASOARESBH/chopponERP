<?php
/**
 * Classe de Rate Limiting
 * Versão 1.0 - Implementação simples com arquivos
 */

class RateLimiter {
    
    private $storage_path;
    
    /**
     * Construtor
     * @param string $storage_path Caminho para armazenar dados de rate limiting
     */
    public function __construct($storage_path = null) {
        $this->storage_path = $storage_path ?? __DIR__ . '/../logs/rate_limit/';
        
        // Criar diretório se não existir
        if (!is_dir($this->storage_path)) {
            mkdir($this->storage_path, 0755, true);
        }
    }
    
    /**
     * Verifica se o limite foi excedido
     * @param string $identifier Identificador único (IP, user_id, etc)
     * @param int $max_attempts Número máximo de tentativas
     * @param int $window_seconds Janela de tempo em segundos
     * @return bool True se limite foi excedido
     */
    public function isLimitExceeded($identifier, $max_attempts = 5, $window_seconds = 60) {
        $key = $this->getKey($identifier);
        $file = $this->storage_path . $key . '.json';
        
        // Limpar tentativas antigas
        $this->cleanup();
        
        $data = $this->loadData($file);
        $now = time();
        
        // Filtrar tentativas dentro da janela de tempo
        $data['attempts'] = array_filter($data['attempts'], function($timestamp) use ($now, $window_seconds) {
            return ($now - $timestamp) < $window_seconds;
        });
        
        // Verificar se excedeu o limite
        if (count($data['attempts']) >= $max_attempts) {
            $this->saveData($file, $data);
            return true;
        }
        
        // Registrar nova tentativa
        $data['attempts'][] = $now;
        $this->saveData($file, $data);
        
        return false;
    }
    
    /**
     * Reseta o contador para um identificador
     * @param string $identifier
     */
    public function reset($identifier) {
        $key = $this->getKey($identifier);
        $file = $this->storage_path . $key . '.json';
        
        if (file_exists($file)) {
            unlink($file);
        }
    }
    
    /**
     * Obtém o número de tentativas restantes
     * @param string $identifier
     * @param int $max_attempts
     * @param int $window_seconds
     * @return int
     */
    public function getRemainingAttempts($identifier, $max_attempts = 5, $window_seconds = 60) {
        $key = $this->getKey($identifier);
        $file = $this->storage_path . $key . '.json';
        
        $data = $this->loadData($file);
        $now = time();
        
        // Filtrar tentativas dentro da janela de tempo
        $data['attempts'] = array_filter($data['attempts'], function($timestamp) use ($now, $window_seconds) {
            return ($now - $timestamp) < $window_seconds;
        });
        
        return max(0, $max_attempts - count($data['attempts']));
    }
    
    /**
     * Gera chave única para o identificador
     * @param string $identifier
     * @return string
     */
    private function getKey($identifier) {
        return md5($identifier);
    }
    
    /**
     * Carrega dados do arquivo
     * @param string $file
     * @return array
     */
    private function loadData($file) {
        if (!file_exists($file)) {
            return ['attempts' => []];
        }
        
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        
        return $data ?: ['attempts' => []];
    }
    
    /**
     * Salva dados no arquivo
     * @param string $file
     * @param array $data
     */
    private function saveData($file, $data) {
        file_put_contents($file, json_encode($data));
    }
    
    /**
     * Limpa arquivos antigos (mais de 1 hora)
     */
    private function cleanup() {
        $files = glob($this->storage_path . '*.json');
        $now = time();
        
        foreach ($files as $file) {
            if (($now - filemtime($file)) > 3600) {
                unlink($file);
            }
        }
    }
    
    /**
     * Obtém IP do cliente
     * @return string
     */
    public static function getClientIP() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Se for lista de IPs, pegar o primeiro
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                return trim($ip);
            }
        }
        
        return '0.0.0.0';
    }
}
