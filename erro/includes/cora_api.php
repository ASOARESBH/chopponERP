<?php
/**
 * Classe para integração com API Banco Cora
 * Emissão de boletos registrados
 */

class CoraAPI {
    private $client_id;
    private $certificate_path;
    private $private_key_path;
    private $environment; // 'stage' ou 'production'
    private $access_token;
    private $token_expires_at;
    private $logger;
    
    /**
     * Construtor
     * @param string $client_id Client ID da Cora
     * @param string $certificate_path Caminho para o certificado .pem
     * @param string $private_key_path Caminho para a private key .key
     * @param string $environment 'stage' ou 'production'
     */
    public function __construct($client_id, $certificate_path, $private_key_path, $environment = 'stage') {
        // Inicializar logger
        require_once __DIR__ . '/RoyaltiesLogger.php';
        $this->logger = new RoyaltiesLogger('cora');
        
        $this->logger->info('Inicializando CoraAPI', [
            'client_id' => $client_id,
            'environment' => $environment
        ]);
        
        $this->client_id = $client_id;
        $this->certificate_path = $certificate_path;
        $this->private_key_path = $private_key_path;
        $this->environment = $environment;
        $this->access_token = null;
        $this->token_expires_at = null;
        
        // Validar certificados
        if (!file_exists($certificate_path)) {
            $this->logger->error('Certificado Cora não encontrado', ['path' => $certificate_path]);
        }
        if (!file_exists($private_key_path)) {
            $this->logger->error('Chave privada Cora não encontrada', ['path' => $private_key_path]);
        }
        
        $this->logger->success('CoraAPI inicializada com sucesso');
    }
    
    /**
     * Obter URLs baseado no ambiente
     */
    private function getAuthUrl() {
        return $this->environment === 'production' 
            ? 'https://matls-clients.api.cora.com.br/token'
            : 'https://matls-clients.api.stage.cora.com.br/token';
    }
    
    private function getApiUrl() {
        return $this->environment === 'production'
            ? 'https://api.cora.com.br/v2/invoices/'
            : 'https://api.stage.cora.com.br/v2/invoices/';
    }
    
    /**
     * Autenticar e obter token de acesso
     * @return bool
     */
    private function authenticate() {
        try {
            $this->logger->startOperation('Autenticação Cora');
            
            // Verificar se já temos um token válido
            if ($this->access_token && $this->token_expires_at && time() < $this->token_expires_at) {
                $this->logger->debug('Token Cora ainda válido, reutilizando');
                return true;
            }
            
            $this->logger->info('Solicitando novo token de acesso Cora');
            
            $ch = curl_init();
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->getAuthUrl(),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->client_id
                ]),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded'
                ],
                CURLOPT_SSLCERT => $this->certificate_path,
                CURLOPT_SSLKEY => $this->private_key_path,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($curl_error) {
                $this->logger->error("Erro cURL na autenticação Cora", ['error' => $curl_error]);
                Logger::error("Erro cURL na autenticação Cora", ['error' => $curl_error]);
                return false;
            }
            
            $this->logger->logHttpResponse($http_code, $response);
            
            if ($http_code !== 200) {
                $this->logger->error("Erro HTTP na autenticação Cora", [
                    'http_code' => $http_code,
                    'response' => $response
                ]);
                Logger::error("Erro HTTP na autenticação Cora", [
                    'http_code' => $http_code,
                    'response' => $response
                ]);
                return false;
            }
            
            $data = json_decode($response, true);
            
            if (!isset($data['access_token'])) {
                Logger::error("Token não retornado pela API Cora", ['response' => $response]);
                return false;
            }
            
            $this->access_token = $data['access_token'];
            $this->token_expires_at = time() + ($data['expires_in'] ?? 86400) - 300; // 5 min de margem
            
            Logger::info("Autenticação Cora realizada com sucesso");
            return true;
            
        } catch (Exception $e) {
            Logger::error("Exceção na autenticação Cora", ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Gerar UUID v4 para Idempotency-Key
     * @return string
     */
    private function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    /**
     * Emitir boleto registrado
     * 
     * @param array $data Dados do boleto
     * @return array|false Retorna dados do boleto ou false em caso de erro
     */
    public function emitirBoleto($data) {
        try {
            // Autenticar primeiro
            if (!$this->authenticate()) {
                return [
                    'success' => false,
                    'error' => 'Falha na autenticação com API Cora'
                ];
            }
            
            // Validar dados obrigatórios
            $required = ['code', 'customer', 'services', 'payment_terms'];
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    return [
                        'success' => false,
                        'error' => "Campo obrigatório ausente: {$field}"
                    ];
                }
            }
            
            // Gerar Idempotency-Key
            $idempotency_key = $this->generateUUID();
            
            // Preparar requisição
            $ch = curl_init();
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->getApiUrl(),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->access_token,
                    'Idempotency-Key: ' . $idempotency_key,
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($curl_error) {
                Logger::error("Erro cURL ao emitir boleto Cora", ['error' => $curl_error]);
                return [
                    'success' => false,
                    'error' => 'Erro de comunicação com API Cora: ' . $curl_error
                ];
            }
            
            $response_data = json_decode($response, true);
            
            if ($http_code !== 200 && $http_code !== 201) {
                Logger::error("Erro HTTP ao emitir boleto Cora", [
                    'http_code' => $http_code,
                    'response' => $response,
                    'data_sent' => $data
                ]);
                
                $error_message = 'Erro ao emitir boleto';
                if (isset($response_data['message'])) {
                    $error_message .= ': ' . $response_data['message'];
                } elseif (isset($response_data['error'])) {
                    $error_message .= ': ' . $response_data['error'];
                }
                
                return [
                    'success' => false,
                    'error' => $error_message,
                    'http_code' => $http_code,
                    'response' => $response_data
                ];
            }
            
            Logger::info("Boleto Cora emitido com sucesso", [
                'boleto_id' => $response_data['id'] ?? null,
                'code' => $data['code']
            ]);
            
            return [
                'success' => true,
                'data' => $response_data
            ];
            
        } catch (Exception $e) {
            Logger::error("Exceção ao emitir boleto Cora", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Consultar boleto por ID
     * 
     * @param string $boleto_id ID do boleto na Cora
     * @return array|false
     */
    public function consultarBoleto($boleto_id) {
        try {
            if (!$this->authenticate()) {
                return [
                    'success' => false,
                    'error' => 'Falha na autenticação com API Cora'
                ];
            }
            
            $ch = curl_init();
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->getApiUrl() . $boleto_id,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->access_token,
                    'Accept: application/json'
                ],
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code !== 200) {
                return [
                    'success' => false,
                    'error' => 'Erro ao consultar boleto',
                    'http_code' => $http_code
                ];
            }
            
            return [
                'success' => true,
                'data' => json_decode($response, true)
            ];
            
        } catch (Exception $e) {
            Logger::error("Exceção ao consultar boleto Cora", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Cancelar boleto
     * 
     * @param string $boleto_id ID do boleto na Cora
     * @return array
     */
    public function cancelarBoleto($boleto_id) {
        try {
            if (!$this->authenticate()) {
                return [
                    'success' => false,
                    'error' => 'Falha na autenticação com API Cora'
                ];
            }
            
            $ch = curl_init();
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->getApiUrl() . $boleto_id,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'DELETE',
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->access_token,
                    'Accept: application/json'
                ],
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code !== 200 && $http_code !== 204) {
                return [
                    'success' => false,
                    'error' => 'Erro ao cancelar boleto',
                    'http_code' => $http_code
                ];
            }
            
            Logger::info("Boleto Cora cancelado", ['boleto_id' => $boleto_id]);
            
            return [
                'success' => true
            ];
            
        } catch (Exception $e) {
            Logger::error("Exceção ao cancelar boleto Cora", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ];
        }
    }
}
?>
