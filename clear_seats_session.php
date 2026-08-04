<?php
require_once 'config.php';

// Verificar que sea una petición POST o GET
$showtimeId = isset($_POST['showtime_id']) ? intval($_POST['showtime_id']) : 
              (isset($_GET['showtime_id']) ? intval($_GET['showtime_id']) : 0);

if ($showtimeId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid showtime_id']);
    exit;
}

// Limpiar todas las sesiones relacionadas con este showtime
$sessionKeys = [
    'food_timeout_' . $showtimeId,
    'food_seats_' . $showtimeId,
    'food_valid_' . $showtimeId
];

foreach ($sessionKeys as $key) {
    if (isset($_SESSION[$key])) {
        unset($_SESSION[$key]);
    }
}

// Limpiar también en la base de datos - liberar asientos expirados
$stmt = $pdo->prepare("
    SELECT id, seats, session_token 
    FROM purchases 
    WHERE showtime_id = ? AND status = 'pending' AND expires_at < NOW()
");
$stmt->execute([$showtimeId]);
$expired = $stmt->fetchAll();

$released = 0;
foreach ($expired as $purchase) {
    // Marcar como expirado
    $stmt = $pdo->prepare("UPDATE purchases SET status = 'expired' WHERE id = ?");
    $stmt->execute([$purchase['id']]);
    
    // Eliminar tickets asociados
    $seatsArray = explode(',', $purchase['seats']);
    $placeholders = implode(',', array_fill(0, count($seatsArray), '?'));
    $stmt = $pdo->prepare("DELETE FROM tickets WHERE showtime_id = ? AND seat_code IN ($placeholders)");
    $stmt->execute(array_merge([$showtimeId], $seatsArray));
    $released++;
}

// También limpiar cualquier sesión de timeout en la base de datos
$stmt = $pdo->prepare("
    UPDATE purchases 
    SET status = 'expired' 
    WHERE showtime_id = ? AND status = 'pending' AND expires_at < NOW()
");
$stmt->execute([$showtimeId]);

// Enviar respuesta
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'released' => $released]);
    exit;
}

// Si es GET normal, redirigir a index
header('Location: index.php');
exit;
?>