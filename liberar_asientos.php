<?php
// ============================================
// liberar_asientos.php - Liberar asientos reservados
// ============================================
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$showtimeId = isset($_POST['showtime_id']) ? intval($_POST['showtime_id']) : 0;

if ($showtimeId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Showtime inválido']);
    exit;
}

try {
    $pdo->beginTransaction();

    // ============================================
    // Marcar compras pendientes como expiradas
    // ============================================
    $stmt = $pdo->prepare("
        UPDATE purchases
        SET status = 'expired', expires_at = NOW()
        WHERE user_id = ? AND showtime_id = ? AND status = 'pending'
    ");
    $stmt->execute([$_SESSION['user_id'], $showtimeId]);
    $expiredCount = $stmt->rowCount();

    // ============================================
    // UNIFICADO: Eliminar SOLO reservas temporales (status='hold')
    // Sin NOT EXISTS redundante. status='hold' garantiza que nunca
    // se toca un ticket confirmado (pagado).
    // ============================================
    $stmt = $pdo->prepare("
        DELETE FROM tickets
        WHERE showtime_id = ? AND user_id = ?
        AND status = 'hold'
    ");
    $stmt->execute([$showtimeId, $_SESSION['user_id']]);
    $deletedCount = $stmt->rowCount();

    $pdo->commit();

    // Limpiar sesión
    clearPurchaseSession($showtimeId);
    unset($_SESSION['food_valid_' . $showtimeId]);
    unset($_SESSION['food_seats_' . $showtimeId]);
    unset($_SESSION['food_timeout_' . $showtimeId]);
    unset($_SESSION['food_order_' . $showtimeId]);

    echo json_encode([
        'success' => true,
        'expired_purchases' => $expiredCount,
        'deleted_tickets' => $deletedCount
    ]);
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("❌ liberar_asientos.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
?>