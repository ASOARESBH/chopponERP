<?php
$page_title = 'Gerenciar Permissões';
$current_page = 'permissoes';

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';

// Apenas Admin Geral pode acessar
requireAdminGeral();

$conn = getDBConnection();
$success = '';
$error = '';

// Processar salvamento de permissões
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_permissions') {
    $user_id = $_POST['user_id'];
    $permissions = $_POST['permissions'] ?? [];
    
    if (saveUserPermissions($user_id, $permissions)) {
        $success = 'Permissões atualizadas com sucesso!';
    } else {
        $error = 'Erro ao atualizar permissões.';
    }
}

// Listar todos os usuários (exceto o próprio admin logado)
$stmt = $conn->prepare("
    SELECT u.*, 
           GROUP_CONCAT(e.name SEPARATOR ', ') as estabelecimentos
    FROM users u
    LEFT JOIN user_estabelecimento ue ON u.id = ue.user_id AND ue.status = 1
    LEFT JOIN estabelecimentos e ON ue.estabelecimento_id = e.id
    WHERE u.id != ?
    GROUP BY u.id
    ORDER BY u.type ASC, u.name ASC
");
$stmt->execute([$_SESSION['user_id']]);
$usuarios = $stmt->fetchAll();

// Obter páginas agrupadas por categoria
$pages_by_category = getPagesByCategory();

require_once '../includes/header.php';
?>

<div class="page-header">
    <h1>Gerenciar Permissões de Usuários</h1>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <div class="row">
            <!-- Lista de Usuários -->
            <div class="col-md-4">
                <h3>Selecione um Usuário</h3>
                <div class="list-group" id="userList">
                    <?php foreach ($usuarios as $usuario): ?>
                    <a href="#" 
                       class="list-group-item list-group-item-action user-item" 
                       data-user-id="<?php echo $usuario['id']; ?>"
                       data-user-name="<?php echo htmlspecialchars($usuario['name']); ?>"
                       data-user-type="<?php echo $usuario['type']; ?>">
                        <div class="d-flex w-100 justify-content-between">
                            <h5 class="mb-1"><?php echo $usuario['name']; ?></h5>
                            <small class="badge badge-<?php echo $usuario['type'] == 1 ? 'danger' : ($usuario['type'] == 2 ? 'primary' : ($usuario['type'] == 3 ? 'info' : 'secondary')); ?>">
                                <?php echo getUserType($usuario['type']); ?>
                            </small>
                        </div>
                        <p class="mb-1"><small><?php echo $usuario['email']; ?></small></p>
                        <?php if ($usuario['estabelecimentos']): ?>
                        <p class="mb-0"><small class="text-muted">🏪 <?php echo $usuario['estabelecimentos']; ?></small></p>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Painel de Permissões -->
            <div class="col-md-8">
                <div id="permissionsPanel" style="display: none;">
                    <h3>Permissões de <span id="selectedUserName"></span></h3>
                    <p class="text-muted">Tipo: <strong id="selectedUserType"></strong></p>
                    
                    <form method="POST" id="permissionsForm">
                        <input type="hidden" name="action" value="save_permissions">
                        <input type="hidden" name="user_id" id="selectedUserId">
                        
                        <div id="permissionsContent"></div>
                        
                        <div class="form-actions" style="margin-top: 20px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Salvar Permissões
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="cancelSelection()">
                                <i class="fas fa-times"></i> Cancelar
                            </button>
                        </div>
                    </form>
                </div>
                
                <div id="noSelectionMessage" class="text-center" style="padding: 60px 20px;">
                    <i class="fas fa-user-lock" style="font-size: 64px; color: #ccc; margin-bottom: 20px;"></i>
                    <h4 style="color: #999;">Selecione um usuário para gerenciar permissões</h4>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.list-group-item {
    cursor: pointer;
    transition: all 0.3s ease;
}

.list-group-item:hover {
    background-color: #f8f9fa;
    transform: translateX(5px);
}

.list-group-item.active {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.permission-category {
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.permission-category h4 {
    margin-bottom: 15px;
    color: var(--primary-color);
    font-size: 18px;
}

.permission-row {
    display: flex;
    align-items: center;
    padding: 12px;
    background-color: white;
    border-radius: 6px;
    margin-bottom: 10px;
    border: 1px solid #e0e0e0;
}

.permission-row:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.permission-name {
    flex: 1;
    font-weight: 500;
}

.permission-name i {
    margin-right: 8px;
    color: var(--primary-color);
}

.permission-actions {
    display: flex;
    gap: 15px;
}

.permission-checkbox {
    display: flex;
    align-items: center;
}

.permission-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
    margin-right: 5px;
    cursor: pointer;
}

.permission-checkbox label {
    margin: 0;
    cursor: pointer;
    font-size: 13px;
}

.admin-only-badge {
    background-color: #dc3545;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    margin-left: 10px;
}

.badge {
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 12px;
}

.badge-danger { background-color: #dc3545; color: white; }
.badge-primary { background-color: #0066CC; color: white; }
.badge-info { background-color: #17a2b8; color: white; }
.badge-secondary { background-color: #6c757d; color: white; }
</style>

<script>
// Dados de permissões do PHP
const pagesByCategory = <?php echo json_encode($pages_by_category); ?>;

// Selecionar usuário
document.querySelectorAll('.user-item').forEach(item => {
    item.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Remover seleção anterior
        document.querySelectorAll('.user-item').forEach(i => i.classList.remove('active'));
        this.classList.add('active');
        
        const userId = this.dataset.userId;
        const userName = this.dataset.userName;
        const userType = this.dataset.userType;
        
        loadUserPermissions(userId, userName, userType);
    });
});

// Carregar permissões do usuário
function loadUserPermissions(userId, userName, userType) {
    document.getElementById('selectedUserId').value = userId;
    document.getElementById('selectedUserName').textContent = userName;
    document.getElementById('selectedUserType').textContent = getUserTypeName(userType);
    
    // Buscar permissões via AJAX
    fetch('ajax/get_user_permissions.php?user_id=' + userId)
        .then(response => response.json())
        .then(permissions => {
            renderPermissions(permissions);
            document.getElementById('noSelectionMessage').style.display = 'none';
            document.getElementById('permissionsPanel').style.display = 'block';
        })
        .catch(error => {
            console.error('Erro ao carregar permissões:', error);
            alert('Erro ao carregar permissões do usuário');
        });
}

// Renderizar permissões
function renderPermissions(permissions) {
    const content = document.getElementById('permissionsContent');
    content.innerHTML = '';
    
    // Agrupar permissões por categoria
    const grouped = {};
    permissions.forEach(perm => {
        const category = perm.page_category || 'Outros';
        if (!grouped[category]) {
            grouped[category] = [];
        }
        grouped[category].push(perm);
    });
    
    // Renderizar cada categoria
    Object.keys(grouped).forEach(category => {
        const categoryDiv = document.createElement('div');
        categoryDiv.className = 'permission-category';
        
        const categoryTitle = document.createElement('h4');
        categoryTitle.innerHTML = `<i class="fas fa-folder"></i> ${category}`;
        categoryDiv.appendChild(categoryTitle);
        
        grouped[category].forEach(perm => {
            const row = document.createElement('div');
            row.className = 'permission-row';
            
            const isAdminOnly = perm.admin_only == 1;
            const disabled = isAdminOnly ? 'disabled' : '';
            
            row.innerHTML = `
                <div class="permission-name">
                    <i class="${perm.page_icon}"></i>
                    ${perm.page_name}
                    ${isAdminOnly ? '<span class="admin-only-badge">ADMIN ONLY</span>' : ''}
                </div>
                <div class="permission-actions">
                    <div class="permission-checkbox">
                        <input type="checkbox" 
                               id="view_${perm.id}" 
                               name="permissions[${perm.id}][view]" 
                               ${perm.can_view == 1 ? 'checked' : ''} 
                               ${disabled}
                               onchange="updateDependentPermissions(${perm.id})">
                        <label for="view_${perm.id}">Ver</label>
                    </div>
                    <div class="permission-checkbox">
                        <input type="checkbox" 
                               id="create_${perm.id}" 
                               name="permissions[${perm.id}][create]" 
                               ${perm.can_create == 1 ? 'checked' : ''} 
                               ${disabled}>
                        <label for="create_${perm.id}">Criar</label>
                    </div>
                    <div class="permission-checkbox">
                        <input type="checkbox" 
                               id="edit_${perm.id}" 
                               name="permissions[${perm.id}][edit]" 
                               ${perm.can_edit == 1 ? 'checked' : ''} 
                               ${disabled}>
                        <label for="edit_${perm.id}">Editar</label>
                    </div>
                    <div class="permission-checkbox">
                        <input type="checkbox" 
                               id="delete_${perm.id}" 
                               name="permissions[${perm.id}][delete]" 
                               ${perm.can_delete == 1 ? 'checked' : ''} 
                               ${disabled}>
                        <label for="delete_${perm.id}">Excluir</label>
                    </div>
                </div>
            `;
            
            categoryDiv.appendChild(row);
        });
        
        content.appendChild(categoryDiv);
    });
}

// Atualizar permissões dependentes
function updateDependentPermissions(pageId) {
    const viewCheckbox = document.getElementById(`view_${pageId}`);
    const createCheckbox = document.getElementById(`create_${pageId}`);
    const editCheckbox = document.getElementById(`edit_${pageId}`);
    const deleteCheckbox = document.getElementById(`delete_${pageId}`);
    
    // Se desmarcar "Ver", desmarcar todas as outras
    if (!viewCheckbox.checked) {
        createCheckbox.checked = false;
        editCheckbox.checked = false;
        deleteCheckbox.checked = false;
    }
}

// Obter nome do tipo de usuário
function getUserTypeName(type) {
    const types = {
        '1': 'Administrador Geral',
        '2': 'Gerente',
        '3': 'Operador',
        '4': 'Visualizador'
    };
    return types[type] || 'Desconhecido';
}

// Cancelar seleção
function cancelSelection() {
    document.querySelectorAll('.user-item').forEach(i => i.classList.remove('active'));
    document.getElementById('permissionsPanel').style.display = 'none';
    document.getElementById('noSelectionMessage').style.display = 'block';
}
</script>

<?php require_once '../includes/footer.php'; ?>
