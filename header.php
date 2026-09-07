<?php
require_once 'config.php';

// Obtener la configuración del sitio desde la base de datos
$siteConfig = getSiteConfig($pdo);
$siteName = $siteConfig['site_name'] ?? 'Cinema';
$siteLogo = $siteConfig['site_logo'] ?? '';
$siteFavicon = $siteConfig['site_favicon'] ?? '';

// Verificar si existe un logo subido y si el archivo existe en el servidor
$hasLogo = !empty($siteLogo) && file_exists($siteLogo);
$hasFavicon = !empty($siteFavicon) && file_exists($siteFavicon);

// GENERAR TOKEN CSRF PARA EL FORMULARIO DE LOGOUT
$header_csrf_token = generateCSRFToken();

// LIMPIAR SESSIONSTORAGE SI LA SESIÓN EXPIRÓ (Script de limpieza automática)
$cleanStorage = isset($_GET['session_expired']) || isset($_GET['timeout']) || isset($_GET['logout']);

// PREFIJO DE RUTA RELATIVA (header usado desde la raíz o desde user/)
$publicPathPrefix = '';
$publicCssDir = str_replace('\\', '/', dirname(__FILE__));
$publicScriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME'] ?? __FILE__));
if ($publicScriptDir !== $publicCssDir && strpos($publicScriptDir, $publicCssDir . '/') === 0) {
    $publicPathPrefix = str_repeat('../', substr_count(substr($publicScriptDir, strlen($publicCssDir) + 1), '/') + 1);
}

// RUTA DEL AVATAR DEL USUARIO LOGUEADO (si existe)
$menuAvatar = '';
if (isset($_SESSION['user_id'])) {
    try {
        $stmtAv = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
        $stmtAv->execute([$_SESSION['user_id']]);
        $avatarVal = $stmtAv->fetchColumn();
        if (!empty($avatarVal) && is_file($avatarVal)) {
            $menuAvatar = $avatarVal . '?v=' . filemtime($avatarVal);
        }
    } catch (Throwable $e) {
        error_log("Error obteniendo avatar en header: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : htmlspecialchars($siteName) ?></title>

    <?php if ($hasFavicon): ?>
        <link rel="icon" type="<?= mime_content_type($siteFavicon) ?>" href="<?= htmlspecialchars($siteFavicon) . '?v=' . filemtime($siteFavicon) ?>">
    <?php else: ?>
        <link rel="icon" type="image/png" href="<?= $publicPathPrefix ?>admin/img/favicon.png">
    <?php endif; ?>

     <!-- ✅ GOOGLE FONTS (Correcto y optimizado con Preconnect) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Tailwind y FontAwesome -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">


    <link rel="stylesheet" href="<?= $publicPathPrefix ?>assets/css/public.css">
</head>

<body>

    <?php
    // Si la página secundaria definió $backUrl la usa; de lo contrario, por defecto va a index.php
    $finalBackUrl = isset($backUrl) ? $backUrl : 'index.php';
    $currentPage = basename($_SERVER['PHP_SELF']);
    ?>

    <!-- Overlay del menú -->
    <div class="menu-overlay" id="menuOverlay"></div>

    <!-- Menú Móvil - FONDO BLANCO -->
    <div class="mobile-menu" id="mobileMenu">
        <button class="close-menu-btn" id="closeMenuBtn" aria-label="Cerrar menú">
            <i class="fas fa-times"></i>
        </button>

        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="menu-header">
                <?php if ($menuAvatar): ?>
                    <img src="<?= htmlspecialchars($menuAvatar) ?>" alt="Foto de perfil" class="user-avatar" style="object-fit: cover;">
                <?php else: ?>
                    <div class="user-avatar">
                        <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Usuario') ?></span>
                    <span class="user-email"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></span>
                </div>
            </div>
        <?php endif; ?>

        <ul class="menu-items">
            <li>
                <a href="<?= $publicPathPrefix ?>index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">
                    <i class="fas fa-home"></i> Inicio
                </a>
            </li>
            <?php if (isset($_SESSION['user_id'])): ?>
                <li>
                    <a href="<?= $publicPathPrefix ?>user/account.php" class="<?= $currentPage === 'account.php' ? 'active' : '' ?>">
                        <i class="fas fa-user-cog"></i> Mi Cuenta
                    </a>
                </li>
                <li>
                    <a href="<?= $publicPathPrefix ?>user/tickets.php" class="<?= $currentPage === 'tickets.php' ? 'active' : '' ?>">
                        <i class="fas fa-ticket-alt"></i> Mis Boletos
                    </a>
                </li>
                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                    <li>
                        <a href="<?= $publicPathPrefix ?>admin/index.php" class="<?= $currentPage === 'admin/index.php' ? 'active' : '' ?>">
                            <i class="fas fa-cog"></i> Panel Admin
                        </a>
                    </li>
                <?php endif; ?>
            <?php endif; ?>
        </ul>

        <div class="menu-divider"></div>

        <ul class="menu-items">
            <li>
                <a href="<?= $publicPathPrefix ?>cartelera.php">
                    <i class="fas fa-film"></i> Cartelera
                </a>
            </li>
            <li>
                <a href="<?= $publicPathPrefix ?>user/promotions.php" class="<?= $currentPage === 'promotions.php' ? 'active' : '' ?>">
                    <i class="fas fa-tags"></i> Promociones
                </a>
            </li>
            <li>
                <a href="<?= $publicPathPrefix ?>contacto.php">
                    <i class="fas fa-envelope"></i> Contacto
                </a>
            </li>
        </ul>

        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="menu-footer">
                <form action="<?= $publicPathPrefix ?>logout.php" method="POST" class="logout-form-inline" style="width:100%;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($header_csrf_token) ?>">
                    <button type="submit" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="menu-footer">
                <a href="<?= $publicPathPrefix ?>login.php" class="logout-btn" style="color: #4f46e5;">
                    <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                </a>
                <a href="<?= $publicPathPrefix ?>register.php" style="display: block; margin-top: 8px; padding: 10px 16px; border-radius: 10px; background: #4f46e5; color: white; text-decoration: none; font-weight: 600; font-size: 0.95rem; text-align: center; transition: background 0.2s ease;">
                    Registrarse
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Header Navegación -->
    <header class="header-gradient border-b border-[#1e1e2e] sticky top-0 z-50">
        <div class="container mx-auto px-4 py-4">
            <div class="flex items-center justify-between header-content">
                <!-- Logo -->
                <a href="<?= $publicPathPrefix ?>index.php" class="flex items-center gap-3 group" title="<?= htmlspecialchars($siteName) ?>">
                    <?php if ($hasLogo): ?>
                        <img src="<?= htmlspecialchars($siteLogo) ?>"
                            alt="<?= htmlspecialchars($siteName) ?>"
                            title="<?= htmlspecialchars($siteName) ?>"
                            class="h-12 w-auto object-contain transition-transform duration-200 group-hover:scale-105">
                    <?php else: ?>
                        <div class="flex flex-col">
                            <span class="text-2xl font-black text-indigo-400 tracking-wider flex items-center gap-2">
                                🎬 <?= htmlspecialchars($siteName) ?>
                            </span>
                            <span class="text-xs text-gray-400 font-medium tracking-normal hidden sm:block">
                                La mejor experiencia cinematográfica
                            </span>
                        </div>
                    <?php endif; ?>
                </a>

                <!-- Menú Desktop -->
                <div class="flex items-center gap-4 desktop-menu">
                    <?php if ($currentPage !== 'index.php'): ?>
                        <a href="<?= $publicPathPrefix . htmlspecialchars($finalBackUrl) ?>" class="text-gray-400 hover:text-white transition-colors text-xs sm:text-sm flex items-center gap-1.5">
                            <i class="fas fa-arrow-left"></i>
                            <span class="hidden sm:inline">Volver</span>
                        </a>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="flex items-center gap-3">
                            <a href="<?= $publicPathPrefix ?>user/account.php" class="flex items-center gap-2 group" title="Mi Cuenta">
                                <?php if ($menuAvatar): ?>
                                    <img src="<?= htmlspecialchars($menuAvatar) ?>" alt="Foto de perfil" class="w-7 h-7 rounded-full object-cover inline-block group-hover:ring-2 group-hover:ring-indigo-400 transition-all">
                                <?php else: ?>
                                    <i class="fas fa-user-circle text-indigo-400 text-lg group-hover:text-indigo-300 transition-colors"></i>
                                <?php endif; ?>
                                <span class="text-sm text-gray-300 hidden sm:inline group-hover:text-white transition-colors">
                                    <?= htmlspecialchars($_SESSION['user_name']) ?>
                                </span>
                            </a>
                            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                                <a href="<?= $publicPathPrefix ?>admin/index.php" class="text-xs text-indigo-400 hover:text-indigo-300 transition-colors flex items-center gap-1">
                                    <i class="fas fa-cog"></i>
                                    <span class="hidden sm:inline">Configurar</span>
                                </a>
                            <?php endif; ?>
                            <form action="<?= $publicPathPrefix ?>logout.php" method="POST" class="logout-form-inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($header_csrf_token) ?>">
                                <button type="submit" class="logout-link-btn">
                                    <i class="fas fa-sign-out-alt"></i>
                                    <span class="hidden sm:inline">Salir</span>
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <a href="<?= $publicPathPrefix ?>login.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg text-sm font-semibold transition-all">
                            <i class="fas fa-sign-in-alt mr-2"></i>Ingresar
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Botón Hamburguesa (Móvil) -->
                <button class="hamburger-btn" id="hamburgerBtn" aria-label="Abrir menú">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </div>
    </header>

    <!-- ============================================ -->
    <!-- SCRIPT DE LIMPIEZA AUTOMÁTICA DE SESSIONSTORAGE -->
    <!-- ============================================ -->
    

    

    <!-- ============================================ -->
    <!-- HANDLERS CSP-SAFE: REEMPLAZAN onclick/onerror INLINE -->
    <!-- ============================================ -->
    
<script src="<?= $publicPathPrefix ?>assets/js/public.js"></script>

</body>

</html>