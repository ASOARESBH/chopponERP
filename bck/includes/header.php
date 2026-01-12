<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
requireAuth();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? SITE_NAME; ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php if (isset($extra_head)) echo $extra_head; ?>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <img src="<?php echo SITE_URL; ?>/assets/images/logo.png" alt="<?php echo SITE_NAME; ?>">
            </div>
            <nav>
                <ul class="sidebar-menu">
                    <li>
                        <a href="<?php echo SITE_URL; ?>/admin/dashboard.php" class="<?php echo ($current_page ?? '') == 'dashboard' ? 'active' : ''; ?>">
                            <span class="icon">📊</span>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo SITE_URL; ?>/admin/bebidas.php" class="<?php echo ($current_page ?? '') == 'bebidas' ? 'active' : ''; ?>">
                            <span class="icon">🍺</span>
                            <span>Bebidas</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo SITE_URL; ?>/admin/taps.php" class="<?php echo ($current_page ?? '') == 'taps' ? 'active' : ''; ?>">
                            <span class="icon">🚰</span>
                            <span>TAPs</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo SITE_URL; ?>/admin/pagamentos.php" class="<?php echo ($current_page ?? '') == 'pagamentos' ? 'active' : ''; ?>">
                            <span class="icon">💳</span>
                            <span>Pagamentos</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo SITE_URL; ?>/admin/pedidos.php" class="<?php echo ($current_page ?? '') == 'pedidos' ? 'active' : ''; ?>">
                            <span class="icon">📋</span>
                            <span>Pedidos</span>
                        </a>
                    </li>
                    <?php if (isAdminGeral()): ?>
                    <li>
                        <a href="<?php echo SITE_URL; ?>/admin/usuarios.php" class="<?php echo ($current_page ?? '') == 'usuarios' ? 'active' : ''; ?>">
                            <span class="icon">👥</span>
                            <span>Usuários</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo SITE_URL; ?>/admin/estabelecimentos.php" class="<?php echo ($current_page ?? '') == 'estabelecimentos' ? 'active' : ''; ?>">
                            <span class="icon">🏪</span>
                            <span>Estabelecimentos</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo SITE_URL; ?>/admin/logs.php" class="<?php echo ($current_page ?? '') == 'logs' ? 'active' : ''; ?>">
                            <span class="icon">📋</span>
                            <span>Logs</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a href="<?php echo SITE_URL; ?>/admin/logout.php">
                            <span class="icon">🚪</span>
                            <span>Sair</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Topbar -->
            <header class="topbar">
                <div class="topbar-left">
                    <button class="menu-toggle" id="menuToggle">☰</button>
                    <h2><?php echo $page_title ?? 'Dashboard'; ?></h2>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <div style="font-weight: 600;"><?php echo $_SESSION['user_name']; ?></div>
                            <div style="font-size: 12px; color: var(--gray-600);">
                                <?php echo getUserType($_SESSION['user_type']); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Content Area -->
            <main class="content-area">
