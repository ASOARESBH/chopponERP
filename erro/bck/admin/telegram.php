<?php
$page_title = 'Configuração Telegram';
$current_page = 'telegram';
require_once '../includes/header.php';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $bot_token = trim($_POST['bot_token']);
        $chat_id = trim($_POST['chat_id']);
        $status = isset($_POST['status']) ? 1 : 0;
        $estabelecimento_id = getEstabelecimentoId();
        
        // Verificar se já existe configuração
        $stmt = $conn->prepare("SELECT id FROM telegram_config WHERE estabelecimento_id = ?");
        $stmt->execute([$estabelecimento_id]);
        $config_exists = $stmt->fetch();
        
        if ($config_exists) {
            // Atualizar
            $stmt = $conn->prepare("
                UPDATE telegram_config 
                SET bot_token = ?, chat_id = ?, status = ?, updated_at = NOW()
                WHERE estabelecimento_id = ?
            ");
            $stmt->execute([$bot_token, $chat_id, $status, $estabelecimento_id]);
            $success_message = "Configuração atualizada com sucesso!";
        } else {
            // Inserir
            $stmt = $conn->prepare("
                INSERT INTO telegram_config (estabelecimento_id, bot_token, chat_id, status, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$estabelecimento_id, $bot_token, $chat_id, $status]);
            $success_message = "Configuração salva com sucesso!";
        }
        
        Logger::info("Configuração Telegram atualizada", [
            'estabelecimento_id' => $estabelecimento_id,
            'user_id' => $_SESSION['user_id']
        ]);
        
    } catch (Exception $e) {
        $error_message = "Erro ao salvar configuração: " . $e->getMessage();
        Logger::error("Erro ao salvar configuração Telegram", [
            'error' => $e->getMessage(),
            'user_id' => $_SESSION['user_id']
        ]);
    }
}

// Buscar configuração atual
$estabelecimento_id = getEstabelecimentoId();
$stmt = $conn->prepare("SELECT * FROM telegram_config WHERE estabelecimento_id = ?");
$stmt->execute([$estabelecimento_id]);
$config = $stmt->fetch();
?>

<div class="page-header">
    <h1>⚙️ Configuração do Telegram</h1>
</div>

<?php if (isset($success_message)): ?>
<div class="alert alert-success"><?php echo $success_message; ?></div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
<div class="alert alert-danger"><?php echo $error_message; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3>Configurar Bot do Telegram</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="bot_token">Bot Token *</label>
                        <input type="text" 
                               class="form-control" 
                               id="bot_token" 
                               name="bot_token" 
                               value="<?php echo $config['bot_token'] ?? ''; ?>"
                               placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz"
                               required>
                        <small class="form-text text-muted">
                            Token fornecido pelo @BotFather ao criar o bot
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="chat_id">Chat ID *</label>
                        <input type="text" 
                               class="form-control" 
                               id="chat_id" 
                               name="chat_id" 
                               value="<?php echo $config['chat_id'] ?? ''; ?>"
                               placeholder="-1001234567890"
                               required>
                        <small class="form-text text-muted">
                            ID do chat/grupo onde as notificações serão enviadas
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-label">
                            <input type="checkbox" 
                                   id="status" 
                                   name="status" 
                                   <?php echo ($config['status'] ?? 1) ? 'checked' : ''; ?>>
                            <label for="status">Ativar notificações</label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            💾 Salvar Configuração
                        </button>
                        <button type="button" class="btn btn-success" onclick="testarTelegram()">
                            🧪 Enviar Mensagem de Teste
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3>📚 Como Configurar</h3>
            </div>
            <div class="card-body">
                <h4>1. Criar o Bot</h4>
                <ol>
                    <li>Abra o Telegram</li>
                    <li>Busque por <code>@BotFather</code></li>
                    <li>Envie <code>/newbot</code></li>
                    <li>Siga as instruções</li>
                    <li>Copie o <strong>Token</strong></li>
                </ol>
                
                <h4>2. Obter Chat ID</h4>
                <ol>
                    <li>Adicione o bot ao grupo/chat</li>
                    <li>Envie uma mensagem qualquer</li>
                    <li>Acesse:<br>
                        <code style="font-size: 10px;">
                            api.telegram.org/bot<br>SEU_TOKEN/getUpdates
                        </code>
                    </li>
                    <li>Copie o <strong>chat.id</strong></li>
                </ol>
                
                <h4>3. Testar</h4>
                <p>Após salvar, clique em <strong>"Enviar Mensagem de Teste"</strong> para verificar se está funcionando.</p>
                
                <div class="alert alert-info" style="font-size: 12px;">
                    <strong>💡 Dica:</strong> Use um grupo do Telegram para receber as notificações. Assim, toda a equipe fica informada!
                </div>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h3>📊 Status Atual</h3>
            </div>
            <div class="card-body">
                <?php if ($config): ?>
                    <p><strong>Bot Token:</strong><br>
                    <code><?php echo substr($config['bot_token'], 0, 20); ?>...</code></p>
                    
                    <p><strong>Chat ID:</strong><br>
                    <code><?php echo $config['chat_id']; ?></code></p>
                    
                    <p><strong>Status:</strong><br>
                    <span class="badge badge-<?php echo $config['status'] ? 'success' : 'danger'; ?>">
                        <?php echo $config['status'] ? 'Ativo' : 'Inativo'; ?>
                    </span></p>
                    
                    <p><strong>Última Atualização:</strong><br>
                    <?php echo date('d/m/Y H:i', strtotime($config['updated_at'] ?? $config['created_at'])); ?></p>
                <?php else: ?>
                    <p class="text-muted">Nenhuma configuração cadastrada ainda.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function testarTelegram() {
    const botToken = document.getElementById('bot_token').value;
    const chatId = document.getElementById('chat_id').value;
    
    if (!botToken || !chatId) {
        alert('Por favor, preencha o Bot Token e Chat ID antes de testar.');
        return;
    }
    
    if (!confirm('Deseja enviar uma mensagem de teste para o Telegram?')) {
        return;
    }
    
    // Mostrar loading
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '⏳ Enviando...';
    btn.disabled = true;
    
    // Enviar requisição
    fetch('<?php echo SITE_URL; ?>/api/test_telegram.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            bot_token: botToken,
            chat_id: chatId
        })
    })
    .then(response => response.json())
    .then(data => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        
        if (data.success) {
            alert('✅ Mensagem de teste enviada com sucesso!\n\nVerifique seu Telegram.');
        } else {
            alert('❌ Erro ao enviar mensagem:\n\n' + data.message);
        }
    })
    .catch(error => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        alert('❌ Erro ao enviar mensagem:\n\n' + error);
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
