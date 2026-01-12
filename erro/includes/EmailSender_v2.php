<?php
/**
 * EmailSender - Biblioteca para envio de e-mails
 * Baseado em padrões testados do CRM INLAUDO
 * Usa configurações do banco de dados
 */

require_once __DIR__ . '/config.php';

class EmailSender {
    
    /**
     * Enviar e-mail usando configuração do banco de dados
     * 
     * @param string $destinatario E-mail do destinatário
     * @param string $assunto Assunto do e-mail
     * @param string $corpoHtml Corpo do e-mail em HTML
     * @param string $corpoTexto Corpo do e-mail em texto puro (opcional)
     * @param string $destinatarioNome Nome do destinatário (opcional)
     * @param string $referenciaTipo Tipo da entidade relacionada (opcional)
     * @param int $referenciaId ID da entidade relacionada (opcional)
     * @return array ['sucesso' => bool, 'mensagem' => string]
     */
    public static function enviar(
        $destinatario,
        $assunto,
        $corpoHtml,
        $corpoTexto = null,
        $destinatarioNome = null,
        $referenciaTipo = null,
        $referenciaId = null
    ) {
        try {
            // Validar e-mail
            if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('E-mail de destinatário inválido: ' . $destinatario);
            }
            
            // Buscar configuração ativa
            $conn = getDBConnection();
            $stmt = $conn->query("SELECT * FROM email_config WHERE ativo = TRUE LIMIT 1");
            $config = $stmt->fetch();
            
            if (!$config) {
                throw new Exception('Nenhuma configuração de e-mail ativa encontrada. Configure em Admin > SMTP Config.');
            }
            
            // Verificar se está em modo de teste
            $destinatarioOriginal = $destinatario;
            if ($config['testar_envio'] && !empty($config['email_teste'])) {
                $destinatario = $config['email_teste'];
                $assunto = "[TESTE] " . $assunto . " (Original: $destinatarioOriginal)";
            }
            
            // Enviar e-mail
            $resultado = self::enviarSMTP(
                $config,
                $destinatario,
                $destinatarioNome,
                $assunto,
                $corpoHtml,
                $corpoTexto
            );
            
            // Registrar no histórico
            self::registrarHistorico(
                $destinatario,
                $destinatarioNome,
                $assunto,
                $corpoHtml,
                $resultado['sucesso'] ? 'enviado' : 'erro',
                $resultado['sucesso'] ? null : $resultado['mensagem'],
                $referenciaTipo,
                $referenciaId
            );
            
            return $resultado;
            
        } catch (Exception $e) {
            Logger::error("Erro ao enviar e-mail", [
                'destinatario' => $destinatario ?? 'N/A',
                'assunto' => $assunto,
                'erro' => $e->getMessage()
            ]);
            
            return [
                'sucesso' => false,
                'mensagem' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Enviar e-mail via SMTP usando funções nativas do PHP
     */
    private static function enviarSMTP($config, $destinatario, $destinatarioNome, $assunto, $corpoHtml, $corpoTexto) {
        try {
            // Preparar headers
            $boundary = md5(time());
            
            $headers = [];
            $headers[] = "From: {$config['from_name']} <{$config['from_email']}>";
            $headers[] = "Reply-To: " . ($config['reply_to_email'] ?: $config['from_email']);
            if ($destinatarioNome) {
                $headers[] = "To: {$destinatarioNome} <{$destinatario}>";
            }
            $headers[] = "MIME-Version: 1.0";
            $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
            $headers[] = "X-Mailer: PHP/" . phpversion();
            $headers[] = "X-Priority: 3";
            
            // Preparar corpo do e-mail
            $message = "--{$boundary}\r\n";
            
            // Parte texto
            if ($corpoTexto) {
                $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
                $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
                $message .= $corpoTexto . "\r\n\r\n";
                $message .= "--{$boundary}\r\n";
            }
            
            // Parte HTML
            $message .= "Content-Type: text/html; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $message .= $corpoHtml . "\r\n\r\n";
            $message .= "--{$boundary}--";
            
            // Tentar enviar via mail() nativo primeiro (mais simples)
            $enviado = mail(
                $destinatario,
                $assunto,
                $message,
                implode("\r\n", $headers)
            );
            
            if ($enviado) {
                Logger::info("E-mail enviado com sucesso", [
                    'destinatario' => $destinatario,
                    'assunto' => $assunto
                ]);
                
                return [
                    'sucesso' => true,
                    'mensagem' => 'E-mail enviado com sucesso'
                ];
            } else {
                // Se mail() falhar, tentar via socket SMTP
                return self::enviarViaSMTPSocket($config, $destinatario, $assunto, $message, $headers);
            }
            
        } catch (Exception $e) {
            Logger::error("Erro ao enviar e-mail via SMTP", [
                'erro' => $e->getMessage()
            ]);
            
            return [
                'sucesso' => false,
                'mensagem' => 'Erro ao enviar e-mail: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Enviar via socket SMTP (fallback)
     */
    private static function enviarViaSMTPSocket($config, $destinatario, $assunto, $message, $headers) {
        try {
            // Conectar ao servidor SMTP
            $host = $config['smtp_secure'] == 'ssl' ? 'ssl://' . $config['smtp_host'] : $config['smtp_host'];
            
            $socket = fsockopen(
                $host,
                $config['smtp_port'],
                $errno,
                $errstr,
                30
            );
            
            if (!$socket) {
                throw new Exception("Não foi possível conectar ao servidor SMTP: $errstr ($errno)");
            }
            
            // Ler resposta inicial
            $response = fgets($socket, 515);
            if (substr($response, 0, 3) != '220') {
                throw new Exception("Erro na conexão SMTP: $response");
            }
            
            // EHLO
            fputs($socket, "EHLO localhost\r\n");
            $response = fgets($socket, 515);
            
            // STARTTLS se necessário
            if ($config['smtp_secure'] == 'tls') {
                fputs($socket, "STARTTLS\r\n");
                $response = fgets($socket, 515);
                if (substr($response, 0, 3) != '220') {
                    throw new Exception("Erro ao iniciar TLS: $response");
                }
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                fputs($socket, "EHLO localhost\r\n");
                $response = fgets($socket, 515);
            }
            
            // AUTH LOGIN
            fputs($socket, "AUTH LOGIN\r\n");
            $response = fgets($socket, 515);
            
            fputs($socket, base64_encode($config['smtp_user']) . "\r\n");
            $response = fgets($socket, 515);
            
            fputs($socket, base64_encode($config['smtp_password']) . "\r\n");
            $response = fgets($socket, 515);
            if (substr($response, 0, 3) != '235') {
                throw new Exception("Erro na autenticação SMTP: $response");
            }
            
            // MAIL FROM
            fputs($socket, "MAIL FROM: <{$config['from_email']}>\r\n");
            $response = fgets($socket, 515);
            
            // RCPT TO
            fputs($socket, "RCPT TO: <{$destinatario}>\r\n");
            $response = fgets($socket, 515);
            
            // DATA
            fputs($socket, "DATA\r\n");
            $response = fgets($socket, 515);
            
            // Enviar headers e mensagem
            fputs($socket, implode("\r\n", $headers) . "\r\n");
            fputs($socket, "Subject: $assunto\r\n");
            fputs($socket, "\r\n");
            fputs($socket, $message);
            fputs($socket, "\r\n.\r\n");
            $response = fgets($socket, 515);
            
            // QUIT
            fputs($socket, "QUIT\r\n");
            fclose($socket);
            
            Logger::info("E-mail enviado via SMTP Socket", [
                'destinatario' => $destinatario,
                'assunto' => $assunto
            ]);
            
            return [
                'sucesso' => true,
                'mensagem' => 'E-mail enviado com sucesso via SMTP'
            ];
            
        } catch (Exception $e) {
            Logger::error("Erro ao enviar via SMTP Socket", [
                'erro' => $e->getMessage()
            ]);
            
            return [
                'sucesso' => false,
                'mensagem' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Enviar e-mail de teste
     */
    public static function enviarEmailTeste($email) {
        $assunto = "Teste de Configuração SMTP - " . SITE_NAME;
        
        $corpoHtml = "
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
                    <h1>✅ Teste de Configuração SMTP</h1>
                </div>
                <div class='content'>
                    <div class='success'>
                        <strong>Parabéns!</strong> Sua configuração SMTP está funcionando corretamente!
                    </div>
                    
                    <p>Este é um e-mail de teste enviado pelo sistema <strong>" . htmlspecialchars(SITE_NAME) . "</strong>.</p>
                    
                    <p><strong>Informações da Configuração:</strong></p>
                    <ul>
                        <li><strong>Sistema:</strong> " . htmlspecialchars(SITE_NAME) . "</li>
                        <li><strong>Versão:</strong> " . SYSTEM_VERSION . "</li>
                        <li><strong>Data/Hora:</strong> " . date('d/m/Y H:i:s') . "</li>
                    </ul>
                    
                    <p>Se você recebeu este e-mail, significa que o sistema está pronto para enviar notificações, alertas e cobranças.</p>
                </div>
                <div class='footer'>
                    <p>Este é um e-mail automático. Não responda.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $corpoTexto = "Teste de Configuração SMTP\n\n" .
                     "Parabéns! Sua configuração SMTP está funcionando corretamente!\n\n" .
                     "Se você recebeu este e-mail, o sistema está pronto para enviar notificações.\n\n" .
                     "Data/Hora: " . date('d/m/Y H:i:s');
        
        return self::enviar($email, $assunto, $corpoHtml, $corpoTexto);
    }
    
    /**
     * Registrar histórico de e-mail
     */
    private static function registrarHistorico(
        $destinatario,
        $destinatarioNome,
        $assunto,
        $corpoHtml,
        $status,
        $mensagemErro,
        $referenciaTipo,
        $referenciaId
    ) {
        try {
            $conn = getDBConnection();
            
            // Verificar se tabela existe
            $stmt = $conn->query("SHOW TABLES LIKE 'email_historico'");
            if ($stmt->rowCount() == 0) {
                return; // Tabela não existe, pular
            }
            
            $sql = "INSERT INTO email_historico 
                    (destinatario, destinatario_nome, assunto, corpo_html, status, mensagem_erro, referencia_tipo, referencia_id, data_envio)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $destinatario,
                $destinatarioNome,
                $assunto,
                $corpoHtml,
                $status,
                $mensagemErro,
                $referenciaTipo,
                $referenciaId
            ]);
            
        } catch (Exception $e) {
            Logger::error("Erro ao registrar histórico de e-mail", [
                'erro' => $e->getMessage()
            ]);
        }
    }
}
?>
