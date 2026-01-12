<?php
/**
 * CoraManager - Gerenciador de Boletos Banco Cora
 * Responsável por integração com API Cora para geração de boletos
 */

class CoraManager {
    private $conn;
    private $logger;
    private $client_id;
    private $certificate_path;
    private $private_key_path;
    private $ambiente;
    private $api_base_url;
    
    const SANDBOX_URL = 'https://sandbox-api.cora.com.br';
    const PRODUCTION_URL = 'https://api.cora.com.br';
    
    public function __construct($conn, $estabelecimento_id = null) {
        $this->conn = $conn;
        
        // Inicializar Logger se disponível
        if (class_exists('RoyaltiesLogger')) {
            $this->logger = new RoyaltiesLogger('cora');
        }
        
        // Carregar configuração do Cora
        $this->carregarConfiguracao($estabelecimento_id);
    }
    
    /**
     * Carregar configuração do Cora do banco
     */
    private function carregarConfiguracao($estabelecimento_id) {
        try {
            if ($estabelecimento_id) {
                $stmt = $this->conn->prepare("
                    SELECT * FROM cora_config 
                    WHERE estabelecimento_id = ? AND ativo = 1 
                    LIMIT 1
                ");
                $stmt->execute([$estabelecimento_id]);
            } else {
                $stmt = $this->conn->query("
                    SELECT * FROM cora_config 
                    WHERE ativo = 1 
                    LIMIT 1
                ");
            }
            
            $config = $stmt->fetch();
            
            if (!$config) {
                throw new Exception('Configuração Cora não encontrada ou inativa');
            }
            
            $this->client_id = $config['cora_client_id'];
            $this->certificate_path = $config['cora_certificate_path'];
            $this->private_key_path = $config['cora_private_key_path'];
            $this->ambiente = $config['ambiente'] ?? 'sandbox';
            
            // Definir URL da API
            $this->api_base_url = ($this->ambiente === 'production') 
                ? self::PRODUCTION_URL 
                : self::SANDBOX_URL;
            
            if ($this->logger) {
                $this->logger->info("Configuração Cora carregada", [
                    'ambiente' => $this->ambiente,
                    'client_id' => substr($this->client_id, 0, 10) . '...'
                ]);
            }
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Erro ao carregar configuração Cora", [
                    'error' => $e->getMessage()
                ]);
            }
            throw $e;
        }
    }
    
    /**
     * Gerar boleto via API Cora
     */
    public function gerarBoleto($dados) {
        try {
            // Validar dados
            $this->validarDadosBoleto($dados);
            
            // Preparar payload para API Cora
            $payload = [
                'amount' => $dados['valor'] * 100, // Converter para centavos
                'description' => substr($dados['descricao'], 0, 255),
                'due_date' => $dados['data_vencimento'],
                'payer' => [
                    'name' => $dados['nome_pagador'],
                    'email' => $dados['email_pagador'],
                    'document' => $this->limparDocumento($dados['documento_pagador']),
                    'phone' => $dados['telefone_pagador'] ?? null
                ],
                'recipient' => [
                    'name' => $dados['nome_recebedor'],
                    'document' => $this->limparDocumento($dados['documento_recebedor'])
                ],
                'notifications' => [
                    'email' => $dados['email_notificacao'] ?? $dados['email_pagador']
                ]
            ];
            
            // Fazer requisição à API Cora
            $response = $this->fazerRequisicao('POST', '/v1/billet', $payload);
            
            if (!isset($response['id'])) {
                throw new Exception('Erro na resposta da API Cora: ' . json_encode($response));
            }
            
            // Extrair dados do boleto
            $boleto = [
                'cora_id' => $response['id'],
                'linha_digitavel' => $response['line'] ?? null,
                'codigo_barras' => $response['barcode'] ?? null,
                'qrcode_pix' => $response['qr_code'] ?? null,
                'url_boleto' => $response['url'] ?? null,
                'data_vencimento' => $dados['data_vencimento'],
                'valor' => $dados['valor'],
                'status' => 'gerado'
            ];
            
            if ($this->logger) {
                $this->logger->success("Boleto gerado com sucesso", [
                    'cora_id' => $boleto['cora_id'],
                    'valor' => $dados['valor']
                ]);
            }
            
            return [
                'success' => true,
                'boleto' => $boleto,
                'message' => 'Boleto gerado com sucesso!'
            ];
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Erro ao gerar boleto", [
                    'error' => $e->getMessage(),
                    'dados' => $dados
                ]);
            }
            
            return [
                'success' => false,
                'message' => 'Erro ao gerar boleto: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Consultar status do boleto
     */
    public function consultarBoleto($cora_id) {
        try {
            if (empty($cora_id)) {
                throw new Exception('ID Cora não fornecido');
            }
            
            $response = $this->fazerRequisicao('GET', '/v1/billet/' . $cora_id);
            
            if (!isset($response['id'])) {
                throw new Exception('Boleto não encontrado');
            }
            
            return [
                'success' => true,
                'boleto' => $response
            ];
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Erro ao consultar boleto", [
                    'cora_id' => $cora_id,
                    'error' => $e->getMessage()
                ]);
            }
            
            return [
                'success' => false,
                'message' => 'Erro ao consultar boleto: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Fazer requisição à API Cora com certificado
     */
    private function fazerRequisicao($metodo, $endpoint, $dados = null) {
        try {
            $url = $this->api_base_url . $endpoint;
            
            // Verificar se os certificados existem
            if (!file_exists($this->certificate_path) || !file_exists($this->private_key_path)) {
                throw new Exception('Certificados do Cora não encontrados');
            }
            
            $ch = curl_init();
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $metodo,
                CURLOPT_SSLCERT => $this->certificate_path,
                CURLOPT_SSLKEY => $this->private_key_path,
                CURLOPT_SSLCERTTYPE => 'PEM',
                CURLOPT_SSLKEYTYPE => 'PEM',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->client_id,
                    'User-Agent: ChoppOnTap/1.0'
                ]
            ]);
            
            // Adicionar corpo da requisição se houver
            if ($dados && in_array($metodo, ['POST', 'PUT', 'PATCH'])) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
            }
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            
            curl_close($ch);
            
            if ($curl_error) {
                throw new Exception('Erro CURL: ' . $curl_error);
            }
            
            if ($http_code >= 400) {
                throw new Exception('Erro HTTP ' . $http_code . ': ' . $response);
            }
            
            $decoded = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Erro ao decodificar resposta JSON: ' . json_last_error_msg());
            }
            
            return $decoded;
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Erro na requisição Cora", [
                    'metodo' => $metodo,
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage()
                ]);
            }
            throw $e;
        }
    }
    
    /**
     * Validar dados do boleto
     */
    private function validarDadosBoleto($dados) {
        $erros = [];
        
        if (empty($dados['valor']) || $dados['valor'] <= 0) {
            $erros[] = 'Valor deve ser maior que zero';
        }
        
        if (empty($dados['descricao'])) {
            $erros[] = 'Descrição é obrigatória';
        }
        
        if (empty($dados['data_vencimento'])) {
            $erros[] = 'Data de vencimento é obrigatória';
        }
        
        if (empty($dados['nome_pagador'])) {
            $erros[] = 'Nome do pagador é obrigatório';
        }
        
        if (empty($dados['email_pagador']) || !filter_var($dados['email_pagador'], FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'E-mail do pagador inválido';
        }
        
        if (empty($dados['documento_pagador'])) {
            $erros[] = 'Documento do pagador é obrigatório';
        }
        
        if (empty($dados['nome_recebedor'])) {
            $erros[] = 'Nome do recebedor é obrigatório';
        }
        
        if (empty($dados['documento_recebedor'])) {
            $erros[] = 'Documento do recebedor é obrigatório';
        }
        
        if (!empty($erros)) {
            throw new Exception(implode(', ', $erros));
        }
    }
    
    /**
     * Limpar documento (remover caracteres especiais)
     */
    private function limparDocumento($documento) {
        return preg_replace('/[^0-9]/', '', $documento);
    }
}
