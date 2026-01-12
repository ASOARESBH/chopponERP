<?php
/**
 * FORNECEDORES - Cadastro e Gerenciamento
 * Página para gerenciar fornecedores de barris
 */

$page_title = 'Fornecedores';
$current_page = 'fornecedores';

require_once '../includes/config.php';
require_once '../includes/auth.php';

requireAuth();

$conn = getDBConnection();

$success = '';
$error = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'criar') {
        try {
            $stmt = $conn->prepare("
                INSERT INTO fornecedores 
                (nome, razao_social, cnpj, email, telefone, whatsapp, endereco, 
                 cidade, estado, cep, contato_nome, observacoes, ativo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            
            $stmt->execute([
                $_POST['nome'],
                $_POST['razao_social'] ?? null,
                $_POST['cnpj'] ?? null,
                $_POST['email'] ?? null,
                $_POST['telefone'] ?? null,
                $_POST['whatsapp'] ?? null,
                $_POST['endereco'] ?? null,
                $_POST['cidade'] ?? null,
                $_POST['estado'] ?? null,
                $_POST['cep'] ?? null,
                $_POST['contato_nome'] ?? null,
                $_POST['observacoes'] ?? null
            ]);
            
            $success = 'Fornecedor cadastrado com sucesso!';
        } catch (Exception $e) {
            $error = 'Erro ao cadastrar fornecedor: ' . $e->getMessage();
        }
    }
    
    if ($action === 'atualizar') {
        try {
            $stmt = $conn->prepare("
                UPDATE fornecedores 
                SET nome = ?, razao_social = ?, cnpj = ?, email = ?, telefone = ?,
                    whatsapp = ?, endereco = ?, cidade = ?, estado = ?, cep = ?,
                    contato_nome = ?, observacoes = ?, ativo = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $_POST['nome'],
                $_POST['razao_social'] ?? null,
                $_POST['cnpj'] ?? null,
                $_POST['email'] ?? null,
                $_POST['telefone'] ?? null,
                $_POST['whatsapp'] ?? null,
                $_POST['endereco'] ?? null,
                $_POST['cidade'] ?? null,
                $_POST['estado'] ?? null,
                $_POST['cep'] ?? null,
                $_POST['contato_nome'] ?? null,
                $_POST['observacoes'] ?? null,
                isset($_POST['ativo']) ? 1 : 0,
                $_POST['id']
            ]);
            
            $success = 'Fornecedor atualizado com sucesso!';
        } catch (Exception $e) {
            $error = 'Erro ao atualizar fornecedor: ' . $e->getMessage();
        }
    }
    
    if ($action === 'excluir') {
        try {
            // Verificar se há produtos vinculados
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM estoque_produtos WHERE fornecedor_id = ?");
            $stmt->execute([$_POST['id']]);
            $result = $stmt->fetch();
            
            if ($result['total'] > 0) {
                $error = 'Não é possível excluir este fornecedor pois há ' . $result['total'] . ' produto(s) vinculado(s).';
            } else {
                $stmt = $conn->prepare("DELETE FROM fornecedores WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $success = 'Fornecedor excluído com sucesso!';
            }
        } catch (Exception $e) {
            $error = 'Erro ao excluir fornecedor: ' . $e->getMessage();
        }
    }
}

// Buscar fornecedores
$busca = $_GET['busca'] ?? '';
$where = [];
$params = [];

if ($busca) {
    $where[] = "(nome LIKE ? OR razao_social LIKE ? OR cnpj LIKE ?)";
    $busca_param = '%' . $busca . '%';
    $params[] = $busca_param;
    $params[] = $busca_param;
    $params[] = $busca_param;
}

$sql = "SELECT * FROM fornecedores";
if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY nome";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$fornecedores = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <h1><i class="fas fa-truck"></i> Fornecedores</h1>
        <p class="text-muted">Cadastro e gerenciamento de fornecedores de barris</p>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" onclick="this.parentElement.style.display='none'"></button>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" onclick="this.parentElement.style.display='none'"></button>
    </div>
    <?php endif; ?>

    <!-- Barra de Ações -->
    <div class="row mb-3">
        <div class="col-md-6">
            <button class="btn btn-primary" onclick="abrirModalFornecedor()">
                <i class="fas fa-plus"></i> Novo Fornecedor
            </button>
        </div>
        <div class="col-md-6">
            <form method="GET" class="d-flex">
                <input type="text" name="busca" class="form-control me-2" 
                       placeholder="Buscar por nome, razão social ou CNPJ..."
                       value="<?= htmlspecialchars($busca) ?>">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- Tabela de Fornecedores -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Razão Social</th>
                            <th>CNPJ</th>
                            <th>Contato</th>
                            <th>Cidade/UF</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($fornecedores)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">
                                Nenhum fornecedor cadastrado
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($fornecedores as $f): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($f['nome']) ?></strong>
                                <?php if ($f['contato_nome']): ?>
                                <br><small class="text-muted">Contato: <?= htmlspecialchars($f['contato_nome']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($f['razao_social'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($f['cnpj'] ?? '-') ?></td>
                            <td>
                                <?php if ($f['telefone']): ?>
                                <i class="fas fa-phone"></i> <?= htmlspecialchars($f['telefone']) ?><br>
                                <?php endif; ?>
                                <?php if ($f['whatsapp']): ?>
                                <i class="fab fa-whatsapp text-success"></i> <?= htmlspecialchars($f['whatsapp']) ?><br>
                                <?php endif; ?>
                                <?php if ($f['email']): ?>
                                <i class="fas fa-envelope"></i> <?= htmlspecialchars($f['email']) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($f['cidade'] || $f['estado']): ?>
                                <?= htmlspecialchars($f['cidade'] ?? '') ?><?= $f['cidade'] && $f['estado'] ? '/' : '' ?><?= htmlspecialchars($f['estado'] ?? '') ?>
                                <?php else: ?>
                                -
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $f['ativo'] ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= $f['ativo'] ? 'Ativo' : 'Inativo' ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-info" onclick='editarFornecedor(<?= json_encode($f) ?>)' title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger" onclick="excluirFornecedor(<?= $f['id'] ?>, '<?= htmlspecialchars($f['nome']) ?>')" title="Excluir">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Fornecedor -->
<div id="modalFornecedor" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h2 id="modalFornecedorTitle">Novo Fornecedor</h2>
            <span class="close" onclick="fecharModalFornecedor()">&times;</span>
        </div>
        <form method="POST" id="formFornecedor">
            <input type="hidden" name="action" id="fornecedor_action" value="criar">
            <input type="hidden" name="id" id="fornecedor_id">
            
            <div class="modal-body">
                <div class="row">
                    <!-- Informações Básicas -->
                    <div class="col-md-12 mb-3">
                        <h5 class="border-bottom pb-2">Informações Básicas</h5>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label required">Nome Fantasia</label>
                        <input type="text" name="nome" id="fornecedor_nome" class="form-control" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Razão Social</label>
                        <input type="text" name="razao_social" id="fornecedor_razao" class="form-control">
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label">CNPJ</label>
                        <input type="text" name="cnpj" id="fornecedor_cnpj" class="form-control" 
                               placeholder="00.000.000/0000-00" maxlength="18">
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Telefone</label>
                        <input type="text" name="telefone" id="fornecedor_telefone" class="form-control" 
                               placeholder="(00) 0000-0000">
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label">WhatsApp</label>
                        <input type="text" name="whatsapp" id="fornecedor_whatsapp" class="form-control" 
                               placeholder="(00) 00000-0000">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">E-mail</label>
                        <input type="email" name="email" id="fornecedor_email" class="form-control">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nome do Contato</label>
                        <input type="text" name="contato_nome" id="fornecedor_contato" class="form-control" 
                               placeholder="Nome da pessoa de contato">
                    </div>
                    
                    <!-- Endereço -->
                    <div class="col-md-12 mb-3 mt-3">
                        <h5 class="border-bottom pb-2">Endereço</h5>
                    </div>
                    
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Endereço Completo</label>
                        <input type="text" name="endereco" id="fornecedor_endereco" class="form-control" 
                               placeholder="Rua, número, complemento, bairro">
                    </div>
                    
                    <div class="col-md-5 mb-3">
                        <label class="form-label">Cidade</label>
                        <input type="text" name="cidade" id="fornecedor_cidade" class="form-control">
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <label class="form-label">UF</label>
                        <select name="estado" id="fornecedor_estado" class="form-select">
                            <option value="">--</option>
                            <option value="AC">AC</option>
                            <option value="AL">AL</option>
                            <option value="AP">AP</option>
                            <option value="AM">AM</option>
                            <option value="BA">BA</option>
                            <option value="CE">CE</option>
                            <option value="DF">DF</option>
                            <option value="ES">ES</option>
                            <option value="GO">GO</option>
                            <option value="MA">MA</option>
                            <option value="MT">MT</option>
                            <option value="MS">MS</option>
                            <option value="MG">MG</option>
                            <option value="PA">PA</option>
                            <option value="PB">PB</option>
                            <option value="PR">PR</option>
                            <option value="PE">PE</option>
                            <option value="PI">PI</option>
                            <option value="RJ">RJ</option>
                            <option value="RN">RN</option>
                            <option value="RS">RS</option>
                            <option value="RO">RO</option>
                            <option value="RR">RR</option>
                            <option value="SC">SC</option>
                            <option value="SP">SP</option>
                            <option value="SE">SE</option>
                            <option value="TO">TO</option>
                        </select>
                    </div>
                    
                    <div class="col-md-5 mb-3">
                        <label class="form-label">CEP</label>
                        <input type="text" name="cep" id="fornecedor_cep" class="form-control" 
                               placeholder="00000-000" maxlength="9">
                    </div>
                    
                    <!-- Observações -->
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Observações</label>
                        <textarea name="observacoes" id="fornecedor_obs" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="col-md-12 mb-3" id="div_ativo" style="display: none;">
                        <label class="form-label">
                            <input type="checkbox" name="ativo" id="fornecedor_ativo" value="1" checked>
                            Fornecedor Ativo
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fecharModalFornecedor()">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    overflow-y: auto;
}

.modal-content {
    background-color: #fff;
    margin: 2% auto;
    padding: 0;
    border-radius: 8px;
    width: 90%;
    max-width: 900px;
}

.modal-lg {
    max-width: 1000px;
}

.modal-header {
    padding: 20px;
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 8px 8px 0 0;
}

.modal-body {
    padding: 20px;
    max-height: 70vh;
    overflow-y: auto;
}

.modal-footer {
    padding: 15px 20px;
    background-color: #f8f9fa;
    border-top: 1px solid #dee2e6;
    text-align: right;
    border-radius: 0 0 8px 8px;
}

.close {
    font-size: 28px;
    font-weight: bold;
    color: #aaa;
    cursor: pointer;
}

.close:hover {
    color: #000;
}

.required::after {
    content: ' *';
    color: red;
}

.btn-close {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
}
</style>

<script>
function abrirModalFornecedor() {
    document.getElementById('modalFornecedor').style.display = 'block';
    document.getElementById('modalFornecedorTitle').textContent = 'Novo Fornecedor';
    document.getElementById('formFornecedor').reset();
    document.getElementById('fornecedor_action').value = 'criar';
    document.getElementById('fornecedor_id').value = '';
    document.getElementById('div_ativo').style.display = 'none';
}

function fecharModalFornecedor() {
    document.getElementById('modalFornecedor').style.display = 'none';
}

function editarFornecedor(fornecedor) {
    document.getElementById('modalFornecedor').style.display = 'block';
    document.getElementById('modalFornecedorTitle').textContent = 'Editar Fornecedor';
    document.getElementById('fornecedor_action').value = 'atualizar';
    document.getElementById('fornecedor_id').value = fornecedor.id;
    document.getElementById('fornecedor_nome').value = fornecedor.nome;
    document.getElementById('fornecedor_razao').value = fornecedor.razao_social || '';
    document.getElementById('fornecedor_cnpj').value = fornecedor.cnpj || '';
    document.getElementById('fornecedor_telefone').value = fornecedor.telefone || '';
    document.getElementById('fornecedor_whatsapp').value = fornecedor.whatsapp || '';
    document.getElementById('fornecedor_email').value = fornecedor.email || '';
    document.getElementById('fornecedor_contato').value = fornecedor.contato_nome || '';
    document.getElementById('fornecedor_endereco').value = fornecedor.endereco || '';
    document.getElementById('fornecedor_cidade').value = fornecedor.cidade || '';
    document.getElementById('fornecedor_estado').value = fornecedor.estado || '';
    document.getElementById('fornecedor_cep').value = fornecedor.cep || '';
    document.getElementById('fornecedor_obs').value = fornecedor.observacoes || '';
    document.getElementById('fornecedor_ativo').checked = fornecedor.ativo == 1;
    document.getElementById('div_ativo').style.display = 'block';
}

function excluirFornecedor(id, nome) {
    if (confirm('Tem certeza que deseja excluir o fornecedor "' + nome + '"?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="excluir">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Máscara para CNPJ
document.getElementById('fornecedor_cnpj')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length <= 14) {
        value = value.replace(/^(\d{2})(\d)/, '$1.$2');
        value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
        value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
        value = value.replace(/(\d{4})(\d)/, '$1-$2');
    }
    e.target.value = value;
});

// Máscara para CEP
document.getElementById('fornecedor_cep')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length <= 8) {
        value = value.replace(/^(\d{5})(\d)/, '$1-$2');
    }
    e.target.value = value;
});

// Fechar modal ao clicar fora
window.onclick = function(event) {
    const modal = document.getElementById('modalFornecedor');
    if (event.target == modal) {
        fecharModalFornecedor();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
