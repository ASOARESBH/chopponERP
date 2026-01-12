<?php
/**
 * Cron Job: Verificar Vencimento de Barris
 * 
 * Executa diariamente para verificar barris próximos do vencimento
 * e enviar alertas via Telegram
 * 
 * Alertas:
 * - 10 dias antes do vencimento
 * - 2 dias antes do vencimento
 * - No dia do vencimento (vencido)
 * 
 * Configurar no cPanel:
 * 0 8 * * * php /home/usuario/public_html/cron/check_vencimento.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/telegram.php';

$conn = getDBConnection();
$logger = Logger::getInstance();

$logger->log('cron', "=== Iniciando verificação de vencimento ===");

try {
    // Buscar TAPs com vencimento próximo ou vencidos
    $stmt = $conn->query("
        SELECT 
            t.id,
            t.vencimento,
            t.alerta_10dias_enviado,
            t.alerta_2dias_enviado,
            t.alerta_vencido_enviado,
            DATEDIFF(t.vencimento, CURDATE()) as dias_para_vencer,
            b.name as bebida_nome,
            b.brand as bebida_marca,
            e.id as estabelecimento_id,
            e.name as estabelecimento_nome
        FROM tap t
        INNER JOIN bebidas b ON t.bebida_id = b.id
        INNER JOIN estabelecimentos e ON t.estabelecimento_id = e.id
        WHERE t.status = 1
    ");
    
    $taps = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $logger->log('cron', "Encontradas " . count($taps) . " TAPs ativas");
    
    $telegram = new TelegramBot($conn);
    $alertas_enviados = 0;
    
    foreach ($taps as $tap) {
        $dias = (int)$tap['dias_para_vencer'];
        
        $logger->log('cron', "Processando TAP {$tap['id']} - {$tap['bebida_nome']} - Dias para vencer: $dias");
        
        // Verificar qual alerta enviar
        $enviar_alerta = false;
        $tipo_alerta = '';
        $campo_update = '';
        
        if ($dias < 0 && !$tap['alerta_vencido_enviado']) {
            // Vencido
            $enviar_alerta = true;
            $tipo_alerta = 'vencido';
            $campo_update = 'alerta_vencido_enviado';
            $logger->log('cron', "TAP {$tap['id']} VENCIDA há " . abs($dias) . " dias");
            
        } elseif ($dias >= 0 && $dias <= 2 && !$tap['alerta_2dias_enviado']) {
            // 2 dias ou menos
            $enviar_alerta = true;
            $tipo_alerta = 'vencimento_2d';
            $campo_update = 'alerta_2dias_enviado';
            $logger->log('cron', "TAP {$tap['id']} vence em $dias dias (alerta 2 dias)");
            
        } elseif ($dias >= 3 && $dias <= 10 && !$tap['alerta_10dias_enviado']) {
            // 10 dias ou menos
            $enviar_alerta = true;
            $tipo_alerta = 'vencimento_10d';
            $campo_update = 'alerta_10dias_enviado';
            $logger->log('cron', "TAP {$tap['id']} vence em $dias dias (alerta 10 dias)");
        }
        
        if ($enviar_alerta) {
            // Formatar mensagem
            $message = TelegramBot::formatVencimentoMessage($tap, $dias);
            
            // Enviar alerta
            if ($telegram->sendMessage($tap['estabelecimento_id'], $message, $tipo_alerta, $tap['id'])) {
                // Marcar como enviado
                $stmt = $conn->prepare("UPDATE tap SET $campo_update = 1 WHERE id = ?");
                $stmt->execute([$tap['id']]);
                
                $alertas_enviados++;
                $logger->log('cron', "Alerta '$tipo_alerta' enviado para TAP {$tap['id']}");
            } else {
                $logger->log('cron', "Falha ao enviar alerta '$tipo_alerta' para TAP {$tap['id']}", 'ERROR');
            }
        }
    }
    
    $logger->log('cron', "=== Verificação concluída: $alertas_enviados alertas enviados ===");
    
} catch (Exception $e) {
    $logger->log('cron', "ERRO: " . $e->getMessage(), 'ERROR');
}
?>
