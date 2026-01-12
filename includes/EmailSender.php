<?php
/**
 * Classe para Envio de E-mails via SMTP
 * Usa PHPMailer para envio robusto
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Incluir PHPMailer (assumindo que está instalado via Composer ou manualmente)
// Se não estiver instalado, baixe de: https://github.com/PHPMailer/PHPMailer
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';

class EmailSender {
    private $conn;
    private $config;
    private $logger;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->carregarConfiguracao();
        
        // Inicializar logger se existir
        if (class_exists('RoyaltiesLogger')) {
            $this->logger = new RoyaltiesLogger('email');
        }
    }
    
    /**
     * Carregar configuração SMTP do banco
     */
    private function carregarConfiguracao() {
        $stmt = $this->conn->query("SELECT * FROM smtp_config WHERE ativo = TRUE LIMIT 1");
        $this->config = $stmt->fetch();
        
        if (!$this->config) {
            throw new Exception('Configuração SMTP não encontrada ou inativa');
        }
    }
    
    /**
     * Enviar e-mail
     * 
     * @param string|array $para Destinatário(s)
     * @param string $assunto Assunto do e-mail
     * @param string $corpo Corpo do e-mail (HTML)
     * @param array $anexos Arquivos para anexar (opcional)
     * @return array Resultado do envio
     */
    public function enviar($para, $assunto, $corpo, $anexos = []) {
        try {
            $mail = new PHPMailer(true);
            
            // Configurações do servidor
            $mail->isSMTP();
            $mail->Host = $this->config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['username'];
            $mail->Password = base64_decode($this->config['password']);
            $mail->Port = $this->config['port'];
            
            // Criptografia
            if ($this->config['encryption'] === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($this->config['encryption'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }
            
            // Charset
            $mail->CharSet = 'UTF-8';
            
            // Remetente
            $mail->setFrom($this->config['from_email'], $this->config['from_name']);
            
            // Destinatários
            if (is_array($para)) {
                foreach ($para as $email) {
                    $mail->addAddress(trim($email));
                }
            } else {
                $mail->addAddress($para);
            }
            
            // Conteúdo
            $mail->isHTML(true);
            $mail->Subject = $assunto;
            $mail->Body = $corpo;
            $mail->AltBody = strip_tags($corpo); // Versão texto
            
            // Anexos
            foreach ($anexos as $anexo) {
                if (file_exists($anexo)) {
                    $mail->addAttachment($anexo);
                }
            }
            
            // Enviar
            $mail->send();
            
            if ($this->logger) {
                $this->logger->success("E-mail enviado", [
                    'para' => is_array($para) ? implode(', ', $para) : $para,
                    'assunto' => $assunto
                ]);
            }
            
            return [
                'success' => true,
                'message' => 'E-mail enviado com sucesso'
            ];
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Erro ao enviar e-mail", [
                    'para' => is_array($para) ? implode(', ', $para) : $para,
                    'assunto' => $assunto,
                    'erro' => $mail->ErrorInfo
                ]);
            }
            
            return [
                'success' => false,
                'message' => 'Erro ao enviar e-mail: ' . $mail->ErrorInfo
            ];
        }
    }
    
    /**
     * Enviar e-mail de teste
     */
    public function enviarEmailTeste($email) {
        $assunto = "Teste de Configuração SMTP - " . $this->config['from_name'];
        
        $corpo = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #007bff; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
                .success { background: #28a745; color: white; padding: 15px; border-radius: 4px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 20px; color: #6c757d; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>✅ Teste de E-mail</h1>
                </div>
                <div class='content'>
                    <div class='success'>
                        <strong>Parabéns!</strong> Sua configuração SMTP está funcionando corretamente!
                    </div>
                    
                    <p>Este é um e-mail de teste enviado pelo sistema <strong>" . htmlspecialchars($this->config['from_name']) . "</strong>.</p>
                    
                    <p><strong>Informações da Configuração:</strong></p>
                    <ul>
                        <li><strong>Servidor:</strong> " . htmlspecialchars($this->config['host']) . "</li>
                        <li><strong>Porta:</strong> " . $this->config['port'] . "</li>
                        <li><strong>Criptografia:</strong> " . strtoupper($this->config['encryption']) . "</li>
                        <li><strong>Remetente:</strong> " . htmlspecialchars($this->config['from_email']) . "</li>
                    </ul>
                    
                    <p>Se você recebeu este e-mail, significa que o sistema está pronto para enviar notificações, alertas e cobranças de royalties.</p>
                    
                    <p><strong>Data/Hora:</strong> " . date('d/m/Y H:i:s') . "</p>
                </div>
                <div class='footer'>
                    <p>Este é um e-mail automático. Não responda.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->enviar($email, $assunto, $corpo);
    }
    
    /**
     * Enviar e-mail de cobrança de royalty
     */
    public function enviarCobrancaRoyalty($royalty, $estabelecimento, $emails) {
        require_once __DIR__ . '/EmailTemplate.php';
        
        $template = new EmailTemplate();
        $assunto = $template->getAssuntoCobranca($royalty, $estabelecimento);
        $corpo = $template->getTemplateCobranca($royalty, $estabelecimento);
        
        return $this->enviar($emails, $assunto, $corpo);
    }
    
    /**
     * Enviar e-mail de confirmação de pagamento
     */
    public function enviarConfirmacaoPagamento($royalty, $estabelecimento, $emails) {
        require_once __DIR__ . '/EmailTemplate.php';
        
        $template = new EmailTemplate();
        $assunto = $template->getAssuntoConfirmacao($royalty, $estabelecimento);
        $corpo = $template->getTemplateConfirmacao($royalty, $estabelecimento);
        
        return $this->enviar($emails, $assunto, $corpo);
    }
    
    /**
     * Enviar alerta genérico
     */
    public function enviarAlerta($tipo, $titulo, $mensagem, $emails, $dados = []) {
        require_once __DIR__ . '/EmailTemplate.php';
        
        $template = new EmailTemplate();
        $assunto = $titulo;
        $corpo = $template->getTemplateAlerta($tipo, $titulo, $mensagem, $dados);
        
        return $this->enviar($emails, $assunto, $corpo);
    }
}
