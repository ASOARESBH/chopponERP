<?php
/**
 * StripeManager - Integração com Stripe
 * Baseado em padrões testados do CRM INLAUDO
 * Documentação: https://stripe.com/docs/invoicing
 */

require_once __DIR__ . '/config.php';

class StripeManager {
    private $secretKey;
    private $publishableKey;
    private $baseUrl = 'https://api.stripe.com/v1';
    
    public function __construct() {
        try {
            $conn = getDBConnection();
            
            // Buscar configuração Stripe
            $stmt = $conn->query("SELECT * FROM stripe_config WHERE ativo = TRUE LIMIT 1");
            $config = $stmt->fetch();
            
            if (!$config) {
                throw new Exception('Integração Stripe não está ativa ou configurada.');
            }
            
            $this->publishableKey = $config['api_key'];
            $this->secretKey = $config['api_secret'];
            
        } catch (Exception $e) {
            Logger::error("Erro ao inicializar StripeManager", [
                'erro' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Criar ou obter customer no Stripe
     */
    public function criarOuObterCustomer($dados) {
        $startTime = microtime(true);
        
        try {
            // Verificar se já existe customer_id
            if (!empty($dados['stripe_customer_id'])) {
                try {
                    $customer = $this->makeRequest('GET', "/customers/{$dados['stripe_customer_id']}");
                    if (!isset($customer['error'])) {
                        Logger::info("Customer Stripe recuperado", [
                            'customer_id' => $customer['id'],
                            'tempo' => microtime(true) - $startTime
                        ]);
                        return $customer['id'];
                    }
                } catch (Exception $e) {
                    // Customer não existe mais, criar novo
                }
            }
            
            // Criar novo customer
            $customerData = [
                'name' => $dados['nome'] ?? $dados['razao_social'] ?? 'Cliente',
                'email' => $dados['email'] ?? '',
                'phone' => $dados['telefone'] ?? $dados['celular'] ?? '',
                'metadata' => [
                    'estabelecimento_id' => $dados['id'] ?? null,
                    'document' => $dados['document'] ?? null
                ]
            ];
            
            $customer = $this->makeRequest('POST', '/customers', $customerData);
            
            if (isset($customer['error'])) {
                throw new Exception('Erro ao criar customer: ' . $customer['error']['message']);
            }
            
            // Salvar customer_id no banco
            $conn = getDBConnection();
            $stmt = $conn->prepare("UPDATE estabelecimentos SET stripe_customer_id = ? WHERE id = ?");
            $stmt->execute([$customer['id'], $dados['id']]);
            
            Logger::info("Customer Stripe criado", [
                'customer_id' => $customer['id'],
                'tempo' => microtime(true) - $startTime
            ]);
            
            return $customer['id'];
            
        } catch (Exception $e) {
            Logger::error("Erro ao criar/obter customer Stripe", [
                'erro' => $e->getMessage(),
                'tempo' => microtime(true) - $startTime
            ]);
            throw $e;
        }
    }
    
    /**
     * Criar fatura (Invoice) no Stripe
     */
    public function criarFatura($dados) {
        $startTime = microtime(true);
        
        try {
            // Validar dados obrigatórios
            $required = ['customer_id', 'descricao', 'valor', 'data_vencimento'];
            foreach ($required as $field) {
                if (empty($dados[$field])) {
                    throw new Exception("Campo obrigatório ausente: $field");
                }
            }
            
            // Criar item da fatura (Invoice Item)
            $invoiceItemData = [
                'customer' => $dados['customer_id'],
                'amount' => (int)($dados['valor'] * 100), // Converter para centavos
                'currency' => 'brl',
                'description' => $dados['descricao']
            ];
            
            $invoiceItem = $this->makeRequest('POST', '/invoiceitems', $invoiceItemData);
            
            if (isset($invoiceItem['error'])) {
                throw new Exception('Erro ao criar item da fatura: ' . $invoiceItem['error']['message']);
            }
            
            // Calcular dias até vencimento
            $diasVencimento = (int)((strtotime($dados['data_vencimento']) - time()) / 86400);
            
            // Criar fatura
            $invoiceData = [
                'customer' => $dados['customer_id'],
                'auto_advance' => true, // Finalizar automaticamente
                'collection_method' => 'send_invoice',
                'days_until_due' => max($diasVencimento, 1),
                'metadata' => [
                    'royalty_id' => $dados['royalty_id'] ?? '',
                    'estabelecimento_id' => $dados['estabelecimento_id'] ?? ''
                ]
            ];
            
            // Se forma de pagamento for boleto, configurar
            if (isset($dados['forma_pagamento']) && $dados['forma_pagamento'] == 'boleto') {
                $invoiceData['payment_settings'] = [
                    'payment_method_types' => ['boleto']
                ];
            }
            
            $invoice = $this->makeRequest('POST', '/invoices', $invoiceData);
            
            if (isset($invoice['error'])) {
                throw new Exception('Erro ao criar fatura: ' . $invoice['error']['message']);
            }
            
            // Finalizar fatura (tornar pagável)
            $invoiceFinalizada = $this->makeRequest('POST', "/invoices/{$invoice['id']}/finalize");
            
            if (isset($invoiceFinalizada['error'])) {
                throw new Exception('Erro ao finalizar fatura: ' . $invoiceFinalizada['error']['message']);
            }
            
            // Extrair informações
            $resultado = [
                'sucesso' => true,
                'invoice_id' => $invoiceFinalizada['id'],
                'numero_fatura' => $invoiceFinalizada['number'],
                'status' => $invoiceFinalizada['status'],
                'valor' => $dados['valor'],
                'data_vencimento' => $dados['data_vencimento'],
                'url_fatura' => $invoiceFinalizada['invoice_pdf'] ?? null,
                'hosted_invoice_url' => $invoiceFinalizada['hosted_invoice_url'] ?? null,
                'payment_intent_id' => $invoiceFinalizada['payment_intent'] ?? null,
                'boleto_url' => null,
                'resposta_completa' => json_encode($invoiceFinalizada)
            ];
            
            // Se tiver boleto, extrair URL
            if (isset($invoiceFinalizada['payment_intent'])) {
                $paymentIntent = $this->makeRequest('GET', "/payment_intents/{$invoiceFinalizada['payment_intent']}");
                if (isset($paymentIntent['next_action']['boleto_display_details']['hosted_voucher_url'])) {
                    $resultado['boleto_url'] = $paymentIntent['next_action']['boleto_display_details']['hosted_voucher_url'];
                }
            }
            
            Logger::info("Fatura Stripe criada", [
                'invoice_id' => $resultado['invoice_id'],
                'tempo' => microtime(true) - $startTime
            ]);
            
            return $resultado;
            
        } catch (Exception $e) {
            Logger::error("Erro ao criar fatura Stripe", [
                'erro' => $e->getMessage(),
                'tempo' => microtime(true) - $startTime
            ]);
            throw $e;
        }
    }
    
    /**
     * Consultar fatura
     */
    public function consultarFatura($invoiceId) {
        $startTime = microtime(true);
        
        try {
            $invoice = $this->makeRequest('GET', "/invoices/$invoiceId");
            
            if (isset($invoice['error'])) {
                throw new Exception('Erro ao consultar fatura: ' . $invoice['error']['message']);
            }
            
            Logger::info("Fatura Stripe consultada", [
                'invoice_id' => $invoiceId,
                'status' => $invoice['status'],
                'tempo' => microtime(true) - $startTime
            ]);
            
            return [
                'invoice_id' => $invoice['id'],
                'status' => $invoice['status'],
                'valor' => ($invoice['amount_due'] ?? 0) / 100,
                'valor_pago' => ($invoice['amount_paid'] ?? 0) / 100,
                'resposta_completa' => json_encode($invoice)
            ];
            
        } catch (Exception $e) {
            Logger::error("Erro ao consultar fatura Stripe", [
                'invoice_id' => $invoiceId,
                'erro' => $e->getMessage(),
                'tempo' => microtime(true) - $startTime
            ]);
            throw $e;
        }
    }
    
    /**
     * Cancelar fatura
     */
    public function cancelarFatura($invoiceId) {
        $startTime = microtime(true);
        
        try {
            $invoice = $this->makeRequest('POST', "/invoices/$invoiceId/void");
            
            if (isset($invoice['error'])) {
                throw new Exception('Erro ao cancelar fatura: ' . $invoice['error']['message']);
            }
            
            Logger::info("Fatura Stripe cancelada", [
                'invoice_id' => $invoiceId,
                'tempo' => microtime(true) - $startTime
            ]);
            
            return [
                'sucesso' => true,
                'invoice_id' => $invoice['id'],
                'status' => $invoice['status']
            ];
            
        } catch (Exception $e) {
            Logger::error("Erro ao cancelar fatura Stripe", [
                'invoice_id' => $invoiceId,
                'erro' => $e->getMessage(),
                'tempo' => microtime(true) - $startTime
            ]);
            throw $e;
        }
    }
    
    /**
     * Fazer requisição à API Stripe
     */
    private function makeRequest($method, $endpoint, $data = null) {
        try {
            $url = $this->baseUrl . $endpoint;
            
            $options = [
                'http' => [
                    'method' => $method,
                    'header' => [
                        'Authorization: Bearer ' . $this->secretKey,
                        'Content-Type: application/x-www-form-urlencoded'
                    ],
                    'timeout' => 30
                ]
            ];
            
            if ($data && in_array($method, ['POST', 'PUT'])) {
                $options['http']['content'] = http_build_query($data);
            }
            
            $context = stream_context_create($options);
            $response = file_get_contents($url, false, $context);
            
            if ($response === false) {
                throw new Exception('Erro ao conectar com API Stripe');
            }
            
            return json_decode($response, true);
            
        } catch (Exception $e) {
            Logger::error("Erro na requisição Stripe", [
                'method' => $method,
                'endpoint' => $endpoint,
                'erro' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Gerar Payment Link (alternativa a Invoice)
     */
    public function gerarPaymentLink($dados) {
        try {
            $linkData = [
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => 'brl',
                            'product_data' => [
                                'name' => $dados['descricao'] ?? 'Pagamento'
                            ],
                            'unit_amount' => (int)($dados['valor'] * 100)
                        ],
                        'quantity' => 1
                    ]
                ],
                'mode' => 'payment',
                'success_url' => $dados['success_url'] ?? SITE_URL . '/sucesso',
                'cancel_url' => $dados['cancel_url'] ?? SITE_URL . '/cancelado'
            ];
            
            $link = $this->makeRequest('POST', '/payment_links', $linkData);
            
            if (isset($link['error'])) {
                throw new Exception('Erro ao gerar payment link: ' . $link['error']['message']);
            }
            
            Logger::info("Payment Link Stripe gerado", [
                'link_id' => $link['id']
            ]);
            
            return [
                'sucesso' => true,
                'link_id' => $link['id'],
                'url' => $link['url']
            ];
            
        } catch (Exception $e) {
            Logger::error("Erro ao gerar payment link", [
                'erro' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
?>
