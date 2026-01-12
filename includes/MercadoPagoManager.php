<?php
/**
 * ========================================
 * MERCADO PAGO MANAGER
 * Gerencia pagamentos via Mercado Pago
 * Versão: 1.0
 * Data: 2024-12-17
 * ========================================
 */

class MercadoPagoManager {
    private $conn;
    private $accessToken;
    private $publicKey;
    private $ambiente;
    private $estabelecimentoId;
    
    public function __construct($conn, $estabelecimentoId = null) {
        $this->conn = $conn;
        $this->estabelecimentoId = $estabelecimentoId;
        $this->carregarConfiguracao($estabelecimentoId);
    }
    
    /**
     * Carregar configuração do Mercado Pago
     */
    private function carregarConfiguracao($estabelecimentoId = null) {
        $sql = "SELECT * FROM mercadopago_config WHERE status = 1";
        
        if ($estabelecimentoId) {
            $sql .= " AND estabelecimento_id = :estabelecimento_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':estabelecimento_id' => $estabelecimentoId]);
        } else {
            $sql .= " LIMIT 1";
            $stmt = $this->conn->query($sql);
        }
        
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$config) {
            throw new Exception('Configuração do Mercado Pago não encontrada');
        }
        
        $this->accessToken = $config['access_token'];
        $this->publicKey = $config['public_key'] ?? '';
        $this->ambiente = $config['ambiente'];
        $this->estabelecimentoId = $config['estabelecimento_id'];
    }
    
    /**
     * Criar link de pagamento para royalty
     */
    public function criarPagamentoRoyalty($royaltyId) {
        try {
            // Buscar dados do royalty
            $stmt = $this->conn->prepare("
                SELECT r.*, e.name as estabelecimento_nome, e.email as estabelecimento_email
                FROM royalties r
                INNER JOIN estabelecimentos e ON r.estabelecimento_id = e.id
                WHERE r.id = :royalty_id
            ");
            $stmt->execute([':royalty_id' => $royaltyId]);
            $royalty = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$royalty) {
                throw new Exception('Royalty não encontrado');
            }
            
            // Preparar dados do pagamento
            $paymentData = [
                'transaction_amount' => (float)$royalty['valor_royalties'],
                'description' => $royalty['descricao'],
                'payment_method_id' => 'pix', // Pode ser alterado conforme necessidade
                'payer' => [
                    'email' => $royalty['email_cobranca'] ?? $royalty['estabelecimento_email'],
                    'first_name' => $royalty['estabelecimento_nome'],
                    'identification' => [
                        'type' => 'CPF',
                        'number' => '00000000000' // Deve ser obtido do cadastro
                    ]
                ],
                'external_reference' => "royalty_{$royaltyId}",
                'notification_url' => SITE_URL . '/webhooks/mercadopago_webhook.php',
                'statement_descriptor' => 'ROYALTIES CHOPP ON',
                'installments' => 1,
                'metadata' => [
                    'royalty_id' => $royaltyId,
                    'estabelecimento_id' => $royalty['estabelecimento_id'],
                    'periodo' => $royalty['periodo_inicial'] . ' a ' . $royalty['periodo_final']
                ]
            ];
            
            // Fazer requisição para API do Mercado Pago
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.mercadopago.com/v1/payments');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymentData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$this->accessToken}",
                "Content-Type: application/json",
                "X-Idempotency-Key: royalty_{$royaltyId}_" . time()
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $result = json_decode($response, true);
            
            // Registrar log
            $this->registrarLog($royaltyId, 'criar_pagamento', 'pendente', $paymentData, $result);
            
            if ($httpCode !== 201 && $httpCode !== 200) {
                throw new Exception('Erro ao criar pagamento: ' . ($result['message'] ?? 'Erro desconhecido'));
            }
            
            // Atualizar royalty com dados do pagamento
            $stmt = $this->conn->prepare("
                UPDATE royalties 
                SET payment_link_id = :payment_id,
                    payment_link_url = :payment_url,
                    status = 'pendente',
                    tipo_cobranca = 'mercadopago',
                    updated_at = NOW()
                WHERE id = :royalty_id
            ");
            
            $stmt->execute([
                ':payment_id' => $result['id'],
                ':payment_url' => $result['point_of_interaction']['transaction_data']['ticket_url'] ?? '',
                ':royalty_id' => $royaltyId
            ]);
            
            return [
                'success' => true,
                'payment_id' => $result['id'],
                'payment_url' => $result['point_of_interaction']['transaction_data']['ticket_url'] ?? '',
                'qr_code' => $result['point_of_interaction']['transaction_data']['qr_code'] ?? '',
                'qr_code_base64' => $result['point_of_interaction']['transaction_data']['qr_code_base64'] ?? ''
            ];
            
        } catch (Exception $e) {
            $this->registrarLog($royaltyId, 'criar_pagamento', 'erro', [], ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verificar status de um pagamento
     */
    public function verificarStatusPagamento($paymentId) {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/v1/payments/$paymentId");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$this->accessToken}",
                "Content-Type: application/json"
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                throw new Exception('Erro ao verificar status do pagamento');
            }
            
            $payment = json_decode($response, true);
            
            return [
                'success' => true,
                'status' => $payment['status'],
                'status_detail' => $payment['status_detail'] ?? '',
                'payment' => $payment
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Atualizar status do royalty baseado no pagamento
     */
    public function atualizarStatusRoyalty($royaltyId) {
        try {
            // Buscar payment_link_id do royalty
            $stmt = $this->conn->prepare("SELECT payment_link_id FROM royalties WHERE id = :royalty_id");
            $stmt->execute([':royalty_id' => $royaltyId]);
            $royalty = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$royalty || !$royalty['payment_link_id']) {
                throw new Exception('Pagamento não encontrado para este royalty');
            }
            
            // Verificar status na API
            $resultado = $this->verificarStatusPagamento($royalty['payment_link_id']);
            
            if (!$resultado['success']) {
                throw new Exception($resultado['error']);
            }
            
            // Mapear status
            $statusMap = [
                'approved' => 'pago',
                'pending' => 'pendente',
                'in_process' => 'pendente',
                'rejected' => 'cancelado',
                'cancelled' => 'cancelado',
                'refunded' => 'cancelado',
                'charged_back' => 'cancelado'
            ];
            
            $novoStatus = $statusMap[$resultado['status']] ?? 'pendente';
            $dataPagamento = ($novoStatus === 'pago') ? date('Y-m-d') : null;
            
            // Atualizar royalty
            $stmt = $this->conn->prepare("
                UPDATE royalties 
                SET status = :status,
                    data_pagamento = :data_pagamento,
                    updated_at = NOW()
                WHERE id = :royalty_id
            ");
            
            $stmt->execute([
                ':status' => $novoStatus,
                ':data_pagamento' => $dataPagamento,
                ':royalty_id' => $royaltyId
            ]);
            
            // Registrar log
            $this->registrarLog($royaltyId, 'verificar_status', $novoStatus, [], $resultado['payment']);
            
            return [
                'success' => true,
                'status' => $novoStatus,
                'status_mercadopago' => $resultado['status']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Registrar log de transação
     */
    private function registrarLog($royaltyId, $acao, $status, $requestData, $responseData) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO royalties_payment_log 
                (royalty_id, estabelecimento_id, metodo_pagamento, acao, status, request_data, response_data, ip_address, user_agent)
                VALUES 
                (:royalty_id, :estabelecimento_id, 'mercadopago', :acao, :status, :request_data, :response_data, :ip_address, :user_agent)
            ");
            
            $stmt->execute([
                ':royalty_id' => $royaltyId,
                ':estabelecimento_id' => $this->estabelecimentoId,
                ':acao' => $acao,
                ':status' => $status,
                ':request_data' => json_encode($requestData),
                ':response_data' => json_encode($responseData),
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'system',
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'MercadoPagoManager'
            ]);
        } catch (Exception $e) {
            // Log silencioso - não interromper fluxo principal
            error_log("Erro ao registrar log: " . $e->getMessage());
        }
    }
    
    /**
     * Obter configuração pública (para frontend)
     */
    public function getPublicConfig() {
        return [
            'public_key' => $this->publicKey,
            'ambiente' => $this->ambiente
        ];
    }
}
?>
