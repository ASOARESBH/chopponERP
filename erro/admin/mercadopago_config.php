<?php
$page_title = 'Configuração Mercado Pago';
$current_page = 'mercadopago_config';

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/DebugLogger.php';

DebugLogger::info('=== INÍCIO mercadopago_config.php ===');
requireAdminGeral();

$conn = getDBConnection();
$success = '';
$error = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    DebugLogger::info('POST recebido', $_POST);
    $action = $_POST['action'] ?? 'save';
    DebugLogger::debug('Ação: ' . $action);
    
    if ($action === 'save') {
        $estabelecimento_id = $_POST['estabelecimento_id'];
        $access_token = sanitize($_POST['access_token']);
        $public_key = sanitize($_POST['public_key'] ?? '');
        $ambiente = sanitize($_POST['ambiente']);
        $webhook_url = sanitize($_POST['webhook_url'] ?? '');
        $webhook_secret = sanitize($_POST['webhook_secret'] ?? '');
        $status = isset($_POST['status']) ? 1 : 0;
        
        DebugLogger::info('Salvando configuração Mercado Pago', [
            'estabelecimento_id' => $estabelecimento_id,
            'ambiente' => $ambiente,
            'status' => $status
        ]);
        
        try {
            $query = "
                INSERT INTO mercadopago_config 
                (estabelecimento_id, access_token, public_key, ambiente, webhook_url, webhook_secret, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    access_token = VALUES(access_token),
                    public_key = VALUES(public_key),
                    ambiente = VALUES(ambiente),
                    webhook_url = VALUES(webhook_url),
                    webhook_secret = VALUES(webhook_secret),
                    status = VALUES(status)
            ";
            
            DebugLogger::sql($query, [
                $estabelecimento_id, 
                '***', // Não logar token completo
                '***', 
                $ambiente, 
                $webhook_url, 
                '***', 
                $status
            ]);
            
            $stmt = $conn->prepare("
                INSERT INTO mercadopago_config 
                (estabelecimento_id, access_token, public_key, ambiente, webhook_url, webhook_secret, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    access_token = VALUES(access_token),
                    public_key = VALUES(public_key),
                    ambiente = VALUES(ambiente),
                    webhook_url = VALUES(webhook_url),
                    webhook_secret = VALUES(webhook_secret),
                    status = VALUES(status)
            ");
            
            // IMPORTANTE: MySQLi usa bind_param, não execute com array
            $stmt->bind_param('isssssi', 
                $estabelecimento_id, 
                $access_token, 
                $public_key, 
                $ambiente, 
                $webhook_url, 
                $webhook_secret, 
                $status
            );
            
            if ($stmt->execute()) {
                $success = 'Configuração do Mercado Pago salva com sucesso!';
                DebugLogger::info('Configuração salva com sucesso');
            } else {
                $error = 'Erro ao salvar configuração: ' . $stmt->error;
                DebugLogger::error('Erro ao executar query', ['error' => $stmt->error]);
            }
        } catch (Exception $e) {
            $error = 'Erro no banco de dados: ' . $e->getMessage();
            DebugLogger::error('Exceção ao salvar', ['exception' => $e->getMessage()]);
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        DebugLogger::info('Excluindo configuração', ['id' => $id]);
        
        $stmt = $conn->prepare("DELETE FROM mercadopago_config WHERE id = ?");
        $stmt->bind_param('i', $id);
        
        if ($stmt->execute()) {
            $success = 'Configuração excluída com sucesso!';
            DebugLogger::info('Configuração excluída com sucesso');
        } else {
            $error = 'Erro ao excluir configuração: ' . $stmt->error;
            DebugLogger::error('Erro ao excluir', ['error' => $stmt->error]);
        }
    }
}

// Listar estabelecimentos
DebugLogger::debug('Listando estabelecimentos');
try {
    $result = $conn->query("SELECT * FROM estabelecimentos WHERE status = 1 ORDER BY name");
    if (!$result) {
        DebugLogger::error('Erro ao listar estabelecimentos', ['error' => $conn->error]);
        throw new Exception('Erro ao listar estabelecimentos: ' . $conn->error);
    }
    
    $estabelecimentos = [];
    while ($row = $result->fetch_assoc()) {
        $estabelecimentos[] = $row;
    }
    DebugLogger::info('Estabelecimentos carregados', ['count' => count($estabelecimentos)]);
} catch (Exception $e) {
    DebugLogger::error('Exceção ao listar estabelecimentos', ['exception' => $e->getMessage()]);
    $estabelecimentos = [];
}

// Listar configurações existentes
DebugLogger::debug('Listando configurações Mercado Pago');
try {
    $result = $conn->query("
        SELECT mp.*, e.name as estabelecimento_nome
        FROM mercadopago_config mp
        INNER JOIN estabelecimentos e ON mp.estabelecimento_id = e.id
        ORDER BY e.name
    ");
    
    if (!$result) {
        DebugLogger::error('Erro ao listar configurações', ['error' => $conn->error]);
        throw new Exception('Erro ao listar configurações: ' . $conn->error);
    }
    
    $configuracoes = [];
    while ($row = $result->fetch_assoc()) {
        $configuracoes[] = $row;
    }
    DebugLogger::info('Configurações carregadas', ['count' => count($configuracoes)]);
} catch (Exception $e) {
    DebugLogger::error('Exceção ao listar configurações', ['exception' => $e->getMessage()]);
    $configuracoes = [];
}

require_once '../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fab fa-cc-mastercard"></i> Configuração Mercado Pago</h1>
    <button class="btn btn-primary" onclick="abrirModal()">
        <i class="fas fa-plus"></i> Nova Configuração
    </button>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Informações Importantes -->
<div class="card mb-4">
    <div class="card-header bg-info text-white">
        <h3 class="card-title"><i class="fas fa-info-circle"></i> Informações Importantes</h3>
    </div>
    <div class="card-body">
        <p><strong>Para obter suas credenciais do Mercado Pago:</strong></p>
        <ol>
            <li>Acesse <a href="https://www.mercadopago.com.br/developers" target="_blank">https://www.mercadopago.com.br/developers</a></li>
            <li>Faça login na sua conta</li>
            <li>Vá em <strong>Suas integrações → Credenciais</strong></li>
            <li>Copie o <strong>Access Token</strong> (Produção ou Teste)</li>
            <li>Copie a <strong>Public Key</strong> (opcional)</li>
        </ol>
        
        <p class="mt-3"><strong>Ambiente:</strong></p>
        <ul>
            <li><strong>Sandbox (Teste):</strong> Use para testes, não cobra valores reais</li>
            <li><strong>Production (Produção):</strong> Use para cobranças reais</li>
        </ul>
        
        <p class="mt-3"><strong>Webhook URL:</strong></p>
        <p><code><?php echo SITE_URL; ?>/api/webhook_mercadopago.php</code></p>
        <p><small>Configure esta URL no painel do Mercado Pago para receber notificações de pagamento</small></p>
    </div>
</div>

<!-- Configurações Cadastradas -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Configurações Cadastradas</h3>
    </div>
    <div class="card-body">
        <?php if (empty($configuracoes)): ?>
            <p class="text-muted">Nenhuma configuração cadastrada ainda.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Estabelecimento</th>
                            <th>Access Token</th>
                            <th>Ambiente</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($configuracoes as $config): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($config['estabelecimento_nome']); ?></td>
                            <td>
                                <code><?php echo substr($config['access_token'], 0, 20) . '...'; ?></code>
                            </td>
                            <td>
                                <?php if ($config['ambiente'] === 'production'): ?>
                                    <span class="badge badge-success">Produção</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Sandbox</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($config['status']): ?>
                                    <span class="badge badge-success">Ativo</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="editarConfig(<?php echo htmlspecialchars(json_encode($config)); ?>)">
                                    <i class="fas fa-edit"></i> Editar
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="excluirConfig(<?php echo $config['id']; ?>, '<?php echo htmlspecialchars($config['estabelecimento_nome']); ?>')">
                                    <i class="fas fa-trash"></i> Excluir
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

<!-- Modal de Configuração -->
<div class="modal fade" id="modalConfig" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="save">
                <div class="modal-header">
                    <h5 class="modal-title">Configuração Mercado Pago</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="estabelecimento_id">Estabelecimento *</label>
                        <select name="estabelecimento_id" id="estabelecimento_id" class="form-control" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($estabelecimentos as $estab): ?>
                            <option value="<?php echo $estab['id']; ?>"><?php echo htmlspecialchars($estab['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="access_token">Access Token *</label>
                        <input type="text" name="access_token" id="access_token" class="form-control" required placeholder="APP_USR-...">
                        <small class="form-text text-muted">Token de acesso obtido no painel do Mercado Pago</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="public_key">Public Key (Opcional)</label>
                        <input type="text" name="public_key" id="public_key" class="form-control" placeholder="APP_USR-...">
                        <small class="form-text text-muted">Necessário apenas para integração com checkout transparente</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="ambiente">Ambiente *</label>
                        <select name="ambiente" id="ambiente" class="form-control" required>
                            <option value="sandbox">Sandbox (Teste)</option>
                            <option value="production">Production (Produção)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="webhook_url">Webhook URL</label>
                        <input type="url" name="webhook_url" id="webhook_url" class="form-control" value="<?php echo SITE_URL; ?>/api/webhook_mercadopago.php">
                        <small class="form-text text-muted">URL para receber notificações de pagamento</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="webhook_secret">Webhook Secret (Opcional)</label>
                        <input type="text" name="webhook_secret" id="webhook_secret" class="form-control" placeholder="Secret para validar webhook">
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="status" value="1" checked>
                            <span>Configuração Ativa</span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Configuração</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Form de Exclusão -->
<form id="formExcluir" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
</form>

<script>
function abrirModal() {
    $('#modalConfig').modal('show');
    document.querySelector('form').reset();
}

function editarConfig(config) {
    $('#estabelecimento_id').val(config.estabelecimento_id);
    $('#access_token').val(config.access_token);
    $('#public_key').val(config.public_key);
    $('#ambiente').val(config.ambiente);
    $('#webhook_url').val(config.webhook_url);
    $('#webhook_secret').val(config.webhook_secret);
    $('input[name="status"]').prop('checked', config.status == 1);
    
    $('#modalConfig').modal('show');
}

function excluirConfig(id, nome) {
    if (confirm(`Tem certeza que deseja excluir a configuração do estabelecimento "${nome}"?`)) {
        $('#delete_id').val(id);
        $('#formExcluir').submit();
    }
}
</script>

<?php 
DebugLogger::info('=== FIM mercadopago_config.php (página carregada com sucesso) ===');
require_once '../includes/footer.php'; 
?>
