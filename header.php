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
        <link rel="icon" type="image/png" href="favicon.png">
    <?php endif; ?>

     <!-- ✅ GOOGLE FONTS (Correcto y optimizado con Preconnect) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Tailwind y FontAwesome -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">


    <style>
        * {
            font-family: 'Inter', sans-serif; /* Ahora sí cargará correctamente */
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #0a0a0f;
            color: #e5e7eb;
        }

        .header-gradient {
            background: #0f0f1a;
            background: linear-gradient(180deg, #0f0f1a 0%, #0a0a0f 100%);
        }

        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #1a1a2e;
        }

        ::-webkit-scrollbar-thumb {
            background: #4f46e5;
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #6366f1;
        }

        /* ============================================
        MENÚ HAMBURGUESA - ESTILOS
        ============================================ */
        .hamburger-btn {
            display: none;
            flex-direction: column;
            gap: 5px;
            cursor: pointer;
            padding: 8px;
            background: transparent;
            border: none;
            z-index: 60;
            position: relative;
        }

        .hamburger-btn span {
            display: block;
            width: 28px;
            height: 3px;
            background: #e5e7eb;
            border-radius: 2px;
            transition: all 0.3s ease;
            transform-origin: center;
        }

        .hamburger-btn.active span:nth-child(1) {
            transform: rotate(45deg) translate(5px, 6px);
        }

        .hamburger-btn.active span:nth-child(2) {
            opacity: 0;
            transform: scaleX(0);
        }

        .hamburger-btn.active span:nth-child(3) {
            transform: rotate(-45deg) translate(5px, -6px);
        }

        .menu-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 110;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .menu-overlay.active {
            display: block;
            opacity: 1;
        }

        /* Menú desplegable - FONDO BLANCO */
        .mobile-menu {
            display: none;
            position: fixed;
            top: 0;
            right: -100%;
            width: 85%;
            max-width: 340px;
            height: 100vh;
            background: #ffffff;
            z-index: 120;
            padding: 80px 24px 30px 24px;
            transition: right 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: -8px 0 30px rgba(0, 0, 0, 0.3);
            overflow-y: auto;
        }

        .mobile-menu.active {
            right: 0;
            display: block;
        }

        /* Estilos del contenido del menú - FONDO BLANCO */
        .mobile-menu .menu-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 20px;
        }

        .mobile-menu .menu-header .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #4f46e5;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .mobile-menu .menu-header .user-info {
            flex: 1;
            min-width: 0;
        }

        .mobile-menu .menu-header .user-info .user-name {
            font-weight: 700;
            color: #111827;
            font-size: 1rem;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mobile-menu .menu-header .user-info .user-email {
            font-size: 0.8rem;
            color: #6b7280;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mobile-menu .menu-items {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .mobile-menu .menu-items li {
            margin-bottom: 4px;
        }

        .mobile-menu .menu-items li a {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            border-radius: 10px;
            color: #1f2937;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .mobile-menu .menu-items li a i {
            width: 22px;
            text-align: center;
            color: #4f46e5;
            font-size: 1.1rem;
        }

        .mobile-menu .menu-items li a:hover {
            background: #f3f4f6;
        }

        .mobile-menu .menu-items li a.active {
            background: #eef2ff;
            color: #4f46e5;
        }

        .mobile-menu .menu-items li a.active i {
            color: #4f46e5;
        }

        .mobile-menu .menu-divider {
            height: 1px;
            background: #e5e7eb;
            margin: 12px 0;
        }

        .mobile-menu .menu-footer {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
        }

        .mobile-menu .menu-footer .logout-btn {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            border-radius: 10px;
            color: #dc2626;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            width: 100%;
            background: none;
            border: none;
            cursor: pointer;
        }

        .mobile-menu .menu-footer .logout-btn:hover {
            background: #fef2f2;
        }

        .mobile-menu .menu-footer .logout-btn i {
            width: 22px;
            text-align: center;
            font-size: 1.1rem;
        }

        .mobile-menu .close-menu-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: none;
            border: none;
            font-size: 1.8rem;
            color: #1f2937;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 8px;
            transition: background 0.2s ease;
        }

        .mobile-menu .close-menu-btn:hover {
            background: #f3f4f6;
        }

        /* ✅ Estilos para el formulario de logout desktop */
        .logout-form-inline {
            display: inline-flex;
            margin: 0;
            padding: 0;
        }

        .logout-form-inline .logout-link-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: #9ca3af;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: color 0.2s ease;
            padding: 0;
            font-family: inherit;
        }

        .logout-form-inline .logout-link-btn:hover {
            color: #ffffff;
        }

        @media (max-width: 768px) {
            .hamburger-btn {
                display: flex;
            }

            .desktop-menu {
                display: none !important;
            }

            .mobile-menu {
                display: block;
                right: -100%;
            }

            .mobile-menu.active {
                right: 0;
            }

            .menu-overlay.active {
                display: block;
            }

            .header-content .right-section {
                gap: 8px;
            }
        }

        @media (max-width: 480px) {
            .mobile-menu {
                width: 90%;
                max-width: 300px;
                padding: 70px 16px 20px 16px;
            }

            .mobile-menu .menu-items li a {
                padding: 10px 14px;
                font-size: 0.9rem;
            }

            .mobile-menu .menu-header .user-avatar {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }

            .mobile-menu .menu-header .user-info .user-name {
                font-size: 0.9rem;
            }
        }
    </style>
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
                <div class="user-avatar">
                    <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Usuario') ?></span>
                    <span class="user-email"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></span>
                </div>
            </div>
        <?php endif; ?>

        <ul class="menu-items">
            <li>
                <a href="index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">
                    <i class="fas fa-home"></i> Inicio
                </a>
            </li>
            <?php if (isset($_SESSION['user_id'])): ?>
                <li>
                    <a href="mis_compras.php" class="<?= $currentPage === 'mis_compras.php' ? 'active' : '' ?>">
                        <i class="fas fa-ticket-alt"></i> Mis Boletos
                    </a>
                </li>
                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                    <li>
                        <a href="admin/index.php">
                            <i class="fas fa-cog"></i> Panel Admin
                        </a>
                    </li>
                <?php endif; ?>
            <?php endif; ?>
        </ul>

        <div class="menu-divider"></div>

        <ul class="menu-items">
            <li>
                <a href="cartelera.php">
                    <i class="fas fa-film"></i> Cartelera
                </a>
            </li>
            <li>
                <a href="promociones.php">
                    <i class="fas fa-tags"></i> Promociones
                </a>
            </li>
            <li>
                <a href="contacto.php">
                    <i class="fas fa-envelope"></i> Contacto
                </a>
            </li>
        </ul>

        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="menu-footer">
                <form action="logout.php" method="POST" class="logout-form-inline" style="width:100%;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($header_csrf_token) ?>">
                    <button type="submit" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="menu-footer">
                <a href="login.php" class="logout-btn" style="color: #4f46e5;">
                    <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                </a>
                <a href="register.php" style="display: block; margin-top: 8px; padding: 10px 16px; border-radius: 10px; background: #4f46e5; color: white; text-decoration: none; font-weight: 600; font-size: 0.95rem; text-align: center; transition: background 0.2s ease;">
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
                <a href="index.php" class="flex items-center gap-3 group" title="<?= htmlspecialchars($siteName) ?>">
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
                        <a href="<?= htmlspecialchars($finalBackUrl) ?>" class="text-gray-400 hover:text-white transition-colors text-xs sm:text-sm flex items-center gap-1.5">
                            <i class="fas fa-arrow-left"></i>
                            <span class="hidden sm:inline">Volver</span>
                        </a>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="flex items-center gap-3">
                            <span class="text-sm text-gray-300 hidden sm:inline">
                                <i class="fas fa-user-circle text-indigo-400 mr-1"></i>
                                <?= htmlspecialchars($_SESSION['user_name']) ?>
                            </span>
                            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                                <a href="admin/index.php" class="text-xs text-indigo-400 hover:text-indigo-300 transition-colors flex items-center gap-1">
                                    <i class="fas fa-cog"></i>
                                    <span class="hidden sm:inline">Configurar</span>
                                </a>
                            <?php endif; ?>
                            <form action="logout.php" method="POST" class="logout-form-inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($header_csrf_token) ?>">
                                <button type="submit" class="logout-link-btn">
                                    <i class="fas fa-sign-out-alt"></i>
                                    <span class="hidden sm:inline">Salir</span>
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg text-sm font-semibold transition-all">
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
    <script nonce="<?= htmlspecialchars($cspNonce ?? '') ?>">
        // ============================================
        // LIMPIAR SESSIONSTORAGE SI LA SESIÓN EXPIRÓ
        // ============================================
        (function() {
            <?php if ($cleanStorage): ?>
                console.log('🗑️ Limpiando sessionStorage por sesión expirada/logout');
                const prefixes = [
                    'food_timeout_', 'food_seats_', 'food_valid_', 'food_order_', 'food_created_',
                    'purchase_token_', 'purchase_expires_at_', 'purchase_token_used_', 'purchase_created_at_',
                    'ticket_quantities_', 'total_seats_', 'subtotal_', 'tax_amount_', 'total_amount_', 'tax_rate_',
                    'payment_method_', 'selected_seats_', 'selected_seats_count_', 'ticket_selection_'
                ];

                const keysToRemove = [];
                for (let i = 0; i < sessionStorage.length; i++) {
                    const key = sessionStorage.key(i);
                    if (key) {
                        const shouldRemove = prefixes.some(prefix => key.includes(prefix));
                        if (shouldRemove) keysToRemove.push(key);
                    }
                }
                keysToRemove.forEach(key => sessionStorage.removeItem(key));
                console.log('✅ SessionStorage limpiado:', keysToRemove.length, 'claves');

                // Limpiar localStorage también
                try {
                    localStorage.clear();
                } catch (e) {}
            <?php endif; ?>

            // Verificar si venimos de index con session_expired en la URL
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('session_expired') || document.referrer.includes('session_expired')) {
                console.log('🗑️ Limpiando sessionStorage por sesión expirada (desde URL/referrer)');
                const prefixes = [
                    'food_timeout_', 'food_seats_', 'food_valid_', 'food_order_', 'food_created_',
                    'purchase_token_', 'purchase_expires_at_', 'purchase_token_used_', 'purchase_created_at_',
                    'ticket_quantities_', 'total_seats_', 'subtotal_', 'tax_amount_', 'total_amount_', 'tax_rate_',
                    'payment_method_', 'selected_seats_', 'selected_seats_count_', 'ticket_selection_'
                ];

                const keysToRemove = [];
                for (let i = 0; i < sessionStorage.length; i++) {
                    const key = sessionStorage.key(i);
                    if (key) {
                        const shouldRemove = prefixes.some(prefix => key.includes(prefix));
                        if (shouldRemove) keysToRemove.push(key);
                    }
                }
                keysToRemove.forEach(key => sessionStorage.removeItem(key));
                console.log('✅ SessionStorage limpiado:', keysToRemove.length, 'claves');

                // Si estamos en price_selection.php y hay parámetro session_expired, redirigir limpio
                if (window.location.pathname.includes('price_selection.php') && urlParams.has('session_expired')) {
                    const showtimeId = urlParams.get('showtime_id');
                    if (showtimeId) {
                        const cleanUrl = window.location.pathname + '?showtime_id=' + showtimeId;
                        window.history.replaceState({}, document.title, cleanUrl);
                    }
                }
            }
        })();
    </script>

    <script nonce="<?= htmlspecialchars($cspNonce ?? '') ?>">
        // ============================================
        // MENÚ HAMBURGUESA
        // ============================================
        (function() {
            const hamburgerBtn = document.getElementById('hamburgerBtn');
            const mobileMenu = document.getElementById('mobileMenu');
            const menuOverlay = document.getElementById('menuOverlay');
            const closeMenuBtn = document.getElementById('closeMenuBtn');

            function openMenu() {
                mobileMenu.classList.add('active');
                menuOverlay.classList.add('active');
                hamburgerBtn.classList.add('active');
                document.body.style.overflow = 'hidden';
            }

            function closeMenu() {
                mobileMenu.classList.remove('active');
                menuOverlay.classList.remove('active');
                hamburgerBtn.classList.remove('active');
                document.body.style.overflow = '';
            }

            function toggleMenu() {
                if (mobileMenu.classList.contains('active')) {
                    closeMenu();
                } else {
                    openMenu();
                }
            }

            hamburgerBtn.addEventListener('click', toggleMenu);
            closeMenuBtn.addEventListener('click', closeMenu);
            menuOverlay.addEventListener('click', closeMenu);

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && mobileMenu.classList.contains('active')) {
                    closeMenu();
                }
            });

            mobileMenu.querySelectorAll('a').forEach(function(link) {
                link.addEventListener('click', function() {
                    if (!this.href.includes('logout.php')) {
                        setTimeout(closeMenu, 150);
                    }
                });
            });

            mobileMenu.addEventListener('touchmove', function(e) {
                if (mobileMenu.classList.contains('active')) {
                    e.stopPropagation();
                }
            });
        })();
    </script>

    <!-- ============================================ -->
    <!-- HANDLERS CSP-SAFE: REEMPLAZAN onclick/onerror INLINE -->
    <!-- ============================================ -->
    <script nonce="<?= htmlspecialchars($cspNonce ?? '') ?>">
        (function() {
            // Imprimir comprobante (antes: onclick="window.print()")
            document.addEventListener('click', function(e) {
                const btn = e.target.closest('[data-print-btn]');
                if (btn) {
                    window.print();
                }
            });

            // Errores de imágenes (antes: onerror inline)
            // data-error-hide     → ocultar la imagen
            // data-error-fallback → ocultar la imagen y mostrar el siguiente elemento
            document.addEventListener('error', function(e) {
                const img = e.target;
                if (!img || img.tagName !== 'IMG') return;

                if (img.hasAttribute('data-error-hide')) {
                    img.style.display = 'none';
                } else if (img.hasAttribute('data-error-fallback')) {
                    img.style.display = 'none';
                    const fallback = img.nextElementSibling;
                    if (fallback) {
                        fallback.style.display = 'flex';
                    }
                }
            }, true);
        })();
    </script>
</body>

</html>