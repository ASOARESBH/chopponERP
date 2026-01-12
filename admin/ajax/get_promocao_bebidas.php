<?php
/**
 * AJAX - Buscar bebidas de uma promoção
 */

require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/promocoes.php';

requireAuth();

header('Content-Type: application/json');

$promocao_id = $_GET['id'] ?? null;

if (!$promocao_id) {
    echo json_encode([]);
    exit;
}

try {
    $bebidas_ids = getPromocaoBebidasIds($promocao_id);
    echo json_encode($bebidas_ids);
} catch (Exception $e) {
    echo json_encode([]);
}
