<?php
require_once 'config.php';

// Verificar autenticación
if (!isset($_SESSION['user_id']) || !isset($_GET['showtime_id'])) {
    http_response_code(403);
    exit;
}

$showtimeId = intval($_GET['showtime_id']);
if ($showtimeId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid showtime_id']);
    exit;
}

// Obtener asientos ocupados para este showtime
$stmt = $pdo->prepare("SELECT seat_code FROM tickets WHERE showtime_id = ?");
$stmt->execute([$showtimeId]);
$occupied = $stmt->fetchAll(PDO::FETCH_COLUMN);

header('Content-Type: application/json');
echo json_encode(['occupied' => $occupied]);
exit;
?>