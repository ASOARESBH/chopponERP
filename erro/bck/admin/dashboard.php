<?php
$page_title = 'Dashboard';
$current_page = 'dashboard';

require_once '../includes/config.php';
require_once '../includes/auth.php';

$conn = getDBConnection();

// Obter dados do dashboard
if (isAdminGeral()) {
    // Admin vê todos os dados
    $stmt = $conn->query("
        SELECT 
            SUM(valor) as vendas_totais,
            SUM(quantidade) as consumo_total,
            COUNT(*) as total_pedidos
        FROM `order` 
        WHERE checkout_status = 'SUCCESSFUL' 
        AND YEAR(created_at) = YEAR(CURDATE())
    ");
    $stats = $stmt->fetch();
    
    $stmt = $conn->query("
        SELECT 
            SUM(valor) as vendas_mensais,
            SUM(quantidade) as consumo_mensal
        FROM `order` 
        WHERE checkout_status = 'SUCCESSFUL' 
        AND YEAR(created_at) = YEAR(CURDATE())
        AND MONTH(created_at) = MONTH(CURDATE())
    ");
    $stats_mensal = $stmt->fetch();
    
    $stmt = $conn->query("SELECT COUNT(*) as total FROM tap");
    $total_taps = $stmt->fetch()['total'];
    
    $stmt = $conn->query("SELECT COUNT(*) as total FROM tap WHERE status = 1");
    $taps_ativas = $stmt->fetch()['total'];
    
    // TAPs com vencimento próximo
    $stmt = $conn->query("
        SELECT t.*, b.name as bebida_name, e.name as estabelecimento_name,
               (t.volume - t.volume_consumido) as volume_atual
        FROM tap t
        INNER JOIN bebidas b ON t.bebida_id = b.id
        INNER JOIN estabelecimentos e ON t.estabelecimento_id = e.id
        WHERE t.vencimento <= DATE_ADD(CURDATE(), INTERVAL 10 DAY)
        ORDER BY t.vencimento ASC
    ");
    $taps_vencimento = $stmt->fetchAll();
    
} else {
    // Usuário normal vê apenas do seu estabelecimento
    $estabelecimento_id = getEstabelecimentoId();
    
    $stmt = $conn->prepare("
        SELECT 
            SUM(valor) as vendas_totais,
            SUM(quantidade) as consumo_total,
            COUNT(*) as total_pedidos
        FROM `order` 
        WHERE checkout_status = 'SUCCESSFUL' 
        AND YEAR(created_at) = YEAR(CURDATE())
        AND estabelecimento_id = ?
    ");
    $stmt->execute([$estabelecimento_id]);
    $stats = $stmt->fetch();
    
    $stmt = $conn->prepare("
        SELECT 
            SUM(valor) as vendas_mensais,
            SUM(quantidade) as consumo_mensal
        FROM `order` 
        WHERE checkout_status = 'SUCCESSFUL' 
        AND YEAR(created_at) = YEAR(CURDATE())
        AND MONTH(created_at) = MONTH(CURDATE())
        AND estabelecimento_id = ?
    ");
    $stmt->execute([$estabelecimento_id]);
    $stats_mensal = $stmt->fetch();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tap WHERE estabelecimento_id = ?");
    $stmt->execute([$estabelecimento_id]);
    $total_taps = $stmt->fetch()['total'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tap WHERE status = 1 AND estabelecimento_id = ?");
    $stmt->execute([$estabelecimento_id]);
    $taps_ativas = $stmt->fetch()['total'];
    
    // TAPs com vencimento próximo
    $stmt = $conn->prepare("
        SELECT t.*, b.name as bebida_name, e.name as estabelecimento_name,
               (t.volume - t.volume_consumido) as volume_atual
        FROM tap t
        INNER JOIN bebidas b ON t.bebida_id = b.id
        INNER JOIN estabelecimentos e ON t.estabelecimento_id = e.id
        WHERE t.vencimento <= DATE_ADD(CURDATE(), INTERVAL 10 DAY)
        AND t.estabelecimento_id = ?
        ORDER BY t.vencimento ASC
    ");
    $stmt->execute([$estabelecimento_id]);
    $taps_vencimento = $stmt->fetchAll();
}

// Dados para gráfico de vendas mensais (ano atual vs anterior)
$vendas_mensais = [];
$ano_atual = date('Y');
$ano_anterior = $ano_atual - 1;

for ($mes = 1; $mes <= 12; $mes++) {
    if (isAdminGeral()) {
        $stmt = $conn->prepare("
            SELECT SUM(valor) as total 
            FROM `order` 
            WHERE checkout_status = 'SUCCESSFUL'
            AND YEAR(created_at) = ? 
            AND MONTH(created_at) = ?
        ");
        $stmt->execute([$ano_anterior, $mes]);
        $vendas_mensais[$ano_anterior][$mes] = $stmt->fetch()['total'] ?? 0;
        
        $stmt->execute([$ano_atual, $mes]);
        $vendas_mensais[$ano_atual][$mes] = $stmt->fetch()['total'] ?? 0;
    } else {
        $estabelecimento_id = getEstabelecimentoId();
        $stmt = $conn->prepare("
            SELECT SUM(valor) as total 
            FROM `order` 
            WHERE checkout_status = 'SUCCESSFUL'
            AND estabelecimento_id = ?
            AND YEAR(created_at) = ? 
            AND MONTH(created_at) = ?
        ");
        $stmt->execute([$estabelecimento_id, $ano_anterior, $mes]);
        $vendas_mensais[$ano_anterior][$mes] = $stmt->fetch()['total'] ?? 0;
        
        $stmt->execute([$estabelecimento_id, $ano_atual, $mes]);
        $vendas_mensais[$ano_atual][$mes] = $stmt->fetch()['total'] ?? 0;
    }
}

require_once '../includes/header.php';
?>

<div class="page-header">
    <h1>Dashboard</h1>
</div>

<!-- Cards de Estatísticas -->
<div class="row">
    <div class="col-md-3">
        <div class="stats-card">
            <p>Vendas totais</p>
            <h3><?php echo formatMoney($stats['vendas_totais'] ?? 0); ?></h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card">
            <p>Consumo em litros total</p>
            <h3><?php echo number_format(($stats['consumo_total'] ?? 0) / 1000, 2, ',', '.'); ?> L</h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card">
            <p>Consumo em litros mensal</p>
            <h3><?php echo number_format(($stats_mensal['consumo_mensal'] ?? 0) / 1000, 2, ',', '.'); ?> L</h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card white">
            <p>Vendas Mensais</p>
            <h3><?php echo formatMoney($stats_mensal['vendas_mensais'] ?? 0); ?></h3>
        </div>
    </div>
</div>

<!-- TAPs Info -->
<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p>Total de TAPs</p>
                        <h1 class="text-primary"><?php echo $total_taps; ?></h1>
                    </div>
                    <div class="col-md-6">
                        <p>Taps ativas</p>
                        <h1 class="text-primary"><?php echo $taps_ativas; ?></h1>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bebidas Mais Vendidas e TAPs com Vencimento -->
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-between align-center">
                    <h4>Bebidas mais vendidas</h4>
                    <select id="periodo" class="form-control" style="width: 150px;">
                        <option value="diario">Diário</option>
                        <option value="semanal" selected>Semanal</option>
                        <option value="mensal">Mensal</option>
                    </select>
                </div>
            </div>
            <div class="card-body">
                <div id="empty-chart" style="display: none; text-align: center; padding: 40px; color: var(--gray-600);">
                    Nenhum dado encontrado para o período selecionado
                </div>
                <canvas id="donut-chart"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h4>TAPs com vencimento nos próximos 10 dias</h4>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($taps_vencimento)): ?>
                    <div class="empty-state">
                        <p>Nenhuma TAP com vencimento próximo</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($taps_vencimento as $tap): ?>
                        <div class="col-md-6">
                            <div class="tap-card <?php echo getTapStatusClass($tap); ?>">
                                <h3>Tap <?php echo $tap['id']; ?></h3>
                                <div class="tap-info">
                                    <strong>Estabelecimento:</strong> <?php echo $tap['estabelecimento_name']; ?><br>
                                    <strong>Bebida:</strong> <?php echo $tap['bebida_name']; ?><br>
                                    <strong>Vencimento:</strong> <?php echo formatDateBR($tap['vencimento']); ?><br>
                                    <strong>Volume:</strong> <?php echo number_format($tap['volume'], 2, ',', '.'); ?>L
                                </div>
                                <div class="volume-badge">
                                    Volume Consumido: <?php echo number_format($tap['volume_atual'], 2, ',', '.'); ?>L
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Gráfico de Vendas Mensais -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4>Vendas Mensais</h4>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="line-graph"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$extra_js = <<<'JS'
<script>
// Gráfico de Bebidas Mais Vendidas
let donutChart = null;

function loadBebidasMaisVendidas(periodo) {
    $.ajax({
        url: 'ajax/bebidas_mais_vendidas.php',
        type: 'POST',
        data: { periodo: periodo },
        dataType: 'json',
        success: function(response) {
            const ctx = document.getElementById('donut-chart').getContext('2d');
            
            if (donutChart) {
                donutChart.destroy();
            }
            
            if (response.length === 0) {
                $('#empty-chart').show();
                $('#donut-chart').hide();
                return;
            }
            
            $('#empty-chart').hide();
            $('#donut-chart').show();
            
            const labels = response.map(item => item.bebida);
            const data = response.map(item => item.total);
            
            donutChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Vendas',
                        data: data,
                        backgroundColor: [
                            'rgba(255, 159, 64, 0.8)',
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(255, 205, 86, 0.8)',
                            'rgba(75, 192, 192, 0.8)',
                            'rgba(153, 102, 255, 0.8)',
                            'rgba(255, 99, 132, 0.8)'
                        ],
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true
                }
            });
        },
        error: function(xhr) {
            console.error('Erro ao buscar dados:', xhr);
        }
    });
}

// Gráfico de Vendas Mensais
const lineCtx = document.getElementById('line-graph').getContext('2d');
const vendasData = <?php echo json_encode($vendas_mensais); ?>;

const lineChart = new Chart(lineCtx, {
    type: 'bar',
    data: {
        labels: ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 
                 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'],
        datasets: [
            {
                label: '<?php echo $ano_anterior; ?>',
                backgroundColor: 'rgba(255, 159, 64, 0.6)',
                data: [
                    <?php for ($i = 1; $i <= 12; $i++) echo ($vendas_mensais[$ano_anterior][$i] ?? 0) . ','; ?>
                ]
            },
            {
                label: '<?php echo $ano_atual; ?>',
                backgroundColor: 'rgba(54, 162, 235, 1)',
                data: [
                    <?php for ($i = 1; $i <= 12; $i++) echo ($vendas_mensais[$ano_atual][$i] ?? 0) . ','; ?>
                ]
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'R$ ' + value.toFixed(2);
                    }
                }
            }
        }
    }
});

// Carregar bebidas mais vendidas ao iniciar
$(document).ready(function() {
    loadBebidasMaisVendidas('semanal');
    
    $('#periodo').change(function() {
        loadBebidasMaisVendidas($(this).val());
    });
});
</script>
JS;

require_once '../includes/footer.php';
?>
