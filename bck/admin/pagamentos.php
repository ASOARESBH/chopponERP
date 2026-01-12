<?php
$page_title = 'Configurações de Pagamento';
$current_page = 'pagamentos';

require_once '../includes/config.php';
require_once '../includes/auth.php';

$conn = getDBConnection();
$success = '';
$error = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token_sumup = sanitize($_POST['token_sumup']);
    $pix = isset($_POST['pix']) ? 1 : 0;
    $credit = isset($_POST['credit']) ? 1 : 0;
    $debit = isset($_POST['debit']) ? 1 : 0;
    
    // Verificar se já existe configuração
    $stmt = $conn->query("SELECT id FROM payment LIMIT 1");
    $existing = $stmt->fetch();
    
    if ($existing) {
        $stmt = $conn->prepare("UPDATE payment SET token_sumup = ?, pix = ?, credit = ?, debit = ? WHERE id = ?");
        if ($stmt->execute([$token_sumup, $pix, $credit, $debit, $existing['id']])) {
            $success = 'Configurações atualizadas com sucesso!';
        } else {
            $error = 'Erro ao atualizar configurações.';
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO payment (token_sumup, pix, credit, debit) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$token_sumup, $pix, $credit, $debit])) {
            $success = 'Configurações salvas com sucesso!';
        } else {
            $error = 'Erro ao salvar configurações.';
        }
    }
}

// Buscar configurações atuais
$stmt = $conn->query("SELECT * FROM payment LIMIT 1");
$payment = $stmt->fetch();

require_once '../includes/header.php';
?>

<div class="page-header">
    <h1>Configurações de Pagamento</h1>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4>Integração SumUp</h4>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label for="token_sumup">Token SumUp *</label>
                        <input type="text" 
                               name="token_sumup" 
                               id="token_sumup" 
                               class="form-control" 
                               value="<?php echo $payment['token_sumup'] ?? ''; ?>" 
                               required>
                        <small style="color: var(--gray-600);">Token de autenticação da API SumUp</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Métodos de Pagamento Habilitados</label>
                        
                        <div class="checkbox-label">
                            <input type="checkbox" 
                                   name="pix" 
                                   id="pix" 
                                   value="1" 
                                   <?php echo ($payment['pix'] ?? 1) ? 'checked' : ''; ?>>
                            <span>PIX</span>
                        </div>
                        
                        <div class="checkbox-label">
                            <input type="checkbox" 
                                   name="credit" 
                                   id="credit" 
                                   value="1" 
                                   <?php echo ($payment['credit'] ?? 1) ? 'checked' : ''; ?>>
                            <span>Cartão de Crédito</span>
                        </div>
                        
                        <div class="checkbox-label">
                            <input type="checkbox" 
                                   name="debit" 
                                   id="debit" 
                                   value="1" 
                                   <?php echo ($payment['debit'] ?? 1) ? 'checked' : ''; ?>>
                            <span>Cartão de Débito</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Salvar Configurações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h4>Informações</h4>
            </div>
            <div class="card-body">
                <p><strong>Merchant Code:</strong> MCTSYDUE</p>
                <p><strong>Webhook URL:</strong></p>
                <code style="font-size: 11px; word-break: break-all;">
                    <?php echo SITE_URL; ?>/api/webhook.php
                </code>
                <hr>
                <p style="font-size: 13px; color: var(--gray-600);">
                    Configure este webhook no painel SumUp para receber notificações de status de pagamento.
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
