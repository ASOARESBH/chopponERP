<?php
/**
 * API AJAX para gerar e exibir link de boleto
 * Retorna link clicável ou QR Code conforme necessário
 */

require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/RoyaltiesManagerV2.php';

$conn = getDBConnection();
$royaltiesManager = new RoyaltiesManagerV2($conn);

// Verificar se é AJAX
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Se não for AJAX, pode ser uma requisição GET para exibir o boleto
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['faturamento_id'])) {
    $faturamento_id = intval($_GET['faturamento_id']);
    
    // Buscar faturamento
    $stmt = $conn->prepare("
        SELECT f.*, e.name as estabelecimento_nome
        FROM faturamentos f
        JOIN estabelecimentos e ON f.estabelecimento_id = e.id
        WHERE f.id = ?
    ");
    $stmt->execute([$faturamento_id]);
    $faturamento = $stmt->fetch();
    
    if (!$faturamento) {
        die('Faturamento não encontrado');
    }
    
    // Se for Stripe, redirecionar para URL da fatura
    if ($faturamento['gateway_type'] === 'stripe') {
        $metadados = json_decode($faturamento['metadados'], true);
        if (isset($metadados['invoice_url'])) {
            header('Location: ' . $metadados['invoice_url']);
            exit;
        }
    }
    
    // Se for Cora, exibir página com boleto
    if ($faturamento['gateway_type'] === 'cora') {
        $metadados = json_decode($faturamento['metadados'], true);
        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Boleto - <?php echo htmlspecialchars($faturamento['descricao']); ?></title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { background-color: #f5f5f5; padding: 20px; }
                .boleto-container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
                .boleto-header { text-align: center; margin-bottom: 30px; }
                .boleto-info { margin-bottom: 20px; }
                .boleto-info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
                .boleto-info-label { font-weight: bold; color: #666; }
                .boleto-info-value { color: #333; }
                .boleto-actions { margin-top: 30px; text-align: center; }
                .btn-group-vertical { width: 100%; }
                .btn-group-vertical .btn { margin-bottom: 10px; }
                .qrcode { text-align: center; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class="boleto-container">
                <div class="boleto-header">
                    <h2><i class="fas fa-barcode"></i> Boleto Bancário</h2>
                    <p class="text-muted">Cora - Banco Digital</p>
                </div>

                <div class="boleto-info">
                    <div class="boleto-info-row">
                        <span class="boleto-info-label">Descrição:</span>
                        <span class="boleto-info-value"><?php echo htmlspecialchars($faturamento['descricao']); ?></span>
                    </div>
                    <div class="boleto-info-row">
                        <span class="boleto-info-label">Valor:</span>
                        <span class="boleto-info-value"><?php echo formatMoney($faturamento['valor']); ?></span>
                    </div>
                    <div class="boleto-info-row">
                        <span class="boleto-info-label">Vencimento:</span>
                        <span class="boleto-info-value"><?php echo formatDateBR($faturamento['data_vencimento']); ?></span>
                    </div>
                    <div class="boleto-info-row">
                        <span class="boleto-info-label">Estabelecimento:</span>
                        <span class="boleto-info-value"><?php echo htmlspecialchars($faturamento['estabelecimento_nome']); ?></span>
                    </div>
                    <div class="boleto-info-row">
                        <span class="boleto-info-label">Status:</span>
                        <span class="boleto-info-value">
                            <?php
                            $status_map = [
                                'pending' => 'Aguardando Pagamento',
                                'paid' => 'Pago',
                                'overdue' => 'Vencido',
                                'canceled' => 'Cancelado'
                            ];
                            echo $status_map[$faturamento['status']] ?? $faturamento['status'];
                            ?>
                        </span>
                    </div>
                </div>

                <?php if (isset($metadados['barcode']) || isset($metadados['line'])): ?>
                <div class="alert alert-info">
                    <h5>Dados do Boleto</h5>
                    <?php if (isset($metadados['barcode'])): ?>
                    <div class="mb-3">
                        <label class="form-label"><strong>Código de Barras:</strong></label>
                        <div class="input-group">
                            <input type="text" class="form-control font-monospace" value="<?php echo htmlspecialchars($metadados['barcode']); ?>" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="copiarParaClipboard(this)">
                                <i class="fas fa-copy"></i> Copiar
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($metadados['line'])): ?>
                    <div class="mb-3">
                        <label class="form-label"><strong>Linha Digitável:</strong></label>
                        <div class="input-group">
                            <input type="text" class="form-control font-monospace" value="<?php echo htmlspecialchars($metadados['line']); ?>" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="copiarParaClipboard(this)">
                                <i class="fas fa-copy"></i> Copiar
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if (isset($metadados['qrcode'])): ?>
                <div class="qrcode">
                    <h5>QR Code PIX</h5>
                    <img src="<?php echo htmlspecialchars($metadados['qrcode']); ?>" alt="QR Code PIX" style="max-width: 300px; height: auto;">
                </div>
                <?php endif; ?>

                <div class="boleto-actions">
                    <button class="btn btn-primary btn-lg" onclick="window.print()">
                        <i class="fas fa-print"></i> Imprimir Boleto
                    </button>
                    <button class="btn btn-secondary btn-lg" onclick="window.close()">
                        <i class="fas fa-times"></i> Fechar
                    </button>
                </div>
            </div>

            <script>
                function copiarParaClipboard(button) {
                    const input = button.previousElementSibling;
                    input.select();
                    document.execCommand('copy');
                    
                    const originalText = button.innerHTML;
                    button.innerHTML = '<i class="fas fa-check"></i> Copiado!';
                    setTimeout(() => {
                        button.innerHTML = originalText;
                    }, 2000);
                }
            </script>
        </body>
        </html>
        <?php
        exit;
    }
}

// Resposta AJAX
header('Content-Type: application/json');

if (!isset($_GET['faturamento_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'faturamento_id não fornecido']);
    exit;
}

$faturamento_id = intval($_GET['faturamento_id']);

// Buscar faturamento
$stmt = $conn->prepare("SELECT * FROM faturamentos WHERE id = ?");
$stmt->execute([$faturamento_id]);
$faturamento = $stmt->fetch();

if (!$faturamento) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Faturamento não encontrado']);
    exit;
}

// Preparar resposta conforme o tipo de gateway
if ($faturamento['gateway_type'] === 'cora') {
    $metadados = json_decode($faturamento['metadados'], true);
    
    echo json_encode([
        'success' => true,
        'gateway' => 'cora',
        'tipo' => 'boleto',
        'url' => SITE_URL . '/admin/ajax/gerar_boleto_link.php?faturamento_id=' . $faturamento_id,
        'boleto_id' => $faturamento['gateway_id'],
        'barcode' => $metadados['barcode'] ?? null,
        'line' => $metadados['line'] ?? null,
        'qrcode' => $metadados['qrcode'] ?? null,
        'valor' => $faturamento['valor'],
        'vencimento' => $faturamento['data_vencimento'],
        'status' => $faturamento['status']
    ]);
} elseif ($faturamento['gateway_type'] === 'stripe') {
    $metadados = json_decode($faturamento['metadados'], true);
    
    echo json_encode([
        'success' => true,
        'gateway' => 'stripe',
        'tipo' => 'link',
        'url' => $metadados['invoice_url'] ?? null,
        'invoice_id' => $faturamento['gateway_id'],
        'valor' => $faturamento['valor'],
        'vencimento' => $faturamento['data_vencimento'],
        'status' => $faturamento['status']
    ]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Gateway não suportado']);
}
?>
