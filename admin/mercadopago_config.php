<?php
// Ativar exibição de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não mostrar na tela
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

$page_title = 'Configuração Mercado Pago';
$current_page = 'mercadopago_config';

try {
    require_once '../includes/config.php';
    require_once '../includes/auth.php';
    requireAdminGeral();
} catch (Exception $e) {
    die('Erro ao carregar sistema: ' . $e->getMessage());
}

$conn = getDBConnection();
$success = '';
$error = '';

// Verificar se tabela existe (PDO)
try {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'mercadopago_config'");
    $tableExists = $tableCheck && $tableCheck->rowCount() > 0;
    
    if (!$tableExists) {
        $error = 'ERRO: Tabela mercadopago_config não existe. Execute o SQL: /sql/add_mercadopago_integration.sql';
    }
} catch (Exception $e) {
    $error = 'Erro ao verificar tabela: ' . $e->getMessage();
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    $action = $_POST['action'] ?? 'save';
    
    if ($action === 'save') {
        $estabelecimento_id = (int)$_POST['estabelecimento_id'];
        $access_token = sanitize($_POST['access_token']);
        $public_key = sanitize($_POST['public_key'] ?? '');
        $ambiente = sanitize($_POST['ambiente']);
        $webhook_url = sanitize($_POST['webhook_url'] ?? '');
        $webhook_secret = sanitize($_POST['webhook_secret'] ?? '');
        $status = isset($_POST['status']) ? 1 : 0;
        
        try {
            $stmt = $conn->prepare("
                INSERT INTO mercadopago_config 
                (estabelecimento_id, access_token, public_key, ambiente, webhook_url, webhook_secret, status)
                VALUES (:estabelecimento_id, :access_token, :public_key, :ambiente, :webhook_url, :webhook_secret, :status)
                ON DUPLICATE KEY UPDATE
                    access_token = VALUES(access_token),
                    public_key = VALUES(public_key),
                    ambiente = VALUES(ambiente),
                    webhook_url = VALUES(webhook_url),
                    webhook_secret = VALUES(webhook_secret),
                    status = VALUES(status)
            ");
            
            $stmt->execute([
                ':estabelecimento_id' => $estabelecimento_id,
                ':access_token' => $access_token,
                ':public_key' => $public_key,
                ':ambiente' => $ambiente,
                ':webhook_url' => $webhook_url,
                ':webhook_secret' => $webhook_secret,
                ':status' => $status
            ]);
            
            $success = 'Configuração do Mercado Pago salva com sucesso!';
        } catch (Exception $e) {
            $error = 'Erro ao salvar configuração: ' . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        
        try {
            $stmt = $conn->prepare("DELETE FROM mercadopago_config WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $success = 'Configuração excluída com sucesso!';
        } catch (Exception $e) {
            $error = 'Erro ao excluir configuração: ' . $e->getMessage();
        }
    }
}

// Listar estabelecimentos
$estabelecimentos = [];
try {
    $result = $conn->query("SELECT id, name FROM estabelecimentos WHERE status = 1 ORDER BY name");
    if ($result) {
        $estabelecimentos = $result->fetchAll();
    }
} catch (Exception $e) {
    $estabelecimentos = []; // Garantir que sempre seja array
}

// Listar configurações existentes
$configuracoes = [];
if ($tableExists) {
    try {
        $result = $conn->query("
            SELECT mp.*, e.name as estabelecimento_nome
            FROM mercadopago_config mp
            INNER JOIN estabelecimentos e ON mp.estabelecimento_id = e.id
            ORDER BY e.name
        ");
        if ($result) {
            $configuracoes = $result->fetchAll();
        }
    } catch (Exception $e) {
        // Silencioso
    }





}

require_once '../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fab fa-cc-mastercard"></i> Configuração Mercado Pago</h1>
    <button class="btn btn-primary" onclick="openModalMercadoPago()">
        <i class="fas fa-plus"></i> Nova Configuração
    </button>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Configurações do Mercado Pago</h3>
    </div>
    <div class="card-body">
        <?php if (empty($configuracoes)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Nenhuma configuração cadastrada. Clique em "Nova Configuração" para adicionar.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Estabelecimento</th>
                            <th>Ambiente</th>
                            <th>Access Token</th>
                            <th>Webhook URL</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($configuracoes as $config): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($config['estabelecimento_nome']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $config['ambiente'] === 'production' ? 'success' : 'warning'; ?>">
                                    <?php echo $config['ambiente'] === 'production' ? 'Produção' : 'Sandbox'; ?>
                                </span>
                            </td>
                            <td>
                                <code><?php echo substr($config['access_token'], 0, 20); ?>...</code>
                            </td>
                            <td>
                                <?php if ($config['webhook_url']): ?>
                                    <small><?php echo htmlspecialchars($config['webhook_url']); ?></small>
                                <?php else: ?>
                                    <span class="text-muted">Não configurado</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $config['status'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $config['status'] ? 'Ativo' : 'Inativo'; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick='editarMercadoPago(<?php echo json_encode($config); ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="excluirMercadoPago(<?php echo $config['id']; ?>, '<?php echo htmlspecialchars($config['estabelecimento_nome']); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de Configuração (Customizado) -->
<div id="mercadoPagoModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Nova Configuração Mercado Pago</h2>
            <span class="close" onclick="closeModalMercadoPago()">&times;</span>
        </div>
        <form method="POST" id="mercadoPagoForm">
            <input type="hidden" name="action" value="save">
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="estabelecimento_id">Estabelecimento *</label>
                    <select name="estabelecimento_id" id="estabelecimento_id" class="form-control" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($estabelecimentos as $est): ?>
                            <option value="<?php echo $est['id']; ?>"><?php echo htmlspecialchars($est['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="access_token">Access Token *</label>
                    <input type="text" name="access_token" id="access_token" class="form-control" required>
                    <small class="form-text">Token de acesso fornecido pelo Mercado Pago</small>
                </div>
                
                <div class="form-group">
                    <label for="public_key">Public Key</label>
                    <input type="text" name="public_key" id="public_key" class="form-control">
                    <small class="form-text">Chave pública (opcional)</small>
                </div>
                
                <div class="form-group">
                    <label for="ambiente">Ambiente *</label>
                    <select name="ambiente" id="ambiente" class="form-control" required>
                        <option value="sandbox">Sandbox (Teste)</option>
                        <option value="production">Produção</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="webhook_url">Webhook URL</label>
                    <input type="url" name="webhook_url" id="webhook_url" class="form-control">
                    <small class="form-text">URL para receber notificações de pagamento</small>
                </div>
                
                <div class="form-group">
                    <label for="webhook_secret">Webhook Secret</label>
                    <input type="text" name="webhook_secret" id="webhook_secret" class="form-control">
                    <small class="form-text">Secret para validar webhooks</small>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="status" id="status" value="1" checked>
                        Ativo
                    </label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModalMercadoPago()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Form de exclusão -->
<form id="formExcluir" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
</form>

<script>
function openModalMercadoPago() {
    document.getElementById('modalTitle').textContent = 'Nova Configuração Mercado Pago';
    document.getElementById('mercadoPagoForm').reset();
    document.getElementById('status').checked = true;
    openModal('mercadoPagoModal');
}

function closeModalMercadoPago() {
    closeModal('mercadoPagoModal');
}

function editarMercadoPago(config) {
    document.getElementById('modalTitle').textContent = 'Editar Configuração Mercado Pago';
    document.getElementById('estabelecimento_id').value = config.estabelecimento_id;
    document.getElementById('access_token').value = config.access_token;
    document.getElementById('public_key').value = config.public_key;
    document.getElementById('ambiente').value = config.ambiente;
    document.getElementById('webhook_url').value = config.webhook_url;
    document.getElementById('webhook_secret').value = config.webhook_secret;
    document.getElementById('status').checked = config.status == 1;
    openModal('mercadoPagoModal');
}

function excluirMercadoPago(id, nome) {
    if (confirm(`Tem certeza que deseja excluir a configuração do estabelecimento "${nome}"?`)) {
        document.getElementById('delete_id').value = id;
        document.getElementById('formExcluir').submit();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
