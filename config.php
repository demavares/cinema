<?php
// ============================================
// CONFIGURACIÓN DE SESIÓN
// ============================================
date_default_timezone_set('America/Caracas');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 0);
    ini_set('session.gc_maxlifetime', 3600);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['last_activity'] = time();

// ============================================
// CONFIGURACIÓN DE BASE DE DATOS
// ============================================
define('DB_HOST', 'datame');
define('DB_USER', 'root');
define('DB_PASS', '123456');
define('DB_NAME', 'cinema_db');
define('TMDB_API_KEY', 'ddfdd934489b749f7d132c356a3d687a');
define('TMDB_API_URL', 'https://api.themoviedb.org/3/');
define('TMDB_TIMEOUT', 5);

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET time_zone = '-04:00'");
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// ============================================
// FUNCIÓN PARA OBTENER CONFIGURACIÓN DEL SITIO
// ============================================
function getSiteConfig($pdo) {
    static $config = null;
    if ($config !== null) {
        return $config;
    }
    try {
        $stmt = $pdo->query("SELECT key_name, value FROM site_config");
        $rows = $stmt->fetchAll();
        $config = [];
        foreach ($rows as $row) {
            $config[$row['key_name']] = $row['value'];
        }
        $defaults = [
            'site_name' => 'Cinema Pro',
            'site_logo' => '',
            'footer_logo' => '',
            'site_favicon' => '',
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
        foreach ($defaults as $key => $default_value) {
            if (!isset($config[$key])) {
                $config[$key] = $default_value;
            }
        }
        return $config;
    } catch (PDOException $e) {
        return $defaults;
    }
}

// ============================================
// FUNCIÓN PARA FORMATEAR MONEDA
// ============================================
function formatCurrency($amount, $config = null) {
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
// FUNCIÓN PARA OBTENER PLACEHOLDER DE IMAGEN
// ============================================
function getPlaceholderImage($width = 300, $height = 450, $text = '🎬') {
    return 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22' . $width . '%22 height=%22' . $height . '%22 viewBox=%220 0 ' . $width . ' ' . $height . '%22%3E%3Crect fill=%22%231a1a2e%22 width=%22' . $width . '%22 height=%22' . $height . '%22/%3E%3Ctext x=%22' . ($width/2) . '%22 y=%22' . ($height/2 + 10) . '%22 text-anchor=%22middle%22 fill=%22%236b7280%22 font-size=%22' . ($width/6) . '%22 font-family=%22Arial%22%3E' . $text . '%3C/text%3E%3C/svg%3E';
}

// ============================================
// FUNCIÓN PARA OBTENER DATOS DE TMDb (SOLO DATOS, NO IMÁGENES)
// ============================================
function getMovieFromTMDB($title, $year = null) {
    $api_key = TMDB_API_KEY;
    $query = urlencode($title);
    $url = TMDB_API_URL . "search/movie?api_key={$api_key}&query={$query}&language=es-ES";
    if ($year) {
        $url .= "&year={$year}";
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, TMDB_TIMEOUT);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    if (!empty($data['results'])) {
        $movie_data = $data['results'][0];
        $tmdb_id = $movie_data['id'];
        $detail_url = TMDB_API_URL . "movie/{$tmdb_id}?api_key={$api_key}&language=es-ES&append_to_response=credits";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $detail_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, TMDB_TIMEOUT);
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
// FUNCIÓN PARA LIBERAR ASIENTOS AUTOMÁTICAMENTE
// ============================================
function releaseExpiredSeats($pdo) {
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
            JOIN tickets t ON t.showtime_id = s.id
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

$released_count = releaseExpiredSeats($pdo);

// ============================================
// FUNCIONES CSRF
// ============================================
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_created'] = time();
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    return true;
}

// ============================================
// FUNCIONES DE TOKEN DE COMPRA
// ============================================
function generatePurchaseToken() {
    return bin2hex(random_bytes(32));
}

function generatePurchaseTokenWithTimeout($showtimeId, $timeout = 900) {
    $token = generatePurchaseToken();
    $_SESSION['purchase_token_' . $showtimeId] = $token;
    $_SESSION['purchase_expires_at_' . $showtimeId] = time() + $timeout;
    return $token;
}

function verifyPurchaseToken($token, $showtimeId) {
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

function verifyPurchaseTokenWithTimeout($token, $showtimeId) {
    if (empty($token) || empty($showtimeId)) {
        return false;
    }
    $expectedToken = $_SESSION['purchase_token_' . $showtimeId] ?? null;
    if (!$expectedToken || !hash_equals($expectedToken, $token)) {
        return false;
    }
    $usedKey = 'purchase_token_used_' . $showtimeId;
    if (isset($_SESSION[$usedKey]) && $_SESSION[$usedKey] === true) {
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

function isPurchaseTokenExpired($showtimeId) {
    $expiresAt = $_SESSION['purchase_expires_at_' . $showtimeId] ?? 0;
    if ($expiresAt === 0) {
        return true;
    }
    return time() > $expiresAt;
}

function getPurchaseTokenTimeLeft($showtimeId) {
    $expiresAt = $_SESSION['purchase_expires_at_' . $showtimeId] ?? 0;
    if ($expiresAt === 0) return 0;
    return max(0, $expiresAt - time());
}

function markPurchaseTokenAsUsed($showtimeId) {
    $_SESSION['purchase_token_used_' . $showtimeId] = true;
}

function clearPurchaseSession($showtimeId) {
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

function generateTransactionId() {
    return 'TXN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -8));
}

// ============================================
// VERIFICAR SESIÓN EXPIRADA PARA PÁGINAS DEL FLUJO
// ============================================
function checkSessionExpired($showtimeId = null) {
    $limite_inactividad = 1800; // 30 minutos
    
    // Verificar inactividad general
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $limite_inactividad)) {
        // 1. Vaciar array de sesión
        $_SESSION = array();
        
        // 2. Eliminar cookie de sesión en el cliente
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        // 3. Destruir la sesión actual
        session_destroy();
        
        // 4. Redirigir enviando la alerta solo mediante GET
        header("Location: index.php?expired=1");
        exit();
    }
    
    // Si no hay usuario logueado
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
    
    // Verificar token específico del showtime
    if ($showtimeId !== null && $showtimeId > 0) {
        $sessionToken = $_SESSION['purchase_token_' . $showtimeId] ?? '';
        $expiresAt = $_SESSION['purchase_expires_at_' . $showtimeId] ?? 0;
        
        if (empty($sessionToken) || time() > $expiresAt) {
            clearPurchaseSession($showtimeId);
            header('Location: index.php?expired=1');
            exit;
        }
    }
    
    // Actualizar timestamp si la sesión sigue activa
    $_SESSION['last_activity'] = time();
}

// ============================================
// FUNCIONES DE FECHA
// ============================================
function getCurrentDate() {
    return date('Y-m-d');
}

function getCurrentDateTime() {
    return date('Y-m-d H:i:s');
}

function formatTimeVenezuela($time) {
    if (empty($time)) return '';
    return date('h:i A', strtotime($time));
}

function formatDateShort($date) {
    if (empty($date)) return '';
    return date('d/m/Y', strtotime($date));
}

function formatDateVenezuela($date) {
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

function getDateInSpanish($date) {
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

// ============================================
// FUNCIONES DE PRECIOS
// ============================================
function getShowtimePrice($showtime) {
    $currentDay = date('N');
    if (isset($showtime['half_price_monday']) && $showtime['half_price_monday'] == 1 && $currentDay == 1) {
        return $showtime['price'] / 2;
    }
    return $showtime['price'];
}

function getTicketPrice($movie) {
    if (!isset($movie['price'])) {
        return 0;
    }
    $currentDay = date('N');
    if (isset($movie['half_price_monday']) && $movie['half_price_monday'] == 1 && $currentDay == 1) {
        return $movie['price'] / 2;
    }
    return $movie['price'];
}

function getTicketPriceByType($showtime, $type) {
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
// RATE LIMITING
// ============================================
function canGenerateToken($showtimeId) {
    if (!isset($_SESSION['user_id'])) return false;
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

function checkRateLimit($action, $maxAttempts = 10, $windowMinutes = 5) {
    $key = 'rate_limit_' . $action . '_' . $_SERVER['REMOTE_ADDR'];
    $now = time();
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'window_start' => $now];
    }
    if ($now - $_SESSION[$key]['window_start'] > ($windowMinutes * 60)) {
        $_SESSION[$key] = ['count' => 0, 'window_start' => $now];
    }
    if ($_SESSION[$key]['count'] >= $maxAttempts) {
        return false;
    }
    $_SESSION[$key]['count']++;
    return true;
}

// ============================================
// VALIDAR Y RECALCULAR PRECIOS
// ============================================
function validateAndRecalculatePrices($pdo, $showtimeId, $ticketsData) {
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
        if ($count < 0) $count = 0;
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
    
    $stmt = $pdo->prepare("SELECT seat_code FROM tickets WHERE showtime_id = ?");
    $stmt->execute([$showtimeId]);
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
?>