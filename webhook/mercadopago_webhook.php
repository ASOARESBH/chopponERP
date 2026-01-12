<?php
/**
 * ========================================
 * WEBHOOK MERCADO PAGO
 * Recebe notificações de pagamento do Mercado Pago
 * Versão: 1.0
 * Data: 2024-12-17
 * ========================================
 */

// Ativar log de erros
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/mercadopago_webhook.log');

// Incluir configurações
require_once __DIR__ . '/../includes/config.php';

// Função para logar
function logWebhook($message, $data = null) {
    $logFile = __DIR__ . '/../logs/mercadopago_webhook.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    if ($data) {
        $logMessage .= "\n" . json_encode($data, JSON_PRETTY_PRINT);
    }
    $logMessage .= "\n---\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Função para responder ao webhook
function respondWebhook($status = 200, $message = 'OK') {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

try {
    // Obter dados do webhook
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    logWebhook('Webhook recebido', [
        'headers' => getallheaders(),
        'body' => $data,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    // Verificar se é notificação válida
    if (!$data || !isset($data['type'])) {
        logWebhook('Webhook inválido - dados ausentes');
        respondWebhook(400, 'Invalid webhook data');
    }
    
    // Tipos de notificação do Mercado Pago
    $type = $data['type'];
    $action = $data['action'] ?? '';
    
    logWebhook("Tipo de notificação: $type, Ação: $action");
    
    // Processar apenas notificações de pagamento
    if ($type === 'payment' || $action === 'payment.created' || $action === 'payment.updated') {
        
        // Obter ID do pagamento
        $paymentId = null;
        if (isset($data['data']['id'])) {
            $paymentId = $data['data']['id'];
        } elseif (isset($data['id'])) {
            $paymentId = $data['id'];
        }
        
        if (!$paymentId) {
            logWebhook('Payment ID não encontrado no webhook');
            respondWebhook(400, 'Payment ID not found');
        }
        
        logWebhook("Payment ID: $paymentId");
        
        // Conectar ao banco
        $conn = getDBConnection();
        
        // Buscar configuração do Mercado Pago para obter access_token
        $stmt = $conn->prepare("
            SELECT access_token, estabelecimento_id 
            FROM mercadopago_config 
            WHERE status = 1 
            LIMIT 1
        ");
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$config) {
            logWebhook('Configuração do Mercado Pago não encontrada');
            respondWebhook(500, 'Mercado Pago not configured');
        }
        
        $accessToken = $config['access_token'];
        
        // Buscar detalhes do pagamento na API do Mercado Pago
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/v1/payments/$paymentId");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $accessToken",
            "Content-Type: application/json"
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            logWebhook("Erro ao buscar pagamento na API do Mercado Pago. HTTP Code: $httpCode", $response);
            respondWebhook(500, 'Error fetching payment details');
        }
        
        $payment = json_decode($response, true);
        logWebhook('Detalhes do pagamento obtidos', $payment);
        
        // Extrair informações do pagamento
        $status = $payment['status'] ?? 'unknown';
        $externalReference = $payment['external_reference'] ?? null;
        $amount = $payment['transaction_amount'] ?? 0;
        $payerEmail = $payment['payer']['email'] ?? '';
        
        logWebhook("Status: $status, External Reference: $externalReference, Amount: $amount");
        
        // Mapear status do Mercado Pago para status do sistema
        $statusMap = [
            'approved' => 'pago',
            'pending' => 'pendente',
            'in_process' => 'pendente',
            'rejected' => 'cancelado',
            'cancelled' => 'cancelado',
            'refunded' => 'cancelado',
            'charged_back' => 'cancelado'
        ];
        
        $novoStatus = $statusMap[$status] ?? 'pendente';
        
        // Buscar royalty pelo external_reference
        if ($externalReference) {
            // External reference deve ser no formato: royalty_ID
            if (preg_match('/royalty_(\d+)/', $externalReference, $matches)) {
                $royaltyId = $matches[1];
                
                logWebhook("Royalty ID encontrado: $royaltyId");
                
                // Atualizar status do royalty
                $stmt = $conn->prepare("
                    UPDATE royalties 
                    SET status = :status,
                        data_pagamento = :data_pagamento,
                        payment_link_id = :payment_id,
                        updated_at = NOW()
                    WHERE id = :royalty_id
                ");
                
                $dataPagamento = ($novoStatus === 'pago') ? date('Y-m-d') : null;
                
                $stmt->execute([
                    ':status' => $novoStatus,
                    ':data_pagamento' => $dataPagamento,
                    ':payment_id' => $paymentId,
                    ':royalty_id' => $royaltyId
                ]);
                
                logWebhook("Royalty $royaltyId atualizado para status: $novoStatus");
                
                // Registrar no log de pagamentos
                $stmt = $conn->prepare("
                    INSERT INTO royalties_payment_log 
                    (royalty_id, estabelecimento_id, metodo_pagamento, acao, status, request_data, response_data, ip_address, user_agent)
                    VALUES 
                    (:royalty_id, :estabelecimento_id, 'mercadopago', 'webhook', :status, :request_data, :response_data, :ip_address, :user_agent)
                ");
                
                $stmt->execute([
                    ':royalty_id' => $royaltyId,
                    ':estabelecimento_id' => $config['estabelecimento_id'],
                    ':status' => $novoStatus,
                    ':request_data' => json_encode($data),
                    ':response_data' => json_encode($payment),
                    ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                ]);
                
                logWebhook("Log de pagamento registrado para royalty $royaltyId");
                
                // Responder sucesso
                respondWebhook(200, 'Payment processed successfully');
                
            } else {
                logWebhook("External reference inválido: $externalReference");
                respondWebhook(400, 'Invalid external reference format');
            }
        } else {
            logWebhook('External reference não encontrado no pagamento');
            respondWebhook(400, 'External reference not found');
        }
        
    } else {
        // Tipo de notificação não processado
        logWebhook("Tipo de notificação não processado: $type");
        respondWebhook(200, 'Notification type not processed');
    }
    
} catch (Exception $e) {
    logWebhook('ERRO: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    respondWebhook(500, 'Internal server error');
}
?>
