<?php
/**
 * Gerenciador de Royalties V2
 * Suporta integração com Cora e Stripe
 * Gerencia criação, emissão e rastreamento de boletos e faturas
 */

class RoyaltiesManagerV2 {
    private $conn;
    private $logger;
    private $cora_api;
    private $stripe_api;
    
    public function __construct($conn) {
        $this->conn = $conn;
        require_once __DIR__ . '/RoyaltiesLogger.php';
        $this->logger = new RoyaltiesLogger('royalties_v2');
        
        $this->logger->info('RoyaltiesManagerV2 inicializado');
    }
    
    /**
     * Inicializar API Cora para um estabelecimento
     */
    private function initCoraAPI($estabelecimento_id) {
        try {
            // Buscar configuração do Cora
            $stmt = $this->conn->prepare("
                SELECT config_data 
                FROM payment_gateway_config 
                WHERE estabelecimento_id = ? AND gateway_type = 'cora' AND ativo = 1
            ");
            $stmt->execute([$estabelecimento_id]);
            $config = $stmt->fetch();
            
            if (!$config) {
                throw new Exception('Configuração Cora não encontrada para este estabelecimento');
            }
            
            $config_data = json_decode($config['config_data'], true);
            
            require_once __DIR__ . '/cora_api_v2.php';
            
            $this->cora_api = new CoraAPIv2(
                $config_data['client_id'],
                $config_data['client_secret'],
                $config_data['environment'] ?? 'stage'
            );
            
            return true;
        } catch (Exception $e) {
            $this->logger->error('Erro ao inicializar Cora API', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Inicializar API Stripe para um estabelecimento
     */
    private function initStripeAPI($estabelecimento_id) {
        try {
            // Buscar configuração do Stripe
            $stmt = $this->conn->prepare("
                SELECT config_data 
                FROM payment_gateway_config 
                WHERE estabelecimento_id = ? AND gateway_type = 'stripe' AND ativo = 1
            ");
            $stmt->execute([$estabelecimento_id]);
            $config = $stmt->fetch();
            
            if (!$config) {
                throw new Exception('Configuração Stripe não encontrada para este estabelecimento');
            }
            
            $config_data = json_decode($config['config_data'], true);
            
            require_once __DIR__ . '/stripe_api.php';
            
            // Temporariamente definir variáveis de ambiente para StripeAPI
            putenv('STRIPE_SECRET_KEY=' . $config_data['secret_key']);
            putenv('STRIPE_WEBHOOK_SECRET=' . $config_data['webhook_secret']);
            putenv('STRIPE_MODE=' . ($config_data['environment'] ?? 'test'));
            
            $this->stripe_api = new StripeAPI($estabelecimento_id);
            
            return true;
        } catch (Exception $e) {
            $this->logger->error('Erro ao inicializar Stripe API', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Criar royalty com opção de gerar boleto/fatura
     */
    public function criarRoyalty($data) {
        try {
            $this->logger->startOperation('Criação de royalty');
            
            // Validar dados obrigatórios
            $required = ['estabelecimento_id', 'periodo_inicial', 'periodo_final', 'valor_faturamento_bruto'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return [
                        'success' => false,
                        'error' => "Campo obrigatório ausente: {$field}"
                    ];
                }
            }
            
            $estabelecimento_id = $data['estabelecimento_id'];
            $percentual_royalties = $data['percentual_royalties'] ?? 7.00;
            $valor_faturamento_bruto = floatval($data['valor_faturamento_bruto']);
            $valor_royalties = $valor_faturamento_bruto * ($percentual_royalties / 100);
            
            // Iniciar transação
            $this->conn->beginTransaction();
            
            try {
                // 1. Criar registro de royalty
                $stmt = $this->conn->prepare("
                    INSERT INTO royalties (
                        estabelecimento_id,
                        periodo_inicial,
                        periodo_final,
                        descricao,
                        valor_faturamento_bruto,
                        percentual_royalties,
                        valor_royalties,
                        status,
                        created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pendente', ?)
                ");
                
                $stmt->execute([
                    $estabelecimento_id,
                    $data['periodo_inicial'],
                    $data['periodo_final'],
                    $data['descricao'] ?? 'Royalties',
                    $valor_faturamento_bruto,
                    $percentual_royalties,
                    $valor_royalties,
                    $_SESSION['user_id'] ?? 1
                ]);
                
                $royalty_id = $this->conn->lastInsertId();
                
                $this->logger->info('Royalty criado', [
                    'royalty_id' => $royalty_id,
                    'valor' => $valor_royalties
                ]);
                
                // 2. Se solicitado, gerar boleto/fatura
                if (isset($data['gerar_boleto']) && $data['gerar_boleto']) {
                    $gateway = $data['gateway'] ?? 'cora'; // padrão: cora
                    
                    $resultado_boleto = $this->gerarBoleto($royalty_id, $gateway);
                    
                    if (!$resultado_boleto['success']) {
                        // Reverter transação se falhar
                        $this->conn->rollBack();
                        return $resultado_boleto;
                    }
                }
                
                $this->conn->commit();
                
                return [
                    'success' => true,
                    'message' => 'Royalty criado com sucesso',
                    'royalty_id' => $royalty_id
                ];
                
            } catch (Exception $e) {
                $this->conn->rollBack();
                throw $e;
            }
            
        } catch (Exception $e) {
            $this->logger->error('Erro ao criar royalty', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Erro ao criar royalty: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Gerar boleto (Cora) ou fatura (Stripe) para um royalty
     */
    public function gerarBoleto($royalty_id, $gateway = 'cora') {
        try {
            $this->logger->startOperation('Geração de boleto/fatura', [
                'royalty_id' => $royalty_id,
                'gateway' => $gateway
            ]);
            
            // Buscar dados do royalty
            $stmt = $this->conn->prepare("
                SELECT r.*, e.name as estabelecimento_nome, e.email_alerta, e.document as cnpj
                FROM royalties r
                JOIN estabelecimentos e ON r.estabelecimento_id = e.id
                WHERE r.id = ?
            ");
            $stmt->execute([$royalty_id]);
            $royalty = $stmt->fetch();
            
            if (!$royalty) {
                return [
                    'success' => false,
                    'error' => 'Royalty não encontrado'
                ];
            }
            
            if ($gateway === 'cora') {
                return $this->gerarBoletoCora($royalty);
            } elseif ($gateway === 'stripe') {
                return $this->gerarFaturaStripe($royalty);
            } else {
                return [
                    'success' => false,
                    'error' => 'Gateway não suportado: ' . $gateway
                ];
            }
            
        } catch (Exception $e) {
            $this->logger->error('Erro ao gerar boleto', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Erro ao gerar boleto: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Gerar boleto via Cora
     */
    private function gerarBoletoCora($royalty) {
        try {
            // Inicializar API Cora
            if (!$this->initCoraAPI($royalty['estabelecimento_id'])) {
                return [
                    'success' => false,
                    'error' => 'Falha ao inicializar API Cora'
                ];
            }
            
            // Preparar dados do boleto conforme documentação Cora
            $data_vencimento = date('Y-m-d', strtotime('+15 days'));
            
            $boleto_data = [
                'amount' => round($royalty['valor_royalties'] * 100), // Converter para centavos
                'due_date' => $data_vencimento,
                'description' => $royalty['descricao'] ?? 'Royalties',
                'payer' => [
                    'name' => $royalty['estabelecimento_nome'],
                    'document' => preg_replace('/\D/', '', $royalty['cnpj']),
                    'email' => $royalty['email_alerta']
                ],
                'beneficiary' => [
                    'name' => defined('CORA_BENEFICIARY_NAME') ? CORA_BENEFICIARY_NAME : 'Sua Empresa',
                    'document' => defined('CORA_BENEFICIARY_DOCUMENT') ? CORA_BENEFICIARY_DOCUMENT : '',
                    'email' => defined('CORA_BENEFICIARY_EMAIL') ? CORA_BENEFICIARY_EMAIL : ''
                ]
            ];
            
            // Emitir boleto
            $resultado = $this->cora_api->emitirBoleto($boleto_data);
            
            if (!$resultado['success']) {
                return $resultado;
            }
            
            $boleto = $resultado['data'];
            
            // Salvar informações do boleto no banco
            $stmt = $this->conn->prepare("
                UPDATE royalties SET
                    boleto_id = ?,
                    boleto_data_vencimento = ?,
                    status = 'boleto_gerado',
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $boleto['id'],
                $data_vencimento,
                $royalty['id']
            ]);
            
            // Criar registro em faturamentos
            $this->criarFaturamento(
                $royalty['estabelecimento_id'],
                'cora',
                $boleto['id'],
                $royalty['id'],
                $royalty['descricao'],
                $royalty['valor_royalties'],
                'pending',
                $data_vencimento,
                $boleto
            );
            
            $this->logger->success('Boleto Cora gerado com sucesso', [
                'boleto_id' => $boleto['id'],
                'royalty_id' => $royalty['id']
            ]);
            
            return [
                'success' => true,
                'message' => 'Boleto gerado com sucesso',
                'boleto_id' => $boleto['id'],
                'boleto_data' => $boleto,
                'tipo' => 'boleto'
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Erro ao gerar boleto Cora', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Erro ao gerar boleto: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Gerar fatura via Stripe
     */
    private function gerarFaturaStripe($royalty) {
        try {
            // Inicializar API Stripe
            if (!$this->initStripeAPI($royalty['estabelecimento_id'])) {
                return [
                    'success' => false,
                    'error' => 'Falha ao inicializar API Stripe'
                ];
            }
            
            // Preparar dados do cliente
            $customer_data = [
                'email' => $royalty['email_alerta'],
                'name' => $royalty['estabelecimento_nome']
            ];
            
            // Criar fatura completa
            $resultado = $this->stripe_api->createCompleteInvoice(
                $customer_data,
                $royalty['valor_royalties'],
                $royalty['descricao'] ?? 'Royalties',
                [
                    'royalty_id' => $royalty['id'],
                    'estabelecimento_id' => $royalty['estabelecimento_id']
                ],
                15 // dias até vencimento
            );
            
            if (!$resultado['success']) {
                return $resultado;
            }
            
            // Salvar informações da fatura no banco
            $stmt = $this->conn->prepare("
                UPDATE royalties SET
                    boleto_id = ?,
                    boleto_data_vencimento = FROM_UNIXTIME(?),
                    status = 'link_gerado',
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $resultado['invoice_id'],
                $resultado['due_date'],
                $royalty['id']
            ]);
            
            // Criar registro em faturamentos
            $this->criarFaturamento(
                $royalty['estabelecimento_id'],
                'stripe',
                $resultado['invoice_id'],
                $royalty['id'],
                $royalty['descricao'],
                $royalty['valor_royalties'],
                'draft',
                date('Y-m-d', $resultado['due_date']),
                $resultado
            );
            
            $this->logger->success('Fatura Stripe gerada com sucesso', [
                'invoice_id' => $resultado['invoice_id'],
                'royalty_id' => $royalty['id']
            ]);
            
            return [
                'success' => true,
                'message' => 'Fatura gerada com sucesso',
                'invoice_id' => $resultado['invoice_id'],
                'invoice_url' => $resultado['invoice_url'],
                'invoice_pdf' => $resultado['invoice_pdf'],
                'tipo' => 'stripe'
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Erro ao gerar fatura Stripe', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Erro ao gerar fatura: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Criar registro em faturamentos
     */
    private function criarFaturamento($estabelecimento_id, $gateway_type, $gateway_id, $royalty_id, $descricao, $valor, $status, $data_vencimento, $metadados) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO faturamentos (
                    estabelecimento_id,
                    gateway_type,
                    gateway_id,
                    royalty_id,
                    tipo_faturamento,
                    descricao,
                    valor,
                    moeda,
                    status,
                    data_criacao,
                    data_vencimento,
                    metadados,
                    proxima_verificacao
                ) VALUES (?, ?, ?, ?, 'royalty', ?, ?, 'BRL', ?, NOW(), ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))
            ");
            
            $stmt->execute([
                $estabelecimento_id,
                $gateway_type,
                $gateway_id,
                $royalty_id,
                $descricao,
                $valor,
                $status,
                $data_vencimento,
                json_encode($metadados)
            ]);
            
            return true;
        } catch (Exception $e) {
            $this->logger->error('Erro ao criar faturamento', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Verificar status de um faturamento
     */
    public function verificarStatusFaturamento($faturamento_id) {
        try {
            // Buscar faturamento
            $stmt = $this->conn->prepare("
                SELECT * FROM faturamentos WHERE id = ?
            ");
            $stmt->execute([$faturamento_id]);
            $faturamento = $stmt->fetch();
            
            if (!$faturamento) {
                return [
                    'success' => false,
                    'error' => 'Faturamento não encontrado'
                ];
            }
            
            $novo_status = null;
            $dados_verificacao = null;
            
            if ($faturamento['gateway_type'] === 'cora') {
                $resultado = $this->verificarStatusBoletoCoraDB($faturamento);
            } else {
                $resultado = $this->verificarStatusFaturaStripeDB($faturamento);
            }
            
            if ($resultado['success']) {
                $novo_status = $resultado['status'];
                $dados_verificacao = $resultado['dados'];
                
                // Atualizar status se mudou
                if ($novo_status !== $faturamento['status']) {
                    $this->atualizarStatusFaturamento($faturamento_id, $novo_status, $dados_verificacao);
                }
            }
            
            return $resultado;
            
        } catch (Exception $e) {
            $this->logger->error('Erro ao verificar status', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Erro ao verificar status: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Verificar status de boleto Cora
     */
    private function verificarStatusBoletoCoraDB($faturamento) {
        try {
            if (!$this->initCoraAPI($faturamento['estabelecimento_id'])) {
                return [
                    'success' => false,
                    'error' => 'Falha ao inicializar API Cora'
                ];
            }
            
            $resultado = $this->cora_api->obterStatusBoleto($faturamento['gateway_id']);
            
            if (!$resultado['success']) {
                return $resultado;
            }
            
            $status_map = [
                'aguardando' => 'pending',
                'vencido' => 'overdue',
                'pago' => 'paid',
                'cancelado' => 'canceled',
                'rejeitado' => 'rejected'
            ];
            
            $novo_status = $status_map[$resultado['status']] ?? $resultado['status'];
            
            return [
                'success' => true,
                'status' => $novo_status,
                'dados' => $resultado
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Erro ao verificar boleto Cora', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Erro ao verificar boleto: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Verificar status de fatura Stripe
     */
    private function verificarStatusFaturaStripeDB($faturamento) {
        try {
            if (!$this->initStripeAPI($faturamento['estabelecimento_id'])) {
                return [
                    'success' => false,
                    'error' => 'Falha ao inicializar API Stripe'
                ];
            }
            
            $resultado = $this->stripe_api->checkInvoiceStatus($faturamento['gateway_id']);
            
            if (!$resultado['success']) {
                return $resultado;
            }
            
            $status_map = [
                'draft' => 'pending',
                'open' => 'pending',
                'paid' => 'paid',
                'void' => 'canceled',
                'uncollectible' => 'rejected'
            ];
            
            $novo_status = $status_map[$resultado['status']] ?? $resultado['status'];
            
            return [
                'success' => true,
                'status' => $novo_status,
                'dados' => $resultado
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Erro ao verificar fatura Stripe', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Erro ao verificar fatura: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Atualizar status de um faturamento
     */
    private function atualizarStatusFaturamento($faturamento_id, $novo_status, $dados_verificacao) {
        try {
            // Buscar status anterior
            $stmt = $this->conn->prepare("SELECT status FROM faturamentos WHERE id = ?");
            $stmt->execute([$faturamento_id]);
            $faturamento = $stmt->fetch();
            $status_anterior = $faturamento['status'];
            
            // Atualizar faturamento
            $stmt = $this->conn->prepare("
                UPDATE faturamentos SET
                    status = ?,
                    ultima_verificacao = NOW(),
                    proxima_verificacao = DATE_ADD(NOW(), INTERVAL 1 HOUR),
                    tentativas_verificacao = tentativas_verificacao + 1,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$novo_status, $faturamento_id]);
            
            // Se foi pago, atualizar data de pagamento
            if ($novo_status === 'paid' && $status_anterior !== 'paid') {
                $stmt = $this->conn->prepare("
                    UPDATE faturamentos SET
                        data_pagamento = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$faturamento_id]);
            }
            
            // Registrar no histórico
            $stmt = $this->conn->prepare("
                INSERT INTO faturamentos_historico (
                    faturamento_id,
                    status_anterior,
                    status_novo,
                    dados_verificacao
                ) VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $faturamento_id,
                $status_anterior,
                $novo_status,
                json_encode($dados_verificacao)
            ]);
            
            return true;
        } catch (Exception $e) {
            $this->logger->error('Erro ao atualizar status', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Listar faturamentos com filtros
     */
    public function listarFaturamentos($filtros = []) {
        try {
            $query = "SELECT * FROM faturamentos WHERE 1=1";
            $params = [];
            
            if (isset($filtros['estabelecimento_id'])) {
                $query .= " AND estabelecimento_id = ?";
                $params[] = $filtros['estabelecimento_id'];
            }
            
            if (isset($filtros['gateway_type'])) {
                $query .= " AND gateway_type = ?";
                $params[] = $filtros['gateway_type'];
            }
            
            if (isset($filtros['status'])) {
                $query .= " AND status = ?";
                $params[] = $filtros['status'];
            }
            
            if (isset($filtros['data_inicial'])) {
                $query .= " AND DATE(data_criacao) >= ?";
                $params[] = $filtros['data_inicial'];
            }
            
            if (isset($filtros['data_final'])) {
                $query .= " AND DATE(data_criacao) <= ?";
                $params[] = $filtros['data_final'];
            }
            
            $query .= " ORDER BY data_criacao DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            $this->logger->error('Erro ao listar faturamentos', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Processar polling automático de faturamentos pendentes
     */
    public function processarPollingAutomatico() {
        try {
            $this->logger->startOperation('Polling automático de faturamentos');
            
            // Buscar faturamentos que precisam ser verificados
            $stmt = $this->conn->prepare("
                SELECT id FROM faturamentos
                WHERE status IN ('pending', 'awaiting_payment', 'overdue')
                AND (proxima_verificacao IS NULL OR proxima_verificacao <= NOW())
                AND tentativas_verificacao < 50
                LIMIT 100
            ");
            $stmt->execute();
            $faturamentos = $stmt->fetchAll();
            
            $verificados = 0;
            $atualizados = 0;
            
            foreach ($faturamentos as $faturamento) {
                $resultado = $this->verificarStatusFaturamento($faturamento['id']);
                $verificados++;
                
                if ($resultado['success'] && isset($resultado['status'])) {
                    $atualizados++;
                }
            }
            
            $this->logger->success('Polling automático concluído', [
                'verificados' => $verificados,
                'atualizados' => $atualizados
            ]);
            
            return [
                'success' => true,
                'verificados' => $verificados,
                'atualizados' => $atualizados
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Erro no polling automático', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Erro no polling automático: ' . $e->getMessage()
            ];
        }
    }
}
?>
