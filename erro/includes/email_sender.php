<?php
/**
 * Classe para envio de e-mails de alerta
 * 
 * Requer uma biblioteca de envio de e-mail (ex: PHPMailer, mas usaremos a função mail() nativa por simplicidade no sandbox)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/logger.php';

class EmailSender {
    private $conn;
    private $logger;
    
    // Configurações SMTP (Exemplo - devem ser configuradas em config.php para produção)
    const SMTP_HOST = 'smtp.exemplo.com';
    const SMTP_USER = 'alerta@exemplo.com';
    const SMTP_PASS = 'sua_senha';
    const SMTP_PORT = 587;
    const FROM_EMAIL = 'alerta@choppontap.com';
    const FROM_NAME = 'Chopp On Tap Alertas';
    
    public function __construct($conn = null) {
        $this->conn = $conn ?? getDBConnection();
        $this->logger = Logger::getInstance();
    }
    
    /**
     * Envia um e-mail.
     * 
     * @param string $to_email E-mail do destinatário.
     * @param string $subject Assunto do e-mail.
     * @param string $body Corpo do e-mail (HTML).
     * @return bool
     */
    private function sendEmail($to_email, $subject, $body) {
        // Para o ambiente de sandbox, usaremos a função mail() nativa ou simularemos o envio.
        // Em um ambiente real, uma biblioteca como PHPMailer seria usada com as configurações SMTP.
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: " . self::FROM_NAME . " <" . self::FROM_EMAIL . ">" . "\r\n";
        
        // Simulação de envio para o log
        $this->logger->log('email', "Tentativa de envio de e-mail para: $to_email - Assunto: $subject");
        
        // Em um ambiente real, descomente a linha abaixo e configure o SMTP
        // return mail($to_email, $subject, $body, $headers);
        
        // Simulação de sucesso no sandbox
        return true;
    }
    
    /**
     * Envia um alerta de e-mail para um estabelecimento.
     * 
     * @param int $estabelecimento_id ID do estabelecimento.
     * @param string $subject Assunto do e-mail.
     * @param string $body Corpo do e-mail (HTML).
     * @param string $type Tipo de alerta (venda, volume_critico, contas_pagar).
     * @return bool
     */
    public function sendAlert($estabelecimento_id, $subject, $body, $type) {
        try {
            $config = $this->getConfig($estabelecimento_id);
            
            if (!$config || !$config['status']) {
                $this->logger->log('email', "Alerta de e-mail não configurado ou desativado para estabelecimento $estabelecimento_id");
                return false;
            }
            
            if (!$this->isNotificationEnabled($config, $type)) {
                $this->logger->log('email', "Notificação tipo '$type' desativada para estabelecimento $estabelecimento_id");
                return false;
            }
            
            $to_email = $config['email_alerta'];
            
            if (empty($to_email) || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
                $this->logger->log('email', "E-mail de alerta inválido ou não configurado para estabelecimento $estabelecimento_id");
                return false;
            }
            
            $success = $this->sendEmail($to_email, $subject, $body);
            
            if ($success) {
                $this->logger->log('email', "Alerta de e-mail enviado com sucesso para $to_email (Estabelecimento $estabelecimento_id)");
            } else {
                $this->logger->log('email', "Falha ao enviar alerta de e-mail para $to_email (Estabelecimento $estabelecimento_id)", 'ERROR');
            }
            
            return $success;
            
        } catch (Exception $e) {
            $this->logger->log('email', "Exceção ao enviar alerta de e-mail: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Busca configuração de e-mail para um estabelecimento.
     */
    private function getConfig($estabelecimento_id) {
        $stmt = $this->conn->prepare("
            SELECT ec.*, e.email_alerta
            FROM email_config ec
            INNER JOIN estabelecimentos e ON ec.estabelecimento_id = e.id
            WHERE ec.estabelecimento_id = ? AND ec.status = 1
            LIMIT 1
        ");
        $stmt->execute([$estabelecimento_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Verifica se um tipo de notificação está habilitado.
     */
    private function isNotificationEnabled($config, $type) {
        switch ($type) {
            case 'venda':
                return (bool)$config['notificar_vendas'];
            case 'volume_critico':
                return (bool)$config['notificar_volume_critico'];
            case 'contas_pagar':
                return (bool)$config['notificar_contas_pagar'];
            default:
                return true; // Outros tipos sempre habilitados
        }
    }
    
    // Métodos de formatação de e-mail (a serem implementados conforme a necessidade)
    
    /**
     * Formata o corpo do e-mail para alerta de Contas a Pagar.
     */
    public static function formatContasPagarBody($contas, $estabelecimento_name, $dias_alerta) {
        $subject_prefix = '';
        if ($dias_alerta > 0) {
            $subject_prefix = "Lembrete: Contas a Pagar Vencendo em $dias_alerta Dia(s)";
        } elseif ($dias_alerta == 0) {
            $subject_prefix = "ALERTA: Contas a Pagar Vencendo HOJE";
        } else {
            $subject_prefix = "URGENTE: Contas a Pagar VENCIDAS";
        }
        
        $html = "<html><body>";
        $html .= "<h2>{$subject_prefix}</h2>";
        $html .= "<p><strong>Estabelecimento:</strong> {$estabelecimento_name}</p>";
        $html .= "<table border='1' cellpadding='5' cellspacing='0' style='width: 100%; border-collapse: collapse;'>";
        $html .= "<thead><tr><th>Descrição</th><th>Tipo</th><th>Valor</th><th>Vencimento</th></tr></thead>";
        $html .= "<tbody>";
        
        foreach ($contas as $conta) {
            $html .= "<tr>";
            $html .= "<td>" . htmlspecialchars($conta['descricao']) . "</td>";
            $html .= "<td>" . htmlspecialchars($conta['tipo']) . "</td>";
            $html .= "<td>" . formatarValor($conta['valor']) . "</td>";
            $html .= "<td>" . formatarData($conta['data_vencimento']) . "</td>";
            $html .= "</tr>";
        }
        
        $html .= "</tbody></table>";
        $html .= "<p>Por favor, verifique o sistema para mais detalhes e efetue o pagamento.</p>";
        $html .= "<p>Atenciosamente,<br>Equipe Chopp On Tap</p>";
        $html .= "</body></html>";
        
        return $html;
    }
    
    /**
     * Formata o corpo do e-mail para alerta de Volume Crítico.
     */
    public static function formatVolumeCriticoBody($tap) {
        $volume_restante = $tap['volume'] - $tap['volume_consumido'];
        $percentual = ($volume_restante / $tap['volume']) * 100;
        
        $html = "<html><body>";
        $html .= "<h2>⚠️ ALERTA: Volume Crítico de Chopp!</h2>";
        $html .= "<p>O barril da TAP <strong>{$tap['android_id']}</strong> no estabelecimento <strong>{$tap['estabelecimento_nome']}</strong> atingiu o volume crítico.</p>";
        $html .= "<ul>";
        $html .= "<li><strong>Bebida:</strong> {$tap['bebida_nome']} ({$tap['bebida_marca']})</li>";
        $html .= "<li><strong>Volume Restante:</strong> " . number_format($volume_restante, 2, ',', '.') . " L</li>";
        $html .= "<li><strong>Percentual:</strong> " . number_format($percentual, 1, ',', '.') . "%</li>";
        $html .= "<li><strong>Volume Crítico Configurado:</strong> " . number_format($tap['volume_critico'], 2, ',', '.') . " L</li>";
        $html .= "</ul>";
        $html .= "<p><strong>Ação Necessária:</strong> Providencie a troca do barril o mais rápido possível para evitar interrupção nas vendas.</p>";
        $html .= "<p>Atenciosamente,<br>Equipe Chopp On Tap</p>";
        $html .= "</body></html>";
        
        return $html;
    }
    
    /**
     * Formata o corpo do e-mail para alerta de Nova Venda.
     */
    public static function formatVendaBody($order) {
        $html = "<html><body>";
        $html .= "<h2>🍺 Nova Venda Realizada!</h2>";
        $html .= "<p>Uma nova venda foi registrada no estabelecimento <strong>{$order['estabelecimento_nome']}</strong>.</p>";
        $html .= "<table border='1' cellpadding='5' cellspacing='0' style='width: 100%; border-collapse: collapse;'>";
        $html .= "<tr><td><strong>Método de Pagamento:</strong></td><td>{$order['method']}</td></tr>";
        $html .= "<tr><td><strong>Valor:</strong></td><td>R$ " . number_format($order['valor'], 2, ',', '.') . "</td></tr>";
        $html .= "<tr><td><strong>Bebida:</strong></td><td>{$order['bebida_nome']}</td></tr>";
        $html .= "<tr><td><strong>Quantidade:</strong></td><td>{$order['quantidade']} ml</td></tr>";
        if (!empty($order['cpf'])) {
            $html .= "<tr><td><strong>CPF:</strong></td><td>{$order['cpf']}</td></tr>";
        }
        $html .= "<tr><td><strong>Data/Hora:</strong></td><td>" . date('d/m/Y H:i:s') . "</td></tr>";
        $html .= "</table>";
        $html .= "<p>Atenciosamente,<br>Equipe Chopp On Tap</p>";
        $html .= "</body></html>";
        
        return $html;
    }
}

// Funções auxiliares (necessárias para o formatContasPagarBody)
if (!function_exists('formatarValor')) {
    function formatarValor($valor) {
        return 'R$ ' . number_format($valor, 2, ',', '.');
    }
}

if (!function_exists('formatarData')) {
    function formatarData($data) {
        $timestamp = strtotime($data);
        return date('d/m/Y', $timestamp);
    }
}
?>
