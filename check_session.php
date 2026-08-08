<?php
require_once 'config.php';

// ============================================
// ✅ CONFIGURAR RESPUESTA JSON
// ============================================
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// ============================================
// ✅ VERIFICAR AUTENTICACIÓN
// ============================================
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['valid' => false, 'reason' => 'no_session']);
    exit;
}

$userId = $_SESSION['user_id'];

// ============================================
// ✅ VALIDAR PARÁMETROS
// ============================================
$showtimeId = isset($_GET['showtime_id']) ? intval($_GET['showtime_id']) : 0;

if ($showtimeId <= 0) {
    echo json_encode(['valid' => false, 'reason' => 'invalid_showtime']);
    exit;
}

try {
    // ============================================
    // ✅ VERIFICAR QUE EL SHOWTIME EXISTE Y ESTÁ ACTIVO
    // ============================================
    $stmt = $pdo->prepare("
        SELECT s.id, s.is_active, s.show_date, s.show_time
        FROM showtimes s
        WHERE s.id = ?
    ");
    $stmt->execute([$showtimeId]);
    $showtime = $stmt->fetch();

    if (!$showtime) {
        echo json_encode(['valid' => false, 'reason' => 'showtime_not_found']);
        exit;
    }

    if ($showtime['is_active'] == 0) {
        echo json_encode(['valid' => false, 'reason' => 'showtime_inactive']);
        exit;
    }

    // ============================================
    // ✅ CLAVES DE SESIÓN
    // ============================================
    $sessionValidKey = 'food_valid_' . $showtimeId;
    $sessionKey = 'food_timeout_' . $showtimeId;
    $sessionSeatsKey = 'food_seats_' . $showtimeId;
    $sessionCreatedKey = 'food_created_' . $showtimeId;
    $sessionFoodOrderKey = 'food_order_' . $showtimeId;

    // ============================================
    // ✅ VERIFICAR SESIÓN DE COMIDA VÁLIDA
    // ============================================
    if (!isset($_SESSION[$sessionValidKey]) || $_SESSION[$sessionValidKey] !== true) {
        echo json_encode(['valid' => false, 'reason' => 'invalid_food_session']);
        exit;
    }

    // ============================================
    // ✅ CALCULAR TIEMPO RESTANTE (LÓGICA UNIFICADA)
    // ============================================
    $maxTimeout = 600; // 10 minutos
    $timeLeft = 0;

    if (isset($_SESSION[$sessionCreatedKey])) {
        // ✅ Método principal: calcular desde el timestamp de creación
        $elapsed = time() - $_SESSION[$sessionCreatedKey];
        $timeLeft = max(0, $maxTimeout - $elapsed);
    } elseif (isset($_SESSION[$sessionKey])) {
        // ✅ Fallback: usar el valor guardado (compatibilidad con sesiones antiguas)
        $timeLeft = max(0, intval($_SESSION[$sessionKey]));
    } else {
        // ✅ Si no hay datos de timeout, asumir sesión inválida
        echo json_encode(['valid' => false, 'reason' => 'timeout_data_missing']);
        exit;
    }

    // ✅ Sincronizar el valor en sesión
    $_SESSION[$sessionKey] = $timeLeft;

    // ============================================
    // ✅ SI EL TIMEOUT EXPIRÓ: LIMPIAR SESIONES
    // ============================================
    if ($timeLeft <= 0) {
        // ✅ Limpiar TODAS las sesiones relacionadas
        $keysToClean = [
            $sessionValidKey,
            $sessionKey,
            $sessionSeatsKey,
            $sessionCreatedKey,
            $sessionFoodOrderKey,
            'purchase_token_' . $showtimeId,
            'purchase_expires_at_' . $showtimeId,
            'purchase_token_used_' . $showtimeId,
            'ticket_quantities_' . $showtimeId,
            'total_seats_' . $showtimeId,
            'subtotal_' . $showtimeId,
            'tax_amount_' . $showtimeId,
            'total_amount_' . $showtimeId
        ];

        foreach ($keysToClean as $key) {
            if (isset($_SESSION[$key])) {
                unset($_SESSION[$key]);
            }
        }

        // ✅ Limpiar compra pendiente en BD si existe
        try {
            $stmt = $pdo->prepare("
                UPDATE purchases 
                SET status = 'expired', session_token = NULL
                WHERE user_id = ? 
                AND showtime_id = ? 
                AND status = 'pending'
            ");
            $stmt->execute([$userId, $showtimeId]);
            
            if ($stmt->rowCount() > 0) {
                error_log(sprintf(
                    "⏰ Sesión expirada limpiada: user_id=%d, showtime_id=%d",
                    $userId,
                    $showtimeId
                ));
            }
        } catch (PDOException $e) {
            error_log("Error limpiando compra expirada en check_session.php: " . $e->getMessage());
        }

        echo json_encode([
            'valid' => false,
            'reason' => 'timeout_expired',
            'timeLeft' => 0,
            'cleaned' => true
        ]);
        exit;
    }

    // ============================================
    // ✅ OBTENER ASIENTOS Y DATOS ADICIONALES
    // ============================================
    $seats = $_SESSION[$sessionSeatsKey] ?? '';
    
    // ✅ Validar que los asientos no estén vacíos
    if (empty($seats)) {
        echo json_encode(['valid' => false, 'reason' => 'no_seats_selected']);
        exit;
    }

    // ✅ Contar asientos seleccionados
    $seatsArray = array_filter(array_map('trim', explode(',', $seats)));
    $seatCount = count($seatsArray);

    // ============================================
    // ✅ VERIFICAR COMPRA PENDIENTE EN BD (opcional)
    // ============================================
    $hasPendingPurchase = false;
    try {
        $stmt = $pdo->prepare("
            SELECT id, seats, expires_at 
            FROM purchases 
            WHERE user_id = ? 
            AND showtime_id = ? 
            AND status = 'pending'
            LIMIT 1
        ");
        $stmt->execute([$userId, $showtimeId]);
        $pendingPurchase = $stmt->fetch();
        
        if ($pendingPurchase) {
            $hasPendingPurchase = true;
            
            // ✅ Verificar si la compra en BD también expiró
            if (strtotime($pendingPurchase['expires_at']) < time()) {
                $stmt = $pdo->prepare("
                    UPDATE purchases 
                    SET status = 'expired' 
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([$pendingPurchase['id'], $userId]);
                $hasPendingPurchase = false;
            }
        }
    } catch (PDOException $e) {
        error_log("Error verificando compra pendiente en check_session.php: " . $e->getMessage());
    }

    // ============================================
    // ✅ RESPONDER CON DATOS COMPLETOS
    // ============================================
    echo json_encode([
        'valid' => true,
        'timeLeft' => $timeLeft,
        'timeLeftFormatted' => gmdate('i:s', $timeLeft),
        'seats' => $seats,
        'seatCount' => $seatCount,
        'hasPendingPurchase' => $hasPendingPurchase,
        'timestamp' => time(),
        'maxTimeout' => $maxTimeout
    ]);
    exit;

} catch (PDOException $e) {
    error_log("Error en check_session.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['valid' => false, 'reason' => 'server_error']);
    exit;
} catch (Exception $e) {
    error_log("Error inesperado en check_session.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['valid' => false, 'reason' => 'unexpected_error']);
    exit;
}
?>