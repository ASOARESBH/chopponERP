<?php
/**
 * Cron Job: Verificar Volume Crítico
 * 
 * Executa a cada hora para verificar TAPs com volume crítico
 * e enviar alertas via Telegram
 * 
 * Configurar no cPanel:
 * */5 * * * * php /home/usuario/public_html/cron/check_volume_critico.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/telegram.php';
require_once __DIR__ . '/../includes/email_sender.php';

$conn = getDBConnection();
$logger = Logger::getInstance();

$logger->log('cron', "=== Iniciando verificação de volume crítico ===");

try {
    // Buscar TAPs com volume crítico que ainda não foram notificadas
        $stmt = $conn->query("
            SELECT 
                t.id,
                t.volume,
                t.volume_consumido,
                t.volume_critico,
                t.alerta_critico_enviado,
                (t.volume - t.volume_consumido) as volume_restante,
                b.name as bebida_nome,
                b.brand as bebida_marca,
                e.id as estabelecimento_id,
                e.name as estabelecimento_nome,
                ec.email_alerta,
                ec.notificar_volume_critico
            FROM tap t
            INNER JOIN bebidas b ON t.bebida_id = b.id
            INNER JOIN estabelecimentos e ON t.estabelecimento_id = e.id
            LEFT JOIN email_config ec ON e.id = ec.estabelecimento_id AND ec.status = 1
            WHERE t.status = 1
              AND (t.volume - t.volume_consumido) <= t.volume_critico
              AND t.alerta_critico_enviado = 0
        ");
    
    $taps_criticos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $logger->log('cron', "Encontradas " . count($taps_criticos) . " TAPs em volume crítico");
    
    $telegram = new TelegramBot($conn);
    $alertas_enviados = 0;
    
    foreach ($taps_criticos as $tap) {
        $logger->log('cron', "Processando TAP {$tap['id']} - {$tap['bebida_nome']} - Volume: {$tap['volume_restante']}L");
        
        // --- Notificação Telegram ---
        $message = TelegramBot::formatVolumeCriticoMessage($tap);
        $telegram_sent = $telegram->sendMessage($tap['estabelecimento_id'], $message, 'volume_critico', $tap['id']);
        
        if ($telegram_sent) {
            $logger->log('cron', "Alerta Telegram enviado para TAP {$tap['id']}");
        } else {
            $logger->log('cron', "Falha ao enviar alerta Telegram para TAP {$tap['id']}", 'ERROR');
        }
        
        // --- Notificação E-mail ---
        $email_sent = false;
        if ($tap['notificar_volume_critico'] && !empty($tap['email_alerta'])) {
            $email_sender = new EmailSender($conn);
            $subject = "ALERTA: Volume Crítico de Chopp - {$tap['bebida_nome']} em {$tap['estabelecimento_nome']}";
            $body = EmailSender::formatVolumeCriticoBody($tap);
            
            $email_sent = $email_sender->sendAlert($tap['estabelecimento_id'], $subject, $body, 'volume_critico');
            
            if ($email_sent) {
                $logger->log('cron', "Alerta E-mail enviado para TAP {$tap['id']}");
            } else {
                $logger->log('cron', "Falha ao enviar alerta E-mail para TAP {$tap['id']}", 'ERROR');
            }
        }
        
        // Marcar como enviado se pelo menos um alerta foi enviado (ou se não havia configuração)
        if ($telegram_sent || $email_sent) {
            $stmt = $conn->prepare("UPDATE tap SET alerta_critico_enviado = 1 WHERE id = ?");
            $stmt->execute([$tap['id']]);
            $alertas_enviados++;
        }
    }
    
    $logger->log('cron', "=== Verificação concluída: $alertas_enviados alertas enviados ===");
    
} catch (Exception $e) {
    $logger->log('cron', "ERRO: " . $e->getMessage(), 'ERROR');
}
?>
