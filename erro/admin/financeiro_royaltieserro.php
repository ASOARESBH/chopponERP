<?php
$page_title = 'Financeiro - Royalties';
$current_page = 'financeiro_royalties';

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/cora_api.php';
require_once '../includes/stripe_api.php';

$conn = getDBConnection();

// Configurações da API Cora (devem ser definidas no config.php ou em variáveis de ambiente)
// IMPORTANTE: Estas credenciais devem ser obtidas no aplicativo Cora
define('CORA_CLIENT_ID', getenv('CORA_CLIENT_ID') ?: '');
define('CORA_CERTIFICATE_PATH', getenv('CORA_CERTIFICATE_PATH') ?: __DIR__ . '/../certs/cora_certificate.pem');
define('CORA_PRIVATE_KEY_PATH', getenv('CORA_PRIVATE_KEY_PATH') ?: __DIR__ . '/../certs/cora_private_key.key');
define('CORA_ENVIRONMENT', getenv('CORA_ENVIRONMENT') ?: 'stage'); // 'stage' ou 'production'

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            $action = $_POST['action'];
            
            if ($action === 'add') {
                // Validar permissões
                $estabelecimento_id = isAdminGeral() ? intval($_POST['estabelecimento_id']) : getEstabelecimentoId();
                
                $periodo_inicial = $_POST['periodo_inicial'];
                $periodo_final = $_POST['periodo_final'];
                $descricao = sanitize($_POST['descricao']);
                $valor_faturamento_bruto = numberToFloat($_POST['valor_faturamento_bruto']);
                $percentual_royalties = 7.00; // Fixo em 7%
                $valor_royalties = $valor_faturamento_bruto * ($percentual_royalties / 100);
                $observacoes = !empty($_POST['observacoes']) ? sanitize($_POST['observacoes']) : null;
                
                // Validações
                if (strtotime($periodo_final) < strtotime($periodo_inicial)) {
                    throw new Exception('A data final deve ser maior ou igual à data inicial');
                }
                
                if ($valor_faturamento_bruto < 0) {
                    throw new Exception('O valor do faturamento bruto deve ser positivo');
                }
                
                // Capturar novos campos
                $tipo_cobranca = sanitize($_POST['tipo_cobranca'] ?? 'cora');
                $forma_pagamento = sanitize($_POST['forma_pagamento'] ?? 'boleto_pix');
                $email_cobranca = sanitize($_POST['email_cobranca']);
                $emails_adicionais = !empty($_POST['emails_adicionais']) ? sanitize($_POST['emails_adicionais']) : null;
                $data_vencimento = $_POST['data_vencimento'];
                $cnpj = sanitize($_POST['cnpj'] ?? '');
                
                // Inserir royalty
                $stmt = $conn->prepare("
                    INSERT INTO royalties 
                    (estabelecimento_id, cnpj, periodo_inicial, periodo_final, descricao, 
                     valor_faturamento_bruto, percentual_royalties, valor_royalties, 
                     tipo_cobranca, forma_pagamento, email_cobranca, emails_adicionais, 
                     data_vencimento, observacoes, created_by, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente')
                ");
                $stmt->execute([
                    $estabelecimento_id,
                    $cnpj, 
                    $periodo_inicial, 
                    $periodo_final, 
                    $descricao,
                    $valor_faturamento_bruto, 
                    $percentual_royalties, 
                    $valor_royalties,
                    $tipo_cobranca,
                    $forma_pagamento,
                    $email_cobranca,
                    $emails_adicionais,
                    $data_vencimento,
                    $observacoes,
                    $_SESSION['user_id']
                ]);
                
                $royalty_id = $conn->lastInsertId();
                
                // Registrar no histórico
                $stmt = $conn->prepare("
                    INSERT INTO royalties_historico (royalty_id, acao, descricao, user_id)
                    VALUES (?, 'criacao', 'Royalty criado manualmente', ?)
                ");
                $stmt->execute([$royalty_id, $_SESSION['user_id']]);
                
                // ===== GERAÇÃO AUTOMÁTICA DE PAGAMENTO =====
                
                // Buscar dados do estabelecimento
                $stmt = $conn->prepare("
                    SELECT name, cnpj, email, phone, address, number, district, city, state, zip_code
                    FROM estabelecimentos WHERE id = ?
                ");
                $stmt->execute([$estabelecimento_id]);
                $estabelecimento = $stmt->fetch();
                
                $payment_link_url = null;
                $boleto_id = null;
                
                if ($tipo_cobranca === 'stripe' && $forma_pagamento === 'payment_link') {
                    // ===== GERAR PAYMENT LINK VIA STRIPE =====
                    try {
                        require_once '../includes/stripe_api.php';
                        $stripe = new StripeAPI($estabelecimento_id);
                        
                        $metadata = [
                            'royalty_id' => $royalty_id,
                            'estabelecimento_id' => $estabelecimento_id,
                            'periodo' => $periodo_inicial . ' a ' . $periodo_final
                        ];
                        
                        $resultado = $stripe->createCompletePaymentLink(
                            $valor_royalties,
                            'Royalties - ' . $descricao,
                            $metadata
                        );
                        
                        if ($resultado['success']) {
                            $payment_link_url = $resultado['payment_link_url'];
                            
                            // Atualizar royalty com link de pagamento
                            $stmt = $conn->prepare("
                                UPDATE royalties 
                                SET payment_link_id = ?, payment_link_url = ?, status = 'aguardando_pagamento'
                                WHERE id = ?
                            ");
                            $stmt->execute([
                                $resultado['payment_link_id'],
                                $payment_link_url,
                                $royalty_id
                            ]);
                            
                            // Enviar e-mail com link de pagamento
                            require_once '../includes/email_sender.php';
                            
                            $emails = [$email_cobranca];
                            if ($emails_adicionais) {
                                $emails_extras = array_map('trim', explode(',', $emails_adicionais));
                                $emails = array_merge($emails, $emails_extras);
                            }
                            
                            $assunto = 'Cobrança de Royalties - ' . $descricao;
                            $mensagem = "
                                <h2>Cobrança de Royalties</h2>
                                <p><strong>Estabelecimento:</strong> {$estabelecimento['name']}</p>
                                <p><strong>Período:</strong> " . formatDateBR($periodo_inicial) . " a " . formatDateBR($periodo_final) . "</p>
                                <p><strong>Descrição:</strong> {$descricao}</p>
                                <p><strong>Valor:</strong> " . formatMoney($valor_royalties) . "</p>
                                <p><strong>Vencimento:</strong> " . formatDateBR($data_vencimento) . "</p>
                                <hr>
                                <p>Para realizar o pagamento, clique no link abaixo:</p>
                                <p><a href='{$payment_link_url}' style='display: inline-block; padding: 12px 24px; background-color: #28a745; color: white; text-decoration: none; border-radius: 4px;'>Pagar Agora</a></p>
                                <p>Ou copie e cole o link: <br><code>{$payment_link_url}</code></p>
                            ";
                            
                            foreach ($emails as $email_destino) {
                                enviarEmail($email_destino, $assunto, $mensagem);
                            }
                            
                            // Registrar envio
                            $stmt = $conn->prepare("
                                UPDATE royalties SET link_enviado_em = NOW() WHERE id = ?
                            ");
                            $stmt->execute([$royalty_id]);
                            
                            // Criar conta a pagar para o estabelecimento
                            $stmt = $conn->prepare("
                                INSERT INTO contas_pagar 
                                (estabelecimento_id, descricao, tipo, valor, data_vencimento, 
                                 payment_link_url, observacoes, status, royalty_id, valor_protegido, origem)
                                VALUES (?, ?, 'Royalties', ?, ?, ?, ?, 'pendente', ?, TRUE, 'royalties')
                            ");
                            $stmt->execute([
                                $estabelecimento_id,
                                'Royalties - ' . $descricao,
                                $valor_royalties,
                                $data_vencimento,
                                $payment_link_url,
                                'Link de pagamento gerado automaticamente',
                                $royalty_id
                            ]);
                            
                            $conta_pagar_id = $conn->lastInsertId();
                            
                            // Vincular conta a pagar ao royalty
                            $stmt = $conn->prepare("UPDATE royalties SET conta_pagar_id = ? WHERE id = ?");
                            $stmt->execute([$conta_pagar_id, $royalty_id]);
                            
                            $_SESSION['success'] = 'Royalty cadastrado! Link de pagamento gerado, e-mail enviado e conta a pagar criada.';
                        }
                    } catch (Exception $e) {
                        $_SESSION['warning'] = 'Royalty cadastrado, mas houve erro ao gerar link: ' . $e->getMessage();
                    }
                } elseif ($tipo_cobranca === 'stripe' && $forma_pagamento === 'invoice') {
                    // Fatura Stripe será gerada ao clicar em "Gerar Boleto"
                    $_SESSION['success'] = 'Royalty cadastrado! Clique em "Gerar Boleto" para criar a fatura.';
                } else {
                    // Boleto Cora será gerado ao clicar em "Gerar Boleto"
                    $_SESSION['success'] = 'Royalty cadastrado! Clique em "Gerar Boleto" para emitir.';
                }
                
            } elseif ($action === 'gerar_boleto') {
                $royalty_id = intval($_POST['royalty_id']);
                
                // Buscar dados do royalty
                $stmt = $conn->prepare("
                    SELECT r.*, e.name as estabelecimento_nome, e.cnpj, e.email, e.phone,
                           e.address, e.number, e.district, e.city, e.state, e.zip_code
                    FROM royalties r
                    INNER JOIN estabelecimentos e ON r.estabelecimento_id = e.id
                    WHERE r.id = ?
                ");
                $stmt->execute([$royalty_id]);
                $royalty = $stmt->fetch();
                
                if (!$royalty) {
                    throw new Exception('Royalty não encontrado');
                }
                
                if ($royalty['status'] !== 'pendente') {
                    throw new Exception('Cobrança já foi gerada para este royalty');
                }
                
                // Validar valor mínimo (R$ 5,00)
                if ($royalty['valor_royalties'] < 5.00) {
                    throw new Exception('Valor mínimo para emissão é R$ 5,00');
                }
                
                // Verificar tipo de cobrança
                $tipo_cobranca = $royalty['tipo_cobranca'];
                
                if ($tipo_cobranca === 'stripe') {
                    // ===== GERAÇÃO VIA STRIPE =====
                    
                    try {
                        $stripe = new StripeAPI($royalty['estabelecimento_id']);
                        
                        // Preparar dados do cliente
                        $customer_data = [
                            'email' => $royalty['email'],
                            'name' => $royalty['estabelecimento_nome'],
                            'phone' => $royalty['phone'] ?? null
                        ];
                        
                        // Preparar metadados
                        $metadata = [
                            'royalty_id' => $royalty_id,
                            'periodo_inicial' => $royalty['periodo_inicial'],
                            'periodo_final' => $royalty['periodo_final'],
                            'estabelecimento_id' => $royalty['estabelecimento_id']
                        ];
                        
                        // Criar fatura completa
                        $resultado = $stripe->createCompleteInvoice(
                            $customer_data,
                            $royalty['valor_royalties'],
                            'Royalties - ' . $royalty['descricao'],
                            $metadata,
                            30 // dias até vencimento
                        );
                        
                        if (!$resultado['success']) {
                            throw new Exception('Erro ao gerar fatura Stripe: ' . $resultado['error']);
                        }
                        
                        // Salvar dados da fatura
                        $stmt = $conn->prepare("
                            INSERT INTO stripe_invoices 
                            (royalty_id, estabelecimento_id, stripe_invoice_id, stripe_customer_id, 
                             stripe_payment_intent_id, invoice_number, invoice_url, hosted_invoice_url, 
                             invoice_pdf, amount, currency, status, due_date, metadata)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $royalty_id,
                            $royalty['estabelecimento_id'],
                            $resultado['invoice_id'],
                            $resultado['customer_id'],
                            $resultado['payment_intent_id'],
                            $resultado['invoice_number'],
                            $resultado['invoice_url'],
                            $resultado['invoice_url'],
                            $resultado['invoice_pdf'],
                            $resultado['amount'],
                            $resultado['currency'],
                            $resultado['status'],
                            date('Y-m-d', $resultado['due_date']),
                            json_encode($metadata)
                        ]);
                        
                        // Atualizar royalty
                        $stmt = $conn->prepare("
                            UPDATE royalties 
                            SET status = 'boleto_gerado',
                                stripe_invoice_id = ?,
                                boleto_data_vencimento = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $resultado['invoice_id'],
                            date('Y-m-d', $resultado['due_date']),
                            $royalty_id
                        ]);
                        
                        // Criar conta a pagar
                        $stmt = $conn->prepare("
                            INSERT INTO contas_pagar 
                            (estabelecimento_id, descricao, tipo, valor, data_vencimento, 
                             link_pagamento, observacoes, status)
                            VALUES (?, ?, 'Royalties', ?, ?, ?, ?, 'pendente')
                        ");
                        $stmt->execute([
                            $royalty['estabelecimento_id'],
                            'Royalties - ' . $royalty['descricao'],
                            $royalty['valor_royalties'],
                            date('Y-m-d', $resultado['due_date']),
                            $resultado['invoice_url'],
                            'Fatura Stripe: ' . $resultado['invoice_number']
                        ]);
                        
                        $conta_pagar_id = $conn->lastInsertId();
                        
                        // Vincular conta a pagar
                        $stmt = $conn->prepare("UPDATE royalties SET conta_pagar_id = ? WHERE id = ?");
                        $stmt->execute([$conta_pagar_id, $royalty_id]);
                        
                        // Registrar histórico
                        $stmt = $conn->prepare("
                            INSERT INTO royalties_historico (royalty_id, acao, descricao, dados_json, user_id)
                            VALUES (?, 'geracao_fatura_stripe', 'Fatura gerada via Stripe', ?, ?)
                        ");
                        $stmt->execute([
                            $royalty_id,
                            json_encode($resultado),
                            $_SESSION['user_id']
                        ]);
                        
                        $_SESSION['success'] = 'Fatura Stripe gerada e enviada por e-mail com sucesso!';
                        
                    } catch (Exception $e) {
                        throw new Exception('Erro Stripe: ' . $e->getMessage());
                    }
                    
                } else {
                    // ===== GERAÇÃO VIA CORA (ORIGINAL) =====
                
                    // Calcular data de vencimento (30 dias a partir de hoje)
                    $data_vencimento = date('Y-m-d', strtotime('+30 days'));
                
                // Preparar dados para API Cora
                $boleto_data = [
                    'code' => 'ROYALTY-' . $royalty_id,
                    'customer' => [
                        'name' => $royalty['estabelecimento_nome'],
                        'email' => $royalty['email'] ?? '',
                        'document' => [
                            'identity' => preg_replace('/[^0-9]/', '', $royalty['cnpj']),
                            'type' => 'CNPJ'
                        ]
                    ],
                    'services' => [
                        [
                            'name' => 'Royalties',
                            'description' => substr($royalty['descricao'], 0, 100),
                            'amount' => intval($royalty['valor_royalties'] * 100) // Converter para centavos
                        ]
                    ],
                    'payment_terms' => [
                        'due_date' => $data_vencimento
                    ],
                    'payment_forms' => ['BANK_SLIP', 'PIX']
                ];
                
                // Adicionar endereço se disponível
                if (!empty($royalty['address'])) {
                    $boleto_data['customer']['address'] = [
                        'street' => $royalty['address'],
                        'number' => $royalty['number'] ?? 'S/N',
                        'district' => $royalty['district'] ?? '',
                        'city' => $royalty['city'] ?? '',
                        'state' => $royalty['state'] ?? '',
                        'complement' => '',
                        'zip_code' => preg_replace('/[^0-9]/', '', $royalty['zip_code'] ?? '')
                    ];
                }
                
                // Emitir boleto via API Cora
                $cora = new CoraAPI(
                    CORA_CLIENT_ID,
                    CORA_CERTIFICATE_PATH,
                    CORA_PRIVATE_KEY_PATH,
                    CORA_ENVIRONMENT
                );
                
                $resultado = $cora->emitirBoleto($boleto_data);
                
                if (!$resultado['success']) {
                    throw new Exception('Erro ao gerar boleto: ' . $resultado['error']);
                }
                
                $boleto = $resultado['data'];
                
                // Atualizar royalty com dados do boleto
                $stmt = $conn->prepare("
                    UPDATE royalties 
                    SET status = 'boleto_gerado',
                        boleto_id = ?,
                        boleto_linha_digitavel = ?,
                        boleto_codigo_barras = ?,
                        boleto_qrcode_pix = ?,
                        boleto_data_vencimento = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $boleto['id'],
                    $boleto['payment_options']['bank_slip']['digitable_line'] ?? null,
                    $boleto['payment_options']['bank_slip']['barcode'] ?? null,
                    $boleto['payment_options']['pix']['qr_code'] ?? null,
                    $data_vencimento,
                    $royalty_id
                ]);
                
                // Criar conta a pagar
                $stmt = $conn->prepare("
                    INSERT INTO contas_pagar 
                    (estabelecimento_id, descricao, tipo, valor, data_vencimento, 
                     codigo_barras, link_pagamento, observacoes, status)
                    VALUES (?, ?, 'Royalties', ?, ?, ?, ?, ?, 'pendente')
                ");
                $stmt->execute([
                    $royalty['estabelecimento_id'],
                    'Royalties - ' . $royalty['descricao'],
                    $royalty['valor_royalties'],
                    $data_vencimento,
                    $boleto['payment_options']['bank_slip']['barcode'] ?? null,
                    null, // link_pagamento
                    'Boleto ID Cora: ' . $boleto['id']
                ]);
                
                $conta_pagar_id = $conn->lastInsertId();
                
                // Vincular conta a pagar ao royalty
                $stmt = $conn->prepare("UPDATE royalties SET conta_pagar_id = ? WHERE id = ?");
                $stmt->execute([$conta_pagar_id, $royalty_id]);
                
                // Registrar no histórico
                $stmt = $conn->prepare("
                    INSERT INTO royalties_historico (royalty_id, acao, descricao, dados_json, user_id)
                    VALUES (?, 'geracao_boleto', 'Boleto gerado via API Cora', ?, ?)
                ");
                $stmt->execute([
                    $royalty_id,
                    json_encode($boleto),
                    $_SESSION['user_id']
                ]);
                
                $_SESSION['success'] = 'Boleto gerado com sucesso e conta a pagar criada!';
                
                } // Fim do else (Cora)
                
            } elseif ($action === 'delete') {
                $royalty_id = intval($_POST['royalty_id']);
                
                // Verificar se pode excluir
                $stmt = $conn->prepare("SELECT status FROM royalties WHERE id = ?");
                $stmt->execute([$royalty_id]);
                $royalty = $stmt->fetch();
                
                if ($royalty['status'] === 'pago') {
                    throw new Exception('Não é possível excluir um royalty já pago');
                }
                
                // Excluir
                $stmt = $conn->prepare("DELETE FROM royalties WHERE id = ?");
                $stmt->execute([$royalty_id]);
                
                $_SESSION['success'] = 'Royalty excluído com sucesso!';
            }
        }
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        Logger::error("Erro ao processar royalty", [
            'error' => $e->getMessage(),
            'user_id' => $_SESSION['user_id']
        ]);
    }
}

// Buscar royalties
if (isAdminGeral()) {
    $stmt = $conn->query("
        SELECT r.*, e.name as estabelecimento_nome, e.cnpj,
               u.name as criado_por_nome
        FROM royalties r
        INNER JOIN estabelecimentos e ON r.estabelecimento_id = e.id
        LEFT JOIN users u ON r.created_by = u.id
        ORDER BY r.created_at DESC
    ");
} else {
    $estabelecimento_id = getEstabelecimentoId();
    $stmt = $conn->prepare("
        SELECT r.*, e.name as estabelecimento_nome, e.cnpj,
               u.name as criado_por_nome
        FROM royalties r
        INNER JOIN estabelecimentos e ON r.estabelecimento_id = e.id
        LEFT JOIN users u ON r.created_by = u.id
        WHERE r.estabelecimento_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$estabelecimento_id]);
}
$royalties = $stmt->fetchAll();

// Calcular totais
$total_pendente = 0;
$total_boleto_gerado = 0;
$total_pago = 0;

foreach ($royalties as $royalty) {
    if ($royalty['status'] === 'pendente') {
        $total_pendente += $royalty['valor_royalties'];
    } elseif ($royalty['status'] === 'boleto_gerado') {
        $total_boleto_gerado += $royalty['valor_royalties'];
    } elseif ($royalty['status'] === 'pago') {
        $total_pago += $royalty['valor_royalties'];
    }
}

// Buscar estabelecimentos (para admin)
if (isAdminGeral()) {
    $stmt = $conn->query("SELECT id, name FROM estabelecimentos WHERE status = 1 ORDER BY name");
    $estabelecimentos = $stmt->fetchAll();
}

require_once '../includes/header.php';
?>

<div class="page-header">
    <h1>Royalties</h1>
    <button class="btn btn-primary" onclick="openModalRoyalty()">
        <span>➕</span> Novo Lançamento
    </button>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger">
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<!-- Cards de resumo -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background-color: #ffc107;">⏳</div>
        <div class="stat-info">
            <div class="stat-label">Pendentes</div>
            <div class="stat-value"><?php echo formatMoney($total_pendente); ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background-color: #17a2b8;">📄</div>
        <div class="stat-info">
            <div class="stat-label">Boletos Gerados</div>
            <div class="stat-value"><?php echo formatMoney($total_boleto_gerado); ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background-color: #28a745;">✅</div>
        <div class="stat-info">
            <div class="stat-label">Pagos</div>
            <div class="stat-value"><?php echo formatMoney($total_pago); ?></div>
        </div>
    </div>
</div>

<!-- Tabela de royalties -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3>Lançamentos de Royalties</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <?php if (isAdminGeral()): ?>
                        <th>Estabelecimento</th>
                        <?php endif; ?>
                        <th>Período</th>
                        <th>Descrição</th>
                        <th>Faturamento Bruto</th>
                        <th>Royalties (7%)</th>
                        <th>Status</th>
                        <th>Vencimento</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($royalties)): ?>
                        <tr>
                            <td colspan="<?php echo isAdminGeral() ? '8' : '7'; ?>" class="text-center">
                                Nenhum lançamento de royalty encontrado
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($royalties as $royalty): ?>
                        <tr>
                            <?php if (isAdminGeral()): ?>
                            <td><?php echo htmlspecialchars($royalty['estabelecimento_nome']); ?></td>
                            <?php endif; ?>
                            <td>
                                <?php echo formatDateBR($royalty['periodo_inicial']); ?> a 
                                <?php echo formatDateBR($royalty['periodo_final']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($royalty['descricao']); ?></td>
                            <td><?php echo formatMoney($royalty['valor_faturamento_bruto']); ?></td>
                            <td><strong><?php echo formatMoney($royalty['valor_royalties']); ?></strong></td>
                            <td>
                                <?php
                                $status_class = [
                                    'pendente' => 'warning',
                                    'boleto_gerado' => 'info',
                                    'pago' => 'success',
                                    'cancelado' => 'secondary'
                                ];
                                $status_text = [
                                    'pendente' => 'Pendente',
                                    'boleto_gerado' => 'Boleto Gerado',
                                    'pago' => 'Pago',
                                    'cancelado' => 'Cancelado'
                                ];
                                ?>
                                <span class="badge badge-<?php echo $status_class[$royalty['status']]; ?>">
                                    <?php echo $status_text[$royalty['status']]; ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                if ($royalty['boleto_data_vencimento']) {
                                    echo formatDateBR($royalty['boleto_data_vencimento']);
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($royalty['status'] === 'pendente'): ?>
                                    <button class="btn btn-sm btn-success" onclick="gerarBoleto(<?php echo $royalty['id']; ?>)" title="Gerar Boleto">
                                        📄 Gerar Boleto
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteRoyalty(<?php echo $royalty['id']; ?>)" title="Excluir">
                                        🗑️
                                    </button>
                                <?php elseif ($royalty['status'] === 'boleto_gerado'): ?>
                                    <button class="btn btn-sm btn-info" onclick="verBoleto(<?php echo $royalty['id']; ?>)" title="Ver Boleto">
                                        👁️ Ver Boleto
                                    </button>
                                <?php elseif ($royalty['status'] === 'pago'): ?>
                                    <span class="text-success">✅ Pago</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal para adicionar royalty -->
<div id="royaltyModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Novo Lançamento de Royalty</h2>
            <span class="close" onclick="closeModalRoyalty()">&times;</span>
        </div>
        <form method="POST" id="royaltyForm">
            <input type="hidden" name="action" value="add">
            
            <div class="modal-body">
                <?php if (isAdminGeral()): ?>
                <div class="form-group">
                    <label for="estabelecimento_id">Estabelecimento *</label>
                    <select name="estabelecimento_id" id="estabelecimento_id" class="form-control" required onchange="loadEstabelecimentoData()">
                        <option value="">Selecione...</option>
                        <?php foreach ($estabelecimentos as $est): ?>
                        <option value="<?php echo $est['id']; ?>"><?php echo htmlspecialchars($est['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="cnpj_estabelecimento">CNPJ do Estabelecimento</label>
                    <input type="text" name="cnpj" id="cnpj_estabelecimento" class="form-control" readonly 
                           style="background-color: #e9ecef;" placeholder="Selecione um estabelecimento">
                    <small class="form-text text-muted">CNPJ preenchido automaticamente ao selecionar o estabelecimento</small>
                </div>
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="periodo_inicial">Período Inicial *</label>
                        <input type="date" name="periodo_inicial" id="periodo_inicial" class="form-control" required>
                    </div>
                    
                    <div class="form-group col-md-6">
                        <label for="periodo_final">Período Final *</label>
                        <input type="date" name="periodo_final" id="periodo_final" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="descricao">Descrição da Cobrança *</label>
                    <input type="text" name="descricao" id="descricao" class="form-control" 
                           placeholder="Ex: Royalties referente ao mês de Dezembro/2024" required maxlength="255">
                </div>
                
                <div class="form-group">
                    <label for="valor_faturamento_bruto">Valor do Faturamento Bruto *</label>
                    <input type="text" name="valor_faturamento_bruto" id="valor_faturamento_bruto" 
                           class="form-control money-input" placeholder="R$ 0,00" required>
                    <small class="form-text text-muted">
                        O sistema calculará automaticamente 7% deste valor para os royalties
                    </small>
                </div>
                
                <div class="form-group">
                    <label>Valor dos Royalties (7%)</label>
                    <div class="form-control-plaintext" id="valor_royalties_display" style="font-size: 1.2em; font-weight: bold; color: #28a745;">
                        R$ 0,00
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="tipo_cobranca">Tipo de Cobrança *</label>
                        <select name="tipo_cobranca" id="tipo_cobranca" class="form-control" required onchange="updateFormasPagamento()">
                            <option value="cora">Banco Cora</option>
                            <option value="stripe">Stripe</option>
                        </select>
                    </div>
                    
                    <div class="form-group col-md-6">
                        <label for="forma_pagamento">Forma de Pagamento *</label>
                        <select name="forma_pagamento" id="forma_pagamento" class="form-control" required>
                            <option value="boleto_pix">Boleto + PIX</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email_cobranca">E-mail para Cobrança *</label>
                    <input type="email" name="email_cobranca" id="email_cobranca" class="form-control" 
                           placeholder="email@estabelecimento.com" required>
                    <small class="form-text text-muted">
                        E-mail principal onde será enviado o link/boleto de pagamento
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="emails_adicionais">E-mails Adicionais (opcional)</label>
                    <input type="text" name="emails_adicionais" id="emails_adicionais" class="form-control" 
                           placeholder="email1@exemplo.com, email2@exemplo.com">
                    <small class="form-text text-muted">
                        Separe múltiplos e-mails por vírgula
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="data_vencimento">Data de Vencimento *</label>
                    <input type="date" name="data_vencimento" id="data_vencimento" class="form-control" required>
                    <small class="form-text text-muted">
                        Data limite para pagamento
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="observacoes">Observações</label>
                    <textarea name="observacoes" id="observacoes" class="form-control" rows="3"></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModalRoyalty()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal para ver boleto -->
<div id="boletoModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Dados do Boleto</h2>
            <span class="close" onclick="closeBoletoModal()">&times;</span>
        </div>
        <div class="modal-body" id="boletoContent">
            <!-- Conteúdo será carregado via JavaScript -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeBoletoModal()">Fechar</button>
        </div>
    </div>
</div>

<style>
.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4);
}

.modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 0;
    border: 1px solid #888;
    width: 80%;
    max-width: 800px;
    border-radius: 8px;
}

.modal-header {
    padding: 20px;
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 20px;
    background-color: #f8f9fa;
    border-top: 1px solid #dee2e6;
    text-align: right;
}

.close {
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover,
.close:focus {
    color: black;
}

.badge {
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 0.85em;
}

.badge-warning { background-color: #ffc107; color: #000; }
.badge-info { background-color: #17a2b8; color: #fff; }
.badge-success { background-color: #28a745; color: #fff; }
.badge-secondary { background-color: #6c757d; color: #fff; }
</style>

<script>
function openModalRoyalty() {
    document.getElementById('royaltyForm').reset();
    document.getElementById('valor_royalties_display').textContent = 'R$ 0,00';
    
    // Definir data de vencimento padrão (30 dias a partir de hoje)
    const hoje = new Date();
    hoje.setDate(hoje.getDate() + 30);
    const dataVencimento = hoje.toISOString().split('T')[0];
    document.getElementById('data_vencimento').value = dataVencimento;
    
    // Atualizar formas de pagamento
    updateFormasPagamento();
    
    // Preencher e-mail do estabelecimento se houver
    <?php if (!isAdminGeral()): ?>
    preencherEmailEstabelecimento();
    <?php endif; ?>
    
    openModal('royaltyModal');
}

function closeModalRoyalty() {
    closeModal('royaltyModal');
    document.getElementById('royaltyForm').reset();
    document.getElementById('valor_royalties_display').textContent = 'R$ 0,00';
}

function updateFormasPagamento() {
    const tipoCobranca = document.getElementById('tipo_cobranca').value;
    const formaPagamentoSelect = document.getElementById('forma_pagamento');
    
    // Limpar opções
    formaPagamentoSelect.innerHTML = '';
    
    if (tipoCobranca === 'cora') {
        // Opções do Banco Cora
        formaPagamentoSelect.innerHTML = `
            <option value="boleto_pix">Boleto + PIX</option>
        `;
    } else if (tipoCobranca === 'stripe') {
        // Opções do Stripe
        formaPagamentoSelect.innerHTML = `
            <option value="invoice">Fatura (Cartão, Boleto, PIX)</option>
            <option value="payment_link">Link de Pagamento</option>
        `;
    }
}

function preencherEmailEstabelecimento() {
    // Buscar e-mail do estabelecimento via AJAX
    <?php if (!isAdminGeral()): ?>
    const estabelecimentoId = <?php echo getEstabelecimentoId(); ?>;
    fetch(`ajax/get_estabelecimento_email.php?id=${estabelecimentoId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.email) {
                document.getElementById('email_cobranca').value = data.email;
            }
            if (data.success && data.cnpj) {
                document.getElementById('cnpj_estabelecimento').value = data.cnpj;
            }
        })
        .catch(error => console.error('Erro ao buscar e-mail:', error));
    <?php endif; ?>
}

// Função para carregar dados do estabelecimento (CNPJ e e-mail)
function loadEstabelecimentoData() {
    const estabelecimentoId = document.getElementById('estabelecimento_id').value;
    
    if (!estabelecimentoId) {
        document.getElementById('cnpj_estabelecimento').value = '';
        document.getElementById('email_cobranca').value = '';
        return;
    }
    
    fetch(`ajax/get_estabelecimento_email.php?id=${estabelecimentoId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.cnpj) {
                    document.getElementById('cnpj_estabelecimento').value = data.cnpj;
                }
                if (data.email) {
                    document.getElementById('email_cobranca').value = data.email;
                }
            } else {
                console.error('Erro ao buscar dados:', data.error);
            }
        })
        .catch(error => {
            console.error('Erro ao buscar dados do estabelecimento:', error);
        });
}

function closeBoletoModal() {
    closeModal('boletoModal');
}

// Função para calcular royalties
function calcularRoyalties() {
    let input = document.getElementById('valor_faturamento_bruto');
    let valor = input.value.replace(/[^\d,]/g, '');
    valor = valor.replace(',', '.');
    let valorFloat = parseFloat(valor) || 0;
    let royalties = valorFloat * 0.07;
    
    document.getElementById('valor_royalties_display').textContent = 
        'R$ ' + royalties.toFixed(2).replace('.', ',');
}

// Calcular royalties em tempo real (múltiplos eventos)
const faturamentoInput = document.getElementById('valor_faturamento_bruto');
faturamentoInput.addEventListener('input', calcularRoyalties);
faturamentoInput.addEventListener('keyup', calcularRoyalties);
faturamentoInput.addEventListener('change', calcularRoyalties);

// Formatar input de dinheiro
document.querySelectorAll('.money-input').forEach(function(input) {
    input.addEventListener('blur', function(e) {
        let valor = e.target.value.replace(/[^\d,]/g, '');
        if (valor) {
            valor = valor.replace(',', '.');
            let valorFloat = parseFloat(valor);
            e.target.value = 'R$ ' + valorFloat.toFixed(2).replace('.', ',');
            
            // Recalcular royalties após formatar
            if (e.target.id === 'valor_faturamento_bruto') {
                calcularRoyalties();
            }
        }
    });
    
    input.addEventListener('focus', function(e) {
        e.target.value = e.target.value.replace('R$ ', '');
    });
});

function gerarBoleto(royaltyId) {
    if (confirm('Deseja gerar o boleto para este royalty? Uma conta a pagar será criada automaticamente.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="gerar_boleto">
            <input type="hidden" name="royalty_id" value="${royaltyId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function verBoleto(royaltyId) {
    // Buscar dados do boleto via AJAX
    fetch(`ajax/get_boleto_royalty.php?id=${royaltyId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let html = `
                    <div class="boleto-info">
                        <p><strong>Linha Digitável:</strong><br>
                        <code style="font-size: 1.1em;">${data.linha_digitavel || 'N/A'}</code></p>
                        
                        <p><strong>Código de Barras:</strong><br>
                        <code>${data.codigo_barras || 'N/A'}</code></p>
                        
                        <p><strong>Data de Vencimento:</strong> ${data.data_vencimento || 'N/A'}</p>
                        
                        <p><strong>Valor:</strong> ${data.valor || 'N/A'}</p>
                        
                        ${data.qrcode_pix ? `
                        <p><strong>QR Code Pix:</strong><br>
                        <img src="data:image/png;base64,${data.qrcode_pix}" style="max-width: 200px;"></p>
                        ` : ''}
                    </div>
                `;
                document.getElementById('boletoContent').innerHTML = html;
                openModal('boletoModal');
            } else {
                alert('Erro ao carregar dados do boleto: ' + data.error);
            }
        })
        .catch(error => {
            alert('Erro ao carregar dados do boleto');
            console.error(error);
        });
}

function deleteRoyalty(royaltyId) {
    if (confirm('Tem certeza que deseja excluir este royalty?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="royalty_id" value="${royaltyId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Fechar modal ao clicar fora
window.onclick = function(event) {
    const modal = document.getElementById('royaltyModal');
    const boletoModal = document.getElementById('boletoModal');
    if (event.target == modal) {
        closeModal();
    }
    if (event.target == boletoModal) {
        closeBoletoModal();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
