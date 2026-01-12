<?php
/**
 * Classe para integração com API Banco Cora v2
 * Emissão de boletos registrados
 * 
 * CONFORMIDADE COM DOCUMENTAÇÃO OFICIAL:
 * https://developers.cora.com.br/docs/instrucoes-iniciais
 * https://developers.cora.com.br/reference/emissão-de-boleto-registrado-v2
 * 
 * CREDENCIAIS NECESSÁRIAS:
 * - client_id: Identificador da aplicação (obtido em Conta > Integrações via APIs)
 * - client_secret: Chave secreta da aplicação (obtido em Conta > Integrações via APIs)
 * - Ambiente: stage (testes) ou production (produção)
 */

class CoraAPIv2 {
    private $client_id;
    private $client_secret;
    private $environment;
    private $access_token;
    private $token_expires_at;
    private $logger;
    
    // URLs da API conforme documentação oficial
    private $urls = [
        'stage' => [
            'auth' => 'https://auth.stage.cora.com.br/oauth/authorize',
            'token' => 'https://auth.stage.cora.com.br/oauth/token',
            'api' => 'https://api.stage.cora.com.br/v2'
        ],
        'production' => [
            'auth' => 'https://auth.cora.com.br/oauth/authorize',
            'token' => 'https://auth.cora.com.br/oauth/token',
            'api' => 'https://api.cora.com.br/v2'
        ]
    ];
    
    /**
     * Construtor
     * @param string $client_id Client ID da Cora
     * @param string $client_secret Client Secret da Cora
     * @param string $environment 'stage' ou 'production'
     */
    public function __construct($client_id, $client_secret, $environment = 'stage') {
        require_once __DIR__ . '/RoyaltiesLogger.php';
        $this->logger = new RoyaltiesLogger('cora_v2');
        
        $this->logger->info('Inicializando CoraAPIv2', [
            'client_id' => $client_id,
            'environment' => $environment
        ]);
        
        if (!in_array($environment, ['stage', 'production'])) {
            throw new Exception('Ambiente deve ser "stage" ou "production"');
        }
        
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->environment = $environment;
        $this->access_token = null;
        $this->token_expires_at = null;
        
        $this->logger->success('CoraAPIv2 inicializada com sucesso');
    }
    
    /**
     * Obter URL baseado no ambiente
     */
    private function getUrl($type) {
        return $this->urls[$this->environment][$type] ?? null;
    }
    
    /**
     * Autenticar e obter token de acesso
     * Segue fluxo OAuth 2.0 conforme documentação
     * @return bool
     */
    private function authenticate() {
        try {
            $this->logger->startOperation('Autenticação Cora OAuth 2.0');
            
            // Verificar se já temos um token válido
            if ($this->access_token && $this->token_expires_at && time() < $this->token_expires_at) {
                $this->logger->debug('Token Cora ainda válido, reutilizando');
                return true;
            }
            
            $this->logger->info('Solicitando novo token de acesso Cora');
            
            $ch = curl_init();
            
            $post_data = [
                'grant_type' => 'client_credentials',
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret
            ];
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->getUrl('token'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($post_data),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json'
                ],
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_TIMEOUT => 30
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($curl_error) {
                $this->logger->error('Erro cURL na autenticação Cora', ['error' => $curl_error]);
                return false;
            }
            
            $this->logger->logHttpResponse($http_code, $response);
            
            if ($http_code !== 200) {
                $this->logger->error('Erro HTTP na autenticação Cora', [
                    'http_code' => $http_code,
                    'response' => $response
                ]);
                return false;
            }
            
            $data = json_decode($response, true);
            
            if (!isset($data['access_token'])) {
                $this->logger->error('Token não retornado pela API Cora', ['response' => $response]);
                return false;
            }
            
            $this->access_token = $data['access_token'];
            // Token expira em 24h por padrão, usar 23h de margem
            $this->token_expires_at = time() + ($data['expires_in'] ?? 86400) - 3600;
            
            $this->logger->success('Autenticação Cora realizada com sucesso');
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Exceção na autenticação Cora', ['error' => $e->getMessage()]);
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
     * Fazer requisição à API Cora
     */
    private function request($endpoint, $method = 'POST', $data = []) {
        if (!$this->authenticate()) {
            return [
                'success' => false,
                'error' => 'Falha na autenticação com API Cora'
            ];
        }
        
        $url = $this->getUrl('api') . $endpoint;
        $ch = curl_init();
        
        $headers = [
            'Authorization: Bearer ' . $this->access_token,
            'Content-Type: application/json',
            'Accept: application/json',
            'Idempotency-Key: ' . $this->generateUUID()
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 30
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            $this->logger->error('Erro cURL na requisição', [
                'endpoint' => $endpoint,
                'error' => $curl_error
            ]);
            return [
                'success' => false,
                'error' => 'Erro de comunicação: ' . $curl_error
            ];
        }
        
        $response_data = json_decode($response, true);
        $this->logger->logHttpResponse($http_code, $response_data);
        
        return [
            'success' => $http_code >= 200 && $http_code < 300,
            'http_code' => $http_code,
            'data' => $response_data,
            'raw_response' => $response
        ];
    }
    
    /**
     * Emitir boleto registrado
     * 
     * Estrutura conforme documentação:
     * https://developers.cora.com.br/reference/emissão-de-boleto-registrado-v2
     * 
     * @param array $data Dados do boleto
     * @return array
     */
    public function emitirBoleto($data) {
        try {
            $this->logger->startOperation('Emissão de boleto registrado');
            
            // Validar dados obrigatórios
            $required_fields = [
                'amount',           // Valor em centavos (ex: 100.50 = 10050)
                'due_date',         // Data de vencimento (YYYY-MM-DD)
                'payer',            // Dados do pagador
                'beneficiary',      // Dados do beneficiário
                'description'       // Descrição do boleto
            ];
            
            foreach ($required_fields as $field) {
                if (!isset($data[$field])) {
                    return [
                        'success' => false,
                        'error' => "Campo obrigatório ausente: {$field}"
                    ];
                }
            }
            
            // Validar estrutura de payer
            if (!isset($data['payer']['name']) || !isset($data['payer']['document'])) {
                return [
                    'success' => false,
                    'error' => 'Dados do pagador incompletos (name e document obrigatórios)'
                ];
            }
            
            // Validar estrutura de beneficiary
            if (!isset($data['beneficiary']['name']) || !isset($data['beneficiary']['document'])) {
                return [
                    'success' => false,
                    'error' => 'Dados do beneficiário incompletos (name e document obrigatórios)'
                ];
            }
            
            // Converter valor para centavos se necessário
            if ($data['amount'] < 1000) {
                // Assumir que é em reais, converter para centavos
                $data['amount'] = round($data['amount'] * 100);
            }
            
            // Validar valor mínimo (R$ 5,00 = 500 centavos)
            if ($data['amount'] < 500) {
                return [
                    'success' => false,
                    'error' => 'Valor mínimo do boleto é R$ 5,00'
                ];
            }
            
            $this->logger->info('Dados do boleto validados', [
                'amount' => $data['amount'],
                'due_date' => $data['due_date'],
                'payer' => $data['payer']['name']
            ]);
            
            $result = $this->request('/invoices', 'POST', $data);
            
            if (!$result['success']) {
                $this->logger->error('Erro ao emitir boleto', [
                    'http_code' => $result['http_code'],
                    'response' => $result['data']
                ]);
                
                $error_message = 'Erro ao emitir boleto';
                if (isset($result['data']['message'])) {
                    $error_message .= ': ' . $result['data']['message'];
                } elseif (isset($result['data']['error'])) {
                    $error_message .= ': ' . $result['data']['error'];
                }
                
                return [
                    'success' => false,
                    'error' => $error_message,
                    'http_code' => $result['http_code']
                ];
            }
            
            $this->logger->success('Boleto emitido com sucesso', [
                'boleto_id' => $result['data']['id'] ?? null
            ]);
            
            return [
                'success' => true,
                'data' => $result['data']
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Exceção ao emitir boleto', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Consultar boleto por ID
     * @param string $boleto_id ID do boleto na Cora
     * @return array
     */
    public function consultarBoleto($boleto_id) {
        try {
            $this->logger->startOperation('Consulta de boleto');
            
            $result = $this->request('/invoices/' . $boleto_id, 'GET');
            
            if (!$result['success']) {
                $this->logger->error('Erro ao consultar boleto', [
                    'boleto_id' => $boleto_id,
                    'http_code' => $result['http_code']
                ]);
                
                return [
                    'success' => false,
                    'error' => 'Erro ao consultar boleto',
                    'http_code' => $result['http_code']
                ];
            }
            
            $this->logger->success('Boleto consultado com sucesso');
            
            return [
                'success' => true,
                'data' => $result['data']
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Exceção ao consultar boleto', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Cancelar boleto
     * @param string $boleto_id ID do boleto na Cora
     * @return array
     */
    public function cancelarBoleto($boleto_id) {
        try {
            $this->logger->startOperation('Cancelamento de boleto');
            
            $result = $this->request('/invoices/' . $boleto_id, 'DELETE');
            
            if (!$result['success']) {
                $this->logger->error('Erro ao cancelar boleto', [
                    'boleto_id' => $boleto_id,
                    'http_code' => $result['http_code']
                ]);
                
                return [
                    'success' => false,
                    'error' => 'Erro ao cancelar boleto',
                    'http_code' => $result['http_code']
                ];
            }
            
            $this->logger->success('Boleto cancelado com sucesso');
            
            return [
                'success' => true,
                'data' => $result['data']
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Exceção ao cancelar boleto', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Listar boletos com filtros
     * @param array $filters Filtros opcionais (status, payer_document, etc)
     * @return array
     */
    public function listarBoletos($filters = []) {
        try {
            $this->logger->startOperation('Listagem de boletos');
            
            $query_string = '';
            if (!empty($filters)) {
                $query_string = '?' . http_build_query($filters);
            }
            
            $result = $this->request('/invoices' . $query_string, 'GET');
            
            if (!$result['success']) {
                $this->logger->error('Erro ao listar boletos', [
                    'http_code' => $result['http_code']
                ]);
                
                return [
                    'success' => false,
                    'error' => 'Erro ao listar boletos',
                    'http_code' => $result['http_code']
                ];
            }
            
            $this->logger->success('Boletos listados com sucesso');
            
            return [
                'success' => true,
                'data' => $result['data']
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Exceção ao listar boletos', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obter status do boleto
     * Retorna status simplificado para uso em polling
     * @param string $boleto_id ID do boleto
     * @return array
     */
    public function obterStatusBoleto($boleto_id) {
        try {
            $result = $this->consultarBoleto($boleto_id);
            
            if (!$result['success']) {
                return $result;
            }
            
            $data = $result['data'];
            
            // Mapear status da Cora para status simplificado
            $status_map = [
                'PENDING' => 'aguardando',
                'OVERDUE' => 'vencido',
                'PAID' => 'pago',
                'CANCELED' => 'cancelado',
                'REJECTED' => 'rejeitado'
            ];
            
            $status = $status_map[$data['status']] ?? $data['status'];
            
            return [
                'success' => true,
                'boleto_id' => $boleto_id,
                'status' => $status,
                'status_original' => $data['status'],
                'amount' => $data['amount'] ?? null,
                'paid_amount' => $data['paid_amount'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'paid_at' => $data['paid_at'] ?? null,
                'created_at' => $data['created_at'] ?? null
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Exceção ao obter status do boleto', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ];
        }
    }
}
?>
