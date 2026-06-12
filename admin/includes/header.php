<?php
/**
 * SiteCatalogo - Admin Header
 */
$admin_nome = $_SESSION['admin_nome'] ?? 'Admin';
$admin_nivel = $_SESSION['admin_nivel'] ?? 'admin';
$admin_avatar = $_SESSION['admin_avatar'] ?? '';
$orcamentos_pendentes = count_orcamentos_pendentes();

// Emails nao lidos
$emails_nao_lidos = 0;
try {
    $emails_nao_lidos = (int)db()->query("SELECT COUNT(*) FROM " . table('emails') . " WHERE pasta = 'inbox' AND status = 'nao_lido'")->fetchColumn();
} catch (Exception $e) {
    $emails_nao_lidos = 0;
}

$site_name = get_config('site_name', 'SiteCatalogo');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Painel'; ?> - <?php echo sanitize($site_name); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<div class="admin-wrapper">
    <!-- Sidebar -->
    <aside class="admin-sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="./" class="sidebar-logo">
                <svg width="32" height="32" viewBox="0 0 36 36" fill="none">
                    <rect width="36" height="36" rx="9" fill="#3b82f6"/>
                    <path d="M10 24V14l8-5 8 5v10H10z" stroke="white" stroke-width="2" fill="none"/>
                    <circle cx="18" cy="19" r="2.5" fill="white"/>
                </svg>
                <span><?php echo sanitize($site_name); ?></span>
            </a>
            <button class="sidebar-close" id="sidebarClose">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <nav class="sidebar-nav">
            <a href="./" class="nav-link <?php echo active_class($current_page, 'index'); ?>">
                <i class="fas fa-chart-pie"></i>
                <span>Dashboard</span>
            </a>

            <div class="nav-divider"></div>

            <a href="produtos.php" class="nav-link <?php echo active_class($current_page, 'produtos'); ?>">
                <i class="fas fa-box-open"></i>
                <span>Produtos</span>
            </a>
            <a href="categorias.php" class="nav-link <?php echo active_class($current_page, 'categorias'); ?>">
                <i class="fas fa-tags"></i>
                <span>Categorias</span>
            </a>
            <a href="estoque.php" class="nav-link <?php echo active_class($current_page, 'estoque'); ?>">
                <i class="fas fa-warehouse"></i>
                <span>Estoque</span>
            </a>
            <a href="banners.php" class="nav-link <?php echo active_class($current_page, 'banners'); ?>">
                <i class="fas fa-image"></i>
                <span>Banners</span>
            </a>

            <div class="nav-divider"></div>

            <a href="orcamentos.php" class="nav-link <?php echo active_class($current_page, 'orcamentos'); ?>">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>Orcamentos</span>
                <?php if ($orcamentos_pendentes > 0): ?>
                <span class="nav-badge"><?php echo $orcamentos_pendentes; ?></span>
                <?php endif; ?>
            </a>
            <a href="clientes.php" class="nav-link <?php echo active_class($current_page, 'clientes'); ?>">
                <i class="fas fa-users"></i>
                <span>Clientes</span>
            </a>

            <div class="nav-divider"></div>

            <a href="email.php" class="nav-link <?php echo active_class($current_page, 'email'); ?>">
                <i class="fas fa-envelope"></i>
                <span>Email</span>
                <?php if ($emails_nao_lidos > 0): ?>
                <span class="nav-badge"><?php echo $emails_nao_lidos; ?></span>
                <?php endif; ?>
            </a>

            <div class="nav-divider"></div>

            <a href="usuarios.php" class="nav-link <?php echo active_class($current_page, 'usuarios'); ?>">
                <i class="fas fa-user-shield"></i>
                <span>Usuarios</span>
            </a>
            <a href="configuracoes.php" class="nav-link <?php echo active_class($current_page, 'configuracoes'); ?>">
                <i class="fas fa-cog"></i>
                <span>Configuracoes</span>
            </a>
            <a href="seo.php" class="nav-link <?php echo active_class($current_page, 'seo'); ?>">
                <i class="fas fa-search"></i>
                <span>SEO</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <a href="../" target="_blank" class="nav-link">
                <i class="fas fa-external-link-alt"></i>
                <span>Ver Site</span>
            </a>
            <a href="logout.php" class="nav-link text-danger">
                <i class="fas fa-sign-out-alt"></i>
                <span>Sair</span>
            </a>
        </div>
    </aside>

    <!-- Main -->
    <div class="admin-main">
        <!-- Topbar -->
        <header class="admin-topbar">
            <button class="menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
            </button>

            <div class="topbar-right">
                <div class="dropdown">
                    <button class="user-menu" id="userMenu">
                        <div class="user-avatar">
                            <?php if ($admin_avatar): ?>
                            <img src="<?php echo uploads_url($admin_avatar); ?>" alt="">
                            <?php else: ?>
                            <i class="fas fa-user"></i>
                            <?php endif; ?>
                        </div>
                        <div class="user-info">
                            <span class="user-name"><?php echo sanitize($admin_nome); ?></span>
                            <span class="user-role"><?php echo ucfirst($admin_nivel); ?></span>
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu" id="userDropdown">
                        <a href="perfil.php"><i class="fas fa-user"></i> Meu Perfil</a>
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <div class="admin-content">
            <?php echo show_flash(); ?>