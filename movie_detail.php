<?php
require_once 'config.php';
// ============================================
// HEADERS PARA PERMITIR EMBED DE YOUTUBE
// ============================================
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com https://www.youtube.com https://www.youtube-nocookie.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; img-src 'self' data: https:; font-src 'self' data: https://cdnjs.cloudflare.com https://fonts.gstatic.com; frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com; frame-ancestors 'self';");

// Regenerar id de sesión para un nuevo intento limpio
if (!isset($_SESSION['last_activity'])) {
    session_regenerate_id(true);
    $_SESSION['last_activity'] = time();
}

// Verificar si viene con expired
if (isset($_GET['expired']) && $_GET['expired'] === '1') {
    header('Location: index.php?expired=1');
    exit;
}

// ============================================
// OBTENER Y VALIDAR ID DE LA PELÍCULA
// ============================================
$movie_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : false;
if ($movie_id === false || $movie_id <= 0) {
    error_log("Intento de acceso a movie_detail.php con ID inválido: " . ($_GET['id'] ?? 'null') . " desde IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: index.php?error=id_invalido');
    exit;
}
if ($movie_id > 999999) {
    header('Location: index.php?error=id_invalido');
    exit;
}

// ============================================
// ✅ RATE LIMITING PARA PREVENIR SCRAPING
// ============================================
if (!checkRateLimit('movie_detail_view', 30, 5)) {
    error_log("Rate limit excedido en movie_detail.php desde IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: index.php?error=demasiadas_solicitudes');
    exit;
}

// Obtener datos de la película
$stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ? AND is_active = 1");
$stmt->execute([$movie_id]);
$movie = $stmt->fetch();
if (!$movie) {
    header('Location: index.php');
    exit;
}

// ============================================
// ✅ OBTENER DATOS DE TMDb SOLO PARA DATOS ADICIONALES (NO PARA IMÁGENES)
// ============================================
$tmdb_data = null;
try {
    $tmdb_data = getMovieFromTMDB($movie['title'], $movie['year'] ?? null);
} catch (Exception $e) {
    error_log("Error obteniendo datos de TMDb: " . $e->getMessage());
}
$tmdb_id = $tmdb_data['tmdb_id'] ?? null;

// ✅ VALIDAR Y SANITIZAR DATOS DE TMDb
if ($tmdb_data) {
    if (!empty($tmdb_data['description'])) {
        $tmdb_data['description'] = strip_tags($tmdb_data['description']);
        $tmdb_data['description'] = mb_substr($tmdb_data['description'], 0, 5000);
    }
    if (!empty($tmdb_data['genres'])) {
        $tmdb_data['genres'] = htmlspecialchars($tmdb_data['genres'], ENT_QUOTES, 'UTF-8');
    }
    if (!empty($tmdb_data['director'])) {
        $tmdb_data['director'] = htmlspecialchars($tmdb_data['director'], ENT_QUOTES, 'UTF-8');
    }
    if (!empty($tmdb_data['cast_members'])) {
        $tmdb_data['cast_members'] = htmlspecialchars($tmdb_data['cast_members'], ENT_QUOTES, 'UTF-8');
    }
    if (!empty($tmdb_data['country'])) {
        $tmdb_data['country'] = htmlspecialchars($tmdb_data['country'], ENT_QUOTES, 'UTF-8');
    }
}

// ============================================
// ✅ USAR SIEMPRE LAS IMÁGENES DE LA BASE DE DATOS
// ============================================
$poster_url = $movie['poster_url'] ?? '';
$backdrop_url = !empty($movie['banner_url']) ? $movie['banner_url'] : $poster_url;

// Solo usar TMDb si no hay imagen en la BD
if (empty($poster_url) && $tmdb_data && !empty($tmdb_data['poster_path'])) {
    $poster_url = $tmdb_data['poster_path'];
}
if (empty($backdrop_url) && $tmdb_data && !empty($tmdb_data['backdrop_path'])) {
    $backdrop_url = $tmdb_data['backdrop_path'];
}

if (empty($poster_url)) {
    $poster_url = getPlaceholderImage(300, 450, '🎬');
}
if (empty($backdrop_url)) {
    $backdrop_url = $poster_url;
}

// ============================================
// DATOS PARA LA VISTA - PRIORIZAR DATOS MANUALES
// ============================================
$description = $movie['description'];
if (empty($description) && !empty($tmdb_data['description'])) {
    $description = $tmdb_data['description'];
}
$duration = $movie['duration'];
if (empty($duration) && !empty($tmdb_data['runtime'])) {
    $duration = $tmdb_data['runtime'];
}
$genre = $movie['genre'];
if (empty($genre) && !empty($tmdb_data['genres'])) {
    $genre = $tmdb_data['genres'];
}
$year = $movie['year'];
if (empty($year) && !empty($tmdb_data['year'])) {
    $year = $tmdb_data['year'];
}
$director = $movie['director'];
if (empty($director) && !empty($tmdb_data['director']) && $tmdb_data['director'] !== 'No disponible') {
    $director = $tmdb_data['director'];
}
if (empty($director)) {
    $director = 'No disponible';
}

// ============================================
// PAÍS DE ORIGEN
// ============================================
$country = '';
if (!empty($movie['country_id'])) {
    $stmt = $pdo->prepare("SELECT name FROM countries WHERE id = ?");
    $stmt->execute([$movie['country_id']]);
    $country_data = $stmt->fetch();
    if ($country_data) {
        $country = $country_data['name'];
    }
}
if (empty($country) && $tmdb_id) {
    $api_key = TMDB_API_KEY;
    $url = TMDB_API_URL . "movie/{$tmdb_id}?api_key={$api_key}&language=es-ES";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, TMDB_TIMEOUT);
    $response = curl_exec($ch);
    curl_close($ch);
    $movie_data = json_decode($response, true);
    if (!empty($movie_data['production_countries']) && !empty($movie_data['production_countries'][0]['name'])) {
        $english_country = $movie_data['production_countries'][0]['name'];
        $country_translations = [
            'United States of America' => 'Estados Unidos de América',
            'United States' => 'Estados Unidos de América',
            'Japan' => 'Japón',
            'United Kingdom' => 'Reino Unido',
            'France' => 'Francia',
            'Germany' => 'Alemania',
            'South Korea' => 'Corea del Sur',
            'China' => 'China',
            'Canada' => 'Canadá',
            'Spain' => 'España',
            'Italy' => 'Italia',
            'Mexico' => 'México',
            'India' => 'India',
            'Australia' => 'Australia',
            'Venezuela' => 'Venezuela',
            'Argentina' => 'Argentina',
            'Colombia' => 'Colombia',
            'Chile' => 'Chile',
            'Peru' => 'Perú',
            'Brazil' => 'Brasil'
        ];
        $country = $country_translations[$english_country] ?? $english_country;
    }
}

// ============================================
// REPARTO - PRIORIZAR MANUAL
// ============================================
$actors = [];
$cast_members = [];
if (!empty($movie['cast_members'])) {
    $cast_members = array_map('trim', explode(',', $movie['cast_members']));
} else if ($tmdb_id) {
    $api_key = TMDB_API_KEY;
    $url = TMDB_API_URL . "movie/{$tmdb_id}/credits?api_key={$api_key}&language=es-ES";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, TMDB_TIMEOUT);
    $response = curl_exec($ch);
    curl_close($ch);
    $credits_data = json_decode($response, true);
    if (!empty($credits_data['cast'])) {
        $actors = array_slice($credits_data['cast'], 0, 6);
    }
}

$classification = $movie['classification'] ?? 'B (Mayores de 12)';
$trailer_url = $movie['trailer_url'] ?? '';
$title = $movie['title'];

$trailer_key = '';
if (!empty($trailer_url)) {
    preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&
?#]+)/', $trailer_url, $matches);
    if (!empty($matches[1])) {
        $trailer_key = $matches[1];
    }
}

// ============================================
// OBTENER HORARIOS
// ============================================
$currentDateTime = getCurrentDateTime();
$currentDate = getCurrentDate();
$stmt = $pdo->prepare("
    SELECT s.*, r.name as room_name, r.capacity, COALESCE(s.format, '2D') as format
    FROM showtimes s
    JOIN rooms r ON s.room_id = r.id
    WHERE s.movie_id = ? AND s.is_active = 1
    AND s.show_date >= ?
    AND DATE_ADD(CONCAT(s.show_date, ' ', s.show_time), INTERVAL ? MINUTE) > ?
    ORDER BY s.show_date, s.show_time
");
$stmt->execute([$movie_id, $currentDate, $duration, $currentDateTime]);
$showtimes = $stmt->fetchAll();

// Agrupar horarios por fecha
$showtimes_by_date = [];
foreach ($showtimes as $showtime) {
    $date = $showtime['show_date'];
    if (!isset($showtimes_by_date[$date])) {
        $showtimes_by_date[$date] = [];
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) as occupied FROM tickets WHERE showtime_id = ?");
    $stmt->execute([$showtime['id']]);
    $occupied = $stmt->fetch();
    $showtime['occupied'] = $occupied['occupied'];
    $showtime['available'] = $showtime['capacity'] - $occupied['occupied'];
    $showtime['is_full'] = $showtime['available'] <= 0;
    $showtime['has_started'] = strtotime($showtime['show_date'] . ' ' . $showtime['show_time']) < time();
    $showtimes_by_date[$date][] = $showtime;
}

$available_dates = array_keys($showtimes_by_date);
$first_date = !empty($available_dates) ? $available_dates[0] : null;

$display_format = '2D';
if (!empty($showtimes)) {
    $first_showtime = $showtimes[0];
    $display_format = $first_showtime['format'] ?? '2D';
}

$currencyConfig = [
    'symbol' => $siteConfig['currency_symbol'] ?? '$',
    'position' => $siteConfig['currency_position'] ?? 'left',
    'thousands' => $siteConfig['thousands_separator'] ?? '.',
    'decimal' => $siteConfig['decimal_separator'] ?? ',',
    'places' => intval($siteConfig['decimal_places'] ?? 2)
];

$year = $movie['year'] ?? '';
$year_display = !empty($year) ? ' (' . $year . ')' : '';
$siteConfig = getSiteConfig($pdo);
$pageTitle = $movie['title'] . $year_display . ' - ' . ($siteConfig['site_name'] ?? 'Cinema');
$backUrl = 'index.php';

require_once 'header.php';
?>

<link rel="stylesheet" href="assets/css/movie_detail.css">

<!-- Modal Tráiler -->
<div class="modal-overlay" id="trailerModal">
    <div class="modal-wrapper">
        <button class="close-modal" onclick="closeTrailer()">&times;</button>
        <div class="modal-content">
            <div class="trailer-container">
                <iframe
                    id="trailerIframe"
                    src=""
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    allowfullscreen
                    loading="lazy"
                    referrerpolicy="strict-origin-when-cross-origin"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- Hero Section -->
<section class="hero-section">
    <div class="hero-backdrop" style="background-image: url('<?= htmlspecialchars($backdrop_url) ?>');"></div>
    <div class="hero-content">
        <div class="hero-poster">
            <img src="<?= htmlspecialchars($poster_url) ?>" alt="<?= htmlspecialchars($title) ?>" title="<?= htmlspecialchars($title) ?>" loading="lazy">
        </div>
        <div class="hero-info">
            <h1><?= htmlspecialchars($title) ?></h1>
            <div class="hero-meta">
                <span class="meta-item">
                    <i class="far fa-clock"></i>
                    <?= $duration > 0 ? formatDuration($duration) : 'Duración no disponible' ?>
                </span>
                <span class="meta-item">
                    <i class="fas fa-tag"></i>
                    <?= !empty($genre) ? htmlspecialchars($genre) : 'Género no disponible' ?>
                </span>
                <?php if ($classification): ?>
                    <span class="meta-item">
                        <span class="badge"><?= htmlspecialchars($classification) ?></span>
                    </span>
                <?php endif; ?>
                <?php if ($year): ?>
                    <span class="meta-item">
                        <i class="far fa-calendar-alt"></i>
                        <?= htmlspecialchars($year) ?>
                    </span>
                <?php endif; ?>
            </div>
            <?php if ($trailer_key): ?>
                <button class="btn-trailer" onclick="openTrailer('<?= htmlspecialchars($trailer_key) ?>')">
                    <i class="fas fa-play"></i> Ver Tráiler
                </button>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Detalles -->
<section class="details-section">
    <div class="details-grid">
        <div class="details-column">
            <h2 class="section-title">📖 Sinopsis</h2>
            <p>
                <?php
                if (!empty($description)) {
                    $escaped_description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
                    $escaped_description = str_replace(["\r\n", "\r", "\n"], '<br>', $escaped_description);
                    echo $escaped_description;
                } else {
                    echo 'Sinopsis no disponible para esta película.';
                }
                ?>
            </p>
        </div>
        <div class="details-column">
            <h2 class="section-title">📋 Ficha Técnica</h2>
            <ul class="tech-list">
                <?php if (!empty($country)): ?>
                    <li>
                        <span class="label">País de Origen</span>
                        <span class="value"><?= htmlspecialchars($country) ?></span>
                    </li>
                <?php endif; ?>
                <li>
                    <span class="label">Director</span>
                    <span class="value"><?= htmlspecialchars($director) ?></span>
                </li>
                <?php if (!empty($cast_members)): ?>
                    <li>
                        <span class="label">Reparto Principal</span>
                        <div class="actors-list">
                            <?php foreach ($cast_members as $actor): ?>
                                <span class="actor-name"><?= htmlspecialchars($actor) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </li>
                <?php elseif (!empty($actors)): ?>
                    <li>
                        <span class="label">Reparto Principal</span>
                        <div class="actors-list">
                            <?php foreach ($actors as $actor): ?>
                                <span class="actor-name"><?= htmlspecialchars($actor['name'] ?? '') ?></span>
                            <?php endforeach; ?>
                        </div>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</section>

<!-- Selección de Funciones -->
<section class="showtimes-section">
    <div class="showtimes-container">
        <h2 class="section-title">🎬 Funciones</h2>
        <?php if (empty($showtimes_by_date)): ?>
            <div class="no-showtimes">
                <i class="fas fa-calendar-times"></i>
                <p>No hay funciones disponibles.</p>
            </div>
        <?php else: ?>
            <div class="dates-slider-wrapper">
                <button class="slider-btn prev" id="sliderPrev" onclick="slideDates('prev')">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div class="dates-slider" id="datesSlider">
                    <?php foreach ($showtimes_by_date as $date => $times):
                        $dateInfo = formatDateDayMonth($date);
                        $isPast = isDatePast($date);
                        $hasAvailable = false;
                        foreach ($times as $t) {
                            if (!$t['is_full']) {
                                $hasAvailable = true;
                                break;
                            }
                        }
                        $isSoldOut = !$hasAvailable && !$isPast;
                        $isFirst = $date === $first_date;
                        $isToday = ($date === date('Y-m-d'));
                    ?>
                        <!-- ✅ MODIFICADO: Asignación directa de clase 'active' para $isFirst -->
                        <div class="date-card <?= $isFirst ? 'active' : '' ?> <?= $isPast ? 'past' : '' ?> <?= $isSoldOut ? 'sold-out' : '' ?> <?= $isToday ? 'today' : '' ?> <?= !$isPast && !$isSoldOut ? 'has-showtimes' : '' ?>"
                            data-date="<?= $date ?>"
                            onclick="selectDate('<?= $date ?>')">
                            <span class="day"><?= $isToday ? 'HOY' : $dateInfo['day'] ?></span>
                            <span class="number"><?= $dateInfo['number'] ?></span>
                            <span class="month"><?= $dateInfo['month'] ?></span>
                            <?php if ($isPast): ?>
                                <span class="text-[8px] text-gray-500 block mt-1">Pasada</span>
                            <?php elseif ($isSoldOut): ?>
                                <span class="text-[8px] text-red-500 block mt-1 font-semibold">Agotado</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button class="slider-btn next" id="sliderNext" onclick="slideDates('next')">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div id="timesContainer">
                <?php if ($first_date && isset($showtimes_by_date[$first_date])): ?>
                    <div class="times-container" id="timesList_<?= $first_date ?>">
                        <?php foreach ($showtimes_by_date[$first_date] as $time):
                            $promotions = $time['promotions'] ? explode(',', $time['promotions']) : [];
                            $hasMonday = in_array('lunes_mitad', $promotions);
                            $hasPresale = in_array('preventa', $promotions);
                            $isFull = $time['is_full'];
                            $hasStarted = $time['has_started'] ?? false;
                            $language = $time['language'] ?? 'español';
                            $lang_label = $language == 'español' ? 'Español' : 'Subtítulos en Español';
                            $movieFormat = $time['format'] ?? '2D';
                            $formatClass = 'format-2d';
                            if (!empty($movieFormat)) {
                                $formatLower = strtolower($movieFormat);
                                $formatClass = 'format-' . str_replace(' ', '-', $formatLower);
                            }
                        ?>
                            <?php if (!$isFull): ?>
                                <a href="price_selection.php?showtime_id=<?= intval($time['id']) ?>" class="time-block">
                                    <span class="hour"><?= formatTimeVenezuela($time['show_time']) ?></span>
                                    <div class="room-format">
                                        <span class="room"><?= htmlspecialchars($time['room_name']) ?></span>
                                        <span class="separator">|</span>
                                        <span class="format-badge <?= $formatClass ?>"><?= htmlspecialchars($movieFormat) ?></span>
                                    </div>
                                    <span class="language-text"><?= htmlspecialchars($lang_label) ?></span>
                                    <?php if ($hasStarted): ?>
                                        <span class="started-badge"><i class="fas fa-clock"></i> Ya inició Función</span>
                                    <?php endif; ?>
                                    <?php if ($hasMonday): ?>
                                        <span class="promo-badge lunes">
                                            <span class="dot"></span>
                                            Lunes ½ Precio
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($hasPresale): ?>
                                        <span class="promo-badge preventa">
                                            <span class="dot"></span>
                                            Preventa
                                        </span>
                                    <?php endif; ?>
                                </a>
                            <?php else: ?>
                                <div class="time-block sold-out">
                                    <span class="hour"><?= formatTimeVenezuela($time['show_time']) ?></span>
                                    <div class="room-format">
                                        <span class="room"><?= htmlspecialchars($time['room_name']) ?></span>
                                        <span class="separator">|</span>
                                        <span class="format-badge <?= $formatClass ?>"><?= htmlspecialchars($movieFormat) ?></span>
                                    </div>
                                    <span class="language-text"><?= htmlspecialchars($lang_label) ?></span>
                                    <?php if ($hasStarted): ?>
                                        <span class="started-badge"><i class="fas fa-clock"></i> Ya inició Función</span>
                                    <?php endif; ?>
                                    <span class="sold-out-label">Agotado</span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once 'footer.php'; ?>

<script>
    const showtimesData = <?= json_encode($showtimes_by_date) ?>;
    const firstDate = '<?= $first_date ?>';
    const availableDates = <?= json_encode($available_dates) ?>;
    const currencyConfig = <?= json_encode($currencyConfig) ?>;</script>
<script src="assets/js/movie_detail.js"></script>
</body>

</html>