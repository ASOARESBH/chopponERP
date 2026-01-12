<?php
/**
 * RoyaltiesManagerV3 - Gerenciador de Royalties com Integração Cora
 * Estende RoyaltiesManager com suporte a Cora e Stripe
 */

require_once 'cora_api_v2.php';

class RoyaltiesManagerV3 {
    private $conn;
    private $stripe;
    private $cora;
    private $logger;
    
    const PERCENTUAL_ROYALTIES = 0.07; // 7%
    
    public function __construct($conn) {
        $this->conn = $conn;
        
        // Inicializar Stripe se disponível
        if (class_exists('StripeAPI')) {
            try {
                $this->stripe = new StripeAPI();
            } catch (Exception $e) {
                error_log("Erro ao inicializar Stripe: " . $e->getMessage());
            }
        }
        
        // Inicializar Cora
        try {
            $this->cora = new CoraAPIv2(
                defined('CORA_CLIENT_ID') ? CORA_CLIENT_ID : '',
                defined('CORA_CLIENT_SECRET') ? CORA_CLIENT_SECRET : '',
                defined('CORA_ENVIRONMENT') ? CORA_ENVIRONMENT : 'stage'
            );
        } catch (Exception $e) {
            error_log("Erro ao inicializar Cora: " . $e->getMessage());
        }
        
        // Inicializar Logger se disponível
        if (class_exists('RoyaltiesLogger')) {
            $this->logger = new RoyaltiesLogger();
        }
    }
    
    /**
     * Calcular valor dos royalties (7%)
     */
    public function calcularRoyalties($valorFaturamento) {
        return round($valorFaturamento * self::PERCENTUAL_ROYALTIES, 2);
    }
    
    /**
     * Converter valor em moeda para número
     */
    private function converterMoeda($valor) {
        if (is_string($valor)) {
            $valor = str_replace('R$', '', $valor);
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
        }
        return (float) $valor;
    }
    
    /**
     * Validar dados do royalty
     */
    private function validarDados($dados) {
        if (empty($dados['estabelecimento_id'])) {
            throw new Exception('Estabelecimento é obrigatório');
        }
        if (empty($dados['periodo_inicial']) || empty($dados['periodo_final'])) {
            throw new Exception('Período é obrigatório');
        }
        if (empty($dados['descricao'])) {
            throw new Exception('Descrição é obrigatória');
        }
        if (empty($dados['valor_faturamento_bruto'])) {
            throw new Exception('Valor do faturamento é obrigatório');
        }
        if (empty($dados['email_cobranca'])) {
            throw new Exception('E-mail para cobrança é obrigatório');
        }
        if (empty($dados['data_vencimento'])) {
            throw new Exception('Data de vencimento é obrigatória');
        }
    }
    
    /**
     * Criar novo royalty com geração automática de boleto/fatura
     */
    public function criar($dados) {
        try {
            // Validar dados
            $this->validarDados($dados);
            
            // Calcular royalties
            $valorFaturamento = $this->converterMoeda($dados['valor_faturamento_bruto']);
            $valorRoyalties = $this->calcularRoyalties($valorFaturamento);
            
            // Iniciar transação
            $this->conn->beginTransaction();
            
            // Inserir no banco
            $stmt = $this->conn->prepare("
                INSERT INTO royalties 
                (estabelecimento_id, periodo_inicial, periodo_final, descricao, 
                 valor_faturamento_bruto, valor_royalties, tipo_cobranca, forma_pagamento,
                 email_cobranca, emails_adicionais, data_vencimento, observacoes, 
                 status, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente', ?, NOW())
            ");
            
            $stmt->execute([
                $dados['estabelecimento_id'],
                $dados['periodo_inicial'],
                $dados['periodo_final'],
                $dados['descricao'],
                $valorFaturamento,
                $valorRoyalties,
                $dados['tipo_cobranca'],
                $dados['forma_pagamento'] ?? 'boleto_pix',
                $dados['email_cobranca'],
                $dados['emails_adicionais'] ?? null,
                $dados['data_vencimento'],
                $dados['observacoes'] ?? null,
                $_SESSION['user_id'] ?? 1
            ]);
            
            $royaltyId = $this->conn->lastInsertId();
            
            // Buscar dados do estabelecimento
            $stmt = $this->conn->prepare("SELECT * FROM estabelecimentos WHERE id = ?");
            $stmt->execute([$dados['estabelecimento_id']]);
            $estabelecimento = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$estabelecimento) {
                throw new Exception('Estabelecimento não encontrado');
            }
            
            // Gerar boleto/fatura conforme tipo de cobrança
            $resultado_faturamento = null;
            $gateway_id = null;
            
            if ($dados['tipo_cobranca'] === 'cora') {
                // Gerar boleto Cora
                $resultado_faturamento = $this->gerarBoletoCora(
                    $royaltyId,
                    $estabelecimento,
                    $valorRoyalties,
                    $dados
                );
                
                if ($resultado_faturamento['success']) {
                    $gateway_id = $resultado_faturamento['boleto_id'];
                }
            } else if ($dados['tipo_cobranca'] === 'stripe') {
                // Gerar fatura Stripe
                $resultado_faturamento = $this->gerarFaturaStripe(
                    $royaltyId,
                    $estabelecimento,
                    $valorRoyalties,
                    $dados
                );
                
                if ($resultado_faturamento['success']) {
                    $gateway_id = $resultado_faturamento['invoice_id'];
                }
            }
            
            // Se gerou boleto/fatura com sucesso, criar registro em faturamentos
            if ($resultado_faturamento && $resultado_faturamento['success']) {
                $this->criarRegistroFaturamento(
                    $royaltyId,
                    $dados['estabelecimento_id'],
                    $dados['tipo_cobranca'],
                    $gateway_id,
                    $dados['descricao'],
                    $valorRoyalties,
                    $dados['data_vencimento']
                );
                
                // Atualizar status do royalty
                $stmt = $this->conn->prepare("
                    UPDATE royalties 
                    SET status = 'link_gerado', gateway_id = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$gateway_id, $royaltyId]);
            }
            
            // Commit transação
            $this->conn->commit();
            
            if ($this->logger) {
                $this->logger->info("Royalty criado com sucesso", [
                    'royalty_id' => $royaltyId,
                    'estabelecimento_id' => $dados['estabelecimento_id'],
                    'valor_royalties' => $valorRoyalties,
                    'tipo_cobranca' => $dados['tipo_cobranca'],
                    'gateway_id' => $gateway_id
                ]);
            }
            
            return [
                'success' => true,
                'royalty_id' => $royaltyId,
                'valor_royalties' => $valorRoyalties,
                'gateway_id' => $gateway_id,
                'tipo_cobranca' => $dados['tipo_cobranca'],
                'faturamento' => $resultado_faturamento,
                'message' => 'Royalty criado com sucesso! Boleto/Fatura gerado automaticamente.'
            ];
            
        } catch (Exception $e) {
            // Rollback em caso de erro
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            
            if ($this->logger) {
                $this->logger->error("Erro ao criar royalty", [
                    'error' => $e->getMessage(),
                    'dados' => $dados
                ]);
            }
            
            return [
                'success' => false,
                'message' => 'Erro ao criar royalty: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Gerar boleto Cora
     */
    private function gerarBoletoCora($royaltyId, $estabelecimento, $valor, $dados) {
        try {
            if (!$this->cora) {
                throw new Exception('Cora não está configurado');
            }
            
            // Preparar dados do boleto
            $boletoDados = [
                'amount' => (int) round($valor * 100), // Converter para centavos
                'due_date' => $dados['data_vencimento'],
                'description' => $dados['descricao'],
                'payer' => [
                    'name' => $estabelecimento['name'],
                    'document' => preg_replace('/\D/', '', $estabelecimento['cnpj'] ?? ''),
                    'email' => $dados['email_cobranca'],
                    'phone' => preg_replace('/\D/', '', $estabelecimento['phone'] ?? '')
                ],
                'beneficiary' => [
                    'name' => defined('CORA_BENEFICIARY_NAME') ? CORA_BENEFICIARY_NAME : 'Sua Empresa',
                    'document' => defined('CORA_BENEFICIARY_DOCUMENT') ? CORA_BENEFICIARY_DOCUMENT : '',
                    'email' => defined('CORA_BENEFICIARY_EMAIL') ? CORA_BENEFICIARY_EMAIL : ''
                ]
            ];
            
            // Emitir boleto
            $resultado = $this->cora->emitirBoleto($boletoDados);
            
            if (!$resultado || !isset($resultado['id'])) {
                throw new Exception('Erro ao emitir boleto: resposta inválida');
            }
            
            return [
                'success' => true,
                'boleto_id' => $resultado['id'],
                'boleto_data' => $resultado,
                'message' => 'Boleto gerado com sucesso'
            ];
            
        } catch (Exception $e) {
            error_log("Erro ao gerar boleto Cora: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao gerar boleto: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Gerar fatura Stripe
     */
    private function gerarFaturaStripe($royaltyId, $estabelecimento, $valor, $dados) {
        try {
            if (!$this->stripe) {
                throw new Exception('Stripe não está configurado');
            }
            
            // Criar fatura completa
            $resultado = $this->stripe->createCompleteInvoice(
                [
                    'email' => $dados['email_cobranca'],
                    'name' => $estabelecimento['name']
                ],
                $valor,
                $dados['descricao'],
                ['royalty_id' => $royaltyId],
                30 // dias até vencimento
            );
            
            if (!$resultado || !isset($resultado['id'])) {
                throw new Exception('Erro ao criar fatura: resposta inválida');
            }
            
            return [
                'success' => true,
                'invoice_id' => $resultado['id'],
                'invoice_data' => $resultado,
                'message' => 'Fatura criada com sucesso'
            ];
            
        } catch (Exception $e) {
            error_log("Erro ao gerar fatura Stripe: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao gerar fatura: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Criar registro em faturamentos
     */
    private function criarRegistroFaturamento($royaltyId, $estabelecimentoId, $gatewayType, $gatewayId, $descricao, $valor, $dataVencimento) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO faturamentos 
                (estabelecimento_id, gateway_type, gateway_id, royalty_id, descricao, 
                 valor, moeda, status, data_criacao, data_vencimento, 
                 ultima_verificacao, proxima_verificacao, tentativas_verificacao, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'BRL', 'pending', NOW(), ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 HOUR), 0, NOW())
            ");
            
            $stmt->execute([
                $estabelecimentoId,
                $gatewayType,
                $gatewayId,
                $royaltyId,
                $descricao,
                $valor,
                $dataVencimento
            ]);
            
            return true;
        } catch (Exception $e) {
            error_log("Erro ao criar registro de faturamento: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Listar royalties com filtros
     */
    public function listar($filtros = []) {
        try {
            $query = "SELECT * FROM royalties WHERE 1=1";
            $params = [];
            
            if (!empty($filtros['estabelecimento_id'])) {
                $query .= " AND estabelecimento_id = ?";
                $params[] = $filtros['estabelecimento_id'];
            }
            
            if (!empty($filtros['status'])) {
                $query .= " AND status = ?";
                $params[] = $filtros['status'];
            }
            
            if (!empty($filtros['data_inicial'])) {
                $query .= " AND DATE(created_at) >= ?";
                $params[] = $filtros['data_inicial'];
            }
            
            if (!empty($filtros['data_final'])) {
                $query .= " AND DATE(created_at) <= ?";
                $params[] = $filtros['data_final'];
            }
            
            $query .= " ORDER BY created_at DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erro ao listar royalties: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obter detalhes de um royalty
     */
    public function obter($id) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM royalties WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erro ao obter royalty: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Atualizar status de um royalty
     */
    public function atualizarStatus($id, $novoStatus) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE royalties 
                SET status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([$novoStatus, $id]);
            return true;
        } catch (Exception $e) {
            error_log("Erro ao atualizar status: " . $e->getMessage());
            return false;
        }
    }
}
?>
