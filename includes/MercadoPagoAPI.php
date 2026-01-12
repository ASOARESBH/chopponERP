<?php
/**
 * Classe MercadoPagoAPI
 * Integração com API do Mercado Pago para pagamentos
 */

class MercadoPagoAPI {
    private $conn;
    private $access_token;
    private $public_key;
    private $ambiente;
    private $base_url;
    
    public function __construct($conn, $estabelecimento_id = null) {
        $this->conn = $conn;
        
        if ($estabelecimento_id) {
            $this->carregarConfiguracao($estabelecimento_id);
        }
    }
    
    /**
     * Carregar configuração do Mercado Pago para um estabelecimento
     */
    private function carregarConfiguracao($estabelecimento_id) {
        $stmt = $this->conn->prepare("
            SELECT * FROM mercadopago_config 
            WHERE estabelecimento_id = ? AND status = 1
        ");
        $stmt->execute([$estabelecimento_id]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$config) {
            throw new Exception("Configuração do Mercado Pago não encontrada para este estabelecimento.");
        }
        
        $this->access_token = $config['access_token'];
        $this->public_key = $config['public_key'];
        $this->ambiente = $config['ambiente'];
        
        // URL base conforme ambiente
        $this->base_url = ($this->ambiente === 'production') 
            ? 'https://api.mercadopago.com' 
            : 'https://api.mercadopago.com'; // Mercado Pago usa mesma URL para sandbox
    }
    
    /**
     * Criar preferência de pagamento
     */
    public function criarPreferencia($dados) {
        $url = $this->base_url . '/checkout/preferences';
        
        $payload = [
            'items' => [
                [
                    'title' => $dados['titulo'],
                    'description' => $dados['descricao'] ?? '',
                    'quantity' => 1,
                    'currency_id' => 'BRL',
                    'unit_price' => (float)$dados['valor']
                ]
            ],
            'payer' => [
                'name' => $dados['pagador_nome'] ?? '',
                'email' => $dados['pagador_email'] ?? '',
                'identification' => [
                    'type' => 'CPF',
                    'number' => $dados['pagador_cpf'] ?? ''
                ]
            ],
            'back_urls' => [
                'success' => $dados['url_sucesso'] ?? '',
                'failure' => $dados['url_falha'] ?? '',
                'pending' => $dados['url_pendente'] ?? ''
            ],
            'auto_return' => 'approved',
            'external_reference' => $dados['referencia_externa'] ?? '',
            'notification_url' => $dados['webhook_url'] ?? '',
            'statement_descriptor' => 'CHOPPON',
            'expires' => true,
            'expiration_date_from' => date('c'),
            'expiration_date_to' => date('c', strtotime('+7 days'))
        ];
        
        $response = $this->fazerRequisicao('POST', $url, $payload);
        
        return $response;
    }
    
    /**
     * Criar pagamento PIX
     */
    public function criarPagamentoPix($dados) {
        $url = $this->base_url . '/v1/payments';
        
        $payload = [
            'transaction_amount' => (float)$dados['valor'],
            'description' => $dados['descricao'],
            'payment_method_id' => 'pix',
            'payer' => [
                'email' => $dados['pagador_email'],
                'first_name' => $dados['pagador_nome'] ?? '',
                'identification' => [
                    'type' => 'CPF',
                    'number' => $dados['pagador_cpf'] ?? ''
                ]
            ],
            'notification_url' => $dados['webhook_url'] ?? '',
            'external_reference' => $dados['referencia_externa'] ?? ''
        ];
        
        $response = $this->fazerRequisicao('POST', $url, $payload);
        
        return $response;
    }
    
    /**
     * Consultar status de pagamento
     */
    public function consultarPagamento($payment_id) {
        $url = $this->base_url . '/v1/payments/' . $payment_id;
        
        $response = $this->fazerRequisicao('GET', $url);
        
        return $response;
    }
    
    /**
     * Processar webhook do Mercado Pago
     */
    public function processarWebhook($data) {
        // Validar webhook
        if (!isset($data['type']) || !isset($data['data']['id'])) {
            throw new Exception('Webhook inválido');
        }
        
        $type = $data['type'];
        $payment_id = $data['data']['id'];
        
        // Buscar informações do pagamento
        $payment = $this->consultarPagamento($payment_id);
        
        return [
            'type' => $type,
            'payment_id' => $payment_id,
            'status' => $payment['status'] ?? null,
            'payment_data' => $payment
        ];
    }
    
    /**
     * Fazer requisição HTTP para API
     */
    private function fazerRequisicao($method, $url, $data = null) {
        $ch = curl_init();
        
        $headers = [
            'Authorization: Bearer ' . $this->access_token,
            'Content-Type: application/json',
            'X-Idempotency-Key: ' . uniqid('mp_', true)
        ];
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        
        curl_close($ch);
        
        if ($curl_error) {
            throw new Exception("Erro na requisição: " . $curl_error);
        }
        
        $response_data = json_decode($response, true);
        
        if ($http_code >= 400) {
            $error_message = $response_data['message'] ?? 'Erro desconhecido';
            throw new Exception("Erro Mercado Pago ({$http_code}): " . $error_message);
        }
        
        return $response_data;
    }
    
    /**
     * Validar configuração
     */
    public function validarConfiguracao() {
        try {
            // Fazer uma requisição simples para validar o token
            $url = $this->base_url . '/v1/payment_methods';
            $this->fazerRequisicao('GET', $url);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Obter métodos de pagamento disponíveis
     */
    public function obterMetodosPagamento() {
        $url = $this->base_url . '/v1/payment_methods';
        
        $response = $this->fazerRequisicao('GET', $url);
        
        return $response;
    }
    
    /**
     * Mapear status do Mercado Pago para status interno
     */
    public static function mapearStatus($mp_status) {
        $map = [
            'pending' => 'pendente',
            'approved' => 'aprovado',
            'authorized' => 'processando',
            'in_process' => 'processando',
            'in_mediation' => 'processando',
            'rejected' => 'recusado',
            'cancelled' => 'cancelado',
            'refunded' => 'cancelado',
            'charged_back' => 'cancelado'
        ];
        
        return $map[$mp_status] ?? 'pendente';
    }
}
?>
