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
    
    <!-- Font Awesome para ícones profissionais -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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
                            <i class="fas fa-chart-line"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo SITE_URL; ?>/admin/bebidas.php" class="<?php echo ($current_page ?? '') == 'bebidas' ? 'active' : ''; ?>">
                            <i class="fas fa-beer"></i>
                            <span>Bebidas</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo SITE_URL; ?>/admin/taps.php" class="<?php echo ($current_page ?? '') == 'taps' ? 'active' : ''; ?>">
                            <i class="fas fa-faucet"></i>
                            <span>TAPs</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo SITE_URL; ?>/admin/pagamentos.php" class="<?php echo ($current_page ?? '') == 'pagamentos' ? 'active' : ''; ?>">
                            <i class="fas fa-credit-card"></i>
                            <span>Pagamentos</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo SITE_URL; ?>/admin/pedidos.php" class="<?php echo ($current_page ?? '') == 'pedidos' ? 'active' : ''; ?>">
                            <i class="fas fa-shopping-cart"></i>
                            <span>Pedidos</span>
                        </a>
                    </li>
                    <?php if (isAdminGeral()): ?>
                    <li>
                        <a href="<?php echo SITE_URL; ?>/admin/usuarios.php" class="<?php echo ($current_page ?? '') == 'usuarios' ? 'active' : ''; ?>">
                            <i class="fas fa-users"></i>
                            <span>Usuários</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo SITE_URL; ?>/admin/estabelecimentos.php" class="<?php echo ($current_page ?? '') == 'estabelecimentos' ? 'active' : ''; ?>">
                            <i class="fas fa-store"></i>
                            <span>Estabelecimentos</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo SITE_URL; ?>/admin/logs.php" class="<?php echo ($current_page ?? '') == 'logs' ? 'active' : ''; ?>">
                            <i class="fas fa-file-alt"></i>
                            <span>Logs</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <!-- Menu Financeiro -->
	                    <li class="menu-item-has-children">
	                        <a href="#" class="<?php echo in_array($current_page ?? '', ['financeiro_taxas', 'financeiro_contas']) ? 'active' : ''; ?>" onclick="toggleSubmenu(event, this)">
	                            <i class="fas fa-wallet"></i>
	                            <span>Financeiro</span>
	                            <i class="fas fa-chevron-down arrow"></i>
	                        </a>
	                        <ul class="submenu <?php echo in_array($current_page ?? '', ['financeiro_taxas', 'financeiro_contas']) ? 'show' : ''; ?>">
	                            <li>
	                                <a href="<?php echo SITE_URL; ?>/admin/financeiro_taxas.php" class="<?php echo ($current_page ?? '') == 'financeiro_taxas' ? 'active' : ''; ?>">
	                                    <i class="fas fa-percentage"></i>
	                                    <span>Taxas de Juros</span>
	                                </a>
	                            </li>
	                            <li>
	                                <a href="<?php echo SITE_URL; ?>/admin/financeiro_contas.php" class="<?php echo ($current_page ?? '') == 'financeiro_contas' ? 'active' : ''; ?>">
	                                    <i class="fas fa-file-invoice-dollar"></i>
	                                    <span>Contas a Pagar</span>
	                                </a>
	                            </li>
	                        </ul>
	                    </li>
	                    <?php if (isAdminGeral()): ?>
	                    <li>
	                        <a href="<?php echo SITE_URL; ?>/admin/email_config.php" class="<?php echo ($current_page ?? '') == 'email_config' ? 'active' : ''; ?>">
	                            <i class="fas fa-envelope"></i>
	                            <span>Config. E-mail</span>
	                        </a>
	                    </li>
	                    <?php endif; ?>
                    <li>
                        <a href="<?php echo SITE_URL; ?>/admin/telegram.php" class="<?php echo ($current_page ?? '') == 'telegram' ? 'active' : ''; ?>">
                            <i class="fab fa-telegram"></i>
                            <span>Telegram</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo SITE_URL; ?>/admin/logout.php" class="logout-link">
                            <i class="fas fa-sign-out-alt"></i>
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
                    <button class="menu-toggle" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h2><?php echo $page_title ?? 'Dashboard'; ?></h2>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <div class="user-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="user-details">
                            <div class="user-name"><?php echo $_SESSION['user_name']; ?></div>
                            <div class="user-role"><?php echo getUserType($_SESSION['user_type']); ?></div>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Content Area -->
            <main class="content-area">
