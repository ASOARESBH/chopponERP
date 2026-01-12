<?php
$page_title = 'Configuração de E-mail';
$current_page = 'email_config';

require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdminGeral(); // Apenas Admin Geral pode configurar

$conn = getDBConnection();
$success = '';
$error = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $estabelecimento_id = $_POST['estabelecimento_id'];
    $email_alerta = sanitize($_POST['email_alerta']);
    $notificar_vendas = isset($_POST['notificar_vendas']) ? 1 : 0;
    $notificar_volume_critico = isset($_POST['notificar_volume_critico']) ? 1 : 0;
    $notificar_contas_pagar = isset($_POST['notificar_contas_pagar']) ? 1 : 0;
    $dias_antes_contas_pagar = (int)$_POST['dias_antes_contas_pagar'];
    $status = isset($_POST['status']) ? 1 : 0;
    
    // 1. Atualizar email_alerta na tabela estabelecimentos
    $stmt = $conn->prepare("UPDATE estabelecimentos SET email_alerta = ? WHERE id = ?");
    $stmt->execute([$email_alerta, $estabelecimento_id]);
    
    // 2. Inserir ou atualizar na tabela email_config
    $stmt = $conn->prepare("
        INSERT INTO email_config (estabelecimento_id, email_alerta, notificar_vendas, notificar_volume_critico, notificar_contas_pagar, dias_antes_contas_pagar, status)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            email_alerta = VALUES(email_alerta),
            notificar_vendas = VALUES(notificar_vendas),
            notificar_volume_critico = VALUES(notificar_volume_critico),
            notificar_contas_pagar = VALUES(notificar_contas_pagar),
            dias_antes_contas_pagar = VALUES(dias_antes_contas_pagar),
            status = VALUES(status)
    ");
    
    // O campo email_alerta na email_config é redundante, mas mantido para consistência.
    if ($stmt->execute([$estabelecimento_id, $email_alerta, $notificar_vendas, $notificar_volume_critico, $notificar_contas_pagar, $dias_antes_contas_pagar, $status])) {
        $success = 'Configuração de e-mail salva com sucesso!';
    } else {
        $error = 'Erro ao salvar a configuração de e-mail.';
    }
}

// Listar estabelecimentos
$stmt = $conn->query("SELECT * FROM estabelecimentos WHERE status = 1 ORDER BY name");
$estabelecimentos = $stmt->fetchAll();

// Obter configuração do primeiro estabelecimento por padrão
$config = null;
$estabelecimento_selecionado = $estabelecimentos[0]['id'] ?? null;

if (isset($_GET['estabelecimento_id'])) {
    $estabelecimento_selecionado = (int)$_GET['estabelecimento_id'];
}

if ($estabelecimento_selecionado) {
    $stmt = $conn->prepare("
        SELECT ec.*, e.email_alerta as email_alerta_estab
        FROM estabelecimentos e
        LEFT JOIN email_config ec ON e.id = ec.estabelecimento_id
        WHERE e.id = ?
    ");
    $stmt->execute([$estabelecimento_selecionado]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Se não houver configuração na email_config, usar o email_alerta do estabelecimento
    if (!$config['id']) {
        $config['email_alerta'] = $config['email_alerta_estab'];
        $config['notificar_vendas'] = 0;
        $config['notificar_volume_critico'] = 0;
        $config['notificar_contas_pagar'] = 0;
        $config['dias_antes_contas_pagar'] = 3;
        $config['status'] = 1;
    }
}

require_once '../includes/header.php';
?>

<div class="page-header">
    <h1>Configuração de Alertas por E-mail</h1>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Selecione o Estabelecimento</h3>
    </div>
    <div class="card-body">
        <form method="GET" class="form-inline">
            <div class="form-group">
                <label for="estabelecimento_id" class="mr-2">Estabelecimento:</label>
                <select name="estabelecimento_id" id="estabelecimento_id" class="form-control" onchange="this.form.submit()">
                    <?php foreach ($estabelecimentos as $estab): ?>
                    <option value="<?php echo $estab['id']; ?>" <?php echo $estab['id'] == $estabelecimento_selecionado ? 'selected' : ''; ?>>
                        <?php echo $estab['name']; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if ($config): ?>
<div class="card mt-4">
    <div class="card-header">
        <h3 class="card-title">Configurações para <?php echo $config['name'] ?? 'Estabelecimento'; ?></h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="estabelecimento_id" value="<?php echo $estabelecimento_selecionado; ?>">
            
            <div class="form-group">
                <label for="email_alerta">E-mail para Alertas *</label>
                <input type="email" name="email_alerta" id="email_alerta" class="form-control" required value="<?php echo htmlspecialchars($config['email_alerta'] ?? $config['email_alerta_estab'] ?? ''); ?>">
                <small class="form-text text-muted">Este e-mail receberá todos os alertas configurados abaixo.</small>
            </div>
            
            <hr>
            
            <h4>Tipos de Alerta</h4>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="notificar_vendas" value="1" <?php echo $config['notificar_vendas'] ? 'checked' : ''; ?>>
                    <span>Notificar Novas Vendas por E-mail</span>
                </label>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="notificar_volume_critico" value="1" <?php echo $config['notificar_volume_critico'] ? 'checked' : ''; ?>>
                    <span>Notificar Volume Crítico de Chopp por E-mail</span>
                </label>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="notificar_contas_pagar" id="notificar_contas_pagar" value="1" <?php echo $config['notificar_contas_pagar'] ? 'checked' : ''; ?>>
                    <span>Notificar Contas a Pagar por E-mail</span>
                </label>
            </div>
            
            <div class="form-group" id="dias_antes_group">
                <label for="dias_antes_contas_pagar">Alertar Contas a Pagar com quantos dias de antecedência?</label>
                <input type="number" name="dias_antes_contas_pagar" id="dias_antes_contas_pagar" class="form-control" min="1" max="30" value="<?php echo $config['dias_antes_contas_pagar'] ?? 3; ?>">
                <small class="form-text text-muted">O alerta será enviado no dia, no dia do vencimento e após o vencimento.</small>
            </div>
            
            <hr>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="status" value="1" <?php echo $config['status'] ? 'checked' : ''; ?>>
                    <span>Configuração de Alerta Ativa</span>
                </label>
            </div>
            
            <button type="submit" class="btn btn-primary">Salvar Configuração</button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php
require_once '../includes/footer.php';
?>
