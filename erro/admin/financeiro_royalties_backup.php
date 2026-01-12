<?php
$page_title = 'Financeiro - Royalties';
$current_page = 'financeiro_royalties';

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/cora_api.php';

$conn = getDBConnection();

// Configurações da API Cora (devem ser definidas no config.php ou em variáveis de ambiente)
// IMPORTANTE: Estas credenciais devem ser obtidas no aplicativo Cora
define('CORA_CLIENT_ID', getenv('CORA_CLIENT_ID') ?: '');
define('CORA_CERTIFICATE_PATH', getenv('CORA_CERTIFICATE_PATH') ?: __DIR__ . '/../certs/cora_certificate.pem');
define('CORA_PRIVATE_KEY_PATH', getenv('CORA_PRIVATE_KEY_PATH') ?: __DIR__ . '/../certs/cora_private_key.key');
define('CORA_ENVIRONMENT', getenv('CORA_ENVIRONMENT') ?: 'stage'); // 'stage' ou 'production'

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            $action = $_POST['action'];
            
            if ($action === 'add') {
                // Validar permissões
                $estabelecimento_id = isAdminGeral() ? intval($_POST['estabelecimento_id']) : getEstabelecimentoId();
                
                $periodo_inicial = $_POST['periodo_inicial'];
                $periodo_final = $_POST['periodo_final'];
                $descricao = sanitize($_POST['descricao']);
                $valor_faturamento_bruto = numberToFloat($_POST['valor_faturamento_bruto']);
                $percentual_royalties = 7.00; // Fixo em 7%
                $valor_royalties = $valor_faturamento_bruto * ($percentual_royalties / 100);
                $observacoes = !empty($_POST['observacoes']) ? sanitize($_POST['observacoes']) : null;
                
                // Validações
                if (strtotime($periodo_final) < strtotime($periodo_inicial)) {
                    throw new Exception('A data final deve ser maior ou igual à data inicial');
                }
                
                if ($valor_faturamento_bruto < 0) {
                    throw new Exception('O valor do faturamento bruto deve ser positivo');
                }
                
                // Inserir royalty
                $stmt = $conn->prepare("
                    INSERT INTO royalties 
                    (estabelecimento_id, periodo_inicial, periodo_final, descricao, 
                     valor_faturamento_bruto, percentual_royalties, valor_royalties, 
                     observacoes, created_by, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente')
                ");
                $stmt->execute([
                    $estabelecimento_id, 
                    $periodo_inicial, 
                    $periodo_final, 
                    $descricao,
                    $valor_faturamento_bruto, 
                    $percentual_royalties, 
                    $valor_royalties,
                    $observacoes,
                    $_SESSION['user_id']
                ]);
                
                $royalty_id = $conn->lastInsertId();
                
                // Registrar no histórico
                $stmt = $conn->prepare("
                    INSERT INTO royalties_historico (royalty_id, acao, descricao, user_id)
                    VALUES (?, 'criacao', 'Royalty criado manualmente', ?)
                ");
                $stmt->execute([$royalty_id, $_SESSION['user_id']]);
                
                $_SESSION['success'] = 'Royalty cadastrado com sucesso!';
                
            } elseif ($action === 'gerar_boleto') {
                $royalty_id = intval($_POST['royalty_id']);
                
                // Buscar dados do royalty
                $stmt = $conn->prepare("
                    SELECT r.*, e.name as estabelecimento_nome, e.cnpj, e.email,
                           e.address, e.number, e.district, e.city, e.state, e.zip_code
                    FROM royalties r
                    INNER JOIN estabelecimentos e ON r.estabelecimento_id = e.id
                    WHERE r.id = ?
                ");
                $stmt->execute([$royalty_id]);
                $royalty = $stmt->fetch();
                
                if (!$royalty) {
                    throw new Exception('Royalty não encontrado');
                }
                
                if ($royalty['status'] !== 'pendente') {
                    throw new Exception('Boleto já foi gerado para este royalty');
                }
                
                // Validar valor mínimo (R$ 5,00)
                if ($royalty['valor_royalties'] < 5.00) {
                    throw new Exception('Valor mínimo para emissão de boleto é R$ 5,00');
                }
                
                // Calcular data de vencimento (30 dias a partir de hoje)
                $data_vencimento = date('Y-m-d', strtotime('+30 days'));
                
                // Preparar dados para API Cora
                $boleto_data = [
                    'code' => 'ROYALTY-' . $royalty_id,
                    'customer' => [
                        'name' => $royalty['estabelecimento_nome'],
                        'email' => $royalty['email'] ?? '',
                        'document' => [
                            'identity' => preg_replace('/[^0-9]/', '', $royalty['cnpj']),
                            'type' => 'CNPJ'
                        ]
                    ],
                    'services' => [
                        [
                            'name' => 'Royalties',
                            'description' => substr($royalty['descricao'], 0, 100),
                            'amount' => intval($royalty['valor_royalties'] * 100) // Converter para centavos
                        ]
                    ],
                    'payment_terms' => [
                        'due_date' => $data_vencimento
                    ],
                    'payment_forms' => ['BANK_SLIP', 'PIX']
                ];
                
                // Adicionar endereço se disponível
                if (!empty($royalty['address'])) {
                    $boleto_data['customer']['address'] = [
                        'street' => $royalty['address'],
                        'number' => $royalty['number'] ?? 'S/N',
                        'district' => $royalty['district'] ?? '',
                        'city' => $royalty['city'] ?? '',
                        'state' => $royalty['state'] ?? '',
                        'complement' => '',
                        'zip_code' => preg_replace('/[^0-9]/', '', $royalty['zip_code'] ?? '')
                    ];
                }
                
                // Emitir boleto via API Cora
                $cora = new CoraAPI(
                    CORA_CLIENT_ID,
                    CORA_CERTIFICATE_PATH,
                    CORA_PRIVATE_KEY_PATH,
                    CORA_ENVIRONMENT
                );
                
                $resultado = $cora->emitirBoleto($boleto_data);
                
                if (!$resultado['success']) {
                    throw new Exception('Erro ao gerar boleto: ' . $resultado['error']);
                }
                
                $boleto = $resultado['data'];
                
                // Atualizar royalty com dados do boleto
                $stmt = $conn->prepare("
                    UPDATE royalties 
                    SET status = 'boleto_gerado',
                        boleto_id = ?,
                        boleto_linha_digitavel = ?,
                        boleto_codigo_barras = ?,
                        boleto_qrcode_pix = ?,
                        boleto_data_vencimento = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $boleto['id'],
                    $boleto['payment_options']['bank_slip']['digitable_line'] ?? null,
                    $boleto['payment_options']['bank_slip']['barcode'] ?? null,
                    $boleto['payment_options']['pix']['qr_code'] ?? null,
                    $data_vencimento,
                    $royalty_id
                ]);
                
                // Criar conta a pagar
                $stmt = $conn->prepare("
                    INSERT INTO contas_pagar 
                    (estabelecimento_id, descricao, tipo, valor, data_vencimento, 
                     codigo_barras, link_pagamento, observacoes, status)
                    VALUES (?, ?, 'Royalties', ?, ?, ?, ?, ?, 'pendente')
                ");
                $stmt->execute([
                    $royalty['estabelecimento_id'],
                    'Royalties - ' . $royalty['descricao'],
                    $royalty['valor_royalties'],
                    $data_vencimento,
                    $boleto['payment_options']['bank_slip']['barcode'] ?? null,
                    null, // link_pagamento
                    'Boleto ID Cora: ' . $boleto['id']
                ]);
                
                $conta_pagar_id = $conn->lastInsertId();
                
                // Vincular conta a pagar ao royalty
                $stmt = $conn->prepare("UPDATE royalties SET conta_pagar_id = ? WHERE id = ?");
                $stmt->execute([$conta_pagar_id, $royalty_id]);
                
                // Registrar no histórico
                $stmt = $conn->prepare("
                    INSERT INTO royalties_historico (royalty_id, acao, descricao, dados_json, user_id)
                    VALUES (?, 'geracao_boleto', 'Boleto gerado via API Cora', ?, ?)
                ");
                $stmt->execute([
                    $royalty_id,
                    json_encode($boleto),
                    $_SESSION['user_id']
                ]);
                
                $_SESSION['success'] = 'Boleto gerado com sucesso e conta a pagar criada!';
                
            } elseif ($action === 'delete') {
                $royalty_id = intval($_POST['royalty_id']);
                
                // Verificar se pode excluir
                $stmt = $conn->prepare("SELECT status FROM royalties WHERE id = ?");
                $stmt->execute([$royalty_id]);
                $royalty = $stmt->fetch();
                
                if ($royalty['status'] === 'pago') {
                    throw new Exception('Não é possível excluir um royalty já pago');
                }
                
                // Excluir
                $stmt = $conn->prepare("DELETE FROM royalties WHERE id = ?");
                $stmt->execute([$royalty_id]);
                
                $_SESSION['success'] = 'Royalty excluído com sucesso!';
            }
        }
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        Logger::error("Erro ao processar royalty", [
            'error' => $e->getMessage(),
            'user_id' => $_SESSION['user_id']
        ]);
    }
}

// Buscar royalties
if (isAdminGeral()) {
    $stmt = $conn->query("
        SELECT r.*, e.name as estabelecimento_nome,
               u.name as criado_por_nome
        FROM royalties r
        INNER JOIN estabelecimentos e ON r.estabelecimento_id = e.id
        LEFT JOIN users u ON r.created_by = u.id
        ORDER BY r.created_at DESC
    ");
} else {
    $estabelecimento_id = getEstabelecimentoId();
    $stmt = $conn->prepare("
        SELECT r.*, e.name as estabelecimento_nome,
               u.name as criado_por_nome
        FROM royalties r
        INNER JOIN estabelecimentos e ON r.estabelecimento_id = e.id
        LEFT JOIN users u ON r.created_by = u.id
        WHERE r.estabelecimento_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$estabelecimento_id]);
}
$royalties = $stmt->fetchAll();

// Calcular totais
$total_pendente = 0;
$total_boleto_gerado = 0;
$total_pago = 0;

foreach ($royalties as $royalty) {
    if ($royalty['status'] === 'pendente') {
        $total_pendente += $royalty['valor_royalties'];
    } elseif ($royalty['status'] === 'boleto_gerado') {
        $total_boleto_gerado += $royalty['valor_royalties'];
    } elseif ($royalty['status'] === 'pago') {
        $total_pago += $royalty['valor_royalties'];
    }
}

// Buscar estabelecimentos (para admin)
if (isAdminGeral()) {
    $stmt = $conn->query("SELECT id, name FROM estabelecimentos WHERE status = 1 ORDER BY name");
    $estabelecimentos = $stmt->fetchAll();
}

require_once '../includes/header.php';
?>

<div class="page-header">
    <h1>Royalties</h1>
    <button class="btn btn-primary" onclick="openModalRoyalty()">
        <span>➕</span> Novo Lançamento
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
            <div class="stat-label">Pendentes</div>
            <div class="stat-value"><?php echo formatMoney($total_pendente); ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background-color: #17a2b8;">📄</div>
        <div class="stat-info">
            <div class="stat-label">Boletos Gerados</div>
            <div class="stat-value"><?php echo formatMoney($total_boleto_gerado); ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background-color: #28a745;">✅</div>
        <div class="stat-info">
            <div class="stat-label">Pagos</div>
            <div class="stat-value"><?php echo formatMoney($total_pago); ?></div>
        </div>
    </div>
</div>

<!-- Tabela de royalties -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3>Lançamentos de Royalties</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <?php if (isAdminGeral()): ?>
                        <th>Estabelecimento</th>
                        <?php endif; ?>
                        <th>Período</th>
                        <th>Descrição</th>
                        <th>Faturamento Bruto</th>
                        <th>Royalties (7%)</th>
                        <th>Status</th>
                        <th>Vencimento</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($royalties)): ?>
                        <tr>
                            <td colspan="<?php echo isAdminGeral() ? '8' : '7'; ?>" class="text-center">
                                Nenhum lançamento de royalty encontrado
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($royalties as $royalty): ?>
                        <tr>
                            <?php if (isAdminGeral()): ?>
                            <td><?php echo htmlspecialchars($royalty['estabelecimento_nome']); ?></td>
                            <?php endif; ?>
                            <td>
                                <?php echo formatDateBR($royalty['periodo_inicial']); ?> a 
                                <?php echo formatDateBR($royalty['periodo_final']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($royalty['descricao']); ?></td>
                            <td><?php echo formatMoney($royalty['valor_faturamento_bruto']); ?></td>
                            <td><strong><?php echo formatMoney($royalty['valor_royalties']); ?></strong></td>
                            <td>
                                <?php
                                $status_class = [
                                    'pendente' => 'warning',
                                    'boleto_gerado' => 'info',
                                    'pago' => 'success',
                                    'cancelado' => 'secondary'
                                ];
                                $status_text = [
                                    'pendente' => 'Pendente',
                                    'boleto_gerado' => 'Boleto Gerado',
                                    'pago' => 'Pago',
                                    'cancelado' => 'Cancelado'
                                ];
                                ?>
                                <span class="badge badge-<?php echo $status_class[$royalty['status']]; ?>">
                                    <?php echo $status_text[$royalty['status']]; ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                if ($royalty['boleto_data_vencimento']) {
                                    echo formatDateBR($royalty['boleto_data_vencimento']);
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($royalty['status'] === 'pendente'): ?>
                                    <button class="btn btn-sm btn-success" onclick="gerarBoleto(<?php echo $royalty['id']; ?>)" title="Gerar Boleto">
                                        📄 Gerar Boleto
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteRoyalty(<?php echo $royalty['id']; ?>)" title="Excluir">
                                        🗑️
                                    </button>
                                <?php elseif ($royalty['status'] === 'boleto_gerado'): ?>
                                    <button class="btn btn-sm btn-info" onclick="verBoleto(<?php echo $royalty['id']; ?>)" title="Ver Boleto">
                                        👁️ Ver Boleto
                                    </button>
                                <?php elseif ($royalty['status'] === 'pago'): ?>
                                    <span class="text-success">✅ Pago</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal para adicionar royalty -->
<div id="royaltyModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Novo Lançamento de Royalty</h2>
            <span class="close" onclick="closeModalRoyalty()">&times;</span>
        </div>
        <form method="POST" id="royaltyForm">
            <input type="hidden" name="action" value="add">
            
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
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="periodo_inicial">Período Inicial *</label>
                        <input type="date" name="periodo_inicial" id="periodo_inicial" class="form-control" required>
                    </div>
                    
                    <div class="form-group col-md-6">
                        <label for="periodo_final">Período Final *</label>
                        <input type="date" name="periodo_final" id="periodo_final" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="descricao">Descrição da Cobrança *</label>
                    <input type="text" name="descricao" id="descricao" class="form-control" 
                           placeholder="Ex: Royalties referente ao mês de Dezembro/2024" required maxlength="255">
                </div>
                
                <div class="form-group">
                    <label for="valor_faturamento_bruto">Valor do Faturamento Bruto *</label>
                    <input type="text" name="valor_faturamento_bruto" id="valor_faturamento_bruto" 
                           class="form-control money-input" placeholder="R$ 0,00" required>
                    <small class="form-text text-muted">
                        O sistema calculará automaticamente 7% deste valor para os royalties
                    </small>
                </div>
                
                <div class="form-group">
                    <label>Valor dos Royalties (7%)</label>
                    <div class="form-control-plaintext" id="valor_royalties_display" style="font-size: 1.2em; font-weight: bold; color: #28a745;">
                        R$ 0,00
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="observacoes">Observações</label>
                    <textarea name="observacoes" id="observacoes" class="form-control" rows="3"></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModalRoyalty()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal para ver boleto -->
<div id="boletoModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Dados do Boleto</h2>
            <span class="close" onclick="closeBoletoModal()">&times;</span>
        </div>
        <div class="modal-body" id="boletoContent">
            <!-- Conteúdo será carregado via JavaScript -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeBoletoModal()">Fechar</button>
        </div>
    </div>
</div>

<style>
.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4);
}

.modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 0;
    border: 1px solid #888;
    width: 80%;
    max-width: 800px;
    border-radius: 8px;
}

.modal-header {
    padding: 20px;
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 20px;
    background-color: #f8f9fa;
    border-top: 1px solid #dee2e6;
    text-align: right;
}

.close {
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover,
.close:focus {
    color: black;
}

.badge {
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 0.85em;
}

.badge-warning { background-color: #ffc107; color: #000; }
.badge-info { background-color: #17a2b8; color: #fff; }
.badge-success { background-color: #28a745; color: #fff; }
.badge-secondary { background-color: #6c757d; color: #fff; }
</style>

<script>
function openModalRoyalty() {
    document.getElementById('royaltyForm').reset();
    document.getElementById('valor_royalties_display').textContent = 'R$ 0,00';
    openModal('royaltyModal');
}

function closeModalRoyalty() {
    closeModal('royaltyModal');
    document.getElementById('royaltyForm').reset();
    document.getElementById('valor_royalties_display').textContent = 'R$ 0,00';
}

function closeBoletoModal() {
    closeModal('boletoModal');
}

// Calcular royalties em tempo real
document.getElementById('valor_faturamento_bruto').addEventListener('input', function(e) {
    let valor = e.target.value.replace(/[^\d,]/g, '');
    valor = valor.replace(',', '.');
    let valorFloat = parseFloat(valor) || 0;
    let royalties = valorFloat * 0.07;
    
    document.getElementById('valor_royalties_display').textContent = 
        'R$ ' + royalties.toFixed(2).replace('.', ',');
});

// Formatar input de dinheiro
document.querySelectorAll('.money-input').forEach(function(input) {
    input.addEventListener('blur', function(e) {
        let valor = e.target.value.replace(/[^\d,]/g, '');
        if (valor) {
            valor = valor.replace(',', '.');
            let valorFloat = parseFloat(valor);
            e.target.value = 'R$ ' + valorFloat.toFixed(2).replace('.', ',');
        }
    });
    
    input.addEventListener('focus', function(e) {
        e.target.value = e.target.value.replace('R$ ', '');
    });
});

function gerarBoleto(royaltyId) {
    if (confirm('Deseja gerar o boleto para este royalty? Uma conta a pagar será criada automaticamente.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="gerar_boleto">
            <input type="hidden" name="royalty_id" value="${royaltyId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function verBoleto(royaltyId) {
    // Buscar dados do boleto via AJAX
    fetch(`ajax/get_boleto_royalty.php?id=${royaltyId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let html = `
                    <div class="boleto-info">
                        <p><strong>Linha Digitável:</strong><br>
                        <code style="font-size: 1.1em;">${data.linha_digitavel || 'N/A'}</code></p>
                        
                        <p><strong>Código de Barras:</strong><br>
                        <code>${data.codigo_barras || 'N/A'}</code></p>
                        
                        <p><strong>Data de Vencimento:</strong> ${data.data_vencimento || 'N/A'}</p>
                        
                        <p><strong>Valor:</strong> ${data.valor || 'N/A'}</p>
                        
                        ${data.qrcode_pix ? `
                        <p><strong>QR Code Pix:</strong><br>
                        <img src="data:image/png;base64,${data.qrcode_pix}" style="max-width: 200px;"></p>
                        ` : ''}
                    </div>
                `;
                document.getElementById('boletoContent').innerHTML = html;
                openModal('boletoModal');
            } else {
                alert('Erro ao carregar dados do boleto: ' + data.error);
            }
        })
        .catch(error => {
            alert('Erro ao carregar dados do boleto');
            console.error(error);
        });
}

function deleteRoyalty(royaltyId) {
    if (confirm('Tem certeza que deseja excluir este royalty?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="royalty_id" value="${royaltyId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Fechar modal ao clicar fora
window.onclick = function(event) {
    const modal = document.getElementById('royaltyModal');
    const boletoModal = document.getElementById('boletoModal');
    if (event.target == modal) {
        closeModal();
    }
    if (event.target == boletoModal) {
        closeBoletoModal();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
