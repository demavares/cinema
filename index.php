<?php
require_once 'config.php';

// ============================================
// ✅ CORREGIDO: DETECTAR SESIÓN EXPIRADA (UNIFICADO CON MODAL)
// Se activa el modal con expired=1 o session_expired=1
// ============================================
$mostrar_alerta = (isset($_GET['expired']) && $_GET['expired'] === '1')
               || (isset($_GET['session_expired']) && $_GET['session_expired'] == 1);

// ============================================
// DETECTAR LOGOUT RECIENTE
// ============================================
$showLogoutMessage = false;
if (isset($_GET['logout']) && $_GET['logout'] == 1) {
    $showLogoutMessage = true;
    if (isset($_SESSION['just_logged_out'])) {
        unset($_SESSION['just_logged_out']);
        unset($_SESSION['logout_time']);
    }
}

// ============================================
// LIMPIAR SESSIONSTORAGE AL CERRAR SESIÓN O TIMEOUT
// ============================================
$shouldClearStorage = isset($_GET['logout']) || isset($_GET['timeout']) || isset($_GET['nocache']) || isset($_GET['expired']) || isset($_GET['session_expired']);

// ============================================
// VERIFICAR TIMEOUT EXPIRADO
// ============================================
$showTimeoutModal = isset($_GET['timeout']) && $_GET['timeout'] == 1;

// Obtener todas las películas activas
$stmt = $pdo->query("SELECT * FROM movies WHERE is_active = 1 ORDER BY title ASC");
$movies = $stmt->fetchAll();

// Obtener fecha actual
$currentDate = getCurrentDate();
$currentDateTime = getCurrentDateTime();

// PROCESAR PELÍCULAS Y USAR IMÁGENES DE LA BD
foreach ($movies as $key => $movie) {
    $stmtShowtimes = $pdo->prepare("
        SELECT * FROM showtimes
        WHERE movie_id = ? AND is_active = 1 AND show_date >= ?
    ");
    $stmtShowtimes->execute([$movie['id'], $currentDate]);
    $movies[$key]['showtimes'] = $stmtShowtimes->fetchAll();

    if (empty($movies[$key]['poster_url'])) {
        $movies[$key]['poster_url'] = getPlaceholderImage(300, 450, '🎬');
    }
    if (empty($movies[$key]['banner_url'])) {
        $movies[$key]['banner_url'] = $movies[$key]['poster_url'];
    }
}

require_once 'header.php';
?>

<!-- ============================================ -->
<!-- MODAL DE TIMEOUT EXPIRADO -->
<!-- ============================================ -->
<div class="timeout-modal-overlay <?= $showTimeoutModal ? 'active' : '' ?>" id="timeoutModal">
    <div class="timeout-modal">
        <div class="timeout-icon">⏰</div>
        <h2 class="timeout-title">¡Sesión Expirada!</h2>
        <p class="timeout-text">Tu tiempo para seleccionar comida ha expirado.<br>Los asientos han sido liberados automáticamente.</p>
        <button class="timeout-btn" onclick="closeTimeoutModal()"><i class="fas fa-home mr-2"></i> Entendido</button>
    </div>
</div>

<!-- ============================================ -->
<!-- ✅ MODAL DE SESIÓN EXPIRADA POR INACTIVIDAD (UNIFICADO) -->
<!-- Se muestra con expired=1 o session_expired=1 -->
<!-- ============================================ -->
<div class="session-expired-modal-overlay <?= $mostrar_alerta ? 'active' : '' ?>" id="sessionExpiredModal">
    <div class="session-expired-modal">
        <div class="session-expired-icon">⏰</div>
        <h2 class="session-expired-title">¡Sesión Expirada!</h2>
        <p class="session-expired-text">Tu sesión ha expirado por inactividad.<br>Por favor, selecciona nuevamente tus boletos para continuar.</p>
        <div class="session-expired-actions">
            <button class="session-expired-btn" onclick="closeSessionExpiredModal()"><i class="fas fa-film mr-2"></i> Ver Cartelera</button>
            <button class="session-expired-btn-secondary" onclick="closeSessionExpiredModal()">Cerrar</button>
        </div>
    </div>
</div>

<style>
/* ============================================
   ESTILOS GENERALES
   ============================================ */
html {
    overflow-x: clip;
    background-color: #ffffff;
}

body {
    max-width: 100%;
    overflow-x: clip;
    position: relative;
    background-color: #ffffff;
    color: #1f2937;
}

header, .site-header, nav.navbar {
    position: sticky !important;
    top: 0 !important;
    z-index: 100 !important;
    width: 100%;
}

main.container {
    padding-top: 0 !important;
    background-color: #ffffff;
    margin-bottom: 3.5rem;
}

/* ============================================
   MODAL DE TIMEOUT
   ============================================ */
.timeout-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(8px);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.timeout-modal-overlay.active {
    display: flex;
}

.timeout-modal {
    background: #1a1a2e;
    border: 2px solid #ef4444;
    border-radius: 16px;
    padding: 40px;
    max-width: 420px;
    width: 100%;
    text-align: center;
    animation: modalFadeIn 0.5s ease;
    box-shadow: 0 20px 60px rgba(239, 68, 68, 0.2);
}

.timeout-icon {
    font-size: 4rem;
    margin-bottom: 16px;
    display: block;
    animation: pulse-danger 1s ease-in-out infinite;
}

.timeout-title {
    color: #fca5a5;
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 12px;
}

.timeout-text {
    color: #9ca3af;
    font-size: 0.95rem;
    margin-bottom: 24px;
    line-height: 1.6;
}

.timeout-btn {
    background: #ef4444;
    color: white;
    padding: 12px 32px;
    border-radius: 8px;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1rem;
}

.timeout-btn:hover {
    background: #dc2626;
    transform: scale(1.05);
}

/* ============================================
   ✅ MODAL DE SESIÓN EXPIRADA (MEJORADO)
   ============================================ */
.session-expired-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.session-expired-modal-overlay.active {
    display: flex;
}

.session-expired-modal {
    background: #1a1a2e;
    border: 2px solid #f59e0b;
    border-radius: 16px;
    padding: 40px;
    max-width: 420px;
    width: 100%;
    text-align: center;
    animation: modalFadeIn 0.5s ease;
    box-shadow: 0 20px 60px rgba(245, 158, 11, 0.2);
    position: relative;
    overflow: hidden;
}

/* Barra decorativa superior */
.session-expired-modal::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #f59e0b, #fbbf24, #f59e0b);
    background-size: 200% 100%;
    animation: gradientMove 3s ease infinite;
}

@keyframes gradientMove {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

.session-expired-icon {
    font-size: 4rem;
    margin-bottom: 16px;
    display: block;
    animation: pulse-warning 1.5s ease-in-out infinite;
}

.session-expired-title {
    color: #fbbf24;
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 12px;
}

.session-expired-text {
    color: #9ca3af;
    font-size: 0.95rem;
    margin-bottom: 24px;
    line-height: 1.6;
}

/* ✅ NUEVO: Contenedor de acciones */
.session-expired-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.session-expired-btn {
    background: #f59e0b;
    color: white;
    padding: 12px 32px;
    border-radius: 8px;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1rem;
    width: 100%;
}

.session-expired-btn:hover {
    background: #d97706;
    transform: scale(1.05);
}

/* ✅ NUEVO: Botón secundario */
.session-expired-btn-secondary {
    background: transparent;
    color: #9ca3af;
    padding: 10px 32px;
    border-radius: 8px;
    font-weight: 600;
    border: 1px solid #374151;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.95rem;
    width: 100%;
}

.session-expired-btn-secondary:hover {
    background: #1f2937;
    color: #ffffff;
    border-color: #4b5563;
}

@keyframes pulse-danger {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

@keyframes pulse-warning {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; transform: scale(1.05); }
}

@keyframes modalFadeIn {
    from { opacity: 0; transform: scale(0.9) translateY(20px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

/* ============================================
   CARRUSEL HERO
   ============================================ */
.hero-carousel {
    position: relative;
    z-index: 1;
    width: 100vw;
    left: 50%;
    right: 50%;
    margin-left: -50vw;
    margin-right: -50vw;
    margin-top: 0;
    margin-bottom: 2.5rem;
    overflow: hidden;
    background: #0a0a0f;
    border-bottom: 1px solid #e5e7eb;
    box-shadow: 0 10px 20px -5px rgba(0, 0, 0, 0.1);
}

.carousel-slides-container {
    display: flex;
    transition: transform 0.6s cubic-bezier(0.25, 1, 0.5, 1);
    width: 100%;
}

.hero-slide {
    min-width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    position: relative;
    min-height: 520px;
    display: flex;
    align-items: flex-end;
    padding: 40px 20px;
    overflow: hidden;
}

.hero-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-size: cover;
    background-position: center top;
    filter: brightness(0.65);
    z-index: 1;
}

.hero-backdrop::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(13,14,21,0.2) 0%, rgba(13,14,21,0.85) 70%, #0d0e15 100%),
                linear-gradient(90deg, rgba(13,14,21,0.9) 0%, rgba(13,14,21,0.3) 50%, rgba(13,14,21,0.9) 100%);
}

.hero-content {
    position: relative;
    z-index: 2;
    display: flex;
    gap: 2rem;
    align-items: flex-end;
    width: 100%;
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 15px;
    box-sizing: border-box;
}

.hero-poster {
    width: 180px;
    flex-shrink: 0;
    border-radius: 0.75rem;
    overflow: hidden;
    box-shadow: 0 10px 25px rgba(0,0,0,0.6);
    border: 1px solid rgba(255,255,255,0.15);
}

.hero-poster img {
    width: 100%;
    height: auto;
    display: block;
}

.hero-info {
    width: 100%;
}

.hero-info h1 {
    font-size: 2.5rem;
    font-weight: 800;
    color: #ffffff;
    line-height: 1.2;
    margin-bottom: 0.5rem;
    text-shadow: 0 2px 4px rgba(0,0,0,0.5);
}

.hero-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: center;
    font-size: 0.9rem;
    color: #d1d5db;
    margin-bottom: 0.75rem;
}

.hero-meta .meta-item {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.overview-text {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #9ca3af;
    max-width: 750px;
    line-height: 1.5;
}

.carousel-nav-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: white;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 10;
    transition: all 0.3s ease;
}

.carousel-nav-btn:hover {
    background: rgba(79, 70, 229, 0.8);
    border-color: #4f46e5;
    transform: translateY(-50%) scale(1.1);
}

.carousel-nav-btn.prev { left: 20px; }
.carousel-nav-btn.next { right: 20px; }

.carousel-dots {
    position: absolute;
    bottom: 20px;
    right: 40px;
    display: flex;
    gap: 8px;
    z-index: 10;
}

.carousel-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
    cursor: pointer;
    transition: all 0.3s ease;
}

.carousel-dot.active {
    background: #6366f1;
    width: 24px;
    border-radius: 10px;
}

/* ============================================
   BOTÓN RESERVAR
   ============================================ */
.btn-reserve {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    text-align: center;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
}

.hero-info .btn-hero-reserve {
    width: auto;
    padding: 10px 24px;
}

.btn-reserve:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(79, 70, 229, 0.3);
}

/* ============================================
   TARJETAS DE PELÍCULAS
   ============================================ */
.movie-card {
    background: #ffffff;
    border: 1px solid #d1d5db;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
    position: relative;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
}

.movie-card:hover {
    transform: translateY(-8px);
    border-color: #4f46e5;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.12), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.movie-card .poster {
    display: block;
    position: relative;
    overflow: hidden;
    aspect-ratio: 2/3;
    cursor: pointer;
}

.movie-card .poster img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

.movie-card:hover .poster img {
    transform: scale(1.05);
}

.genre-tag {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
    background: #f3f4f6;
    color: #374151;
    border: 1px solid #d1d5db;
}

.presale-ribbon-img {
    position: absolute;
    top: 0;
    left: 0;
    z-index: 10;
    width: 85px;
    max-width: 140px;
    max-height: 140px;
    height: auto;
    display: block;
    pointer-events: none;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

.movie-card {
    animation: fadeInUp 0.6s ease forwards;
}

.info-row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 6px;
    flex-wrap: wrap;
    line-height: 1.5;
}

.certification-tag {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 700;
    color: white;
    line-height: 1.4;
}

.certification-tag.a { background: #22c55e; }
.certification-tag.b { background: #3b82f6; }
.certification-tag.c { background: #ef4444; }

@media (max-width: 480px) {
    .timeout-modal, .session-expired-modal {
        padding: 30px 20px;
        margin: 0 16px;
    }
    .timeout-icon, .session-expired-icon { font-size: 3rem; }
    .timeout-title, .session-expired-title { font-size: 1.2rem; }
    .timeout-text, .session-expired-text { font-size: 0.85rem; }
    .timeout-btn, .session-expired-btn, .session-expired-btn-secondary { 
        padding: 10px 24px; 
        font-size: 0.9rem; 
        width: 100%; 
    }
}

@media (max-width: 768px) {
    main.container { margin-bottom: 2.5rem; }
    .hero-slide {
        padding: 24px 16px 12px 16px;
        min-height: 440px;
    }
    .hero-info h1 { font-size: 1.6rem; }
    .hero-info .btn-hero-reserve {
        width: 100% !important;
        justify-content: center;
        margin-bottom: 2rem;
    }
    .carousel-nav-btn {
        width: 38px;
        height: 38px;
    }
    .carousel-nav-btn.prev { left: 10px; }
    .carousel-nav-btn.next { right: 10px; }
    .carousel-dots {
        right: 50%;
        transform: translateX(50%);
        bottom: 12px;
    }
    .overview-text {
        -webkit-line-clamp: 2;
        font-size: 0.85rem;
    }
    .presale-ribbon-img { width: 70px; }
}

@media (max-width: 640px) {
    .movie-card .poster { aspect-ratio: 16/9; }
    .presale-ribbon-img { width: 55px; }
    .info-row { gap: 6px; line-height: 1.6; }
}

@media (max-width: 480px) {
    .presale-ribbon-img { width: 45px; }
}
</style>

<!-- Main Content -->
<main class="container mx-auto px-4 pb-16 mb-12">

<?php if (!empty($movies)): ?>
    <!-- ========================================== -->
    <!-- BANNER CARRUSEL 100% ANCHO (HERO)          -->
    <!-- ========================================== -->
    <section class="hero-carousel">
        <div class="carousel-slides-container" id="carouselSlides">
            <?php foreach ($movies as $index => $m):
                $poster_url = $m['poster_url'] ?? getPlaceholderImage(300, 450, '🎬');
                $backdrop_url = !empty($m['banner_url']) ? $m['banner_url'] : $poster_url;
                $m_description = $m['description'] ?? '';
                $m_duration = $m['duration'] ?? 0;
                $m_genre = $m['genre'] ?? '';
                $m_year = $m['year'] ?? '';
                $m_classification = $m['classification'] ?? '';
                $m_title = $m['title'] ?? '';
                $m_id = $m['id'] ?? 0;
            ?>
                <div class="hero-slide">
                    <div class="hero-backdrop" style="background-image: url('<?= htmlspecialchars($backdrop_url) ?>');"></div>
                    <div class="hero-content">
                        <a href="movie_detail.php?id=<?= intval($m_id) ?>" class="hero-poster hidden sm:block">
                            <img src="<?= htmlspecialchars($poster_url) ?>" alt="<?= htmlspecialchars($m_title) ?>"  loading="lazy">
                        </a>
                        <div class="hero-info">
                            <h1><?= htmlspecialchars($m_title) ?></h1>
                            <div class="hero-meta">
                                <?php if($m_duration > 0): ?>
                                    <span class="meta-item"><i class="far fa-clock text-indigo-400"></i><?= formatDuration($m_duration) ?></span>
                                <?php endif; ?>
                                <?php if(!empty($m_genre)): ?>
                                    <span class="meta-item"><i class="fas fa-tag text-indigo-400"></i><?= htmlspecialchars($m_genre) ?></span>
                                <?php endif; ?>
                                <?php if(!empty($m_classification)): ?>
                                    <span class="meta-item"><span class="certification-tag b"><?= htmlspecialchars($m_classification) ?></span></span>
                                <?php endif; ?>
                                <?php if(!empty($m_year)): ?>
                                    <span class="meta-item"><i class="far fa-calendar-alt text-indigo-400"></i><?= htmlspecialchars($m_year) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if(!empty($m_description)): ?>
                                <p class="overview-text text-sm mb-4"><?= htmlspecialchars($m_description) ?></p>
                            <?php endif; ?>
                            <div class="flex items-center gap-3 flex-wrap">
                                <a href="movie_detail.php?id=<?= intval($m_id) ?>" class="btn-reserve btn-hero-reserve"><i class="fas fa-ticket-alt"></i> Ver Funciones</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if(count($movies) > 1): ?>
            <button class="carousel-nav-btn prev" onclick="moveSlide(-1)"><i class="fas fa-chevron-left"></i></button>
            <button class="carousel-nav-btn next" onclick="moveSlide(1)"><i class="fas fa-chevron-right"></i></button>
            <div class="carousel-dots">
                <?php foreach($movies as $i => $m): ?>
                    <span class="carousel-dot <?= $i === 0 ? 'active' : '' ?>" onclick="goToSlide(<?= $i ?>)"></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<!-- Título de sección -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Cartelera</h2>
        <p class="text-gray-500 text-sm"><?= count($movies) ?> películas en cartelera</p>
    </div>
    <div class="flex items-center gap-2 text-sm text-gray-500">
        <i class="fas fa-calendar-alt text-indigo-600"></i>
        <span><?= getDateInSpanish(date('Y-m-d')) ?></span>
    </div>
</div>

<!-- Grid de películas -->
<?php if (empty($movies)): ?>
    <div class="bg-white border border-gray-300 rounded-2xl p-12 text-center shadow-sm">
        <div class="text-6xl mb-4">🎬</div>
        <h3 class="text-xl font-bold text-gray-700 mb-2">No hay películas programadas</h3>
        <p class="text-gray-500 text-sm">El administrador debe agregar películas y horarios desde el <a href="admin.php" class="text-indigo-600 hover:underline">panel de configuración</a>.</p>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-8">
        <?php foreach ($movies as $index => $movie):
            $classification = $movie['classification'] ?? '';
            $certClass = 'b';
            $certLabel = 'B (Mayores de 12)';
            if (!empty($classification)) {
                $certLabel = $classification;
                if (strpos($classification, 'A') !== false) { $certClass = 'a'; }
                elseif (strpos($classification, 'C') !== false) { $certClass = 'c'; }
            }

            $genre_names = [];
            if (!empty($movie['genre'])) {
                $genre_names = array_map('trim', explode(',', $movie['genre']));
            }

            $duration = $movie['duration'] ?? 0;
            $formatted_duration = formatDuration($duration);
            $director = $movie['director'] ?? '';

            $hasPresale = false;
            if (!empty($movie['showtimes'])) {
                foreach ($movie['showtimes'] as $st) {
                    if (!empty($st['promotions'])) {
                        $promotions = explode(',', $st['promotions']);
                        if (in_array('preventa', $promotions)) {
                            $hasPresale = true;
                            break;
                        }
                    }
                }
            }

            $poster_url = $movie['poster_url'] ?? getPlaceholderImage(300, 450, '🎬');
        ?>
            <div class="movie-card rounded-xl">
                <a href="movie_detail.php?id=<?= intval($movie['id']) ?>" class="poster">
                    <img src="<?= htmlspecialchars($poster_url) ?>" alt="<?= htmlspecialchars($movie['title']) ?>" title="<?= htmlspecialchars($movie['title']) ?>" loading="lazy">
                    <?php if($hasPresale): ?>
                        <img src="preventa.png" alt="PREVENTA" class="presale-ribbon-img">
                    <?php endif; ?>
                </a>
                <div class="p-4">
                    <h3 class="font-bold text-gray-900 text-base truncate" title="<?= htmlspecialchars($movie['title']) ?>">
                        <a href="movie_detail.php?id=<?= intval($movie['id']) ?>" class="hover:text-indigo-600 transition-colors"><?= htmlspecialchars($movie['title']) ?></a>
                    </h3>

                    <?php if(count($genre_names) > 0): ?>
                        <div class="flex flex-wrap gap-1 mt-2">
                            <?php $display_genres = array_slice($genre_names, 0, 2);
                            foreach ($display_genres as $genre_name): ?>
                                <span class="genre-tag"><?= htmlspecialchars($genre_name) ?></span>
                            <?php endforeach; ?>
                            <?php if(count($genre_names) > 2): ?>
                                <span class="genre-tag">+<?= count($genre_names) - 2 ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="info-row">
                        <?php if($certLabel): ?>
                            <span class="certification-tag <?= $certClass ?>"><?= htmlspecialchars($certLabel) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="flex items-center gap-3 mt-2 text-xs text-gray-500">
                        <?php if($duration > 0): ?>
                            <span><i class="far fa-clock mr-1"></i><?= $formatted_duration ?></span>
                        <?php endif; ?>
                        <?php if($movie['year']): ?>
                            <span><i class="far fa-calendar-alt mr-1"></i><?= htmlspecialchars($movie['year']) ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if(!empty($director)): ?>
                        <div class="mt-1 text-xs text-gray-500"><span class="text-gray-600">Dir: <?= htmlspecialchars($director) ?></span></div>
                    <?php endif; ?>

                    <button onclick="window.location.href='movie_detail.php?id=<?= intval($movie['id']) ?>'" class="btn-reserve mt-3"><i class="fas fa-ticket-alt"></i> Ver Funciones</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

</main>

<?php require_once 'footer.php'; ?>

<script>
// ============================================
// LIMPIAR SESSIONSTORAGE SI ES NECESARIO
// ============================================
<?php if ($shouldClearStorage): ?>
(function() {
    const sessionPrefixes = [
        'food_timeout_', 'food_seats_', 'food_valid_', 'food_order_', 'food_created_',
        'purchase_token_', 'purchase_expires_at_', 'purchase_token_used_', 'purchase_created_at_',
        'ticket_quantities_', 'total_seats_', 'subtotal_', 'tax_amount_', 'total_amount_', 'tax_rate_',
        'payment_method_', 'selected_seats_', 'selected_seats_count_', 'ticket_selection_',
        'pending_checkout', 'last_order_id', 'last_showtime_id'
    ];
    
    const sessionKeysToRemove = [];
    for (let i = 0; i < sessionStorage.length; i++) {
        const key = sessionStorage.key(i);
        if (key) {
            const shouldRemove = sessionPrefixes.some(prefix => key.includes(prefix));
            if (shouldRemove) sessionKeysToRemove.push(key);
        }
    }
    
    sessionKeysToRemove.forEach(key => sessionStorage.removeItem(key));
    console.log('✅ SessionStorage limpiado:', sessionKeysToRemove.length, 'claves eliminadas');
})();
<?php endif; ?>

// ============================================
// FUNCIONES DE MODALES
// ============================================
function closeTimeoutModal() {
    const modal = document.getElementById('timeoutModal');
    if (!modal) return;
    modal.classList.remove('active');
    
    if (window.history && window.history.replaceState) {
        const url = new URL(window.location.href);
        url.searchParams.delete('timeout');
        window.history.replaceState({}, document.title, url.toString());
    }
    document.body.style.overflow = '';
}

// ✅ CORREGIDO: Limpiar URL al cerrar el modal de sesión expirada
function closeSessionExpiredModal() {
    const modal = document.getElementById('sessionExpiredModal');
    if (!modal) return;
    modal.classList.remove('active');
    document.body.style.overflow = '';
    
    // Limpiar parámetros de la URL para evitar que reaparezca al recargar
    if (window.history && window.history.replaceState) {
        const url = new URL(window.location.href);
        url.searchParams.delete('expired');
        url.searchParams.delete('session_expired');
        window.history.replaceState({}, document.title, url.toString());
        console.log('🧹 URL limpiada, eliminados parámetros de sesión expirada');
    }
}

// Cerrar modales con Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const timeoutModal = document.getElementById('timeoutModal');
        if (timeoutModal && timeoutModal.classList.contains('active')) closeTimeoutModal();
        
        const sessionModal = document.getElementById('sessionExpiredModal');
        if (sessionModal && sessionModal.classList.contains('active')) closeSessionExpiredModal();
    }
});

// Click fuera del modal
document.addEventListener('DOMContentLoaded', function() {
    const timeoutModal = document.getElementById('timeoutModal');
    if (timeoutModal && timeoutModal.classList.contains('active')) {
        document.body.style.overflow = 'hidden';
        timeoutModal.addEventListener('click', function(e) {
            if (e.target === this) closeTimeoutModal();
        });
    }
    
    const sessionModal = document.getElementById('sessionExpiredModal');
    if (sessionModal && sessionModal.classList.contains('active')) {
        document.body.style.overflow = 'hidden';
        sessionModal.addEventListener('click', function(e) {
            if (e.target === this) closeSessionExpiredModal();
        });
    }
});

// ============================================
// CARRUSEL
// ============================================
let currentSlideIndex = 0;
const totalSlides = <?= count($movies) ?>;
let autoSlideInterval;

function updateCarousel() {
    const container = document.getElementById('carouselSlides');
    if (!container) return;
    container.style.transform = `translateX(-${currentSlideIndex * 100}%)`;
    
    const dots = document.querySelectorAll('.carousel-dot');
    dots.forEach((dot, index) => {
        dot.classList.toggle('active', index === currentSlideIndex);
    });
}

function moveSlide(direction) {
    if (totalSlides <= 1) return;
    currentSlideIndex += direction;
    if (currentSlideIndex >= totalSlides) currentSlideIndex = 0;
    else if (currentSlideIndex < 0) currentSlideIndex = totalSlides - 1;
    updateCarousel();
    resetAutoSlide();
}

function goToSlide(index) {
    currentSlideIndex = index;
    updateCarousel();
    resetAutoSlide();
}

function startAutoSlide() {
    if (totalSlides > 1) {
        autoSlideInterval = setInterval(() => moveSlide(1), 6000);
    }
}

function resetAutoSlide() {
    clearInterval(autoSlideInterval);
    startAutoSlide();
}

// Animar tarjetas al cargar
document.querySelectorAll('.movie-card').forEach((card, index) => {
    card.style.animationDelay = (index * 0.1) + 's';
});

// Iniciar carrusel automático
document.addEventListener('DOMContentLoaded', () => {
    startAutoSlide();
});
</script>

</body>
</html>