<?php
$page_title = 'Configuração Banco Cora';
$current_page = 'cora_config';

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
        $client_id = sanitize($_POST['client_id']);
        $client_secret = sanitize($_POST['client_secret']);
        $ambiente = sanitize($_POST['ambiente']);
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        
        try {
            // Verificar se já existe configuração
            $stmt = $conn->prepare("SELECT id FROM cora_config WHERE estabelecimento_id = ?");
            $stmt->execute([$estabelecimento_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Atualizar
                $stmt = $conn->prepare("
                    UPDATE cora_config 
                    SET client_id = ?, client_secret = ?, environment = ?, ativo = ?
                    WHERE estabelecimento_id = ?
                ");
                $stmt->execute([$client_id, $client_secret, $ambiente, $ativo, $estabelecimento_id]);
                $success = 'Configuração do Banco Cora atualizada com sucesso!';
            } else {
                // Inserir
                $stmt = $conn->prepare("
                    INSERT INTO cora_config (estabelecimento_id, client_id, client_secret, environment, ativo)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$estabelecimento_id, $client_id, $client_secret, $ambiente, $ativo]);
                $success = 'Configuração do Banco Cora cadastrada com sucesso!';
            }
            
            $_SESSION['success'] = $success;
            header('Location: cora_config.php');
            exit;
            
        } catch (Exception $e) {
            $error = 'Erro ao salvar configuração: ' . $e->getMessage();
        }
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        
        try {
            $stmt = $conn->prepare("DELETE FROM cora_config WHERE id = ?");
            $stmt->execute([$id]);
            
            $_SESSION['success'] = 'Configuração excluída com sucesso!';
            header('Location: cora_config.php');
            exit;
        } catch (Exception $e) {
            $error = 'Erro ao excluir configuração: ' . $e->getMessage();
        }
    }
}

// Buscar configurações existentes
$stmt = $conn->query("
    SELECT c.*, e.name as estabelecimento_nome 
    FROM cora_config c
    LEFT JOIN estabelecimentos e ON c.estabelecimento_id = e.id
    ORDER BY e.name
");
$configs = $stmt->fetchAll();

// Buscar estabelecimentos para dropdown
$stmt = $conn->query("SELECT id, name FROM estabelecimentos WHERE status = 1 ORDER BY name");
$estabelecimentos = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="page-header">
    <h1>Configuração Banco Cora</h1>
    <button class="btn btn-primary" onclick="openModalCora()">
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
    <p><strong>Para obter suas credenciais do Banco Cora:</strong></p>
    <ol>
        <li>Acesse o aplicativo Cora ou <a href="https://web.cora.com.br" target="_blank">web.cora.com.br</a></li>
        <li>Vá em <strong>Configurações → Integrações → API</strong></li>
        <li>Solicite as credenciais de <strong>Integração Direta</strong></li>
        <li>Copie o <strong>Client ID</strong></li>
        <li>Copie o <strong>Client Secret</strong></li>
    </ol>
    <p><strong>Ambiente Stage:</strong> Use para testes (sandbox)</p>
    <p><strong>Ambiente Production:</strong> Use para produção com cobranças reais</p>
    <p><strong>Importante:</strong> O plano CoraPro (R$ 44,90/mês) é necessário para usar a API</p>
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
                            <th>Client ID</th>
                            <th>Ambiente</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($configs as $config): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($config['estabelecimento_nome']); ?></td>
                            <td><code><?php echo substr($config['client_id'], 0, 15) . '...'; ?></code></td>
                            <td>
                                <span class="badge badge-<?php echo $config['environment'] === 'production' ? 'success' : 'warning'; ?>">
                                    <?php echo strtoupper($config['environment']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $config['ativo'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $config['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick='editCoraConfig(<?php echo json_encode($config); ?>)'>
                                    Editar
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteCoraConfig(<?php echo $config['id']; ?>)">
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

<!-- Modal -->
<div id="modalCora" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Nova Configuração Banco Cora</h2>
            <span class="close" onclick="closeModalCora()">&times;</span>
        </div>
        <form method="POST" id="coraForm">
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
                    <label for="client_id">Client ID *</label>
                    <input type="text" name="client_id" id="client_id" class="form-control" 
                           placeholder="Seu Client ID da Cora" required>
                    <small class="form-text">Obtido em: Conta > Integrações via APIs</small>
                </div>
                
                <div class="form-group">
                    <label for="client_secret">Client Secret *</label>
                    <input type="password" name="client_secret" id="client_secret" class="form-control" 
                           placeholder="Seu Client Secret da Cora" required>
                    <small class="form-text">Obtido em: Conta > Integrações via APIs</small>
                </div>
                
                <div class="form-group">
                    <label for="ambiente">Ambiente *</label>
                    <select name="ambiente" id="ambiente" class="form-control" required>
                        <option value="stage">Stage (Testes)</option>
                        <option value="production">Production (Produção)</option>
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
                <button type="button" class="btn btn-secondary" onclick="closeModalCora()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<style>
.info-box {
    background: #e8f5e9;
    border-left: 4px solid #4CAF50;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.info-box h3 {
    margin-top: 0;
    color: #2E7D32;
}

.info-box ol {
    margin: 10px 0;
    padding-left: 20px;
}

.info-box li {
    margin: 5px 0;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 0;
    border: 1px solid #888;
    width: 90%;
    max-width: 600px;
    border-radius: 8px;
}

.modal-header {
    padding: 20px;
    background-color: #f5f5f5;
    border-bottom: 1px solid #ddd;
    border-radius: 8px 8px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 15px 20px;
    background-color: #f5f5f5;
    border-top: 1px solid #ddd;
    border-radius: 0 0 8px 8px;
    text-align: right;
}

.close {
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    color: #000;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-text {
    display: block;
    margin-top: 5px;
    color: #666;
    font-size: 12px;
}

.badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}

.badge-success {
    background-color: #4CAF50;
    color: white;
}

.badge-warning {
    background-color: #FF9800;
    color: white;
}

.badge-secondary {
    background-color: #9E9E9E;
    color: white;
}
</style>

<script>
function openModalCora() {
    document.getElementById('modalCora').style.display = 'block';
    document.getElementById('modalTitle').textContent = 'Nova Configuração Banco Cora';
    document.getElementById('coraForm').reset();
    document.getElementById('client_secret').setAttribute('required', 'required');
}

function closeModalCora() {
    document.getElementById('modalCora').style.display = 'none';
}

function editCoraConfig(config) {
    document.getElementById('modalCora').style.display = 'block';
    document.getElementById('modalTitle').textContent = 'Editar Configuração Banco Cora';
    
    document.getElementById('estabelecimento_id').value = config.estabelecimento_id;
    document.getElementById('client_id').value = config.client_id;
    document.getElementById('client_secret').value = config.client_secret || '';
    document.getElementById('ambiente').value = config.environment;
    document.getElementById('ativo').checked = config.ativo == 1;
    
    // Client secret não é obrigatório na edição (mantém o anterior se não preencher)
    document.getElementById('client_secret').removeAttribute('required');
    document.getElementById('client_secret').placeholder = 'Deixe em branco para manter o atual';
}

function deleteCoraConfig(id) {
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

// Fechar modal ao clicar fora
window.onclick = function(event) {
    const modal = document.getElementById('modalCora');
    if (event.target == modal) {
        closeModalCora();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
