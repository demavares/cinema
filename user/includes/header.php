<?php
// ============================================
// MÓDULO DE USUARIO — HEADER / SIDEBAR (estilo admin)
// ============================================
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$siteConfig = getSiteConfig($pdo);
$siteName = $siteConfig['site_name'] ?? 'Cinema Pro';
$activePage = $activePage ?? 'account';

$userPageTitles = [
    'account' => 'Mi Cuenta',
    'tickets' => 'Mis Boletos',
    'promotions' => 'Promociones',
];
$userPageTitle = $userPageTitles[$activePage] ?? ucfirst($activePage);
$pageTitle = $pageTitle ?? ($userPageTitle . ' - ' . $siteName);

// CSS específico de cada página del módulo de usuario
$userPageStylesheets = [
    'account' => 'assets/css/account.css',
    'tickets' => 'assets/css/tickets.css',
    'promotions' => 'assets/css/promotions.css',
];

// CSRF para logout
$user_header_csrf_token = generateCSRFToken();

// Estado colapsado del sidebar (misma cookie que el admin, gestionada por admin.js)
$sidebarCollapsed = (($_COOKIE['admin_sidebar_collapsed'] ?? '') === 'true');

$siteRoot = dirname(__DIR__, 2);

// Datos del usuario (reutiliza $userAuth si la página lo cargó vía user_auth.php)
if (empty($layoutUser)) {
    $layoutUser = $userAuth ?? [];
    if (empty($layoutUser)) {
        try {
            $stmt = $pdo->prepare("SELECT id, name, email, avatar FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $layoutUser = $stmt->fetch() ?: ['name' => $_SESSION['user_name'] ?? 'Usuario', 'email' => $_SESSION['user_email'] ?? '', 'avatar' => null];
        } catch (Throwable $e) {
            $layoutUser = ['name' => $_SESSION['user_name'] ?? 'Usuario', 'email' => $_SESSION['user_email'] ?? '', 'avatar' => null];
        }
    }
}

// Ruta del avatar para mostrar en la interfaz
$userAvatarHref = '';
if (!empty($layoutUser['avatar'])) {
    $avatarFs = $siteRoot . '/' . $layoutUser['avatar'];
    if (is_file($avatarFs)) {
        $userAvatarHref = '../' . $layoutUser['avatar'] . '?v=' . filemtime($avatarFs);
    }
}

// Logo / favicon del sitio
$site_logo = $siteConfig['site_logo'] ?? '';
$site_logo_fs = (!empty($site_logo) && !preg_match('#^(https?:)?//#', $site_logo) && $site_logo[0] !== '/') ? $siteRoot . '/' . $site_logo : $site_logo;
$site_logo_href = (!empty($site_logo) && !preg_match('#^(https?:)?//#', $site_logo) && $site_logo[0] !== '/') ? '../' . $site_logo : $site_logo;

$favicon_path = $siteConfig['site_favicon'] ?? '';
$favicon_fs = '';
$favicon_href = $favicon_path;
if (!empty($favicon_path) && !preg_match('#^(https?:)?//#', $favicon_path) && $favicon_path[0] !== '/') {
    $favicon_fs = $siteRoot . '/' . $favicon_path;
    $favicon_href = '../' . $favicon_path;
} elseif (!empty($favicon_path)) {
    $favicon_fs = $favicon_path;
}
$hasFavicon = !empty($favicon_path) && is_file($favicon_fs);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <?php if ($hasFavicon): ?>
        <link rel="icon" type="<?= mime_content_type($favicon_fs) ?>" href="<?= htmlspecialchars($favicon_href) . '?v=' . filemtime($favicon_fs) ?>">
    <?php else: ?>
        <link rel="icon" type="image/png" href="../admin/img/favicon.png">
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Estilos del panel (reutiliza el CSS del admin) -->
    <link rel="stylesheet" href="../admin/css/admin.css">

    <!-- Estilos propios del módulo de usuario -->
    <link rel="stylesheet" href="assets/css/user.css">
    <?php if (isset($userPageStylesheets[$activePage])): ?>
        <link rel="stylesheet" href="<?= $userPageStylesheets[$activePage] ?>">
    <?php endif; ?>
</head>
<body>
    <!-- Overlay para móvil -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar (user) -->
    <aside class="sidebar <?= $sidebarCollapsed ? 'collapsed' : '' ?>" id="adminSidebar">
        <div class="sidebar-header">
            <a href="../index.php" class="sidebar-brand" title="<?= htmlspecialchars($siteName) ?>">
                <?php if (!empty($site_logo) && is_file($site_logo_fs)): ?>
                    <img src="<?= htmlspecialchars($site_logo_href) ?>" alt="<?= htmlspecialchars($siteName) ?>" class="sidebar-logo">
                <?php else: ?>
                    <span class="sidebar-brand-text"><?= htmlspecialchars($siteName) ?></span>
                <?php endif; ?>
            </a>
            <div class="sidebar-header-actions">
                <button class="sidebar-collapse-toggle" id="sidebarCollapseToggle" aria-label="Colapsar">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="sidebar-close" id="sidebarClose" aria-label="Cerrar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <div class="sidebar-user">
            <?php if ($userAvatarHref): ?>
                <img src="<?= htmlspecialchars($userAvatarHref) ?>" alt="Foto de perfil" class="sidebar-user-avatar">
            <?php else: ?>
                <div class="sidebar-user-avatar"><?= htmlspecialchars(strtoupper(substr($layoutUser['name'] ?? 'U', 0, 1))) ?></div>
            <?php endif; ?>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= htmlspecialchars($layoutUser['name'] ?? 'Usuario') ?></div>
                <div class="sidebar-user-role"><?= htmlspecialchars($layoutUser['email'] ?? '') ?></div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <ul class="sidebar-menu">
                <li>
                    <a href="account.php" class="sidebar-link <?= $activePage === 'account' ? 'active' : '' ?>" title="Mi Cuenta">
                        <i class="fas fa-user-cog"></i>
                        <span>Mi Cuenta</span>
                    </a>
                </li>
                <li>
                    <a href="tickets.php" class="sidebar-link <?= $activePage === 'tickets' ? 'active' : '' ?>" title="Mis Boletos">
                        <i class="fas fa-ticket-alt"></i>
                        <span>Mis Boletos</span>
                    </a>
                </li>
                <li>
                    <a href="promotions.php" class="sidebar-link <?= $activePage === 'promotions' ? 'active' : '' ?>" title="Promociones">
                        <i class="fas fa-tags"></i>
                        <span>Promociones</span>
                    </a>
                </li>
            </ul>
        </nav>

        <div class="sidebar-footer">
            <a href="../index.php" class="sidebar-link" target="_blank" title="Ver Cartelera">
                <i class="fas fa-film"></i>
                <span>Ver Cartelera</span>
            </a>
            <form action="../logout.php" method="POST" class="sidebar-logout-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($user_header_csrf_token) ?>">
                <button type="submit" class="sidebar-link" title="Cerrar Sesión">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Cerrar Sesión</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- Contenido principal -->
    <main class="admin-main <?= $sidebarCollapsed ? 'sidebar-collapsed' : '' ?>" id="adminMain">
        <header class="admin-topbar">
            <div class="topbar-left">
                <button class="topbar-toggle" id="sidebarToggle" aria-label="Abrir menú">
                    <i class="fas fa-bars"></i>
                </button>
                <button class="topbar-collapse-toggle" id="topbarCollapseToggle" aria-label="Colapsar">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <h2 class="topbar-title"><?= htmlspecialchars($userPageTitle) ?></h2>
            </div>
            <div class="topbar-right">
                <span class="topbar-user">
                    <?php if ($userAvatarHref): ?>
                        <img src="<?= htmlspecialchars($userAvatarHref) ?>" alt="Foto de perfil" class="topbar-avatar">
                    <?php else: ?>
                        <i class="fas fa-user-circle"></i>
                    <?php endif; ?>
                    <span><?= htmlspecialchars($layoutUser['name'] ?? $_SESSION['user_name'] ?? 'Usuario') ?></span>
                </span>
                <a href="../index.php" class="topbar-btn" target="_blank" title="Ver Cartelera">
                    <i class="fas fa-external-link-alt"></i>
                </a>
            </div>
        </header>

        <div class="admin-page-content">