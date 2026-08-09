<?php
require_once 'config.php';

header('Content-Type: application/json');

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
$token = $_POST['purchase_token'] ?? '';

if ($showtimeId <= 0 || empty($seats)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data']);
    exit;
}

// ============================================
// VERIFICAR TIMEOUT DEL TOKEN
// ============================================
if (isPurchaseTokenExpired($showtimeId)) {
    clearPurchaseSession($showtimeId);
    http_response_code(403);
    echo json_encode(['error' => 'El tiempo para la reserva ha expirado']);
    exit;
}

// ============================================
// VALIDAR TOKEN DE COMPRA
// ============================================
if (empty($token) || !verifyPurchaseTokenWithTimeout($token, $showtimeId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token de compra inválido o expirado']);
    exit;
}

try {
    $pdo->beginTransaction();
    
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
    // PROCESAR Y VALIDAR ASIENTOS
    // ============================================
    $seatArray = array_filter(array_map('trim', explode(',', $seats)));
    $seatCount = count($seatArray);
    
    if ($seatCount === 0) {
        throw new Exception("Debes seleccionar al menos un asiento");
    }
    
    if ($seatCount !== count(array_unique($seatArray))) {
        throw new Exception("Se detectaron asientos duplicados en la selección");
    }
    
    $capacity = intval($showtimeLocked['capacity'] ?? 0);
    if ($capacity > 0 && $seatCount > $capacity) {
        throw new Exception("La selección excede la capacidad de la sala ($capacity asientos)");
    }
    
    if ($seatCount > 20) {
        throw new Exception("Máximo 20 asientos por compra");
    }
    
    foreach ($seatArray as $seat) {
        if (!preg_match('/^[A-Z]{1,2}[0-9]{1,3}$/', $seat)) {
            throw new Exception("Formato de asiento inválido: $seat");
        }
    }
    
    $totalSeatsKey = 'total_seats_' . $showtimeId;
    if (isset($_SESSION[$totalSeatsKey])) {
        $expectedSeats = intval($_SESSION[$totalSeatsKey]);
        if ($seatCount !== $expectedSeats) {
            throw new Exception("La cantidad de asientos ($seatCount) no coincide con los boletos seleccionados ($expectedSeats)");
        }
    }
    
    // ============================================
    // VERIFICAR ASIENTOS OCUPADOS
    // ============================================
    $placeholders = implode(',', array_fill(0, count($seatArray), '?'));
    $stmt = $pdo->prepare("
        SELECT seat_code FROM tickets 
        WHERE showtime_id = ? AND seat_code IN ($placeholders)
        FOR UPDATE
    ");
    $stmt->execute(array_merge([$showtimeId], $seatArray));
    $occupiedSeats = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $conflictSeats = array_intersect($seatArray, $occupiedSeats);
    
    if (!empty($conflictSeats)) {
        throw new Exception("Los siguientes asientos ya están ocupados: " . implode(', ', $conflictSeats));
    }
    
    // ============================================
    // VERIFICAR ASIENTOS BLOQUEADOS
    // ============================================
    $layout = json_decode($showtimeLocked['seat_layout'], true);
    $blockedSeats = $layout['blockedSeats'] ?? [];
    $blockedRequested = array_intersect($seatArray, $blockedSeats);
    
    if (!empty($blockedRequested)) {
        throw new Exception("Los siguientes asientos están bloqueados: " . implode(', ', $blockedRequested));
    }
    
    // ============================================
    // VERIFICAR QUE EL USUARIO TENGA UNA COMPRA PENDIENTE
    // ============================================
    $stmt = $pdo->prepare("
        SELECT id, seats FROM purchases 
        WHERE user_id = ? AND showtime_id = ? AND status = 'pending'
        FOR UPDATE
    ");
    $stmt->execute([$_SESSION['user_id'], $showtimeId]);
    $existing = $stmt->fetch();
    
    if (!$existing) {
        // Crear una compra pendiente si no existe
        $stmt = $pdo->prepare("
            INSERT INTO purchases (user_id, showtime_id, seats, total_tickets, total_food, total_amount, session_token, expires_at, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 'pending')
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $showtimeId,
            $seats,
            $seatCount,
            0,
            0,
            bin2hex(random_bytes(32))
        ]);
        error_log("✅ Compra pendiente creada para usuario " . $_SESSION['user_id'] . " en showtime $showtimeId");
    } else {
        // Actualizar compra existente
        $stmt = $pdo->prepare("
            UPDATE purchases 
            SET seats = ?, expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE), session_token = ?
            WHERE id = ?
        ");
        $stmt->execute([$seats, bin2hex(random_bytes(32)), $existing['id']]);
        error_log("✅ Compra pendiente actualizada para usuario " . $_SESSION['user_id'] . " en showtime $showtimeId");
    }
    
    // ============================================
    // CREAR TICKETS TEMPORALES (SOLO SI NO EXISTEN)
    // ============================================
    
    // Primero, eliminar tickets temporales antiguos del usuario para este showtime
    $stmt = $pdo->prepare("
        DELETE t FROM tickets t
        WHERE t.showtime_id = ? AND t.user_id = ?
        AND NOT EXISTS (
            SELECT 1 FROM purchases p 
            WHERE p.user_id = t.user_id 
            AND p.showtime_id = t.showtime_id 
            AND p.status = 'completed'
        )
    ");
    $stmt->execute([$showtimeId, $_SESSION['user_id']]);
    $deletedCount = $stmt->rowCount();
    if ($deletedCount > 0) {
        error_log("🗑️ Tickets temporales eliminados en create_food_session: $deletedCount asientos");
    }
    
    // Luego, insertar los nuevos tickets
    $stmtInsert = $pdo->prepare("
        INSERT INTO tickets (user_id, showtime_id, seat_code, price_paid)
        VALUES (?, ?, ?, 0)
    ");
    
    $ticketsCreated = 0;
    foreach ($seatArray as $seat) {
        // Verificar si el ticket ya existe (para evitar duplicados)
        $stmtCheck = $pdo->prepare("
            SELECT id FROM tickets 
            WHERE showtime_id = ? AND user_id = ? AND seat_code = ?
        ");
        $stmtCheck->execute([$showtimeId, $_SESSION['user_id'], $seat]);
        
        if (!$stmtCheck->fetch()) {
            $stmtInsert->execute([$_SESSION['user_id'], $showtimeId, $seat]);
            $ticketsCreated++;
            error_log("✅ Ticket temporal creado: asiento $seat para usuario " . $_SESSION['user_id']);
        }
    }
    
    error_log("✅ Tickets temporales creados/actualizados: $ticketsCreated de " . count($seatArray) . " asientos");
    
    // Verificar que los tickets existen
    $stmtVerify = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE showtime_id = ? AND user_id = ?");
    $stmtVerify->execute([$showtimeId, $_SESSION['user_id']]);
    $count = $stmtVerify->fetchColumn();
    error_log("📊 Total tickets en BD para este showtime/usuario: $count");
    
    // ============================================
    // ESTABLECER SESIONES DE COMIDA
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
    
    // Establecer nuevas sesiones
    $_SESSION[$sessionKey] = 600;
    $_SESSION[$sessionSeatsKey] = $seats;
    $_SESSION[$sessionValidKey] = true;
    $_SESSION[$sessionCreatedKey] = time();
    
    // Guardar el token en sesión
    $_SESSION['purchase_token_' . $showtimeId] = $token;
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'tickets_created' => $ticketsCreated]);
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("❌ Error en create_food_session.php: " . $e->getMessage());
    http_response_code(409);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
?>