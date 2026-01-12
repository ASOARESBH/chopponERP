<?php
/**
 * Classe EmailNotifications
 * Gerencia notificações automáticas via E-mail com SMTP
 */

// Verificar se PHPMailer está disponível
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    $phpmailer_path = __DIR__ . '/../erro/vendor/phpmailer/phpmailer/src';
    if (file_exists($phpmailer_path . '/PHPMailer.php')) {
        require_once $phpmailer_path . '/Exception.php';
        require_once $phpmailer_path . '/PHPMailer.php';
        require_once $phpmailer_path . '/SMTP.php';
    }
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailNotifications {
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
                   ec.email_alerta
            FROM estabelecimentos e
            INNER JOIN email_config ec ON e.id = ec.estabelecimento_id
            WHERE ec.status = 1 AND ec.notificar_estoque_minimo = 1
              AND ec.email_alerta IS NOT NULL AND ec.email_alerta != ''
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
                        $html = $this->montarEmailEstoque($produto, $estab['estabelecimento_nome']);
                        $assunto = "⚠️ Alerta: Estoque Mínimo - {$produto['nome']}";
                        
                        if ($this->enviarEmail($estab['estabelecimento_id'], $estab['email_alerta'], $assunto, $html)) {
                            $this->registrarEnvio($estab['estabelecimento_id'], 'estoque_minimo', $produto['id'], 
                                                 $estab['email_alerta'], $assunto, $html);
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
        
        $stmt = $this->conn->query("
            SELECT e.id as estabelecimento_id, e.nome as estabelecimento_nome,
                   ec.email_alerta, ec.dias_antes_vencimento, ec.dias_apos_vencimento
            FROM estabelecimentos e
            INNER JOIN email_config ec ON e.id = ec.estabelecimento_id
            WHERE ec.status = 1 AND ec.notificar_contas_pagar = 1
              AND ec.email_alerta IS NOT NULL AND ec.email_alerta != ''
        ");
        $estabelecimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($estabelecimentos as $estab) {
            $dias_antes = $estab['dias_antes_vencimento'];
            $dias_apos = $estab['dias_apos_vencimento'];
            
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
                    if (!$this->jaEnviadoHoje($estab['estabelecimento_id'], 'conta_pagar', $conta['id'])) {
                        $html = $this->montarEmailConta($conta, $estab['estabelecimento_nome']);
                        $assunto = "💳 Alerta: Conta a Pagar - {$conta['descricao']}";
                        
                        if ($this->enviarEmail($estab['estabelecimento_id'], $estab['email_alerta'], $assunto, $html)) {
                            $this->registrarEnvio($estab['estabelecimento_id'], 'conta_pagar', $conta['id'],
                                                 $estab['email_alerta'], $assunto, $html);
                            $alertas_enviados++;
                        }
                    }
                }
            }
        }
        
        return $alertas_enviados;
    }
    
    /**
     * Verificar e enviar alertas de royalties
     */
    public function verificarRoyalties() {
        $alertas_enviados = 0;
        
        $stmt = $this->conn->query("
            SELECT e.id as estabelecimento_id, e.nome as estabelecimento_nome,
                   ec.email_alerta, ec.dias_antes_vencimento, ec.dias_apos_vencimento
            FROM estabelecimentos e
            INNER JOIN email_config ec ON e.id = ec.estabelecimento_id
            WHERE ec.status = 1 AND ec.notificar_royalties = 1
              AND ec.email_alerta IS NOT NULL AND ec.email_alerta != ''
        ");
        $estabelecimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($estabelecimentos as $estab) {
            $dias_antes = $estab['dias_antes_vencimento'];
            $dias_apos = $estab['dias_apos_vencimento'];
            
            $stmt = $this->conn->prepare("
                SELECT r.id, r.periodo_inicio, r.periodo_fim, r.valor_royalties, r.data_vencimento,
                       DATEDIFF(r.data_vencimento, CURDATE()) as dias_ate_vencimento,
                       e.nome as estabelecimento_nome
                FROM royalties r
                INNER JOIN estabelecimentos e ON r.estabelecimento_id = e.id
                WHERE r.status = 'pendente'
                  AND r.estabelecimento_id = ?
                  AND r.data_vencimento BETWEEN DATE_SUB(CURDATE(), INTERVAL ? DAY) 
                                            AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                ORDER BY r.data_vencimento ASC
            ");
            $stmt->execute([$estab['estabelecimento_id'], $dias_apos, $dias_antes]);
            $royalties = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($royalties)) {
                foreach ($royalties as $royalty) {
                    if (!$this->jaEnviadoHoje($estab['estabelecimento_id'], 'royalty', $royalty['id'])) {
                        $html = $this->montarEmailRoyalty($royalty, $estab['estabelecimento_nome']);
                        $assunto = "👑 Alerta: Royalty Vencendo";
                        
                        if ($this->enviarEmail($estab['estabelecimento_id'], $estab['email_alerta'], $assunto, $html)) {
                            $this->registrarEnvio($estab['estabelecimento_id'], 'royalty', $royalty['id'],
                                                 $estab['email_alerta'], $assunto, $html);
                            $alertas_enviados++;
                        }
                    }
                }
            }
        }
        
        return $alertas_enviados;
    }
    
    /**
     * Verificar e enviar alertas de promoções
     */
    public function verificarPromocoes() {
        $alertas_enviados = 0;
        
        $stmt = $this->conn->query("
            SELECT e.id as estabelecimento_id, e.nome as estabelecimento_nome,
                   ec.email_alerta, ec.dias_antes_vencimento
            FROM estabelecimentos e
            INNER JOIN email_config ec ON e.id = ec.estabelecimento_id
            WHERE ec.status = 1 AND ec.notificar_promocoes = 1
              AND ec.email_alerta IS NOT NULL AND ec.email_alerta != ''
        ");
        $estabelecimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($estabelecimentos as $estab) {
            $dias_antes = $estab['dias_antes_vencimento'];
            
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
                    if (!$this->jaEnviadoHoje($estab['estabelecimento_id'], 'promocao', $promocao['id'])) {
                        $html = $this->montarEmailPromocao($promocao, $estab['estabelecimento_nome']);
                        $assunto = "🎉 Alerta: Promoção Expirando - {$promocao['nome']}";
                        
                        if ($this->enviarEmail($estab['estabelecimento_id'], $estab['email_alerta'], $assunto, $html)) {
                            $this->registrarEnvio($estab['estabelecimento_id'], 'promocao', $promocao['id'],
                                                 $estab['email_alerta'], $assunto, $html);
                            $alertas_enviados++;
                        }
                    }
                }
            }
        }
        
        return $alertas_enviados;
    }
    
    /**
     * Montar HTML do e-mail de estoque
     */
    private function montarEmailEstoque($produto, $estabelecimento) {
        $cor_alerta = $produto['estoque_atual'] == 0 ? '#dc3545' : '#ffc107';
        $titulo = $produto['estoque_atual'] == 0 ? 'ESTOQUE ZERADO!' : 'Estoque Mínimo Atingido';
        
        return $this->montarTemplateEmail($titulo, $cor_alerta, "
            <p><strong>Estabelecimento:</strong> {$estabelecimento}</p>
            <p><strong>Produto:</strong> {$produto['nome']}</p>
            <p><strong>Código:</strong> {$produto['codigo']}</p>
            <hr>
            <p><strong>Estoque Atual:</strong> <span style='color: {$cor_alerta}; font-weight: bold;'>{$produto['estoque_atual']} unidades</span></p>
            <p><strong>Estoque Mínimo:</strong> {$produto['estoque_minimo']} unidades</p>
            <p><strong>Quantidade a Repor:</strong> {$produto['quantidade_repor']} unidades</p>
        ");
    }
    
    /**
     * Montar HTML do e-mail de conta
     */
    private function montarEmailConta($conta, $estabelecimento) {
        $dias = $conta['dias_ate_vencimento'];
        
        if ($dias < 0) {
            $cor_alerta = '#dc3545';
            $status = "VENCIDA HÁ " . abs($dias) . " DIAS";
        } elseif ($dias == 0) {
            $cor_alerta = '#ff6b6b';
            $status = "VENCE HOJE";
        } else {
            $cor_alerta = '#ffc107';
            $status = "Vence em {$dias} dias";
        }
        
        $fornecedor_html = $conta['fornecedor_nome'] ? "<p><strong>Fornecedor:</strong> {$conta['fornecedor_nome']}</p>" : '';
        
        return $this->montarTemplateEmail('Conta a Pagar', $cor_alerta, "
            <p><strong>Estabelecimento:</strong> {$estabelecimento}</p>
            <p><strong>Descrição:</strong> {$conta['descricao']}</p>
            {$fornecedor_html}
            <hr>
            <p><strong>Valor:</strong> R$ " . number_format($conta['valor'], 2, ',', '.') . "</p>
            <p><strong>Vencimento:</strong> " . date('d/m/Y', strtotime($conta['data_vencimento'])) . "</p>
            <p style='color: {$cor_alerta}; font-weight: bold;'>⚠️ {$status}</p>
        ");
    }
    
    /**
     * Montar HTML do e-mail de royalty
     */
    private function montarEmailRoyalty($royalty, $estabelecimento) {
        $dias = $royalty['dias_ate_vencimento'];
        $cor_alerta = $dias <= 0 ? '#dc3545' : '#ffc107';
        
        return $this->montarTemplateEmail('Royalty Vencendo', $cor_alerta, "
            <p><strong>Estabelecimento:</strong> {$estabelecimento}</p>
            <p><strong>Período:</strong> " . date('d/m/Y', strtotime($royalty['periodo_inicio'])) . " a " . date('d/m/Y', strtotime($royalty['periodo_fim'])) . "</p>
            <hr>
            <p><strong>Valor:</strong> R$ " . number_format($royalty['valor_royalties'], 2, ',', '.') . "</p>
            <p><strong>Vencimento:</strong> " . date('d/m/Y', strtotime($royalty['data_vencimento'])) . "</p>
            <p style='color: {$cor_alerta}; font-weight: bold;'>⚠️ Vence em {$dias} dias</p>
        ");
    }
    
    /**
     * Montar HTML do e-mail de promoção
     */
    private function montarEmailPromocao($promocao, $estabelecimento) {
        $dias = $promocao['dias_ate_fim'];
        $cor_alerta = $dias == 0 ? '#dc3545' : '#ffc107';
        $descricao_html = $promocao['descricao'] ? "<p>{$promocao['descricao']}</p>" : '';
        $desconto_html = $promocao['desconto_percentual'] ? "<p><strong>Desconto:</strong> {$promocao['desconto_percentual']}%</p>" : '';
        
        return $this->montarTemplateEmail('Promoção Expirando', $cor_alerta, "
            <p><strong>Estabelecimento:</strong> {$estabelecimento}</p>
            <p><strong>Promoção:</strong> {$promocao['nome']}</p>
            {$descricao_html}
            {$desconto_html}
            <hr>
            <p><strong>Data Fim:</strong> " . date('d/m/Y', strtotime($promocao['data_fim'])) . "</p>
            <p style='color: {$cor_alerta}; font-weight: bold;'>⚠️ Expira em {$dias} dias</p>
        ");
    }
    
    /**
     * Template HTML base para e-mails
     */
    private function montarTemplateEmail($titulo, $cor, $conteudo) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: {$cor}; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 0 0 5px 5px; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
                hr { border: none; border-top: 1px solid #ddd; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>⚠️ {$titulo}</h2>
                </div>
                <div class='content'>
                    {$conteudo}
                </div>
                <div class='footer'>
                    <p>Este é um e-mail automático do sistema Chopp On Tap</p>
                    <p>Data/Hora: " . date('d/m/Y H:i:s') . "</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Enviar e-mail via SMTP
     */
    private function enviarEmail($estabelecimentoId, $destinatario, $assunto, $html) {
        try {
            // Buscar configuração SMTP
            $stmt = $this->conn->prepare("
                SELECT * FROM smtp_config 
                WHERE estabelecimento_id = ? AND status = 1
            ");
            $stmt->execute([$estabelecimentoId]);
            $smtp = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$smtp) {
                throw new Exception("Configuração SMTP não encontrada ou inativa");
            }
            
            $mail = new PHPMailer(true);
            
            // Configuração SMTP
            $mail->isSMTP();
            $mail->Host = $smtp['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $smtp['smtp_username'];
            $mail->Password = $smtp['smtp_password'];
            $mail->SMTPSecure = $smtp['smtp_secure'];
            $mail->Port = $smtp['smtp_port'];
            $mail->CharSet = 'UTF-8';
            
            // Remetente e destinatário
            $mail->setFrom($smtp['from_email'], $smtp['from_name']);
            $mail->addAddress($destinatario);
            
            // Conteúdo
            $mail->isHTML(true);
            $mail->Subject = $assunto;
            $mail->Body = $html;
            
            return $mail->send();
            
        } catch (Exception $e) {
            $this->registrarLog($estabelecimentoId, 'outro', null, $destinatario, $assunto, $html, 'erro', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar se alerta já foi enviado hoje
     */
    private function jaEnviadoHoje($estabelecimentoId, $tipo, $referenciaId) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as total
            FROM email_alerts_sent
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
    private function registrarEnvio($estabelecimentoId, $tipo, $referenciaId, $destinatario, $assunto, $mensagem) {
        try {
            $this->registrarLog($estabelecimentoId, $tipo, $referenciaId, $destinatario, $assunto, $mensagem, 'enviado');
            
            $stmt = $this->conn->prepare("
                INSERT INTO email_alerts_sent 
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
     * Registrar log de e-mail
     */
    private function registrarLog($estabelecimentoId, $tipo, $referenciaId, $destinatario, $assunto, $mensagem, $status = 'enviado', $erroMensagem = null) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO email_notifications_log 
                (estabelecimento_id, tipo, referencia_id, destinatario, assunto, mensagem, status, erro_mensagem, enviado_em)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$estabelecimentoId, $tipo, $referenciaId, $destinatario, $assunto, $mensagem, $status, $erroMensagem]);
        } catch (Exception $e) {
            // Silencioso
        }
    }
    
    /**
     * Executar todas as verificações
     */
    public function executarTodasVerificacoes() {
        $total = 0;
        
        $total += $this->verificarEstoqueMinimo();
        $total += $this->verificarContasPagar();
        $total += $this->verificarRoyalties();
        $total += $this->verificarPromocoes();
        
        return $total;
    }
    
    /**
     * Enviar e-mail de teste
     */
    public function enviarEmailTeste($estabelecimentoId, $destinatario) {
        $html = $this->montarTemplateEmail('Teste de Configuração SMTP', '#28a745', "
            <p>Este é um e-mail de teste para verificar a configuração SMTP.</p>
            <p>Se você recebeu este e-mail, significa que a configuração está correta!</p>
            <hr>
            <p><strong>Estabelecimento ID:</strong> {$estabelecimentoId}</p>
            <p><strong>Destinatário:</strong> {$destinatario}</p>
        ");
        
        return $this->enviarEmail($estabelecimentoId, $destinatario, '✅ Teste de E-mail - Chopp On Tap', $html);
    }
}
?>
