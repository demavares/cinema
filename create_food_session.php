<?php
require_once 'config.php';

// ============================================
// VERIFICAR AUTENTICACIÓN
// ============================================
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$showtimeId = isset($_POST['showtime_id']) ? intval($_POST['showtime_id']) : 0;
$seats = isset($_POST['seats']) ? $_POST['seats'] : '';
$token = isset($_POST['token']) ? $_POST['token'] : '';

if ($showtimeId <= 0 || empty($seats)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data']);
    exit;
}

// ============================================
// ✅ VALIDAR TOKEN DE COMPRA
// ============================================
if (empty($token) || !verifyPurchaseToken($token, $showtimeId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token de compra inválido']);
    exit;
}

// ============================================
// ✅ VERIFICAR DISPONIBILIDAD CON BLOQUEO (FOR UPDATE)
// ============================================
try {
    $pdo->beginTransaction();
    
    // Bloquear la fila del showtime
    $stmt = $pdo->prepare("
        SELECT s.*, r.capacity, r.seat_layout
        FROM showtimes s
        JOIN rooms r ON s.room_id = r.id
        WHERE s.id = ? FOR UPDATE
    ");
    $stmt->execute([$showtimeId]);
    $showtimeLocked = $stmt->fetch();
    
    if (!$showtimeLocked) {
        throw new Exception("Función no encontrada");
    }
    
    // Obtener asientos ocupados actuales
    $stmt = $pdo->prepare("SELECT seat_code FROM tickets WHERE showtime_id = ?");
    $stmt->execute([$showtimeId]);
    $occupiedSeats = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Verificar que los asientos solicitados estén disponibles
    $requestedSeats = array_filter(array_map('trim', explode(',', $seats)));
    $conflictSeats = array_intersect($requestedSeats, $occupiedSeats);
    
    if (!empty($conflictSeats)) {
        throw new Exception("Los siguientes asientos ya están ocupados: " . implode(', ', $conflictSeats));
    }
    
    // Verificar asientos bloqueados
    $layout = json_decode($showtimeLocked['seat_layout'], true);
    $blockedSeats = $layout['blockedSeats'] ?? [];
    $blockedRequested = array_intersect($requestedSeats, $blockedSeats);
    
    if (!empty($blockedRequested)) {
        throw new Exception("Los siguientes asientos están bloqueados: " . implode(', ', $blockedRequested));
    }
    
    // ============================================
    // CREAR SESIÓN DE COMIDA VÁLIDA
    // ============================================
    $sessionKey = 'food_timeout_' . $showtimeId;
    $sessionSeatsKey = 'food_seats_' . $showtimeId;
    $sessionValidKey = 'food_valid_' . $showtimeId;
    $sessionCreatedKey = 'food_created_' . $showtimeId;
    
    // Limpiar sesiones anteriores
    unset($_SESSION[$sessionKey]);
    unset($_SESSION[$sessionSeatsKey]);
    unset($_SESSION[$sessionValidKey]);
    unset($_SESSION[$sessionCreatedKey]);
    
    // Guardar timestamp de creación y tiempo inicial
    $_SESSION[$sessionKey] = 600; // 10 minutos
    $_SESSION[$sessionSeatsKey] = $seats;
    $_SESSION[$sessionValidKey] = true;
    $_SESSION[$sessionCreatedKey] = time();
    
    // ============================================
    // REGISTRAR RESERVA TEMPORAL EN BASE DE DATOS
    // ============================================
    $stmt = $pdo->prepare("
        SELECT id FROM purchases 
        WHERE user_id = ? AND showtime_id = ? AND status = 'pending'
        FOR UPDATE
    ");
    $stmt->execute([$_SESSION['user_id'], $showtimeId]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        $stmt = $pdo->prepare("
            UPDATE purchases 
            SET seats = ?, expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE), session_token = ?
            WHERE id = ?
        ");
        $stmt->execute([$seats, bin2hex(random_bytes(32)), $existing['id']]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO purchases (user_id, showtime_id, seats, total_tickets, total_food, total_amount, session_token, expires_at, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 'pending')
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $showtimeId,
            $seats,
            count(explode(',', $seats)),
            0,
            0,
            bin2hex(random_bytes(32))
        ]);
    }
    
    $pdo->commit();
    
    echo json_encode(['success' => true]);
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("ERROR en create_food_session: " . $e->getMessage());
    http_response_code(409);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
?>