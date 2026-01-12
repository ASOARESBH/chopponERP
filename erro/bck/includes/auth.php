<?php
/**
 * Funções de Autenticação
 */

require_once __DIR__ . '/config.php';

/**
 * Realiza login do usuário
 */
function login($email, $password) {
    Logger::auth("Tentativa de login", ['email' => $email]);
    
    $conn = getDBConnection();
    
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        Logger::debug("Usuário encontrado no banco", [
            'email' => $email,
            'user_found' => !empty($user),
            'user_id' => $user['id'] ?? null
        ]);
        
        if (!$user) {
            Logger::auth("Login falhou: Usuário não encontrado", ['email' => $email]);
            return false;
        }
        
        // Log do hash armazenado (apenas para debug)
        Logger::debug("Verificando senha", [
            'email' => $email,
            'hash_stored' => substr($user['password'], 0, 20) . '...',
            'password_length' => strlen($password)
        ]);
        
        // Verificar senha
        $passwordValid = password_verify($password, $user['password']);
        
        Logger::debug("Resultado da verificação de senha", [
            'email' => $email,
            'password_valid' => $passwordValid
        ]);
        
        if ($passwordValid) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_type'] = $user['type'];
            $_SESSION['login_time'] = time();
            
            // Se não for admin geral, buscar estabelecimento padrão
            if ($user['type'] > 1) {
                $stmt = $conn->prepare("SELECT estabelecimento_id FROM user_estabelecimento WHERE user_id = ? AND status = 1 LIMIT 1");
                $stmt->execute([$user['id']]);
                $userEstab = $stmt->fetch();
                if ($userEstab) {
                    $_SESSION['estabelecimento_id'] = $userEstab['estabelecimento_id'];
                }
            }
            
            Logger::auth("Login bem-sucedido", [
                'user_id' => $user['id'],
                'email' => $email,
                'type' => $user['type']
            ]);
            
            return true;
        }
        
        Logger::auth("Login falhou: Senha incorreta", ['email' => $email]);
        return false;
        
    } catch (Exception $e) {
        Logger::error("Erro no processo de login", [
            'email' => $email,
            'error' => $e->getMessage()
        ]);
        return false;
    }
}

/**
 * Realiza logout do usuário
 */
function logout() {
    session_unset();
    session_destroy();
    redirect('index.php');
}

/**
 * Verifica se usuário está autenticado
 */
function requireAuth() {
    if (!isLoggedIn()) {
        redirect('index.php');
    }
}

/**
 * Verifica se usuário é admin geral
 */
function requireAdminGeral() {
    requireAuth();
    if (!isAdminGeral()) {
        redirect('admin/dashboard.php');
    }
}

/**
 * Obtém dados do usuário logado
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Obtém estabelecimentos do usuário
 */
function getUserEstabelecimentos($user_id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT e.* 
        FROM estabelecimentos e
        INNER JOIN user_estabelecimento ue ON e.id = ue.estabelecimento_id
        WHERE ue.user_id = ? AND ue.status = 1
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

/**
 * Define estabelecimento ativo na sessão
 */
function setEstabelecimento($estabelecimento_id) {
    $_SESSION['estabelecimento_id'] = $estabelecimento_id;
}

/**
 * Obtém ID do estabelecimento ativo
 */
function getEstabelecimentoId() {
    return $_SESSION['estabelecimento_id'] ?? null;
}
