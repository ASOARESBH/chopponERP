<?php
/**
 * Webhook SumUp
 * Recebe notificações de status de pagamento
 */

require_once '../includes/config.php';
require_once '../includes/telegram.php';

// Desabilitar output de erros para não interferir na resposta
ini_set('display_errors', 0);

// Log para debug
$log_file = __DIR__ . '/../logs/webhook.log';
$log_dir = dirname($log_file);
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// Receber dados do webhook
$raw_data = file_get_contents('php://input');
file_put_contents($log_file, date('Y-m-d H:i:s') . " - Webhook recebido: " . $raw_data . "\n", FILE_APPEND);

$json = json_decode($raw_data);

if ($json && isset($json->id)) {
    $conn = getDBConnection();
    
    try {
        // Verificar se tem payload (transação de cartão) ou é PIX direto
        if (!empty($json->payload)) {
            // Transação de cartão
            $checkout_id = $json->payload->client_transaction_id ?? null;
            $status = strtoupper($json->payload->status ?? '');
        } else {
            // Transação PIX
            $checkout_id = $json->id ?? null;
            $status = strtoupper($json->status ?? '');
        }
        
        if ($checkout_id) {
            $stmt = $conn->prepare("
                UPDATE `order` 
                SET checkout_status = ?, 
                    response = ?,
                    updated_at = NOW()
                WHERE checkout_id = ?
            ");
            
            $stmt->execute([$status, $raw_data, $checkout_id]);
            
            file_put_contents($log_file, date('Y-m-d H:i:s') . " - Order atualizada: $checkout_id -> $status\n", FILE_APPEND);
            
            // Enviar notificação Telegram se pagamento aprovado
            if (in_array($status, ['PAID', 'SUCCESSFUL', 'APPROVED'])) {
                try {
                    // Buscar dados completos do pedido
                    $stmt = $conn->prepare("
                        SELECT o.*, 
                               b.name as bebida_nome, 
                               b.brand as bebida_marca,
                               e.name as estabelecimento_nome
                        FROM `order` o
                        INNER JOIN bebidas b ON o.bebida_id = b.id
                        INNER JOIN estabelecimentos e ON o.estabelecimento_id = e.id
                        WHERE o.checkout_id = ? AND o.telegram_notificado = 0
                        LIMIT 1
                    ");
                    $stmt->execute([$checkout_id]);
                    $order = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($order) {
                        $telegram = new TelegramBot($conn);
                        $message = TelegramBot::formatVendaMessage($order);
                        
                        if ($telegram->sendMessage($order['estabelecimento_id'], $message, 'venda', $order['id'])) {
                            // Marcar como notificado
                            $stmt = $conn->prepare("UPDATE `order` SET telegram_notificado = 1 WHERE id = ?");
                            $stmt->execute([$order['id']]);
                            
                            file_put_contents($log_file, date('Y-m-d H:i:s') . " - Telegram enviado para pedido {$order['id']}\n", FILE_APPEND);
                        }
                    }
                } catch (Exception $e) {
                    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Erro ao enviar Telegram: " . $e->getMessage() . "\n", FILE_APPEND);
                }
            }
            
            http_response_code(200);
            echo json_encode(['success' => true]);
        } else {
            file_put_contents($log_file, date('Y-m-d H:i:s') . " - Checkout ID não encontrado\n", FILE_APPEND);
            http_response_code(400);
            echo json_encode(['error' => 'checkout_id not found']);
        }
    } catch (Exception $e) {
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - Erro: " . $e->getMessage() . "\n", FILE_APPEND);
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - JSON inválido\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(['error' => 'invalid json']);
}
