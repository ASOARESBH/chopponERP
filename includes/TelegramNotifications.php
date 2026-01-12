<?php
/**
 * Classe TelegramNotifications
 * Gerencia notificações automáticas via Telegram
 */

require_once __DIR__ . '/telegram.php';

class TelegramNotifications {
    private $conn;
    
    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
    }
    
    /**
     * Verificar e enviar alertas de estoque mínimo
     */
    public function verificarEstoqueMinimo() {
        $alertas_enviados = 0;
        
        // Buscar estabelecimentos com notificação ativa
        $stmt = $this->conn->query("
            SELECT e.id as estabelecimento_id, e.nome as estabelecimento_nome,
                   tc.bot_token, tc.chat_id
            FROM estabelecimentos e
            INNER JOIN telegram_config tc ON e.id = tc.estabelecimento_id
            WHERE tc.status = 1 AND tc.notificar_estoque_minimo = 1
        ");
        $estabelecimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($estabelecimentos as $estab) {
            // Buscar produtos com estoque crítico
            $stmt = $this->conn->prepare("
                SELECT p.id, p.nome, p.codigo, p.estoque_atual, p.estoque_minimo,
                       (p.estoque_minimo - p.estoque_atual) as quantidade_repor
                FROM estoque_produtos p
                WHERE p.estoque_atual <= p.estoque_minimo
                  AND p.ativo = 1
                ORDER BY p.estoque_atual ASC
            ");
            $stmt->execute();
            $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($produtos)) {
                foreach ($produtos as $produto) {
                    // Verificar se já foi enviado hoje
                    if (!$this->jaEnviadoHoje($estab['estabelecimento_id'], 'estoque_minimo', $produto['id'])) {
                        $mensagem = $this->montarMensagemEstoque($produto, $estab['estabelecimento_nome']);
                        
                        if ($this->enviarTelegram($estab['bot_token'], $estab['chat_id'], $mensagem)) {
                            $this->registrarEnvio($estab['estabelecimento_id'], 'estoque_minimo', $produto['id'], $mensagem);
                            $alertas_enviados++;
                        }
                    }
                }
            }
        }
        
        return $alertas_enviados;
    }
    
    /**
     * Verificar e enviar alertas de contas a pagar
     */
    public function verificarContasPagar() {
        $alertas_enviados = 0;
        
        // Buscar estabelecimentos com notificação ativa
        $stmt = $this->conn->query("
            SELECT e.id as estabelecimento_id, e.nome as estabelecimento_nome,
                   tc.bot_token, tc.chat_id, tc.dias_antes_vencimento, tc.dias_apos_vencimento
            FROM estabelecimentos e
            INNER JOIN telegram_config tc ON e.id = tc.estabelecimento_id
            WHERE tc.status = 1 AND tc.notificar_contas_pagar = 1
        ");
        $estabelecimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($estabelecimentos as $estab) {
            $dias_antes = $estab['dias_antes_vencimento'];
            $dias_apos = $estab['dias_apos_vencimento'];
            
            // Buscar contas vencendo ou vencidas
            $stmt = $this->conn->prepare("
                SELECT c.id, c.descricao, c.valor, c.data_vencimento,
                       DATEDIFF(c.data_vencimento, CURDATE()) as dias_ate_vencimento,
                       f.nome as fornecedor_nome
                FROM financeiro_contas c
                LEFT JOIN fornecedores f ON c.fornecedor_id = f.id
                WHERE c.status = 'pendente'
                  AND c.data_vencimento BETWEEN DATE_SUB(CURDATE(), INTERVAL ? DAY) 
                                            AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                ORDER BY c.data_vencimento ASC
            ");
            $stmt->execute([$dias_apos, $dias_antes]);
            $contas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($contas)) {
                foreach ($contas as $conta) {
                    // Verificar se já foi enviado hoje
                    if (!$this->jaEnviadoHoje($estab['estabelecimento_id'], 'conta_pagar', $conta['id'])) {
                        $mensagem = $this->montarMensagemConta($conta, $estab['estabelecimento_nome']);
                        
                        if ($this->enviarTelegram($estab['bot_token'], $estab['chat_id'], $mensagem)) {
                            $this->registrarEnvio($estab['estabelecimento_id'], 'conta_pagar', $conta['id'], $mensagem);
                            $alertas_enviados++;
                        }
                    }
                }
            }
        }
        
        return $alertas_enviados;
    }
    
    /**
     * Verificar e enviar alertas de promoções expirando
     */
    public function verificarPromocoes() {
        $alertas_enviados = 0;
        
        // Buscar estabelecimentos com notificação ativa
        $stmt = $this->conn->query("
            SELECT e.id as estabelecimento_id, e.nome as estabelecimento_nome,
                   tc.bot_token, tc.chat_id, tc.dias_antes_vencimento
            FROM estabelecimentos e
            INNER JOIN telegram_config tc ON e.id = tc.estabelecimento_id
            WHERE tc.status = 1 AND tc.notificar_promocoes = 1
        ");
        $estabelecimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($estabelecimentos as $estab) {
            $dias_antes = $estab['dias_antes_vencimento'];
            
            // Buscar promoções expirando
            $stmt = $this->conn->prepare("
                SELECT p.id, p.nome, p.descricao, p.data_fim, p.desconto_percentual,
                       DATEDIFF(p.data_fim, CURDATE()) as dias_ate_fim
                FROM promocoes p
                WHERE p.ativo = 1
                  AND p.data_fim BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                ORDER BY p.data_fim ASC
            ");
            $stmt->execute([$dias_antes]);
            $promocoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($promocoes)) {
                foreach ($promocoes as $promocao) {
                    // Verificar se já foi enviado hoje
                    if (!$this->jaEnviadoHoje($estab['estabelecimento_id'], 'promocao', $promocao['id'])) {
                        $mensagem = $this->montarMensagemPromocao($promocao, $estab['estabelecimento_nome']);
                        
                        if ($this->enviarTelegram($estab['bot_token'], $estab['chat_id'], $mensagem)) {
                            $this->registrarEnvio($estab['estabelecimento_id'], 'promocao', $promocao['id'], $mensagem);
                            $alertas_enviados++;
                        }
                    }
                }
            }
        }
        
        return $alertas_enviados;
    }
    
    /**
     * Montar mensagem de estoque mínimo
     */
    private function montarMensagemEstoque($produto, $estabelecimento) {
        $emoji = $produto['estoque_atual'] == 0 ? '🔴' : '⚠️';
        
        $mensagem = "{$emoji} *ALERTA DE ESTOQUE*\n\n";
        $mensagem .= "📍 *Estabelecimento:* {$estabelecimento}\n";
        $mensagem .= "📦 *Produto:* {$produto['nome']}\n";
        $mensagem .= "🔢 *Código:* {$produto['codigo']}\n";
        $mensagem .= "📊 *Estoque Atual:* {$produto['estoque_atual']} unidades\n";
        $mensagem .= "⚡ *Estoque Mínimo:* {$produto['estoque_minimo']} unidades\n";
        
        if ($produto['estoque_atual'] == 0) {
            $mensagem .= "\n❌ *ESTOQUE ZERADO!*\n";
        } else {
            $mensagem .= "📈 *Repor:* {$produto['quantidade_repor']} unidades\n";
        }
        
        $mensagem .= "\n⏰ " . date('d/m/Y H:i');
        
        return $mensagem;
    }
    
    /**
     * Montar mensagem de conta a pagar
     */
    private function montarMensagemConta($conta, $estabelecimento) {
        $dias = $conta['dias_ate_vencimento'];
        
        if ($dias < 0) {
            $emoji = '🔴';
            $status = "*VENCIDA HÁ " . abs($dias) . " DIAS*";
        } elseif ($dias == 0) {
            $emoji = '🟠';
            $status = "*VENCE HOJE*";
        } else {
            $emoji = '🟡';
            $status = "*Vence em {$dias} dias*";
        }
        
        $mensagem = "{$emoji} *ALERTA DE CONTA A PAGAR*\n\n";
        $mensagem .= "📍 *Estabelecimento:* {$estabelecimento}\n";
        $mensagem .= "📄 *Descrição:* {$conta['descricao']}\n";
        
        if ($conta['fornecedor_nome']) {
            $mensagem .= "🏢 *Fornecedor:* {$conta['fornecedor_nome']}\n";
        }
        
        $mensagem .= "💰 *Valor:* R$ " . number_format($conta['valor'], 2, ',', '.') . "\n";
        $mensagem .= "📅 *Vencimento:* " . date('d/m/Y', strtotime($conta['data_vencimento'])) . "\n";
        $mensagem .= "⚠️ {$status}\n";
        $mensagem .= "\n⏰ " . date('d/m/Y H:i');
        
        return $mensagem;
    }
    
    /**
     * Montar mensagem de promoção expirando
     */
    private function montarMensagemPromocao($promocao, $estabelecimento) {
        $dias = $promocao['dias_ate_fim'];
        
        if ($dias == 0) {
            $emoji = '🔴';
            $status = "*EXPIRA HOJE*";
        } elseif ($dias == 1) {
            $emoji = '🟠';
            $status = "*Expira amanhã*";
        } else {
            $emoji = '🟡';
            $status = "*Expira em {$dias} dias*";
        }
        
        $mensagem = "{$emoji} *ALERTA DE PROMOÇÃO*\n\n";
        $mensagem .= "📍 *Estabelecimento:* {$estabelecimento}\n";
        $mensagem .= "🎉 *Promoção:* {$promocao['nome']}\n";
        
        if ($promocao['descricao']) {
            $mensagem .= "📝 *Descrição:* {$promocao['descricao']}\n";
        }
        
        if ($promocao['desconto_percentual']) {
            $mensagem .= "💸 *Desconto:* {$promocao['desconto_percentual']}%\n";
        }
        
        $mensagem .= "📅 *Data Fim:* " . date('d/m/Y', strtotime($promocao['data_fim'])) . "\n";
        $mensagem .= "⚠️ {$status}\n";
        $mensagem .= "\n⏰ " . date('d/m/Y H:i');
        
        return $mensagem;
    }
    
    /**
     * Enviar mensagem via Telegram
     */
    private function enviarTelegram($botToken, $chatId, $mensagem) {
        try {
            $telegram = new TelegramBot($botToken);
            return $telegram->sendMessage($chatId, $mensagem, true); // true = markdown
        } catch (Exception $e) {
            $this->registrarLog(0, 'outro', null, $mensagem, 'erro', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar se alerta já foi enviado hoje
     */
    private function jaEnviadoHoje($estabelecimentoId, $tipo, $referenciaId) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as total
            FROM telegram_alerts_sent
            WHERE estabelecimento_id = ?
              AND tipo = ?
              AND referencia_id = ?
              AND data_envio = CURDATE()
        ");
        $stmt->execute([$estabelecimentoId, $tipo, $referenciaId]);
        $result = $stmt->fetch();
        
        return $result['total'] > 0;
    }
    
    /**
     * Registrar envio de alerta
     */
    private function registrarEnvio($estabelecimentoId, $tipo, $referenciaId, $mensagem) {
        try {
            // Registrar no log
            $this->registrarLog($estabelecimentoId, $tipo, $referenciaId, $mensagem, 'enviado');
            
            // Registrar controle de envio
            $stmt = $this->conn->prepare("
                INSERT INTO telegram_alerts_sent 
                (estabelecimento_id, tipo, referencia_id, data_envio)
                VALUES (?, ?, ?, CURDATE())
                ON DUPLICATE KEY UPDATE created_at = NOW()
            ");
            $stmt->execute([$estabelecimentoId, $tipo, $referenciaId]);
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Registrar log de notificação
     */
    private function registrarLog($estabelecimentoId, $tipo, $referenciaId, $mensagem, $status = 'enviado', $erroMensagem = null) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO telegram_notifications_log 
                (estabelecimento_id, tipo, referencia_id, mensagem, status, erro_mensagem, enviado_em)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$estabelecimentoId, $tipo, $referenciaId, $mensagem, $status, $erroMensagem]);
        } catch (Exception $e) {
            // Silencioso para não quebrar o fluxo
        }
    }
    
    /**
     * Executar todas as verificações
     */
    public function executarTodasVerificacoes() {
        $total = 0;
        
        $total += $this->verificarEstoqueMinimo();
        $total += $this->verificarContasPagar();
        $total += $this->verificarPromocoes();
        
        return $total;
    }
    
    /**
     * Obter estatísticas de notificações
     */
    public function obterEstatisticas($estabelecimentoId = null, $dias = 7) {
        $where = ["DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL ? DAY)"];
        $params = [$dias];
        
        if ($estabelecimentoId) {
            $where[] = "estabelecimento_id = ?";
            $params[] = $estabelecimentoId;
        }
        
        $sql = "
            SELECT 
                tipo,
                status,
                COUNT(*) as total
            FROM telegram_notifications_log
            WHERE " . implode(' AND ', $where) . "
            GROUP BY tipo, status
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
