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
    $adminScripts = [];

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
define('TMDB_TIMEOUT', 10);

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
// 🕐 ZONA HORARIA DEL SITIO (configurable desde el panel)
// ============================================
// Se lee de site_config ('timezone'). Si no existe o es inválida,
// se usa 'America/Caracas' como valor por defecto.
$siteTimezone = 'America/Caracas';
try {
    $stmtTz = $pdo->query("SELECT value FROM site_config WHERE key_name = 'timezone' LIMIT 1");
    $configuredTz = $stmtTz ? $stmtTz->fetchColumn() : '';
    if ($configuredTz && in_array($configuredTz, DateTimeZone::listIdentifiers(), true)) {
        $siteTimezone = $configuredTz;
    }
} catch (Exception $e) {
    // Tabla aún no disponible (instalación nueva): se mantiene el valor por defecto.
}
date_default_timezone_set($siteTimezone);
// Sincronizar también la sesión de MySQL para que NOW()/DATE_ADD() usen la misma zona
try {
    $tzOffset = (new DateTimeZone($siteTimezone))->getOffset(new DateTime('now', new DateTimeZone('UTC')));
    $tzSign = $tzOffset < 0 ? '-' : '+';
    $tzAbs = abs($tzOffset);
    $tzOffsetStr = $tzSign
        . str_pad((string)intdiv($tzAbs, 3600), 2, '0', STR_PAD_LEFT)
        . ':' . str_pad((string)intdiv($tzAbs % 3600, 60), 2, '0', STR_PAD_LEFT);
    $pdo->exec("SET time_zone = '" . $tzOffsetStr . "'");
} catch (Exception $e) {
    error_log("⚠️ No se pudo sincronizar time_zone de MySQL: " . $e->getMessage());
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

// ============================================
// HELPERS DESACOPLADOS (app/Helpers)
// ============================================
require_once __DIR__ . '/app/Helpers/site.php';
require_once __DIR__ . '/app/Helpers/formats.php';
require_once __DIR__ . '/app/Helpers/tmdb.php';
require_once __DIR__ . '/app/Helpers/pricing.php';
require_once __DIR__ . '/app/Helpers/security.php';
require_once __DIR__ . '/app/Helpers/maintenance.php';

// ============================================
// MANTENIMIENTO AUTOMÁTICO (por petición)
// ============================================
$released_count = releaseExpiredSeatsOptimized($pdo);
cleanupExpiredPurchasesPeriodic($pdo);
autoHideMoviesWithoutActiveShows($pdo);