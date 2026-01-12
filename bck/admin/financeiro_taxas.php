<?php
$page_title = 'Financeiro - Taxas de Juros';
$current_page = 'financeiro_taxas';

require_once '../includes/config.php';
require_once '../includes/auth.php';

$conn = getDBConnection();

// Processar ações (adicionar, editar, excluir)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            $action = $_POST['action'];
            
            if ($action === 'add' || $action === 'edit') {
                $estabelecimento_id = isAdminGeral() ? $_POST['estabelecimento_id'] : getEstabelecimentoId();
                $tipo = sanitize($_POST['tipo']);
                $bandeira = !empty($_POST['bandeira']) ? sanitize($_POST['bandeira']) : null;
                $taxa_percentual = numberToFloat($_POST['taxa_percentual']);
                $taxa_fixa = numberToFloat($_POST['taxa_fixa']);
                $ativo = isset($_POST['ativo']) ? 1 : 0;
                
                if ($action === 'add') {
                    $stmt = $conn->prepare("
                        INSERT INTO formas_pagamento 
                        (estabelecimento_id, tipo, bandeira, taxa_percentual, taxa_fixa, ativo) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$estabelecimento_id, $tipo, $bandeira, $taxa_percentual, $taxa_fixa, $ativo]);
                    $_SESSION['success'] = 'Forma de pagamento cadastrada com sucesso!';
                } else {
                    $id = intval($_POST['id']);
                    $stmt = $conn->prepare("
                        UPDATE formas_pagamento 
                        SET tipo = ?, bandeira = ?, taxa_percentual = ?, taxa_fixa = ?, ativo = ?
                        WHERE id = ? AND estabelecimento_id = ?
                    ");
                    $stmt->execute([$tipo, $bandeira, $taxa_percentual, $taxa_fixa, $ativo, $id, $estabelecimento_id]);
                    $_SESSION['success'] = 'Forma de pagamento atualizada com sucesso!';
                }
            } elseif ($action === 'delete') {
                $id = intval($_POST['id']);
                $estabelecimento_id = isAdminGeral() ? $_POST['estabelecimento_id'] : getEstabelecimentoId();
                
                $stmt = $conn->prepare("DELETE FROM formas_pagamento WHERE id = ? AND estabelecimento_id = ?");
                $stmt->execute([$id, $estabelecimento_id]);
                $_SESSION['success'] = 'Forma de pagamento excluída com sucesso!';
            }
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = 'Erro ao processar ação: ' . $e->getMessage();
    }
}

// Buscar formas de pagamento
if (isAdminGeral()) {
    $stmt = $conn->query("
        SELECT fp.*, e.name as estabelecimento_nome
        FROM formas_pagamento fp
        INNER JOIN estabelecimentos e ON fp.estabelecimento_id = e.id
        ORDER BY e.name, fp.tipo, fp.bandeira
    ");
} else {
    $estabelecimento_id = getEstabelecimentoId();
    $stmt = $conn->prepare("
        SELECT fp.*, e.name as estabelecimento_nome
        FROM formas_pagamento fp
        INNER JOIN estabelecimentos e ON fp.estabelecimento_id = e.id
        WHERE fp.estabelecimento_id = ?
        ORDER BY fp.tipo, fp.bandeira
    ");
    $stmt->execute([$estabelecimento_id]);
}
$formas_pagamento = $stmt->fetchAll();

// Buscar estabelecimentos (para admin)
if (isAdminGeral()) {
    $stmt = $conn->query("SELECT id, name FROM estabelecimentos WHERE status = 1 ORDER BY name");
    $estabelecimentos = $stmt->fetchAll();
}

require_once '../includes/header.php';
?>

<div class="page-header">
    <h1>Taxas de Juros - Formas de Pagamento</h1>
    <button class="btn btn-primary" onclick="openModal()">
        <span>➕</span> Nova Forma de Pagamento
    </button>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger">
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>Formas de Pagamento Cadastradas</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <?php if (isAdminGeral()): ?>
                        <th>Estabelecimento</th>
                        <?php endif; ?>
                        <th>Tipo</th>
                        <th>Bandeira</th>
                        <th>Taxa (%)</th>
                        <th>Taxa Fixa (R$)</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($formas_pagamento)): ?>
                        <tr>
                            <td colspan="<?php echo isAdminGeral() ? '7' : '6'; ?>" class="text-center">
                                Nenhuma forma de pagamento cadastrada
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($formas_pagamento as $forma): ?>
                            <tr>
                                <?php if (isAdminGeral()): ?>
                                <td><?php echo htmlspecialchars($forma['estabelecimento_nome']); ?></td>
                                <?php endif; ?>
                                <td>
                                    <?php 
                                    $tipo_label = [
                                        'pix' => '💰 PIX',
                                        'credito' => '💳 Crédito',
                                        'debito' => '💳 Débito'
                                    ];
                                    echo $tipo_label[$forma['tipo']] ?? $forma['tipo'];
                                    ?>
                                </td>
                                <td><?php echo $forma['bandeira'] ? htmlspecialchars($forma['bandeira']) : '-'; ?></td>
                                <td><?php echo number_format($forma['taxa_percentual'], 2, ',', '.'); ?>%</td>
                                <td><?php echo formatMoney($forma['taxa_fixa']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $forma['ativo'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $forma['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-info" onclick='editForma(<?php echo json_encode($forma); ?>)'>
                                        ✏️ Editar
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteForma(<?php echo $forma['id']; ?>, <?php echo $forma['estabelecimento_id']; ?>)">
                                        🗑️ Excluir
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal para adicionar/editar forma de pagamento -->
<div id="formaModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Nova Forma de Pagamento</h2>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <form method="POST" id="formaForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="formaId">
            <?php if (!isAdminGeral()): ?>
            <input type="hidden" name="estabelecimento_id" value="<?php echo getEstabelecimentoId(); ?>">
            <?php endif; ?>
            
            <div class="modal-body">
                <?php if (isAdminGeral()): ?>
                <div class="form-group">
                    <label for="estabelecimento_id">Estabelecimento *</label>
                    <select name="estabelecimento_id" id="estabelecimento_id" class="form-control" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($estabelecimentos as $est): ?>
                            <option value="<?php echo $est['id']; ?>"><?php echo htmlspecialchars($est['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="tipo">Tipo de Pagamento *</label>
                    <select name="tipo" id="tipo" class="form-control" required onchange="toggleBandeira()">
                        <option value="">Selecione...</option>
                        <option value="pix">PIX</option>
                        <option value="credito">Crédito</option>
                        <option value="debito">Débito</option>
                    </select>
                </div>
                
                <div class="form-group" id="bandeiraGroup" style="display: none;">
                    <label for="bandeira">Bandeira</label>
                    <select name="bandeira" id="bandeira" class="form-control">
                        <option value="">Selecione...</option>
                        <option value="Mastercard">Mastercard</option>
                        <option value="Visa">Visa</option>
                        <option value="Elo">Elo</option>
                        <option value="American Express">American Express</option>
                        <option value="Hipercard">Hipercard</option>
                        <option value="Diners">Diners</option>
                        <option value="Discover">Discover</option>
                        <option value="Outras">Outras</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="taxa_percentual">Taxa Percentual (%)</label>
                        <input type="text" name="taxa_percentual" id="taxa_percentual" class="form-control" value="0,00" placeholder="Ex: 2,50">
                        <small class="form-text">Taxa em percentual sobre o valor da transação</small>
                    </div>
                    
                    <div class="form-group col-md-6">
                        <label for="taxa_fixa">Taxa Fixa (R$)</label>
                        <input type="text" name="taxa_fixa" id="taxa_fixa" class="form-control" value="0,00" placeholder="Ex: 0,50">
                        <small class="form-text">Taxa fixa por transação</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="ativo" id="ativo" checked>
                        <span>Forma de pagamento ativa</span>
                    </label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
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
}

.modal-content {
    background-color: #fff;
    margin: 5% auto;
    padding: 0;
    border-radius: 8px;
    width: 90%;
    max-width: 600px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
    font-size: 1.5rem;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid #e0e0e0;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
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

.form-row {
    display: flex;
    gap: 15px;
}

.form-row .form-group {
    flex: 1;
}

.badge {
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.badge-success {
    background-color: #28a745;
    color: white;
}

.badge-secondary {
    background-color: #6c757d;
    color: white;
}
</style>

<script>
function openModal() {
    document.getElementById('formaModal').style.display = 'block';
    document.getElementById('modalTitle').textContent = 'Nova Forma de Pagamento';
    document.getElementById('formAction').value = 'add';
    document.getElementById('formaForm').reset();
    document.getElementById('formaId').value = '';
    document.getElementById('ativo').checked = true;
    toggleBandeira();
}

function closeModal() {
    document.getElementById('formaModal').style.display = 'none';
}

function toggleBandeira() {
    const tipo = document.getElementById('tipo').value;
    const bandeiraGroup = document.getElementById('bandeiraGroup');
    const bandeiraSelect = document.getElementById('bandeira');
    
    if (tipo === 'credito' || tipo === 'debito') {
        bandeiraGroup.style.display = 'block';
        bandeiraSelect.required = true;
    } else {
        bandeiraGroup.style.display = 'none';
        bandeiraSelect.required = false;
        bandeiraSelect.value = '';
    }
}

function editForma(forma) {
    document.getElementById('formaModal').style.display = 'block';
    document.getElementById('modalTitle').textContent = 'Editar Forma de Pagamento';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formaId').value = forma.id;
    
    <?php if (isAdminGeral()): ?>
    document.getElementById('estabelecimento_id').value = forma.estabelecimento_id;
    <?php endif; ?>
    
    document.getElementById('tipo').value = forma.tipo;
    toggleBandeira();
    
    if (forma.bandeira) {
        document.getElementById('bandeira').value = forma.bandeira;
    }
    
    document.getElementById('taxa_percentual').value = parseFloat(forma.taxa_percentual).toFixed(2).replace('.', ',');
    document.getElementById('taxa_fixa').value = parseFloat(forma.taxa_fixa).toFixed(2).replace('.', ',');
    document.getElementById('ativo').checked = forma.ativo == 1;
}

function deleteForma(id, estabelecimentoId) {
    if (confirm('Tem certeza que deseja excluir esta forma de pagamento?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="${id}">
            <input type="hidden" name="estabelecimento_id" value="${estabelecimentoId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Fechar modal ao clicar fora
window.onclick = function(event) {
    const modal = document.getElementById('formaModal');
    if (event.target == modal) {
        closeModal();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
