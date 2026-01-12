<?php
/**
 * ============================================
 * TESTE STRIPE COM BANCO DE DADOS - COMPLETO
 * ============================================
 * 
 * Script completo que:
 * 1. Conecta ao banco de dados
 * 2. Lê credenciais Stripe da tabela stripe_config
 * 3. Busca um royalty pendente
 * 4. Cria um Price no Stripe
 * 5. Cria um Payment Link no Stripe
 * 6. Atualiza o banco com o link gerado
 * 7. Exibe relatório completo
 * 
 * Uso: php test_stripe_com_banco_completo.php
 */

// ============================================
// CONFIGURAÇÕES
// ============================================

// Banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'inlaud99_choppontap');
define('DB_USER', 'inlaud99_admin');
define('DB_PASS', 'Admin259087@');
define('DB_CHARSET', 'utf8mb4');

// Stripe
define('STRIPE_API_URL', 'https://api.stripe.com/v1');
define('STRIPE_TIMEOUT', 30);

// Configuração
define('ESTABELECIMENTO_ID', 1);
define('ROYALTY_ID', null); // null = buscar o primeiro pendente

// ============================================
// CLASSE: StripeClient
// ============================================

class StripeClient {
    private $secret_key;
    private $logs = [];
    
    public function __construct($secret_key) {
        if (empty($secret_key)) {
            throw new Exception('Chave secreta do Stripe não fornecida');
        }
        
        $this->secret_key = $secret_key;
        $this->log('StripeClient inicializado', 'info');
    }
    
    /**
     * Fazer requisição à API Stripe
     */
    public function request($method, $endpoint, $data = []) {
        $url = STRIPE_API_URL . $endpoint;
        
        $this->log("Requisição: $method $endpoint", 'debug');
        
        $ch = curl_init();
        
        // Configurações básicas
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, STRIPE_TIMEOUT);
        curl_setopt($ch, CURLOPT_USERPWD, $this->secret_key . ':');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: PHP-StripeClient/1.0'
        ]);
        
        // Método HTTP
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            $this->log("Dados: " . json_encode($data), 'debug');
        } elseif ($method === 'GET') {
            if (!empty($data)) {
                $url .= '?' . http_build_query($data);
                curl_setopt($ch, CURLOPT_URL, $url);
            }
        }
        
        // Executar requisição
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        // Tratar erro CURL
        if ($curl_error) {
            $this->log("Erro CURL: $curl_error", 'error');
            throw new Exception("Erro CURL: $curl_error");
        }
        
        // Decodificar resposta
        $result = json_decode($response, true);
        
        if ($result === null) {
            $this->log("Resposta não é JSON válido: " . substr($response, 0, 200), 'error');
            throw new Exception("Resposta não é JSON válido");
        }
        
        $this->log("HTTP Code: $http_code", 'debug');
        
        // Tratar erro HTTP
        if ($http_code >= 400) {
            $error_msg = $result['error']['message'] ?? 'Erro desconhecido';
            $this->log("Erro Stripe ($http_code): $error_msg", 'error');
            throw new Exception("Erro Stripe ($http_code): $error_msg");
        }
        
        $this->log("Sucesso: " . ($result['id'] ?? 'OK'), 'success');
        
        return $result;
    }
    
    /**
     * Criar Price
     */
    public function createPrice($amount_cents, $currency, $product_name) {
        $data = [
            'unit_amount' => $amount_cents,
            'currency' => $currency,
            'product_data[name]' => $product_name
        ];
        
        return $this->request('POST', '/prices', $data);
    }
    
    /**
     * Criar Payment Link
     */
    public function createPaymentLink($price_id, $quantity = 1, $metadata = []) {
        $data = [
            'line_items[0][price]' => $price_id,
            'line_items[0][quantity]' => $quantity
        ];
        
        // Adicionar metadados
        foreach ($metadata as $key => $value) {
            $data["metadata[$key]"] = (string)$value;
        }
        
        return $this->request('POST', '/payment_links', $data);
    }
    
    /**
     * Adicionar log
     */
    private function log($message, $level = 'info') {
        $timestamp = date('Y-m-d H:i:s');
        $this->logs[] = [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message
        ];
    }
    
    /**
     * Obter logs
     */
    public function getLogs() {
        return $this->logs;
    }
}

// ============================================
// CLASSE: DatabaseManager
// ============================================

class DatabaseManager {
    private $conn;
    private $logs = [];
    
    public function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 30
                ]
            );
            $this->log('Conectado ao banco de dados', 'success');
        } catch (PDOException $e) {
            $this->log('Erro ao conectar: ' . $e->getMessage(), 'error');
            throw new Exception('Erro ao conectar ao banco: ' . $e->getMessage());
        }
    }
    
    /**
     * Buscar configuração Stripe
     */
    public function getStripeConfig($estabelecimento_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    id,
                    estabelecimento_id,
                    stripe_public_key,
                    stripe_secret_key,
                    stripe_webhook_secret,
                    modo,
                    ativo,
                    created_at,
                    updated_at
                FROM stripe_config
                WHERE estabelecimento_id = ? AND ativo = 1
                LIMIT 1
            ");
            
            $stmt->execute([$estabelecimento_id]);
            $config = $stmt->fetch();
            
            if (!$config) {
                $this->log("Nenhuma configuração Stripe encontrada para estabelecimento_id = $estabelecimento_id", 'warning');
                return null;
            }
            
            $this->log("Configuração Stripe encontrada (ID: {$config['id']})", 'success');
            return $config;
            
        } catch (PDOException $e) {
            $this->log('Erro ao buscar configuração: ' . $e->getMessage(), 'error');
            throw new Exception('Erro ao buscar configuração: ' . $e->getMessage());
        }
    }
    
    /**
     * Buscar royalty pendente
     */
    public function getRoyaltyPendente($estabelecimento_id, $royalty_id = null) {
        try {
            if ($royalty_id) {
                $stmt = $this->conn->prepare("
                    SELECT 
                        r.*,
                        e.name as estabelecimento_nome,
                        e.document as estabelecimento_document,
                        e.email_alerta as estabelecimento_email
                    FROM royalties r
                    JOIN estabelecimentos e ON r.estabelecimento_id = e.id
                    WHERE r.id = ? AND r.estabelecimento_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$royalty_id, $estabelecimento_id]);
            } else {
                $stmt = $this->conn->prepare("
                    SELECT 
                        r.*,
                        e.name as estabelecimento_nome,
                        e.document as estabelecimento_document,
                        e.email_alerta as estabelecimento_email
                    FROM royalties r
                    JOIN estabelecimentos e ON r.estabelecimento_id = e.id
                    WHERE r.estabelecimento_id = ? AND r.status = 'pendente'
                    ORDER BY r.id DESC
                    LIMIT 1
                ");
                $stmt->execute([$estabelecimento_id]);
            }
            
            $royalty = $stmt->fetch();
            
            if (!$royalty) {
                $this->log("Nenhum royalty pendente encontrado para estabelecimento_id = $estabelecimento_id", 'warning');
                return null;
            }
            
            $this->log("Royalty encontrado (ID: {$royalty['id']}, Valor: R$ " . number_format($royalty['valor_royalties'], 2, ',', '.') . ")", 'success');
            return $royalty;
            
        } catch (PDOException $e) {
            $this->log('Erro ao buscar royalty: ' . $e->getMessage(), 'error');
            throw new Exception('Erro ao buscar royalty: ' . $e->getMessage());
        }
    }
    
    /**
     * Atualizar royalty com link de pagamento
     */
    public function updateRoyaltyWithPaymentLink($royalty_id, $payment_link_url, $payment_link_id) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE royalties 
                SET 
                    payment_link_url = ?,
                    payment_link_id = ?,
                    status = 'link_gerado',
                    data_geracao_link = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $payment_link_url,
                $payment_link_id,
                $royalty_id
            ]);
            
            $affected_rows = $stmt->rowCount();
            
            if ($affected_rows > 0) {
                $this->log("Royalty atualizado com sucesso (ID: $royalty_id)", 'success');
                return true;
            } else {
                $this->log("Nenhuma linha foi atualizada (ID: $royalty_id)", 'warning');
                return false;
            }
            
        } catch (PDOException $e) {
            $this->log('Erro ao atualizar royalty: ' . $e->getMessage(), 'error');
            throw new Exception('Erro ao atualizar royalty: ' . $e->getMessage());
        }
    }
    
    /**
     * Adicionar log
     */
    private function log($message, $level = 'info') {
        $timestamp = date('Y-m-d H:i:s');
        $this->logs[] = [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message
        ];
    }
    
    /**
     * Obter logs
     */
    public function getLogs() {
        return $this->logs;
    }
}

// ============================================
// FUNÇÃO: Exibir Cabeçalho
// ============================================

function exibirCabecalho() {
    echo "\n";
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║     TESTE STRIPE COM BANCO DE DADOS - COMPLETO                ║\n";
    echo "║     Versão 2.0 - Com Tratamento de Erros                      ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n";
    echo "\n";
}

// ============================================
// FUNÇÃO: Exibir Seção
// ============================================

function exibirSecao($numero, $titulo) {
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "ETAPA $numero: $titulo\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
}

// ============================================
// FUNÇÃO: Exibir Resultado
// ============================================

function exibirResultado($sucesso, $mensagem, $detalhes = []) {
    $icone = $sucesso ? '✅' : '❌';
    echo "$icone $mensagem\n";
    
    foreach ($detalhes as $chave => $valor) {
        if (is_array($valor)) {
            echo "   $chave:\n";
            foreach ($valor as $k => $v) {
                echo "      $k: $v\n";
            }
        } else {
            echo "   $chave: $valor\n";
        }
    }
}

// ============================================
// FUNÇÃO: Formatar Valor
// ============================================

function formatarValor($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

// ============================================
// FUNÇÃO: Formatar Data
// ============================================

function formatarData($data) {
    return date('d/m/Y', strtotime($data));
}

// ============================================
// FUNÇÃO: Exibir Logs
// ============================================

function exibirLogs($titulo, $logs) {
    if (empty($logs)) {
        return;
    }
    
    echo "\n📋 $titulo:\n";
    
    foreach ($logs as $log) {
        $timestamp = $log['timestamp'];
        $level = $log['level'];
        $message = $log['message'];
        
        $icone = match($level) {
            'success' => '✅',
            'error' => '❌',
            'warning' => '⚠️',
            'debug' => '🔍',
            default => 'ℹ️'
        };
        
        echo "   [$timestamp] $icone $message\n";
    }
}

// ============================================
// MAIN - EXECUTAR TESTE
// ============================================

try {
    exibirCabecalho();
    
    // ===== ETAPA 1: Conectar ao Banco =====
    
    exibirSecao(1, 'Conectar ao Banco de Dados');
    
    $db = new DatabaseManager();
    exibirResultado(true, 'Conectado ao banco de dados');
    
    // ===== ETAPA 2: Buscar Configuração Stripe =====
    
    exibirSecao(2, 'Buscar Configuração Stripe');
    
    $config = $db->getStripeConfig(ESTABELECIMENTO_ID);
    
    if (!$config) {
        exibirResultado(false, 'Configuração Stripe não encontrada', [
            'Ação' => 'Acesse Admin > Stripe Config e configure as credenciais'
        ]);
        exit(1);
    }
    
    exibirResultado(true, 'Configuração Stripe encontrada', [
        'ID' => $config['id'],
        'Estabelecimento ID' => $config['estabelecimento_id'],
        'Modo' => strtoupper($config['modo']),
        'Ativo' => $config['ativo'] ? 'Sim' : 'Não',
        'Public Key' => substr($config['stripe_public_key'], 0, 20) . '...',
        'Secret Key' => substr($config['stripe_secret_key'], 0, 20) . '...',
        'Tamanho Secret Key' => strlen($config['stripe_secret_key']) . ' caracteres'
    ]);
    
    // ===== ETAPA 3: Validar Credenciais =====
    
    exibirSecao(3, 'Validar Credenciais Stripe');
    
    $secret_key = $config['stripe_secret_key'];
    
    if (empty($secret_key)) {
        exibirResultado(false, 'Chave secreta vazia');
        exit(1);
    }
    
    if (strlen($secret_key) < 50) {
        exibirResultado(false, 'Chave secreta muito curta (truncada?)', [
            'Tamanho' => strlen($secret_key) . ' caracteres',
            'Esperado' => '~91 caracteres',
            'Ação' => 'Copie a chave completa do Stripe Dashboard'
        ]);
        exit(1);
    }
    
    if (strpos($secret_key, 'sk_test_') !== 0 && strpos($secret_key, 'sk_live_') !== 0) {
        exibirResultado(false, 'Chave secreta em formato inválido', [
            'Deve começar com' => 'sk_test_ ou sk_live_',
            'Começa com' => substr($secret_key, 0, 10)
        ]);
        exit(1);
    }
    
    exibirResultado(true, 'Credenciais validadas', [
        'Chave' => substr($secret_key, 0, 10) . '...' . substr($secret_key, -10),
        'Modo' => strpos($secret_key, 'sk_test_') === 0 ? 'TEST' : 'LIVE',
        'Tamanho' => strlen($secret_key) . ' caracteres'
    ]);
    
    // ===== ETAPA 4: Inicializar Cliente Stripe =====
    
    exibirSecao(4, 'Inicializar Cliente Stripe');
    
    $stripe = new StripeClient($secret_key);
    exibirResultado(true, 'Cliente Stripe inicializado');
    
    // ===== ETAPA 5: Buscar Royalty Pendente =====
    
    exibirSecao(5, 'Buscar Royalty Pendente');
    
    $royalty = $db->getRoyaltyPendente(ESTABELECIMENTO_ID, ROYALTY_ID);
    
    if (!$royalty) {
        exibirResultado(false, 'Nenhum royalty pendente encontrado', [
            'Ação' => 'Crie um novo royalty em Financeiro > Royalties'
        ]);
        exit(1);
    }
    
    exibirResultado(true, 'Royalty encontrado', [
        'ID' => $royalty['id'],
        'Estabelecimento' => $royalty['estabelecimento_nome'],
        'Período' => formatarData($royalty['periodo_inicial']) . ' a ' . formatarData($royalty['periodo_final']),
        'Faturamento Bruto' => formatarValor($royalty['valor_faturamento_bruto']),
        'Royalties (7%)' => formatarValor($royalty['valor_royalties']),
        'Status' => $royalty['status'],
        'E-mail' => $royalty['email_cobranca']
    ]);
    
    // ===== ETAPA 6: Testar Conexão com Stripe =====
    
    exibirSecao(6, 'Testar Conexão com Stripe API');
    
    try {
        $stripe->request('GET', '/charges', []);
        exibirResultado(true, 'Conexão com Stripe funcionando');
    } catch (Exception $e) {
        exibirResultado(false, 'Falha ao conectar com Stripe', [
            'Erro' => $e->getMessage()
        ]);
        exit(1);
    }
    
    // ===== ETAPA 7: Criar Price =====
    
    exibirSecao(7, 'Criar Price no Stripe');
    
    $descricao = sprintf(
        "Royalties - %s | Período: %s a %s",
        $royalty['estabelecimento_nome'],
        formatarData($royalty['periodo_inicial']),
        formatarData($royalty['periodo_final'])
    );
    
    $amount_cents = round($royalty['valor_royalties'] * 100);
    
    echo "📋 Dados do Price:\n";
    echo "   Valor: " . formatarValor($royalty['valor_royalties']) . "\n";
    echo "   Centavos: $amount_cents\n";
    echo "   Moeda: BRL\n";
    echo "   Descrição: " . substr($descricao, 0, 60) . "...\n\n";
    
    try {
        $price_result = $stripe->createPrice($amount_cents, 'brl', $descricao);
        $price_id = $price_result['id'];
        
        exibirResultado(true, 'Price criado com sucesso', [
            'Price ID' => $price_id,
            'Valor' => formatarValor($royalty['valor_royalties']),
            'Status' => $price_result['active'] ? 'Ativo' : 'Inativo'
        ]);
    } catch (Exception $e) {
        exibirResultado(false, 'Falha ao criar Price', [
            'Erro' => $e->getMessage()
        ]);
        exit(1);
    }
    
    // ===== ETAPA 8: Criar Payment Link =====
    
    exibirSecao(8, 'Criar Payment Link no Stripe');
    
    $metadata = [
        'royalty_id' => $royalty['id'],
        'estabelecimento_id' => $royalty['estabelecimento_id'],
        'estabelecimento_nome' => $royalty['estabelecimento_nome'],
        'tipo' => 'royalty',
        'periodo' => formatarData($royalty['periodo_inicial']) . ' a ' . formatarData($royalty['periodo_final'])
    ];
    
    echo "📋 Dados do Payment Link:\n";
    echo "   Price ID: $price_id\n";
    echo "   Quantidade: 1\n";
    echo "   Metadados: " . count($metadata) . " campos\n\n";
    
    try {
        $payment_link_result = $stripe->createPaymentLink($price_id, 1, $metadata);
        $payment_link_id = $payment_link_result['id'];
        $payment_link_url = $payment_link_result['url'];
        
        exibirResultado(true, 'Payment Link criado com sucesso', [
            'Payment Link ID' => $payment_link_id,
            'URL' => $payment_link_url,
            'Status' => $payment_link_result['active'] ? 'Ativo' : 'Inativo'
        ]);
    } catch (Exception $e) {
        exibirResultado(false, 'Falha ao criar Payment Link', [
            'Erro' => $e->getMessage()
        ]);
        exit(1);
    }
    
    // ===== ETAPA 9: Atualizar Banco de Dados =====
    
    exibirSecao(9, 'Atualizar Banco de Dados');
    
    try {
        $updated = $db->updateRoyaltyWithPaymentLink(
            $royalty['id'],
            $payment_link_url,
            $payment_link_id
        );
        
        if ($updated) {
            exibirResultado(true, 'Banco de dados atualizado com sucesso', [
                'Royalty ID' => $royalty['id'],
                'Payment Link ID' => $payment_link_id,
                'Novo Status' => 'link_gerado'
            ]);
        } else {
            exibirResultado(false, 'Falha ao atualizar banco de dados');
            exit(1);
        }
    } catch (Exception $e) {
        exibirResultado(false, 'Erro ao atualizar banco de dados', [
            'Erro' => $e->getMessage()
        ]);
        exit(1);
    }
    
    // ===== RESULTADO FINAL =====
    
    echo "\n╔════════════════════════════════════════════════════════════════╗\n";
    echo "║         ✅ TESTE CONCLUÍDO COM SUCESSO!                        ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n";
    
    echo "\n📊 Resumo Final:\n";
    echo "   ✅ Banco de dados conectado\n";
    echo "   ✅ Configuração Stripe validada\n";
    echo "   ✅ Conexão com Stripe funcionando\n";
    echo "   ✅ Royalty encontrado (ID: {$royalty['id']})\n";
    echo "   ✅ Price criado: $price_id\n";
    echo "   ✅ Payment Link criado: $payment_link_id\n";
    echo "   ✅ Banco de dados atualizado\n";
    
    echo "\n💳 Link de Pagamento Gerado:\n";
    echo "   $payment_link_url\n";
    
    echo "\n📋 Dados do Royalty:\n";
    echo "   Estabelecimento: {$royalty['estabelecimento_nome']}\n";
    echo "   Valor: " . formatarValor($royalty['valor_royalties']) . "\n";
    echo "   Período: " . formatarData($royalty['periodo_inicial']) . " a " . formatarData($royalty['periodo_final']) . "\n";
    echo "   Status: link_gerado\n";
    echo "   E-mail: {$royalty['email_cobranca']}\n";
    
    echo "\n✨ Tudo funcionando perfeitamente!\n";
    
    // Exibir logs
    exibirLogs('Logs do Banco de Dados', $db->getLogs());
    exibirLogs('Logs do Stripe', $stripe->getLogs());
    
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "PRÓXIMOS PASSOS:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    echo "1️⃣  Copie o link e teste no navegador\n";
    echo "2️⃣  Use as credenciais de teste do Stripe para fazer um pagamento\n";
    echo "3️⃣  Verifique se o webhook foi recebido no Stripe\n";
    echo "4️⃣  Implemente este código na classe StripeAPI do seu projeto\n";
    echo "5️⃣  Teste a geração de link via admin do seu sistema\n\n";
    
} catch (Exception $e) {
    echo "\n❌ Erro Fatal: " . $e->getMessage() . "\n";
    echo "\nStack Trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

?>
