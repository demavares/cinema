<?php
// Establecer zona horaria de Venezuela (UTC-4)
date_default_timezone_set('America/Caracas');

// ============================================
// CONFIGURACIÓN DE SESIÓN - SESIÓN EXPIRA AL CERRAR NAVEGADOR
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    // Configurar cookie de sesión para que expire al cerrar el navegador
    ini_set('session.cookie_lifetime', 0);
    ini_set('session.gc_maxlifetime', 3600);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    
    session_start();
}

// Verificar si la sesión debe ser destruida por timeout de inactividad
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['last_activity'] = time();

// Configuración de la Base de Datos
define('DB_HOST', 'datame');
define('DB_USER', 'root');
define('DB_PASS', '123456');
define('DB_NAME', 'cinema_db');

// Configuración de la API de TMDB
define('TMDB_API_KEY', 'ddfdd934489b749f7d132c356a3d687a');
define('TMDB_API_URL', 'https://api.themoviedb.org/3/');

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
        return [
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
    
    if ($position === 'right') {
        return $formatted . ' ' . $symbol;
    } else {
        return $symbol . $formatted;
    }
}

// ============================================
// FUNCIÓN PARA OBTENER DATOS DE TMDb
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
// FUNCIÓN PARA LIBERAR ASIENTOS AUTOMÁTICAMENTE
// ============================================
function releaseExpiredSeats($pdo) {
    $currentDateTime = date('Y-m-d H:i:s');
    
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
    
    $total_released = 0;
    
    foreach ($expired_showtimes as $showtime) {
        $ticket_count = $showtime['ticket_count'];
        
        if ($ticket_count > 0) {
            $stmt_log = $pdo->prepare("INSERT INTO ticket_logs (showtime_id, ticket_count) VALUES (?, ?)");
            $stmt_log->execute([$showtime['id'], $ticket_count]);
            $total_released += $ticket_count;
        }
        
        $stmt_delete = $pdo->prepare("DELETE FROM tickets WHERE showtime_id = ?");
        $stmt_delete->execute([$showtime['id']]);
        
        $stmt_update = $pdo->prepare("UPDATE showtimes SET is_active = 0 WHERE id = ?");
        $stmt_update->execute([$showtime['id']]);
    }
    
    return $total_released;
}

// Ejecutar liberación automática al cargar cualquier página
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
// ✅ GENERAR TOKEN DE COMPRA ÚNICO CON TIMEOUT
// ============================================
function generatePurchaseToken() {
    return bin2hex(random_bytes(32));
}

function generatePurchaseTokenWithTimeout($showtimeId, $timeout = 900) {
    $token = generatePurchaseToken();
    $_SESSION['purchase_token_' . $showtimeId] = $token;
    $_SESSION['purchase_expires_at_' . $showtimeId] = time() + $timeout; // 15 minutos por defecto
    $_SESSION['purchase_created_at_' . $showtimeId] = time();
    return $token;
}

function verifyPurchaseToken($token, $showtimeId) {
    if (empty($token) || empty($showtimeId)) return false;
    
    $expectedToken = $_SESSION['purchase_token_' . $showtimeId] ?? null;
    if (!$expectedToken || !hash_equals($expectedToken, $token)) {
        return false;
    }
    
    $usedKey = 'purchase_token_used_' . $showtimeId;
    if (isset($_SESSION[$usedKey]) && $_SESSION[$usedKey] === true) {
        return false;
    }
    
    return true;
}

function verifyPurchaseTokenWithTimeout($token, $showtimeId) {
    if (empty($token) || empty($showtimeId)) return false;
    
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

function markPurchaseTokenAsUsed($showtimeId) {
    $_SESSION['purchase_token_used_' . $showtimeId] = true;
}

function isPurchaseTokenExpired($showtimeId) {
    $expiresAt = $_SESSION['purchase_expires_at_' . $showtimeId] ?? 0;
    return time() > $expiresAt;
}

function getPurchaseTokenTimeLeft($showtimeId) {
    $expiresAt = $_SESSION['purchase_expires_at_' . $showtimeId] ?? 0;
    if ($expiresAt === 0) return 0;
    return max(0, $expiresAt - time());
}

function clearPurchaseSession($showtimeId) {
    $keys = [
        'purchase_token_' . $showtimeId,
        'purchase_expires_at_' . $showtimeId,
        'purchase_token_used_' . $showtimeId,
        'purchase_created_at_' . $showtimeId,
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
        'payment_method_' . $showtimeId,
        'pending_checkout'
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
// FUNCIÓN PARA VALIDAR CONFLICTOS DE HORARIOS
// ============================================
function checkShowtimeConflict($pdo, $room_id, $show_date, $show_time, $duration, $exclude_id = null) {
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
        
        $overlap_start = max($start_minutes, $existing_start);
        $overlap_end = min($end_minutes_with_cleanup, $existing_end);
        $overlap_minutes = max(0, $overlap_end - $overlap_start);
        
        $overlap = ($start_minutes < $existing_end && $end_minutes_with_cleanup > $existing_start);
        
        if ($overlap) {
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
// FUNCIONES DE FORMATO
// ============================================
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
    
    $months = [
        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
    ];
    $days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    
    $timestamp = strtotime($date);
    $dayName = $days[date('w', $timestamp)];
    $day = date('d', $timestamp);
    $month = $months[date('n', $timestamp) - 1];
    $year = date('Y', $timestamp);
    
    return "$dayName, $day de $month de $year";
}

function getCurrentDate() {
    return date('Y-m-d');
}

function getCurrentDateTime() {
    return date('Y-m-d H:i:s');
}

function getAgeRating($genre) {
    $ratings = [
        'Animación' => 'A (Todo público)',
        'Comedia' => 'A (Todo público)',
        'Aventura' => 'A (Todo público)',
        'Familiar' => 'A (Todo público)',
        'Ciencia Ficción' => 'B (Mayores de 12)',
        'Acción' => 'B (Mayores de 12)',
        'Drama' => 'B (Mayores de 12)',
        'Fantasía' => 'B (Mayores de 12)',
        'Misterio' => 'B (Mayores de 12)',
        'Romance' => 'B (Mayores de 12)',
        'Terror' => 'C (Mayores de 18)'
    ];
    
    return $ratings[$genre] ?? 'B (Mayores de 12)';
}

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
// LIMPIAR SESIONES EXPIRADAS
// ============================================
function cleanExpiredSessions($pdo) {
    $stmt = $pdo->prepare("
        SELECT id, seats, showtime_id 
        FROM purchases 
        WHERE status = 'pending' AND expires_at < NOW()
    ");
    $stmt->execute();
    $expired = $stmt->fetchAll();
    
    $total = 0;
    foreach ($expired as $purchase) {
        $stmt = $pdo->prepare("UPDATE purchases SET status = 'expired' WHERE id = ?");
        $stmt->execute([$purchase['id']]);
        
        $seatsArray = explode(',', $purchase['seats']);
        $placeholders = implode(',', array_fill(0, count($seatsArray), '?'));
        $stmt = $pdo->prepare("DELETE FROM tickets WHERE showtime_id = ? AND seat_code IN ($placeholders)");
        $stmt->execute(array_merge([$purchase['showtime_id']], $seatsArray));
        $total++;
    }
    
    return $total;
}

$cleaned = cleanExpiredSessions($pdo);

// ============================================
// OBTENER CONFIGURACIÓN DEL SITIO (GLOBAL)
// ============================================
$siteConfig = getSiteConfig($pdo);

// ============================================
// FUNCIÓN PARA DESTRUIR SESIÓN COMPLETAMENTE
// ============================================
function destroySession() {
    $_SESSION = [];
    
    if (ini_get("session_use_cookies")) {
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
    
    session_destroy();
}

// ============================================
// ✅ VALIDAR Y RECALCULAR PRECIOS EN EL SERVIDOR
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