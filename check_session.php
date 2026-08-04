<?php
require_once 'config.php';

header('Content-Type: application/json');

// Verificar que el usuario tenga sesión
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['valid' => false, 'reason' => 'no_session']);
    exit;
}

$showtimeId = isset($_GET['showtime_id']) ? intval($_GET['showtime_id']) : 0;

if ($showtimeId <= 0) {
    echo json_encode(['valid' => false, 'reason' => 'invalid_showtime']);
    exit;
}

// Usar las mismas claves que en create_food_session.php
$sessionValidKey = 'food_valid_' . $showtimeId;
$sessionKey = 'food_timeout_' . $showtimeId;
$sessionSeatsKey = 'food_seats_' . $showtimeId;
$sessionCreatedKey = 'food_created_' . $showtimeId;

// Verificar si la sesión de comida es válida para este showtime_id
if (!isset($_SESSION[$sessionValidKey]) || $_SESSION[$sessionValidKey] !== true) {
    echo json_encode(['valid' => false, 'reason' => 'invalid_food_session']);
    exit;
}

// Verificar si el timeout expiró
if (isset($_SESSION[$sessionKey]) && $_SESSION[$sessionKey] <= 0) {
    echo json_encode(['valid' => false, 'reason' => 'timeout_expired']);
    exit;
}

// Calcular tiempo restante basado en el timestamp de creación
$maxTimeout = 600; // 10 minutos
if (isset($_SESSION[$sessionCreatedKey])) {
    $elapsed = time() - $_SESSION[$sessionCreatedKey];
    $timeLeft = max(0, $maxTimeout - $elapsed);
    $_SESSION[$sessionKey] = $timeLeft;
} else {
    // Fallback: usar el valor guardado en sesión
    $timeLeft = isset($_SESSION[$sessionKey]) ? intval($_SESSION[$sessionKey]) : 600;
}

if ($timeLeft <= 0) {
    echo json_encode(['valid' => false, 'reason' => 'timeout_expired']);
    exit;
}

// Devolver el tiempo restante y los asientos
echo json_encode([
    'valid' => true,
    'timeLeft' => $timeLeft,
    'seats' => $_SESSION[$sessionSeatsKey] ?? ''
]);
exit;
?>