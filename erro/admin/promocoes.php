<?php
$page_title = 'Promoções';
$current_page = 'promocoes';

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';
require_once '../includes/promocoes.php';

// Verificar permissão de acesso
requirePagePermission('promocoes', 'view');

$conn = getDBConnection();
$success = '';
$error = '';

// Obter estabelecimento do usuário
$estabelecimento_id = null;
if (!isAdminGeral()) {
    $stmt = $conn->prepare("SELECT estabelecimento_id FROM user_estabelecimento WHERE user_id = ? AND status = 1 LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch();
    $estabelecimento_id = $result['estabelecimento_id'] ?? null;
}

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' && hasPagePermission('promocoes', 'create')) {
        $data = [
            'estabelecimento_id' => isAdminGeral() ? $_POST['estabelecimento_id'] : $estabelecimento_id,
            'nome' => sanitize($_POST['nome']),
            'descricao' => sanitize($_POST['descricao']),
            'data_inicio' => $_POST['data_inicio'],
            'data_fim' => $_POST['data_fim'],
            'tipo_regra' => $_POST['tipo_regra'],
            'cupons' => $_POST['tipo_regra'] === 'cupom' ? sanitize($_POST['cupons']) : null,
            'cashback_valor' => $_POST['tipo_regra'] === 'cashback' ? $_POST['cashback_valor'] : null,
            'cashback_ml' => $_POST['tipo_regra'] === 'cashback' ? $_POST['cashback_ml'] : null,
            'status' => $_POST['status'] ?? 1
        ];
        
        $promocao_id = createPromocao($data);
        
        if ($promocao_id && isset($_POST['bebidas'])) {
            vincularBebidasPromocao($promocao_id, $_POST['bebidas']);
            $success = 'Promoção criada com sucesso!';
        } else {
            $error = 'Erro ao criar promoção.';
        }
    }
    
    if ($action === 'update' && hasPagePermission('promocoes', 'edit')) {
        $id = $_POST['id'];
        $data = [
            'estabelecimento_id' => isAdminGeral() ? $_POST['estabelecimento_id'] : $estabelecimento_id,
            'nome' => sanitize($_POST['nome']),
            'descricao' => sanitize($_POST['descricao']),
            'data_inicio' => $_POST['data_inicio'],
            'data_fim' => $_POST['data_fim'],
            'tipo_regra' => $_POST['tipo_regra'],
            'cupons' => $_POST['tipo_regra'] === 'cupom' ? sanitize($_POST['cupons']) : null,
            'cashback_valor' => $_POST['tipo_regra'] === 'cashback' ? $_POST['cashback_valor'] : null,
            'cashback_ml' => $_POST['tipo_regra'] === 'cashback' ? $_POST['cashback_ml'] : null,
            'status' => $_POST['status'] ?? 1
        ];
        
        if (updatePromocao($id, $data)) {
            if (isset($_POST['bebidas'])) {
                vincularBebidasPromocao($id, $_POST['bebidas']);
            }
            $success = 'Promoção atualizada com sucesso!';
        } else {
            $error = 'Erro ao atualizar promoção.';
        }
    }
    
    if ($action === 'delete' && hasPagePermission('promocoes', 'delete')) {
        $id = $_POST['id'];
        if (deletePromocao($id)) {
            $success = 'Promoção excluída com sucesso!';
        } else {
            $error = 'Erro ao excluir promoção.';
        }
    }
}

// Listar promoções
$promocoes = getAllPromocoes($estabelecimento_id);

// Listar estabelecimentos (para admin)
$estabelecimentos = [];
if (isAdminGeral()) {
    $estabelecimentos = $conn->query("SELECT * FROM estabelecimentos ORDER BY name")->fetchAll();
}

// Listar bebidas
$bebidas_query = "SELECT * FROM bebidas";
if ($estabelecimento_id) {
    $bebidas_query .= " WHERE estabelecimento_id = $estabelecimento_id";
}
$bebidas_query .= " ORDER BY name";
$bebidas = $conn->query($bebidas_query)->fetchAll();

require_once '../includes/header.php';
?>

<div class="page-header">
    <h1>🎉 Promoções</h1>
    <?php if (hasPagePermission('promocoes', 'create')): ?>
    <button class="btn btn-primary" onclick="openModal('modalPromocao')">
        <i class="fas fa-plus"></i> Nova Promoção
    </button>
    <?php endif; ?>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Filtros -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body">
        <div class="filters">
            <select id="filtroSituacao" onchange="filtrarPromocoes()">
                <option value="">Todas as Situações</option>
                <option value="ativa">Ativas</option>
                <option value="agendada">Agendadas</option>
                <option value="expirada">Expiradas</option>
            </select>
            
            <select id="filtroRegra" onchange="filtrarPromocoes()">
                <option value="">Todas as Regras</option>
                <option value="todos">Todos os Clientes</option>
                <option value="cupom">Com Cupom</option>
                <option value="cashback">Cashback</option>
            </select>
        </div>
    </div>
</div>

<!-- Lista de Promoções -->
<div class="promocoes-grid">
    <?php foreach ($promocoes as $promo): ?>
    <div class="promocao-card" 
         data-situacao="<?php echo $promo['situacao']; ?>" 
         data-regra="<?php echo $promo['tipo_regra']; ?>">
        <div class="promocao-header">
            <div class="promocao-titulo">
                <h3><?php echo $promo['nome']; ?></h3>
                <span class="badge badge-<?php echo $promo['situacao']; ?>">
                    <?php echo ucfirst($promo['situacao']); ?>
                </span>
            </div>
            <div class="promocao-actions">
                <?php if (hasPagePermission('promocoes', 'edit')): ?>
                <button class="btn-icon" onclick="editPromocao(<?php echo htmlspecialchars(json_encode($promo)); ?>)" title="Editar">
                    <i class="fas fa-edit"></i>
                </button>
                <?php endif; ?>
                
                <?php if (hasPagePermission('promocoes', 'delete')): ?>
                <button class="btn-icon btn-danger" onclick="deletePromocaoConfirm(<?php echo $promo['id']; ?>)" title="Excluir">
                    <i class="fas fa-trash"></i>
                </button>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="promocao-body">
            <p class="promocao-descricao"><?php echo $promo['descricao']; ?></p>
            
            <div class="promocao-info">
                <div class="info-item">
                    <i class="fas fa-calendar"></i>
                    <span><?php echo date('d/m/Y H:i', strtotime($promo['data_inicio'])); ?></span>
                </div>
                <div class="info-item">
                    <i class="fas fa-calendar-check"></i>
                    <span><?php echo date('d/m/Y H:i', strtotime($promo['data_fim'])); ?></span>
                </div>
            </div>
            
            <div class="promocao-regra">
                <?php if ($promo['tipo_regra'] === 'todos'): ?>
                    <span class="regra-badge regra-todos">
                        <i class="fas fa-users"></i> Todos os Clientes
                    </span>
                <?php elseif ($promo['tipo_regra'] === 'cupom'): ?>
                    <span class="regra-badge regra-cupom">
                        <i class="fas fa-ticket-alt"></i> Com Cupom
                    </span>
                    <div class="cupons-list">
                        <?php 
                        $cupons = explode(',', $promo['cupons']);
                        foreach ($cupons as $cupom): ?>
                            <span class="cupom-tag"><?php echo trim($cupom); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($promo['tipo_regra'] === 'cashback'): ?>
                    <span class="regra-badge regra-cashback">
                        <i class="fas fa-coins"></i> Cashback
                    </span>
                    <p class="cashback-info">
                        R$ <?php echo number_format($promo['cashback_valor'], 2, ',', '.'); ?> = 
                        <?php echo $promo['cashback_ml']; ?>ML
                    </p>
                <?php endif; ?>
            </div>
            
            <div class="promocao-footer">
                <div class="footer-item">
                    <i class="fas fa-beer"></i>
                    <span><?php echo $promo['total_bebidas']; ?> bebidas</span>
                </div>
                <?php if (isAdminGeral()): ?>
                <div class="footer-item">
                    <i class="fas fa-store"></i>
                    <span><?php echo $promo['estabelecimento_nome']; ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php if (empty($promocoes)): ?>
    <div class="empty-state">
        <i class="fas fa-tags"></i>
        <h3>Nenhuma promoção cadastrada</h3>
        <p>Crie sua primeira promoção para começar!</p>
    </div>
    <?php endif; ?>
</div>

<!-- Modal de Promoção -->
<div id="modalPromocao" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h2 id="modalTitle">Nova Promoção</h2>
            <span class="close" onclick="closeModal('modalPromocao')">&times;</span>
        </div>
        <form method="POST" id="promocaoForm">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id" id="promocaoId">
            
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group col-md-8">
                        <label for="nome">Nome da Promoção *</label>
                        <input type="text" 
                               id="nome" 
                               name="nome" 
                               class="form-control" 
                               placeholder="Ex: Happy Hour" 
                               required>
                    </div>
                    
                    <div class="form-group col-md-4">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="1">Ativa</option>
                            <option value="0">Inativa</option>
                        </select>
                    </div>
                </div>
                
                <?php if (isAdminGeral()): ?>
                <div class="form-group">
                    <label for="estabelecimento_id">Estabelecimento *</label>
                    <select id="estabelecimento_id" name="estabelecimento_id" class="form-control" required onchange="carregarBebidasEstabelecimento()">
                        <option value="">Selecione...</option>
                        <?php foreach ($estabelecimentos as $estab): ?>
                        <option value="<?php echo $estab['id']; ?>"><?php echo $estab['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="descricao">Descrição</label>
                    <textarea id="descricao" 
                              name="descricao" 
                              class="form-control" 
                              rows="3" 
                              placeholder="Ex: Desconto especial no happy hour para bebidas selecionadas"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="data_inicio">Data e Hora de Início *</label>
                        <input type="datetime-local" 
                               id="data_inicio" 
                               name="data_inicio" 
                               class="form-control" 
                               required>
                    </div>
                    
                    <div class="form-group col-md-6">
                        <label for="data_fim">Data e Hora de Fim *</label>
                        <input type="datetime-local" 
                               id="data_fim" 
                               name="data_fim" 
                               class="form-control" 
                               required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="tipo_regra">Regra de Aplicação *</label>
                    <select id="tipo_regra" name="tipo_regra" class="form-control" required onchange="toggleRegraFields()">
                        <option value="todos">Todos os Clientes do Estabelecimento</option>
                        <option value="cupom">Clientes com Cupom</option>
                        <option value="cashback">Troca de Cashback</option>
                    </select>
                </div>
                
                <!-- Campo de Cupons -->
                <div id="camposCupom" class="regra-fields" style="display: none;">
                    <div class="form-group">
                        <label for="cupons">Cupons Válidos *</label>
                        <input type="text" 
                               id="cupons" 
                               name="cupons" 
                               class="form-control" 
                               placeholder="Ex: #cupom24h, #cupomNatal">
                        <small class="form-text">Separe múltiplos cupons com vírgula. Use # no início.</small>
                    </div>
                </div>
                
                <!-- Campos de Cashback -->
                <div id="camposCashback" class="regra-fields" style="display: none;">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="cashback_valor">Valor em Cashback (R$) *</label>
                            <input type="number" 
                                   id="cashback_valor" 
                                   name="cashback_valor" 
                                   class="form-control" 
                                   step="0.01" 
                                   placeholder="Ex: 100.00">
                            <small class="form-text">Valor necessário para trocar</small>
                        </div>
                        
                        <div class="form-group col-md-6">
                            <label for="cashback_ml">ML Liberados *</label>
                            <input type="number" 
                                   id="cashback_ml" 
                                   name="cashback_ml" 
                                   class="form-control" 
                                   placeholder="Ex: 100">
                            <small class="form-text">ML liberados por troca</small>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Exemplo: A cada R$ 100,00 em cashback, o cliente ganha 100ML grátis
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Bebidas da Promoção *</label>
                    <div class="bebidas-selection" id="bebidasSelection">
                        <?php foreach ($bebidas as $bebida): ?>
                        <label class="checkbox-card">
                            <input type="checkbox" 
                                   name="bebidas[]" 
                                   value="<?php echo $bebida['id']; ?>"
                                   data-estabelecimento="<?php echo $bebida['estabelecimento_id']; ?>">
                            <div class="checkbox-content">
                                <?php if ($bebida['image']): ?>
                                <img src="<?php echo SITE_URL; ?>/uploads/bebidas/<?php echo $bebida['image']; ?>" alt="<?php echo $bebida['name']; ?>">
                                <?php else: ?>
                                <div class="no-image"><i class="fas fa-beer"></i></div>
                                <?php endif; ?>
                                <div class="bebida-info">
                                    <strong><?php echo $bebida['name']; ?></strong>
                                    <span>R$ <?php echo number_format($bebida['valor'], 2, ',', '.'); ?></span>
                                    <?php if ($bebida['valor_promo']): ?>
                                    <span class="promo">→ R$ <?php echo number_format($bebida['valor_promo'], 2, ',', '.'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalPromocao')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar Promoção</button>
            </div>
        </form>
    </div>
</div>

<style>
.promocoes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.promocao-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.promocao-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.promocao-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.promocao-titulo h3 {
    margin: 0 0 8px 0;
    font-size: 1.3rem;
}

.badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.badge-ativa {
    background-color: #28a745;
    color: white;
}

.badge-agendada {
    background-color: #ffc107;
    color: #333;
}

.badge-expirada {
    background-color: #6c757d;
    color: white;
}

.promocao-actions {
    display: flex;
    gap: 8px;
}

.btn-icon {
    background: rgba(255,255,255,0.2);
    border: none;
    color: white;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    cursor: pointer;
    transition: background 0.3s ease;
}

.btn-icon:hover {
    background: rgba(255,255,255,0.3);
}

.btn-icon.btn-danger:hover {
    background: #dc3545;
}

.promocao-body {
    padding: 20px;
}

.promocao-descricao {
    color: #666;
    margin-bottom: 15px;
    line-height: 1.5;
}

.promocao-info {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e0e0e0;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #666;
    font-size: 14px;
}

.info-item i {
    color: #667eea;
}

.promocao-regra {
    margin-bottom: 15px;
}

.regra-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 10px;
}

.regra-todos {
    background-color: #e3f2fd;
    color: #1976d2;
}

.regra-cupom {
    background-color: #fff3e0;
    color: #f57c00;
}

.regra-cashback {
    background-color: #e8f5e9;
    color: #388e3c;
}

.cupons-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
}

.cupom-tag {
    background-color: #f5f5f5;
    color: #333;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-family: monospace;
}

.cashback-info {
    color: #666;
    font-size: 14px;
    margin-top: 8px;
}

.promocao-footer {
    display: flex;
    justify-content: space-between;
    padding-top: 15px;
    border-top: 1px solid #e0e0e0;
}

.footer-item {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #666;
    font-size: 13px;
}

.footer-item i {
    color: #667eea;
}

.filters {
    display: flex;
    gap: 15px;
}

.filters select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
}

.bebidas-selection {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 10px;
    max-height: 400px;
    overflow-y: auto;
    padding: 10px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
}

.checkbox-card {
    cursor: pointer;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    padding: 10px;
    transition: all 0.3s ease;
}

.checkbox-card:hover {
    border-color: #667eea;
    background-color: #f8f9fa;
}

.checkbox-card input[type="checkbox"] {
    display: none;
}

.checkbox-card input[type="checkbox"]:checked + .checkbox-content {
    border-color: #667eea;
    background-color: #e3f2fd;
}

.checkbox-content {
    display: flex;
    align-items: center;
    gap: 10px;
}

.checkbox-content img,
.checkbox-content .no-image {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 6px;
}

.no-image {
    background-color: #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #999;
}

.bebida-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.bebida-info strong {
    font-size: 14px;
}

.bebida-info span {
    font-size: 12px;
    color: #666;
}

.bebida-info .promo {
    color: #28a745;
    font-weight: 500;
}

.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.empty-state i {
    font-size: 64px;
    margin-bottom: 20px;
}

.modal-large {
    max-width: 900px;
}

.regra-fields {
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
}
</style>

<script>
function toggleRegraFields() {
    const tipoRegra = document.getElementById('tipo_regra').value;
    
    document.getElementById('camposCupom').style.display = 'none';
    document.getElementById('camposCashback').style.display = 'none';
    
    if (tipoRegra === 'cupom') {
        document.getElementById('camposCupom').style.display = 'block';
        document.getElementById('cupons').required = true;
    } else if (tipoRegra === 'cashback') {
        document.getElementById('camposCashback').style.display = 'block';
        document.getElementById('cashback_valor').required = true;
        document.getElementById('cashback_ml').required = true;
    }
}

function editPromocao(promo) {
    openModal('modalPromocao');
    document.getElementById('modalTitle').textContent = 'Editar Promoção';
    document.getElementById('formAction').value = 'update';
    document.getElementById('promocaoId').value = promo.id;
    
    <?php if (isAdminGeral()): ?>
    document.getElementById('estabelecimento_id').value = promo.estabelecimento_id;
    <?php endif; ?>
    
    document.getElementById('nome').value = promo.nome;
    document.getElementById('descricao').value = promo.descricao || '';
    document.getElementById('data_inicio').value = promo.data_inicio.replace(' ', 'T').substring(0, 16);
    document.getElementById('data_fim').value = promo.data_fim.replace(' ', 'T').substring(0, 16);
    document.getElementById('tipo_regra').value = promo.tipo_regra;
    document.getElementById('status').value = promo.status;
    
    if (promo.tipo_regra === 'cupom') {
        document.getElementById('cupons').value = promo.cupons || '';
    } else if (promo.tipo_regra === 'cashback') {
        document.getElementById('cashback_valor').value = promo.cashback_valor || '';
        document.getElementById('cashback_ml').value = promo.cashback_ml || '';
    }
    
    toggleRegraFields();
    
    // Carregar bebidas selecionadas
    fetch('ajax/get_promocao_bebidas.php?id=' + promo.id)
        .then(response => response.json())
        .then(bebidas => {
            document.querySelectorAll('input[name="bebidas[]"]').forEach(checkbox => {
                checkbox.checked = bebidas.includes(parseInt(checkbox.value));
            });
        });
}

function deletePromocaoConfirm(id) {
    if (confirm('Tem certeza que deseja excluir esta promoção?')) {
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

function filtrarPromocoes() {
    const situacao = document.getElementById('filtroSituacao').value;
    const regra = document.getElementById('filtroRegra').value;
    
    document.querySelectorAll('.promocao-card').forEach(card => {
        const cardSituacao = card.dataset.situacao;
        const cardRegra = card.dataset.regra;
        
        const matchSituacao = !situacao || cardSituacao === situacao;
        const matchRegra = !regra || cardRegra === regra;
        
        card.style.display = matchSituacao && matchRegra ? 'block' : 'none';
    });
}

<?php if (isAdminGeral()): ?>
function carregarBebidasEstabelecimento() {
    const estabelecimentoId = document.getElementById('estabelecimento_id').value;
    
    document.querySelectorAll('input[name="bebidas[]"]').forEach(checkbox => {
        const bebidaEstab = checkbox.dataset.estabelecimento;
        const label = checkbox.closest('.checkbox-card');
        
        if (!estabelecimentoId || bebidaEstab == estabelecimentoId) {
            label.style.display = 'block';
        } else {
            label.style.display = 'none';
            checkbox.checked = false;
        }
    });
}
<?php endif; ?>

// Inicializar
toggleRegraFields();
</script>

<?php require_once '../includes/footer.php'; ?>
