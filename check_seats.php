<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'No autenticado', 'occupied' => []]);
    exit;
}

$showtimeId = isset($_GET['showtime_id']) ? intval($_GET['showtime_id']) : 0;
if ($showtimeId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'showtime_id requerido', 'occupied' => []]);
    exit;
}

try {
    // Obtener asientos ocupados (completados + pendientes de otros)
    $stmtCompleted = $pdo->prepare("
        SELECT DISTINCT t.seat_code 
        FROM tickets t
        INNER JOIN purchases p ON t.user_id = p.user_id AND t.showtime_id = p.showtime_id
        WHERE t.showtime_id = ? AND p.status = 'completed'
    ");
    $stmtCompleted->execute([$showtimeId]);
    $occupiedSeats = $stmtCompleted->fetchAll(PDO::FETCH_COLUMN);
    
    $stmtPending = $pdo->prepare("
        SELECT seats FROM purchases 
        WHERE showtime_id = ? AND status = 'pending' AND user_id != ? AND expires_at > NOW()
    ");
    $stmtPending->execute([$showtimeId, $_SESSION['user_id']]);
    $pendingPurchases = $stmtPending->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($pendingPurchases as $seatsString) {
        if (!empty($seatsString)) {
            $seatsArray = array_map('trim', explode(',', $seatsString));
            $occupiedSeats = array_merge($occupiedSeats, $seatsArray);
        }
    }
    $occupiedSeats = array_unique($occupiedSeats);
    
    echo json_encode([
        'success' => true,
        'occupied' => $occupiedSeats,
        'count' => count($occupiedSeats),
        'timestamp' => time()
    ]);
    exit;
    
} catch (PDOException $e) {
    error_log("check_seats: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno', 'occupied' => []]);
    exit;
}
?>