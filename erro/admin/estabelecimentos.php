<?php
$page_title = 'Estabelecimentos';
$current_page = 'estabelecimentos';

require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdminGeral();

$conn = getDBConnection();
$success = '';
$error = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $name = sanitize($_POST['name']);
        $document = sanitize($_POST['document']);
        $address = sanitize($_POST['address']);
        $phone = sanitize($_POST['phone']);
        
        $stmt = $conn->prepare("INSERT INTO estabelecimentos (name, document, address, phone, status) VALUES (?, ?, ?, ?, 1)");
        
        if ($stmt->execute([$name, $document, $address, $phone])) {
            $success = 'Estabelecimento cadastrado com sucesso!';
        } else {
            $error = 'Erro ao cadastrar estabelecimento.';
        }
    }
    
    if ($action === 'update') {
        $id = $_POST['id'];
        $name = sanitize($_POST['name']);
        $document = sanitize($_POST['document']);
        $address = sanitize($_POST['address']);
        $phone = sanitize($_POST['phone']);
        $status = isset($_POST['status']) ? 1 : 0;
        
        $stmt = $conn->prepare("UPDATE estabelecimentos SET name = ?, document = ?, address = ?, phone = ?, status = ? WHERE id = ?");
        
        if ($stmt->execute([$name, $document, $address, $phone, $status, $id])) {
            $success = 'Estabelecimento atualizado com sucesso!';
        } else {
            $error = 'Erro ao atualizar estabelecimento.';
        }
    }
    
    if ($action === 'delete') {
        $id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM estabelecimentos WHERE id = ?");
        if ($stmt->execute([$id])) {
            $success = 'Estabelecimento excluído com sucesso!';
        } else {
            $error = 'Erro ao excluir estabelecimento.';
        }
    }
}

// Listar estabelecimentos
$stmt = $conn->query("SELECT * FROM estabelecimentos ORDER BY created_at DESC");
$estabelecimentos = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="page-header">
    <h1>Estabelecimentos</h1>
    <button class="btn btn-primary" onclick="openModal('modalEstabelecimento')">+ Novo Estabelecimento</button>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Documento</th>
                        <th>Endereço</th>
                        <th>Telefone</th>
                        <th>Status</th>
                        <th>Cadastrado em</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($estabelecimentos as $estab): ?>
                    <tr>
                        <td><?php echo $estab['id']; ?></td>
                        <td><?php echo $estab['name']; ?></td>
                        <td><?php echo $estab['document']; ?></td>
                        <td><?php echo $estab['address']; ?></td>
                        <td><?php echo $estab['phone']; ?></td>
                        <td>
                            <span class="badge badge-<?php echo $estab['status'] ? 'success' : 'danger'; ?>">
                                <?php echo $estab['status'] ? 'Ativo' : 'Inativo'; ?>
                            </span>
                        </td>
                        <td><?php echo formatDateTimeBR($estab['created_at']); ?></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-sm btn-primary" onclick='editEstabelecimento(<?php echo json_encode($estab); ?>)'>Editar</button>
                                <form method="POST" style="display: inline;" onsubmit="return confirmDelete('Deseja excluir este estabelecimento?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $estab['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Excluir</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Estabelecimento -->
<div id="modalEstabelecimento" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Novo Estabelecimento</h3>
            <button class="modal-close" onclick="closeModal('modalEstabelecimento')">&times;</button>
        </div>
        <form method="POST" id="formEstabelecimento">
            <div class="modal-body">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="estabelecimentoId">
                
                <div class="form-group">
                    <label for="name">Nome *</label>
                    <input type="text" name="name" id="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="document">CNPJ/CPF *</label>
                    <input type="text" name="document" id="document" class="form-control" required 
                           placeholder="00.000.000/0000-00 ou 000.000.000-00"
                           onkeyup="maskCNPJCPF(this)" onblur="validateCNPJCPF(this)">
                    <small class="form-text text-muted">Digite apenas números, a máscara será aplicada automaticamente</small>
                </div>
                
                <div class="form-group">
                    <label for="address">Endereço *</label>
                    <input type="text" name="address" id="address" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Telefone *</label>
                    <input type="text" name="phone" id="phone" class="form-control" required onkeyup="maskPhone(this)">
                </div>
                
                <div class="form-group" id="statusGroup" style="display: none;">
                    <label class="checkbox-label">
                        <input type="checkbox" name="status" id="status" value="1" checked>
                        <span>Estabelecimento Ativo</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalEstabelecimento')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<?php
$extra_js = <<<'JS'
<script>
function editEstabelecimento(estab) {
    document.getElementById('modalTitle').textContent = 'Editar Estabelecimento';
    document.getElementById('formAction').value = 'update';
    document.getElementById('estabelecimentoId').value = estab.id;
    document.getElementById('name').value = estab.name;
    document.getElementById('document').value = estab.document;
    document.getElementById('address').value = estab.address;
    document.getElementById('phone').value = estab.phone;
    document.getElementById('status').checked = estab.status == 1;
    document.getElementById('statusGroup').style.display = 'block';
    
    openModal('modalEstabelecimento');
}

// Reset form ao abrir modal para novo estabelecimento
document.querySelector('[onclick="openModal(\'modalEstabelecimento\')"]').addEventListener('click', function() {
    document.getElementById('modalTitle').textContent = 'Novo Estabelecimento';
    document.getElementById('formAction').value = 'create';
    document.getElementById('formEstabelecimento').reset();
    document.getElementById('statusGroup').style.display = 'none';
});

// Máscara de CNPJ/CPF
function maskCNPJCPF(input) {
    let value = input.value.replace(/\D/g, '');
    
    if (value.length <= 11) {
        // Máscara CPF: 000.000.000-00
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    } else {
        // Máscara CNPJ: 00.000.000/0000-00
        value = value.replace(/(\d{2})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d)/, '$1/$2');
        value = value.replace(/(\d{4})(\d{1,2})$/, '$1-$2');
    }
    
    input.value = value;
}

// Validar CNPJ/CPF
function validateCNPJCPF(input) {
    let value = input.value.replace(/\D/g, '');
    
    if (value.length === 0) {
        return; // Campo vazio, validação required cuidará
    }
    
    if (value.length === 11) {
        // Validar CPF
        if (!validarCPF(value)) {
            alert('CPF inválido!');
            input.focus();
            return false;
        }
    } else if (value.length === 14) {
        // Validar CNPJ
        if (!validarCNPJ(value)) {
            alert('CNPJ inválido!');
            input.focus();
            return false;
        }
    } else {
        alert('CNPJ/CPF deve ter 11 (CPF) ou 14 (CNPJ) dígitos!');
        input.focus();
        return false;
    }
    
    return true;
}

function validarCPF(cpf) {
    if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;
    
    let soma = 0;
    let resto;
    
    for (let i = 1; i <= 9; i++) {
        soma += parseInt(cpf.substring(i - 1, i)) * (11 - i);
    }
    
    resto = (soma * 10) % 11;
    if (resto === 10 || resto === 11) resto = 0;
    if (resto !== parseInt(cpf.substring(9, 10))) return false;
    
    soma = 0;
    for (let i = 1; i <= 10; i++) {
        soma += parseInt(cpf.substring(i - 1, i)) * (12 - i);
    }
    
    resto = (soma * 10) % 11;
    if (resto === 10 || resto === 11) resto = 0;
    if (resto !== parseInt(cpf.substring(10, 11))) return false;
    
    return true;
}

function validarCNPJ(cnpj) {
    if (cnpj.length !== 14 || /^(\d)\1{13}$/.test(cnpj)) return false;
    
    let tamanho = cnpj.length - 2;
    let numeros = cnpj.substring(0, tamanho);
    let digitos = cnpj.substring(tamanho);
    let soma = 0;
    let pos = tamanho - 7;
    
    for (let i = tamanho; i >= 1; i--) {
        soma += numeros.charAt(tamanho - i) * pos--;
        if (pos < 2) pos = 9;
    }
    
    let resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
    if (resultado != digitos.charAt(0)) return false;
    
    tamanho = tamanho + 1;
    numeros = cnpj.substring(0, tamanho);
    soma = 0;
    pos = tamanho - 7;
    
    for (let i = tamanho; i >= 1; i--) {
        soma += numeros.charAt(tamanho - i) * pos--;
        if (pos < 2) pos = 9;
    }
    
    resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
    if (resultado != digitos.charAt(1)) return false;
    
    return true;
}
</script>
JS;

require_once '../includes/footer.php';
?>
