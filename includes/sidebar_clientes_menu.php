<!-- 
    ADICIONE ESTE CÓDIGO NO ARQUIVO admin/includes/sidebar.php
    LOGO APÓS O MENU "LOGS" (ou onde preferir)
-->

<!-- Menu Clientes -->
<li class="menu-item <?php echo in_array($current_page, ['clientes', 'cashback']) ? 'active' : ''; ?>">
    <a href="#" onclick="toggleSubmenu(event, this)">
        <i class="fas fa-users"></i>
        <span>Clientes</span>
        <i class="fas fa-chevron-down arrow"></i>
    </a>
    <ul class="submenu">
        <li>
            <a href="clientes.php" class="<?php echo $current_page == 'clientes' ? 'active' : ''; ?>">
                <i class="fas fa-list"></i>
                <span>Lista de Clientes</span>
            </a>
        </li>
        <li>
            <a href="cashback_regras.php" class="<?php echo $current_page == 'cashback' ? 'active' : ''; ?>">
                <i class="fas fa-coins"></i>
                <span>Regras de Cashback</span>
            </a>
        </li>
    </ul>
</li>

<!--
    EXEMPLO DE POSICIONAMENTO NO MENU:
    
    ... (outros menus acima)
    
    <li class="menu-item">
        <a href="logs.php">
            <i class="fas fa-file-alt"></i>
            <span>Logs</span>
        </a>
    </li>
    
    <!-- ADICIONE AQUI O MENU DE CLIENTES -->
    <li class="menu-item <?php echo in_array($current_page, ['clientes', 'cashback']) ? 'active' : ''; ?>">
        <a href="#" onclick="toggleSubmenu(event, this)">
            <i class="fas fa-users"></i>
            <span>Clientes</span>
            <i class="fas fa-chevron-down arrow"></i>
        </a>
        <ul class="submenu">
            <li>
                <a href="clientes.php" class="<?php echo $current_page == 'clientes' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i>
                    <span>Lista de Clientes</span>
                </a>
            </li>
            <li>
                <a href="cashback_regras.php" class="<?php echo $current_page == 'cashback' ? 'active' : ''; ?>">
                    <i class="fas fa-coins"></i>
                    <span>Regras de Cashback</span>
                </a>
            </li>
        </ul>
    </li>
    
    ... (outros menus abaixo)
-->
