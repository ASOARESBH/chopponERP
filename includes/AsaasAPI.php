<?php
/**
 * Classe AsaasAPI
 * Integração completa com API do Asaas para pagamentos
 * 
 * Documentação: https://docs.asaas.com/
 * 
 * @author ChopponERP
 * @version 1.0.0
 * @date 2026-01-12
 */

class AsaasAPI {
    private $conn;
    private $api_key;
    private $webhook_token;
    private $ambiente;
    private $base_url;
    private $estabelecimento_id;
    
    /**
     * Construtor
     * 
     * @param PDO $conn Conexão com banco de dados
     * @param int $estabelecimento_id ID do estabelecimento (opcional)
     */
    public function __construct($conn, $estabelecimento_id = null) {
        $this->conn = $conn;
        $this->estabelecimento_id = $estabelecimento_id;
        
        if ($estabelecimento_id) {
            $this->carregarConfiguracao($estabelecimento_id);
        }
    }
    
    /**
     * Carregar configuração do Asaas para um estabelecimento
     * 
     * @param int $estabelecimento_id
     * @throws Exception Se configuração não encontrada
     */
    private function carregarConfiguracao($estabelecimento_id) {
        $stmt = $this->conn->prepare("
            SELECT * FROM asaas_config 
            WHERE estabelecimento_id = ? AND ativo = 1
        ");
        $stmt->execute([$estabelecimento_id]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$config) {
            throw new Exception("Configuração do Asaas não encontrada para este estabelecimento.");
        }
        
        $this->api_key = $config['asaas_api_key'];
        $this->webhook_token = $config['asaas_webhook_token'];
        $this->ambiente = $config['ambiente'];
        
        // URL base conforme ambiente
        $this->base_url = ($this->ambiente === 'production') 
            ? 'https://api.asaas.com/v3' 
            : 'https://api-sandbox.asaas.com/v3';
            
        $this->log('info', 'Configuração carregada', [
            'estabelecimento_id' => $estabelecimento_id,
            'ambiente' => $this->ambiente
        ]);
    }
    
    /**
     * Criar cliente no Asaas
     * 
     * @param array $dados Dados do cliente
     * @return array Resposta da API com customer_id
     */
    public function criarCliente($dados) {
        $url = $this->base_url . '/customers';
        
        $payload = [
            'name' => $dados['nome'],
            'cpfCnpj' => $dados['cpf_cnpj'],
            'email' => $dados['email'] ?? null,
            'phone' => $dados['telefone'] ?? null,
            'mobilePhone' => $dados['celular'] ?? null,
            'address' => $dados['endereco'] ?? null,
            'addressNumber' => $dados['numero'] ?? null,
            'complement' => $dados['complemento'] ?? null,
            'province' => $dados['bairro'] ?? null,
            'postalCode' => $dados['cep'] ?? null,
            'externalReference' => $dados['referencia_externa'] ?? null,
            'notificationDisabled' => $dados['desabilitar_notificacao'] ?? false,
            'additionalEmails' => $dados['emails_adicionais'] ?? null,
            'municipalInscription' => $dados['inscricao_municipal'] ?? null,
            'stateInscription' => $dados['inscricao_estadual'] ?? null,
            'observations' => $dados['observacoes'] ?? null
        ];
        
        // Remover campos nulos
        $payload = array_filter($payload, function($value) {
            return $value !== null;
        });
        
        $this->log('info', 'Criando cliente', ['payload' => $payload]);
        
        try {
            $response = $this->fazerRequisicao('POST', $url, $payload);
            
            // Salvar mapeamento no banco
            if (isset($response['id']) && isset($dados['cliente_id'])) {
                $this->salvarMapeamentoCliente($dados['cliente_id'], $response['id'], $dados['cpf_cnpj']);
            }
            
            $this->log('sucesso', 'Cliente criado', ['customer_id' => $response['id'] ?? null]);
            
            return $response;
        } catch (Exception $e) {
            $this->log('erro', 'Erro ao criar cliente', ['erro' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Buscar ou criar cliente no Asaas
     * Se o cliente já existe no mapeamento, retorna o customer_id
     * Caso contrário, cria um novo cliente
     * 
     * @param int $cliente_id ID do cliente local
     * @param array $dados_cliente Dados do cliente para criação
     * @return string customer_id do Asaas
     */
    public function buscarOuCriarCliente($cliente_id, $dados_cliente) {
        // Verificar se já existe mapeamento
        $stmt = $this->conn->prepare("
            SELECT asaas_customer_id 
            FROM asaas_clientes 
            WHERE cliente_id = ? AND estabelecimento_id = ?
        ");
        $stmt->execute([$cliente_id, $this->estabelecimento_id]);
        $mapeamento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($mapeamento) {
            $this->log('info', 'Cliente já existe', ['customer_id' => $mapeamento['asaas_customer_id']]);
            return $mapeamento['asaas_customer_id'];
        }
        
        // Criar novo cliente
        $dados_cliente['cliente_id'] = $cliente_id;
        $response = $this->criarCliente($dados_cliente);
        
        return $response['id'];
    }
    
    /**
     * Criar cobrança no Asaas
     * 
     * @param array $dados Dados da cobrança
     * @return array Resposta da API com payment_id
     */
    public function criarCobranca($dados) {
        $url = $this->base_url . '/payments';
        
        $payload = [
            'customer' => $dados['customer_id'],
            'billingType' => $dados['tipo_cobranca'], // BOLETO, CREDIT_CARD, PIX, UNDEFINED
            'value' => (float)$dados['valor'],
            'dueDate' => $dados['data_vencimento'], // YYYY-MM-DD
            'description' => $dados['descricao'] ?? null,
            'externalReference' => $dados['referencia_externa'] ?? null,
            'installmentCount' => $dados['numero_parcelas'] ?? null,
            'installmentValue' => $dados['valor_parcela'] ?? null,
            'postalService' => $dados['enviar_correios'] ?? false
        ];
        
        // Desconto
        if (isset($dados['desconto'])) {
            $payload['discount'] = $dados['desconto'];
        }
        
        // Juros
        if (isset($dados['juros'])) {
            $payload['interest'] = $dados['juros'];
        }
        
        // Multa
        if (isset($dados['multa'])) {
            $payload['fine'] = $dados['multa'];
        }
        
        // Split de pagamento
        if (isset($dados['split'])) {
            $payload['split'] = $dados['split'];
        }
        
        // Callback (redirecionamento após pagamento)
        if (isset($dados['callback'])) {
            $payload['callback'] = $dados['callback'];
        }
        
        // Remover campos nulos
        $payload = array_filter($payload, function($value) {
            return $value !== null;
        });
        
        $this->log('info', 'Criando cobrança', ['payload' => $payload]);
        
        try {
            $response = $this->fazerRequisicao('POST', $url, $payload);
            
            // Salvar pagamento no banco
            if (isset($response['id'])) {
                $this->salvarPagamento($response);
            }
            
            $this->log('sucesso', 'Cobrança criada', ['payment_id' => $response['id'] ?? null]);
            
            return $response;
        } catch (Exception $e) {
            $this->log('erro', 'Erro ao criar cobrança', ['erro' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Consultar cobrança no Asaas
     * 
     * @param string $payment_id ID do pagamento no Asaas
     * @return array Dados da cobrança
     */
    public function consultarCobranca($payment_id) {
        $url = $this->base_url . '/payments/' . $payment_id;
        
        $this->log('info', 'Consultando cobrança', ['payment_id' => $payment_id]);
        
        try {
            $response = $this->fazerRequisicao('GET', $url);
            
            // Atualizar pagamento no banco
            if (isset($response['id'])) {
                $this->atualizarPagamento($response);
            }
            
            return $response;
        } catch (Exception $e) {
            $this->log('erro', 'Erro ao consultar cobrança', ['erro' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Obter QR Code PIX de uma cobrança
     * 
     * @param string $payment_id ID do pagamento no Asaas
     * @return array Dados do QR Code (payload e encodedImage)
     */
    public function obterQRCodePix($payment_id) {
        $url = $this->base_url . '/payments/' . $payment_id . '/pixQrCode';
        
        $this->log('info', 'Obtendo QR Code PIX', ['payment_id' => $payment_id]);
        
        try {
            $response = $this->fazerRequisicao('GET', $url);
            return $response;
        } catch (Exception $e) {
            $this->log('erro', 'Erro ao obter QR Code PIX', ['erro' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Obter linha digitável do boleto
     * 
     * @param string $payment_id ID do pagamento no Asaas
     * @return array Dados do boleto (identificationField, nossoNumero, barCode)
     */
    public function obterLinhaDigitavel($payment_id) {
        $url = $this->base_url . '/payments/' . $payment_id . '/identificationField';
        
        $this->log('info', 'Obtendo linha digitável', ['payment_id' => $payment_id]);
        
        try {
            $response = $this->fazerRequisicao('GET', $url);
            return $response;
        } catch (Exception $e) {
            $this->log('erro', 'Erro ao obter linha digitável', ['erro' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Atualizar cobrança existente
     * 
     * @param string $payment_id ID do pagamento no Asaas
     * @param array $dados Dados para atualização
     * @return array Resposta da API
     */
    public function atualizarCobranca($payment_id, $dados) {
        $url = $this->base_url . '/payments/' . $payment_id;
        
        $payload = array_filter($dados, function($value) {
            return $value !== null;
        });
        
        $this->log('info', 'Atualizando cobrança', ['payment_id' => $payment_id, 'payload' => $payload]);
        
        try {
            $response = $this->fazerRequisicao('PUT', $url, $payload);
            
            // Atualizar pagamento no banco
            if (isset($response['id'])) {
                $this->atualizarPagamento($response);
            }
            
            $this->log('sucesso', 'Cobrança atualizada', ['payment_id' => $payment_id]);
            
            return $response;
        } catch (Exception $e) {
            $this->log('erro', 'Erro ao atualizar cobrança', ['erro' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Excluir cobrança
     * 
     * @param string $payment_id ID do pagamento no Asaas
     * @return array Resposta da API
     */
    public function excluirCobranca($payment_id) {
        $url = $this->base_url . '/payments/' . $payment_id;
        
        $this->log('info', 'Excluindo cobrança', ['payment_id' => $payment_id]);
        
        try {
            $response = $this->fazerRequisicao('DELETE', $url);
            $this->log('sucesso', 'Cobrança excluída', ['payment_id' => $payment_id]);
            return $response;
        } catch (Exception $e) {
            $this->log('erro', 'Erro ao excluir cobrança', ['erro' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Processar webhook do Asaas
     * 
     * @param array $data Dados do webhook
     * @param string $token Token recebido no header
     * @return array Dados processados
     */
    public function processarWebhook($data, $token = null) {
        // Validar token se configurado
        if ($this->webhook_token && $token !== $this->webhook_token) {
            $this->log('erro', 'Token de webhook inválido', ['token_recebido' => $token]);
            throw new Exception('Token de webhook inválido');
        }
        
        // Validar estrutura do webhook
        if (!isset($data['event']) || !isset($data['payment'])) {
            $this->log('erro', 'Webhook inválido', ['data' => $data]);
            throw new Exception('Webhook inválido');
        }
        
        $event_type = $data['event'];
        $payment_data = $data['payment'];
        $payment_id = $payment_data['id'] ?? null;
        $event_id = $data['id'] ?? uniqid('evt_');
        
        $this->log('info', 'Processando webhook', [
            'event_id' => $event_id,
            'event_type' => $event_type,
            'payment_id' => $payment_id
        ]);
        
        // Salvar webhook no banco
        $this->salvarWebhook($event_id, $event_type, $payment_id, $data);
        
        // Atualizar pagamento no banco
        if ($payment_id) {
            $this->atualizarPagamento($payment_data);
        }
        
        return [
            'event_id' => $event_id,
            'event_type' => $event_type,
            'payment_id' => $payment_id,
            'status' => $payment_data['status'] ?? null,
            'payment_data' => $payment_data
        ];
    }
    
    /**
     * Fazer requisição HTTP para API do Asaas
     * 
     * @param string $method Método HTTP (GET, POST, PUT, DELETE)
     * @param string $url URL completa
     * @param array $data Dados para enviar (opcional)
     * @return array Resposta decodificada
     * @throws Exception Em caso de erro
     */
    private function fazerRequisicao($method, $url, $data = null) {
        $ch = curl_init();
        
        $headers = [
            'access_token: ' . $this->api_key,
            'Content-Type: application/json',
            'User-Agent: ChopponERP/1.0'
        ];
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        
        curl_close($ch);
        
        if ($curl_error) {
            throw new Exception("Erro na requisição: " . $curl_error);
        }
        
        $response_data = json_decode($response, true);
        
        // Tratar erros HTTP
        if ($http_code >= 400) {
            $error_message = 'Erro desconhecido';
            
            if (isset($response_data['errors']) && is_array($response_data['errors'])) {
                $errors = [];
                foreach ($response_data['errors'] as $error) {
                    $errors[] = $error['description'] ?? $error['code'] ?? 'Erro';
                }
                $error_message = implode('; ', $errors);
            } elseif (isset($response_data['message'])) {
                $error_message = $response_data['message'];
            }
            
            throw new Exception("Erro Asaas ({$http_code}): " . $error_message);
        }
        
        return $response_data;
    }
    
    /**
     * Salvar mapeamento de cliente no banco
     */
    private function salvarMapeamentoCliente($cliente_id, $asaas_customer_id, $cpf_cnpj) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO asaas_clientes 
                (cliente_id, estabelecimento_id, asaas_customer_id, cpf_cnpj)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    asaas_customer_id = VALUES(asaas_customer_id),
                    cpf_cnpj = VALUES(cpf_cnpj)
            ");
            $stmt->execute([$cliente_id, $this->estabelecimento_id, $asaas_customer_id, $cpf_cnpj]);
        } catch (Exception $e) {
            $this->log('erro', 'Erro ao salvar mapeamento de cliente', ['erro' => $e->getMessage()]);
        }
    }
    
    /**
     * Salvar pagamento no banco
     */
    private function salvarPagamento($payment_data) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO asaas_pagamentos 
                (estabelecimento_id, asaas_payment_id, asaas_customer_id, tipo_cobranca, 
                 valor, data_vencimento, status_asaas, url_fatura, payload_completo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    status_asaas = VALUES(status_asaas),
                    url_fatura = VALUES(url_fatura),
                    payload_completo = VALUES(payload_completo)
            ");
            
            $stmt->execute([
                $this->estabelecimento_id,
                $payment_data['id'],
                $payment_data['customer'],
                $payment_data['billingType'],
                $payment_data['value'],
                $payment_data['dueDate'],
                $payment_data['status'],
                $payment_data['invoiceUrl'] ?? null,
                json_encode($payment_data)
            ]);
        } catch (Exception $e) {
            $this->log('erro', 'Erro ao salvar pagamento', ['erro' => $e->getMessage()]);
        }
    }
    
    /**
     * Atualizar pagamento no banco
     */
    private function atualizarPagamento($payment_data) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE asaas_pagamentos SET
                    status_asaas = ?,
                    url_boleto = ?,
                    linha_digitavel = ?,
                    nosso_numero = ?,
                    url_fatura = ?,
                    data_pagamento = ?,
                    data_confirmacao = ?,
                    data_credito = ?,
                    valor_liquido = ?,
                    payload_completo = ?
                WHERE asaas_payment_id = ?
            ");
            
            $stmt->execute([
                $payment_data['status'],
                $payment_data['bankSlipUrl'] ?? null,
                $payment_data['identificationField'] ?? null,
                $payment_data['nossoNumero'] ?? null,
                $payment_data['invoiceUrl'] ?? null,
                $payment_data['paymentDate'] ?? null,
                $payment_data['confirmedDate'] ?? null,
                $payment_data['creditDate'] ?? null,
                $payment_data['netValue'] ?? null,
                json_encode($payment_data),
                $payment_data['id']
            ]);
        } catch (Exception $e) {
            $this->log('erro', 'Erro ao atualizar pagamento', ['erro' => $e->getMessage()]);
        }
    }
    
    /**
     * Salvar webhook no banco
     */
    private function salvarWebhook($event_id, $event_type, $payment_id, $payload) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO asaas_webhooks 
                (event_id, event_type, asaas_payment_id, payload)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    event_type = VALUES(event_type),
                    payload = VALUES(payload)
            ");
            
            $stmt->execute([
                $event_id,
                $event_type,
                $payment_id,
                json_encode($payload)
            ]);
        } catch (Exception $e) {
            $this->log('erro', 'Erro ao salvar webhook', ['erro' => $e->getMessage()]);
        }
    }
    
    /**
     * Registrar log de operação
     */
    private function log($status, $operacao, $dados = []) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO asaas_logs 
                (operacao, status, estabelecimento_id, dados_requisicao, dados_resposta, mensagem_erro)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $operacao,
                $status,
                $this->estabelecimento_id,
                isset($dados['payload']) ? json_encode($dados['payload']) : null,
                isset($dados['response']) ? json_encode($dados['response']) : null,
                $dados['erro'] ?? null
            ]);
        } catch (Exception $e) {
            // Silencioso para não causar loop de erros
            error_log("Erro ao salvar log Asaas: " . $e->getMessage());
        }
    }
    
    /**
     * Validar configuração testando a API
     * 
     * @return bool True se configuração válida
     */
    public function validarConfiguracao() {
        try {
            // Fazer uma requisição simples para validar o token
            $url = $this->base_url . '/customers?limit=1';
            $this->fazerRequisicao('GET', $url);
            return true;
        } catch (Exception $e) {
            $this->log('erro', 'Validação de configuração falhou', ['erro' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Mapear status do Asaas para status interno
     * 
     * @param string $asaas_status Status do Asaas
     * @return string Status interno
     */
    public static function mapearStatus($asaas_status) {
        $map = [
            'PENDING' => 'pendente',
            'RECEIVED' => 'recebido',
            'CONFIRMED' => 'confirmado',
            'OVERDUE' => 'pendente',
            'REFUNDED' => 'cancelado',
            'RECEIVED_IN_CASH' => 'recebido',
            'REFUND_REQUESTED' => 'processando',
            'CHARGEBACK_REQUESTED' => 'processando',
            'CHARGEBACK_DISPUTE' => 'processando',
            'AWAITING_CHARGEBACK_REVERSAL' => 'processando',
            'DUNNING_REQUESTED' => 'processando',
            'DUNNING_RECEIVED' => 'recebido',
            'AWAITING_RISK_ANALYSIS' => 'processando'
        ];
        
        return $map[$asaas_status] ?? 'pendente';
    }
}
?>
