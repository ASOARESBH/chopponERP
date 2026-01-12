<?php
/**
 * Script de Instalação do Módulo Financeiro
 * Chopp On Tap - v1.0.0
 * 
 * Este script instala automaticamente o módulo financeiro no sistema
 */

// Configurações
$DB_HOST = 'localhost';
$DB_NAME = 'inlaud99_choppontap';
$DB_USER = 'inlaud99_admin';
$DB_PASS = 'Admin259087@';

// Verificar se está sendo executado via navegador
$is_cli = php_sapi_name() === 'cli';

if (!$is_cli) {
    echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Instalação Módulo Financeiro - Chopp On Tap</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #0066CC;
            padding-bottom: 10px;
        }
        .step {
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border-left: 4px solid #0066CC;
        }
        .success {
            color: #28a745;
            font-weight: bold;
        }
        .error {
            color: #dc3545;
            font-weight: bold;
        }
        .warning {
            color: #ffc107;
            font-weight: bold;
        }
        .info {
            color: #17a2b8;
            font-weight: bold;
        }
        pre {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #0066CC;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #0052A3;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🚀 Instalação do Módulo Financeiro</h1>
        <p><strong>Sistema:</strong> Chopp On Tap v3.0+</p>
        <p><strong>Módulo:</strong> Financeiro v1.0.0</p>
        <hr>";
}

function log_message($message, $type = 'info') {
    global $is_cli;
    
    $colors = [
        'success' => "\033[0;32m",
        'error' => "\033[0;31m",
        'warning' => "\033[0;33m",
        'info' => "\033[0;36m",
        'reset' => "\033[0m"
    ];
    
    if ($is_cli) {
        echo $colors[$type] . $message . $colors['reset'] . "\n";
    } else {
        echo "<div class='step'><span class='$type'>$message</span></div>";
        flush();
        ob_flush();
    }
}

try {
    log_message("Iniciando instalação do Módulo Financeiro...", 'info');
    
    // Conectar ao banco de dados
    log_message("Conectando ao banco de dados...", 'info');
    $conn = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    log_message("✓ Conexão estabelecida com sucesso!", 'success');
    
    // Ler arquivo SQL
    log_message("Lendo arquivo de instalação SQL...", 'info');
    $sql_file = __DIR__ . '/database_financeiro.sql';
    
    if (!file_exists($sql_file)) {
        throw new Exception("Arquivo database_financeiro.sql não encontrado!");
    }
    
    $sql_content = file_get_contents($sql_file);
    log_message("✓ Arquivo SQL carregado!", 'success');
    
    // Executar SQL
    log_message("Criando tabelas no banco de dados...", 'info');
    
    // Dividir em comandos individuais
    $statements = array_filter(
        array_map('trim', explode(';', $sql_content)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt);
        }
    );
    
    $executed = 0;
    foreach ($statements as $statement) {
        if (!empty(trim($statement))) {
            try {
                $conn->exec($statement);
                $executed++;
            } catch (PDOException $e) {
                // Ignorar erros de "já existe" ou "campo duplicado"
                if (strpos($e->getMessage(), 'already exists') === false && 
                    strpos($e->getMessage(), 'Duplicate') === false) {
                    log_message("⚠ Aviso: " . $e->getMessage(), 'warning');
                }
            }
        }
    }
    
    log_message("✓ $executed comandos SQL executados com sucesso!", 'success');
    
    // Verificar tabelas criadas
    log_message("Verificando tabelas criadas...", 'info');
    
    $tables = ['formas_pagamento', 'contas_pagar', 'historico_notificacoes_contas'];
    foreach ($tables as $table) {
        $stmt = $conn->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            log_message("✓ Tabela '$table' criada com sucesso!", 'success');
        } else {
            log_message("✗ Tabela '$table' não foi criada!", 'error');
        }
    }
    
    // Verificar alterações na tabela order
    log_message("Verificando alterações na tabela 'order'...", 'info');
    $stmt = $conn->query("SHOW COLUMNS FROM `order` LIKE 'forma_pagamento_id'");
    if ($stmt->rowCount() > 0) {
        log_message("✓ Campo 'forma_pagamento_id' adicionado à tabela 'order'!", 'success');
    } else {
        log_message("⚠ Campo 'forma_pagamento_id' não foi adicionado", 'warning');
    }
    
    // Verificar arquivos PHP
    log_message("Verificando arquivos PHP criados...", 'info');
    
    $files = [
        'admin/financeiro_taxas.php' => 'Página de Taxas de Juros',
        'admin/financeiro_contas.php' => 'Página de Contas a Pagar',
        'cron/notificar_contas_vencer.php' => 'Script CRON de Notificações'
    ];
    
    foreach ($files as $file => $description) {
        $filepath = __DIR__ . '/' . $file;
        if (file_exists($filepath)) {
            log_message("✓ $description encontrado!", 'success');
        } else {
            log_message("✗ $description não encontrado: $file", 'error');
        }
    }
    
    // Verificar permissões do script CRON
    log_message("Verificando permissões do script CRON...", 'info');
    $cron_file = __DIR__ . '/cron/notificar_contas_vencer.php';
    if (file_exists($cron_file)) {
        chmod($cron_file, 0755);
        log_message("✓ Permissões do script CRON configuradas!", 'success');
    }
    
    // Resumo final
    log_message("\n" . str_repeat("=", 60), 'info');
    log_message("INSTALAÇÃO CONCLUÍDA COM SUCESSO!", 'success');
    log_message(str_repeat("=", 60) . "\n", 'info');
    
    log_message("Próximos passos:", 'info');
    log_message("1. Configure o CRON job para notificações automáticas", 'warning');
    log_message("   Comando: 0 8 * * * /usr/bin/php " . __DIR__ . "/cron/notificar_contas_vencer.php", 'info');
    log_message("2. Acesse o sistema e vá em 'Financeiro → Taxas de Juros'", 'warning');
    log_message("3. Cadastre as formas de pagamento e suas taxas", 'warning');
    log_message("4. Acesse 'Financeiro → Contas a Pagar' para gerenciar contas", 'warning');
    log_message("5. Configure o Telegram em 'Admin → Telegram' para receber notificações", 'warning');
    
    if (!$is_cli) {
        echo "<hr>
        <h2>✅ Instalação Concluída!</h2>
        <p>O módulo financeiro foi instalado com sucesso no seu sistema.</p>
        
        <h3>📋 Próximos Passos:</h3>
        <ol>
            <li><strong>Configure o CRON job</strong> para notificações automáticas:
                <pre>0 8 * * * /usr/bin/php " . __DIR__ . "/cron/notificar_contas_vencer.php</pre>
            </li>
            <li><strong>Acesse o sistema</strong> e vá em <code>Financeiro → Taxas de Juros</code></li>
            <li><strong>Cadastre</strong> as formas de pagamento e suas taxas</li>
            <li><strong>Gerencie</strong> suas contas em <code>Financeiro → Contas a Pagar</code></li>
            <li><strong>Configure o Telegram</strong> em <code>Admin → Telegram</code> para receber notificações</li>
        </ol>
        
        <a href='admin/financeiro_taxas.php' class='btn'>Acessar Taxas de Juros</a>
        <a href='admin/financeiro_contas.php' class='btn'>Acessar Contas a Pagar</a>
        
        <hr>
        <p><small>Para mais informações, consulte o arquivo <code>INSTALACAO_MODULO_FINANCEIRO.md</code></small></p>
    </div>
</body>
</html>";
    }
    
} catch (Exception $e) {
    log_message("ERRO: " . $e->getMessage(), 'error');
    
    if (!$is_cli) {
        echo "<div class='step'><span class='error'>❌ Erro durante a instalação: " . htmlspecialchars($e->getMessage()) . "</span></div>
        <p>Por favor, verifique as configurações do banco de dados e tente novamente.</p>
    </div>
</body>
</html>";
    }
    
    exit(1);
}

exit(0);
?>
