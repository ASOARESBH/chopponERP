<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'ID não fornecido']);
    exit;
}

$estabelecimento_id = intval($_GET['id']);

try {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT email_alerta as email, document as cnpj, name FROM estabelecimentos WHERE id = ?");
    $stmt->execute([$estabelecimento_id]);
    $result = $stmt->fetch();
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'email' => $result['email'],
            'cnpj' => $result['cnpj'],
            'name' => $result['name']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Estabelecimento não encontrado'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
