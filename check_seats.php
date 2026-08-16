<?php
// ============================================
// check_seats.php - Verificar asientos ocupados en tiempo real
// ============================================
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
    // ============================================
    // UNIFICADO: Consulta de asientos ocupados (solo lectura).
    // Un asiento está ocupado si:
    //   - Está confirmado (pagado), O
    //   - Es una reserva 'hold' con una compra pending válida (no expirada).
    // Esto excluye automáticamente los "zombies" (holds sin compra vigente)
    // sin necesidad de borrarlos aquí (la limpieza la hacen seats.php y el cron).
    // ============================================
    $stmt = $pdo->prepare("
        SELECT DISTINCT t.seat_code
        FROM tickets t
        WHERE t.showtime_id = ?
          AND t.user_id != ?
          AND t.status IN ('hold', 'confirmed')
          AND (
                t.status = 'confirmed'
                OR EXISTS (
                    SELECT 1 FROM purchases p
                    WHERE p.user_id = t.user_id
                      AND p.showtime_id = t.showtime_id
                      AND p.status = 'pending'
                      AND p.expires_at > NOW()
                )
          )
    ");
    $stmt->execute([$showtimeId, $_SESSION['user_id']]);
    $occupiedSeats = $stmt->fetchAll(PDO::FETCH_COLUMN);

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