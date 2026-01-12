<?php
/**
 * AJAX - Obter Estabelecimentos do Usuário
 */

header('Content-Type: application/json');
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$user_id = $_GET['user_id'] ?? 0;

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'user_id inválido']);
    exit;
}

$conn = getDBConnection();
$stmt = $conn->prepare("
    SELECT estabelecimento_id 
    FROM user_estabelecimento 
    WHERE user_id = ? AND status = 1
");
$stmt->execute([$user_id]);
$estabelecimentos = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo json_encode($estabelecimentos);
