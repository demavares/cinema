<?php
// ============================================
// CONFIGURACIÓN DE SESIÓN Y SEGURIDAD
// ============================================
date_default_timezone_set('America/Caracas');

// ============================================
// ✅ CSP CON NONCE (PÚBLICO) / COMPATIBLE (ADMIN)
// ============================================
function getCSPNonce(): string {
    static $nonce = null;
    if ($nonce === null) {
        $nonce = base64_encode(random_bytes(16));
    }
    return $nonce;
}
$cspNonce = getCSPNonce();

if (!headers_sent()) {
    $currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $adminScripts = ['admin.php', 'room_builder.php'];

    if (in_array($currentScript, $adminScripts, true)) {
        // ✅ PANEL ADMIN: compatibilidad con scripts/handlers inline legacy
        $cspDirectives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com",
            "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com",
            "img-src 'self' data: https: blob:",
            "font-src 'self' data: https://cdnjs.cloudflare.com https://fonts.gstatic.com",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "upgrade-insecure-requests"
        ];
    } else {
        // ✅ PÁGINAS PÚBLICAS: CSP estricta con nonce
        $cspDirectives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-" . $cspNonce . "' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com",
            "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com",
            "img-src 'self' data: https: blob:",
            "font-src 'self' data: https://cdnjs.cloudflare.com https://fonts.gstatic.com",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "upgrade-insecure-requests"
        ];
    }

    header("Content-Security-Policy: " . implode('; ', $cspDirectives));
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 0);
    ini_set('session.gc_maxlifetime', 3600);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax');

    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }

    session_start();
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
    session_unset();
    session_destroy();
    session_start();
}

$_SESSION['last_activity'] = time();

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// ============================================
// CARGAR VARIABLES DE ENTORNO DESDE .env
// ============================================
function loadEnv($path = '.env')
{
    if (!file_exists($path)) {
        error_log("❌ Error: El archivo .env no existe en: " . $path);
        return false;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        error_log("❌ Error: No se pudo leer el archivo .env");
        return false;
    }

    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")
            ) {
                $value = substr($value, 1, -1);
            }

            if (!array_key_exists($key, $_ENV) && !getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }

    return true;
}

function env($key, $default = null)
{
    $value = getenv($key);

    if ($value === false) {
        $value = $_ENV[$key] ?? null;
    }

    if ($value === null) {
        return $default;
    }

    switch (strtolower($value)) {
        case 'true':
        case '(true)':
            return true;
        case 'false':
        case '(false)':
            return false;
        case 'empty':
        case '(empty)':
            return '';
        case 'null':
        case '(null)':
            return null;
    }

    return $value;
}

/**
 * Registra datos en el log de errores de forma segura,
 * omitiendo campos sensibles.
 */
function secure_log($data, $message = "Datos procesados")
{
    $sensitiveKeys = [
        'password',
        'confirm_password',
        'current_password',
        'new_password',
        'csrf_token',
        'api_key',
        'card_number'
    ];

    $safeData = $data;

    foreach ($sensitiveKeys as $key) {
        if (isset($safeData[$key])) {
            $safeData[$key] = '*** REDACTED ***';
        }
    }

    error_log($message . " - Data: " . print_r($safeData, true));
}

loadEnv(__DIR__ . '/.env');

// ============================================
// CONFIGURACIÓN DE BASE DE DATOS
// ============================================
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_NAME', env('DB_NAME', 'cinema_db'));
define('TMDB_API_KEY', env('TMDB_API_KEY', ''));
define('TMDB_API_URL', env('TMDB_API_URL', 'https://api.themoviedb.org/3/'));

if (empty(DB_USER) || empty(DB_NAME)) {
    error_log("❌ Error: Faltan credenciales de base de datos en .env");
    die("Error de configuración. Contacte al administrador.");
}

if (empty(TMDB_API_KEY)) {
    error_log("⚠️ Advertencia: TMDB_API_KEY no configurada en .env");
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '-04:00'"
        ]
    );
} catch (PDOException $e) {
    error_log("Error de conexión: " . $e->getMessage());
    die("Error de conexión a la base de datos. Por favor, contacte al administrador.");
}

// ============================================
// CONFIGURACIÓN DEL SITIO
// ============================================
function getSiteConfig($pdo)
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $defaults = [
        'site_name' => 'Cinema Pro',
        'site_logo' => '',
        'footer_logo' => '',
        'site_favicon' => '',
        'footer_copyright' => '© ' . date('Y') . ' Cinema. Todos los derechos reservados.',
        'currency_symbol' => '$',
        'currency_position' => 'left',
        'thousands_separator' => '.',
        'decimal_separator' => ',',
        'decimal_places' => '2',
        'address' => '',
        'phone' => '',
        'email' => '',
        'instagram' => '',
        'facebook' => '',
        'twitter' => '',
        'telegram' => '',
        'whatsapp' => ''
    ];

    try {
        $stmt = $pdo->query("SELECT key_name, value FROM site_config");
        $rows = $stmt->fetchAll();

        $config = [];

        foreach ($rows as $row) {
            $config[$row['key_name']] = $row['value'];
        }

        foreach ($defaults as $key => $default_value) {
            if (!isset($config[$key]) || $config[$key] === '') {
                $config[$key] = $default_value;
            }
        }

        return $config;
    } catch (PDOException $e) {
        error_log("Error cargando configuración del sitio: " . $e->getMessage());
        return $defaults;
    }
}

function getFaviconHref($siteConfig)
{
    $favicon = trim($siteConfig['site_favicon'] ?? ($siteConfig['favicon'] ?? ''));

    if ($favicon === '') {
        return 'favicon.png';
    }

    $urlPath = parse_url($favicon, PHP_URL_PATH);
    $localPath = ltrim($urlPath ?: $favicon, '/');

    if ($localPath !== '' && is_file($localPath)) {
        return $favicon . '?v=' . filemtime($localPath);
    }

    if (filter_var($favicon, FILTER_VALIDATE_URL)) {
        return $favicon;
    }

    return is_file('favicon.png') ? 'favicon.png' : $favicon;
}

// ============================================
// FORMATEAR MONEDA
// ============================================
function formatCurrency($amount, $config = null)
{
    if ($config === null) {
        global $pdo;
        $config = getSiteConfig($pdo);
    }

    $symbol = $config['currency_symbol'] ?? '$';
    $position = $config['currency_position'] ?? 'left';
    $thousands = $config['thousands_separator'] ?? '.';
    $decimal = $config['decimal_separator'] ?? ',';
    $decimals = intval($config['decimal_places'] ?? 2);

    $formatted = number_format($amount, $decimals, $decimal, $thousands);

    return $position === 'right' ? $formatted . ' ' . $symbol : $symbol . $formatted;
}

// ============================================
// TMDb
// ============================================
function getMovieFromTMDB($title, $year = null)
{
    $api_key = TMDB_API_KEY;

    if (empty($api_key)) {
        error_log("⚠️ TMDB_API_KEY no configurada");
        return null;
    }

    $query = urlencode($title);
    $url = TMDB_API_URL . "search/movie?api_key={$api_key}&query={$query}&language=es-ES";

    if ($year) {
        $url .= "&year={$year}";
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("Error en API TMDB: HTTP $httpCode");
        return null;
    }

    $data = json_decode($response, true);

    if (!empty($data['results'])) {
        $movie_data = $data['results'][0];
        $tmdb_id = $movie_data['id'];

        $detail_url = TMDB_API_URL . "movie/{$tmdb_id}?api_key={$api_key}&language=es-ES&append_to_response=credits";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $detail_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $detail_response = curl_exec($ch);
        curl_close($ch);

        $detail_data = json_decode($detail_response, true);

        $genres = [];
        if (isset($detail_data['genres'])) {
            foreach ($detail_data['genres'] as $genre) {
                $genres[] = $genre['name'];
            }
        }

        $directors = [];
        if (isset($detail_data['credits']['crew'])) {
            foreach ($detail_data['credits']['crew'] as $crew) {
                if ($crew['job'] === 'Director') {
                    $directors[] = $crew['name'];
                }
            }
        }

        $director = !empty($directors) ? implode(' y ', $directors) : 'No disponible';

        $cast = [];
        if (isset($detail_data['credits']['cast'])) {
            $top_cast = array_slice($detail_data['credits']['cast'], 0, 6);
            foreach ($top_cast as $actor) {
                $cast[] = $actor['name'];
            }
        }

        $cast_members = !empty($cast) ? implode(', ', $cast) : '';

        $country = '';
        if (isset($detail_data['production_countries']) && !empty($detail_data['production_countries'])) {
            $english_country = $detail_data['production_countries'][0]['name'];

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

        return [
            'tmdb_id' => $tmdb_id,
            'description' => $movie_data['overview'] ?? '',
            'poster_path' => $movie_data['poster_path'] ?? null,
            'backdrop_path' => $movie_data['backdrop_path'] ?? null,
            'vote_average' => $movie_data['vote_average'] ?? 0,
            'genres' => implode(', ', $genres),
            'director' => $director,
            'cast_members' => $cast_members,
            'country' => $country,
            'runtime' => $detail_data['runtime'] ?? 0,
            'release_date' => $movie_data['release_date'] ?? '',
            'year' => !empty($movie_data['release_date']) ? date('Y', strtotime($movie_data['release_date'])) : null
        ];
    }

    return null;
}

// ============================================
// LIBERAR ASIENTOS EXPIRADOS
// ============================================
function releaseExpiredSeats($pdo)
{
    $currentDateTime = date('Y-m-d H:i:s');
    $total_released = 0;

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT id, seats, showtime_id
            FROM purchases
            WHERE status = 'pending' AND expires_at < ?
            FOR UPDATE
        ");
        $stmt->execute([$currentDateTime]);

        $expired_purchases = $stmt->fetchAll();

        foreach ($expired_purchases as $purchase) {
            $stmt = $pdo->prepare("UPDATE purchases SET status = 'expired' WHERE id = ?");
            $stmt->execute([$purchase['id']]);

            $seatsArray = explode(',', $purchase['seats']);
            $placeholders = implode(',', array_fill(0, count($seatsArray), '?'));

            $stmt = $pdo->prepare("DELETE FROM tickets WHERE showtime_id = ? AND seat_code IN ($placeholders)");
            $stmt->execute(array_merge([$purchase['showtime_id']], $seatsArray));

            $total_released += count($seatsArray);
        }

        $stmt = $pdo->prepare("
            SELECT DISTINCT s.id, s.show_date, s.show_time, m.duration, COUNT(t.id) as ticket_count
            FROM showtimes s
            JOIN movies m ON s.movie_id = m.id
            LEFT JOIN tickets t ON t.showtime_id = s.id
            WHERE DATE_ADD(CONCAT(s.show_date, ' ', s.show_time), INTERVAL m.duration MINUTE) < ?
            AND s.is_active = 1
            GROUP BY s.id
        ");
        $stmt->execute([$currentDateTime]);

        $expired_showtimes = $stmt->fetchAll();

        foreach ($expired_showtimes as $showtime) {
            $stmt_log = $pdo->prepare("INSERT INTO ticket_logs (showtime_id, ticket_count) VALUES (?, ?)");
            $stmt_log->execute([$showtime['id'], $showtime['ticket_count']]);

            $stmt_update = $pdo->prepare("UPDATE showtimes SET is_active = 0 WHERE id = ?");
            $stmt_update->execute([$showtime['id']]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error liberando asientos expirados: " . $e->getMessage());
    }

    return $total_released;
}

function releaseExpiredSeatsOptimized($pdo)
{
    $cacheKey = 'last_seat_release_time';
    $cacheInterval = 60;

    $lastRelease = $_SESSION[$cacheKey] ?? 0;
    $currentTime = time();

    if (($currentTime - $lastRelease) < $cacheInterval) {
        return 0;
    }

    $released = releaseExpiredSeats($pdo);

    $_SESSION[$cacheKey] = $currentTime;

    return $released;
}

$released_count = releaseExpiredSeatsOptimized($pdo);

// ============================================
// LIMPIEZA AUTOMÁTICA
// ============================================
function cleanupExpiredPurchasesPeriodic($pdo)
{
    try {
        $lastCleanupKey = 'last_cleanup_expired_purchases';

        $stmt = $pdo->prepare("SELECT value FROM site_config WHERE key_name = ?");
        $stmt->execute([$lastCleanupKey]);
        $lastCleanup = $stmt->fetch();

        $now = time();
        $fiveDaysInSeconds = 5 * 24 * 60 * 60;
        $currentHour = (int)date('H');
        $inMaintenanceWindow = ($currentHour >= 1 && $currentHour < 6);

        if (!$inMaintenanceWindow) {
            return;
        }

        $shouldCleanup = false;

        if (!$lastCleanup || empty($lastCleanup['value'])) {
            $shouldCleanup = true;
        } else {
            $lastCleanupTime = strtotime($lastCleanup['value']);

            if (($now - $lastCleanupTime) >= $fiveDaysInSeconds) {
                $shouldCleanup = true;
            }
        }

        if (!$shouldCleanup) {
            return;
        }

        $stmtDelete = $pdo->prepare("
            DELETE FROM purchases
            WHERE status = 'expired'
            AND purchase_date < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmtDelete->execute();
        $deletedPurchases = $stmtDelete->rowCount();

        $stmtOrphanTickets = $pdo->prepare("
            DELETE t FROM tickets t
            WHERE NOT EXISTS (
                SELECT 1 FROM purchases p
                WHERE p.user_id = t.user_id
                AND p.showtime_id = t.showtime_id
            )
            AND t.created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmtOrphanTickets->execute();
        $deletedOrphanTickets = $stmtOrphanTickets->rowCount();

        $stmtOrphanFood = $pdo->prepare("
            DELETE FROM food_orders
            WHERE status = 'pending'
            AND order_date < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmtOrphanFood->execute();
        $deletedOrphanFood = $stmtOrphanFood->rowCount();

        $stmtOldLogs = $pdo->prepare("
            DELETE FROM ticket_logs
            WHERE released_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
        ");
        $stmtOldLogs->execute();
        $deletedOldLogs = $stmtOldLogs->rowCount();

        if ($lastCleanup && !empty($lastCleanup['value'])) {
            $stmtUpdate = $pdo->prepare("UPDATE site_config SET value = NOW(), updated_at = NOW() WHERE key_name = ?");
        } else {
            $stmtUpdate = $pdo->prepare("INSERT INTO site_config (key_name, value) VALUES (?, NOW())");
        }

        $stmtUpdate->execute([$lastCleanupKey]);

        error_log(sprintf(
            "🧹 Limpieza automática [%s]: %d compras expiradas, %d tickets huérfanos, %d pedidos comida, %d logs antiguos eliminados",
            date('Y-m-d H:i:s'),
            $deletedPurchases,
            $deletedOrphanTickets,
            $deletedOrphanFood,
            $deletedOldLogs
        ));
    } catch (Exception $e) {
        error_log("❌ Error en limpieza automática periódica: " . $e->getMessage());
    }
}

cleanupExpiredPurchasesPeriodic($pdo);

// ============================================
// CSRF
// ============================================
function generateCSRFToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_created'] = time();
    }

    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token)
{
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_created'] = time();

    return true;
}

function isCSRFTokenExpired()
{
    if (!isset($_SESSION['csrf_token_created'])) {
        return true;
    }

    $maxAge = 3600;

    return (time() - $_SESSION['csrf_token_created']) > $maxAge;
}

// ============================================
// TOKENS DE COMPRA
// ============================================
function generatePurchaseToken()
{
    return bin2hex(random_bytes(32));
}

function generatePurchaseTokenWithTimeout($showtimeId, $timeout = 900)
{
    $token = generatePurchaseToken();

    $_SESSION['purchase_token_' . $showtimeId] = $token;
    $_SESSION['purchase_expires_at_' . $showtimeId] = time() + $timeout;

    return $token;
}

function verifyPurchaseToken($token, $showtimeId)
{
    if (empty($token) || empty($showtimeId)) {
        return false;
    }

    $expectedToken = $_SESSION['purchase_token_' . $showtimeId] ?? null;

    if (!$expectedToken || !hash_equals($expectedToken, $token)) {
        return false;
    }

    $expiresAt = $_SESSION['purchase_expires_at_' . $showtimeId] ?? 0;

    if (time() > $expiresAt) {
        unset($_SESSION['purchase_token_' . $showtimeId]);
        unset($_SESSION['purchase_expires_at_' . $showtimeId]);
        return false;
    }

    return true;
}

function isPurchaseTokenExpired($showtimeId)
{
    $expiresAt = $_SESSION['purchase_expires_at_' . $showtimeId] ?? 0;

    if ($expiresAt === 0) {
        return true;
    }

    return time() > $expiresAt;
}

function getPurchaseTokenTimeLeft($showtimeId)
{
    $expiresAt = $_SESSION['purchase_expires_at_' . $showtimeId] ?? 0;

    if ($expiresAt === 0) {
        return 0;
    }

    return max(0, $expiresAt - time());
}

function markPurchaseTokenAsUsed($showtimeId)
{
    $_SESSION['purchase_token_used_' . $showtimeId] = true;
}

function clearPurchaseSession($showtimeId)
{
    $keys = [
        'purchase_token_' . $showtimeId,
        'purchase_expires_at_' . $showtimeId,
        'purchase_token_used_' . $showtimeId,
        'ticket_quantities_' . $showtimeId,
        'total_seats_' . $showtimeId,
        'subtotal_' . $showtimeId,
        'tax_amount_' . $showtimeId,
        'total_amount_' . $showtimeId,
        'tax_rate_' . $showtimeId,
        'food_timeout_' . $showtimeId,
        'food_seats_' . $showtimeId,
        'food_valid_' . $showtimeId,
        'food_order_' . $showtimeId,
        'base_subtotal_' . $showtimeId
    ];

    foreach ($keys as $key) {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
}

function generateTransactionId()
{
    return 'TXN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -8));
}

// ============================================
// CONFLICTOS DE HORARIOS
// ============================================
function checkShowtimeConflict($pdo, $room_id, $show_date, $show_time, $duration, $exclude_id = null)
{
    $cleanup_time = 15;

    $start_minutes = strtotime($show_time) / 60;
    $end_minutes = $start_minutes + $duration;
    $end_minutes_with_cleanup = $end_minutes + $cleanup_time;

    $sql = "SELECT s.*, m.duration, m.title, r.name as room_name
            FROM showtimes s
            JOIN movies m ON s.movie_id = m.id
            JOIN rooms r ON s.room_id = r.id
            WHERE s.room_id = ?
            AND s.show_date = ?
            AND s.is_active = 1";

    if ($exclude_id) {
        $sql .= " AND s.id != ?";
    }

    $stmt = $pdo->prepare($sql);

    $params = [$room_id, $show_date];

    if ($exclude_id) {
        $params[] = $exclude_id;
    }

    $stmt->execute($params);

    $existing = $stmt->fetchAll();

    foreach ($existing as $e) {
        $existing_start = strtotime($e['show_time']) / 60;
        $existing_end = $existing_start + $e['duration'];

        $overlap = ($start_minutes < $existing_end && $end_minutes_with_cleanup > $existing_start);

        if ($overlap) {
            $overlap_start = max($start_minutes, $existing_start);
            $overlap_end = min($end_minutes_with_cleanup, $existing_end);
            $overlap_minutes = max(0, $overlap_end - $overlap_start);

            $conflict_minutes = ceil($overlap_minutes);

            $start_time_formatted = date('h:i A', strtotime($e['show_time']));
            $end_time_formatted = date('h:i A', strtotime("+{$e['duration']} minutes", strtotime($e['show_time'])));
            $room_name = $e['room_name'];

            $message = "Conflicto con: " . $e['title'] .
                " (" . $start_time_formatted . " - " . $end_time_formatted . ") - " .
                "Sala " . $room_name . " - Se superpone en " . $conflict_minutes . " minutos";

            return [
                'conflict' => true,
                'conflicting_showtime' => $e,
                'message' => $message
            ];
        }
    }

    return ['conflict' => false];
}

// ============================================
// SESIÓN EXPIRADA
// ============================================
function checkSessionExpired($showtimeId = null)
{
    $limite_inactividad = 1800;

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $limite_inactividad)) {
        $_SESSION = array();

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }

        session_destroy();

        header("Location: index.php?expired=1");
        exit();
    }

    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    if ($showtimeId !== null && $showtimeId > 0) {
        $sessionToken = $_SESSION['purchase_token_' . $showtimeId] ?? '';
        $expiresAt = $_SESSION['purchase_expires_at_' . $showtimeId] ?? 0;

        if (empty($sessionToken) || time() > $expiresAt) {
            clearPurchaseSession($showtimeId);
            header('Location: index.php?expired=1');
            exit;
        }
    }

    $_SESSION['last_activity'] = time();
}

// ============================================
// FECHAS
// ============================================
function getCurrentDate()
{
    return date('Y-m-d');
}

function getCurrentDateTime()
{
    return date('Y-m-d H:i:s');
}

function formatTimeVenezuela($time)
{
    if (empty($time)) return '';
    return date('h:i A', strtotime($time));
}

function formatDateShort($date)
{
    if (empty($date)) return '';
    return date('d/m/Y', strtotime($date));
}

function formatDateVenezuela($date)
{
    if (empty($date)) return '';

    $months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    $days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

    $timestamp = strtotime($date);

    $dayName = $days[date('w', $timestamp)];
    $day = date('d', $timestamp);
    $month = $months[date('n', $timestamp) - 1];
    $year = date('Y', $timestamp);

    return "$dayName, $day de $month de $year";
}

function getDateInSpanish($date)
{
    if (empty($date)) return '';

    $timestamp = strtotime($date);

    $days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    $months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    $dayName = $days[date('w', $timestamp)];
    $day = date('d', $timestamp);
    $month = $months[date('n', $timestamp) - 1];
    $year = date('Y', $timestamp);

    return "$dayName, $day de $month de $year";
}

function formatDuration($minutes)
{
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

function isDatePast($date)
{
    return strtotime($date) < strtotime(date('Y-m-d'));
}

function formatDateDayMonth($date)
{
    $timestamp = strtotime($date);

    $months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    $days = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

    return [
        'day' => $days[date('w', $timestamp)],
        'number' => date('d', $timestamp),
        'month' => $months[date('n', $timestamp) - 1]
    ];
}

// ============================================
// PRECIOS
// ============================================
function getShowtimePrice($showtime)
{
    $currentDay = date('N');

    if (isset($showtime['half_price_monday']) && $showtime['half_price_monday'] == 1 && $currentDay == 1) {
        return $showtime['price'] / 2;
    }

    return $showtime['price'];
}

function getTicketPrice($movie)
{
    if (!isset($movie['price'])) {
        return 0;
    }

    $currentDay = date('N');

    if (isset($movie['half_price_monday']) && $movie['half_price_monday'] == 1 && $currentDay == 1) {
        return $movie['price'] / 2;
    }

    return $movie['price'];
}

function getTicketPriceByType($showtime, $type)
{
    $prices = [
        'adult' => $showtime['price_adult'] ?? $showtime['price'] ?? 0,
        'child' => $showtime['price_child'] ?? 0,
        'senior' => $showtime['price_senior'] ?? 0
    ];

    $currentDay = date('N');
    $promotions = $showtime['promotions'] ? explode(',', $showtime['promotions']) : [];

    if (in_array('lunes_mitad', $promotions) && $currentDay == 1) {
        $prices['adult'] = $prices['adult'] / 2;
        $prices['child'] = $prices['child'] / 2;
        $prices['senior'] = $prices['senior'] / 2;
    }

    return $prices[$type] ?? $prices['adult'];
}

// ============================================
// RATE LIMITING PERSISTENTE
// ============================================

if (!defined('LOGIN_IP_MAX_ATTEMPTS')) {
    define('LOGIN_IP_MAX_ATTEMPTS', 20);
}

if (!defined('LOGIN_IP_WINDOW_MINUTES')) {
    define('LOGIN_IP_WINDOW_MINUTES', 15);
}

if (!defined('LOGIN_IP_BLOCK_MINUTES')) {
    define('LOGIN_IP_BLOCK_MINUTES', 5);
}

if (!defined('LOGIN_ACCOUNT_MAX_ATTEMPTS')) {
    define('LOGIN_ACCOUNT_MAX_ATTEMPTS', 5);
}

if (!defined('LOGIN_ACCOUNT_WINDOW_MINUTES')) {
    define('LOGIN_ACCOUNT_WINDOW_MINUTES', 15);
}

if (!defined('LOGIN_ACCOUNT_BLOCK_MINUTES')) {
    define('LOGIN_ACCOUNT_BLOCK_MINUTES', 5);
}

if (!defined('LOGIN_WARNING_FAILED_ATTEMPTS')) {
    define('LOGIN_WARNING_FAILED_ATTEMPTS', 4);
}

function getLoginIp(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function getLoginIpKey(string $ip): string
{
    return 'ip:' . $ip;
}

function getLoginAccountKey(string $email): string
{
    $normalized = strtolower(trim($email));
    return 'account:' . hash('sha256', $normalized);
}

function getRateLimitRow(PDO $pdo, string $key): ?array
{
    $stmt = $pdo->prepare('
        SELECT *
        FROM login_rate_limits
        WHERE rate_limit_key = ?
        LIMIT 1
    ');

    $stmt->execute([$key]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function checkRateLimitKey(
    PDO $pdo,
    string $key,
    int $maxAttempts,
    int $windowMinutes,
    int $blockMinutes
): array {
    $now = time();
    $row = getRateLimitRow($pdo, $key);

    if (!$row) {
        return [
            'limited' => false,
            'retry_after' => 0
        ];
    }

    if ($row['blocked_until'] !== null) {
        if ((int)$row['blocked_until'] > $now) {
            return [
                'limited' => true,
                'retry_after' => (int)$row['blocked_until'] - $now
            ];
        }

        $stmt = $pdo->prepare('
            UPDATE login_rate_limits
            SET
                attempts = 0,
                first_attempt_at = NULL,
                last_attempt_at = ?,
                blocked_until = NULL
            WHERE rate_limit_key = ?
        ');

        $stmt->execute([$now, $key]);

        return [
            'limited' => false,
            'retry_after' => 0
        ];
    }

    $windowStart = $now - ($windowMinutes * 60);

    if ((int)$row['last_attempt_at'] < $windowStart) {
        return [
            'limited' => false,
            'retry_after' => 0
        ];
    }

    if ((int)$row['attempts'] >= $maxAttempts) {
        $blockedUntil = $now + ($blockMinutes * 60);

        $stmt = $pdo->prepare('
            UPDATE login_rate_limits
            SET blocked_until = ?
            WHERE rate_limit_key = ?
        ');

        $stmt->execute([$blockedUntil, $key]);

        return [
            'limited' => true,
            'retry_after' => $blockMinutes * 60
        ];
    }

    return [
        'limited' => false,
        'retry_after' => 0
    ];
}

function recordRateLimitFailureForKey(
    PDO $pdo,
    string $key,
    int $maxAttempts,
    int $windowMinutes,
    int $blockMinutes
): void {
    $now = time();
    $row = getRateLimitRow($pdo, $key);

    if (!$row) {
        $stmt = $pdo->prepare('
            INSERT INTO login_rate_limits
                (rate_limit_key, attempts, first_attempt_at, last_attempt_at, blocked_until)
            VALUES
                (?, 1, ?, ?, NULL)
        ');

        $stmt->execute([$key, $now, $now]);

        return;
    }

    if ($row['blocked_until'] !== null) {
        if ((int)$row['blocked_until'] > $now) {
            return;
        }

        $stmt = $pdo->prepare('
            UPDATE login_rate_limits
            SET
                attempts = 1,
                first_attempt_at = ?,
                last_attempt_at = ?,
                blocked_until = NULL
            WHERE rate_limit_key = ?
        ');

        $stmt->execute([$now, $now, $key]);

        return;
    }

    $windowStart = $now - ($windowMinutes * 60);

    if ((int)$row['last_attempt_at'] < $windowStart) {
        $attempts = 1;
        $firstAttemptAt = $now;
    } else {
        $attempts = (int)$row['attempts'] + 1;
        $firstAttemptAt = (int)$row['first_attempt_at'] ?: $now;
    }

    $blockedUntil = null;

    if ($attempts >= $maxAttempts) {
        $blockedUntil = $now + ($blockMinutes * 60);
    }

    $stmt = $pdo->prepare('
        UPDATE login_rate_limits
        SET
            attempts = ?,
            first_attempt_at = ?,
            last_attempt_at = ?,
            blocked_until = ?
        WHERE rate_limit_key = ?
    ');

    $stmt->execute([
        $attempts,
        $firstAttemptAt,
        $now,
        $blockedUntil,
        $key
    ]);
}

function consumeRateLimitKey(
    PDO $pdo,
    string $key,
    int $maxAttempts,
    int $windowMinutes,
    int $blockMinutes = 0
): bool {
    $now = time();
    $row = getRateLimitRow($pdo, $key);

    if (!$row) {
        $stmt = $pdo->prepare('
            INSERT INTO login_rate_limits
                (rate_limit_key, attempts, first_attempt_at, last_attempt_at, blocked_until)
            VALUES
                (?, 1, ?, ?, NULL)
        ');

        $stmt->execute([$key, $now, $now]);

        return true;
    }

    if ($row['blocked_until'] !== null) {
        if ((int)$row['blocked_until'] > $now) {
            return false;
        }

        $stmt = $pdo->prepare('
            UPDATE login_rate_limits
            SET
                attempts = 1,
                first_attempt_at = ?,
                last_attempt_at = ?,
                blocked_until = NULL
            WHERE rate_limit_key = ?
        ');

        $stmt->execute([$now, $now, $key]);

        return true;
    }

    $windowStart = $now - ($windowMinutes * 60);

    if ((int)$row['last_attempt_at'] < $windowStart) {
        $stmt = $pdo->prepare('
            UPDATE login_rate_limits
            SET
                attempts = 1,
                first_attempt_at = ?,
                last_attempt_at = ?,
                blocked_until = NULL
            WHERE rate_limit_key = ?
        ');

        $stmt->execute([$now, $now, $key]);

        return true;
    }

    if ((int)$row['attempts'] >= $maxAttempts) {
        if ($blockMinutes > 0) {
            $blockedUntil = $now + ($blockMinutes * 60);

            $stmt = $pdo->prepare('
                UPDATE login_rate_limits
                SET blocked_until = ?
                WHERE rate_limit_key = ?
            ');

            $stmt->execute([$blockedUntil, $key]);
        }

        return false;
    }

    $attempts = (int)$row['attempts'] + 1;
    $blockedUntil = null;

    if ($blockMinutes > 0 && $attempts >= $maxAttempts) {
        $blockedUntil = $now + ($blockMinutes * 60);
    }

    $stmt = $pdo->prepare('
        UPDATE login_rate_limits
        SET
            attempts = ?,
            last_attempt_at = ?,
            blocked_until = ?
        WHERE rate_limit_key = ?
    ');

    $stmt->execute([$attempts, $now, $blockedUntil, $key]);

    return true;
}

function resetRateLimitKey(PDO $pdo, string $key): void
{
    $stmt = $pdo->prepare('
        DELETE FROM login_rate_limits
        WHERE rate_limit_key = ?
    ');

    $stmt->execute([$key]);
}

function checkLoginRateLimit(PDO $pdo, string $ip, string $email): array
{
    $ipCheck = checkRateLimitKey(
        $pdo,
        getLoginIpKey($ip),
        LOGIN_IP_MAX_ATTEMPTS,
        LOGIN_IP_WINDOW_MINUTES,
        LOGIN_IP_BLOCK_MINUTES
    );

    if ($ipCheck['limited']) {
        return $ipCheck;
    }

    return checkRateLimitKey(
        $pdo,
        getLoginAccountKey($email),
        LOGIN_ACCOUNT_MAX_ATTEMPTS,
        LOGIN_ACCOUNT_WINDOW_MINUTES,
        LOGIN_ACCOUNT_BLOCK_MINUTES
    );
}

function getLoginRateLimitStatus(PDO $pdo, string $ip, string $email): array
{
    $ipCheck = checkRateLimitKey(
        $pdo,
        getLoginIpKey($ip),
        LOGIN_IP_MAX_ATTEMPTS,
        LOGIN_IP_WINDOW_MINUTES,
        LOGIN_IP_BLOCK_MINUTES
    );

    if ($ipCheck['limited']) {
        return [
            'limited' => true,
            'retry_after' => $ipCheck['retry_after'],
            'attempts' => null,
            'attempts_left' => 0,
            'warning' => false,
            'reason' => 'ip'
        ];
    }

    $accountKey = getLoginAccountKey($email);

    $accountCheck = checkRateLimitKey(
        $pdo,
        $accountKey,
        LOGIN_ACCOUNT_MAX_ATTEMPTS,
        LOGIN_ACCOUNT_WINDOW_MINUTES,
        LOGIN_ACCOUNT_BLOCK_MINUTES
    );

    if ($accountCheck['limited']) {
        return [
            'limited' => true,
            'retry_after' => $accountCheck['retry_after'],
            'attempts' => LOGIN_ACCOUNT_MAX_ATTEMPTS,
            'attempts_left' => 0,
            'warning' => false,
            'reason' => 'account'
        ];
    }

    $row = getRateLimitRow($pdo, $accountKey);
    $attempts = 0;

    if ($row) {
        $windowStart = time() - (LOGIN_ACCOUNT_WINDOW_MINUTES * 60);

        if ((int)$row['last_attempt_at'] >= $windowStart) {
            $attempts = (int)$row['attempts'];
        }
    }

    if ($attempts >= LOGIN_ACCOUNT_MAX_ATTEMPTS) {
        $blockedUntil = time() + (LOGIN_ACCOUNT_BLOCK_MINUTES * 60);

        $stmt = $pdo->prepare('
            UPDATE login_rate_limits
            SET blocked_until = ?
            WHERE rate_limit_key = ?
        ');

        $stmt->execute([$blockedUntil, $accountKey]);

        return [
            'limited' => true,
            'retry_after' => LOGIN_ACCOUNT_BLOCK_MINUTES * 60,
            'attempts' => $attempts,
            'attempts_left' => 0,
            'warning' => false,
            'reason' => 'account'
        ];
    }

    $attemptsLeft = max(0, LOGIN_ACCOUNT_MAX_ATTEMPTS - $attempts);

    $warningThreshold = defined('LOGIN_WARNING_FAILED_ATTEMPTS')
        ? LOGIN_WARNING_FAILED_ATTEMPTS
        : max(1, LOGIN_ACCOUNT_MAX_ATTEMPTS - 1);

    $warning = ($attempts >= $warningThreshold && $attempts < LOGIN_ACCOUNT_MAX_ATTEMPTS);

    return [
        'limited' => false,
        'retry_after' => 0,
        'attempts' => $attempts,
        'attempts_left' => $attemptsLeft,
        'warning' => $warning,
        'reason' => null
    ];
}

function recordFailedLogin(PDO $pdo, string $ip, string $email): void
{
    recordRateLimitFailureForKey(
        $pdo,
        getLoginIpKey($ip),
        LOGIN_IP_MAX_ATTEMPTS,
        LOGIN_IP_WINDOW_MINUTES,
        LOGIN_IP_BLOCK_MINUTES
    );

    recordRateLimitFailureForKey(
        $pdo,
        getLoginAccountKey($email),
        LOGIN_ACCOUNT_MAX_ATTEMPTS,
        LOGIN_ACCOUNT_WINDOW_MINUTES,
        LOGIN_ACCOUNT_BLOCK_MINUTES
    );
}

function resetLoginRateLimit(PDO $pdo, string $ip, string $email): void
{
    resetRateLimitKey(
        $pdo,
        getLoginAccountKey($email)
    );
}

function cleanupLoginRateLimits(PDO $pdo, int $olderThanSeconds = 86400): int
{
    $stmt = $pdo->prepare('
        DELETE FROM login_rate_limits
        WHERE last_attempt_at < ?
    ');

    $stmt->execute([time() - $olderThanSeconds]);

    return $stmt->rowCount();
}

function canGenerateToken($showtimeId)
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    $key = 'token_generations_' . $_SESSION['user_id'] . '_' . $showtimeId;
    $now = time();

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'window_start' => $now];
    }

    if ($now - $_SESSION[$key]['window_start'] > 300) {
        $_SESSION[$key] = ['count' => 0, 'window_start' => $now];
    }

    if ($_SESSION[$key]['count'] >= 10) {
        return false;
    }

    $_SESSION[$key]['count']++;

    return true;
}

function checkRateLimit($action, $maxAttempts = 10, $windowMinutes = 5)
{
    global $pdo;

    $key = 'action:' . $action . ':ip:' . getLoginIp();

    return consumeRateLimitKey(
        $pdo,
        $key,
        $maxAttempts,
        $windowMinutes,
        0
    );
}

// ============================================
// VALIDAR Y RECALCULAR PRECIOS
// ============================================
function validateAndRecalculatePrices($pdo, $showtimeId, $ticketsData)
{
    $stmt = $pdo->prepare("
        SELECT s.*, m.duration
        FROM showtimes s
        JOIN movies m ON s.movie_id = m.id
        WHERE s.id = ? AND s.is_active = 1
    ");

    $stmt->execute([$showtimeId]);
    $showtime = $stmt->fetch();

    if (!$showtime) {
        return ['error' => 'Función no encontrada o inactiva'];
    }

    $stmt = $pdo->query("SELECT tax_rate FROM tax_config WHERE is_active = 1 LIMIT 1");
    $tax = $stmt->fetch();
    $taxRate = $tax ? floatval($tax['tax_rate']) : 16;

    $priceAdult = floatval($showtime['price_adult'] ?? $showtime['price'] ?? 0);
    $priceChild = floatval($showtime['price_child'] ?? 0);
    $priceSenior = floatval($showtime['price_senior'] ?? 0);

    $currentDay = date('N');
    $promotions = $showtime['promotions'] ? explode(',', $showtime['promotions']) : [];

    if (in_array('lunes_mitad', $promotions) && $currentDay == 1) {
        $priceAdult = $priceAdult / 2;
        $priceChild = $priceChild / 2;
        $priceSenior = $priceSenior / 2;
    }

    $validTypes = ['adult', 'child', 'senior'];
    $totalSeats = 0;
    $subtotal = 0;

    foreach ($validTypes as $type) {
        $count = intval($ticketsData[$type] ?? 0);

        if ($count < 0) {
            $count = 0;
        }

        $totalSeats += $count;

        switch ($type) {
            case 'adult':
                $subtotal += $count * $priceAdult;
                break;
            case 'child':
                $subtotal += $count * $priceChild;
                break;
            case 'senior':
                $subtotal += $count * $priceSenior;
                break;
        }
    }

    if ($totalSeats <= 0) {
        return ['error' => 'Debes seleccionar al menos un boleto'];
    }

    $stmt = $pdo->prepare("
        SELECT r.capacity, r.seat_layout
        FROM showtimes s
        JOIN rooms r ON s.room_id = r.id
        WHERE s.id = ?
    ");

    $stmt->execute([$showtimeId]);
    $room = $stmt->fetch();

    if (!$room) {
        return ['error' => 'Sala no encontrada'];
    }

    $currentUserId = $_SESSION['user_id'] ?? 0;

    $stmt = $pdo->prepare("
        SELECT DISTINCT t.seat_code
        FROM tickets t
        WHERE t.showtime_id = ?
          AND NOT (t.user_id = ? AND t.status = 'hold')
          AND (
              t.status = 'confirmed'
              OR (
                  t.status = 'hold'
                  AND EXISTS (
                      SELECT 1
                      FROM purchases p
                      WHERE p.user_id = t.user_id
                        AND p.showtime_id = t.showtime_id
                        AND p.status = 'pending'
                        AND p.expires_at > NOW()
                  )
              )
          )
    ");

    $stmt->execute([$showtimeId, $currentUserId]);

    $occupiedSeats = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $layout = json_decode($room['seat_layout'], true);
    $blockedSeats = $layout['blockedSeats'] ?? [];

    $totalAvailable = ($layout['totalSeats'] ?? 0) - count($blockedSeats) - count($occupiedSeats);

    if ($totalAvailable < $totalSeats) {
        return ['error' => 'No hay suficientes asientos disponibles'];
    }

    $taxAmount = $subtotal * ($taxRate / 100);
    $totalAmount = $subtotal + $taxAmount;

    return [
        'success' => true,
        'subtotal' => $subtotal,
        'tax_rate' => $taxRate,
        'tax_amount' => $taxAmount,
        'total_amount' => $totalAmount,
        'total_seats' => $totalSeats,
        'prices' => [
            'adult' => $priceAdult,
            'child' => $priceChild,
            'senior' => $priceSenior
        ],
        'available_seats' => $totalAvailable
    ];
}
