<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/cashback_helper.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('clientes.php');
}

$conn = getDBConnection();
$cliente_id = intval($_POST['cliente_id']);
$tipo = $_POST['tipo']; // 'credito' ou 'resgate'
$valor = numberToFloat($_POST['valor']);
$descricao = sanitize($_POST['descricao']);

// Buscar dados do cliente
$stmt = $conn->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$cliente_id]);
$cliente = $stmt->fetch();

if (!$cliente) {
    $_SESSION['error'] = 'Cliente não encontrado.';
    redirect('clientes.php');
}

// Verificar permissão
if (!isAdminGeral() && $cliente['estabelecimento_id'] != getEstabelecimentoId()) {
    $_SESSION['error'] = 'Sem permissão para ajustar cashback.';
    redirect('clientes.php');
}

// Ajustar cashback
$cashback = new CashbackHelper($conn, $cliente['estabelecimento_id']);
$result = $cashback->ajustarCashback(
    $cliente_id,
    $tipo,
    $valor,
    $descricao,
    $_SESSION['user_id']
);

if ($result['success']) {
    $_SESSION['success'] = $result['message'] . ' Novo saldo: ' . formatMoney($result['saldo_atual']);
} else {
    $_SESSION['error'] = $result['message'];
}

redirect('cliente_detalhes.php?id=' . $cliente_id);
?>
