<?php
/**
 * ========================================
 * HELPER PARA INTEGRAÇÃO COM MERCADO PAGO
 * Classe auxiliar para gerenciar webhooks
 * Versão: 2.0
 * ========================================
 */

class MercadoPagoWebhookHelper {
    
    private $conn;
    private $config;
    private $accessToken;
    private $webhookSecret;
    private $estabelecimentoId;
    
    /**
     * Construtor
     */
    public function __construct($connection, $config = null) {
        $this->conn = $connection;
        
        if ($config) {
            $this->config = $config;
            $this->accessToken = $config['access_token'];
            $this->webhookSecret = $config['webhook_secret'];
            $this->estabelecimentoId = $config['estabelecimento_id'];
        } else {
            $this->loadConfig();
        }
    }
    
    /**
     * Carregar configuração do banco de dados
     */
    private function loadConfig() {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM `mercadopago_config` 
                WHERE status = 1 
                LIMIT 1
            ");
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception('Configuração do Mercado Pago não encontrada');
            }
            
            $this->config = $result->fetch_assoc();
            $this->accessToken = $this->config['access_token'];
            $this->webhookSecret = $this->config['webhook_secret'];
            $this->estabelecimentoId = $this->config['estabelecimento_id'];
            
        } catch (Exception $e) {
            throw new Exception("Erro ao carregar configuração: " . $e->getMessage());
        }
    }
    
    /**
     * Validar assinatura HMAC do webhook
     */
    public function validateSignature($payload, $signature) {
        if (empty($signature) || empty($this->webhookSecret)) {
            return false;
        }
        
        $expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * Consultar pagamento na API do Mercado Pago
     */
    public function fetchPayment($paymentId) {
        try {
            $url = "https://api.mercadopago.com/v1/payments/$paymentId";
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer {$this->accessToken}",
                    "Content-Type: application/json",
                    "User-Agent: ChoppOn-WebhookHelper/2.0"
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
                throw new Exception("API retornou HTTP $httpCode");
            }
            
            $payment = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Erro ao decodificar JSON: " . json_last_error_msg());
            }
            
            return $payment;
            
        } catch (Exception $e) {
            throw new Exception("Erro ao consultar pagamento: " . $e->getMessage());
        }
    }
    
    /**
     * Mapear status do Mercado Pago para status do sistema
     */
    public function mapPaymentStatus($mpStatus) {
        $statusMap = [
            'approved'      => 'pago',
            'pending'       => 'pendente',
            'in_process'    => 'processando',
            'rejected'      => 'cancelado',
            'cancelled'     => 'cancelado',
            'refunded'      => 'cancelado',
            'charged_back'  => 'cancelado'
        ];
        
        return $statusMap[$mpStatus] ?? 'pendente';
    }
    
    /**
     * Buscar pedido pelo external_reference ou payment_id
     */
    public function findOrder($externalReference = null, $paymentId = null) {
        try {
            // Tentar buscar por external_reference
            if ($externalReference && preg_match('/order_(\d+)/i', $externalReference, $matches)) {
                $orderId = $matches[1];
                
                $stmt = $this->conn->prepare("
                    SELECT * FROM `order` 
                    WHERE id = ? 
                    AND estabelecimento_id = ?
                    LIMIT 1
                ");
                
                $stmt->bind_param("ii", $orderId, $this->estabelecimentoId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    return $result->fetch_assoc();
                }
            }
            
            // Tentar buscar por checkout_id (payment_id)
            if ($paymentId) {
                $stmt = $this->conn->prepare("
                    SELECT * FROM `order` 
                    WHERE checkout_id = ? 
                    AND estabelecimento_id = ?
                    LIMIT 1
                ");
                
                $stmt->bind_param("si", $paymentId, $this->estabelecimentoId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    return $result->fetch_assoc();
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            throw new Exception("Erro ao buscar pedido: " . $e->getMessage());
        }
    }
    
    /**
     * Atualizar status do pedido
     */
    public function updateOrderStatus($orderId, $newStatus, $paymentData = []) {
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
            
            // Construir query
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
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            
            if (!$stmt->execute()) {
                throw new Exception("Erro ao executar query: " . $stmt->error);
            }
            
            return true;
            
        } catch (Exception $e) {
            throw new Exception("Erro ao atualizar status do pedido: " . $e->getMessage());
        }
    }
    
    /**
     * Registrar log de webhook no banco de dados
     */
    public function logWebhook($orderId, $paymentId, $status, $requestData, $responseData) {
        try {
            // Criar tabela se não existir
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
            
            $this->conn->query($createTableQuery);
            
            // Inserir log
            $stmt = $this->conn->prepare("
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
                $this->estabelecimentoId,
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
            throw new Exception("Erro ao registrar log: " . $e->getMessage());
        }
    }
    
    /**
     * Obter histórico de webhooks
     */
    public function getWebhookHistory($orderId = null, $limit = 50, $offset = 0) {
        try {
            $query = "SELECT * FROM `mercadopago_webhook_logs` WHERE 1=1";
            $params = [];
            $types = '';
            
            if ($orderId) {
                $query .= " AND order_id = ?";
                $params[] = $orderId;
                $types .= 'i';
            }
            
            $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            $types .= 'ii';
            
            $stmt = $this->conn->prepare($query);
            
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $logs = [];
            while ($row = $result->fetch_assoc()) {
                $row['request_data'] = json_decode($row['request_data'], true);
                $row['response_data'] = json_decode($row['response_data'], true);
                $logs[] = $row;
            }
            
            return $logs;
            
        } catch (Exception $e) {
            throw new Exception("Erro ao obter histórico: " . $e->getMessage());
        }
    }
    
    /**
     * Reprocessar webhook manualmente
     */
    public function reprocessWebhook($paymentId) {
        try {
            // Consultar pagamento
            $payment = $this->fetchPayment($paymentId);
            
            // Mapear status
            $paymentStatus = $payment['status'] ?? 'unknown';
            $newStatus = $this->mapPaymentStatus($paymentStatus);
            
            // Buscar pedido
            $externalReference = $payment['external_reference'] ?? null;
            $order = $this->findOrder($externalReference, $paymentId);
            
            if (!$order) {
                throw new Exception("Pedido não encontrado para payment_id: $paymentId");
            }
            
            // Atualizar status
            $this->updateOrderStatus($order['id'], $newStatus, $payment);
            
            // Registrar log
            $this->logWebhook(
                $order['id'],
                $paymentId,
                $newStatus,
                ['reprocessed' => true],
                $payment
            );
            
            return [
                'success' => true,
                'order_id' => $order['id'],
                'payment_id' => $paymentId,
                'status' => $newStatus
            ];
            
        } catch (Exception $e) {
            throw new Exception("Erro ao reprocessar webhook: " . $e->getMessage());
        }
    }
    
    /**
     * Obter estatísticas de webhooks
     */
    public function getWebhookStats($days = 30) {
        try {
            $query = "
                SELECT 
                    status,
                    COUNT(*) as total,
                    DATE(created_at) as data
                FROM `mercadopago_webhook_logs`
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                AND estabelecimento_id = ?
                GROUP BY status, DATE(created_at)
                ORDER BY data DESC
            ";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("ii", $days, $this->estabelecimentoId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $stats = [];
            while ($row = $result->fetch_assoc()) {
                $stats[] = $row;
            }
            
            return $stats;
            
        } catch (Exception $e) {
            throw new Exception("Erro ao obter estatísticas: " . $e->getMessage());
        }
    }
    
    /**
     * Getter para config
     */
    public function getConfig() {
        return $this->config;
    }
    
    /**
     * Getter para estabelecimento_id
     */
    public function getEstabelecimentoId() {
        return $this->estabelecimentoId;
    }
}

?>
