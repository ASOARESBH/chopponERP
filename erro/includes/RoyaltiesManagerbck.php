<?php
/**
 * RoyaltiesManager - Gerenciador de Royalties - VERSÃO CORRIGIDA
 * Responsável por toda lógica de negócio relacionada a royalties
 * 
 * MELHORIAS IMPLEMENTADAS:
 * ✓ Validação de tipos de metadados
 * ✓ Tratamento robusto de erros
 * ✓ Logging detalhado
 * ✓ Conversão de valores numéricos para string
 */

class RoyaltiesManager {
    private $conn;
    private $stripe;
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
     * Criar novo royalty
     */
    public function criar($dados) {
        try {
            // Validar dados
            $this->validarDados($dados);
            
            // Calcular royalties
            $valorFaturamento = $this->converterMoeda($dados['valor_faturamento_bruto']);
            $valorRoyalties = $this->calcularRoyalties($valorFaturamento);
            
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
                $dados['forma_pagamento'],
                $dados['email_cobranca'],
                $dados['emails_adicionais'] ?? null,
                $dados['data_vencimento'],
                $dados['observacoes'] ?? null,
                $_SESSION['user_id']
            ]);
            
            $royaltyId = $this->conn->lastInsertId();
            
            if ($this->logger) {
                $this->logger->info("Royalty criado com sucesso", [
                    'royalty_id' => $royaltyId,
                    'estabelecimento_id' => $dados['estabelecimento_id'],
                    'valor_royalties' => $valorRoyalties
                ]);
            }
            
            return [
                'success' => true,
                'royalty_id' => $royaltyId,
                'valor_royalties' => $valorRoyalties,
                'message' => 'Royalty criado com sucesso!'
            ];
            
        } catch (Exception $e) {
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
     * ✓ CORRIGIDO: Gerar Payment Link via Stripe com validações robustas
     */
    public function gerarPaymentLink($royaltyId) {
        try {
            // Buscar dados do royalty
            $royalty = $this->buscarPorId($royaltyId);
            
            if (!$royalty) {
                throw new Exception('Royalty não encontrado');
            }
            
            if (!$this->stripe) {
                throw new Exception('Stripe não configurado');
            }
            
            // Buscar dados do estabelecimento
            $stmt = $this->conn->prepare("
                SELECT name, document, email_alerta, phone, address 
                FROM estabelecimentos WHERE id = ?
            ");
            $stmt->execute([$royalty['estabelecimento_id']]);
            $estabelecimento = $stmt->fetch();
            
            if (!$estabelecimento) {
                throw new Exception('Estabelecimento não encontrado');
            }
            
            // Preparar dados para Stripe
            $descricao = sprintf(
                "%s | Período: %s a %s",
                $royalty['descricao'],
                date('d/m/Y', strtotime($royalty['periodo_inicial'])),
                date('d/m/Y', strtotime($royalty['periodo_final']))
            );
            
            // ✓ CORRIGIDO: Converter valores numéricos para string nos metadados
            $metadata = [
                'royalty_id' => (string)$royaltyId,  // Converter para string
                'estabelecimento_id' => (string)$royalty['estabelecimento_id'],  // Converter para string
                'estabelecimento_nome' => (string)$estabelecimento['name'],
                'tipo' => 'royalty',
                'periodo' => $royalty['periodo_inicial'] . ' a ' . $royalty['periodo_final']
            ];
            
            if ($this->logger) {
                $this->logger->info('Preparando dados para Payment Link', [
                    'royalty_id' => $royaltyId,
                    'valor' => $royalty['valor_royalties'],
                    'estabelecimento' => $estabelecimento['name'],
                    'metadata' => $metadata
                ]);
            }
            
            // Criar Payment Link via Stripe
            $resultado = $this->stripe->createCompletePaymentLink(
                $royalty['valor_royalties'],
                "Royalties - " . $estabelecimento['name'] . " | " . $descricao,
                $metadata
            );
            
            if (!$resultado['success']) {
                throw new Exception('Erro Stripe: ' . ($resultado['error'] ?? 'Desconhecido'));
            }
            
            $paymentLink = [
                'url' => $resultado['payment_link_url'],
                'id' => $resultado['payment_link_id']
            ];
            
            // Atualizar royalty com link
            $stmt = $this->conn->prepare("
                UPDATE royalties 
                SET payment_link_url = ?, 
                    payment_link_id = ?,
                    status = 'link_gerado',
                    data_geracao_link = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $paymentLink['url'],
                $paymentLink['id'] ?? null,
                $royaltyId
            ]);
            
            // Criar conta a pagar para o estabelecimento
            $this->criarContaPagar($royalty, $paymentLink['url']);
            
            if ($this->logger) {
                $this->logger->success("Payment link gerado", [
                    'royalty_id' => $royaltyId,
                    'payment_link' => $paymentLink['url']
                ]);
            }
            
            return [
                'success' => true,
                'payment_link' => $paymentLink['url'],
                'payment_link_id' => $paymentLink['id'],
                'message' => 'Link de pagamento gerado com sucesso!'
            ];
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Erro ao gerar payment link", [
                    'royalty_id' => $royaltyId,
                    'error' => $e->getMessage()
                ]);
            }
            
            return [
                'success' => false,
                'message' => 'Erro ao gerar link: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Enviar e-mail de cobrança
     */
    public function enviarEmail($royaltyId) {
        try {
            $royalty = $this->buscarPorId($royaltyId);
            
            if (!$royalty) {
                throw new Exception('Royalty não encontrado');
            }
            
            if (empty($royalty['payment_link_url'])) {
                throw new Exception('Payment link não gerado ainda');
            }
            
            // Buscar dados do estabelecimento
            $stmt = $this->conn->prepare("SELECT name FROM estabelecimentos WHERE id = ?");
            $stmt->execute([$royalty['estabelecimento_id']]);
            $estabelecimento = $stmt->fetch();
            
            // Preparar lista de e-mails
            $emails = [$royalty['email_cobranca']];
            if (!empty($royalty['emails_adicionais'])) {
                $emailsAdicionais = array_map('trim', explode(',', $royalty['emails_adicionais']));
                $emails = array_merge($emails, $emailsAdicionais);
            }
            
            // Gerar corpo do e-mail
            $emailTemplate = new EmailTemplate();
            $assunto = sprintf(
                "Cobrança de Royalties - %s - %s a %s",
                $estabelecimento['name'],
                date('d/m/Y', strtotime($royalty['periodo_inicial'])),
                date('d/m/Y', strtotime($royalty['periodo_final']))
            );
            
            $corpo = $emailTemplate->royaltiesCobranca([
                'estabelecimento' => $estabelecimento['name'],
                'periodo_inicial' => date('d/m/Y', strtotime($royalty['periodo_inicial'])),
                'periodo_final' => date('d/m/Y', strtotime($royalty['periodo_final'])),
                'faturamento_bruto' => number_format($royalty['valor_faturamento_bruto'], 2, ',', '.'),
                'valor_royalties' => number_format($royalty['valor_royalties'], 2, ',', '.'),
                'data_vencimento' => date('d/m/Y', strtotime($royalty['data_vencimento'])),
                'forma_pagamento' => $this->formatarFormaPagamento($royalty['forma_pagamento']),
                'payment_link' => $royalty['payment_link_url'],
                'descricao' => $royalty['descricao']
            ]);
            
            // Enviar e-mail
            $enviado = $this->enviarEmailSMTP($emails, $assunto, $corpo);
            
            if ($enviado) {
                // Atualizar status
                $stmt = $this->conn->prepare("
                    UPDATE royalties 
                    SET status = 'enviado', data_envio_email = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$royaltyId]);
                
                if ($this->logger) {
                    $this->logger->success("E-mail enviado", [
                        'royalty_id' => $royaltyId,
                        'emails' => implode(', ', $emails)
                    ]);
                }
                
                return [
                    'success' => true,
                    'message' => 'E-mail enviado com sucesso para: ' . implode(', ', $emails)
                ];
            } else {
                throw new Exception('Falha ao enviar e-mail');
            }
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Erro ao enviar e-mail", [
                    'royalty_id' => $royaltyId,
                    'error' => $e->getMessage()
                ]);
            }
            
            return [
                'success' => false,
                'message' => 'Erro ao enviar e-mail: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Buscar royalty por ID
     */
    public function buscarPorId($id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM royalties WHERE id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Erro ao buscar royalty", ['id' => $id, 'error' => $e->getMessage()]);
            }
            return null;
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
                $query .= " AND created_at >= ?";
                $params[] = $filtros['data_inicial'] . ' 00:00:00';
            }
            
            if (!empty($filtros['data_final'])) {
                $query .= " AND created_at <= ?";
                $params[] = $filtros['data_final'] . ' 23:59:59';
            }
            
            $query .= " ORDER BY created_at DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Erro ao listar royalties", ['error' => $e->getMessage()]);
            }
            return [];
        }
    }
    
    /**
     * Validar dados do royalty
     */
    private function validarDados($dados) {
        if (empty($dados['estabelecimento_id'])) {
            throw new Exception('Estabelecimento ID é obrigatório');
        }
        
        if (empty($dados['periodo_inicial']) || empty($dados['periodo_final'])) {
            throw new Exception('Período inicial e final são obrigatórios');
        }
        
        if (strtotime($dados['periodo_final']) < strtotime($dados['periodo_inicial'])) {
            throw new Exception('Data final deve ser maior que data inicial');
        }
        
        if (empty($dados['valor_faturamento_bruto']) || $dados['valor_faturamento_bruto'] <= 0) {
            throw new Exception('Valor de faturamento deve ser maior que zero');
        }
        
        if (empty($dados['email_cobranca'])) {
            throw new Exception('E-mail de cobrança é obrigatório');
        }
        
        if (!filter_var($dados['email_cobranca'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('E-mail de cobrança inválido');
        }
    }
    
    /**
     * Converter moeda
     */
    private function converterMoeda($valor) {
        // Remover formatação de moeda se existir
        $valor = str_replace(['R$', '.', ','], ['', '', '.'], $valor);
        return (float)$valor;
    }
    
    /**
     * Formatar forma de pagamento
     */
    private function formatarFormaPagamento($forma) {
        $formas = [
            'boleto' => 'Boleto Bancário',
            'pix' => 'PIX',
            'boleto_pix' => 'Boleto ou PIX',
            'cartao' => 'Cartão de Crédito',
            'transferencia' => 'Transferência Bancária'
        ];
        
        return $formas[$forma] ?? $forma;
    }
    
    /**
     * Enviar e-mail via SMTP
     */
    private function enviarEmailSMTP($emails, $assunto, $corpo) {
        // Implementar conforme sua configuração SMTP
        // Este é um placeholder
        return true;
    }
    
    /**
     * Criar conta a pagar
     */
    private function criarContaPagar($royalty, $payment_link_url) {
        // Implementar conforme sua lógica de negócio
        // Este é um placeholder
        return true;
    }
}
?>
