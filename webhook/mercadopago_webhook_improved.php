<?php
/**
 * ========================================
 * WEBHOOK MERCADO PAGO - VERSÃO MELHORADA
 * Recebe notificações de pagamento do Mercado Pago
 * Integração com tabela ORDER
 * Versão: 2.0
 * Data: 2026-01-07
 * ========================================
 */

// ========================================
// CONFIGURAÇÕES INICIAIS
// ========================================

// Ativar log de erros
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/mercadopago_webhook.log');

// Incluir configurações e dependências
require_once __DIR__ . '/../includes/config.php';

// ========================================
// CONSTANTES
// ========================================

const LOG_FILE = __DIR__ . '/../logs/mercadopago_webhook.log';
const ERROR_LOG_FILE = __DIR__ . '/../logs/mercadopago_webhook_error.log';
const MAX_RETRY_ATTEMPTS = 3;
const RETRY_DELAY = 5; // segundos

// ========================================
// MAPEAMENTO DE STATUS
// ========================================

$STATUS_MAP = [
    'approved'      => 'pago',
    'pending'       => 'pendente',
    'in_process'    => 'processando',
    'rejected'      => 'cancelado',
    'cancelled'     => 'cancelado',
    'refunded'      => 'cancelado',
    'charged_back'  => 'cancelado'
];

// ========================================
// FUNÇÕES AUXILIARES
// ========================================

/**
 * Log estruturado de eventos
 */
function logWebhook($level, $message, $data = null, $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message";
    
    if ($data !== null) {
        $logMessage .= "\n" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    if (!empty($context)) {
        $logMessage .= "\nContexto: " . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    $logMessage .= "\n" . str_repeat("-", 80) . "\n";
    
    file_put_contents(LOG_FILE, $logMessage, FILE_APPEND);
}

/**
 * Log de erros críticos
 */
function logError($message, $exception = null) {
    $timestamp = date('Y-m-d H:i:s');
    $errorMessage = "[$timestamp] ERRO: $message";
    
    if ($exception instanceof Exception) {
        $errorMessage .= "\n" . $exception->getMessage();
        $errorMessage .= "\nArquivo: " . $exception->getFile();
        $errorMessage .= "\nLinha: " . $exception->getLine();
        $errorMessage .= "\nTrace:\n" . $exception->getTraceAsString();
    }
    
    $errorMessage .= "\n" . str_repeat("-", 80) . "\n";
    
    file_put_contents(ERROR_LOG_FILE, $errorMessage, FILE_APPEND);
}

/**
 * Responder ao webhook
 */
function respondWebhook($statusCode = 200, $message = 'OK', $data = []) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    
    $response = [
        'status' => $statusCode,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Validar assinatura do webhook (HMAC)
 */
function validateWebhookSignature($payload, $signature, $secret) {
    if (empty($signature) || empty($secret)) {
        return false;
    }
    
    // Gerar HMAC SHA256 do payload
    $expectedSignature = hash_hmac('sha256', $payload, $secret);
    
    // Comparação segura (timing-safe)
    return hash_equals($expectedSignature, $signature);
}

/**
 * Consultar detalhes do pagamento na API do Mercado Pago
 */
function fetchPaymentDetails($paymentId, $accessToken) {
    try {
        $url = "https://api.mercadopago.com/v1/payments/$paymentId";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $accessToken",
                "Content-Type: application/json",
                "User-Agent: ChoppOn-Webhook/2.0"
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception("cURL Error: $curlError");
        }
        
        if ($httpCode !== 200) {
            throw new Exception("API retornou HTTP $httpCode: $response");
        }
        
        $payment = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Erro ao decodificar JSON: " . json_last_error_msg());
        }
        
        return [
            'success' => true,
            'data' => $payment
        ];
        
    } catch (Exception $e) {
        logError("Erro ao consultar pagamento $paymentId", $e);
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Buscar pedido pelo external_reference ou payment_id
 */
function findOrder($conn, $externalReference = null, $paymentId = null, $estabelecimentoId = null) {
    try {
        if ($externalReference) {
            // Tentar buscar por external_reference (formato: order_ID)
            if (preg_match('/order_(\d+)/i', $externalReference, $matches)) {
                $orderId = $matches[1];
                
                $stmt = $conn->prepare("
                    SELECT * FROM `order` 
                    WHERE id = ? 
                    AND estabelecimento_id = ?
                    LIMIT 1
                ");
                
                $stmt->bind_param("ii", $orderId, $estabelecimentoId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    return $result->fetch_assoc();
                }
            }
        }
        
        // Se não encontrou, tentar buscar por checkout_id (payment_id)
        if ($paymentId) {
            $stmt = $conn->prepare("
                SELECT * FROM `order` 
                WHERE checkout_id = ? 
                AND estabelecimento_id = ?
                LIMIT 1
            ");
            
            $stmt->bind_param("si", $paymentId, $estabelecimentoId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                return $result->fetch_assoc();
            }
        }
        
        return null;
        
    } catch (Exception $e) {
        logError("Erro ao buscar pedido", $e);
        return null;
    }
}

/**
 * Atualizar status do pedido
 */
function updateOrderStatus($conn, $orderId, $newStatus, $paymentData = []) {
    try {
        $timestamp = date('Y-m-d H:i:s');
        
        // Preparar dados para atualização
        $updateData = [
            'checkout_status' => $newStatus,
            'updated_at' => $timestamp
        ];
        
        // Se aprovado, registrar data de aprovação
        if ($newStatus === 'pago') {
            $updateData['status_liberacao'] = 'FINISHED';
        }
        
        // Construir query de atualização
        $updates = [];
        $params = [];
        $types = '';
        
        foreach ($updateData as $field => $value) {
            $updates[] = "`$field` = ?";
            $params[] = $value;
            $types .= 's';
        }
        
        $params[] = $orderId;
        $types .= 'i';
        
        $query = "UPDATE `order` SET " . implode(", ", $updates) . " WHERE id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $result = $stmt->execute();
        
        if (!$result) {
            throw new Exception("Erro ao executar query: " . $stmt->error);
        }
        
        return true;
        
    } catch (Exception $e) {
        logError("Erro ao atualizar status do pedido $orderId", $e);
        return false;
    }
}

/**
 * Registrar log de webhook no banco de dados
 */
function logWebhookToDatabase($conn, $orderId, $estabelecimentoId, $paymentId, $status, $requestData, $responseData) {
    try {
        // Criar tabela de logs se não existir
        $createTableQuery = "
            CREATE TABLE IF NOT EXISTS `mercadopago_webhook_logs` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `order_id` BIGINT UNSIGNED,
                `estabelecimento_id` BIGINT UNSIGNED,
                `payment_id` VARCHAR(255),
                `status` VARCHAR(50),
                `request_data` LONGTEXT,
                `response_data` LONGTEXT,
                `ip_address` VARCHAR(45),
                `user_agent` VARCHAR(255),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_order_id (order_id),
                INDEX idx_payment_id (payment_id),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $conn->query($createTableQuery);
        
        // Inserir log
        $stmt = $conn->prepare("
            INSERT INTO `mercadopago_webhook_logs` 
            (order_id, estabelecimento_id, payment_id, status, request_data, response_data, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255);
        $requestJson = json_encode($requestData, JSON_UNESCAPED_UNICODE);
        $responseJson = json_encode($responseData, JSON_UNESCAPED_UNICODE);
        
        $stmt->bind_param(
            "iissssss",
            $orderId,
            $estabelecimentoId,
            $paymentId,
            $status,
            $requestJson,
            $responseJson,
            $ipAddress,
            $userAgent
        );
        
        $stmt->execute();
        
        return true;
        
    } catch (Exception $e) {
        logError("Erro ao registrar webhook no banco de dados", $e);
        return false;
    }
}

/**
 * Enviar notificação (Telegram, Email, etc)
 */
function sendNotification($conn, $orderId, $estabelecimentoId, $status, $paymentData = []) {
    try {
        // Implementar notificações conforme necessário
        // Exemplo: Telegram, Email, etc
        
        logWebhook('INFO', "Notificação enviada para pedido $orderId com status $status");
        
        return true;
        
    } catch (Exception $e) {
        logError("Erro ao enviar notificação", $e);
        return false;
    }
}

// ========================================
// PROCESSAMENTO DO WEBHOOK
// ========================================

try {
    // Registrar entrada
    logWebhook('INFO', 'Webhook recebido', [
        'method' => $_SERVER['REQUEST_METHOD'],
        'headers' => getallheaders(),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    // Validar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        logWebhook('WARN', 'Método HTTP inválido: ' . $_SERVER['REQUEST_METHOD']);
        respondWebhook(405, 'Method Not Allowed');
    }
    
    // Obter payload
    $payload = file_get_contents('php://input');
    $data = json_decode($payload, true);
    
    if (!$data) {
        logWebhook('WARN', 'Payload JSON inválido');
        respondWebhook(400, 'Invalid JSON payload');
    }
    
    logWebhook('DEBUG', 'Dados do webhook', $data);
    
    // Validar tipo de notificação
    $type = $data['type'] ?? $data['topic'] ?? null;
    $action = $data['action'] ?? null;
    
    if (!$type && !$action) {
        logWebhook('WARN', 'Tipo de notificação não identificado');
        respondWebhook(400, 'Notification type not identified');
    }
    
    // Processar apenas notificações de pagamento
    if (!in_array($type, ['payment', 'merchant_order']) && !in_array($action, ['payment.created', 'payment.updated'])) {
        logWebhook('INFO', "Tipo de notificação não processado: $type / $action");
        respondWebhook(200, 'Notification type not processed');
    }
    
    // Obter ID do pagamento
    $paymentId = $data['data']['id'] ?? $data['id'] ?? null;
    
    if (!$paymentId) {
        logWebhook('WARN', 'Payment ID não encontrado no webhook');
        respondWebhook(400, 'Payment ID not found');
    }
    
    logWebhook('INFO', "Payment ID identificado: $paymentId");
    
    // Conectar ao banco de dados
    $conn = getDBConnection();
    
    if (!$conn) {
        logError('Falha ao conectar ao banco de dados');
        respondWebhook(500, 'Database connection failed');
    }
    
    // Buscar configuração do Mercado Pago
    $stmt = $conn->prepare("
        SELECT * FROM `mercadopago_config` 
        WHERE status = 1 
        LIMIT 1
    ");
    
    if (!$stmt->execute()) {
        throw new Exception("Erro ao executar query: " . $stmt->error);
    }
    
    $configResult = $stmt->get_result();
    
    if ($configResult->num_rows === 0) {
        logError('Configuração do Mercado Pago não encontrada');
        respondWebhook(500, 'Mercado Pago not configured');
    }
    
    $config = $configResult->fetch_assoc();
    $accessToken = $config['access_token'];
    $webhookSecret = $config['webhook_secret'];
    $estabelecimentoId = $config['estabelecimento_id'];
    
    logWebhook('DEBUG', "Configuração carregada para estabelecimento $estabelecimentoId");
    
    // ========================================
    // VALIDAÇÃO DE SEGURANÇA (HMAC)
    // ========================================
    
    // Verificar assinatura do webhook se disponível
    $signature = $_SERVER['HTTP_X_MERCADOPAGO_SIGNATURE'] ?? null;
    
    if ($signature && $webhookSecret) {
        logWebhook('DEBUG', 'Validando assinatura HMAC do webhook');
        
        if (!validateWebhookSignature($payload, $signature, $webhookSecret)) {
            logWebhook('WARN', 'Assinatura HMAC inválida', [
                'received_signature' => $signature,
                'payload_length' => strlen($payload)
            ]);
            respondWebhook(401, 'Invalid webhook signature');
        }
        
        logWebhook('DEBUG', 'Assinatura HMAC validada com sucesso');
    } else {
        logWebhook('WARN', 'Webhook sem assinatura HMAC (validação desabilitada)');
    }
    
    // ========================================
    // CONSULTAR DETALHES DO PAGAMENTO
    // ========================================
    
    logWebhook('INFO', "Consultando detalhes do pagamento $paymentId na API do Mercado Pago");
    
    $paymentDetails = fetchPaymentDetails($paymentId, $accessToken);
    
    if (!$paymentDetails['success']) {
        logError("Falha ao consultar pagamento: " . $paymentDetails['error']);
        respondWebhook(500, 'Failed to fetch payment details');
    }
    
    $payment = $paymentDetails['data'];
    
    logWebhook('DEBUG', 'Detalhes do pagamento obtidos', $payment);
    
    // ========================================
    // EXTRAIR INFORMAÇÕES DO PAGAMENTO
    // ========================================
    
    $paymentStatus = $payment['status'] ?? 'unknown';
    $externalReference = $payment['external_reference'] ?? null;
    $amount = $payment['transaction_amount'] ?? 0;
    $payerEmail = $payment['payer']['email'] ?? '';
    $paymentMethod = $payment['payment_method_id'] ?? 'unknown';
    
    logWebhook('INFO', 'Informações do pagamento extraídas', [
        'status' => $paymentStatus,
        'external_reference' => $externalReference,
        'amount' => $amount,
        'payer_email' => $payerEmail,
        'payment_method' => $paymentMethod
    ]);
    
    // Mapear status
    global $STATUS_MAP;
    $newStatus = $STATUS_MAP[$paymentStatus] ?? 'pendente';
    
    logWebhook('INFO', "Status mapeado: $paymentStatus -> $newStatus");
    
    // ========================================
    // BUSCAR PEDIDO NO BANCO DE DADOS
    // ========================================
    
    logWebhook('INFO', 'Buscando pedido no banco de dados');
    
    $order = findOrder($conn, $externalReference, $paymentId, $estabelecimentoId);
    
    if (!$order) {
        logWebhook('WARN', 'Pedido não encontrado', [
            'external_reference' => $externalReference,
            'payment_id' => $paymentId,
            'estabelecimento_id' => $estabelecimentoId
        ]);
        
        // Responder com sucesso mesmo sem encontrar pedido
        // (pode ser um pagamento antigo ou de outro sistema)
        respondWebhook(200, 'Payment processed but order not found');
    }
    
    $orderId = $order['id'];
    
    logWebhook('INFO', "Pedido encontrado: ID $orderId", [
        'current_status' => $order['checkout_status'],
        'valor' => $order['valor'],
        'created_at' => $order['created_at']
    ]);
    
    // ========================================
    // ATUALIZAR STATUS DO PEDIDO
    // ========================================
    
    logWebhook('INFO', "Atualizando status do pedido $orderId para $newStatus");
    
    $updateSuccess = updateOrderStatus($conn, $orderId, $newStatus, $payment);
    
    if (!$updateSuccess) {
        logError("Falha ao atualizar status do pedido $orderId");
        respondWebhook(500, 'Failed to update order status');
    }
    
    logWebhook('INFO', "Status do pedido $orderId atualizado com sucesso para $newStatus");
    
    // ========================================
    // REGISTRAR LOG NO BANCO DE DADOS
    // ========================================
    
    logWebhookToDatabase(
        $conn,
        $orderId,
        $estabelecimentoId,
        $paymentId,
        $newStatus,
        $data,
        $payment
    );
    
    // ========================================
    // ENVIAR NOTIFICAÇÕES
    // ========================================
    
    sendNotification($conn, $orderId, $estabelecimentoId, $newStatus, $payment);
    
    // ========================================
    // RESPONDER COM SUCESSO
    // ========================================
    
    logWebhook('INFO', "Webhook processado com sucesso para pedido $orderId");
    
    respondWebhook(200, 'Payment processed successfully', [
        'order_id' => $orderId,
        'payment_id' => $paymentId,
        'status' => $newStatus
    ]);
    
} catch (Exception $e) {
    logError('Erro não tratado ao processar webhook', $e);
    respondWebhook(500, 'Internal server error');
}

?>
