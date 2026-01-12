<?php
/**
 * ============================================
 * TESTE STRIPE - DEMONSTRAÇÃO
 * ============================================
 * 
 * Este script demonstra como criar um payment link
 * usando as credenciais reais do seu Stripe
 * 
 * INSTRUÇÕES:
 * 1. Copie as credenciais da tabela stripe_config
 * 2. Cole nos campos abaixo
 * 3. Execute: php test_stripe_demo.php
 */

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║         TESTE STRIPE - DEMONSTRAÇÃO                           ║\n";
echo "║         Com Credenciais Reais                                 ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// ===== CONFIGURAÇÕES - COPIE DO BANCO =====

// Conforme visto nas imagens:
$STRIPE_SECRET_KEY = 'sk_test_51Sab57C86LvbvOYmQpOK3Ep5jEno2uL6BiHRbQgvSMizdGhqHZBMxVMw82rOhm3MuwZWdMqS00BKU6n08p';
$STRIPE_PUBLIC_KEY = 'pk_test_51Sab57C86LvbvOYm9R2JCrekUS4jDSBZvSmS6a53i3k3ESXqIgC1iMJ37w1115573X17VSiHf';
$STRIPE_WEBHOOK_SECRET = 'whsec_F7VjyrJ4h5EkSMizdGhqHZBMxVMw82rO';

// Dados do royalty
$royalty_data = [
    'royalty_id' => '1',
    'estabelecimento_id' => '1',
    'estabelecimento_nome' => 'Chopp On Tap',
    'valor' => 494.98,  // R$ 494,98 (7% de R$ 7.071,20)
    'periodo_inicial' => '2025-12-04',
    'periodo_final' => '2025-12-04',
    'email' => 'asoaresbh@gmail.com'
];

// ===== FUNÇÃO: Fazer Requisição CURL =====

function fazerRequisicaoStripe($endpoint, $method = 'POST', $data = [], $secret_key = '') {
    $url = 'https://api.stripe.com/v1' . $endpoint;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $secret_key . ':');
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
        return [
            'success' => false,
            'error' => $curl_error,
            'http_code' => $http_code
        ];
    }
    
    $result = json_decode($response, true);
    
    if ($result === null) {
        return [
            'success' => false,
            'error' => 'Resposta não é JSON válido',
            'http_code' => $http_code,
            'response' => $response
        ];
    }
    
    if ($http_code >= 400) {
        $error_message = $result['error']['message'] ?? 'Erro desconhecido';
        return [
            'success' => false,
            'error' => $error_message,
            'http_code' => $http_code,
            'result' => $result
        ];
    }
    
    return [
        'success' => true,
        'http_code' => $http_code,
        'result' => $result
    ];
}

// ===== TESTE 1: Validar Credenciais =====

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TESTE 1: Validar Credenciais\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

if (empty($STRIPE_SECRET_KEY) || strlen($STRIPE_SECRET_KEY) < 20) {
    echo "❌ Chave secreta não está configurada ou é muito curta\n";
    echo "   Comprimento: " . strlen($STRIPE_SECRET_KEY) . " caracteres\n";
    exit(1);
}

if (strpos($STRIPE_SECRET_KEY, 'sk_test_') !== 0 && strpos($STRIPE_SECRET_KEY, 'sk_live_') !== 0) {
    echo "❌ Chave secreta em formato inválido\n";
    echo "   Deve começar com 'sk_test_' ou 'sk_live_'\n";
    exit(1);
}

$modo = strpos($STRIPE_SECRET_KEY, 'sk_test_') === 0 ? 'TEST' : 'LIVE';

echo "✅ Chave secreta válida\n";
echo "   Modo: $modo\n";
echo "   Comprimento: " . strlen($STRIPE_SECRET_KEY) . " caracteres\n";
echo "   Primeiros 20 chars: " . substr($STRIPE_SECRET_KEY, 0, 20) . "...\n";

// ===== TESTE 2: Testar Conexão com Stripe =====

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TESTE 2: Testar Conexão com Stripe\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "📤 Conectando a https://api.stripe.com/v1/charges...\n";

$teste_conexao = fazerRequisicaoStripe('/charges', 'GET', [], $STRIPE_SECRET_KEY);

if (!$teste_conexao['success']) {
    echo "❌ Falha ao conectar com Stripe\n";
    echo "   Erro: " . $teste_conexao['error'] . "\n";
    exit(1);
}

echo "✅ Conexão com Stripe funcionando\n";
echo "   HTTP Code: " . $teste_conexao['http_code'] . "\n";

// ===== TESTE 3: Criar Price =====

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TESTE 3: Criar Price\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$descricao = sprintf(
    "Royalties - %s | Período: %s a %s",
    $royalty_data['estabelecimento_nome'],
    date('d/m/Y', strtotime($royalty_data['periodo_inicial'])),
    date('d/m/Y', strtotime($royalty_data['periodo_final']))
);

$price_data = [
    'unit_amount' => round($royalty_data['valor'] * 100),  // Converter para centavos
    'currency' => 'brl',
    'product_data[name]' => $descricao
];

echo "📋 Dados do Price:\n";
echo "   Valor: R$ " . number_format($royalty_data['valor'], 2, ',', '.') . "\n";
echo "   Centavos: " . $price_data['unit_amount'] . "\n";
echo "   Moeda: " . $price_data['currency'] . "\n";
echo "   Descrição: " . substr($descricao, 0, 60) . "...\n\n";

echo "📤 Criando Price...\n";

$price_result = fazerRequisicaoStripe('/prices', 'POST', $price_data, $STRIPE_SECRET_KEY);

if (!$price_result['success']) {
    echo "❌ Falha ao criar Price\n";
    echo "   Erro: " . $price_result['error'] . "\n";
    echo "   HTTP Code: " . $price_result['http_code'] . "\n";
    exit(1);
}

$price_id = $price_result['result']['id'];
echo "✅ Price criado com sucesso\n";
echo "   Price ID: $price_id\n";

// ===== TESTE 4: Criar Payment Link =====

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TESTE 4: Criar Payment Link\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$payment_link_data = [
    'line_items[0][price]' => $price_id,
    'line_items[0][quantity]' => 1,
    'metadata[royalty_id]' => $royalty_data['royalty_id'],
    'metadata[estabelecimento_id]' => $royalty_data['estabelecimento_id'],
    'metadata[estabelecimento_nome]' => $royalty_data['estabelecimento_nome'],
    'metadata[tipo]' => 'royalty',
    'metadata[periodo]' => date('d/m/Y', strtotime($royalty_data['periodo_inicial'])) . ' a ' . date('d/m/Y', strtotime($royalty_data['periodo_final']))
];

echo "📋 Dados do Payment Link:\n";
echo "   Price ID: $price_id\n";
echo "   Quantidade: 1\n";
echo "   Metadados: " . (count($payment_link_data) - 2) . " campos\n\n";

echo "📤 Criando Payment Link...\n";

$payment_link_result = fazerRequisicaoStripe('/payment_links', 'POST', $payment_link_data, $STRIPE_SECRET_KEY);

if (!$payment_link_result['success']) {
    echo "❌ Falha ao criar Payment Link\n";
    echo "   Erro: " . $payment_link_result['error'] . "\n";
    echo "   HTTP Code: " . $payment_link_result['http_code'] . "\n";
    exit(1);
}

$payment_link_id = $payment_link_result['result']['id'];
$payment_link_url = $payment_link_result['result']['url'];

echo "✅ Payment Link criado com sucesso\n";
echo "   Payment Link ID: $payment_link_id\n";
echo "   URL: $payment_link_url\n";

// ===== RESULTADO FINAL =====

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║         ✅ TODOS OS TESTES PASSARAM COM SUCESSO!               ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";

echo "\n📊 Resumo:\n";
echo "   ✅ Chave secreta válida\n";
echo "   ✅ Conexão com Stripe funcionando\n";
echo "   ✅ Price criado: $price_id\n";
echo "   ✅ Payment Link criado: $payment_link_id\n";

echo "\n💳 Link de Pagamento:\n";
echo "   " . $payment_link_url . "\n";

echo "\n📋 Dados do Royalty:\n";
echo "   Estabelecimento: " . $royalty_data['estabelecimento_nome'] . "\n";
echo "   Valor: R$ " . number_format($royalty_data['valor'], 2, ',', '.') . "\n";
echo "   Período: " . date('d/m/Y', strtotime($royalty_data['periodo_inicial'])) . " a " . date('d/m/Y', strtotime($royalty_data['periodo_final'])) . "\n";
echo "   Royalty ID: " . $royalty_data['royalty_id'] . "\n";
echo "   E-mail: " . $royalty_data['email'] . "\n";

echo "\n✨ O link está pronto para ser enviado ao cliente!\n";

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "PRÓXIMOS PASSOS:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "1️⃣  Copie o link e teste no navegador\n";
echo "2️⃣  Use as credenciais de teste do Stripe para fazer um pagamento\n";
echo "3️⃣  Verifique se o webhook foi recebido no Stripe\n";
echo "4️⃣  Implemente este código na classe StripeAPI do seu projeto\n";
echo "5️⃣  Teste a geração de link via admin do seu sistema\n\n";

?>
