<?php
/**
 * AJAX - Buscar permissões de um usuário
 */

require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/permissions.php';

// Apenas Admin Geral pode acessar
requireAdminGeral();

header('Content-Type: application/json');

$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['error' => 'ID do usuário não fornecido']);
    exit;
}

try {
    $permissions = getUserPermissions($user_id);
    echo json_encode($permissions);
} catch (Exception $e) {
    echo json_encode(['error' => 'Erro ao buscar permissões: ' . $e->getMessage()]);
}
