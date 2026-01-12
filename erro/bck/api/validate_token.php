<?php
/**
 * API - Validar Token
 * GET /api/validate_token.php
 */

header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/jwt.php';

$headers = getallheaders();
$token = $headers['token'] ?? $headers['Token'] ?? '';

if (empty($token)) {
    http_response_code(401);
    echo json_encode(['valid' => false, 'error' => 'Token não fornecido']);
    exit;
}

$decoded = jwtDecode($token);

if ($decoded !== false) {
    http_response_code(200);
    echo json_encode(['valid' => $decoded]);
} else {
    http_response_code(401);
    echo json_encode(['valid' => false, 'error' => 'Token inválido']);
}
