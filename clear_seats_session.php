<?php
// ============================================
// clear_seats_session.php - Limpieza al expirar el tiempo de comida
// ============================================
// Llamado por timeout_manager.js cuando el contador de comida llega a 0.
// Expira la compra pending del usuario, elimina sus holds y limpia la
// sesión del flujo, para liberar los asientos de forma inmediata
// (sin esperar el siguiente ciclo de releaseExpiredSeats).
// ============================================
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$showtimeId = intval($_POST['showtime_id'] ?? 0);
if ($showtimeId <= 0) {
    echo json_encode(['success' => false, 'error' => 'showtime_id inválido']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    // ============================================
    // 1) Expirar compras 'pending' del usuario en este showtime
    // ============================================
    $pdo->beginTransaction();

    $stmtExpire = $pdo->prepare("
        UPDATE purchases
        SET status = 'expired', expires_at = NOW(), session_token = NULL
        WHERE user_id = ?
          AND showtime_id = ?
          AND status = 'pending'
    ");
    $stmtExpire->execute([$userId, $showtimeId]);

    // ============================================
    // 2) Eliminar tickets 'hold' del usuario en este showtime
    // ============================================
    $stmtDelete = $pdo->prepare("
        DELETE FROM tickets
        WHERE user_id = ?
          AND showtime_id = ?
          AND status = 'hold'
    ");
    $stmtDelete->execute([$userId, $showtimeId]);
    $released = $stmtDelete->rowCount();

    $pdo->commit();

    // ============================================
    // 3) Limpiar claves de sesión del flujo (compra + comida)
    // ============================================
    clearPurchaseSession($showtimeId);

    foreach ([
        'food_created_' . $showtimeId,
        'food_timeout_' . $showtimeId,
        'food_seats_' . $showtimeId,
        'food_valid_' . $showtimeId,
        'food_order_' . $showtimeId
    ] as $key) {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }

    echo json_encode(['success' => true, 'released' => $released]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("❌ clear_seats_session.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error interno']);
}