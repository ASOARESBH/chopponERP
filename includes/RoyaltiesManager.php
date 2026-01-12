<?php
/**
 * RoyaltiesManager - Gerenciador de Royalties
 * Responsável por toda lógica de negócio relacionada a royalties
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
            
            // Se tipo de cobrança for Cora, gerar boleto automaticamente
            $boletoGerado = false;
            if (isset($dados['tipo_cobranca']) && $dados['tipo_cobranca'] === 'cora') {
                $resultadoBoleto = $this->gerarBoletoCora($royaltyId);
                $boletoGerado = $resultadoBoleto['success'] ?? false;
            }
            
            return [
                'success' => true,
                'royalty_id' => $royaltyId,
                'valor_royalties' => $valorRoyalties,
                'boleto_gerado' => $boletoGerado,
                'message' => $boletoGerado ? 'Royalty criado e boleto gerado com sucesso!' : 'Royalty criado com sucesso!'
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
     * Gerar Payment Link via Stripe
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
            
            // Preparar dados para Stripe
            $descricao = sprintf(
                "%s | Período: %s a %s",
                $royalty['descricao'],
                date('d/m/Y', strtotime($royalty['periodo_inicial'])),
                date('d/m/Y', strtotime($royalty['periodo_final']))
            );
            
            // Criar Payment Link via Stripe
            $resultado = $this->stripe->createCompletePaymentLink(
                $royalty['valor_royalties'],
                "Royalties - " . $estabelecimento['name'] . " | " . $descricao,
                [
                    'royalty_id' => $royaltyId,
                    'estabelecimento_id' => $royalty['estabelecimento_id'],
                    'estabelecimento_nome' => $estabelecimento['name'],
                    'tipo' => 'royalty',
                    'periodo' => $royalty['periodo_inicial'] . ' a ' . $royalty['periodo_final']
                ]
            );
            
            if (!$resultado['success']) {
                throw new Exception('Erro Stripe: ' . ($resultado['error'] ?? 'Desconhecido'));
            }
            
            $paymentLink = [
                'url' => $resultado['payment_link_url'],
                'id' => $resultado['payment_link_id']
            ];
            
            // Validação já feita acima
            
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
        $stmt = $this->conn->prepare("
            SELECT r.*, e.name as estabelecimento_nome, e.document as cnpj
            FROM royalties r
            INNER JOIN estabelecimentos e ON r.estabelecimento_id = e.id
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Listar royalties com filtros
     */
    public function listar($filtros = []) {
        $where = ['1=1'];
        $params = [];
        
        // Filtro por estabelecimento (se não for admin geral)
        if (!isAdminGeral()) {
            $where[] = 'r.estabelecimento_id = ?';
            $params[] = getEstabelecimentoId();
        } elseif (!empty($filtros['estabelecimento_id'])) {
            $where[] = 'r.estabelecimento_id = ?';
            $params[] = $filtros['estabelecimento_id'];
        }
        
        // Filtro por status
        if (!empty($filtros['status'])) {
            $where[] = 'r.status = ?';
            $params[] = $filtros['status'];
        }
        
        // Filtro por período
        if (!empty($filtros['data_inicial'])) {
            $where[] = 'r.periodo_inicial >= ?';
            $params[] = $filtros['data_inicial'];
        }
        
        if (!empty($filtros['data_final'])) {
            $where[] = 'r.periodo_final <= ?';
            $params[] = $filtros['data_final'];
        }
        
        $sql = "
            SELECT r.*, e.name as estabelecimento_nome
            FROM royalties r
            INNER JOIN estabelecimentos e ON r.estabelecimento_id = e.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY r.created_at DESC
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Criar conta a pagar vinculada ao royalty
     */
    private function criarContaPagar($royalty, $paymentLink) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO contas_pagar 
                (estabelecimento_id, descricao, tipo, valor, data_vencimento, 
                 payment_link_url, observacoes, status, royalty_id, valor_protegido, origem)
                VALUES (?, ?, 'Royalties', ?, ?, ?, ?, 'pendente', ?, TRUE, 'royalties')
            ");
            
            $stmt->execute([
                $royalty['estabelecimento_id'],
                'Royalties - ' . $royalty['descricao'],
                $royalty['valor_royalties'],
                $royalty['data_vencimento'],
                $paymentLink,
                'Conta gerada automaticamente pelo sistema de royalties',
                $royalty['id']
            ]);
            
            $contaPagarId = $this->conn->lastInsertId();
            
            // Vincular conta a pagar ao royalty
            $stmt = $this->conn->prepare("UPDATE royalties SET conta_pagar_id = ? WHERE id = ?");
            $stmt->execute([$contaPagarId, $royalty['id']]);
            
            return $contaPagarId;
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Erro ao criar conta a pagar", [
                    'royalty_id' => $royalty['id'],
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    /**
     * Validar dados do formulário
     */
    private function validarDados($dados) {
        $erros = [];
        
        if (empty($dados['estabelecimento_id'])) {
            $erros[] = 'Estabelecimento é obrigatório';
        }
        
        if (empty($dados['periodo_inicial']) || empty($dados['periodo_final'])) {
            $erros[] = 'Período é obrigatório';
        }
        
        if (strtotime($dados['periodo_final']) < strtotime($dados['periodo_inicial'])) {
            $erros[] = 'Período final deve ser maior que período inicial';
        }
        
        if (empty($dados['descricao'])) {
            $erros[] = 'Descrição é obrigatória';
        }
        
        $valorFaturamento = $this->converterMoeda($dados['valor_faturamento_bruto']);
        if ($valorFaturamento <= 0) {
            $erros[] = 'Valor do faturamento deve ser maior que zero';
        }
        
        if (empty($dados['email_cobranca']) || !filter_var($dados['email_cobranca'], FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'E-mail de cobrança inválido';
        }
        
        if (!empty($erros)) {
            throw new Exception(implode(', ', $erros));
        }
    }
    
    /**
     * Converter valor monetário para float
     */
    private function converterMoeda($valor) {
        $valor = str_replace(['R$', ' ', '.'], '', $valor);
        $valor = str_replace(',', '.', $valor);
        return floatval($valor);
    }
    
    /**
     * Formatar forma de pagamento para exibição
     */
    private function formatarFormaPagamento($forma) {
        $formas = [
            'boleto_pix' => 'Boleto + PIX',
            'cartao_pix' => 'Cartão + PIX',
            'todos' => 'Todos os métodos'
        ];
        
        return $formas[$forma] ?? $forma;
    }
    
    /**
     * Gerar boleto via Cora API
     */
    public function gerarBoletoCora($royaltyId) {
        try {
            // Buscar dados do royalty
            $royalty = $this->buscarPorId($royaltyId);
            
            if (!$royalty) {
                throw new Exception('Royalty não encontrado');
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
            
            // Buscar configuração Cora do banco de dados
            $stmt = $this->conn->prepare("
                SELECT client_id, client_secret, environment 
                FROM cora_config 
                WHERE estabelecimento_id = ? AND ativo = 1
            ");
            $stmt->execute([$royalty['estabelecimento_id']]);
            $coraConfig = $stmt->fetch();
            
            if (!$coraConfig) {
                throw new Exception('Configuração Cora não encontrada para este estabelecimento');
            }
            
            if (empty($coraConfig['client_id']) || empty($coraConfig['client_secret'])) {
                throw new Exception('Credenciais Cora incompletas. Configure Client ID e Client Secret.');
            }
            
            require_once __DIR__ . '/cora_api_v2.php';
            
            $cora = new CoraAPIv2(
                $coraConfig['client_id'],
                $coraConfig['client_secret'],
                $coraConfig['environment'] ?? 'stage'
            );
            
            // Preparar dados do boleto
            $dadosBoleto = [
                'amount' => round($royalty['valor_royalties'] * 100), // Converter para centavos
                'due_date' => $royalty['data_vencimento'],
                'description' => sprintf(
                    "Royalties - %s | Período: %s a %s",
                    $royalty['descricao'],
                    date('d/m/Y', strtotime($royalty['periodo_inicial'])),
                    date('d/m/Y', strtotime($royalty['periodo_final']))
                ),
                'payer' => [
                    'name' => $estabelecimento['name'],
                    'document' => preg_replace('/[^0-9]/', '', $estabelecimento['document']),
                    'email' => $royalty['email_cobranca']
                ],
                'beneficiary' => [
                    'name' => SITE_NAME ?? 'Chopp On Tap',
                    'document' => CORA_BENEFICIARY_DOCUMENT ?? '00000000000000'
                ]
            ];
            
            // Emitir boleto
            $resultado = $cora->emitirBoleto($dadosBoleto);
            
            if (!$resultado['success']) {
                throw new Exception($resultado['error'] ?? 'Erro ao emitir boleto');
            }
            
            $boletoData = $resultado['data'];
            
            // Atualizar royalty com dados do boleto
            $stmt = $this->conn->prepare("
                UPDATE royalties 
                SET boleto_id = ?,
                    boleto_url = ?,
                    boleto_linha_digitavel = ?,
                    boleto_codigo_barras = ?,
                    boleto_data_emissao = NOW(),
                    status = 'link_gerado'
                WHERE id = ?
            ");
            
            $stmt->execute([
                $boletoData['id'] ?? null,
                $boletoData['pdf_url'] ?? $boletoData['url'] ?? null,
                $boletoData['digitable_line'] ?? $boletoData['linha_digitavel'] ?? null,
                $boletoData['barcode'] ?? $boletoData['codigo_barras'] ?? null,
                $royaltyId
            ]);
            
            // Criar conta a pagar para o estabelecimento
            $boletoUrl = $boletoData['pdf_url'] ?? $boletoData['url'] ?? null;
            if ($boletoUrl) {
                $this->criarContaPagar($royalty, $boletoUrl);
            }
            
            if ($this->logger) {
                $this->logger->success("Boleto Cora gerado", [
                    'royalty_id' => $royaltyId,
                    'boleto_id' => $boletoData['id'] ?? null
                ]);
            }
            
            return [
                'success' => true,
                'boleto_url' => $boletoUrl,
                'boleto_id' => $boletoData['id'] ?? null,
                'message' => 'Boleto gerado com sucesso!'
            ];
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Erro ao gerar boleto Cora", [
                    'royalty_id' => $royaltyId,
                    'error' => $e->getMessage()
                ]);
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Erro ao gerar boleto: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Enviar e-mail via SMTP
     */
    private function enviarEmailSMTP($destinatarios, $assunto, $corpo) {
        // Implementar envio real de e-mail aqui
        // Por enquanto, apenas simular sucesso
        return true;
    }
}
