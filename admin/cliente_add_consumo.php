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
$bebida_id = intval($_POST['bebida_id']);
$quantidade = floatval($_POST['quantidade']);
$valor_unitario = numberToFloat($_POST['valor_unitario']);
$data_consumo = $_POST['data_consumo'];

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
    $_SESSION['error'] = 'Sem permissão para adicionar consumo.';
    redirect('clientes.php');
}

// Buscar dados da bebida
$stmt = $conn->prepare("SELECT name FROM bebidas WHERE id = ?");
$stmt->execute([$bebida_id]);
$bebida = $stmt->fetch();

if (!$bebida) {
    $_SESSION['error'] = 'Bebida não encontrada.';
    redirect('cliente_detalhes.php?id=' . $cliente_id);
}

// Registrar consumo com cashback
$cashback = new CashbackHelper($conn, $cliente['estabelecimento_id']);
$result = $cashback->registrarConsumo(
    $cliente_id,
    $bebida_id,
    $bebida['name'],
    $quantidade,
    $valor_unitario,
    $data_consumo
);

if ($result['success']) {
    $_SESSION['success'] = 'Consumo adicionado com sucesso! Cashback: ' . formatMoney($result['cashback']);
} else {
    $_SESSION['error'] = 'Erro ao adicionar consumo: ' . $result['error'];
}

redirect('cliente_detalhes.php?id=' . $cliente_id);
?>
