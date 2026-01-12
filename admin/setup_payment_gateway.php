<?php
/**
 * Setup Payment Gateway - Criar tabelas automaticamente
 * Este script cria as tabelas necessárias para integração Cora e Stripe
 */

require_once '../includes/config.php';

// Verificar se usuário é admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    die('Acesso negado. Apenas administradores podem executar este script.');
}

$erro = '';
$sucesso = '';

// Ler arquivo SQL
$sql_file = '../sql/payment_gateway_config.sql';

if (!file_exists($sql_file)) {
    $erro = "Arquivo SQL não encontrado: $sql_file";
} else {
    try {
        // Ler conteúdo do arquivo SQL
        $sql_content = file_get_contents($sql_file);
        
        // Dividir por ponto e vírgula para executar cada comando
        $queries = array_filter(array_map('trim', explode(';', $sql_content)));
        
        $tabelas_criadas = [];
        
        foreach ($queries as $query) {
            if (!empty($query)) {
                try {
                    // Executar query
                    $result = mysqli_query($conexao, $query);
                    
                    if (!$result) {
                        // Se erro 1050 (tabela já existe), continuar
                        if (strpos(mysqli_error($conexao), '1050') !== false) {
                            preg_match('/`([^`]+)`/', $query, $matches);
                            if (isset($matches[1])) {
                                $tabelas_criadas[] = $matches[1] . ' (já existia)';
                            }
                        } else {
                            throw new Exception(mysqli_error($conexao));
                        }
                    } else {
                        // Extrair nome da tabela
                        if (stripos($query, 'CREATE TABLE') !== false) {
                            preg_match('/CREATE TABLE[S]?\s+(?:IF NOT EXISTS\s+)?`?([^`\s]+)`?/i', $query, $matches);
                            if (isset($matches[1])) {
                                $tabelas_criadas[] = $matches[1];
                            }
                        }
                    }
                } catch (Exception $e) {
                    $erro .= "Erro ao executar query: " . $e->getMessage() . "\n";
                }
            }
        }
        
        if (empty($erro)) {
            $sucesso = "Tabelas criadas com sucesso: " . implode(', ', $tabelas_criadas);
        }
        
    } catch (Exception $e) {
        $erro = "Erro ao ler arquivo SQL: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Payment Gateway</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container-setup {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 40px;
            max-width: 600px;
            width: 100%;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #667eea;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .header p {
            color: #666;
            font-size: 14px;
        }
        .alert {
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .btn-setup {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            font-weight: bold;
            padding: 12px 30px;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-setup:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
            color: white;
            text-decoration: none;
        }
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .info-box h5 {
            color: #667eea;
            margin-bottom: 10px;
        }
        .info-box ul {
            margin: 0;
            padding-left: 20px;
        }
        .info-box li {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container-setup">
        <div class="header">
            <h1>⚙️ Setup Payment Gateway</h1>
            <p>Criar tabelas para integração Cora e Stripe</p>
        </div>

        <?php if (!empty($sucesso)): ?>
            <div class="alert alert-success" role="alert">
                <h4 class="alert-heading">✅ Sucesso!</h4>
                <p><?php echo htmlspecialchars($sucesso); ?></p>
                <hr>
                <p class="mb-0">As tabelas foram criadas com sucesso. Você pode agora:</p>
                <ul style="margin-top: 10px;">
                    <li>Configurar credenciais Cora em <code>cora_config_v2.php</code></li>
                    <li>Inserir credenciais Stripe no banco de dados</li>
                    <li>Agendar o CRON de polling automático</li>
                    <li>Acessar <a href="financeiro_faturamento.php">Faturamento</a> para visualizar boletos e faturas</li>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($erro)): ?>
            <div class="alert alert-danger" role="alert">
                <h4 class="alert-heading">❌ Erro!</h4>
                <p><?php echo htmlspecialchars($erro); ?></p>
                <hr>
                <p class="mb-0">Por favor, verifique o arquivo SQL e tente novamente.</p>
            </div>
        <?php endif; ?>

        <?php if (empty($sucesso) && empty($erro)): ?>
            <div class="info-box">
                <h5>📋 O que este script faz:</h5>
                <ul>
                    <li>Cria tabela <code>payment_gateway_config</code> - Armazena credenciais de gateways</li>
                    <li>Cria tabela <code>faturamentos</code> - Registro unificado de boletos e faturas</li>
                    <li>Cria tabela <code>faturamentos_historico</code> - Histórico de alterações</li>
                    <li>Cria índices para otimizar performance</li>
                </ul>
            </div>

            <form method="POST" action="">
                <button type="submit" class="btn btn-setup w-100">
                    🚀 Executar Setup Agora
                </button>
            </form>

            <div class="info-box" style="margin-top: 20px;">
                <h5>⚠️ Importante:</h5>
                <ul>
                    <li>Este script deve ser executado apenas uma vez</li>
                    <li>Se as tabelas já existem, elas não serão recriadas</li>
                    <li>Você precisa ser administrador para executar este script</li>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($sucesso)): ?>
            <a href="financeiro_faturamento.php" class="btn btn-setup w-100" style="margin-top: 20px;">
                ➜ Ir para Faturamento
            </a>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
