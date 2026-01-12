<?php
/**
 * Sistema de Permissões por Página
 * Chopp On Tap - v3.1
 */

require_once __DIR__ . '/config.php';

/**
 * Verifica se o usuário tem permissão para acessar uma página
 */
function hasPagePermission($page_key, $action = 'view') {
    // Admin Geral sempre tem acesso total
    if (isAdminGeral()) {
        return true;
    }
    
    // Verificar se está logado
    if (!isLoggedIn()) {
        return false;
    }
    
    $user_id = $_SESSION['user_id'];
    $conn = getDBConnection();
    
    try {
        // Buscar permissão do usuário para a página
        $stmt = $conn->prepare("
            SELECT up.can_view, up.can_create, up.can_edit, up.can_delete, sp.admin_only
            FROM user_permissions up
            INNER JOIN system_pages sp ON up.page_id = sp.id
            WHERE up.user_id = ? AND sp.page_key = ?
        ");
        $stmt->execute([$user_id, $page_key]);
        $permission = $stmt->fetch();
        
        // Se não encontrou permissão, negar acesso
        if (!$permission) {
            return false;
        }
        
        // Páginas admin_only são exclusivas do Admin Geral
        if ($permission['admin_only'] == 1) {
            return false;
        }
        
        // Verificar ação específica
        switch ($action) {
            case 'view':
                return $permission['can_view'] == 1;
            case 'create':
                return $permission['can_create'] == 1;
            case 'edit':
                return $permission['can_edit'] == 1;
            case 'delete':
                return $permission['can_delete'] == 1;
            default:
                return false;
        }
    } catch (Exception $e) {
        Logger::error("Erro ao verificar permissão", [
            'user_id' => $user_id,
            'page_key' => $page_key,
            'action' => $action,
            'error' => $e->getMessage()
        ]);
        return false;
    }
}

/**
 * Requer permissão para acessar uma página
 * Redireciona para dashboard se não tiver permissão
 */
function requirePagePermission($page_key, $action = 'view') {
    if (!hasPagePermission($page_key, $action)) {
        $_SESSION['error'] = 'Você não tem permissão para acessar esta página.';
        redirect('admin/dashboard.php');
        exit;
    }
}

/**
 * Obtém todas as páginas do sistema
 */
function getAllPages() {
    $conn = getDBConnection();
    $stmt = $conn->query("
        SELECT * FROM system_pages 
        WHERE status = 1 
        ORDER BY page_category, page_name
    ");
    return $stmt->fetchAll();
}

/**
 * Obtém páginas por categoria
 */
function getPagesByCategory() {
    $pages = getAllPages();
    $categorized = [];
    
    foreach ($pages as $page) {
        $category = $page['page_category'] ?? 'Outros';
        if (!isset($categorized[$category])) {
            $categorized[$category] = [];
        }
        $categorized[$category][] = $page;
    }
    
    return $categorized;
}

/**
 * Obtém permissões de um usuário
 */
function getUserPermissions($user_id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT sp.*, 
               COALESCE(up.can_view, 0) as can_view,
               COALESCE(up.can_create, 0) as can_create,
               COALESCE(up.can_edit, 0) as can_edit,
               COALESCE(up.can_delete, 0) as can_delete,
               up.id as permission_id
        FROM system_pages sp
        LEFT JOIN user_permissions up ON sp.id = up.page_id AND up.user_id = ?
        WHERE sp.status = 1
        ORDER BY sp.page_category, sp.page_name
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

/**
 * Salva permissões de um usuário
 */
function saveUserPermissions($user_id, $permissions) {
    $conn = getDBConnection();
    
    try {
        $conn->beginTransaction();
        
        // Limpar permissões existentes (exceto para Admin Geral)
        $stmt = $conn->prepare("DELETE FROM user_permissions WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Inserir novas permissões
        $stmt = $conn->prepare("
            INSERT INTO user_permissions (user_id, page_id, can_view, can_create, can_edit, can_delete)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($permissions as $page_id => $perms) {
            $can_view = isset($perms['view']) ? 1 : 0;
            $can_create = isset($perms['create']) ? 1 : 0;
            $can_edit = isset($perms['edit']) ? 1 : 0;
            $can_delete = isset($perms['delete']) ? 1 : 0;
            
            $stmt->execute([$user_id, $page_id, $can_view, $can_create, $can_edit, $can_delete]);
        }
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollBack();
        Logger::error("Erro ao salvar permissões", [
            'user_id' => $user_id,
            'error' => $e->getMessage()
        ]);
        return false;
    }
}

/**
 * Obtém páginas que o usuário tem acesso
 */
function getUserAccessiblePages($user_id) {
    // Admin Geral tem acesso a tudo
    if (isAdminGeral()) {
        return getAllPages();
    }
    
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT sp.*
        FROM system_pages sp
        INNER JOIN user_permissions up ON sp.id = up.page_id
        WHERE up.user_id = ? AND up.can_view = 1 AND sp.status = 1
        ORDER BY sp.page_category, sp.page_name
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

/**
 * Verifica se o usuário pode ver um botão de ação
 */
function canShowActionButton($page_key, $action) {
    return hasPagePermission($page_key, $action);
}

/**
 * Cria permissões padrão para um novo usuário baseado no tipo
 */
function createDefaultPermissions($user_id, $user_type) {
    $conn = getDBConnection();
    
    try {
        // Admin Geral (type = 1) - Acesso total
        if ($user_type == 1) {
            $stmt = $conn->prepare("
                INSERT INTO user_permissions (user_id, page_id, can_view, can_create, can_edit, can_delete)
                SELECT ?, id, 1, 1, 1, 1 FROM system_pages
                ON DUPLICATE KEY UPDATE can_view=1, can_create=1, can_edit=1, can_delete=1
            ");
            $stmt->execute([$user_id]);
        }
        // Gerente (type = 2) - Acesso a páginas operacionais e financeiras
        elseif ($user_type == 2) {
            $stmt = $conn->prepare("
                INSERT INTO user_permissions (user_id, page_id, can_view, can_create, can_edit, can_delete)
                SELECT ?, id, 1, 1, 1, 0 FROM system_pages WHERE admin_only = 0
                ON DUPLICATE KEY UPDATE can_view=1, can_create=1, can_edit=1, can_delete=0
            ");
            $stmt->execute([$user_id]);
        }
        // Operador (type = 3) - Acesso a páginas operacionais (sem criar/excluir)
        elseif ($user_type == 3) {
            $stmt = $conn->prepare("
                INSERT INTO user_permissions (user_id, page_id, can_view, can_create, can_edit, can_delete)
                SELECT ?, id, 1, 0, 1, 0 FROM system_pages 
                WHERE admin_only = 0 AND page_category IN ('Operacional', 'Geral')
                ON DUPLICATE KEY UPDATE can_view=1, can_create=0, can_edit=1, can_delete=0
            ");
            $stmt->execute([$user_id]);
        }
        // Visualizador (type = 4) - Apenas visualização
        else {
            $stmt = $conn->prepare("
                INSERT INTO user_permissions (user_id, page_id, can_view, can_create, can_edit, can_delete)
                SELECT ?, id, 1, 0, 0, 0 FROM system_pages 
                WHERE admin_only = 0 AND page_key IN ('dashboard', 'bebidas', 'taps', 'pedidos')
                ON DUPLICATE KEY UPDATE can_view=1, can_create=0, can_edit=0, can_delete=0
            ");
            $stmt->execute([$user_id]);
        }
        
        return true;
    } catch (Exception $e) {
        Logger::error("Erro ao criar permissões padrão", [
            'user_id' => $user_id,
            'user_type' => $user_type,
            'error' => $e->getMessage()
        ]);
        return false;
    }
}

/**
 * Atualiza menu lateral baseado nas permissões do usuário
 */
function getMenuItems() {
    $user_id = $_SESSION['user_id'] ?? null;
    
    if (!$user_id) {
        return [];
    }
    
    // Admin Geral vê tudo
    if (isAdminGeral()) {
        return getAllPages();
    }
    
    // Outros usuários veem apenas o que têm permissão
    return getUserAccessiblePages($user_id);
}
