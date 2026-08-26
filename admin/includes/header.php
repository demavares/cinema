<?php
// Incluir funciones de seguridad si existen
if (file_exists(__DIR__ . '/security.php')) {
    require_once __DIR__ . '/security.php';
}

$siteConfig = getSiteConfig($pdo);
$siteName = $siteConfig['site_name'] ?? 'Cinema Pro';
$pageTitle = $pageTitle ?? "Panel de Control - " . $siteName;
$activeTab = $activeTab ?? 'dashboard';
$subAction = $_GET['action'] ?? 'list';

// Generar CSRF token para el logout
$header_csrf_token = generateCSRFToken();

// Leer estado del sidebar desde cookie (configurado vía JavaScript)
$sidebarCollapsed = isset($_COOKIE['admin_sidebar_collapsed']) && $_COOKIE['admin_sidebar_collapsed'] === 'true';

// Obtener favicon de la BD
$favicon_path = $siteConfig['site_favicon'] ?? '';
$hasFavicon = !empty($favicon_path) && file_exists($favicon_path);

// Generar nonce para CSP
$cspNonce = base64_encode(random_bytes(16));

// Enviar CSP como cabecera HTTP
if (!headers_sent()) {
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$cspNonce}' 'unsafe-hashes' 'sha256-0sPcLBXDBAM7UZD29cW5zU0BKhSwQkyY6tJp4TGz7YY=' 'sha256-lJvJhOvw0H8Wm41c2zGvQI5xYR6TStK8K6C+9HBCUxo=' 'sha256-q32amf+RnV31DyWrV5+J4yE3/k6OOdbrM6bWxYDsSgQ=' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; img-src 'self' data: https:; font-src 'self' data: https://cdnjs.cloudflare.com https://fonts.gstatic.com; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; upgrade-insecure-requests");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- Favicon desde BD -->
    <?php if ($hasFavicon): ?>
        <link rel="icon" type="<?= mime_content_type($favicon_path) ?>" href="<?= htmlspecialchars($favicon_path) . '?v=' . filemtime($favicon_path) ?>">
    <?php else: ?>
        <link rel="icon" type="image/png" href="../favicon.png">
    <?php endif; ?>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Estilos del Admin (incluye estilos de películas) -->
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <!-- Overlay para móvil -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <aside class="sidebar <?= $sidebarCollapsed ? 'collapsed' : '' ?>" id="adminSidebar">
        <div class="sidebar-header">
            <a href="index.php" class="sidebar-brand">
                <?php if (!empty($siteConfig['site_logo']) && file_exists($siteConfig['site_logo'])): ?>
                    <img src="<?= htmlspecialchars($siteConfig['site_logo']) ?>" alt="<?= htmlspecialchars($siteName) ?>" class="sidebar-logo">
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

        <nav class="sidebar-nav">
            <ul class="sidebar-menu">
                <li>
                    <a href="index.php" class="sidebar-link <?= $activeTab === 'dashboard' ? 'active' : '' ?>">
                        <i class="fas fa-chart-pie"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="has-submenu <?= $activeTab === 'movies' ? 'open' : '' ?>">
                    <a href="javascript:void(0)" class="sidebar-link submenu-toggle <?= $activeTab === 'movies' ? 'active' : '' ?>">
                        <i class="fas fa-film"></i>
                        <span>Películas</span>
                        <i class="fas fa-chevron-down submenu-arrow"></i>
                    </a>
                    <ul class="submenu <?= $activeTab === 'movies' ? 'open' : '' ?>">
                        <li>
                            <a href="index.php?tab=movies" class="sidebar-link <?= ($activeTab === 'movies' && $subAction !== 'register') ? 'active' : '' ?>">
                                <i class="fas fa-list"></i>
                                <span>Lista de Películas</span>
                            </a>
                        </li>
                        <li>
                            <a href="index.php?tab=movies&action=register" class="sidebar-link <?= ($activeTab === 'movies' && $subAction === 'register') ? 'active' : '' ?>">
                                <i class="fas fa-plus-circle"></i>
                                <span>Registrar Película</span>
                            </a>
                        </li>
                    </ul>
                </li>
                <li>
                    <a href="index.php?tab=showtimes" class="sidebar-link <?= $activeTab === 'showtimes' ? 'active' : '' ?>">
                        <i class="fas fa-clock"></i>
                        <span>Horarios</span>
                    </a>
                </li>
                <li>
                    <a href="index.php?tab=rooms" class="sidebar-link <?= $activeTab === 'rooms' ? 'active' : '' ?>">
                        <i class="fas fa-door-open"></i>
                        <span>Salas</span>
                    </a>
                </li>
                <li>
                    <a href="index.php?tab=users" class="sidebar-link <?= $activeTab === 'users' ? 'active' : '' ?>">
                        <i class="fas fa-users"></i>
                        <span>Usuarios</span>
                    </a>
                </li>
                <li>
                    <a href="index.php?tab=food" class="sidebar-link <?= $activeTab === 'food' ? 'active' : '' ?>">
                        <i class="fas fa-utensils"></i>
                        <span>Comida</span>
                    </a>
                </li>
                <li>
                    <a href="index.php?tab=history" class="sidebar-link <?= $activeTab === 'history' ? 'active' : '' ?>">
                        <i class="fas fa-history"></i>
                        <span>Historial</span>
                    </a>
                </li>
                <li>
                    <a href="index.php?tab=config" class="sidebar-link <?= $activeTab === 'config' ? 'active' : '' ?>">
                        <i class="fas fa-cog"></i>
                        <span>Configuración</span>
                    </a>
                </li>
            </ul>
        </nav>

        <div class="sidebar-footer">
            <a href="../index.php" class="sidebar-link" target="_blank">
                <i class="fas fa-external-link-alt"></i>
                <span>Ver Cartelera</span>
            </a>
            <form action="../logout.php" method="POST" class="sidebar-logout-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($header_csrf_token) ?>">
                <button type="submit" class="sidebar-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Cerrar Sesión</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- Contenido principal -->
    <main class="admin-main <?= $sidebarCollapsed ? 'sidebar-collapsed' : '' ?>" id="adminMain">
        <!-- Header superior -->
        <header class="admin-topbar">
            <div class="topbar-left">
                <button class="topbar-toggle" id="sidebarToggle" aria-label="Abrir menú">
                    <i class="fas fa-bars"></i>
                </button>
                <button class="topbar-collapse-toggle" id="topbarCollapseToggle" aria-label="Colapsar">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <h2 class="topbar-title">
                    <?php if ($activeTab === 'movies'): ?>
                        <?= $subAction === 'register' ? 'Registrar Película' : 'Películas' ?>
                    <?php else: ?>
                        <?= $activeTab === 'dashboard' ? 'Dashboard' : ucfirst($activeTab) ?>
                    <?php endif; ?>
                </h2>
            </div>
            <div class="topbar-right">
                <span class="topbar-user">
                    <i class="fas fa-user-circle"></i>
                    <span><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span>
                </span>
                <a href="../index.php" class="topbar-btn" title="Ver Cartelera">
                    <i class="fas fa-external-link-alt"></i>
                </a>
            </div>
        </header>

        <!-- Contenedor de contenido -->
        <div class="admin-page-content">