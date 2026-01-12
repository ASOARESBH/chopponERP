<?php
$page_title = 'Pedidos';
$current_page = 'pedidos';

require_once '../includes/config.php';
require_once '../includes/auth.php';

$conn = getDBConnection();

// Filtros
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$status = $_GET['status'] ?? '';
$metodo = $_GET['metodo'] ?? '';

// Construir query
$where = ["DATE(o.created_at) BETWEEN ? AND ?"];
$params = [$data_inicio, $data_fim];

if (!isAdminGeral()) {
    $where[] = "o.estabelecimento_id = ?";
    $params[] = getEstabelecimentoId();
}

if (!empty($status)) {
    $where[] = "o.checkout_status = ?";
    $params[] = $status;
}

if (!empty($metodo)) {
    $where[] = "o.method = ?";
    $params[] = $metodo;
}

$where_clause = implode(' AND ', $where);

// Buscar pedidos
$stmt = $conn->prepare("
    SELECT o.*, 
           b.name as bebida_name,
           e.name as estabelecimento_name,
           t.android_id
    FROM `order` o
    INNER JOIN bebidas b ON o.bebida_id = b.id
    INNER JOIN estabelecimentos e ON o.estabelecimento_id = e.id
    INNER JOIN tap t ON o.tap_id = t.id
    WHERE $where_clause
    ORDER BY o.created_at DESC
");
$stmt->execute($params);
$pedidos = $stmt->fetchAll();

// Estatísticas
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_pedidos,
        SUM(CASE WHEN checkout_status = 'SUCCESSFUL' THEN valor ELSE 0 END) as total_vendas,
        SUM(CASE WHEN checkout_status = 'SUCCESSFUL' THEN quantidade ELSE 0 END) as total_litros
    FROM `order` o
    WHERE $where_clause
");
$stmt->execute($params);
$stats = $stmt->fetch();

require_once '../includes/header.php';
?>

<div class="page-header">
    <h1>Relatório de Pedidos</h1>
</div>

<!-- Filtros -->
<div class="card">
    <div class="card-body">
        <form method="GET" class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label for="data_inicio">Data Início</label>
                    <input type="date" name="data_inicio" id="data_inicio" class="form-control" value="<?php echo $data_inicio; ?>">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label for="data_fim">Data Fim</label>
                    <input type="date" name="data_fim" id="data_fim" class="form-control" value="<?php echo $data_fim; ?>">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label for="status">Status</label>
                    <select name="status" id="status" class="form-control">
                        <option value="">Todos</option>
                        <option value="SUCCESSFUL" <?php echo $status === 'SUCCESSFUL' ? 'selected' : ''; ?>>Sucesso</option>
                        <option value="PENDING" <?php echo $status === 'PENDING' ? 'selected' : ''; ?>>Pendente</option>
                        <option value="CANCELLED" <?php echo $status === 'CANCELLED' ? 'selected' : ''; ?>>Cancelado</option>
                        <option value="FAILED" <?php echo $status === 'FAILED' ? 'selected' : ''; ?>>Falhou</option>
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label for="metodo">Método</label>
                    <select name="metodo" id="metodo" class="form-control">
                        <option value="">Todos</option>
                        <option value="pix" <?php echo $metodo === 'pix' ? 'selected' : ''; ?>>PIX</option>
                        <option value="credit" <?php echo $metodo === 'credit' ? 'selected' : ''; ?>>Crédito</option>
                        <option value="debit" <?php echo $metodo === 'debit' ? 'selected' : ''; ?>>Débito</option>
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary btn-block">Filtrar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Estatísticas -->
<div class="row">
    <div class="col-md-4">
        <div class="stats-card">
            <p>Total de Pedidos</p>
            <h3><?php echo $stats['total_pedidos']; ?></h3>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stats-card">
            <p>Total em Vendas</p>
            <h3><?php echo formatMoney($stats['total_vendas']); ?></h3>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stats-card">
            <p>Total em Litros</p>
            <h3><?php echo number_format($stats['total_litros'] / 1000, 2, ',', '.'); ?> L</h3>
        </div>
    </div>
</div>

<!-- Tabela de Pedidos -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Data/Hora</th>
                        <th>Bebida</th>
                        <?php if (isAdminGeral()): ?>
                        <th>Estabelecimento</th>
                        <?php endif; ?>
                        <th>Quantidade</th>
                        <th>Valor</th>
                        <th>Método</th>
                        <th>Status Pagamento</th>
                        <th>Status Liberação</th>
                        <th>CPF</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pedidos)): ?>
                    <tr>
                        <td colspan="<?php echo isAdminGeral() ? '10' : '9'; ?>" class="text-center">
                            Nenhum pedido encontrado
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($pedidos as $pedido): ?>
                        <tr>
                            <td><?php echo $pedido['id']; ?></td>
                            <td><?php echo formatDateTimeBR($pedido['created_at']); ?></td>
                            <td><?php echo $pedido['bebida_name']; ?></td>
                            <?php if (isAdminGeral()): ?>
                            <td><?php echo $pedido['estabelecimento_name']; ?></td>
                            <?php endif; ?>
                            <td><?php echo $pedido['quantidade']; ?> ml</td>
                            <td><?php echo formatMoney($pedido['valor']); ?></td>
                            <td><?php echo getPaymentMethod($pedido['method']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo getOrderStatusClass($pedido['checkout_status']); ?>">
                                    <?php echo $pedido['checkout_status']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo getOrderStatusClass($pedido['status_liberacao']); ?>">
                                    <?php echo $pedido['status_liberacao']; ?>
                                </span>
                            </td>
                            <td><?php echo $pedido['cpf']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
