<?php
// Ativar exibição de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não mostrar na tela
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

$page_title = 'Configuração Asaas';
$current_page = 'asaas_config';

try {
    require_once '../includes/config.php';
    require_once '../includes/auth.php';
    requireAdminGeral();
} catch (Exception $e) {
    die('Erro ao carregar sistema: ' . $e->getMessage());
}

$conn = getDBConnection();
$success = '';
$error = '';

// Verificar se tabela existe (PDO)
try {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'asaas_config'");
    $tableExists = $tableCheck && $tableCheck->rowCount() > 0;
    
    if (!$tableExists) {
        $error = 'ERRO: Tabela asaas_config não existe. Execute o SQL: /sql/add_asaas_integration.sql';
    }
} catch (Exception $e) {
    $error = 'Erro ao verificar tabela: ' . $e->getMessage();
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    $action = $_POST['action'] ?? 'save';
    
    if ($action === 'save') {
        $estabelecimento_id = (int)$_POST['estabelecimento_id'];
        $asaas_api_key = sanitize($_POST['asaas_api_key']);
        $asaas_webhook_token = sanitize($_POST['asaas_webhook_token'] ?? '');
        $ambiente = sanitize($_POST['ambiente']);
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        
        // Validar formato da API Key
        $prefixo_esperado = ($ambiente === 'production') ? '$aact_prod_' : '$aact_hmlg_';
        if (strpos($asaas_api_key, $prefixo_esperado) !== 0) {
            $error = "ATENÇÃO: A API Key não parece ser do ambiente {$ambiente}. " .
                     "Chaves de produção começam com \$aact_prod_ e de sandbox com \$aact_hmlg_";
        } else {
            try {
                $stmt = $conn->prepare("
                    INSERT INTO asaas_config 
                    (estabelecimento_id, asaas_api_key, asaas_webhook_token, ambiente, ativo)
                    VALUES (:estabelecimento_id, :asaas_api_key, :asaas_webhook_token, :ambiente, :ativo)
                    ON DUPLICATE KEY UPDATE
                        asaas_api_key = VALUES(asaas_api_key),
                        asaas_webhook_token = VALUES(asaas_webhook_token),
                        ambiente = VALUES(ambiente),
                        ativo = VALUES(ativo)
                ");
                
                $stmt->execute([
                    ':estabelecimento_id' => $estabelecimento_id,
                    ':asaas_api_key' => $asaas_api_key,
                    ':asaas_webhook_token' => $asaas_webhook_token,
                    ':ambiente' => $ambiente,
                    ':ativo' => $ativo
                ]);
                
                // Testar configuração
                require_once '../includes/AsaasAPI.php';
                try {
                    $asaas = new AsaasAPI($conn, $estabelecimento_id);
                    if ($asaas->validarConfiguracao()) {
                        $success = 'Configuração do Asaas salva e validada com sucesso!';
                    } else {
                        $error = 'Configuração salva, mas a validação com a API Asaas falhou. Verifique a API Key.';
                    }
                } catch (Exception $e) {
                    $error = 'Configuração salva, mas erro ao validar: ' . $e->getMessage();
                }
                
            } catch (Exception $e) {
                $error = 'Erro ao salvar configuração: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        
        try {
            $stmt = $conn->prepare("DELETE FROM asaas_config WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $success = 'Configuração excluída com sucesso!';
        } catch (Exception $e) {
            $error = 'Erro ao excluir configuração: ' . $e->getMessage();
        }
    } elseif ($action === 'test') {
        $estabelecimento_id = (int)$_POST['estabelecimento_id'];
        
        require_once '../includes/AsaasAPI.php';
        try {
            $asaas = new AsaasAPI($conn, $estabelecimento_id);
            if ($asaas->validarConfiguracao()) {
                $success = 'Conexão com Asaas validada com sucesso!';
            } else {
                $error = 'Falha ao validar conexão com Asaas. Verifique a API Key.';
            }
        } catch (Exception $e) {
            $error = 'Erro ao testar conexão: ' . $e->getMessage();
        }
    }
}

// Listar estabelecimentos
$estabelecimentos = [];
try {
    $result = $conn->query("SELECT id, name FROM estabelecimentos WHERE status = 1 ORDER BY name");
    if ($result) {
        $estabelecimentos = $result->fetchAll();
    }
} catch (Exception $e) {
    $estabelecimentos = []; // Garantir que sempre seja array
}

// Listar configurações existentes
$configuracoes = [];
if ($tableExists) {
    try {
        $result = $conn->query("
            SELECT ac.*, e.name as estabelecimento_nome
            FROM asaas_config ac
            INNER JOIN estabelecimentos e ON ac.estabelecimento_id = e.id
            ORDER BY e.name
        ");
        if ($result) {
            $configuracoes = $result->fetchAll();
        }
    } catch (Exception $e) {
        // Silencioso
    }
}

// Gerar URL do webhook
$webhook_url = SITE_URL . '/webhook/asaas_webhook.php';

require_once '../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-dollar-sign"></i> Configuração Asaas</h1>
    <button class="btn btn-primary" onclick="openModalAsaas()">
        <i class="fas fa-plus"></i> Nova Configuração
    </button>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<!-- Card de informações -->
<div class="card mb-3">
    <div class="card-header bg-info text-white">
        <h3 class="card-title mb-0"><i class="fas fa-info-circle"></i> Informações Importantes</h3>
    </div>
    <div class="card-body">
        <h5>Como obter sua API Key do Asaas:</h5>
        <ol>
            <li>Acesse <a href="https://www.asaas.com/" target="_blank">www.asaas.com</a> e faça login</li>
            <li>Vá em <strong>Minha Conta → Integrações</strong></li>
            <li>Clique em <strong>Gerar nova chave de API</strong></li>
            <li>Copie a chave (ela será exibida apenas uma vez!)</li>
            <li>Cole a chave no campo abaixo</li>
        </ol>
        
        <h5 class="mt-3">Ambientes:</h5>
        <ul>
            <li><strong>Sandbox (Testes)</strong>: Use para testes. Chaves começam com <code>$aact_hmlg_</code></li>
            <li><strong>Produção</strong>: Use para pagamentos reais. Chaves começam com <code>$aact_prod_</code></li>
        </ul>
        
        <h5 class="mt-3">URL do Webhook:</h5>
        <div class="input-group">
            <input type="text" class="form-control" value="<?php echo $webhook_url; ?>" readonly id="webhook_url_copy">
            <div class="input-group-append">
                <button class="btn btn-outline-secondary" type="button" onclick="copiarWebhookURL()">
                    <i class="fas fa-copy"></i> Copiar
                </button>
            </div>
        </div>
        <small class="text-muted">Configure esta URL no painel do Asaas para receber notificações de pagamento</small>
        
        <h5 class="mt-3">Documentação:</h5>
        <p><a href="https://docs.asaas.com/" target="_blank"><i class="fas fa-external-link-alt"></i> Documentação Oficial da API Asaas</a></p>
    </div>
</div>

<!-- Card de configurações -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Configurações do Asaas</h3>
    </div>
    <div class="card-body">
        <?php if (empty($configuracoes)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Nenhuma configuração cadastrada. Clique em "Nova Configuração" para adicionar.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Estabelecimento</th>
                            <th>Ambiente</th>
                            <th>API Key</th>
                            <th>Webhook Token</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($configuracoes as $config): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($config['estabelecimento_nome']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $config['ambiente'] === 'production' ? 'success' : 'warning'; ?>">
                                    <?php echo $config['ambiente'] === 'production' ? 'Produção' : 'Sandbox'; ?>
                                </span>
                            </td>
                            <td>
                                <code><?php echo substr($config['asaas_api_key'], 0, 20); ?>...</code>
                            </td>
                            <td>
                                <?php if ($config['asaas_webhook_token']): ?>
                                    <code><?php echo substr($config['asaas_webhook_token'], 0, 15); ?>...</code>
                                <?php else: ?>
                                    <span class="text-muted">Não configurado</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $config['ativo'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $config['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-success" onclick="testarAsaas(<?php echo $config['estabelecimento_id']; ?>)" title="Testar conexão">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button class="btn btn-sm btn-info" onclick='editarAsaas(<?php echo json_encode($config); ?>)' title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="excluirAsaas(<?php echo $config['id']; ?>, '<?php echo htmlspecialchars($config['estabelecimento_nome']); ?>')" title="Excluir">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de Configuração -->
<div id="asaasModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Nova Configuração Asaas</h2>
            <span class="close" onclick="closeModalAsaas()">&times;</span>
        </div>
        <form method="POST" id="asaasForm">
            <input type="hidden" name="action" value="save">
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="estabelecimento_id">Estabelecimento *</label>
                    <select name="estabelecimento_id" id="estabelecimento_id" class="form-control" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($estabelecimentos as $est): ?>
                            <option value="<?php echo $est['id']; ?>"><?php echo htmlspecialchars($est['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="ambiente">Ambiente *</label>
                    <select name="ambiente" id="ambiente" class="form-control" required>
                        <option value="sandbox">Sandbox (Testes)</option>
                        <option value="production">Produção</option>
                    </select>
                    <small class="form-text">
                        <strong>Sandbox:</strong> Para testes (chave começa com $aact_hmlg_)<br>
                        <strong>Produção:</strong> Para pagamentos reais (chave começa com $aact_prod_)
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="asaas_api_key">API Key *</label>
                    <input type="text" name="asaas_api_key" id="asaas_api_key" class="form-control" required 
                           placeholder="$aact_hmlg_... ou $aact_prod_...">
                    <small class="form-text">
                        Obtenha em: Asaas → Minha Conta → Integrações<br>
                        <strong>ATENÇÃO:</strong> A chave é exibida apenas uma vez na criação!
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="asaas_webhook_token">Webhook Token (Opcional)</label>
                    <input type="text" name="asaas_webhook_token" id="asaas_webhook_token" class="form-control" 
                           placeholder="Token para autenticação de webhooks">
                    <small class="form-text">
                        Token opcional para validar webhooks. Configure o mesmo token no painel do Asaas.
                    </small>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="ativo" id="ativo" value="1" checked>
                        Ativo
                    </label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModalAsaas()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Form de exclusão -->
<form id="formExcluir" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
</form>

<!-- Form de teste -->
<form id="formTestar" method="POST" style="display: none;">
    <input type="hidden" name="action" value="test">
    <input type="hidden" name="estabelecimento_id" id="test_estabelecimento_id">
</form>

<script>
function openModalAsaas() {
    document.getElementById('modalTitle').textContent = 'Nova Configuração Asaas';
    document.getElementById('asaasForm').reset();
    document.getElementById('ativo').checked = true;
    openModal('asaasModal');
}

function closeModalAsaas() {
    closeModal('asaasModal');
}

function editarAsaas(config) {
    document.getElementById('modalTitle').textContent = 'Editar Configuração Asaas';
    document.getElementById('estabelecimento_id').value = config.estabelecimento_id;
    document.getElementById('asaas_api_key').value = config.asaas_api_key;
    document.getElementById('asaas_webhook_token').value = config.asaas_webhook_token || '';
    document.getElementById('ambiente').value = config.ambiente;
    document.getElementById('ativo').checked = config.ativo == 1;
    openModal('asaasModal');
}

function excluirAsaas(id, nome) {
    if (confirm(`Tem certeza que deseja excluir a configuração do estabelecimento "${nome}"?`)) {
        document.getElementById('delete_id').value = id;
        document.getElementById('formExcluir').submit();
    }
}

function testarAsaas(estabelecimento_id) {
    if (confirm('Deseja testar a conexão com o Asaas?')) {
        document.getElementById('test_estabelecimento_id').value = estabelecimento_id;
        document.getElementById('formTestar').submit();
    }
}

function copiarWebhookURL() {
    const input = document.getElementById('webhook_url_copy');
    input.select();
    input.setSelectionRange(0, 99999); // Para mobile
    document.execCommand('copy');
    alert('URL do webhook copiada para a área de transferência!');
}
</script>

<?php require_once '../includes/footer.php'; ?>
