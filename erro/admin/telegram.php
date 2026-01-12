<?php
$page_title = 'Configuração Telegram';
$current_page = 'telegram';

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/telegram.php';

$conn = getDBConnection();
$success = '';
$error = '';

// Obter estabelecimento do usuário
$estabelecimento_id = isAdminGeral() ? ($_POST['estabelecimento_id'] ?? $_GET['estabelecimento_id'] ?? null) : getEstabelecimentoId();

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $bot_token = sanitize($_POST['bot_token']);
        $chat_id = sanitize($_POST['chat_id']);
        $notificar_vendas = isset($_POST['notificar_vendas']) ? 1 : 0;
        $notificar_volume_critico = isset($_POST['notificar_volume_critico']) ? 1 : 0;
        $notificar_vencimento = isset($_POST['notificar_vencimento']) ? 1 : 0;
        $status = isset($_POST['status']) ? 1 : 0;
        
        if (empty($bot_token) || empty($chat_id)) {
            $error = 'Bot Token e Chat ID são obrigatórios.';
        } else {
            // Verificar se já existe configuração
            $stmt = $conn->prepare("SELECT id FROM telegram_config WHERE estabelecimento_id = ?");
            $stmt->execute([$estabelecimento_id]);
            $config_exists = $stmt->fetch();
            
            if ($config_exists) {
                // Atualizar
                $stmt = $conn->prepare("
                    UPDATE telegram_config 
                    SET bot_token = ?, chat_id = ?, notificar_vendas = ?, notificar_volume_critico = ?, notificar_vencimento = ?, status = ?
                    WHERE estabelecimento_id = ?
                ");
                
                if ($stmt->execute([$bot_token, $chat_id, $notificar_vendas, $notificar_volume_critico, $notificar_vencimento, $status, $estabelecimento_id])) {
                    $success = 'Configuração atualizada com sucesso!';
                } else {
                    $error = 'Erro ao atualizar configuração.';
                }
            } else {
                // Inserir
                $stmt = $conn->prepare("
                    INSERT INTO telegram_config (estabelecimento_id, bot_token, chat_id, notificar_vendas, notificar_volume_critico, notificar_vencimento, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                if ($stmt->execute([$estabelecimento_id, $bot_token, $chat_id, $notificar_vendas, $notificar_volume_critico, $notificar_vencimento, $status])) {
                    $success = 'Configuração salva com sucesso!';
                } else {
                    $error = 'Erro ao salvar configuração.';
                }
            }
        }
    }
    
    if ($action === 'test_connection') {
        $bot_token = sanitize($_POST['bot_token']);
        
        if (empty($bot_token)) {
            $error = 'Bot Token é obrigatório para testar.';
        } else {
            $result = TelegramBot::testConnection($bot_token);
            
            if ($result['success']) {
                $success = "Conexão OK! Bot: @{$result['bot_name']} (ID: {$result['bot_id']})";
            } else {
                $error = "Erro na conexão: {$result['error']}";
            }
        }
    }
    
    if ($action === 'send_test') {
        $bot_token = sanitize($_POST['bot_token']);
        $chat_id = sanitize($_POST['chat_id']);
        
        if (empty($bot_token) || empty($chat_id)) {
            $error = 'Bot Token e Chat ID são obrigatórios para enviar teste.';
        } else {
            $result = TelegramBot::sendTestMessage($bot_token, $chat_id);
            
            if ($result['success']) {
                $success = 'Mensagem de teste enviada com sucesso! Verifique o Telegram.';
            } else {
                $error = "Erro ao enviar mensagem: {$result['error']}";
            }
        }
    }
}

// Buscar configuração atual
$config = null;
if ($estabelecimento_id) {
    $stmt = $conn->prepare("SELECT * FROM telegram_config WHERE estabelecimento_id = ?");
    $stmt->execute([$estabelecimento_id]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Listar estabelecimentos para admin
$estabelecimentos = [];
if (isAdminGeral()) {
    $stmt = $conn->query("SELECT * FROM estabelecimentos WHERE status = 1 ORDER BY name");
    $estabelecimentos = $stmt->fetchAll();
}

// Buscar histórico de alertas
$alertas = [];
if ($estabelecimento_id) {
    $stmt = $conn->prepare("
        SELECT * FROM telegram_alerts 
        WHERE estabelecimento_id = ? 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmt->execute([$estabelecimento_id]);
    $alertas = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once '../includes/header.php';
?>

<div class="page-header">
    <h1>🤖 Configuração Telegram Bot</h1>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (isAdminGeral() && !$estabelecimento_id): ?>
    <div class="card">
        <div class="card-body">
            <h3>Selecione um Estabelecimento</h3>
            <form method="GET">
                <div class="form-group">
                    <label>Estabelecimento</label>
                    <select name="estabelecimento_id" class="form-control" onchange="this.form.submit()">
                        <option value="">Selecione...</option>
                        <?php foreach ($estabelecimentos as $est): ?>
                            <option value="<?php echo $est['id']; ?>"><?php echo $est['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>
<?php else: ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3>Configuração do Bot</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="save">
                    <?php if (isAdminGeral()): ?>
                        <input type="hidden" name="estabelecimento_id" value="<?php echo $estabelecimento_id; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Bot Token *</label>
                        <input type="text" name="bot_token" class="form-control" 
                               value="<?php echo $config['bot_token'] ?? ''; ?>" 
                               placeholder="1234567890:ABCdefGHIjklMNOpqrsTUVwxyz" required>
                        <small class="form-text text-muted">
                            Obtenha o token com o <a href="https://t.me/BotFather" target="_blank">@BotFather</a>
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label>Chat ID *</label>
                        <input type="text" name="chat_id" class="form-control" 
                               value="<?php echo $config['chat_id'] ?? ''; ?>" 
                               placeholder="-1001234567890" required>
                        <small class="form-text text-muted">
                            ID do grupo ou canal. Use <a href="https://t.me/userinfobot" target="_blank">@userinfobot</a> para descobrir
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label>Notificações</label>
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="notificar_vendas" 
                                       <?php echo (!$config || $config['notificar_vendas']) ? 'checked' : ''; ?>>
                                💰 Vendas realizadas
                            </label>
                        </div>
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="notificar_volume_critico" 
                                       <?php echo (!$config || $config['notificar_volume_critico']) ? 'checked' : ''; ?>>
                                ⚠️ Volume crítico de barris
                            </label>
                        </div>
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="notificar_vencimento" 
                                       <?php echo (!$config || $config['notificar_vencimento']) ? 'checked' : ''; ?>>
                                📅 Alertas de vencimento
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox">
                            <input type="checkbox" name="status" 
                                   <?php echo (!$config || $config['status']) ? 'checked' : ''; ?>>
                            ✅ Ativar notificações
                        </label>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Salvar Configuração</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3>Testes</h3>
            </div>
            <div class="card-body">
                <form method="POST" style="margin-bottom: 15px;">
                    <input type="hidden" name="action" value="test_connection">
                    <input type="hidden" name="bot_token" value="<?php echo $config['bot_token'] ?? ''; ?>">
                    <button type="submit" class="btn btn-info btn-block" 
                            <?php echo empty($config['bot_token']) ? 'disabled' : ''; ?>>
                        🔍 Testar Conexão
                    </button>
                </form>
                
                <form method="POST">
                    <input type="hidden" name="action" value="send_test">
                    <input type="hidden" name="bot_token" value="<?php echo $config['bot_token'] ?? ''; ?>">
                    <input type="hidden" name="chat_id" value="<?php echo $config['chat_id'] ?? ''; ?>">
                    <button type="submit" class="btn btn-success btn-block" 
                            <?php echo (empty($config['bot_token']) || empty($config['chat_id'])) ? 'disabled' : ''; ?>>
                        📤 Enviar Mensagem Teste
                    </button>
                </form>
                
                <hr>
                
                <h4>Como Configurar</h4>
                <ol style="font-size: 13px; padding-left: 20px;">
                    <li>Abra o Telegram e procure por <strong>@BotFather</strong></li>
                    <li>Envie <code>/newbot</code> e siga as instruções</li>
                    <li>Copie o <strong>token</strong> fornecido</li>
                    <li>Adicione o bot ao seu grupo/canal</li>
                    <li>Use <strong>@userinfobot</strong> para obter o Chat ID</li>
                    <li>Cole as informações acima e salve</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3>📊 Histórico de Alertas</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Tipo</th>
                        <th>Mensagem</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($alertas)): ?>
                    <tr>
                        <td colspan="4" class="text-center">Nenhum alerta enviado ainda</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($alertas as $alerta): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($alerta['created_at'])); ?></td>
                            <td>
                                <?php
                                $tipos = [
                                    'venda' => '💰 Venda',
                                    'volume_critico' => '⚠️ Volume Crítico',
                                    'vencimento_10d' => '🟡 Vence em 10 dias',
                                    'vencimento_2d' => '🟠 Vence em 2 dias',
                                    'vencido' => '🔴 Vencido'
                                ];
                                echo $tipos[$alerta['type']] ?? $alerta['type'];
                                ?>
                            </td>
                            <td style="max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?php echo strip_tags($alerta['message']); ?>
                            </td>
                            <td>
                                <?php if ($alerta['status'] === 'sent'): ?>
                                    <span class="badge badge-success">✓ Enviado</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">✗ Falha</span>
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

<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
