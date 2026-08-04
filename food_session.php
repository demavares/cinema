<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Usuario no autenticado']);
    exit;
}

$showtimeId = isset($_POST['showtime_id']) ? intval($_POST['showtime_id']) : 0;
$seatsRaw = isset($_POST['seats']) ? trim($_POST['seats']) : '';

if ($showtimeId <= 0 || empty($seatsRaw)) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos de función o asientos inválidos']);
    exit;
}

// Normalizar formato de asientos (ordenar alfabéticamente)
$seatsArray = array_map('trim', explode(',', $seatsRaw));
sort($seatsArray);
$seats = implode(',', $seatsArray);

// Guardar datos en la sesión PHP
$_SESSION['checkout'] = [
    'showtime_id' => $showtimeId,
    'seats' => $seats,
    'seats_count' => count($seatsArray),
    'expire_time' => time() + 600 // 10 minutos de validez
];

// Sincronizar reserva temporal en base de datos
try {
    $stmt = $pdo->prepare("SELECT id FROM purchases WHERE showtime_id = ? AND user_id = ? AND status = 'pending'");
    $stmt->execute([$showtimeId, $_SESSION['user_id']]);
    $existing = $stmt->fetch();
    
    $token = bin2hex(random_bytes(32));
    
    if ($existing) {
        $stmt = $pdo->prepare("
            UPDATE purchases 
            SET seats = ?, total_tickets = ?, expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE), session_token = ?
            WHERE id = ?
        ");
        $stmt->execute([$seats, count($seatsArray), $token, $existing['id']]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO purchases (user_id, showtime_id, seats, total_tickets, total_food, total_amount, session_token, expires_at, status) 
            VALUES (?, ?, ?, ?, 0.00, 0.00, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 'pending')
        ");
        $stmt->execute([$_SESSION['user_id'], $showtimeId, $seats, count($seatsArray), $token]);
    }
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Error en food_session.php: " . $e->getMessage());
    echo json_encode(['error' => 'Error de servidor al reservar temporalmente.']);
}
exit;
?>