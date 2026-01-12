<?php
$page_title = 'Configuração Stripe';
$current_page = 'stripe_config';

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';

// Apenas Admin Geral pode acessar
if (!isAdminGeral()) {
    header('Location: ../index.php');
    exit;
}

$conn = getDBConnection();
$success = '';
$error = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $estabelecimento_id = intval($_POST['estabelecimento_id']);
        $stripe_public_key = sanitize($_POST['stripe_public_key']);
        $stripe_secret_key = sanitize($_POST['stripe_secret_key']);
        $stripe_webhook_secret = sanitize($_POST['stripe_webhook_secret']);
        $modo = sanitize($_POST['modo']);
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        
        try {
            // Verificar se já existe configuração
            $stmt = $conn->prepare("SELECT id FROM stripe_config WHERE estabelecimento_id = ?");
            $stmt->execute([$estabelecimento_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Atualizar
                $stmt = $conn->prepare("
                    UPDATE stripe_config 
                    SET stripe_public_key = ?, stripe_secret_key = ?, stripe_webhook_secret = ?, modo = ?, ativo = ?
                    WHERE estabelecimento_id = ?
                ");
                $stmt->execute([$stripe_public_key, $stripe_secret_key, $stripe_webhook_secret, $modo, $ativo, $estabelecimento_id]);
                $success = 'Configuração do Stripe atualizada com sucesso!';
            } else {
                // Inserir
                $stmt = $conn->prepare("
                    INSERT INTO stripe_config (estabelecimento_id, stripe_public_key, stripe_secret_key, stripe_webhook_secret, modo, ativo)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$estabelecimento_id, $stripe_public_key, $stripe_secret_key, $stripe_webhook_secret, $modo, $ativo]);
                $success = 'Configuração do Stripe cadastrada com sucesso!';
            }
            
            $_SESSION['success'] = $success;
            header('Location: stripe_config.php');
            exit;
            
        } catch (Exception $e) {
            $error = 'Erro ao salvar configuração: ' . $e->getMessage();
        }
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        try {
            $stmt = $conn->prepare("DELETE FROM stripe_config WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = 'Configuração removida com sucesso!';
            header('Location: stripe_config.php');
            exit;
        } catch (Exception $e) {
            $error = 'Erro ao remover configuração: ' . $e->getMessage();
        }
    }
}

// Buscar estabelecimentos
$estabelecimentos = [];
$stmt = $conn->query("SELECT id, name FROM estabelecimentos ORDER BY name");
$estabelecimentos = $stmt->fetchAll();

// Buscar configurações
$configs = [];
$stmt = $conn->query("
    SELECT sc.*, e.name as estabelecimento_nome
    FROM stripe_config sc
    INNER JOIN estabelecimentos e ON sc.estabelecimento_id = e.id
    ORDER BY e.name
");
$configs = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="page-header">
    <h1>Configuração Stripe Pagamentos</h1>
    <button class="btn btn-primary" onclick="openModalStripe()">
        <span>➕</span> Nova Configuração
    </button>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
        <?php 
        echo $_SESSION['success']; 
        unset($_SESSION['success']);
        ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<div class="info-box">
    <h3>📋 Informações Importantes</h3>
    <p><strong>Para obter suas credenciais do Stripe:</strong></p>
    <ol>
        <li>Acesse <a href="https://dashboard.stripe.com" target="_blank">dashboard.stripe.com</a></li>
        <li>Faça login na sua conta Stripe</li>
        <li>Vá em <strong>Developers → API keys</strong></li>
        <li>Copie a <strong>Publishable key</strong> (pk_test_xxx ou pk_live_xxx)</li>
        <li>Copie a <strong>Secret key</strong> (sk_test_xxx ou sk_live_xxx)</li>
        <li>Para o Webhook Secret, vá em <strong>Developers → Webhooks</strong></li>
        <li>Adicione um endpoint: <code><?php echo SITE_URL; ?>/webhook/stripe_webhook.php</code></li>
        <li>Copie o <strong>Signing secret</strong> (whsec_xxx)</li>
    </ol>
    <p><strong>Modo Test:</strong> Use para testes sem cobranças reais (chaves começam com pk_test_ e sk_test_)</p>
    <p><strong>Modo Live:</strong> Use para produção com cobranças reais (chaves começam com pk_live_ e sk_live_)</p>
</div>

<div class="card">
    <div class="card-header">
        <h2>Configurações Cadastradas</h2>
    </div>
    <div class="card-body">
        <?php if (empty($configs)): ?>
            <p class="text-muted">Nenhuma configuração cadastrada.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Estabelecimento</th>
                            <th>Public Key</th>
                            <th>Modo</th>
                            <th>Status</th>
                            <th>Última Atualização</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($configs as $config): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($config['estabelecimento_nome']); ?></td>
                            <td><code><?php echo substr($config['stripe_public_key'], 0, 20) . '...'; ?></code></td>
                            <td>
                                <span class="badge badge-<?php echo $config['modo'] === 'live' ? 'success' : 'warning'; ?>">
                                    <?php echo strtoupper($config['modo']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $config['ativo'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $config['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                </span>
                            </td>
                            <td><?php echo formatDateTimeBR($config['updated_at']); ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick='editStripeConfig(<?php echo json_encode($config); ?>)'>
                                    Editar
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteStripeConfig(<?php echo $config['id']; ?>)">
                                    Excluir
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

<!-- Modal para adicionar/editar configuração -->
<div id="stripeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Nova Configuração Stripe</h2>
            <span class="close" onclick="closeModalStripe()">&times;</span>
        </div>
        <form method="POST" id="stripeForm">
            <input type="hidden" name="action" value="save">
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="estabelecimento_id">Estabelecimento *</label>
                    <select name="estabelecimento_id" id="estabelecimento_id" class="form-control" required>
                        <option value="">Selecione</option>
                        <?php foreach ($estabelecimentos as $estab): ?>
                        <option value="<?php echo $estab['id']; ?>"><?php echo htmlspecialchars($estab['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="stripe_public_key">Stripe Public Key (Publishable Key) *</label>
                    <input type="text" name="stripe_public_key" id="stripe_public_key" class="form-control" 
                           placeholder="pk_test_xxxx ou pk_live_xxxx" required>
                    <small class="form-text">Começa com pk_test_ (teste) ou pk_live_ (produção)</small>
                </div>
                
                <div class="form-group">
                    <label for="stripe_secret_key">Stripe Secret Key *</label>
                    <input type="password" name="stripe_secret_key" id="stripe_secret_key" class="form-control" 
                           placeholder="sk_test_xxxx ou sk_live_xxxx" required>
                    <small class="form-text">Começa com sk_test_ (teste) ou sk_live_ (produção)</small>
                </div>
                
                <div class="form-group">
                    <label for="stripe_webhook_secret">Webhook Signing Secret *</label>
                    <input type="password" name="stripe_webhook_secret" id="stripe_webhook_secret" class="form-control" 
                           placeholder="whsec_xxxx" required>
                    <small class="form-text">Obtido ao criar o webhook endpoint</small>
                </div>
                
                <div class="form-group">
                    <label for="modo">Modo *</label>
                    <select name="modo" id="modo" class="form-control" required>
                        <option value="test">Test (Testes)</option>
                        <option value="live">Live (Produção)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="ativo" id="ativo" value="1" checked>
                        Ativo
                    </label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModalStripe()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<style>
.info-box {
    background: #e3f2fd;
    border-left: 4px solid #2196F3;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.info-box h3 {
    margin-top: 0;
    color: #1976D2;
}

.info-box ol {
    margin: 10px 0;
    padding-left: 20px;
}

.info-box code {
    background: #fff;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.9em;
}

.badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}

.badge-success { background-color: #28a745; color: #fff; }
.badge-warning { background-color: #ffc107; color: #000; }
.badge-secondary { background-color: #6c757d; color: #fff; }
</style>

<script>
function openModalStripe() {
    document.getElementById('modalTitle').textContent = 'Nova Configuração Stripe';
    document.getElementById('stripeForm').reset();
    document.getElementById('ativo').checked = true;
    openModal('stripeModal');
}

function closeModalStripe() {
    closeModal('stripeModal');
}

function editStripeConfig(config) {
    document.getElementById('modalTitle').textContent = 'Editar Configuração Stripe';
    document.getElementById('estabelecimento_id').value = config.estabelecimento_id;
    document.getElementById('stripe_public_key').value = config.stripe_public_key;
    document.getElementById('stripe_secret_key').value = config.stripe_secret_key;
    document.getElementById('stripe_webhook_secret').value = config.stripe_webhook_secret;
    document.getElementById('modo').value = config.modo;
    document.getElementById('ativo').checked = config.ativo == 1;
    
    openModal('stripeModal');
}

function deleteStripeConfig(id) {
    if (confirm('Tem certeza que deseja excluir esta configuração?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
