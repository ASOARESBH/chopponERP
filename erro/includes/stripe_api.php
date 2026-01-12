<?php
/**
 * Classe para integração com Stripe API
 * Gerencia criação de clientes, faturas e pagamentos
 */

class StripeAPI {
    private $secret_key;
    private $webhook_secret;
    private $modo;
    private $api_base = 'https://api.stripe.com/v1';
    private $logger;
    
    public function __construct($estabelecimento_id = null) {
        // Inicializar logger
        require_once __DIR__ . '/RoyaltiesLogger.php';
        $this->logger = new RoyaltiesLogger('stripe');
        
        $this->logger->info('Inicializando StripeAPI', ['estabelecimento_id' => $estabelecimento_id]);
        
        // Tentar buscar da tabela de configuração
        if ($estabelecimento_id) {
            try {
                $conn = getDBConnection();
                
                // Buscar configuração do Stripe
                $stmt = $conn->prepare("
                    SELECT stripe_secret_key, stripe_webhook_secret, modo, ativo
                    FROM stripe_config
                    WHERE estabelecimento_id = ? AND ativo = 1
                ");
                $stmt->execute([$estabelecimento_id]);
                $config = $stmt->fetch();
                
                if ($config) {
                    $this->secret_key = $config['stripe_secret_key'];
                    $this->webhook_secret = $config['stripe_webhook_secret'];
                    $this->modo = $config['modo'];
                    return;
                }
            } catch (Exception $e) {
                // Tabela não existe ainda, usar configuração global
                $this->logger->warning('Tabela stripe_config não encontrada, usando fallback', ['error' => $e->getMessage()]);
            }
        }
        
        // Fallback: usar variáveis de ambiente ou constantes
        $this->secret_key = getenv('STRIPE_SECRET_KEY') ?: (defined('STRIPE_SECRET_KEY') ? STRIPE_SECRET_KEY : '');
        $this->webhook_secret = getenv('STRIPE_WEBHOOK_SECRET') ?: (defined('STRIPE_WEBHOOK_SECRET') ? STRIPE_WEBHOOK_SECRET : '');
        $this->modo = getenv('STRIPE_MODE') ?: (defined('STRIPE_MODE') ? STRIPE_MODE : 'test');
        
        if (empty($this->secret_key)) {
            $this->logger->error('Secret key do Stripe não configurada');
            throw new Exception('Configuração do Stripe não encontrada. Configure STRIPE_SECRET_KEY.');
        }
        
        $this->logger->success('StripeAPI inicializada com sucesso', ['modo' => $this->modo]);
    }
    
    /**
     * Fazer requisição à API do Stripe
     */
    private function request($endpoint, $method = 'POST', $data = []) {
        $url = $this->api_base . $endpoint;
        
        $this->logger->logHttpRequest($method, $url, [], $data);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->secret_key . ':');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        } elseif ($method === 'GET') {
            if (!empty($data)) {
                $url .= '?' . http_build_query($data);
                curl_setopt($ch, CURLOPT_URL, $url);
            }
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            $this->logger->error('Erro CURL na requisição Stripe', ['error' => $curl_error, 'url' => $url]);
            throw new Exception('Erro de conexão com Stripe: ' . $curl_error);
        }
        
        $result = json_decode($response, true);
        $this->logger->logHttpResponse($http_code, $result);
        
        if ($http_code >= 400) {
            $error_message = $result['error']['message'] ?? 'Erro desconhecido na API do Stripe';
            $this->logger->error('Erro na API Stripe', [
                'http_code' => $http_code,
                'error_message' => $error_message,
                'endpoint' => $endpoint,
                'response' => $result
            ]);
            throw new Exception($error_message);
        }
        
        return $result;
    }
    
    /**
     * Criar ou buscar cliente no Stripe
     */
    public function createOrGetCustomer($email, $name, $phone = null, $address = null) {
        // Buscar cliente existente por e-mail
        $customers = $this->request('/customers', 'GET', ['email' => $email]);
        
        if (!empty($customers['data'])) {
            return $customers['data'][0];
        }
        
        // Criar novo cliente
        $data = [
            'email' => $email,
            'name' => $name
        ];
        
        if ($phone) {
            $data['phone'] = $phone;
        }
        
        if ($address) {
            $data['address'] = $address;
        }
        
        return $this->request('/customers', 'POST', $data);
    }
    
    /**
     * Criar item de fatura
     */
    public function createInvoiceItem($customer_id, $amount, $description, $currency = 'brl') {
        $data = [
            'customer' => $customer_id,
            'amount' => round($amount * 100), // Converter para centavos
            'currency' => $currency,
            'description' => $description
        ];
        
        return $this->request('/invoiceitems', 'POST', $data);
    }
    
    /**
     * Criar fatura
     */
    public function createInvoice($customer_id, $days_until_due = 30, $metadata = []) {
        $data = [
            'customer' => $customer_id,
            'collection_method' => 'send_invoice',
            'days_until_due' => $days_until_due,
            'auto_advance' => true
        ];
        
        if (!empty($metadata)) {
            foreach ($metadata as $key => $value) {
                $data['metadata[' . $key . ']'] = $value;
            }
        }
        
        return $this->request('/invoices', 'POST', $data);
    }
    
    /**
     * Finalizar fatura (torna a fatura pronta para pagamento)
     */
    public function finalizeInvoice($invoice_id) {
        return $this->request('/invoices/' . $invoice_id . '/finalize', 'POST');
    }
    
    /**
     * Enviar fatura por e-mail
     */
    public function sendInvoice($invoice_id) {
        return $this->request('/invoices/' . $invoice_id . '/send', 'POST');
    }
    
    /**
     * Buscar fatura
     */
    public function getInvoice($invoice_id) {
        return $this->request('/invoices/' . $invoice_id, 'GET');
    }
    
    /**
     * Criar fatura completa (cliente + item + fatura + finalizar + enviar)
     * 
     * @param array $customer_data ['email', 'name', 'phone', 'address']
     * @param float $amount Valor em reais
     * @param string $description Descrição da fatura
     * @param array $metadata Metadados adicionais
     * @param int $days_until_due Dias até vencimento
     * @return array Dados da fatura criada
     */
    public function createCompleteInvoice($customer_data, $amount, $description, $metadata = [], $days_until_due = 30) {
        try {
            // 1. Criar ou buscar cliente
            $customer = $this->createOrGetCustomer(
                $customer_data['email'],
                $customer_data['name'],
                $customer_data['phone'] ?? null,
                $customer_data['address'] ?? null
            );
            
            // 2. Criar item de fatura
            $this->createInvoiceItem(
                $customer['id'],
                $amount,
                $description
            );
            
            // 3. Criar fatura
            $invoice = $this->createInvoice(
                $customer['id'],
                $days_until_due,
                $metadata
            );
            
            // 4. Finalizar fatura
            $invoice = $this->finalizeInvoice($invoice['id']);
            
            // 5. Enviar por e-mail
            $this->sendInvoice($invoice['id']);
            
            return [
                'success' => true,
                'customer_id' => $customer['id'],
                'invoice_id' => $invoice['id'],
                'invoice_number' => $invoice['number'],
                'invoice_url' => $invoice['hosted_invoice_url'],
                'invoice_pdf' => $invoice['invoice_pdf'],
                'payment_intent_id' => $invoice['payment_intent'] ?? null,
                'amount' => $amount,
                'currency' => $invoice['currency'],
                'status' => $invoice['status'],
                'due_date' => $invoice['due_date']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verificar status de pagamento de uma fatura
     */
    public function checkInvoiceStatus($invoice_id) {
        try {
            $invoice = $this->getInvoice($invoice_id);
            
            return [
                'success' => true,
                'status' => $invoice['status'],
                'paid' => $invoice['status'] === 'paid',
                'amount_paid' => $invoice['amount_paid'] / 100,
                'paid_at' => $invoice['status_transitions']['paid_at'] ?? null
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Criar Price (preço) no Stripe
     */
    public function createPrice($amount, $description, $currency = 'brl') {
        $data = [
            'unit_amount' => round($amount * 100), // Converter para centavos
            'currency' => $currency,
            'product_data[name]' => $description
        ];
        
        return $this->request('/prices', 'POST', $data);
    }
    
    /**
     * Criar Payment Link
     */
    public function createPaymentLink($price_id, $metadata = []) {
        $data = [
            'line_items[0][price]' => $price_id,
            'line_items[0][quantity]' => 1
        ];
        
        if (!empty($metadata)) {
            foreach ($metadata as $key => $value) {
                $data['metadata[' . $key . ']'] = $value;
            }
        }
        
        return $this->request('/payment_links', 'POST', $data);
    }
    
    /**
     * Criar Payment Link completo (price + link)
     * 
     * @param float $amount Valor em reais
     * @param string $description Descrição do produto/serviço
     * @param array $metadata Metadados adicionais
     * @return array Dados do payment link criado
     */
    public function createCompletePaymentLink($amount, $description, $metadata = []) {
        try {
            // 1. Criar Price
            $price = $this->createPrice($amount, $description);
            
            // 2. Criar Payment Link
            $payment_link = $this->createPaymentLink($price['id'], $metadata);
            
            return [
                'success' => true,
                'payment_link_id' => $payment_link['id'],
                'payment_link_url' => $payment_link['url'],
                'price_id' => $price['id'],
                'amount' => $amount,
                'currency' => $payment_link['currency']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Validar webhook do Stripe
     */
    public function validateWebhook($payload, $signature) {
        $secret = $this->webhook_secret;
        
        // Extrair timestamp e assinatura
        $elements = explode(',', $signature);
        $timestamp = null;
        $sig = null;
        
        foreach ($elements as $element) {
            list($key, $value) = explode('=', $element, 2);
            if ($key === 't') {
                $timestamp = $value;
            } elseif ($key === 'v1') {
                $sig = $value;
            }
        }
        
        if (!$timestamp || !$sig) {
            throw new Exception('Assinatura do webhook inválida');
        }
        
        // Verificar timestamp (não pode ser muito antigo)
        $tolerance = 300; // 5 minutos
        if (abs(time() - $timestamp) > $tolerance) {
            throw new Exception('Webhook expirado');
        }
        
        // Calcular assinatura esperada
        $signed_payload = $timestamp . '.' . $payload;
        $expected_sig = hash_hmac('sha256', $signed_payload, $secret);
        
        // Comparar assinaturas
        if (!hash_equals($expected_sig, $sig)) {
            throw new Exception('Assinatura do webhook não corresponde');
        }
        
        return true;
    }
    
    /**
     * Obter modo atual (test ou live)
     */
    public function getModo() {
        return $this->modo;
    }
}
?>
