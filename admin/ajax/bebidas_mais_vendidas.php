<?php
/**
 * AJAX - Bebidas Mais Vendidas
 */

header('Content-Type: application/json');
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$periodo = $_POST['periodo'] ?? 'semanal';
$conn = getDBConnection();

// Definir intervalo de datas
switch ($periodo) {
    case 'diario':
        $data_inicio = date('Y-m-d');
        break;
    case 'semanal':
        $data_inicio = date('Y-m-d', strtotime('-7 days'));
        break;
    case 'mensal':
        $data_inicio = date('Y-m-d', strtotime('-30 days'));
        break;
    default:
        $data_inicio = date('Y-m-d', strtotime('-7 days'));
}

// Buscar bebidas mais vendidas
if (isAdminGeral()) {
    $stmt = $conn->prepare("
        SELECT b.name as bebida, COUNT(*) as total
        FROM `order` o
        INNER JOIN bebidas b ON o.bebida_id = b.id
        WHERE o.checkout_status = 'SUCCESSFUL'
        AND DATE(o.created_at) >= ?
        GROUP BY o.bebida_id
        ORDER BY total DESC
        LIMIT 6
    ");
    $stmt->execute([$data_inicio]);
} else {
    $estabelecimento_id = getEstabelecimentoId();
    $stmt = $conn->prepare("
        SELECT b.name as bebida, COUNT(*) as total
        FROM `order` o
        INNER JOIN bebidas b ON o.bebida_id = b.id
        WHERE o.checkout_status = 'SUCCESSFUL'
        AND DATE(o.created_at) >= ?
        AND o.estabelecimento_id = ?
        GROUP BY o.bebida_id
        ORDER BY total DESC
        LIMIT 6
    ");
    $stmt->execute([$data_inicio, $estabelecimento_id]);
}

$result = $stmt->fetchAll();

echo json_encode($result);
