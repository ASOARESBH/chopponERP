<?php
/**
 * Configuração SMTP para Envio de E-mails
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';

// Apenas Admin Geral pode acessar
if (!isAdminGeral()) {
    header('Location: ../index.php');
    exit;
}

$conn = getDBConnection();
$mensagem = '';
$tipo_mensagem = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'salvar') {
        try {
            $host = trim($_POST['host']);
            $port = intval($_POST['port']);
            $username = trim($_POST['username']);
            $password = trim($_POST['password']);
            $from_email = trim($_POST['from_email']);
            $from_name = trim($_POST['from_name']);
            $encryption = $_POST['encryption'];
            
            // Validações
            if (empty($host) || empty($username) || empty($from_email) || empty($from_name)) {
                throw new Exception('Preencha todos os campos obrigatórios');
            }
            
            if (!filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('E-mail remetente inválido');
            }
            
            // Verificar se já existe configuração
            $stmt = $conn->query("SELECT id FROM smtp_config LIMIT 1");
            $config_existe = $stmt->fetch();
            
            if ($config_existe) {
                // Atualizar
                $sql = "UPDATE smtp_config SET 
                        host = ?, port = ?, username = ?, 
                        from_email = ?, from_name = ?, encryption = ?, ativo = TRUE";
                $params = [$host, $port, $username, $from_email, $from_name, $encryption];
                
                // Só atualizar senha se foi preenchida
                if (!empty($password)) {
                    $sql .= ", password = ?";
                    $params[] = base64_encode($password); // Criptografia básica
                }
                
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
            } else {
                // Inserir
                $stmt = $conn->prepare("
                    INSERT INTO smtp_config (host, port, username, password, from_email, from_name, encryption, ativo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, TRUE)
                ");
                $stmt->execute([
                    $host, $port, $username, 
                    base64_encode($password),
                    $from_email, $from_name, $encryption
                ]);
            }
            
            $mensagem = 'Configuração SMTP salva com sucesso!';
            $tipo_mensagem = 'success';
            
        } catch (Exception $e) {
            $mensagem = 'Erro: ' . $e->getMessage();
            $tipo_mensagem = 'danger';
        }
    } elseif ($action === 'testar') {
        try {
            require_once '../includes/EmailSender.php';
            
            $email_teste = trim($_POST['email_teste']);
            
            if (empty($email_teste) || !filter_var($email_teste, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('E-mail de teste inválido');
            }
            
            $emailSender = new EmailSender($conn);
            $resultado = $emailSender->enviarEmailTeste($email_teste);
            
            if ($resultado['success']) {
                $mensagem = 'E-mail de teste enviado com sucesso! Verifique a caixa de entrada.';
                $tipo_mensagem = 'success';
            } else {
                throw new Exception($resultado['message']);
            }
            
        } catch (Exception $e) {
            $mensagem = 'Erro ao enviar e-mail de teste: ' . $e->getMessage();
            $tipo_mensagem = 'danger';
        }
    }
}

// Buscar configuração atual
$stmt = $conn->query("SELECT * FROM smtp_config LIMIT 1");
$config = $stmt->fetch();
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-envelope-open-text"></i> Configuração SMTP</h2>
        <a href="integracoes.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
    </div>
    
    <?php if ($mensagem): ?>
    <div class="alert alert-<?= $tipo_mensagem ?> alert-dismissible fade show">
        <?= htmlspecialchars($mensagem) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Formulário de Configuração -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-cog"></i> Configurações do Servidor SMTP</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="salvar">
                        
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label required">Servidor SMTP (Host)</label>
                                <input type="text" name="host" class="form-control" required
                                       value="<?= htmlspecialchars($config['host'] ?? '') ?>"
                                       placeholder="smtp.gmail.com">
                                <small class="text-muted">Ex: smtp.gmail.com, smtp.office365.com, smtp.sendgrid.net</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label required">Porta</label>
                                <input type="number" name="port" class="form-control" required
                                       value="<?= $config['port'] ?? 587 ?>">
                                <small class="text-muted">587 (TLS) ou 465 (SSL)</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label required">Usuário SMTP</label>
                            <input type="text" name="username" class="form-control" required
                                   value="<?= htmlspecialchars($config['username'] ?? '') ?>"
                                   placeholder="seu-email@gmail.com">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label <?= $config ? '' : 'required' ?>">Senha SMTP</label>
                            <input type="password" name="password" class="form-control" 
                                   <?= $config ? '' : 'required' ?>
                                   placeholder="<?= $config ? '(deixe em branco para manter a atual)' : 'Digite a senha' ?>">
                            <?php if (!$config): ?>
                            <small class="text-muted">Para Gmail, use uma "Senha de App" gerada em: 
                                <a href="https://myaccount.google.com/apppasswords" target="_blank">Google App Passwords</a>
                            </small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label required">E-mail Remetente</label>
                            <input type="email" name="from_email" class="form-control" required
                                   value="<?= htmlspecialchars($config['from_email'] ?? '') ?>"
                                   placeholder="noreply@seudominio.com">
                            <small class="text-muted">E-mail que aparecerá como remetente</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label required">Nome do Remetente</label>
                            <input type="text" name="from_name" class="form-control" required
                                   value="<?= htmlspecialchars($config['from_name'] ?? 'Sistema Chopp ON') ?>"
                                   placeholder="Sistema Chopp ON">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label required">Tipo de Criptografia</label>
                            <select name="encryption" class="form-select" required>
                                <option value="tls" <?= ($config['encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>
                                    TLS (Recomendado - Porta 587)
                                </option>
                                <option value="ssl" <?= ($config['encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>
                                    SSL (Porta 465)
                                </option>
                                <option value="none" <?= ($config['encryption'] ?? '') === 'none' ? 'selected' : '' ?>>
                                    Nenhuma (Não recomendado)
                                </option>
                            </select>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> Salvar Configuração
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Painel Lateral -->
        <div class="col-md-4">
            <!-- Teste de E-mail -->
            <div class="card mb-3">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-paper-plane"></i> Testar Configuração</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="testar">
                        <div class="mb-3">
                            <label class="form-label">E-mail para Teste</label>
                            <input type="email" name="email_teste" class="form-control" required
                                   placeholder="seu-email@exemplo.com">
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-envelope"></i> Enviar E-mail Teste
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Status -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> Status</h5>
                </div>
                <div class="card-body">
                    <?php if ($config && $config['ativo']): ?>
                        <div class="alert alert-success mb-0">
                            <i class="fas fa-check-circle"></i> SMTP Configurado e Ativo
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning mb-0">
                            <i class="fas fa-exclamation-triangle"></i> SMTP Não Configurado
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Ajuda -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-question-circle"></i> Ajuda</h5>
                </div>
                <div class="card-body">
                    <h6>Provedores Comuns:</h6>
                    <ul class="small">
                        <li><strong>Gmail:</strong> smtp.gmail.com:587 (TLS)</li>
                        <li><strong>Outlook:</strong> smtp.office365.com:587 (TLS)</li>
                        <li><strong>SendGrid:</strong> smtp.sendgrid.net:587 (TLS)</li>
                        <li><strong>Mailgun:</strong> smtp.mailgun.org:587 (TLS)</li>
                    </ul>
                    
                    <h6 class="mt-3">Importante:</h6>
                    <ul class="small">
                        <li>Para Gmail, ative a verificação em 2 etapas e gere uma senha de app</li>
                        <li>Verifique se seu servidor permite conexões SMTP</li>
                        <li>Teste sempre após salvar as configurações</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
