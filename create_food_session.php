<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF token inválido']);
    exit;
}

$showtimeId = isset($_POST['showtime_id']) ? intval($_POST['showtime_id']) : 0;
$seats = isset($_POST['seats']) ? $_POST['seats'] : '';
$token = isset($_POST['purchase_token']) ? $_POST['purchase_token'] : '';

if ($showtimeId <= 0 || empty($seats)) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos inválidos']);
    exit;
}

// ============================================
// ✅ VERIFICAR TIMEOUT DEL TOKEN
// ============================================
if (isPurchaseTokenExpired($showtimeId)) {
    clearPurchaseSession($showtimeId);
    http_response_code(403);
    echo json_encode(['error' => 'El tiempo para la reserva ha expirado']);
    exit;
}

// ============================================
// ✅ VALIDAR TOKEN DE COMPRA
// ============================================
if (empty($token) || !verifyPurchaseTokenWithTimeout($token, $showtimeId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token de compra inválido o expirado']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // ============================================
    // 1. BLOQUEAR LA FILA DEL SHOWTIME
    // ============================================
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
    
    // ============================================
    // 2. VERIFICAR ASIENTOS OCUPADOS
    // ============================================
    $requestedSeats = array_filter(array_map('trim', explode(',', $seats)));
    
    $placeholders = implode(',', array_fill(0, count($requestedSeats), '?'));
    $stmtCheck = $pdo->prepare("SELECT seat_code FROM tickets WHERE showtime_id = ? AND seat_code IN ($placeholders) FOR UPDATE");
    $stmtCheck->execute(array_merge([$showtimeId], $requestedSeats));
    $occupiedSeats = $stmtCheck->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($occupiedSeats)) {
        throw new Exception("Los siguientes asientos ya están ocupados: " . implode(', ', $occupiedSeats));
    }
    
    // ============================================
    // 3. VERIFICAR COMPRAS PENDIENTES DE OTROS USUARIOS
    // ============================================
    $stmtPending = $pdo->prepare("
        SELECT seats FROM purchases 
        WHERE showtime_id = ? AND status = 'pending' AND user_id != ?
        FOR UPDATE
    ");
    $stmtPending->execute([$showtimeId, $_SESSION['user_id']]);
    $pendingPurchases = $stmtPending->fetchAll();

    foreach ($pendingPurchases as $pending) {
        $pendingSeats = explode(',', $pending['seats']);
        $conflictSeats = array_intersect($requestedSeats, $pendingSeats);
        if (!empty($conflictSeats)) {
            throw new Exception("Los siguientes asientos están siendo reservados por otro usuario: " . implode(', ', $conflictSeats));
        }
    }
    
    // ============================================
    // 4. VERIFICAR ASIENTOS BLOQUEADOS POR DISEÑO
    // ============================================
    $layout = json_decode($showtimeLocked['seat_layout'], true);
    $blockedSeats = $layout['blockedSeats'] ?? [];
    $blockedRequested = array_intersect($requestedSeats, $blockedSeats);
    
    if (!empty($blockedRequested)) {
        throw new Exception("Los siguientes asientos están bloqueados: " . implode(', ', $blockedRequested));
    }
    
    // ============================================
    // 5. ELIMINAR COMPRAS PENDIENTES DEL USUARIO ACTUAL
    // ============================================
    $stmtDelete = $pdo->prepare("
        DELETE FROM purchases 
        WHERE user_id = ? AND showtime_id = ? AND status = 'pending'
    ");
    $stmtDelete->execute([$_SESSION['user_id'], $showtimeId]);
    
    // ============================================
    // 6. CREAR NUEVA COMPRA PENDIENTE
    // ============================================
    $sessionKey = 'food_timeout_' . $showtimeId;
    $sessionSeatsKey = 'food_seats_' . $showtimeId;
    $sessionValidKey = 'food_valid_' . $showtimeId;
    $sessionCreatedKey = 'food_created_' . $showtimeId;
    
    unset($_SESSION[$sessionKey]);
    unset($_SESSION[$sessionSeatsKey]);
    unset($_SESSION[$sessionValidKey]);
    unset($_SESSION[$sessionCreatedKey]);
    
    $_SESSION[$sessionKey] = 600;
    $_SESSION[$sessionSeatsKey] = $seats;
    $_SESSION[$sessionValidKey] = true;
    $_SESSION[$sessionCreatedKey] = time();
    
    // ✅ Insertar compra pendiente con todos los campos necesarios
    $sessionToken = bin2hex(random_bytes(32));
    $tempHash = hash('sha256', $seats . '|' . count($requestedSeats) . '|pending');
    
    $stmt = $pdo->prepare("
        INSERT INTO purchases (
            user_id, 
            showtime_id, 
            seats, 
            total_tickets, 
            total_food,
            subtotal,
            tax_amount,
            tax_rate,
            total_amount,
            session_token, 
            expires_at, 
            status,
            data_hash,
            data_integrity_check
        ) VALUES (?, ?, ?, ?, 0, 0, 0, 0, 0, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 'pending', ?, 0)
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $showtimeId,
        $seats,
        count($requestedSeats),
        $sessionToken,
        $tempHash
    ]);
    
    // ============================================
    // 7. INSERTAR TICKETS TEMPORALES
    // ============================================
    $stmtTicket = $pdo->prepare("
        INSERT INTO tickets (user_id, showtime_id, seat_code, price_paid) 
        VALUES (?, ?, ?, ?)
    ");
    
    $tempPrice = getShowtimePrice($showtimeLocked);
    
    foreach ($requestedSeats as $seat) {
        $stmtTicket->execute([$_SESSION['user_id'], $showtimeId, $seat, $tempPrice]);
    }
    
    $pdo->commit();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error en create_food_session: " . $e->getMessage());
    http_response_code(409);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
?>