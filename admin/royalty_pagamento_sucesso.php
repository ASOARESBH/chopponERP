<?php
/**
 * Página de Sucesso do Pagamento de Royalty
 */

$page_title = 'Pagamento Realizado';
$current_page = 'financeiro_royalties';

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/RoyaltiesManager.php';

$conn = getDBConnection();
$royaltiesManager = new RoyaltiesManager($conn);

$royalty_id = (int)($_GET['id'] ?? 0);

if (!$royalty_id) {
    header('Location: financeiro_royalties.php');
    exit;
}

$royalty = $royaltiesManager->buscarPorId($royalty_id);

if (!$royalty) {
    header('Location: financeiro_royalties.php?error=royalty_nao_encontrado');
    exit;
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card mt-5">
                <div class="card-body text-center py-5">
                    <div class="mb-4">
                        <i class="fas fa-check-circle fa-5x text-success"></i>
                    </div>
                    
                    <h1 class="text-success mb-3">Pagamento Iniciado com Sucesso!</h1>
                    
                    <p class="lead">Seu pagamento foi processado e está sendo confirmado.</p>
                    
                    <div class="alert alert-info mt-4">
                        <h5>Detalhes do Pagamento</h5>
                        <p><strong>Estabelecimento:</strong> <?php echo htmlspecialchars($royalty['estabelecimento_nome']); ?></p>
                        <p><strong>Valor:</strong> R$ <?php echo number_format($royalty['valor_royalties'], 2, ',', '.'); ?></p>
                        <p><strong>Método:</strong> <?php echo strtoupper($royalty['metodo_pagamento'] ?? 'N/A'); ?></p>
                        <p><strong>Status:</strong> <?php echo ucfirst($royalty['payment_status'] ?? 'pendente'); ?></p>
                    </div>
                    
                    <?php if ($royalty['metodo_pagamento'] === 'cora' && $royalty['payment_url']): ?>
                    <div class="mt-4">
                        <a href="<?php echo htmlspecialchars($royalty['payment_url']); ?>" target="_blank" class="btn btn-primary btn-lg">
                            <i class="fas fa-file-pdf"></i> Visualizar Boleto
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mt-4">
                        <a href="financeiro_royalties.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Voltar para Royalties
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
