<?php
require_once 'config.php';

// ============================================
// ✅ CONFIGURAR RESPUESTA JSON
// ============================================
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// ============================================
// ✅ VERIFICAR AUTENTICACIÓN (CRÍTICO)
// ============================================
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$userId = $_SESSION['user_id'];

// ============================================
// ✅ VERIFICAR MÉTODO (solo POST o GET con ajax)
// ============================================
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !$isAjax) {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// ============================================
// ✅ OBTENER Y VALIDAR showtime_id
// ============================================
$showtimeId = isset($_POST['showtime_id']) ? intval($_POST['showtime_id']) : 
              (isset($_GET['showtime_id']) ? intval($_GET['showtime_id']) : 0);

if ($showtimeId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'showtime_id inválido']);
    exit;
}

// ============================================
// ✅ VERIFICAR CSRF (para peticiones POST)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!empty($csrfToken) && !verifyCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Token CSRF inválido']);
        exit;
    }
}

try {
    $released = 0;
    $cleanedSessions = 0;

    // ============================================
    // ✅ LIMPIAR TODAS LAS SESIONES RELACIONADAS
    // ============================================
    $sessionPrefixes = [
        'food_timeout_' . $showtimeId,
        'food_seats_' . $showtimeId,
        'food_valid_' . $showtimeId,
        'food_order_' . $showtimeId,
        'food_created_' . $showtimeId,
        'purchase_token_' . $showtimeId,
        'purchase_expires_at_' . $showtimeId,
        'purchase_token_used_' . $showtimeId,
        'purchase_created_at_' . $showtimeId,
        'ticket_quantities_' . $showtimeId,
        'total_seats_' . $showtimeId,
        'subtotal_' . $showtimeId,
        'tax_amount_' . $showtimeId,
        'total_amount_' . $showtimeId,
        'tax_rate_' . $showtimeId,
        'payment_method_' . $showtimeId
    ];

    foreach ($sessionPrefixes as $key) {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
            $cleanedSessions++;
        }
    }

    // ============================================
    // ✅ LIBERAR COMPRAS PENDIENTES EXPIRADAS
    // SOLO DEL USUARIO ACTUAL (seguridad)
    // ============================================
    $stmt = $pdo->prepare("
        SELECT id, seats, session_token, expires_at
        FROM purchases 
        WHERE user_id = ?
        AND showtime_id = ? 
        AND status = 'pending' 
        AND expires_at < NOW()
    ");
    $stmt->execute([$userId, $showtimeId]);
    $expired = $stmt->fetchAll();

    foreach ($expired as $purchase) {
        // Marcar como expirado
        $stmt = $pdo->prepare("UPDATE purchases SET status = 'expired' WHERE id = ? AND user_id = ?");
        $stmt->execute([$purchase['id'], $userId]);
        
        // ✅ Solo eliminar tickets si existen y pertenecen a esta compra pendiente
        if (!empty($purchase['seats'])) {
            $seatsArray = array_filter(array_map('trim', explode(',', $purchase['seats'])));
            
            if (!empty($seatsArray)) {
                $placeholders = implode(',', array_fill(0, count($seatsArray), '?'));
                
                // ✅ Solo eliminar tickets que NO estén asociados a compras completadas
                $stmt = $pdo->prepare("
                    DELETE t FROM tickets t
                    WHERE t.showtime_id = ? 
                    AND t.seat_code IN ($placeholders)
                    AND t.user_id = ?
                    AND NOT EXISTS (
                        SELECT 1 FROM purchases p 
                        WHERE p.user_id = t.user_id 
                        AND p.showtime_id = t.showtime_id 
                        AND p.status = 'completed'
                    )
                ");
                $stmt->execute(array_merge([$showtimeId], $seatsArray, [$userId]));
            }
        }
        
        $released++;
        
        error_log(sprintf(
            "🗑️ Compra pendiente #%d expirada y liberada (user_id=%d, showtime_id=%d)",
            $purchase['id'],
            $userId,
            $showtimeId
        ));
    }

    // ============================================
    // ✅ LIMPIAR TAMBIÉN COMPRAS PENDIENTES NO EXPIRADAS
    // del usuario actual si está abandonando la sesión
    // ============================================
    $stmt = $pdo->prepare("
        UPDATE purchases 
        SET status = 'expired', session_token = NULL
        WHERE user_id = ?
        AND showtime_id = ? 
        AND status = 'pending'
    ");
    $stmt->execute([$userId, $showtimeId]);
    $additionalCleaned = $stmt->rowCount();

    // ============================================
    // ✅ RESPONDER
    // ============================================
    echo json_encode([
        'success' => true,
        'released' => $released,
        'additional_cleaned' => $additionalCleaned,
        'sessions_cleaned' => $cleanedSessions,
        'showtime_id' => $showtimeId
    ]);
    exit;

} catch (PDOException $e) {
    error_log("Error en clear_seats_session.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
    exit;
} catch (Exception $e) {
    error_log("Error inesperado en clear_seats_session.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error inesperado']);
    exit;
}
?>