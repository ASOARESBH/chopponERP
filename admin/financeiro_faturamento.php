<?php
/**
 * ========================================
 * FINANCEIRO - FATURAMENTO UNIFICADO
 * Visualização de todas as faturas (Stripe e Cora)
 * Versão: 1.0
 * Data: 2025-12-04
 * ========================================
 */

$page_title = 'Financeiro - Faturamento';
$current_page = 'financeiro_faturamento';

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/RoyaltiesManagerV2.php';

$conn = getDBConnection();
$royaltiesManager = new RoyaltiesManagerV2($conn);

// ===== PROCESSAMENTO DE AÇÕES =====

$success = '';
$error = '';

// Verificar se foi solicitado refresh de status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'verificar_status') {
        $faturamento_id = intval($_POST['faturamento_id']);
        $resultado = $royaltiesManager->verificarStatusFaturamento($faturamento_id);
        
        if ($resultado['success']) {
            $success = 'Status verificado com sucesso';
        } else {
            $error = $resultado['error'] ?? 'Erro ao verificar status';
        }
    } elseif ($_POST['action'] === 'polling_automatico') {
        $resultado = $royaltiesManager->processarPollingAutomatico();
        
        if ($resultado['success']) {
            $success = "Polling automático: {$resultado['verificados']} verificados, {$resultado['atualizados']} atualizados";
        } else {
            $error = $resultado['error'] ?? 'Erro no polling automático';
        }
    }
}

// ===== BUSCAR DADOS PARA LISTAGEM =====

$filtros = [
    'estabelecimento_id' => $_GET['estabelecimento_id'] ?? null,
    'gateway_type' => $_GET['gateway_type'] ?? null,
    'status' => $_GET['status'] ?? null,
    'data_inicial' => $_GET['data_inicial'] ?? null,
    'data_final' => $_GET['data_final'] ?? null
];

// Se não é admin geral, filtrar pelo estabelecimento do usuário
if (!isAdminGeral()) {
    $estabelecimento_id = getEstabelecimentoId();
    $filtros['estabelecimento_id'] = $estabelecimento_id;
}

$faturamentos = $royaltiesManager->listarFaturamentos($filtros);

// Calcular totais por status e gateway
$totais = [
    'stripe' => ['pending' => 0, 'paid' => 0, 'canceled' => 0],
    'cora' => ['pending' => 0, 'paid' => 0, 'overdue' => 0, 'canceled' => 0],
    'total_pendente' => 0,
    'total_pago' => 0
];

foreach ($faturamentos as $f) {
    if ($f['gateway_type'] === 'stripe') {
        if (isset($totais['stripe'][$f['status']])) {
            $totais['stripe'][$f['status']] += $f['valor'];
        }
    } else {
        if (isset($totais['cora'][$f['status']])) {
            $totais['cora'][$f['status']] += $f['valor'];
        }
    }
    
    if (in_array($f['status'], ['pending', 'awaiting_payment', 'overdue'])) {
        $totais['total_pendente'] += $f['valor'];
    } elseif ($f['status'] === 'paid') {
        $totais['total_pago'] += $f['valor'];
    }
}

// Buscar estabelecimentos para dropdown
if (isAdminGeral()) {
    $stmt = $conn->query("SELECT id, name FROM estabelecimentos WHERE status = 1 ORDER BY name");
    $estabelecimentos = $stmt->fetchAll();
}

// Função para mapear status
function mapearStatusFaturamento($status, $gateway) {
    $status_map = [
        'pending' => ['label' => 'Pendente', 'class' => 'warning'],
        'awaiting_payment' => ['label' => 'Aguardando Pagamento', 'class' => 'info'],
        'paid' => ['label' => 'Pago', 'class' => 'success'],
        'overdue' => ['label' => 'Vencido', 'class' => 'danger'],
        'canceled' => ['label' => 'Cancelado', 'class' => 'secondary'],
        'rejected' => ['label' => 'Rejeitado', 'class' => 'danger'],
        'draft' => ['label' => 'Rascunho', 'class' => 'secondary']
    ];
    
    return $status_map[$status] ?? ['label' => $status, 'class' => 'secondary'];
}

// Função para obter ícone do gateway
function getGatewayIcon($gateway) {
    return $gateway === 'cora' ? '<i class="fas fa-barcode"></i> Boleto' : '<i class="fas fa-credit-card"></i> Stripe';
}

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-header">
                <h1><i class="fas fa-file-invoice-dollar"></i> Faturamento</h1>
                <p class="text-muted">Visualização unificada de faturas e boletos (Stripe e Cora)</p>
            </div>
        </div>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- ===== RESUMO DE TOTAIS ===== -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="card-title text-muted">Total Pendente</h6>
                    <h3 class="text-warning"><?php echo formatMoney($totais['total_pendente']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="card-title text-muted">Total Pago</h6>
                    <h3 class="text-success"><?php echo formatMoney($totais['total_pago']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="card-title text-muted">Stripe</h6>
                    <small class="text-muted">
                        Pendente: <?php echo formatMoney($totais['stripe']['pending']); ?><br>
                        Pago: <?php echo formatMoney($totais['stripe']['paid']); ?>
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="card-title text-muted">Cora</h6>
                    <small class="text-muted">
                        Pendente: <?php echo formatMoney($totais['cora']['pending']); ?><br>
                        Pago: <?php echo formatMoney($totais['cora']['paid']); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== FILTROS ===== -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-filter"></i> Filtros</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <?php if (isAdminGeral()): ?>
                        <div class="col-md-3">
                            <label for="estabelecimento_id" class="form-label">Estabelecimento</label>
                            <select class="form-select" id="estabelecimento_id" name="estabelecimento_id">
                                <option value="">Todos</option>
                                <?php foreach ($estabelecimentos as $est): ?>
                                <option value="<?php echo $est['id']; ?>" <?php echo $filtros['estabelecimento_id'] == $est['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($est['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="col-md-3">
                            <label for="gateway_type" class="form-label">Gateway</label>
                            <select class="form-select" id="gateway_type" name="gateway_type">
                                <option value="">Todos</option>
                                <option value="stripe" <?php echo $filtros['gateway_type'] === 'stripe' ? 'selected' : ''; ?>>Stripe</option>
                                <option value="cora" <?php echo $filtros['gateway_type'] === 'cora' ? 'selected' : ''; ?>>Cora (Boleto)</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">Todos</option>
                                <option value="pending" <?php echo $filtros['status'] === 'pending' ? 'selected' : ''; ?>>Pendente</option>
                                <option value="paid" <?php echo $filtros['status'] === 'paid' ? 'selected' : ''; ?>>Pago</option>
                                <option value="overdue" <?php echo $filtros['status'] === 'overdue' ? 'selected' : ''; ?>>Vencido</option>
                                <option value="canceled" <?php echo $filtros['status'] === 'canceled' ? 'selected' : ''; ?>>Cancelado</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filtrar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== AÇÕES ===== -->
    <div class="row mb-4">
        <div class="col-12">
            <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="polling_automatico">
                <button type="submit" class="btn btn-info">
                    <i class="fas fa-sync-alt"></i> Atualizar Status de Todos
                </button>
            </form>
        </div>
    </div>

    <!-- ===== TABELA DE FATURAMENTOS ===== -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Faturamentos (<?php echo count($faturamentos); ?>)</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Data</th>
                                <th>Tipo</th>
                                <th>Descrição</th>
                                <th>Valor</th>
                                <th>Vencimento</th>
                                <th>Status</th>
                                <th>Última Verificação</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($faturamentos)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox"></i> Nenhum faturamento encontrado
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($faturamentos as $f): ?>
                                <?php $status_info = mapearStatusFaturamento($f['status'], $f['gateway_type']); ?>
                                <tr>
                                    <td>
                                        <small><?php echo formatDateBR($f['data_criacao']); ?></small>
                                    </td>
                                    <td>
                                        <?php echo getGatewayIcon($f['gateway_type']); ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars(substr($f['descricao'], 0, 30)); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($f['gateway_id']); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo formatMoney($f['valor']); ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($f['data_vencimento']): ?>
                                            <small><?php echo formatDateBR($f['data_vencimento']); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $status_info['class']; ?>">
                                            <?php echo $status_info['label']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php if ($f['ultima_verificacao']): ?>
                                                <?php echo formatDateTimeBR($f['ultima_verificacao']); ?>
                                            <?php else: ?>
                                                Nunca
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="verificar_status">
                                                <input type="hidden" name="faturamento_id" value="<?php echo $f['id']; ?>">
                                                <button type="submit" class="btn btn-outline-primary" title="Verificar status">
                                                    <i class="fas fa-sync"></i>
                                                </button>
                                            </form>
                                            
                                            <?php if ($f['gateway_type'] === 'cora'): ?>
                                                <a href="<?php echo SITE_URL; ?>/admin/ajax/gerar_boleto_link.php?faturamento_id=<?php echo $f['id']; ?>" 
                                                   class="btn btn-outline-success" target="_blank" title="Ver Boleto">
                                                    <i class="fas fa-barcode"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="#" class="btn btn-outline-secondary" title="Link Stripe" onclick="alert('URL: ' + '<?php echo htmlspecialchars($f['gateway_id']); ?>'); return false;">
                                                    <i class="fas fa-link"></i>
                                                </a>
                                            <?php endif; ?>
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

</div>

<?php require_once '../includes/footer.php'; ?>
