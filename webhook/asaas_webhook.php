<?php
/**
 * ========================================
 * WEBHOOK ASAAS
 * Recebe notificações de pagamento do Asaas
 * Versão: 1.0
 * Data: 2026-01-12
 * ========================================
 * 
 * Eventos suportados:
 * - PAYMENT_CREATED: Nova cobrança criada
 * - PAYMENT_UPDATED: Cobrança atualizada
 * - PAYMENT_CONFIRMED: Pagamento confirmado (saldo não disponível ainda)
 * - PAYMENT_RECEIVED: Cobrança recebida (saldo disponível)
 * - PAYMENT_OVERDUE: Cobrança vencida
 * - PAYMENT_DELETED: Cobrança removida
 * - PAYMENT_RESTORED: Cobrança restaurada
 * - PAYMENT_REFUNDED: Cobrança estornada
 * - PAYMENT_ANTICIPATED: Cobrança antecipada
 * - PAYMENT_CHARGEBACK_REQUESTED: Chargeback recebido
 */

// Ativar log de erros
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/asaas_webhook.log');

// Incluir configurações
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/AsaasAPI.php';

/**
 * Função para logar eventos do webhook
 */
function logWebhook($message, $data = null) {
    $logFile = __DIR__ . '/../logs/asaas_webhook.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    if ($data) {
        $logMessage .= "\n" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    $logMessage .= "\n" . str_repeat('-', 80) . "\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

/**
 * Função para responder ao webhook
 */
function respondWebhook($status = 200, $message = 'OK') {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(['status' => $status, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Obter dados do webhook
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Obter token do header (se configurado)
    $headers = getallheaders();
    $receivedToken = $headers['asaas-access-token'] ?? $headers['Asaas-Access-Token'] ?? null;
    
    logWebhook('Webhook recebido', [
        'headers' => $headers,
        'body' => $data,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'token_received' => $receivedToken ? 'sim' : 'não'
    ]);
    
    // Verificar se é notificação válida
    if (!$data || !isset($data['event'])) {
        logWebhook('Webhook inválido - dados ausentes ou formato incorreto');
        respondWebhook(400, 'Invalid webhook data');
    }
    
    // Extrair informações do webhook
    $event_type = $data['event'];
    $event_id = $data['id'] ?? uniqid('evt_');
    $payment_data = $data['payment'] ?? null;
    
    if (!$payment_data) {
        logWebhook('Webhook sem dados de pagamento');
        respondWebhook(400, 'Payment data not found');
    }
    
    $payment_id = $payment_data['id'] ?? null;
    $payment_status = $payment_data['status'] ?? 'UNKNOWN';
    $external_reference = $payment_data['externalReference'] ?? null;
    
    logWebhook("Evento recebido", [
        'event_id' => $event_id,
        'event_type' => $event_type,
        'payment_id' => $payment_id,
        'payment_status' => $payment_status,
        'external_reference' => $external_reference
    ]);
    
    // Conectar ao banco
    $conn = getDBConnection();
    
    // Buscar configuração do Asaas para validar token (se configurado)
    $stmt = $conn->prepare("
        SELECT ac.*, e.id as estabelecimento_id
        FROM asaas_config ac
        INNER JOIN estabelecimentos e ON ac.estabelecimento_id = e.id
        WHERE ac.ativo = 1
        LIMIT 1
    ");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config) {
        logWebhook('Configuração do Asaas não encontrada');
        respondWebhook(500, 'Asaas not configured');
    }
    
    // Validar token se configurado
    if ($config['asaas_webhook_token'] && $receivedToken !== $config['asaas_webhook_token']) {
        logWebhook('Token de webhook inválido', [
            'expected' => substr($config['asaas_webhook_token'], 0, 10) . '...',
            'received' => $receivedToken ? substr($receivedToken, 0, 10) . '...' : 'null'
        ]);
        respondWebhook(401, 'Invalid webhook token');
    }
    
    // Salvar webhook no banco (para idempotência)
    $stmt = $conn->prepare("
        INSERT INTO asaas_webhooks 
        (event_id, event_type, asaas_payment_id, payload, processado)
        VALUES (?, ?, ?, ?, 0)
        ON DUPLICATE KEY UPDATE
            event_type = VALUES(event_type),
            payload = VALUES(payload)
    ");
    
    $stmt->execute([
        $event_id,
        $event_type,
        $payment_id,
        json_encode($data)
    ]);
    
    // Verificar se já foi processado (idempotência)
    $stmt = $conn->prepare("
        SELECT processado FROM asaas_webhooks WHERE event_id = ?
    ");
    $stmt->execute([$event_id]);
    $webhook = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($webhook && $webhook['processado'] == 1) {
        logWebhook("Webhook já processado anteriormente (idempotência)", ['event_id' => $event_id]);
        respondWebhook(200, 'Webhook already processed');
    }
    
    // Atualizar informações do pagamento na tabela asaas_pagamentos
    $stmt = $conn->prepare("
        UPDATE asaas_pagamentos 
        SET status_asaas = ?,
            url_boleto = ?,
            linha_digitavel = ?,
            nosso_numero = ?,
            url_fatura = ?,
            data_pagamento = ?,
            data_confirmacao = ?,
            data_credito = ?,
            valor_liquido = ?,
            payload_completo = ?,
            data_atualizacao = NOW()
        WHERE asaas_payment_id = ?
    ");
    
    $stmt->execute([
        $payment_status,
        $payment_data['bankSlipUrl'] ?? null,
        $payment_data['identificationField'] ?? null,
        $payment_data['nossoNumero'] ?? null,
        $payment_data['invoiceUrl'] ?? null,
        $payment_data['paymentDate'] ?? null,
        $payment_data['confirmedDate'] ?? null,
        $payment_data['creditDate'] ?? null,
        $payment_data['netValue'] ?? null,
        json_encode($payment_data),
        $payment_id
    ]);
    
    logWebhook("Pagamento atualizado na tabela asaas_pagamentos", ['payment_id' => $payment_id]);
    
    // Mapear status do Asaas para status do sistema
    $statusMap = [
        'PENDING' => 'pendente',
        'RECEIVED' => 'pago',
        'CONFIRMED' => 'confirmado',
        'OVERDUE' => 'pendente',
        'REFUNDED' => 'cancelado',
        'RECEIVED_IN_CASH' => 'pago',
        'REFUND_REQUESTED' => 'processando',
        'CHARGEBACK_REQUESTED' => 'processando',
        'CHARGEBACK_DISPUTE' => 'processando',
        'AWAITING_CHARGEBACK_REVERSAL' => 'processando',
        'DUNNING_REQUESTED' => 'processando',
        'DUNNING_RECEIVED' => 'pago',
        'AWAITING_RISK_ANALYSIS' => 'processando'
    ];
    
    $novoStatus = $statusMap[$payment_status] ?? 'pendente';
    
    // Buscar royalty pelo external_reference
    if ($external_reference) {
        // External reference deve ser no formato: ROYALTY_ID
        if (preg_match('/ROYALTY_(\d+)/i', $external_reference, $matches)) {
            $royaltyId = $matches[1];
            
            logWebhook("Royalty ID encontrado", ['royalty_id' => $royaltyId]);
            
            // Buscar royalty
            $stmt = $conn->prepare("SELECT * FROM royalties WHERE id = ?");
            $stmt->execute([$royaltyId]);
            $royalty = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$royalty) {
                logWebhook("Royalty não encontrado", ['royalty_id' => $royaltyId]);
                respondWebhook(404, 'Royalty not found');
            }
            
            // Atualizar status do royalty
            $stmt = $conn->prepare("
                UPDATE royalties 
                SET status = ?,
                    payment_status = ?,
                    data_pagamento = ?,
                    paid_at = ?,
                    payment_data = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $dataPagamento = null;
            $paidAt = null;
            
            // Definir data de pagamento apenas quando realmente pago
            if ($novoStatus === 'pago') {
                $dataPagamento = $payment_data['paymentDate'] ?? date('Y-m-d');
                $paidAt = $payment_data['paymentDate'] ? date('Y-m-d H:i:s', strtotime($payment_data['paymentDate'])) : date('Y-m-d H:i:s');
            }
            
            $stmt->execute([
                $novoStatus,
                $payment_status,
                $dataPagamento,
                $paidAt,
                json_encode($payment_data),
                $royaltyId
            ]);
            
            logWebhook("Royalty atualizado", [
                'royalty_id' => $royaltyId,
                'novo_status' => $novoStatus,
                'payment_status' => $payment_status
            ]);
            
            // Registrar no log de pagamentos
            $stmt = $conn->prepare("
                INSERT INTO royalties_payment_log 
                (royalty_id, estabelecimento_id, metodo_pagamento, acao, status, request_data, response_data, ip_address, user_agent)
                VALUES 
                (?, ?, 'asaas', 'webhook', ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $royaltyId,
                $royalty['estabelecimento_id'],
                $novoStatus,
                json_encode($data),
                json_encode($payment_data),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
            logWebhook("Log de pagamento registrado", ['royalty_id' => $royaltyId]);
            
            // Marcar webhook como processado
            $stmt = $conn->prepare("
                UPDATE asaas_webhooks 
                SET processado = 1, data_processamento = NOW()
                WHERE event_id = ?
            ");
            $stmt->execute([$event_id]);
            
            logWebhook("Webhook processado com sucesso", [
                'event_id' => $event_id,
                'royalty_id' => $royaltyId,
                'status' => $novoStatus
            ]);
            
            // Responder sucesso
            respondWebhook(200, 'Payment processed successfully');
            
        } else {
            logWebhook("External reference inválido", ['external_reference' => $external_reference]);
            
            // Mesmo com external reference inválido, marcar como processado para evitar reprocessamento
            $stmt = $conn->prepare("
                UPDATE asaas_webhooks 
                SET processado = 1, 
                    data_processamento = NOW(),
                    erro_mensagem = 'External reference inválido'
                WHERE event_id = ?
            ");
            $stmt->execute([$event_id]);
            
            respondWebhook(400, 'Invalid external reference format');
        }
    } else {
        logWebhook('External reference não encontrado no pagamento');
        
        // Marcar como processado mesmo sem external reference
        $stmt = $conn->prepare("
            UPDATE asaas_webhooks 
            SET processado = 1, 
                data_processamento = NOW(),
                erro_mensagem = 'External reference não encontrado'
            WHERE event_id = ?
        ");
        $stmt->execute([$event_id]);
        
        respondWebhook(400, 'External reference not found');
    }
    
} catch (Exception $e) {
    logWebhook('ERRO CRÍTICO', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    // Tentar marcar webhook com erro
    try {
        if (isset($conn) && isset($event_id)) {
            $stmt = $conn->prepare("
                UPDATE asaas_webhooks 
                SET erro_mensagem = ?
                WHERE event_id = ?
            ");
            $stmt->execute([$e->getMessage(), $event_id]);
        }
    } catch (Exception $e2) {
        logWebhook('Erro ao salvar mensagem de erro no banco', ['error' => $e2->getMessage()]);
    }
    
    respondWebhook(500, 'Internal server error');
}
?>
