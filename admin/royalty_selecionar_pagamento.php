<?php
/**
 * Seleção de Método de Pagamento para Royalty
 * 
 * CORREÇÃO APLICADA:
 * - Linha 65: Alterado 'status = 1' para 'ativo = 1' em stripe_config
 * Data: 2026-01-07
 */

$page_title = 'Selecionar Método de Pagamento';
$current_page = 'financeiro_royalties';

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/RoyaltiesManager.php';

$conn = getDBConnection();
$royaltiesManager = new RoyaltiesManager($conn);

// Verificar se o ID do royalty foi fornecido
$royalty_id = (int)($_GET['id'] ?? 0);

if (!$royalty_id) {
    header('Location: financeiro_royalties.php?error=id_invalido');
    exit;
}

// Buscar dados do royalty
$royalty = $royaltiesManager->buscarPorId($royalty_id);

if (!$royalty) {
    header('Location: financeiro_royalties.php?error=royalty_nao_encontrado');
    exit;
}

// Verificar se já foi pago
if ($royalty['status'] === 'pago') {
    header('Location: financeiro_royalties.php?error=royalty_ja_pago');
    exit;
}

// Buscar métodos de pagamento disponíveis para o estabelecimento
$estabelecimento_id = $royalty['estabelecimento_id'];

// Verificar Stripe (PDO)
// CORRIGIDO: Usar 'ativo' em vez de 'status'
$stmt = $conn->prepare("SELECT * FROM stripe_config WHERE estabelecimento_id = :estabelecimento_id AND ativo = 1");
$stmt->execute([':estabelecimento_id' => $estabelecimento_id]);
$stripe_disponivel = $stmt->rowCount() > 0;

// Verificar Cora (PDO)
$stmt = $conn->prepare("SELECT * FROM cora_config WHERE estabelecimento_id = :estabelecimento_id AND ativo = 1");
$stmt->execute([':estabelecimento_id' => $estabelecimento_id]);
$cora_disponivel = $stmt->rowCount() > 0;

// Verificar Mercado Pago (PDO)
$stmt = $conn->prepare("SELECT * FROM mercadopago_config WHERE estabelecimento_id = :estabelecimento_id AND status = 1");
$stmt->execute([':estabelecimento_id' => $estabelecimento_id]);
$mercadopago_disponivel = $stmt->rowCount() > 0;

// Verificar Asaas (PDO)
$stmt = $conn->prepare("SELECT * FROM asaas_config WHERE estabelecimento_id = :estabelecimento_id AND ativo = 1");
$stmt->execute([':estabelecimento_id' => $estabelecimento_id]);
$asaas_disponivel = $stmt->rowCount() > 0;

require_once '../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="page-header">
                <h1><i class="fas fa-credit-card"></i> Selecionar Método de Pagamento</h1>
                <p class="text-muted">Escolha como deseja pagar este royalty</p>
            </div>

            <!-- Informações do Royalty -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h3 class="card-title">Detalhes do Royalty</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Estabelecimento:</strong> <?php echo htmlspecialchars($royalty['estabelecimento_nome']); ?></p>
                            <p><strong>Período:</strong> <?php echo date('d/m/Y', strtotime($royalty['periodo_inicial'])); ?> a <?php echo date('d/m/Y', strtotime($royalty['periodo_final'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Faturamento Bruto:</strong> R$ <?php echo number_format($royalty['valor_faturamento_bruto'], 2, ',', '.'); ?></p>
                            <p><strong>Royalties (<?php echo $royalty['percentual_royalties']; ?>%):</strong> <span class="h4 text-success">R$ <?php echo number_format($royalty['valor_royalties'], 2, ',', '.'); ?></span></p>
                        </div>
                    </div>
                    <?php if ($royalty['descricao_cobranca']): ?>
                    <p><strong>Descrição:</strong> <?php echo nl2br(htmlspecialchars($royalty['descricao_cobranca'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Métodos de Pagamento -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Escolha o Método de Pagamento</h3>
                </div>
                <div class="card-body">
                    <?php if (!$stripe_disponivel && !$cora_disponivel && !$mercadopago_disponivel && !$asaas_disponivel): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> Nenhum método de pagamento configurado para este estabelecimento.
                            <br>Entre em contato com o administrador para configurar Stripe, Cora, Mercado Pago ou Asaas.
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php if ($stripe_disponivel): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card payment-method-card" onclick="selecionarMetodo('stripe')">
                                    <div class="card-body text-center">
                                        <i class="fab fa-stripe fa-4x text-primary mb-3"></i>
                                        <h4>Stripe</h4>
                                        <p class="text-muted">Cartão de Crédito</p>
                                        <button type="button" class="btn btn-primary btn-block">
                                            <i class="fas fa-arrow-right"></i> Pagar com Stripe
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($cora_disponivel): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card payment-method-card" onclick="selecionarMetodo('cora')">
                                    <div class="card-body text-center">
                                        <i class="fas fa-university fa-4x text-success mb-3"></i>
                                        <h4>Banco Cora</h4>
                                        <p class="text-muted">Boleto Bancário</p>
                                        <button type="button" class="btn btn-success btn-block">
                                            <i class="fas fa-arrow-right"></i> Pagar com Cora
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($mercadopago_disponivel): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card payment-method-card" onclick="selecionarMetodo('mercadopago')">
                                    <div class="card-body text-center">
                                        <i class="fab fa-cc-mastercard fa-4x text-info mb-3"></i>
                                        <h4>Mercado Pago</h4>
                                        <p class="text-muted">Cartão, PIX ou Boleto</p>
                                        <button type="button" class="btn btn-info btn-block">
                                            <i class="fas fa-arrow-right"></i> Pagar com Mercado Pago
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($asaas_disponivel): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card payment-method-card" onclick="selecionarMetodo('asaas')">
                                    <div class="card-body text-center">
                                        <i class="fas fa-dollar-sign fa-4x text-warning mb-3"></i>
                                        <h4>Asaas</h4>
                                        <p class="text-muted">Cartão, PIX ou Boleto</p>
                                        <button type="button" class="btn btn-warning btn-block">
                                            <i class="fas fa-arrow-right"></i> Pagar com Asaas
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="mt-4">
                        <a href="financeiro_royalties.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Voltar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.payment-method-card {
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid #e0e0e0;
}

.payment-method-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    border-color: #007bff;
}
</style>

<script>
function selecionarMetodo(metodo) {
    const royaltyId = <?php echo $royalty_id; ?>;
    
    // Confirmar antes de redirecionar
    if (confirm(`Confirma o pagamento via ${metodo.toUpperCase()}?`)) {
        // Redirecionar para processamento
        window.location.href = `royalty_processar_pagamento.php?id=${royaltyId}&metodo=${metodo}`;
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
