<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$showtimeId = isset($_POST['showtime_id']) ? intval($_POST['showtime_id']) : 0;
if ($showtimeId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'showtime_id inválido']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Limpiar compras pendientes
    $stmt = $pdo->prepare("
        UPDATE purchases 
        SET status = 'expired', expires_at = NOW()
        WHERE user_id = ? AND showtime_id = ? AND status = 'pending'
    ");
    $stmt->execute([$_SESSION['user_id'], $showtimeId]);
    
    // Eliminar tickets temporales
    $stmt = $pdo->prepare("
        DELETE t FROM tickets t
        WHERE t.showtime_id = ? AND t.user_id = ?
        AND NOT EXISTS (
            SELECT 1 FROM purchases p 
            WHERE p.user_id = t.user_id AND p.showtime_id = t.showtime_id AND p.status = 'completed'
        )
    ");
    $stmt->execute([$showtimeId, $_SESSION['user_id']]);
    $deletedCount = $stmt->rowCount();
    
    $pdo->commit();
    clearPurchaseSession($showtimeId);
    
    echo json_encode(['success' => true, 'deleted' => $deletedCount]);
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("clear_seats_session: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
?>