<?php
/**
 * Ação AJAX para Gerar Boleto Cora
 * Processa geração de boleto quando selecionado pagamento via Cora
 */

require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/RoyaltiesManager.php';
require_once '../../includes/CoraManager.php';

header('Content-Type: application/json');

$conn = getDBConnection();
$royaltiesManager = new RoyaltiesManager($conn);

try {
    $action = $_REQUEST['action'] ?? '';
    
    if (empty($action)) {
        throw new Exception('Ação não especificada');
    }
    
    switch ($action) {
        
        // ===== GERAR BOLETO CORA =====
        case 'gerar_boleto':
            $id = intval($_POST['id'] ?? 0);
            
            if (!$id) {
                throw new Exception('ID inválido');
            }
            
            // Verificar permissão
            $royalty = $royaltiesManager->buscarPorId($id);
            if (!$royalty) {
                throw new Exception('Royalty não encontrado');
            }
            
            if (!isAdminGeral() && $royalty['estabelecimento_id'] != getEstabelecimentoId()) {
                throw new Exception('Sem permissão para gerar boleto');
            }
            
            // Gerar boleto
            $resultado = $royaltiesManager->gerarBoletoCora($id);
            echo json_encode($resultado);
            break;
        
        // ===== GERAR E ENVIAR BOLETO =====
        case 'gerar_e_enviar_boleto':
            $id = intval($_POST['id'] ?? 0);
            
            if (!$id) {
                throw new Exception('ID inválido');
            }
            
            // Verificar permissão
            $royalty = $royaltiesManager->buscarPorId($id);
            if (!$royalty) {
                throw new Exception('Royalty não encontrado');
            }
            
            if (!isAdminGeral() && $royalty['estabelecimento_id'] != getEstabelecimentoId()) {
                throw new Exception('Sem permissão para gerar boleto');
            }
            
            // Primeiro gera o boleto
            $resultadoBoleto = $royaltiesManager->gerarBoletoCora($id);
            
            if (!$resultadoBoleto['success']) {
                echo json_encode($resultadoBoleto);
                break;
            }
            
            // Depois envia o e-mail
            $resultadoEmail = $royaltiesManager->enviarEmail($id);
            
            if (!$resultadoEmail['success']) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Boleto gerado, mas erro ao enviar e-mail: ' . $resultadoEmail['message']
                ]);
                break;
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Boleto gerado e e-mail enviado com sucesso!',
                'boleto' => $resultadoBoleto['boleto']
            ]);
            break;
        
        // ===== CONSULTAR STATUS DO BOLETO =====
        case 'consultar_boleto':
            $royalty_id = intval($_GET['royalty_id'] ?? 0);
            
            if (!$royalty_id) {
                throw new Exception('ID do royalty não fornecido');
            }
            
            $royalty = $royaltiesManager->buscarPorId($royalty_id);
            if (!$royalty) {
                throw new Exception('Royalty não encontrado');
            }
            
            if (!isAdminGeral() && $royalty['estabelecimento_id'] != getEstabelecimentoId()) {
                throw new Exception('Sem permissão para consultar boleto');
            }
            
            if (empty($royalty['boleto_cora_id'])) {
                throw new Exception('Boleto não foi gerado ainda');
            }
            
            // Consultar status via Cora
            $coraManager = new CoraManager($conn, $royalty['estabelecimento_id']);
            $resultado = $coraManager->consultarBoleto($royalty['boleto_cora_id']);
            
            echo json_encode($resultado);
            break;
        
        default:
            throw new Exception('Ação inválida: ' . $action);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
