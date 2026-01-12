<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

try {
    if (!isset($_GET['id'])) {
        throw new Exception('ID não fornecido');
    }
    
    $royalty_id = intval($_GET['id']);
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        SELECT r.*, e.name as estabelecimento_nome
        FROM royalties r
        INNER JOIN estabelecimentos e ON r.estabelecimento_id = e.id
        WHERE r.id = ?
    ");
    $stmt->execute([$royalty_id]);
    $royalty = $stmt->fetch();
    
    if (!$royalty) {
        throw new Exception('Royalty não encontrado');
    }
    
    // Verificar permissão
    if (!isAdminGeral() && $royalty['estabelecimento_id'] != getEstabelecimentoId()) {
        throw new Exception('Sem permissão para visualizar este royalty');
    }
    
    if ($royalty['status'] !== 'boleto_gerado' && $royalty['status'] !== 'pago') {
        throw new Exception('Boleto ainda não foi gerado');
    }
    
    echo json_encode([
        'success' => true,
        'linha_digitavel' => $royalty['boleto_linha_digitavel'],
        'codigo_barras' => $royalty['boleto_codigo_barras'],
        'qrcode_pix' => $royalty['boleto_qrcode_pix'],
        'data_vencimento' => formatDateBR($royalty['boleto_data_vencimento']),
        'valor' => formatMoney($royalty['valor_royalties'])
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
