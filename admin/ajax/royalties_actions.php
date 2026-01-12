<?php
/**
 * Ações AJAX para Royalties
 * Processa: gerar link, enviar e-mail, buscar dados, cancelar
 */

require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/RoyaltiesManager.php';

header('Content-Type: application/json');

$conn = getDBConnection();
$royaltiesManager = new RoyaltiesManager($conn);

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        
        // ===== BUSCAR ROYALTY =====
        case 'buscar':
            $id = intval($_GET['id'] ?? 0);
            
            if (!$id) {
                throw new Exception('ID inválido');
            }
            
            $royalty = $royaltiesManager->buscarPorId($id);
            
            if (!$royalty) {
                throw new Exception('Royalty não encontrado');
            }
            
            echo json_encode([
                'success' => true,
                'royalty' => $royalty
            ]);
            break;
        
        // ===== GERAR LINK =====
        case 'gerar_link':
            $id = intval($_POST['id'] ?? 0);
            
            if (!$id) {
                throw new Exception('ID inválido');
            }
            
            $resultado = $royaltiesManager->gerarPaymentLink($id);
            echo json_encode($resultado);
            break;
        
        // ===== ENVIAR E-MAIL =====
        case 'enviar_email':
            $id = intval($_POST['id'] ?? 0);
            
            if (!$id) {
                throw new Exception('ID inválido');
            }
            
            $resultado = $royaltiesManager->enviarEmail($id);
            echo json_encode($resultado);
            break;
        
        // ===== GERAR E ENVIAR TUDO =====
        case 'gerar_e_enviar':
            $id = intval($_POST['id'] ?? 0);
            
            if (!$id) {
                throw new Exception('ID inválido');
            }
            
            // Primeiro gera o link
            $resultadoLink = $royaltiesManager->gerarPaymentLink($id);
            
            if (!$resultadoLink['success']) {
                echo json_encode($resultadoLink);
                break;
            }
            
            // Depois envia o e-mail
            $resultadoEmail = $royaltiesManager->enviarEmail($id);
            
            if (!$resultadoEmail['success']) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Link gerado, mas erro ao enviar e-mail: ' . $resultadoEmail['message']
                ]);
                break;
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Link gerado e e-mail enviado com sucesso!',
                'payment_link' => $resultadoLink['payment_link']
            ]);
            break;
        
        // ===== CANCELAR ROYALTY =====
        case 'cancelar':
            $id = intval($_POST['id'] ?? 0);
            
            if (!$id) {
                throw new Exception('ID inválido');
            }
            
            $stmt = $conn->prepare("UPDATE royalties SET status = 'cancelado' WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Royalty cancelado com sucesso'
            ]);
            break;
        
        default:
            throw new Exception('Ação inválida');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
