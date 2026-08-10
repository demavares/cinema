<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$showtimeId = isset($_POST['showtime_id']) ? intval($_POST['showtime_id']) : 0;
if ($showtimeId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Showtime inválido']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Marcar compras pendientes como expiradas
    $stmt = $pdo->prepare("
        UPDATE purchases 
        SET status = 'expired', expires_at = NOW()
        WHERE user_id = ? AND showtime_id = ? AND status = 'pending'
    ");
    $stmt->execute([$_SESSION['user_id'], $showtimeId]);
    $expiredCount = $stmt->rowCount();
    
    // Obtener asientos a liberar
    $stmt = $pdo->prepare("
        SELECT seats FROM purchases 
        WHERE user_id = ? AND showtime_id = ? AND status = 'expired'
    ");
    $stmt->execute([$_SESSION['user_id'], $showtimeId]);
    $expiredSeats = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $allSeats = [];
    foreach ($expiredSeats as $seatsString) {
        if (!empty($seatsString)) {
            $allSeats = array_merge($allSeats, array_map('trim', explode(',', $seatsString)));
        }
    }
    $allSeats = array_unique($allSeats);
    
    // Eliminar tickets temporales
    if (!empty($allSeats)) {
        $placeholders = implode(',', array_fill(0, count($allSeats), '?'));
        $stmt = $pdo->prepare("
            DELETE t FROM tickets t
            WHERE t.showtime_id = ? AND t.user_id = ? AND t.seat_code IN ($placeholders)
            AND NOT EXISTS (
                SELECT 1 FROM purchases p 
                WHERE p.user_id = t.user_id AND p.showtime_id = t.showtime_id AND p.status = 'completed'
            )
        ");
        $stmt->execute(array_merge([$showtimeId, $_SESSION['user_id']], $allSeats));
        $deletedCount = $stmt->rowCount();
    } else {
        $deletedCount = 0;
    }
    
    $pdo->commit();
    clearPurchaseSession($showtimeId);
    
    echo json_encode([
        'success' => true,
        'expired_purchases' => $expiredCount,
        'deleted_tickets' => $deletedCount,
        'released_seats' => $allSeats
    ]);
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("liberar_asientos: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
?>