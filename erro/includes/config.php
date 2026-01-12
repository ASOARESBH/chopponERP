<?php
/**
 * Configurações do Sistema Chopp On Tap - Versão 3.1.0
 * Integrado com padrões do CRM INLAUDO
 * Data: 04/12/2025
 */

// ========================================
// CONFIGURAÇÕES DO BANCO DE DADOS
// ========================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'inlaud99_choppontap');
define('DB_USER', 'inlaud99_admin');
define('DB_PASS', 'Admin259087@');
define('DB_CHARSET', 'utf8mb4');

// ========================================
// CONFIGURAÇÕES DO SISTEMA
// ========================================

define('SITE_NAME', 'Chopp On Tap');
define('SITE_URL', detectSiteURL());
define('SYSTEM_VERSION', 'v3.1.0');
define('DEBUG_MODE', false); // Mudar para true em desenvolvimento

// ========================================
// TIMEZONE
// ========================================

date_default_timezone_set('America/Sao_Paulo');

// ========================================
// FUNÇÃO DE CONEXÃO COM BANCO DE DADOS
// ========================================

function getDBConnection() {
    static $conn = null;
    
    if ($conn === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            Logger::error("Erro de conexão com banco de dados", ['error' => $e->getMessage()]);
            die("Erro de conexão com o banco de dados. Verifique as configurações.");
        }
    }
    
    return $conn;
}

// ========================================
// DETECÇÃO AUTOMÁTICA DE URL
// ========================================

function detectSiteURL() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $base_dir = rtrim($base_dir, '/');
    
    if ($base_dir === '.' || $base_dir === '/') {
        $base_dir = '';
    }
    
    return $protocol . $host . $base_dir;
}

// ========================================
// FUNÇÕES DE FORMATAÇÃO
// ========================================

function formatCNPJ($cnpj) {
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpj);
}

function formatCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
}

function formatTelefone($telefone) {
    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    if (strlen($telefone) == 11) {
        return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $telefone);
    } elseif (strlen($telefone) == 10) {
        return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $telefone);
    }
    return $telefone;
}

function formatCEP($cep) {
    $cep = preg_replace('/[^0-9]/', '', $cep);
    return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $cep);
}

function formatMoney($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function formatDate($data) {
    if (empty($data)) return '';
    try {
        $dt = new DateTime($data);
        return $dt->format('d/m/Y');
    } catch (Exception $e) {
        return '';
    }
}

function formatDateTime($data) {
    if (empty($data)) return '';
    try {
        $dt = new DateTime($data);
        return $dt->format('d/m/Y H:i:s');
    } catch (Exception $e) {
        return '';
    }
}

function dateBRtoMySQL($data) {
    if (empty($data)) return null;
    $partes = explode('/', $data);
    if (count($partes) == 3) {
        return $partes[2] . '-' . $partes[1] . '-' . $partes[0];
    }
    return null;
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function isAjax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getUserType($type) {
    $tipos = [
        1 => 'Admin. Geral',
        2 => 'Admin. Estabelecimento',
        3 => 'Gerente Estabelecimento',
        4 => 'Operador'
    ];
    return $tipos[$type] ?? 'Tipo inválido';
}

function getPaymentMethod($method) {
    $metodos = [
        'pix' => 'PIX',
        'credit' => 'Crédito',
        'debit' => 'Débito',
        'boleto' => 'Boleto',
        'stripe' => 'Stripe',
        'cora' => 'Banco Cora'
    ];
    return $metodos[$method] ?? $method;
}

function getStatusClass($status) {
    $classes = [
        'SUCCESSFUL' => 'success',
        'PENDING' => 'warning',
        'CANCELLED' => 'danger',
        'FAILED' => 'danger',
        'pendente' => 'warning',
        'pago' => 'success',
        'cancelado' => 'danger',
        'boleto_gerado' => 'info',
        'link_gerado' => 'info',
        'enviado' => 'primary'
    ];
    return $classes[$status] ?? 'secondary';
}

function getSessionTime() {
    if (isset($_SESSION['login_time'])) {
        $diff = time() - $_SESSION['login_time'];
        $hours = floor($diff / 3600);
        $minutes = floor(($diff % 3600) / 60);
        return sprintf('%02d:%02d', $hours, $minutes);
    }
    return '00:00';
}

// ========================================
// FUNÇÕES DE AUTENTICAÇÃO
// ========================================

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdminGeral() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] == 1;
}

function getEstabelecimentoId() {
    return $_SESSION['estabelecimento_id'] ?? null;
}

function redirect($url) {
    if (strpos($url, 'http') !== 0) {
        $url = SITE_URL . '/' . ltrim($url, '/');
    }
    header("Location: " . $url);
    exit();
}

// ========================================
// INICIALIZAR SESSÃO
// ========================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ========================================
// INCLUIR SISTEMA DE LOGS
// ========================================

require_once __DIR__ . '/logger.php';

// ========================================
// LOG DE INICIALIZAÇÃO
// ========================================

if (DEBUG_MODE) {
    Logger::debug("Config carregado", [
        'SITE_URL' => SITE_URL,
        'SYSTEM_VERSION' => SYSTEM_VERSION,
        'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'N/A'
    ]);
}
?>
