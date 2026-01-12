<?php
/**
 * ============================================
 * TESTE COMPLETO - STRIPE PAYMENT LINK
 * Para Royalties
 * ============================================
 * 
 * Este script testa a integração completa com Stripe
 * Simula a criação de um payment link para royalties
 * com todos os parâmetros necessários
 */

// ===== CONFIGURAÇÃO DE TESTE =====

// Dados de teste (USAR CHAVES DE TEST DO STRIPE)
$STRIPE_SECRET_KEY = 'sk_test_seu_secret_key_aqui';  // ⚠️ SUBSTITUA COM SUA CHAVE
$STRIPE_WEBHOOK_SECRET = 'whsec_seu_webhook_secret_aqui';  // ⚠️ SUBSTITUA COM SUA CHAVE

// Dados do royalty de teste
$royalty_test = [
    'royalty_id' => 12345,
    'estabelecimento_id' => 1,
    'estabelecimento_nome' => 'Bar do João',
    'valor_royalties' => 150.50,  // R$ 150,50
    'descricao' => 'Royalties - Novembro 2025',
    'periodo_inicial' => '2025-11-01',
    'periodo_final' => '2025-11-30',
    'email_cobranca' => 'cobranca@barjao.com.br',
];

// ===== SIMULAÇÃO DA CLASSE STRIPE =====

class StripeAPITest {
    private $secret_key;
    private $webhook_secret;
    private $modo = 'test';
    private $api_base = 'https://api.stripe.com/v1';
    private $test_mode = true;  // Usar modo de teste
    private $log = [];
    
    public function __construct($secret_key, $webhook_secret) {
        $this->secret_key = $secret_key;
        $this->webhook_secret = $webhook_secret;
        
        // Validar chave
        if (strpos($secret_key, 'sk_test_') === 0) {
            $this->modo = 'test';
        } elseif (strpos($secret_key, 'sk_live_') === 0) {
            $this->modo = 'live';
        } else {
            throw new Exception('Chave secreta inválida. Deve começar com sk_test_ ou sk_live_');
        }
        
        $this->log('Stripe inicializado em modo: ' . strtoupper($this->modo));
    }
    
    /**
     * Fazer requisição à API do Stripe
     */
    private function request($endpoint, $method = 'POST', $data = []) {
        $url = $this->api_base . $endpoint;
        
        $this->log("Requisição: $method $endpoint");
        $this->log("Dados: " . json_encode($data, JSON_PRETTY_PRINT));
        
        // ===== VALIDAÇÕES ANTES DE ENVIAR =====
        
        // 1. Validar autenticação
        if (empty($this->secret_key)) {
            throw new Exception('Chave secreta não configurada');
        }
        
        // 2. Validar dados obrigatórios
        if ($method === 'POST' && empty($data)) {
            throw new Exception('Dados vazios para requisição POST');
        }
        
        // 3. Validar tamanho dos metadados
        if (isset($data['metadata'])) {
            foreach ($data['metadata'] as $key => $value) {
                if (strlen((string)$value) > 500) {
                    throw new Exception("Valor de metadado '$key' muito longo (máx 500 caracteres)");
                }
            }
        }
        
        // ===== SIMULAR RESPOSTA DO STRIPE =====
        
        // Em modo de teste, retornar resposta simulada
        if ($this->test_mode) {
            return $this->simulateStripeResponse($endpoint, $method, $data);
        }
        
        // Em modo real, fazer requisição com curl
        return $this->makeCurlRequest($url, $method, $data);
    }
    
    /**
     * Simular resposta do Stripe para testes
     */
    private function simulateStripeResponse($endpoint, $method, $data) {
        $this->log("MODO TESTE: Simulando resposta do Stripe");
        
        if ($endpoint === '/prices' && $method === 'POST') {
            // Simular criação de price
            return [
                'id' => 'price_' . uniqid(),
                'object' => 'price',
                'active' => true,
                'billing_scheme' => 'per_unit',
                'created' => time(),
                'currency' => $data['currency'] ?? 'brl',
                'custom_unit_amount' => null,
                'livemode' => false,
                'lookup_key' => null,
                'metadata' => [],
                'nickname' => null,
                'product' => 'prod_' . uniqid(),
                'recurring' => null,
                'tax_behavior' => 'unspecified',
                'type' => 'one_time',
                'unit_amount' => $data['unit_amount'] ?? 0,
                'unit_amount_decimal' => ($data['unit_amount'] ?? 0) . '.00'
            ];
        }
        
        if ($endpoint === '/payment_links' && $method === 'POST') {
            // Simular criação de payment link
            return [
                'id' => 'plink_' . uniqid(),
                'object' => 'payment_link',
                'active' => true,
                'after_completion' => ['type' => 'redirect', 'redirect' => ['url' => null]],
                'allow_promotion_codes' => false,
                'application' => null,
                'application_fee_amount' => null,
                'application_fee_percent' => null,
                'automatic_tax' => ['enabled' => false],
                'billing_address_collection' => 'auto',
                'created' => time(),
                'currency' => $data['currency'] ?? 'brl',
                'customer_creation' => 'if_required',
                'livemode' => false,
                'metadata' => $data['metadata'] ?? [],
                'on_behalf_of' => null,
                'payment_intent_data' => null,
                'payment_method_collection' => 'always',
                'payment_method_types' => ['card', 'boleto'],
                'phone_number_collection' => ['enabled' => false],
                'restrictions' => null,
                'shipping_address_collection' => null,
                'shipping_options' => [],
                'submit_type' => 'auto',
                'subscription_data' => null,
                'tax_id_collection' => ['enabled' => false],
                'transfer_data' => null,
                'url' => 'https://buy.stripe.com/test_' . uniqid(),
                'line_items' => [
                    'object' => 'list',
                    'data' => [
                        [
                            'id' => 'li_' . uniqid(),
                            'object' => 'item',
                            'amount_discount' => 0,
                            'amount_subtotal' => $data['line_items[0][quantity]'] * ($data['unit_amount'] ?? 0),
                            'amount_tax' => 0,
                            'amount_total' => $data['line_items[0][quantity]'] * ($data['unit_amount'] ?? 0),
                            'currency' => $data['currency'] ?? 'brl',
                            'description' => $data['description'] ?? 'Royalties',
                            'discount_amounts' => [],
                            'discountable' => true,
                            'discounts' => [],
                            'price' => [
                                'id' => $data['line_items[0][price]'] ?? 'price_test',
                                'object' => 'price',
                                'active' => true,
                                'billing_scheme' => 'per_unit',
                                'created' => time(),
                                'currency' => $data['currency'] ?? 'brl',
                                'custom_unit_amount' => null,
                                'livemode' => false,
                                'lookup_key' => null,
                                'metadata' => [],
                                'nickname' => null,
                                'object' => 'price',
                                'product' => 'prod_' . uniqid(),
                                'recurring' => null,
                                'tax_behavior' => 'unspecified',
                                'type' => 'one_time',
                                'unit_amount' => $data['unit_amount'] ?? 0,
                                'unit_amount_decimal' => ($data['unit_amount'] ?? 0) . '.00'
                            ],
                            'quantity' => $data['line_items[0][quantity]'] ?? 1,
                            'tax_amounts' => []
                        ]
                    ],
                    'has_more' => false,
                    'total_count' => 1,
                    'url' => '/v1/payment_links/plink_' . uniqid() . '/line_items'
                ]
            ];
        }
        
        throw new Exception('Endpoint não suportado em modo teste: ' . $endpoint);
    }
    
    /**
     * Fazer requisição real com CURL
     */
    private function makeCurlRequest($url, $method, $data) {
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
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            throw new Exception('Erro CURL: ' . $curl_error);
        }
        
        $result = json_decode($response, true);
        
        if ($http_code >= 400) {
            $error_message = $result['error']['message'] ?? 'Erro desconhecido';
            throw new Exception("Erro Stripe ($http_code): " . $error_message);
        }
        
        return $result;
    }
    
    /**
     * Criar Price (preço)
     */
    public function createPrice($amount, $description, $currency = 'brl') {
        // ===== VALIDAÇÕES =====
        
        if ($amount <= 0) {
            throw new Exception('Valor deve ser maior que zero');
        }
        
        if (strlen($description) > 1000) {
            throw new Exception('Descrição muito longa (máximo 1000 caracteres)');
        }
        
        if (strlen($description) < 3) {
            throw new Exception('Descrição muito curta (mínimo 3 caracteres)');
        }
        
        // Converter para centavos
        $unit_amount = round($amount * 100);
        
        if ($unit_amount > 999999999) {
            throw new Exception('Valor muito alto (máximo R$ 9.999.999,99)');
        }
        
        $data = [
            'unit_amount' => $unit_amount,
            'currency' => strtolower($currency),
            'product_data[name]' => $description
        ];
        
        return $this->request('/prices', 'POST', $data);
    }
    
    /**
     * Criar Payment Link
     */
    public function createPaymentLink($price_id, $metadata = []) {
        // ===== VALIDAÇÕES =====
        
        if (empty($price_id)) {
            throw new Exception('Price ID não pode estar vazio');
        }
        
        if (!preg_match('/^price_[a-zA-Z0-9]+$/', $price_id)) {
            throw new Exception('Price ID inválido. Deve começar com "price_"');
        }
        
        // Validar e converter metadados
        $metadata_validated = [];
        foreach ($metadata as $key => $value) {
            // Validar chave
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                throw new Exception("Chave de metadado inválida: '$key'. Apenas letras, números e underscore");
            }
            
            // Converter valor para string
            $str_value = (string)$value;
            
            // Validar comprimento
            if (strlen($str_value) > 500) {
                throw new Exception("Valor de metadado '$key' muito longo (máximo 500 caracteres)");
            }
            
            $metadata_validated[$key] = $str_value;
        }
        
        $data = [
            'line_items[0][price]' => $price_id,
            'line_items[0][quantity]' => 1
        ];
        
        // Adicionar metadados validados
        foreach ($metadata_validated as $key => $value) {
            $data['metadata[' . $key . ']'] = $value;
        }
        
        return $this->request('/payment_links', 'POST', $data);
    }
    
    /**
     * Criar Payment Link Completo
     */
    public function createCompletePaymentLink($amount, $description, $metadata = []) {
        try {
            $this->log("=== INICIANDO CRIAÇÃO DE PAYMENT LINK ===");
            $this->log("Valor: R$ " . number_format($amount, 2, ',', '.'));
            $this->log("Descrição: " . $description);
            $this->log("Metadados: " . json_encode($metadata, JSON_PRETTY_PRINT));
            
            // 1. Criar Price
            $this->log("\n[1/2] Criando Price...");
            $price = $this->createPrice($amount, $description);
            $this->log("✓ Price criado: " . $price['id']);
            
            // 2. Criar Payment Link
            $this->log("\n[2/2] Criando Payment Link...");
            $payment_link = $this->createPaymentLink($price['id'], $metadata);
            $this->log("✓ Payment Link criado: " . $payment_link['id']);
            
            return [
                'success' => true,
                'payment_link_id' => $payment_link['id'],
                'payment_link_url' => $payment_link['url'],
                'price_id' => $price['id'],
                'amount' => $amount,
                'currency' => $payment_link['currency'],
                'metadata' => $metadata_validated ?? $metadata
            ];
            
        } catch (Exception $e) {
            $this->log("✗ ERRO: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Registrar log
     */
    private function log($message) {
        $this->log[] = $message;
        echo $message . "\n";
    }
    
    /**
     * Obter logs
     */
    public function getLogs() {
        return $this->log;
    }
    
    /**
     * Obter modo
     */
    public function getModo() {
        return $this->modo;
    }
}

// ===== EXECUTAR TESTES =====

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║         TESTE COMPLETO - STRIPE PAYMENT LINK                   ║\n";
echo "║         Para Royalties                                         ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

try {
    // Inicializar Stripe
    echo "📌 Inicializando Stripe...\n";
    $stripe = new StripeAPITest($STRIPE_SECRET_KEY, $STRIPE_WEBHOOK_SECRET);
    echo "✓ Modo: " . strtoupper($stripe->getModo()) . "\n\n";
    
    // Testar criação de Payment Link
    echo "📌 Testando criação de Payment Link para Royalties...\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    $resultado = $stripe->createCompletePaymentLink(
        $royalty_test['valor_royalties'],
        sprintf(
            "Royalties - %s | Período: %s a %s",
            $royalty_test['estabelecimento_nome'],
            date('d/m/Y', strtotime($royalty_test['periodo_inicial'])),
            date('d/m/Y', strtotime($royalty_test['periodo_final']))
        ),
        [
            'royalty_id' => (string)$royalty_test['royalty_id'],
            'estabelecimento_id' => (string)$royalty_test['estabelecimento_id'],
            'estabelecimento_nome' => $royalty_test['estabelecimento_nome'],
            'tipo' => 'royalty',
            'periodo' => $royalty_test['periodo_inicial'] . ' a ' . $royalty_test['periodo_final']
        ]
    );
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // Exibir resultado
    if ($resultado['success']) {
        echo "✅ SUCESSO!\n\n";
        echo "📊 Resultado:\n";
        echo "  • Payment Link ID: " . $resultado['payment_link_id'] . "\n";
        echo "  • URL: " . $resultado['payment_link_url'] . "\n";
        echo "  • Price ID: " . $resultado['price_id'] . "\n";
        echo "  • Valor: R$ " . number_format($resultado['amount'], 2, ',', '.') . "\n";
        echo "  • Moeda: " . strtoupper($resultado['currency']) . "\n";
        echo "  • Metadados: " . json_encode($resultado['metadata']) . "\n";
    } else {
        echo "❌ ERRO!\n";
        echo "  • Mensagem: " . $resultado['error'] . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERRO CRÍTICO: " . $e->getMessage() . "\n";
}

echo "\n\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║         TESTE FINALIZADO                                       ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
?>
