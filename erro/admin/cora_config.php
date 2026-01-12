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
        $cora_client_id = sanitize($_POST['cora_client_id']);
        $ambiente = sanitize($_POST['ambiente']);
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        
        // Upload de certificado
        $cert_path = '';
        if (isset($_FILES['certificate']) && $_FILES['certificate']['error'] === 0) {
            $upload_dir = '../certs/';
            $file_name = 'cora_cert_' . $estabelecimento_id . '.pem';
            $target_file = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['certificate']['tmp_name'], $target_file)) {
                chmod($target_file, 0600); // Permissões restritas
                $cert_path = 'certs/' . $file_name;
            }
        }
        
        // Upload de chave privada
        $key_path = '';
        if (isset($_FILES['private_key']) && $_FILES['private_key']['error'] === 0) {
            $upload_dir = '../certs/';
            $file_name = 'cora_key_' . $estabelecimento_id . '.key';
            $target_file = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['private_key']['tmp_name'], $target_file)) {
                chmod($target_file, 0600); // Permissões restritas
                $key_path = 'certs/' . $file_name;
            }
        }
        
        try {
            // Verificar se já existe configuração
            $stmt = $conn->prepare("SELECT id, cora_certificate_path, cora_private_key_path FROM cora_config WHERE estabelecimento_id = ?");
            $stmt->execute([$estabelecimento_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Atualizar
                $update_parts = ["cora_client_id = ?", "ambiente = ?", "ativo = ?"];
                $params = [$cora_client_id, $ambiente, $ativo];
                
                if ($cert_path) {
                    $update_parts[] = "cora_certificate_path = ?";
                    $params[] = $cert_path;
                }
                
                if ($key_path) {
                    $update_parts[] = "cora_private_key_path = ?";
                    $params[] = $key_path;
                }
                
                $params[] = $estabelecimento_id;
                
                $stmt = $conn->prepare("
                    UPDATE cora_config 
                    SET " . implode(", ", $update_parts) . "
                    WHERE estabelecimento_id = ?
                ");
                $stmt->execute($params);
                $success = 'Configuração do Banco Cora atualizada com sucesso!';
            } else {
                // Inserir
                $stmt = $conn->prepare("
                    INSERT INTO cora_config (estabelecimento_id, cora_client_id, cora_certificate_path, cora_private_key_path, ambiente, ativo)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$estabelecimento_id, $cora_client_id, $cert_path, $key_path, $ambiente, $ativo]);
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
            // Buscar arquivos para deletar
            $stmt = $conn->prepare("SELECT cora_certificate_path, cora_private_key_path FROM cora_config WHERE id = ?");
            $stmt->execute([$id]);
            $config = $stmt->fetch();
            
            if ($config) {
                if ($config['cora_certificate_path'] && file_exists('../' . $config['cora_certificate_path'])) {
                    unlink('../' . $config['cora_certificate_path']);
                }
                if ($config['cora_private_key_path'] && file_exists('../' . $config['cora_private_key_path'])) {
                    unlink('../' . $config['cora_private_key_path']);
                }
            }
            
            $stmt = $conn->prepare("DELETE FROM cora_config WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = 'Configuração removida com sucesso!';
            header('Location: cora_config.php');
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
    SELECT cc.*, e.name as estabelecimento_nome
    FROM cora_config cc
    INNER JOIN estabelecimentos e ON cc.estabelecimento_id = e.id
    ORDER BY e.name
");
$configs = $stmt->fetchAll();

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
        <li>Faça o download do <strong>Certificado Digital</strong> (arquivo .pem)</li>
        <li>Faça o download da <strong>Chave Privada</strong> (arquivo .key)</li>
        <li>Copie o <strong>Client ID</strong></li>
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
                            <th>Certificado</th>
                            <th>Chave Privada</th>
                            <th>Ambiente</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($configs as $config): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($config['estabelecimento_nome']); ?></td>
                            <td><code><?php echo substr($config['cora_client_id'], 0, 15) . '...'; ?></code></td>
                            <td>
                                <?php if ($config['cora_certificate_path']): ?>
                                    <span class="badge badge-success">✓ Enviado</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">✗ Não enviado</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($config['cora_private_key_path']): ?>
                                    <span class="badge badge-success">✓ Enviado</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">✗ Não enviado</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $config['ambiente'] === 'production' ? 'success' : 'warning'; ?>">
                                    <?php echo strtoupper($config['ambiente']); ?>
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

<!-- Modal para adicionar/editar configuração -->
<div id="coraModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Nova Configuração Banco Cora</h2>
            <span class="close" onclick="closeModalCora()">&times;</span>
        </div>
        <form method="POST" enctype="multipart/form-data" id="coraForm">
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
                    <label for="cora_client_id">Client ID *</label>
                    <input type="text" name="cora_client_id" id="cora_client_id" class="form-control" 
                           placeholder="Seu Client ID da Cora" required>
                </div>
                
                <div class="form-group">
                    <label for="certificate">Certificado Digital (.pem) *</label>
                    <input type="file" name="certificate" id="certificate" class="form-control" accept=".pem">
                    <small class="form-text">Arquivo fornecido pela Cora com extensão .pem</small>
                </div>
                
                <div class="form-group">
                    <label for="private_key">Chave Privada (.key) *</label>
                    <input type="file" name="private_key" id="private_key" class="form-control" accept=".key">
                    <small class="form-text">Arquivo fornecido pela Cora com extensão .key</small>
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
function openModalCora() {
    document.getElementById('modalTitle').textContent = 'Nova Configuração Banco Cora';
    document.getElementById('coraForm').reset();
    document.getElementById('ativo').checked = true;
    openModal('coraModal');
}

function closeModalCora() {
    closeModal('coraModal');
}

function editCoraConfig(config) {
    document.getElementById('modalTitle').textContent = 'Editar Configuração Banco Cora';
    document.getElementById('estabelecimento_id').value = config.estabelecimento_id;
    document.getElementById('cora_client_id').value = config.cora_client_id;
    document.getElementById('ambiente').value = config.ambiente;
    document.getElementById('ativo').checked = config.ativo == 1;
    
    // Remover required dos arquivos ao editar
    document.getElementById('certificate').removeAttribute('required');
    document.getElementById('private_key').removeAttribute('required');
    
    openModal('coraModal');
}

function deleteCoraConfig(id) {
    if (confirm('Tem certeza que deseja excluir esta configuração? Os arquivos de certificado também serão removidos.')) {
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
