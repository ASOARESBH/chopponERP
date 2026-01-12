#!/usr/bin/env php
<?php
/**
 * Script CRON para notificar contas a vencer via Telegram
 * 
 * Execução recomendada: Diariamente às 08:00
 * Crontab: 0 8 * * * /usr/bin/php /caminho/para/cron/notificar_contas_vencer.php
 */

// Configurar para execução em linha de comando
if (php_sapi_name() !== 'cli') {
    die("Este script deve ser executado via linha de comando (CLI)\n");
}

// Incluir configurações
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/logger.php';
require_once dirname(__DIR__) . '/includes/telegram.php'; // Adicionado para incluir a função de envio do Telegram
require_once dirname(__DIR__) . '/includes/email_sender.php'; // Adicionado para incluir a classe de envio de e-mail

// Desabilitar limite de tempo
set_time_limit(0);

// Função para formatar valor em reais
function formatarValor($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

// Função para formatar data
function formatarData($data) {
    $timestamp = strtotime($data);
    return date('d/m/Y', $timestamp);
}

try {
    $conn = getDBConnection();
    
    echo "[" . date('Y-m-d H:i:s') . "] Iniciando verificação de contas a vencer...\n";
    Logger::info("Iniciando verificação de contas a vencer");
    
        // Buscar estabelecimentos com Telegram ou E-mail configurado
        $stmt = $conn->query("
            SELECT 
                e.id, 
                e.name, 
                tc.bot_token, 
                tc.chat_id,
                ec.email_alerta,
                ec.notificar_contas_pagar,
                ec.dias_antes_contas_pagar
            FROM estabelecimentos e
            LEFT JOIN telegram_config tc ON e.id = tc.estabelecimento_id AND tc.status = 1
            LEFT JOIN email_config ec ON e.id = ec.estabelecimento_id AND ec.status = 1
            WHERE e.status = 1 AND (tc.id IS NOT NULL OR ec.id IS NOT NULL)
        ");
        $estabelecimentos = $stmt->fetchAll();
        
        echo "Encontrados " . count($estabelecimentos) . " estabelecimento(s) com notificação configurada\n";
        
        foreach ($estabelecimentos as $estabelecimento) {
        echo "\n--- Processando: {$estabelecimento['name']} ---\n";
        
        // Obter dias de antecedência para e-mail (padrão 3)
        $dias_antes = $estabelecimento['dias_antes_contas_pagar'] ?? 3;
        
        // Buscar contas que vencem hoje
        $stmt = $conn->prepare("
            SELECT * FROM contas_pagar
            WHERE estabelecimento_id = ?
            AND status = 'pendente'
            AND data_vencimento = CURDATE()
            AND notificacao_enviada = 0
        ");
        $stmt->execute([$estabelecimento['id']]);
        $contas_hoje = $stmt->fetchAll();
        
        // Buscar contas que vencem em X dias (configurado)
        $stmt = $conn->prepare("
            SELECT * FROM contas_pagar
            WHERE estabelecimento_id = ?
            AND status = 'pendente'
            AND data_vencimento = DATE_ADD(CURDATE(), INTERVAL ? DAY)
            AND notificacao_enviada = 0
        ");
        $stmt->execute([$estabelecimento['id'], $dias_antes]);
        $contas_xdias = $stmt->fetchAll();
        
        // Buscar contas vencidas (não notificadas)
        $stmt = $conn->prepare("
            SELECT * FROM contas_pagar
            WHERE estabelecimento_id = ?
            AND status = 'pendente'
            AND data_vencimento < CURDATE()
            AND notificacao_enviada = 0
        ");
        $stmt->execute([$estabelecimento['id']]);
        $contas_vencidas = $stmt->fetchAll();
        
        // Processar contas que vencem hoje
        if (!empty($contas_hoje)) {
            echo "  → " . count($contas_hoje) . " conta(s) vencendo HOJE\n";
            
            // --- Notificação Telegram ---
            if (!empty($estabelecimento['bot_token'])) {
                $mensagem = "🔔 <b>CONTAS VENCENDO HOJE</b>\n\n";
                $mensagem .= "📅 Data: " . date('d/m/Y') . "\n";
                $mensagem .= "🏪 Estabelecimento: {$estabelecimento['name']}\n\n";
                
                foreach ($contas_hoje as $conta) {
                    $mensagem .= "━━━━━━━━━━━━━━━━━━━━\n";
                    $mensagem .= "📋 <b>{$conta['descricao']}</b>\n";
                    $mensagem .= "🏷️ Tipo: {$conta['tipo']}\n";
                    $mensagem .= "💰 Valor: " . formatarValor($conta['valor']) . "\n";
                    $mensagem .= "📆 Vencimento: " . formatarData($conta['data_vencimento']) . "\n";
                    
                    if ($conta['codigo_barras']) {
                        $mensagem .= "📊 Código de Barras:\n<code>{$conta['codigo_barras']}</code>\n";
                    }
                    
                    if ($conta['link_pagamento']) {
                        $mensagem .= "🔗 Link: {$conta['link_pagamento']}\n";
                    }
                    
                    $mensagem .= "\n";
                }
                
                $mensagem .= "⚠️ <b>Atenção: Estas contas vencem HOJE!</b>";
                
                $telegram = new TelegramBot($conn);
                if ($telegram->sendMessage($estabelecimento['id'], $mensagem, 'vencimento_hoje')) {
                    echo "  ✓ Notificação Telegram enviada com sucesso\n";
                } else {
                    echo "  ✗ Erro ao enviar notificação Telegram. Verifique o log de erros.\n";
                }
            }
            
            // --- Notificação E-mail ---
            if ($estabelecimento['notificar_contas_pagar'] && !empty($estabelecimento['email_alerta'])) {
                $email_sender = new EmailSender($conn);
                $subject = "ALERTA: Contas a Pagar Vencendo HOJE - {$estabelecimento['name']}";
                $body = EmailSender::formatContasPagarBody($contas_hoje, $estabelecimento['name'], 0);
                
                if ($email_sender->sendAlert($estabelecimento['id'], $subject, $body, 'contas_pagar')) {
                    echo "  ✓ Notificação E-mail enviada com sucesso\n";
                } else {
                    echo "  ✗ Erro ao enviar notificação E-mail. Verifique o log de erros.\n";
                }
            }
            
            // Marcar contas como notificadas
            foreach ($contas_hoje as $conta) {
                $stmt = $conn->prepare("UPDATE contas_pagar SET notificacao_enviada = 1 WHERE id = ?");
                $stmt->execute([$conta['id']]);
            }
        }
        
        // Processar contas que vencem em X dias
        if (!empty($contas_xdias)) {
            echo "  → " . count($contas_xdias) . " conta(s) vencendo em {$dias_antes} DIA(S)\n";
            
            // --- Notificação Telegram ---
            if (!empty($estabelecimento['bot_token'])) {
                $mensagem = "⏰ <b>CONTAS A VENCER EM {$dias_antes} DIAS</b>\n\n";
                $mensagem .= "📅 Data: " . date('d/m/Y') . "\n";
                $mensagem .= "🏪 Estabelecimento: {$estabelecimento['name']}\n\n";
                
                foreach ($contas_xdias as $conta) {
                    $mensagem .= "━━━━━━━━━━━━━━━━━━━━\n";
                    $mensagem .= "📋 <b>{$conta['descricao']}</b>\n";
                    $mensagem .= "🏷️ Tipo: {$conta['tipo']}\n";
                    $mensagem .= "💰 Valor: " . formatarValor($conta['valor']) . "\n";
                    $mensagem .= "📆 Vencimento: " . formatarData($conta['data_vencimento']) . "\n";
                    
                    if ($conta['codigo_barras']) {
                        $mensagem .= "📊 Código de Barras:\n<code>{$conta['codigo_barras']}</code>\n";
                    }
                    
                    if ($conta['link_pagamento']) {
                        $mensagem .= "🔗 Link: {$conta['link_pagamento']}\n";
                    }
                    
                    $mensagem .= "\n";
                }
                
                $mensagem .= "📌 Lembrete: Organize-se para efetuar o pagamento!";
                
                $telegram = new TelegramBot($conn);
                if ($telegram->sendMessage($estabelecimento['id'], $mensagem, 'vencimento_proximo')) {
                    echo "  ✓ Notificação Telegram enviada com sucesso\n";
                } else {
                    echo "  ✗ Erro ao enviar notificação Telegram. Verifique o log de erros.\n";
                }
            }
            
            // --- Notificação E-mail ---
            if ($estabelecimento['notificar_contas_pagar'] && !empty($estabelecimento['email_alerta'])) {
                $email_sender = new EmailSender($conn);
                $subject = "Lembrete: Contas a Pagar Vencendo em {$dias_antes} Dia(s) - {$estabelecimento['name']}";
                $body = EmailSender::formatContasPagarBody($contas_xdias, $estabelecimento['name'], $dias_antes);
                
                if ($email_sender->sendAlert($estabelecimento['id'], $subject, $body, 'contas_pagar')) {
                    echo "  ✓ Notificação E-mail enviada com sucesso\n";
                } else {
                    echo "  ✗ Erro ao enviar notificação E-mail. Verifique o log de erros.\n";
                }
            }
            
            // Marcar contas como notificadas
            foreach ($contas_xdias as $conta) {
                $stmt = $conn->prepare("UPDATE contas_pagar SET notificacao_enviada = 1 WHERE id = ?");
                $stmt->execute([$conta['id']]);
            }
        }
        
        // Processar contas vencidas
        if (!empty($contas_vencidas)) {
            echo "  → " . count($contas_vencidas) . " conta(s) VENCIDAS\n";
            
            $total_vencido = 0;
            foreach ($contas_vencidas as $conta) {
                $total_vencido += $conta['valor'];
            }
            
            // --- Notificação Telegram ---
            if (!empty($estabelecimento['bot_token'])) {
                $mensagem = "🚨 <b>CONTAS VENCIDAS - ATENÇÃO!</b>\n\n";
                $mensagem .= "📅 Data: " . date('d/m/Y') . "\n";
                $mensagem .= "🏪 Estabelecimento: {$estabelecimento['name']}\n\n";
                
                foreach ($contas_vencidas as $conta) {
                    $dias_vencido = (strtotime(date('Y-m-d')) - strtotime($conta['data_vencimento'])) / 86400;
                    
                    $mensagem .= "━━━━━━━━━━━━━━━━━━━━\n";
                    $mensagem .= "📋 <b>{$conta['descricao']}</b>\n";
                    $mensagem .= "🏷️ Tipo: {$conta['tipo']}\n";
                    $mensagem .= "💰 Valor: " . formatarValor($conta['valor']) . "\n";
                    $mensagem .= "📆 Venceu em: " . formatarData($conta['data_vencimento']) . "\n";
                    $mensagem .= "⚠️ Vencida há: {$dias_vencido} dia(s)\n";
                    
                    if ($conta['codigo_barras']) {
                        $mensagem .= "📊 Código de Barras:\n<code>{$conta['codigo_barras']}</code>\n";
                    }
                    
                    if ($conta['link_pagamento']) {
                        $mensagem .= "🔗 Link: {$conta['link_pagamento']}\n";
                    }
                    
                    $mensagem .= "\n";
                }
                
                $mensagem .= "💸 <b>Total Vencido: " . formatarValor($total_vencido) . "</b>\n";
                $mensagem .= "🚨 <b>URGENTE: Regularize estas contas o quanto antes!</b>";
                
                $telegram = new TelegramBot($conn);
                if ($telegram->sendMessage($estabelecimento['id'], $mensagem, 'vencido')) {
                    echo "  ✓ Notificação Telegram enviada com sucesso\n";
                } else {
                    echo "  ✗ Erro ao enviar notificação Telegram. Verifique o log de erros.\n";
                }
            }
            
            // --- Notificação E-mail ---
            if ($estabelecimento['notificar_contas_pagar'] && !empty($estabelecimento['email_alerta'])) {
                $email_sender = new EmailSender($conn);
                $subject = "URGENTE: Contas a Pagar VENCIDAS - {$estabelecimento['name']}";
                $body = EmailSender::formatContasPagarBody($contas_vencidas, $estabelecimento['name'], -1);
                
                if ($email_sender->sendAlert($estabelecimento['id'], $subject, $body, 'contas_pagar')) {
                    echo "  ✓ Notificação E-mail enviada com sucesso\n";
                } else {
                    echo "  ✗ Erro ao enviar notificação E-mail. Verifique o log de erros.\n";
                }
            }
            
            // Marcar contas como notificadas e atualizar status
            foreach ($contas_vencidas as $conta) {
                $stmt = $conn->prepare("
                    UPDATE contas_pagar 
                    SET notificacao_enviada = 1, status = 'vencido'
                    WHERE id = ?
                ");
                $stmt->execute([$conta['id']]);
            }
        }
        
        if (empty($contas_hoje) && empty($contas_xdias) && empty($contas_vencidas)) {
            echo "  ✓ Nenhuma conta a notificar\n";
        }
    }
    
    echo "\n[" . date('Y-m-d H:i:s') . "] Verificação concluída com sucesso!\n";
    Logger::info("Verificação de contas a vencer concluída");
    
} catch (Exception $e) {
    echo "\n[ERRO] " . $e->getMessage() . "\n";
    Logger::error("Erro ao verificar contas a vencer", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit(1);
}

exit(0);
?>
