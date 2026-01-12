<?php
/**
 * Helper de Cashback
 * Funções para calcular e aplicar regras de cashback
 */

class CashbackHelper {
    
    private $conn;
    private $estabelecimento_id;
    
    public function __construct($conn, $estabelecimento_id) {
        $this->conn = $conn;
        $this->estabelecimento_id = $estabelecimento_id;
    }
    
    /**
     * Calcula o cashback para um consumo
     * 
     * @param float $valor_total Valor total do consumo
     * @param int $bebida_id ID da bebida (opcional)
     * @param string $data_consumo Data/hora do consumo
     * @return array ['valor' => float, 'regra_id' => int, 'regra_nome' => string]
     */
    public function calcularCashback($valor_total, $bebida_id = null, $data_consumo = null) {
        if (!$data_consumo) {
            $data_consumo = date('Y-m-d H:i:s');
        }
        
        // Verificar se cashback está ativo
        $stmt = $this->conn->prepare("SELECT ativo FROM cashback_config WHERE estabelecimento_id = ?");
        $stmt->execute([$this->estabelecimento_id]);
        $config = $stmt->fetch();
        
        if (!$config || !$config['ativo']) {
            return ['valor' => 0, 'regra_id' => null, 'regra_nome' => 'Cashback desativado'];
        }
        
        // Buscar regras aplicáveis (ordenadas por prioridade)
        $stmt = $this->conn->prepare("
            SELECT * FROM cashback_regras
            WHERE estabelecimento_id = ? AND ativo = 1
            ORDER BY prioridade DESC, id ASC
        ");
        $stmt->execute([$this->estabelecimento_id]);
        $regras = $stmt->fetchAll();
        
        $timestamp = strtotime($data_consumo);
        $dia_semana = date('w', $timestamp);
        $hora = date('H:i:s', $timestamp);
        $data = date('Y-m-d', $timestamp);
        
        // Aplicar primeira regra que atender todas as condições
        foreach ($regras as $regra) {
            // Verificar valor mínimo
            if ($valor_total < $regra['valor_minimo']) {
                continue;
            }
            
            // Verificar dias da semana
            if ($regra['dias_semana']) {
                $dias_permitidos = json_decode($regra['dias_semana']);
                if (!in_array($dia_semana, $dias_permitidos)) {
                    continue;
                }
            }
            
            // Verificar horário
            if ($regra['hora_inicio'] && $regra['hora_fim']) {
                if ($hora < $regra['hora_inicio'] || $hora > $regra['hora_fim']) {
                    continue;
                }
            }
            
            // Verificar período
            if ($regra['data_inicio'] && $data < $regra['data_inicio']) {
                continue;
            }
            if ($regra['data_fim'] && $data > $regra['data_fim']) {
                continue;
            }
            
            // Verificar bebidas específicas
            if ($regra['bebidas_especificas'] && $bebida_id) {
                $bebidas_permitidas = json_decode($regra['bebidas_especificas']);
                if (!in_array($bebida_id, $bebidas_permitidas)) {
                    continue;
                }
            }
            
            // Calcular cashback conforme tipo de regra
            $cashback = 0;
            
            switch ($regra['tipo_regra']) {
                case 'percentual':
                    $cashback = ($valor_total * $regra['valor_regra']) / 100;
                    break;
                    
                case 'valor_fixo':
                    $cashback = $regra['valor_regra'];
                    break;
                    
                case 'pontos_por_real':
                    $cashback = floor($valor_total) * $regra['valor_regra'];
                    break;
            }
            
            // Aplicar multiplicador
            $cashback *= $regra['multiplicador'];
            
            // Aplicar valor máximo
            if ($regra['valor_maximo'] && $cashback > $regra['valor_maximo']) {
                $cashback = $regra['valor_maximo'];
            }
            
            // Retornar primeira regra aplicável
            return [
                'valor' => round($cashback, 2),
                'regra_id' => $regra['id'],
                'regra_nome' => $regra['nome']
            ];
        }
        
        // Nenhuma regra aplicável
        return ['valor' => 0, 'regra_id' => null, 'regra_nome' => 'Nenhuma regra aplicável'];
    }
    
    /**
     * Registra um consumo e aplica cashback
     * 
     * @param int $cliente_id ID do cliente
     * @param int $bebida_id ID da bebida
     * @param string $bebida_nome Nome da bebida
     * @param float $quantidade Quantidade em litros
     * @param float $valor_unitario Valor por litro
     * @param string $data_consumo Data/hora do consumo
     * @param int $pedido_id ID do pedido (opcional)
     * @return array ['success' => bool, 'consumo_id' => int, 'cashback' => float]
     */
    public function registrarConsumo($cliente_id, $bebida_id, $bebida_nome, $quantidade, $valor_unitario, $data_consumo = null, $pedido_id = null) {
        try {
            $this->conn->beginTransaction();
            
            if (!$data_consumo) {
                $data_consumo = date('Y-m-d H:i:s');
            }
            
            $valor_total = $quantidade * $valor_unitario;
            
            // Calcular cashback
            $cashback_info = $this->calcularCashback($valor_total, $bebida_id, $data_consumo);
            $pontos_ganhos = $cashback_info['valor'];
            
            // Inserir consumo
            $stmt = $this->conn->prepare("
                INSERT INTO clientes_consumo (
                    cliente_id, estabelecimento_id, pedido_id, bebida_id, bebida_nome,
                    quantidade, valor_unitario, valor_total, pontos_ganhos, data_consumo
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $cliente_id, $this->estabelecimento_id, $pedido_id, $bebida_id, $bebida_nome,
                $quantidade, $valor_unitario, $valor_total, $pontos_ganhos, $data_consumo
            ]);
            
            $consumo_id = $this->conn->lastInsertId();
            
            // Se ganhou pontos, registrar no histórico de cashback
            if ($pontos_ganhos > 0) {
                // Buscar saldo atual
                $stmt = $this->conn->prepare("SELECT pontos_cashback FROM clientes WHERE id = ?");
                $stmt->execute([$cliente_id]);
                $cliente = $stmt->fetch();
                $saldo_anterior = $cliente['pontos_cashback'];
                $saldo_atual = $saldo_anterior + $pontos_ganhos;
                
                // Registrar histórico
                $stmt = $this->conn->prepare("
                    INSERT INTO cashback_historico (
                        cliente_id, estabelecimento_id, tipo, valor,
                        saldo_anterior, saldo_atual, descricao, consumo_id, regra_id
                    ) VALUES (?, ?, 'credito', ?, ?, ?, ?, ?, ?)
                ");
                
                $descricao = "Cashback de consumo - " . $cashback_info['regra_nome'];
                
                $stmt->execute([
                    $cliente_id, $this->estabelecimento_id, $pontos_ganhos,
                    $saldo_anterior, $saldo_atual, $descricao, $consumo_id, $cashback_info['regra_id']
                ]);
                
                // Atualizar saldo do cliente
                $stmt = $this->conn->prepare("UPDATE clientes SET pontos_cashback = ? WHERE id = ?");
                $stmt->execute([$saldo_atual, $cliente_id]);
            }
            
            $this->conn->commit();
            
            Logger::info("Consumo registrado com cashback", [
                'cliente_id' => $cliente_id,
                'valor_total' => $valor_total,
                'pontos_ganhos' => $pontos_ganhos,
                'regra' => $cashback_info['regra_nome']
            ]);
            
            return [
                'success' => true,
                'consumo_id' => $consumo_id,
                'cashback' => $pontos_ganhos,
                'regra_nome' => $cashback_info['regra_nome']
            ];
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            
            Logger::error("Erro ao registrar consumo", [
                'cliente_id' => $cliente_id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Resgata pontos de cashback
     * 
     * @param int $cliente_id ID do cliente
     * @param float $valor Valor a resgatar
     * @param string $descricao Descrição do resgate
     * @param int $user_id ID do usuário que está fazendo o resgate
     * @return array ['success' => bool, 'message' => string]
     */
    public function resgatarCashback($cliente_id, $valor, $descricao, $user_id) {
        try {
            // Verificar se resgate está permitido
            $stmt = $this->conn->prepare("SELECT * FROM cashback_config WHERE estabelecimento_id = ?");
            $stmt->execute([$this->estabelecimento_id]);
            $config = $stmt->fetch();
            
            if (!$config || !$config['permite_resgate']) {
                return ['success' => false, 'message' => 'Resgate de cashback não permitido.'];
            }
            
            if ($valor < $config['valor_minimo_resgate']) {
                return [
                    'success' => false,
                    'message' => 'Valor mínimo para resgate: ' . formatMoney($config['valor_minimo_resgate'])
                ];
            }
            
            // Buscar saldo atual
            $stmt = $this->conn->prepare("SELECT pontos_cashback FROM clientes WHERE id = ?");
            $stmt->execute([$cliente_id]);
            $cliente = $stmt->fetch();
            
            if (!$cliente) {
                return ['success' => false, 'message' => 'Cliente não encontrado.'];
            }
            
            $saldo_anterior = $cliente['pontos_cashback'];
            
            if ($valor > $saldo_anterior) {
                return ['success' => false, 'message' => 'Saldo insuficiente.'];
            }
            
            $this->conn->beginTransaction();
            
            $saldo_atual = $saldo_anterior - $valor;
            
            // Registrar histórico
            $stmt = $this->conn->prepare("
                INSERT INTO cashback_historico (
                    cliente_id, estabelecimento_id, tipo, valor,
                    saldo_anterior, saldo_atual, descricao, user_id
                ) VALUES (?, ?, 'resgate', ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $cliente_id, $this->estabelecimento_id, $valor,
                $saldo_anterior, $saldo_atual, $descricao, $user_id
            ]);
            
            // Atualizar saldo do cliente
            $stmt = $this->conn->prepare("UPDATE clientes SET pontos_cashback = ? WHERE id = ?");
            $stmt->execute([$saldo_atual, $cliente_id]);
            
            $this->conn->commit();
            
            Logger::info("Cashback resgatado", [
                'cliente_id' => $cliente_id,
                'valor' => $valor,
                'user_id' => $user_id
            ]);
            
            return [
                'success' => true,
                'message' => 'Resgate realizado com sucesso!',
                'saldo_atual' => $saldo_atual
            ];
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            
            Logger::error("Erro ao resgatar cashback", [
                'cliente_id' => $cliente_id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Erro ao processar resgate: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Ajusta manualmente o saldo de cashback
     * 
     * @param int $cliente_id ID do cliente
     * @param string $tipo 'credito' ou 'resgate'
     * @param float $valor Valor do ajuste
     * @param string $descricao Motivo do ajuste
     * @param int $user_id ID do usuário que está fazendo o ajuste
     * @return array ['success' => bool, 'message' => string]
     */
    public function ajustarCashback($cliente_id, $tipo, $valor, $descricao, $user_id) {
        try {
            $this->conn->beginTransaction();
            
            // Buscar saldo atual
            $stmt = $this->conn->prepare("SELECT pontos_cashback FROM clientes WHERE id = ?");
            $stmt->execute([$cliente_id]);
            $cliente = $stmt->fetch();
            
            if (!$cliente) {
                return ['success' => false, 'message' => 'Cliente não encontrado.'];
            }
            
            $saldo_anterior = $cliente['pontos_cashback'];
            
            if ($tipo == 'credito') {
                $saldo_atual = $saldo_anterior + $valor;
            } else {
                if ($valor > $saldo_anterior) {
                    return ['success' => false, 'message' => 'Saldo insuficiente.'];
                }
                $saldo_atual = $saldo_anterior - $valor;
            }
            
            // Registrar histórico
            $stmt = $this->conn->prepare("
                INSERT INTO cashback_historico (
                    cliente_id, estabelecimento_id, tipo, valor,
                    saldo_anterior, saldo_atual, descricao, user_id
                ) VALUES (?, ?, 'ajuste', ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $cliente_id, $this->estabelecimento_id, $valor,
                $saldo_anterior, $saldo_atual, $descricao, $user_id
            ]);
            
            // Atualizar saldo do cliente
            $stmt = $this->conn->prepare("UPDATE clientes SET pontos_cashback = ? WHERE id = ?");
            $stmt->execute([$saldo_atual, $cliente_id]);
            
            $this->conn->commit();
            
            Logger::info("Cashback ajustado manualmente", [
                'cliente_id' => $cliente_id,
                'tipo' => $tipo,
                'valor' => $valor,
                'user_id' => $user_id
            ]);
            
            return [
                'success' => true,
                'message' => 'Ajuste realizado com sucesso!',
                'saldo_atual' => $saldo_atual
            ];
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            
            Logger::error("Erro ao ajustar cashback", [
                'cliente_id' => $cliente_id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Erro ao processar ajuste: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Busca cliente por CPF
     * 
     * @param string $cpf CPF do cliente (apenas números)
     * @return array|null Dados do cliente ou null
     */
    public function buscarClientePorCPF($cpf) {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        
        $stmt = $this->conn->prepare("
            SELECT * FROM clientes 
            WHERE cpf = ? AND estabelecimento_id = ? AND status = 1
        ");
        $stmt->execute([$cpf, $this->estabelecimento_id]);
        
        return $stmt->fetch();
    }
}
?>
