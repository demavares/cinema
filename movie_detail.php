<?php
require_once 'config.php';

// Obtener ID de la película
$movie_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($movie_id <= 0) {
    header('Location: index.php');
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

// Obtener datos de TMDb
$tmdb_data = getMovieFromTMDB($movie['title'], $movie['year'] ?? null);
$tmdb_id = $tmdb_data['tmdb_id'] ?? null;

// Obtener configuración de moneda
$siteConfig = getSiteConfig($pdo);
$currency_symbol = $siteConfig['currency_symbol'] ?? '$';
$currency_position = $siteConfig['currency_position'] ?? 'left';
$thousands_separator = $siteConfig['thousands_separator'] ?? '.';
$decimal_separator = $siteConfig['decimal_separator'] ?? ',';
$decimal_places = intval($siteConfig['decimal_places'] ?? 2);

// ============================================
// LIMPIAR SELECCIÓN DE BOLETOS Y ASIENTOS AL VOLVER DE PRICE_SELECTION
// ============================================
if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'price_selection.php') !== false) {
    // Limpiar sesión PHP
    $sessionKeys = [
        'ticket_quantities_' . $movie_id,
        'total_seats_' . $movie_id,
        'subtotal_' . $movie_id,
        'tax_amount_' . $movie_id,
        'total_amount_' . $movie_id,
        'food_seats_' . $movie_id,
        'food_timeout_' . $movie_id,
        'food_valid_' . $movie_id,
        'food_order_' . $movie_id,
        'purchase_token_' . $movie_id
    ];
    foreach ($sessionKeys as $key) {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
    
    // Limpiar sessionStorage via JavaScript al cargar
    echo '<script>
        sessionStorage.removeItem("ticket_selection_' . $movie_id . '");
        sessionStorage.removeItem("selected_seats_' . $movie_id . '");
        sessionStorage.removeItem("selected_seats_count_' . $movie_id . '");
        sessionStorage.removeItem("food_timeout_' . $movie_id . '");
        sessionStorage.removeItem("food_seats_' . $movie_id . '");
        console.log("🗑️ Selección limpiada (viene de price_selection)");
    </script>';
}

// ============================================
// LIMPIAR SESIÓN CUANDO EL USUARIO ENTRA DIRECTAMENTE DESDE INDEX
// ============================================
$referrer = $_SERVER['HTTP_REFERER'] ?? '';
if (empty($referrer) || strpos($referrer, 'index.php') !== false) {
    $sessionKeys = [
        'ticket_quantities_' . $movie_id,
        'total_seats_' . $movie_id,
        'subtotal_' . $movie_id,
        'tax_amount_' . $movie_id,
        'total_amount_' . $movie_id,
        'food_seats_' . $movie_id,
        'food_timeout_' . $movie_id,
        'food_valid_' . $movie_id,
        'food_order_' . $movie_id,
        'purchase_token_' . $movie_id
    ];
    foreach ($sessionKeys as $key) {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
}

// ============================================
// DATOS PARA LA VISTA - PRIORIZAR DATOS MANUALES
// ============================================

// Poster URL
$poster_url = $movie['poster_url'];

// Banner/Backdrop
$backdrop_url = $poster_url;
if (!empty($movie['banner_url'])) {
    $backdrop_url = $movie['banner_url'];
} elseif (!empty($tmdb_data['backdrop_path'])) {
    $backdrop_url = 'https://image.tmdb.org/t/p/original' . $tmdb_data['backdrop_path'];
}

// Descripción
$description = $movie['description'];
if (empty($description) && !empty($tmdb_data['description'])) {
    $description = $tmdb_data['description'];
}

// Duración
$duration = $movie['duration'];
if (empty($duration) && !empty($tmdb_data['runtime'])) {
    $duration = $tmdb_data['runtime'];
}

// Género
$genre = $movie['genre'];
if (empty($genre) && !empty($tmdb_data['genres'])) {
    $genre = $tmdb_data['genres'];
}

// Año
$year = $movie['year'];
if (empty($year) && !empty($tmdb_data['year'])) {
    $year = $tmdb_data['year'];
}

// Director
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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    $credits_data = json_decode($response, true);
    
    if (!empty($credits_data['cast'])) {
        $actors = array_slice($credits_data['cast'], 0, 6);
    }
}

// Clasificación
$classification = $movie['classification'] ?? 'B (Mayores de 12)';

// Trailer
$trailer_url = $movie['trailer_url'] ?? '';

// Título para la vista
$title = $movie['title'];

// Obtener key del trailer de YouTube
$trailer_key = '';
if (!empty($trailer_url)) {
    preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/', $trailer_url, $matches);
    if (!empty($matches[1])) {
        $trailer_key = $matches[1];
    }
}

// ============================================
// OBTENER HORARIOS - SIN PRECIO
// ============================================
$currentDateTime = getCurrentDateTime();
$currentDate = getCurrentDate();

$stmt = $pdo->prepare("
    SELECT s.*, r.name as room_name, r.capacity
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
    $showtimes_by_date[$date][] = $showtime;
}

// Obtener fechas disponibles
$available_dates = array_keys($showtimes_by_date);
$first_date = !empty($available_dates) ? $available_dates[0] : null;

// Pasar configuración de moneda a JavaScript
$currencyConfig = [
    'symbol' => $currency_symbol,
    'position' => $currency_position,
    'thousands' => $thousands_separator,
    'decimal' => $decimal_separator,
    'places' => $decimal_places
];

function formatDateDayMonth($date) {
    $timestamp = strtotime($date);
    $months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    $days = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
    return [
        'day' => $days[date('w', $timestamp)],
        'number' => date('d', $timestamp),
        'month' => $months[date('n', $timestamp) - 1]
    ];
}

function formatDuration($minutes) {
    if ($minutes <= 0) return 'No disponible';
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    if ($hours > 0 && $mins > 0) {
        return $hours . 'h ' . $mins . 'min';
    } elseif ($hours > 0) {
        return $hours . 'h';
    } else {
        return $mins . 'min';
    }
}

function isDatePast($date) {
    return strtotime($date) < strtotime(date('Y-m-d'));
}

// ============================================
// TÍTULO DEL NAVEGADOR
// ============================================
$year_display = !empty($year) ? ' (' . $year . ')' : '';
$pageTitle = $title . $year_display . ' - ' . ($siteConfig['site_name'] ?? 'Cinema Pro');
$backUrl = 'index.php';

require_once 'header.php';
?>

    <style>
        .hero-section {
            position: relative;
            width: 100%;
            min-height: 480px;
            background: #0a0a0f;
            overflow: hidden;
            display: flex;
            align-items: flex-end;
            padding: 0 40px 40px 40px;
        }
        
        .hero-backdrop {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center 20%;
            filter: blur(2px) brightness(0.3);
            transform: scale(1.05);
            z-index: 0;
        }
        
        .hero-backdrop::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 60%;
            background: linear-gradient(to top, #0a0a0f 0%, transparent 100%);
        }
        
        .hero-content {
            position: relative;
            z-index: 1;
            display: flex;
            gap: 40px;
            align-items: flex-end;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }
        
        .hero-poster {
            flex-shrink: 0;
            width: 220px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.8);
            transform: translateY(30px);
        }
        
        .hero-poster img {
            width: 100%;
            height: auto;
            display: block;
            aspect-ratio: 2/3;
            object-fit: cover;
        }
        
        .hero-info {
            flex: 1;
            padding-bottom: 10px;
        }
        
        .hero-info h1 {
            font-size: clamp(2rem, 4vw, 3.5rem);
            font-weight: 900;
            letter-spacing: -0.03em;
            text-transform: uppercase;
            color: #ffffff;
            line-height: 1.1;
            margin-bottom: 12px;
            text-shadow: 0 2px 20px rgba(0, 0, 0, 0.5);
        }
        
        .hero-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .hero-meta .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
            color: #9ca3af;
        }
        
        .hero-meta .meta-item i {
            color: #6366f1;
            font-size: 0.8rem;
        }
        
        .hero-meta .meta-item .badge {
            background: rgba(99, 102, 241, 0.2);
            color: #818cf8;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid rgba(99, 102, 241, 0.2);
        }
        
        .btn-trailer {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: white;
            padding: 12px 28px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-trailer:hover {
            background: rgba(99, 102, 241, 0.8);
            border-color: #6366f1;
            transform: scale(1.05);
        }
        
        .btn-trailer i {
            font-size: 1rem;
        }
        
        .details-section {
            background: #f4f5f7;
            padding: 60px 40px;
            border-top: 1px solid #e5e7eb;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .details-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 60px;
        }
        
        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #4f46e5;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .details-column p {
            font-size: 1rem;
            line-height: 1.8;
            color: #374151;
        }
        
        .tech-list {
            list-style: none;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .tech-list li {
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .tech-list .label {
            color: #6b7280;
            font-weight: 500;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .tech-list .value {
            color: #111827;
            font-weight: 600;
            font-size: 1rem;
        }
        
        .actors-list {
            display: flex;
            flex-wrap: wrap;
            gap: 4px 12px;
        }
        
        .actors-list .actor-name {
            color: #374151;
            font-size: 0.95rem;
            font-weight: 500;
        }
        
        .actors-list .actor-name:not(:last-child)::after {
            content: ',';
            color: #9ca3af;
        }
        
        .showtimes-section {
            background: #ffffff;
            padding: 40px;
        }
        
        .showtimes-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .showtimes-container .section-title {
            margin-bottom: 24px;
        }
        
        .dates-slider-wrapper {
            position: relative;
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .dates-slider {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            padding: 4px 0 8px 0;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        
        .dates-slider::-webkit-scrollbar {
            display: none;
        }
        
        .slider-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: #4f46e5;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border: none;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            z-index: 5;
            opacity: 0;
            pointer-events: none;
        }
        
        .slider-btn:hover {
            background: #6366f1;
            transform: translateY(-50%) scale(1.05);
        }
        
        .slider-btn.show {
            opacity: 1;
            pointer-events: auto;
        }
        
        .slider-btn.prev { left: -18px; }
        .slider-btn.next { right: -18px; }
      
        .date-card {
            flex: 0 0 auto;
            padding: 20px 28px;
            border-radius: 12px;
            border: 2px solid #e5e7eb;
            background: #ffffff;
            color: #6b7280;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 90px;
            text-align: center;
        }
        
        .date-card:hover:not(.sold-out):not(.past) {
            border-color: #4f46e5;
            background: #f4f5f7;
        }
        
        .date-card.active {
            border-color: #4f46e5;
            background: rgba(99, 102, 241, 0.08);
            color: #4f46e5;
        }
        
        .date-card .day {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: block;
        }
        
        .date-card .number {
            font-size: 1.8rem;
            font-weight: 800;
            display: block;
            line-height: 1.2;
            color: #111827;
        }
        
        .date-card.active .number {
            color: #4f46e5;
        }
        
        .date-card .month {
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            display: block;
        }
        
        .date-card.sold-out {
            border-color: #fca5a5;
            background: #fef2f2;
            color: #9ca3af;
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        .date-card.sold-out .number { color: #dc2626; }
        
        .date-card.past {
            border-color: #e5e7eb;
            background: #f3f4f6;
            color: #9ca3af;
            cursor: not-allowed;
            opacity: 0.5;
        }
        
        .date-card.has-showtimes { border-color: #818cf8; }
        .date-card.has-showtimes:not(.active):hover { border-color: #4f46e5; }
        
        .times-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
            padding: 20px 0;
        }
        
        .time-block {
            padding: 20px 22px;
            border-radius: 14px;
            background: #f4f5f7;
            border: 2px solid #e5e7eb;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: #111827;
            min-height: 100px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .time-block:hover:not(.sold-out) {
            border-color: #4f46e5;
            background: #ffffff;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        }
        
        .time-block .hour {
            font-size: 1.3rem;
            font-weight: 800;
            display: block;
            color: #111827;
        }
        
        .time-block .room {
            font-size: 0.9rem;
            color: #6b7280;
            display: block;
            margin-top: 4px;
            font-weight: 500;
        }
        
        .time-block .language-badge {
            display: inline-block;
            font-size: 0.8rem;
            font-weight: 700;
            padding: 4px 15px;
            border-radius: 20px;
            margin-top: 6px;
            letter-spacing: 0.3px;
        }
        .time-block .language-badge.espanol {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }
        .time-block .language-badge.subtitulos {
            background: #dbeafe;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }
        
        .time-block .promo-badge {
            display: inline-block;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 12px;
            margin-top: 4px;
        }
        .time-block .promo-badge.lunes {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }
        .time-block .promo-badge.preventa {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }
        
        .time-block.sold-out {
            border-color: #fca5a5;
            background: #fef2f2;
            cursor: not-allowed;
            opacity: 0.9;
        }
        
        .time-block.sold-out .hour { color: #9ca3af; }
        .time-block.sold-out .room { color: #9ca3af; }
        
        .time-block .sold-out-label {
            font-size: 0.7rem;
            font-weight: 700;
            color: #dc2626;
            text-transform: uppercase;
            display: block;
            margin-top: 4px;
            letter-spacing: 0.5px;
        }
        
        .no-showtimes {
            text-align: center;
            padding: 40px 0;
            color: #6b7280;
        }
        
        .no-showtimes i {
            font-size: 3rem;
            display: block;
            margin-bottom: 12px;
            color: #9ca3af;
        }
        
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(8px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .modal-overlay.active { display: flex; }
        
        .modal-wrapper {
            position: relative;
            width: 100%;
            max-width: 900px;
            animation: modalFadeIn 0.3s ease;
        }

        .modal-content {
            background: #000000;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .close-modal {
            position: absolute;
            top: -16px;
            right: -16px;
            width: 40px;
            height: 40px;
            background: #6366f1;
            color: #ffffff;
            border: 2px solid #ffffff;
            border-radius: 50%;
            font-size: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 20;
            box-shadow: 0 4px 12px rgba(0,0,0,0.4);
            transition: all 0.3s ease;
        }

        .close-modal:hover {
            background: #4f46e5;
            transform: scale(1.1);
        }

        .trailer-container {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
        }

        .trailer-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: 0;
        }
        
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.95) translateY(20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
        
        @media (max-width: 1024px) {
            .details-grid { gap: 40px; }
        }
        
        @media (max-width: 768px) {
            .hero-section {
                padding: 20px 20px 30px 20px;
                min-height: 380px;
            }
            .hero-content {
                flex-direction: column;
                align-items: center;
                gap: 16px;
            }
            .hero-poster {
                width: 200px;
                max-width: 200px;
                transform: translateY(0);
                align-self: center;
            }
            .hero-info {
                text-align: center;
                padding-bottom: 0;
            }
            .hero-info h1 { 
                font-size: 1.8rem; 
                margin-bottom: 12px; 
                line-height: 1.35; 
            }
            
            .hero-meta {
                justify-content: center;
                gap: 10px;
                margin-bottom: 16px;
                line-height: 1.2;
            }
            .hero-meta .meta-item {
                font-size: 0.85rem;
                gap: 4px;
            }

            .btn-trailer {
                margin-top: 10px;
            }

            .details-section { padding: 30px 20px; }
            .details-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            .showtimes-section { padding: 30px 20px; }
            .times-container { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); }
            .slider-btn {
                width: 30px;
                height: 30px;
                font-size: 12px;
            }
            .slider-btn.prev { left: -10px; }
            .slider-btn.next { right: -10px; }
            .date-card {
                padding: 12px 18px;
                min-width: 70px;
            }
            .date-card .number { font-size: 1.5rem; }
            .close-modal {
                top: -12px;
                right: -12px;
                width: 34px;
                height: 34px;
                font-size: 16px;
            }
        }
        
        @media (max-width: 480px) {
            .hero-poster { width: 200px; }
            .hero-info h1 { 
                font-size: 1.4rem; 
                line-height: 1.50; 
                margin-bottom: 10px; 
            }
            .hero-meta { gap: 8px; margin-bottom: 14px; }
            
            .btn-trailer {
                padding: 10px 22px;
                font-size: 0.85rem;
                margin-top: 8px;
            }
            
            .times-container {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 10px;
            }
            .time-block {
                padding: 14px 16px;
                min-height: 80px;
            }
            .time-block .hour { font-size: 1.1rem; }
            .time-block .room { font-size: 0.75rem; }
            .time-block .language-badge {
                font-size: 0.6rem;
                padding: 2px 10px;
            }
            .date-card {
                padding: 10px 14px;
                min-width: 60px;
            }
            .date-card .number { font-size: 1.2rem; }
            .slider-btn {
                width: 26px;
                height: 26px;
                font-size: 10px;
            }
            .slider-btn.prev { left: -8px; }
            .slider-btn.next { right: -8px; }
        }
    </style>

    <!-- Modal Tráiler -->
    <div class="modal-overlay" id="trailerModal">
        <div class="modal-wrapper">
            <button class="close-modal" onclick="closeTrailer()">&times;</button>
            <div class="modal-content">
                <div class="trailer-container">
                    <iframe id="trailerIframe" src="" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-backdrop" style="background-image: url('<?= htmlspecialchars($backdrop_url) ?>');"></div>
        
        <div class="hero-content">
            <div class="hero-poster">
                <img src="<?= htmlspecialchars($poster_url) ?>" alt="<?= htmlspecialchars($title) ?>" loading="lazy">
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
                    <?php if($classification): ?>
                    <span class="meta-item">
                        <span class="badge"><?= htmlspecialchars($classification) ?></span>
                    </span>
                    <?php endif; ?>
                    <?php if($year): ?>
                    <span class="meta-item">
                        <i class="far fa-calendar-alt"></i>
                        <?= $year ?>
                    </span>
                    <?php endif; ?>
                </div>
                
                <?php if($trailer_key): ?>
                <button class="btn-trailer" onclick="openTrailer('<?= $trailer_key ?>')">
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
                <p><?= !empty($description) ? nl2br(htmlspecialchars($description)) : 'Sinopsis no disponible para esta película.' ?></p>
            </div>
            
            <div class="details-column">
                <h2 class="section-title">📋 Ficha Técnica</h2>
                <ul class="tech-list">
                    <?php if(!empty($country)): ?>
                    <li>
                        <span class="label">País de Origen</span>
                        <span class="value"><?= htmlspecialchars($country) ?></span>
                    </li>
                    <?php endif; ?>
                    <li>
                        <span class="label">Director</span>
                        <span class="value"><?= htmlspecialchars($director) ?></span>
                    </li>
                    <?php if(!empty($cast_members)): ?>
                    <li>
                        <span class="label">Reparto Principal</span>
                        <div class="actors-list">
                            <?php foreach($cast_members as $actor): ?>
                                <span class="actor-name"><?= htmlspecialchars($actor) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </li>
                    <?php elseif(!empty($actors)): ?>
                    <li>
                        <span class="label">Reparto Principal</span>
                        <div class="actors-list">
                            <?php foreach($actors as $actor): ?>
                                <span class="actor-name"><?= htmlspecialchars($actor['name']) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </section>
    
    <!-- Selección de Funciones - SIN PRECIO -->
    <section class="showtimes-section">
        <div class="showtimes-container">
            <h2 class="section-title">🎬 Funciones</h2>
            
            <?php if(empty($showtimes_by_date)): ?>
                <div class="no-showtimes">
                    <i class="fas fa-calendar-times"></i>
                    <p>No hay funciones disponibles para esta película.</p>
                </div>
            <?php else: ?>
            
            <div class="dates-slider-wrapper">
                <button class="slider-btn prev" id="sliderPrev" onclick="slideDates('prev')">
                    <i class="fas fa-chevron-left"></i>
                </button>
                
                <div class="dates-slider" id="datesSlider">
                    <?php foreach($showtimes_by_date as $date => $times): 
                        $dateInfo = formatDateDayMonth($date);
                        $isPast = isDatePast($date);
                        $hasAvailable = false;
                        foreach($times as $t) {
                            if(!$t['is_full']) {
                                $hasAvailable = true;
                                break;
                            }
                        }
                        $isSoldOut = !$hasAvailable && !$isPast;
                        $isFirst = $date === $first_date;
                    ?>
                        <div class="date-card <?= $isFirst ? 'active' : '' ?> <?= $isPast ? 'past' : '' ?> <?= $isSoldOut ? 'sold-out' : '' ?> <?= !$isPast && !$isSoldOut ? 'has-showtimes' : '' ?>" 
                             data-date="<?= $date ?>"
                             onclick="selectDate('<?= $date ?>')">
                            <span class="day"><?= $dateInfo['day'] ?></span>
                            <span class="number"><?= $dateInfo['number'] ?></span>
                            <span class="month"><?= $dateInfo['month'] ?></span>
                            <?php if($isPast): ?>
                                <span class="text-[8px] text-gray-500 block mt-1">Pasada</span>
                            <?php elseif($isSoldOut): ?>
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
                <?php if($first_date && isset($showtimes_by_date[$first_date])): ?>
                    <div class="times-container" id="timesList_<?= $first_date ?>">
                        <?php foreach($showtimes_by_date[$first_date] as $time): 
                            $promotions = $time['promotions'] ? explode(',', $time['promotions']) : [];
                            $hasMonday = in_array('lunes_mitad', $promotions);
                            $hasPresale = in_array('preventa', $promotions);
                            $isFull = $time['is_full'];
                            $language = $time['language'] ?? 'español';
                            $lang_class = $language == 'español' ? 'espanol' : 'subtitulos';
                            $lang_label = $language == 'español' ? 'Español' : 'Subtítulos en Español';
                        ?>
                            <?php if(!$isFull): ?>
                            <a href="price_selection.php?showtime_id=<?= $time['id'] ?>" class="time-block">
                                <span class="hour"><?= formatTimeVenezuela($time['show_time']) ?></span>
                                <span class="room"><?= htmlspecialchars($time['room_name']) ?></span>
                                <span class="language-badge <?= $lang_class ?>"><?= $lang_label ?></span>
                                <?php if($hasMonday): ?>
                                    <span class="promo-badge lunes">Lunes ½ Precio</span>
                                <?php endif; ?>
                                <?php if($hasPresale): ?>
                                    <span class="promo-badge preventa">Preventa</span>
                                <?php endif; ?>
                            </a>
                            <?php else: ?>
                            <div class="time-block sold-out">
                                <span class="hour"><?= formatTimeVenezuela($time['show_time']) ?></span>
                                <span class="room"><?= htmlspecialchars($time['room_name']) ?></span>
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
        const currencyConfig = <?= json_encode($currencyConfig) ?>;
        
        function selectDate(date) {
            document.querySelectorAll('.date-card').forEach(card => {
                card.classList.remove('active');
                if (card.dataset.date === date) {
                    card.classList.add('active');
                }
            });
            
            const container = document.getElementById('timesContainer');
            if (showtimesData[date]) {
                let html = `<div class="times-container" id="timesList_${date}">`;
                showtimesData[date].forEach(time => {
                    const isFull = time.is_full;
                    const promotions = time.promotions ? time.promotions.split(',') : [];
                    const hasMonday = promotions.includes('lunes_mitad');
                    const hasPresale = promotions.includes('preventa');
                    const language = time.language || 'español';
                    const langClass = language == 'español' ? 'espanol' : 'subtitulos';
                    const langLabel = language == 'español' ? 'Español' : 'Subtítulos en Español';
                    
                    if (!isFull) {
                        html += `
                            <a href="price_selection.php?showtime_id=${time.id}" class="time-block">
                                <span class="hour">${formatTimeVenezuela(time.show_time)}</span>
                                <span class="room">${escapeHtml(time.room_name)}</span>
                                <span class="language-badge ${langClass}">${langLabel}</span>
                                ${hasMonday ? `<span class="promo-badge lunes">Lunes ½ Precio</span>` : ''}
                                ${hasPresale ? `<span class="promo-badge preventa">Preventa</span>` : ''}
                            </a>
                        `;
                    } else {
                        html += `
                            <div class="time-block sold-out">
                                <span class="hour">${formatTimeVenezuela(time.show_time)}</span>
                                <span class="room">${escapeHtml(time.room_name)}</span>
                                <span class="sold-out-label">Agotado</span>
                            </div>
                        `;
                    }
                });
                html += `</div>`;
                container.innerHTML = html;
            } else {
                container.innerHTML = `<div class="no-showtimes"><p>No hay funciones disponibles para esta fecha.</p></div>`;
            }
        }
        
        function formatTimeVenezuela(timeStr) {
            if (!timeStr) return '';
            const parts = timeStr.split(':');
            let hours = parseInt(parts[0], 10);
            const minutes = parts[1] || '00';
            const ampm = hours >= 12 ? 'PM' : 'AM';
            const hour12 = hours % 12 || 12;
            const formattedHour = hour12.toString().padStart(2, '0');
            return `${formattedHour}:${minutes.padStart(2, '0')} ${ampm}`;
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function slideDates(direction) {
            const slider = document.getElementById('datesSlider');
            const cardWidth = slider.querySelector('.date-card')?.offsetWidth || 100;
            const gap = 12;
            const scrollAmount = cardWidth + gap;
            
            if (direction === 'next') {
                slider.scrollBy({ left: scrollAmount, behavior: 'smooth' });
            } else {
                slider.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
            }
        }
        
        function updateSliderButtons() {
            const slider = document.getElementById('datesSlider');
            const prevBtn = document.getElementById('sliderPrev');
            const nextBtn = document.getElementById('sliderNext');
            
            if (window.innerWidth < 769) {
                const isAtStart = slider.scrollLeft <= 10;
                const isAtEnd = slider.scrollLeft + slider.clientWidth >= slider.scrollWidth - 10;
                
                prevBtn.classList.toggle('show', !isAtStart);
                nextBtn.classList.toggle('show', !isAtEnd);
            } else {
                prevBtn.classList.remove('show');
                nextBtn.classList.remove('show');
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const slider = document.getElementById('datesSlider');
            
            slider.addEventListener('scroll', updateSliderButtons);
            window.addEventListener('resize', updateSliderButtons);
            
            setTimeout(updateSliderButtons, 100);
        });
        
        function openTrailer(trailerKey) {
            const modal = document.getElementById('trailerModal');
            const iframe = document.getElementById('trailerIframe');
            
            if (trailerKey) {
                iframe.src = 'https://www.youtube.com/embed/' + trailerKey + '?autoplay=1&rel=0';
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }
        
        function closeTrailer() {
            const modal = document.getElementById('trailerModal');
            const iframe = document.getElementById('trailerIframe');
            
            modal.classList.remove('active');
            iframe.src = '';
            document.body.style.overflow = '';
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeTrailer();
        });
        
        document.getElementById('trailerModal').addEventListener('click', function(e) {
            if (e.target === this) closeTrailer();
        });
    </script>
</body>
</html>