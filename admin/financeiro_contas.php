<?php
$page_title = 'Financeiro - Contas a Pagar';
$current_page = 'financeiro_contas';

require_once '../includes/config.php';
require_once '../includes/auth.php';

$conn = getDBConnection();

// Processar ações (adicionar, editar, excluir, marcar como pago)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            $action = $_POST['action'];
            
            if ($action === 'add' || $action === 'edit') {
                $estabelecimento_id = isAdminGeral() ? $_POST['estabelecimento_id'] : getEstabelecimentoId();
                $descricao = sanitize($_POST['descricao']);
                $tipo = sanitize($_POST['tipo']);
                $valor = numberToFloat($_POST['valor']);
                $data_vencimento = $_POST['data_vencimento'];
                $codigo_barras = !empty($_POST['codigo_barras']) ? sanitize($_POST['codigo_barras']) : null;
                $link_pagamento = !empty($_POST['link_pagamento']) ? sanitize($_POST['link_pagamento']) : null;
                $observacoes = !empty($_POST['observacoes']) ? sanitize($_POST['observacoes']) : null;
                
                // Verificar se é conta protegida (de royalties)
                if ($action === 'edit') {
                    $id = intval($_POST['id']);
                    $stmt = $conn->prepare("SELECT valor_protegido, valor FROM contas_pagar WHERE id = ?");
                    $stmt->execute([$id]);
                    $conta_atual = $stmt->fetch();
                    
                    if ($conta_atual && $conta_atual['valor_protegido'] && !isAdminGeral()) {
                        // Restaurar valor original se não for admin geral
                        $valor = $conta_atual['valor'];
                    }
                }
                
                if ($action === 'add') {
                    $stmt = $conn->prepare("
                        INSERT INTO contas_pagar 
                        (estabelecimento_id, descricao, tipo, valor, data_vencimento, codigo_barras, link_pagamento, observacoes, valor_protegido, origem) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, FALSE, 'manual')
                    ");
                    $stmt->execute([$estabelecimento_id, $descricao, $tipo, $valor, $data_vencimento, $codigo_barras, $link_pagamento, $observacoes]);
                    $_SESSION['success'] = 'Conta cadastrada com sucesso!';
                } else {
                    $stmt = $conn->prepare("
                        UPDATE contas_pagar 
                        SET descricao = ?, tipo = ?, valor = ?, data_vencimento = ?, 
                            codigo_barras = ?, link_pagamento = ?, observacoes = ?
                        WHERE id = ? AND estabelecimento_id = ?
                    ");
                    $stmt->execute([$descricao, $tipo, $valor, $data_vencimento, $codigo_barras, $link_pagamento, $observacoes, $id, $estabelecimento_id]);
                    $_SESSION['success'] = 'Conta atualizada com sucesso!';
                }
            } elseif ($action === 'delete') {
                $id = intval($_POST['id']);
                $estabelecimento_id = isAdminGeral() ? $_POST['estabelecimento_id'] : getEstabelecimentoId();
                
                $stmt = $conn->prepare("DELETE FROM contas_pagar WHERE id = ? AND estabelecimento_id = ?");
                $stmt->execute([$id, $estabelecimento_id]);
                $_SESSION['success'] = 'Conta excluída com sucesso!';
            } elseif ($action === 'pagar') {
                $id = intval($_POST['id']);
                $estabelecimento_id = isAdminGeral() ? $_POST['estabelecimento_id'] : getEstabelecimentoId();
                $valor_pago = numberToFloat($_POST['valor_pago']);
                $data_pagamento = $_POST['data_pagamento'];
                
                $stmt = $conn->prepare("
                    UPDATE contas_pagar 
                    SET status = 'pago', data_pagamento = ?, valor_pago = ?
                    WHERE id = ? AND estabelecimento_id = ?
                ");
                $stmt->execute([$data_pagamento, $valor_pago, $id, $estabelecimento_id]);
                $_SESSION['success'] = 'Conta marcada como paga!';
            }
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = 'Erro ao processar ação: ' . $e->getMessage();
    }
}

// Filtros
$filtro_status = isset($_GET['status']) ? $_GET['status'] : 'pendente';
$filtro_mes = isset($_GET['mes']) ? $_GET['mes'] : date('Y-m');

// Buscar contas a pagar
if (isAdminGeral()) {
    $sql = "
        SELECT c.*, e.name as estabelecimento_nome,
               DATEDIFF(c.data_vencimento, CURDATE()) as dias_para_vencer
        FROM contas_pagar c
        INNER JOIN estabelecimentos e ON c.estabelecimento_id = e.id
        WHERE 1=1
    ";
    $params = [];
    
    if ($filtro_status !== 'todos') {
        $sql .= " AND c.status = ?";
        $params[] = $filtro_status;
    }
    
    if ($filtro_mes) {
        $sql .= " AND DATE_FORMAT(c.data_vencimento, '%Y-%m') = ?";
        $params[] = $filtro_mes;
    }
    
    $sql .= " ORDER BY c.data_vencimento ASC, c.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
} else {
    $estabelecimento_id = getEstabelecimentoId();
    $sql = "
        SELECT c.*, e.name as estabelecimento_nome,
               DATEDIFF(c.data_vencimento, CURDATE()) as dias_para_vencer
        FROM contas_pagar c
        INNER JOIN estabelecimentos e ON c.estabelecimento_id = e.id
        WHERE c.estabelecimento_id = ?
    ";
    $params = [$estabelecimento_id];
    
    if ($filtro_status !== 'todos') {
        $sql .= " AND c.status = ?";
        $params[] = $filtro_status;
    }
    
    if ($filtro_mes) {
        $sql .= " AND DATE_FORMAT(c.data_vencimento, '%Y-%m') = ?";
        $params[] = $filtro_mes;
    }
    
    $sql .= " ORDER BY c.data_vencimento ASC, c.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
}
$contas = $stmt->fetchAll();

// Calcular totais
$total_pendente = 0;
$total_pago = 0;
$total_vencido = 0;

foreach ($contas as $conta) {
    if ($conta['status'] === 'pendente') {
        $total_pendente += $conta['valor'];
        if ($conta['dias_para_vencer'] < 0) {
            $total_vencido += $conta['valor'];
        }
    } elseif ($conta['status'] === 'pago') {
        $total_pago += $conta['valor_pago'] ?? $conta['valor'];
    }
}

// Buscar estabelecimentos (para admin)
if (isAdminGeral()) {
    $stmt = $conn->query("SELECT id, name FROM estabelecimentos WHERE status = 1 ORDER BY name");
    $estabelecimentos = $stmt->fetchAll();
}

// Buscar tipos de conta únicos
$stmt = $conn->query("SELECT DISTINCT tipo FROM contas_pagar ORDER BY tipo");
$tipos_conta = $stmt->fetchAll(PDO::FETCH_COLUMN);

require_once '../includes/header.php';
?>

<div class="page-header">
    <h1>Contas a Pagar</h1>
    <button class="btn btn-primary" onclick="openModalConta()">
        <span>➕</span> Nova Conta
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

<!-- Cards de resumo -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background-color: #ffc107;">⏳</div>
        <div class="stat-info">
            <div class="stat-label">Contas Pendentes</div>
            <div class="stat-value"><?php echo formatMoney($total_pendente); ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background-color: #dc3545;">⚠️</div>
        <div class="stat-info">
            <div class="stat-label">Contas Vencidas</div>
            <div class="stat-value"><?php echo formatMoney($total_vencido); ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background-color: #28a745;">✅</div>
        <div class="stat-info">
            <div class="stat-label">Contas Pagas</div>
            <div class="stat-value"><?php echo formatMoney($total_pago); ?></div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card" style="margin-top: 20px;">
    <div class="card-body">
        <form method="GET" class="filter-form">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="status">Status</label>
                    <select name="status" id="status" class="form-control" onchange="this.form.submit()">
                        <option value="todos" <?php echo $filtro_status === 'todos' ? 'selected' : ''; ?>>Todos</option>
                        <option value="pendente" <?php echo $filtro_status === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                        <option value="pago" <?php echo $filtro_status === 'pago' ? 'selected' : ''; ?>>Pago</option>
                        <option value="vencido" <?php echo $filtro_status === 'vencido' ? 'selected' : ''; ?>>Vencido</option>
                        <option value="cancelado" <?php echo $filtro_status === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                    </select>
                </div>
                
                <div class="form-group col-md-4">
                    <label for="mes">Mês de Vencimento</label>
                    <input type="month" name="mes" id="mes" class="form-control" value="<?php echo $filtro_mes; ?>" onchange="this.form.submit()">
                </div>
                
                <div class="form-group col-md-4">
                    <label>&nbsp;</label>
                    <button type="button" class="btn btn-secondary btn-block" onclick="window.location.href='<?php echo $_SERVER['PHP_SELF']; ?>'">
                        Limpar Filtros
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Tabela de contas -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3>Contas Cadastradas</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <?php if (isAdminGeral()): ?>
                        <th>Estabelecimento</th>
                        <?php endif; ?>
                        <th>Descrição</th>
                        <th>Tipo</th>
                        <th>Valor</th>
                        <th>Vencimento</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($contas)): ?>
                        <tr>
                            <td colspan="<?php echo isAdminGeral() ? '7' : '6'; ?>" class="text-center">
                                Nenhuma conta encontrada
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($contas as $conta): ?>
                            <?php
                            $status_class = 'secondary';
                            $status_label = $conta['status'];
                            
                            if ($conta['status'] === 'pago') {
                                $status_class = 'success';
                                $status_label = '✅ Pago';
                            } elseif ($conta['status'] === 'pendente') {
                                if ($conta['dias_para_vencer'] < 0) {
                                    $status_class = 'danger';
                                    $status_label = '⚠️ Vencido';
                                } elseif ($conta['dias_para_vencer'] == 0) {
                                    $status_class = 'warning';
                                    $status_label = '⏰ Vence Hoje';
                                } elseif ($conta['dias_para_vencer'] <= 3) {
                                    $status_class = 'warning';
                                    $status_label = '⏳ Vence em ' . $conta['dias_para_vencer'] . ' dia(s)';
                                } else {
                                    $status_class = 'info';
                                    $status_label = '📅 Pendente';
                                }
                            } elseif ($conta['status'] === 'cancelado') {
                                $status_class = 'dark';
                                $status_label = '❌ Cancelado';
                            }
                            ?>
                            <tr>
                                <?php if (isAdminGeral()): ?>
                                <td><?php echo htmlspecialchars($conta['estabelecimento_nome']); ?></td>
                                <?php endif; ?>
                                <td>
                                    <strong><?php echo htmlspecialchars($conta['descricao']); ?></strong>
                                    <?php if ($conta['observacoes']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($conta['observacoes']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($conta['tipo']); ?></td>
                                <td><?php echo formatMoney($conta['valor']); ?></td>
                                <td><?php echo formatDateBR($conta['data_vencimento']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $status_class; ?>">
                                        <?php echo $status_label; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <?php if ($conta['status'] === 'pendente'): ?>
                                        <button class="btn btn-sm btn-success" onclick='pagarConta(<?php echo json_encode($conta); ?>)' title="Marcar como Pago">
                                            💰 Pagar
                                        </button>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($conta['payment_link_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($conta['payment_link_url']); ?>" target="_blank" class="btn btn-sm btn-primary" title="Abrir Link de Pagamento">
                                            🔗 Link
                                        </a>
                                        <?php endif; ?>
                                        
                                        <button class="btn btn-sm btn-info" onclick='verDetalhes(<?php echo json_encode($conta); ?>)' title="Ver Detalhes">
                                            👁️
                                        </button>
                                        
                                        <?php if ($conta['status'] !== 'pago' && (isAdminGeral() || !$conta['valor_protegido'])): ?>
                                        <button class="btn btn-sm btn-warning" onclick='editConta(<?php echo json_encode($conta); ?>)' title="Editar">
                                            ✏️
                                        </button>
                                        <?php elseif ($conta['valor_protegido'] && !isAdminGeral()): ?>
                                        <button class="btn btn-sm btn-secondary" disabled title="Conta protegida - somente leitura">
                                            🔒
                                        </button>
                                        <?php endif; ?>
                                        
                                        <button class="btn btn-sm btn-danger" onclick="deleteConta(<?php echo $conta['id']; ?>, <?php echo $conta['estabelecimento_id']; ?>)" title="Excluir">
                                            🗑️
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

<!-- Modal para adicionar/editar conta -->
<div id="contaModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Nova Conta a Pagar</h2>
            <span class="close" onclick="closeModalConta()">&times;</span>
        </div>
        <form method="POST" id="contaForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="contaId">
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
                    <label for="descricao">Descrição *</label>
                    <input type="text" name="descricao" id="descricao" class="form-control" required placeholder="Ex: Conta de Luz - Novembro/2025">
                </div>
                
                <div class="form-group">
                    <label for="tipo">Tipo *</label>
                    <input type="text" name="tipo" id="tipo" class="form-control" list="tiposList" required placeholder="Ex: Água, Luz, Aluguel...">
                    <datalist id="tiposList">
                        <option value="Água">
                        <option value="Luz">
                        <option value="Aluguel">
                        <option value="Internet">
                        <option value="Telefone">
                        <option value="Fornecedor">
                        <option value="Impostos">
                        <option value="Salários">
                        <option value="Manutenção">
                        <?php foreach ($tipos_conta as $tipo): ?>
                            <option value="<?php echo htmlspecialchars($tipo); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="valor">Valor (R$) *</label>
                        <input type="text" name="valor" id="valor" class="form-control" required placeholder="Ex: 150,00">
                    </div>
                    
                    <div class="form-group col-md-6">
                        <label for="data_vencimento">Data de Vencimento *</label>
                        <input type="date" name="data_vencimento" id="data_vencimento" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="codigo_barras">Código de Barras</label>
                    <textarea name="codigo_barras" id="codigo_barras" class="form-control" rows="2" placeholder="Cole aqui o código de barras (se houver)"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="link_pagamento">Link de Pagamento</label>
                    <input type="url" name="link_pagamento" id="link_pagamento" class="form-control" placeholder="https://...">
                </div>
                
                <div class="form-group">
                    <label for="observacoes">Observações</label>
                    <textarea name="observacoes" id="observacoes" class="form-control" rows="2" placeholder="Observações adicionais..."></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModalConta()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal para marcar como pago -->
<div id="pagarModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Marcar Conta como Paga</h2>
            <span class="close" onclick="closePagarModal()">&times;</span>
        </div>
        <form method="POST" id="pagarForm">
            <input type="hidden" name="action" value="pagar">
            <input type="hidden" name="id" id="pagarId">
            <input type="hidden" name="estabelecimento_id" id="pagarEstabelecimentoId">
            
            <div class="modal-body">
                <p id="pagarDescricao"></p>
                
                <div class="form-group">
                    <label for="valor_pago">Valor Pago (R$) *</label>
                    <input type="text" name="valor_pago" id="valor_pago" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="data_pagamento">Data do Pagamento *</label>
                    <input type="date" name="data_pagamento" id="data_pagamento" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closePagarModal()">Cancelar</button>
                <button type="submit" class="btn btn-success">Confirmar Pagamento</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal para ver detalhes -->
<div id="detalhesModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Detalhes da Conta</h2>
            <span class="close" onclick="closeDetalhesModal()">&times;</span>
        </div>
        <div class="modal-body" id="detalhesContent">
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeDetalhesModal()">Fechar</button>
        </div>
    </div>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.stat-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-right: 15px;
}

.stat-info {
    flex: 1;
}

.stat-label {
    font-size: 14px;
    color: #666;
    margin-bottom: 5px;
}

.stat-value {
    font-size: 24px;
    font-weight: bold;
    color: #333;
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
    align-items: center;
    justify-content: center;
    overflow-y: auto;
}

.modal-content {
    background-color: #fff;
    padding: 0;
    border-radius: 8px;
    width: 90%;
    max-width: 700px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    margin: 20px;
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
    max-height: 70vh;
    overflow-y: auto;
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

.filter-form .form-row {
    align-items: flex-end;
}

.badge {
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.badge-success { background-color: #28a745; color: white; }
.badge-danger { background-color: #dc3545; color: white; }
.badge-warning { background-color: #ffc107; color: #000; }
.badge-info { background-color: #17a2b8; color: white; }
.badge-secondary { background-color: #6c757d; color: white; }
.badge-dark { background-color: #343a40; color: white; }

.btn-group {
    display: flex;
    gap: 5px;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 12px;
}

.text-muted {
    color: #6c757d;
}
</style>

<script>
function openModalConta() {
    document.getElementById('modalTitle').textContent = 'Nova Conta a Pagar';
    document.getElementById('formAction').value = 'add';
    document.getElementById('contaForm').reset();
    document.getElementById('contaId').value = '';
    openModal('contaModal');
}

function closeModalConta() {
    closeModal('contaModal');
}

function editConta(conta) {
    document.getElementById('modalTitle').textContent = 'Editar Conta a Pagar';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('contaId').value = conta.id;
    
    <?php if (isAdminGeral()): ?>
    document.getElementById('estabelecimento_id').value = conta.estabelecimento_id;
    <?php endif; ?>
    
    document.getElementById('descricao').value = conta.descricao;
    document.getElementById('tipo').value = conta.tipo;
    document.getElementById('valor').value = parseFloat(conta.valor).toFixed(2).replace('.', ',');
    document.getElementById('data_vencimento').value = conta.data_vencimento;
    document.getElementById('codigo_barras').value = conta.codigo_barras || '';
    document.getElementById('link_pagamento').value = conta.payment_link_url || conta.link_pagamento || '';
    document.getElementById('observacoes').value = conta.observacoes || '';
    
    // Desabilitar campo valor se for conta protegida
    <?php if (!isAdminGeral()): ?>
    if (conta.valor_protegido) {
        document.getElementById('valor').readOnly = true;
        document.getElementById('valor').style.backgroundColor = '#e9ecef';
        document.getElementById('valor').title = 'Valor protegido - não pode ser alterado';
    } else {
        document.getElementById('valor').readOnly = false;
        document.getElementById('valor').style.backgroundColor = '';
        document.getElementById('valor').title = '';
    }
    <?php endif; ?>
    
    openModal('contaModal');
}

function deleteConta(id, estabelecimentoId) {
    if (confirm('Tem certeza que deseja excluir esta conta?')) {
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

function pagarConta(conta) {
    openModal('pagarModal');
    document.getElementById('pagarId').value = conta.id;
    document.getElementById('pagarEstabelecimentoId').value = conta.estabelecimento_id;
    document.getElementById('pagarDescricao').innerHTML = `
        <strong>Descrição:</strong> ${conta.descricao}<br>
        <strong>Tipo:</strong> ${conta.tipo}<br>
        <strong>Valor:</strong> R$ ${parseFloat(conta.valor).toFixed(2).replace('.', ',')}
    `;
    document.getElementById('valor_pago').value = parseFloat(conta.valor).toFixed(2).replace('.', ',');
}

function closePagarModal() {
    closeModal('pagarModal');
}

function verDetalhes(conta) {
    
    let html = `
        <div style="line-height: 1.8;">
            <p><strong>Descrição:</strong> ${conta.descricao}</p>
            <p><strong>Tipo:</strong> ${conta.tipo}</p>
            <p><strong>Valor:</strong> R$ ${parseFloat(conta.valor).toFixed(2).replace('.', ',')}</p>
            <p><strong>Data de Vencimento:</strong> ${formatDateBR(conta.data_vencimento)}</p>
            <p><strong>Status:</strong> ${conta.status}</p>
    `;
    
    if (conta.codigo_barras) {
        html += `<p><strong>Código de Barras:</strong><br><code style="background: #f4f4f4; padding: 5px; display: block; margin-top: 5px; word-break: break-all;">${conta.codigo_barras}</code></p>`;
    }
    
    if (conta.link_pagamento) {
        html += `<p><strong>Link de Pagamento:</strong><br><a href="${conta.link_pagamento}" target="_blank">${conta.link_pagamento}</a></p>`;
    }
    
    if (conta.observacoes) {
        html += `<p><strong>Observações:</strong><br>${conta.observacoes}</p>`;
    }
    
    if (conta.data_pagamento) {
        html += `<p><strong>Data de Pagamento:</strong> ${formatDateBR(conta.data_pagamento)}</p>`;
        html += `<p><strong>Valor Pago:</strong> R$ ${parseFloat(conta.valor_pago).toFixed(2).replace('.', ',')}</p>`;
    }
    
    html += '</div>';
    
    document.getElementById('detalhesContent').innerHTML = html;
    openModal('detalhesModal');
}

function closeDetalhesModal() {
    closeModal('detalhesModal');
}

function formatDateBR(date) {
    if (!date) return '';
    const parts = date.split('-');
    return `${parts[2]}/${parts[1]}/${parts[0]}`;
}

// Fechar modais ao clicar fora
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
