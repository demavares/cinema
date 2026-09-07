<?php
require_once 'config.php';

// ============================================
// CORREGIDO: DETECTAR SESIÓN EXPIRADA (UNIFICADO CON MODAL)
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
        <button class="timeout-btn" id="closeTimeoutBtn"><i class="fas fa-home mr-2"></i> Entendido</button>
    </div>
</div>

<!-- ============================================ -->
<!-- MODAL DE SESIÓN EXPIRADA POR INACTIVIDAD (UNIFICADO) -->
<!-- ============================================ -->
<div class="session-expired-modal-overlay <?= $mostrar_alerta ? 'active' : '' ?>" id="sessionExpiredModal">
    <div class="session-expired-modal">
        <div class="session-expired-icon">⏰</div>
        <h2 class="session-expired-title">¡Sesión Expirada!</h2>
        <p class="session-expired-text">Tu sesión ha expirado por inactividad.<br>Por favor, selecciona nuevamente tus boletos para continuar.</p>
        <div class="session-expired-actions">
            <button class="session-expired-btn" id="closeSessionBtn1"><i class="fas fa-film mr-2"></i> Ver Cartelera</button>
            <button class="session-expired-btn-secondary" id="closeSessionBtn2">Cerrar</button>
        </div>
    </div>
</div>

<link rel="stylesheet" href="assets/css/index.css">

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
                                <img src="<?= htmlspecialchars($poster_url) ?>" alt="<?= htmlspecialchars($m_title) ?>" loading="lazy">
                            </a>
                            <div class="hero-info">
                                <h1><?= htmlspecialchars($m_title) ?></h1>
                                <div class="hero-meta">
                                    <?php if ($m_duration > 0): ?>
                                        <span class="meta-item"><i class="far fa-clock text-indigo-400"></i><?= formatDuration($m_duration) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($m_genre)): ?>
                                        <span class="meta-item"><i class="fas fa-tag text-indigo-400"></i><?= htmlspecialchars($m_genre) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($m_classification)): ?>
                                        <span class="meta-item"><span class="certification-tag b"><?= htmlspecialchars($m_classification) ?></span></span>
                                    <?php endif; ?>
                                    <?php if (!empty($m_year)): ?>
                                        <span class="meta-item"><i class="far fa-calendar-alt text-indigo-400"></i><?= htmlspecialchars($m_year) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($m_description)): ?>
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

            <?php if (count($movies) > 1): ?>
                <button class="carousel-nav-btn prev" id="carouselPrev"><i class="fas fa-chevron-left"></i></button>
                <button class="carousel-nav-btn next" id="carouselNext"><i class="fas fa-chevron-right"></i></button>
                <div class="carousel-dots">
                    <?php foreach ($movies as $i => $m): ?>
                        <span class="carousel-dot <?= $i === 0 ? 'active' : '' ?>" data-slide="<?= $i ?>"></span>
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
            <p class="text-gray-500 text-sm">El administrador debe agregar películas y horarios desde el <a href="admin/index.php" class="text-indigo-600 hover:underline">panel de configuración</a>.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-8">
            <?php foreach ($movies as $index => $movie):
                $classification = $movie['classification'] ?? '';
                $certClass = 'b';
                $certLabel = 'B (Mayores de 12)';
                if (!empty($classification)) {
                    $certLabel = $classification;
                    if (strpos($classification, 'A') !== false) {
                        $certClass = 'a';
                    } elseif (strpos($classification, 'C') !== false) {
                        $certClass = 'c';
                    }
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
                        <?php if ($hasPresale): ?>
                            <img src="admin/img/preventa.png" alt="PREVENTA" class="presale-ribbon-img">
                        <?php endif; ?>
                    </a>
                    <div class="p-4">
                        <h3 class="font-bold text-gray-900 text-base truncate" title="<?= htmlspecialchars($movie['title']) ?>">
                            <a href="movie_detail.php?id=<?= intval($movie['id']) ?>" class="hover:text-indigo-600 transition-colors"><?= htmlspecialchars($movie['title']) ?></a>
                        </h3>

                        <?php if (count($genre_names) > 0): ?>
                            <div class="flex flex-wrap gap-1 mt-2">
                                <?php $display_genres = array_slice($genre_names, 0, 2);
                                foreach ($display_genres as $genre_name): ?>
                                    <span class="genre-tag"><?= htmlspecialchars($genre_name) ?></span>
                                <?php endforeach; ?>
                                <?php if (count($genre_names) > 2): ?>
                                    <span class="genre-tag">+<?= count($genre_names) - 2 ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="info-row">
                            <?php if ($certLabel): ?>
                                <span class="certification-tag <?= $certClass ?>"><?= htmlspecialchars($certLabel) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="flex items-center gap-3 mt-2 text-xs text-gray-500">
                            <?php if ($duration > 0): ?>
                                <span><i class="far fa-clock mr-1"></i><?= $formatted_duration ?></span>
                            <?php endif; ?>
                            <?php if ($movie['year']): ?>
                                <span><i class="far fa-calendar-alt mr-1"></i><?= htmlspecialchars($movie['year']) ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($director)): ?>
                            <div class="mt-1 text-xs text-gray-500"><span class="text-gray-600">Dir: <?= htmlspecialchars($director) ?></span></div>
                        <?php endif; ?>

                        <button data-movie-id="<?= intval($movie['id']) ?>" class="btn-reserve mt-3 movie-detail-btn"><i class="fas fa-ticket-alt"></i> Ver Funciones</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</main>

<?php require_once 'footer.php'; ?>

<script nonce="<?= htmlspecialchars($cspNonce ?? '') ?>">
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

    function closeSessionExpiredModal() {
        const modal = document.getElementById('sessionExpiredModal');
        if (!modal) return;
        modal.classList.remove('active');
        document.body.style.overflow = '';
        if (window.history && window.history.replaceState) {
            const url = new URL(window.location.href);
            url.searchParams.delete('expired');
            url.searchParams.delete('session_expired');
            window.history.replaceState({}, document.title, url.toString());
            console.log('🧹 URL limpiada, eliminados parámetros de sesión expirada');
        }
    }

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

    // ============================================
    // EVENT LISTENERS (Reemplazo de onclick inline)
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        // Botones de navegación del carrusel
        const prevBtn = document.getElementById('carouselPrev');
        const nextBtn = document.getElementById('carouselNext');

        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                moveSlide(-1);
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                moveSlide(1);
            });
        }

        // Dots del carrusel
        const dots = document.querySelectorAll('.carousel-dot');
        dots.forEach((dot, index) => {
            dot.addEventListener('click', function() {
                goToSlide(index);
            });
        });

        // Botones de cerrar modales
        const closeTimeoutBtn = document.getElementById('closeTimeoutBtn');
        if (closeTimeoutBtn) {
            closeTimeoutBtn.addEventListener('click', closeTimeoutModal);
        }

        const closeSessionBtn1 = document.getElementById('closeSessionBtn1');
        if (closeSessionBtn1) {
            closeSessionBtn1.addEventListener('click', closeSessionExpiredModal);
        }

        const closeSessionBtn2 = document.getElementById('closeSessionBtn2');
        if (closeSessionBtn2) {
            closeSessionBtn2.addEventListener('click', closeSessionExpiredModal);
        }

        // Botones de ver funciones en tarjetas
        const movieDetailBtns = document.querySelectorAll('.movie-detail-btn');
        movieDetailBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const movieId = this.getAttribute('data-movie-id');
                if (movieId) {
                    window.location.href = 'movie_detail.php?id=' + movieId;
                }
            });
        });

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

        // Animar tarjetas al cargar
        document.querySelectorAll('.movie-card').forEach((card, index) => {
            card.style.animationDelay = (index * 0.1) + 's';
        });

        // Iniciar carrusel automático
        startAutoSlide();
    });
</script>

</body>

</html>