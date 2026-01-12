<?php
/**
 * Script de Instalação do Sistema de Permissões
 * Chopp On Tap - v3.1
 * 
 * IMPORTANTE: Execute este script apenas UMA VEZ após fazer backup do banco de dados
 */

// Configurações
define('INSTALL_PASSWORD', 'ChoppOnTap2025!'); // Altere esta senha antes de executar

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalação do Sistema de Permissões</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 800px;
            width: 100%;
            padding: 40px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
        }
        
        .alert-success {
            background-color: #d4edda;
            border: 1px solid #28a745;
            color: #155724;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            border: 1px solid #dc3545;
            color: #721c24;
        }
        
        .alert-info {
            background-color: #d1ecf1;
            border: 1px solid #17a2b8;
            color: #0c5460;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background-color: #0066CC;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #0052A3;
        }
        
        .steps {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .steps ol {
            margin-left: 20px;
        }
        
        .steps li {
            margin-bottom: 10px;
            line-height: 1.6;
        }
        
        .log {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            max-height: 400px;
            overflow-y: auto;
            margin-top: 20px;
        }
        
        .log-item {
            padding: 5px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .log-success { color: #28a745; }
        .log-error { color: #dc3545; }
        .log-info { color: #17a2b8; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Sistema de Permissões</h1>
        <p class="subtitle">Instalação do Sistema de Controle de Acesso por Página</p>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
            $password = $_POST['password'] ?? '';
            
            if ($password !== INSTALL_PASSWORD) {
                echo '<div class="alert alert-danger">❌ Senha incorreta! Verifique a senha definida no arquivo.</div>';
            } else {
                echo '<div class="alert alert-info">⏳ Iniciando instalação...</div>';
                echo '<div class="log">';
                
                try {
                    require_once 'includes/config.php';
                    $conn = getDBConnection();
                    
                    // Ler arquivo SQL
                    $sql = file_get_contents('install_permissions.sql');
                    
                    if (!$sql) {
                        throw new Exception('Erro ao ler arquivo install_permissions.sql');
                    }
                    
                    echo '<div class="log-item log-info">📄 Arquivo SQL carregado com sucesso</div>';
                    
                    // Executar SQL
                    $conn->exec($sql);
                    
                    echo '<div class="log-item log-success">✅ Tabelas criadas com sucesso</div>';
                    echo '<div class="log-item log-success">✅ Páginas do sistema cadastradas</div>';
                    echo '<div class="log-item log-success">✅ Permissões padrão criadas</div>';
                    
                    // Verificar se as tabelas foram criadas
                    $tables = $conn->query("SHOW TABLES LIKE 'system_pages'")->fetchAll();
                    if (count($tables) > 0) {
                        $count_pages = $conn->query("SELECT COUNT(*) FROM system_pages")->fetchColumn();
                        echo '<div class="log-item log-info">📊 Total de páginas cadastradas: ' . $count_pages . '</div>';
                    }
                    
                    $tables = $conn->query("SHOW TABLES LIKE 'user_permissions'")->fetchAll();
                    if (count($tables) > 0) {
                        $count_perms = $conn->query("SELECT COUNT(*) FROM user_permissions")->fetchColumn();
                        echo '<div class="log-item log-info">📊 Total de permissões criadas: ' . $count_perms . '</div>';
                    }
                    
                    echo '</div>';
                    echo '<div class="alert alert-success" style="margin-top: 20px;">';
                    echo '<h3>✅ Instalação Concluída com Sucesso!</h3>';
                    echo '<p><strong>Próximos passos:</strong></p>';
                    echo '<ol>';
                    echo '<li>Acesse o sistema e faça login como Administrador Geral</li>';
                    echo '<li>Vá em "Permissões" no menu lateral</li>';
                    echo '<li>Configure as permissões de cada usuário conforme necessário</li>';
                    echo '<li><strong>IMPORTANTE:</strong> Delete ou renomeie este arquivo (install_permissions.php) por segurança</li>';
                    echo '</ol>';
                    echo '<p style="margin-top: 15px;"><a href="admin/dashboard.php" class="btn btn-primary">Ir para o Dashboard</a></p>';
                    echo '</div>';
                    
                } catch (Exception $e) {
                    echo '<div class="log-item log-error">❌ ERRO: ' . $e->getMessage() . '</div>';
                    echo '</div>';
                    echo '<div class="alert alert-danger" style="margin-top: 20px;">';
                    echo '<h3>❌ Erro na Instalação</h3>';
                    echo '<p>' . $e->getMessage() . '</p>';
                    echo '<p><strong>Verifique:</strong></p>';
                    echo '<ul>';
                    echo '<li>Se o arquivo install_permissions.sql existe no mesmo diretório</li>';
                    echo '<li>Se as configurações do banco de dados estão corretas</li>';
                    echo '<li>Se o usuário do banco tem permissões para criar tabelas</li>';
                    echo '</ul>';
                    echo '</div>';
                }
            }
        } else {
        ?>
        
        <div class="alert alert-warning">
            <strong>⚠️ ATENÇÃO:</strong> Este script irá modificar o banco de dados. Certifique-se de ter feito backup antes de continuar.
        </div>
        
        <div class="steps">
            <h3>📋 O que este script faz:</h3>
            <ol>
                <li>Cria a tabela <code>system_pages</code> com todas as páginas do sistema</li>
                <li>Cria a tabela <code>user_permissions</code> para controlar permissões</li>
                <li>Cadastra todas as páginas do sistema (Dashboard, Bebidas, TAPs, etc.)</li>
                <li>Cria permissões padrão para todos os usuários existentes baseado no tipo</li>
                <li>Define páginas exclusivas do Admin (Logs, E-mail, Telegram)</li>
            </ol>
        </div>
        
        <div class="alert alert-info">
            <strong>ℹ️ Permissões Padrão:</strong>
            <ul style="margin-top: 10px; margin-left: 20px;">
                <li><strong>Admin Geral:</strong> Acesso total a todas as páginas</li>
                <li><strong>Gerente:</strong> Acesso a páginas operacionais e financeiras (sem excluir)</li>
                <li><strong>Operador:</strong> Acesso a páginas operacionais (sem criar/excluir)</li>
                <li><strong>Visualizador:</strong> Apenas visualização de páginas básicas</li>
            </ul>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label for="password">Senha de Instalação:</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       placeholder="Digite a senha definida no arquivo" 
                       required>
                <small style="color: #666; display: block; margin-top: 5px;">
                    A senha está definida na constante INSTALL_PASSWORD no início deste arquivo
                </small>
            </div>
            
            <button type="submit" name="install" class="btn btn-primary">
                🚀 Iniciar Instalação
            </button>
        </form>
        
        <?php } ?>
    </div>
</body>
</html>
