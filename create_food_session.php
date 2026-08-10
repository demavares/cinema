<?php
// ============================================
// create_food_session.php - Crear sesión de comida
// ============================================
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once 'config.php';

// ============================================
// VERIFICAR AUTENTICACIÓN
// ============================================
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF inválido']);
    exit;
}

$showtimeId = isset($_POST['showtime_id']) ? intval($_POST['showtime_id']) : 0;
$seats = isset($_POST['seats']) ? trim($_POST['seats']) : '';
$token = isset($_POST['purchase_token']) ? trim($_POST['purchase_token']) : '';

error_log("🔍 create_food_session.php - showtimeId: $showtimeId, seats: " . substr($seats, 0, 50) . "...");

if ($showtimeId <= 0 || empty($seats)) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos inválidos']);
    exit;
}

// ============================================
// VERIFICAR TOKEN
// ============================================
try {
    // Verificar token en sesión
    $sessionToken = $_SESSION['purchase_token_' . $showtimeId] ?? '';
    
    // Si no hay token o expiró, regenerar
    if (empty($sessionToken) || isPurchaseTokenExpired($showtimeId)) {
        error_log("🔄 create_food_session.php: Token expirado o inexistente, regenerando");
        clearPurchaseSession($showtimeId);
        $sessionToken = generatePurchaseTokenWithTimeout($showtimeId, 900);
        $_SESSION['purchase_token_' . $showtimeId] = $sessionToken;
    }
    
    // Verificar que el token recibido coincida con el de sesión
    if (empty($token) || !hash_equals($sessionToken, $token)) {
        error_log("❌ create_food_session.php: Token no coincide. Recibido: " . substr($token, 0, 10) . "...");
        http_response_code(403);
        echo json_encode(['error' => 'Token inválido o expirado']);
        exit;
    }
    
} catch (Exception $e) {
    error_log("❌ create_food_session.php - Error verificando token: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // ============================================
    // VERIFICAR SHOWTIME
    // ============================================
    $stmt = $pdo->prepare("
        SELECT s.*, r.capacity, r.seat_layout
        FROM showtimes s
        JOIN rooms r ON s.room_id = r.id
        WHERE s.id = ? AND s.is_active = 1
        FOR UPDATE
    ");
    $stmt->execute([$showtimeId]);
    $showtimeLocked = $stmt->fetch();
    
    if (!$showtimeLocked) {
        throw new Exception("Función no encontrada o inactiva");
    }
    
    // ============================================
    // VALIDAR ASIENTOS
    // ============================================
    $seatArray = array_filter(array_map('trim', explode(',', $seats)));
    $seatCount = count($seatArray);
    
    if ($seatCount === 0) {
        throw new Exception("Debes seleccionar al menos un asiento");
    }
    
    if ($seatCount !== count(array_unique($seatArray))) {
        throw new Exception("Asientos duplicados en la selección");
    }
    
    if ($seatCount > 20) {
        throw new Exception("Máximo 20 asientos por compra");
    }
    
    foreach ($seatArray as $seat) {
        if (!preg_match('/^[A-Z]{1,2}[0-9]{1,3}$/', $seat)) {
            throw new Exception("Formato de asiento inválido: $seat");
        }
    }
    
    // Validar cantidad de asientos
    $totalSeatsKey = 'total_seats_' . $showtimeId;
    if (isset($_SESSION[$totalSeatsKey])) {
        $expectedSeats = intval($_SESSION[$totalSeatsKey]);
        if ($seatCount !== $expectedSeats) {
            throw new Exception("Cantidad de asientos ($seatCount) no coincide con boletos ($expectedSeats)");
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
        throw new Exception("Asientos ocupados: " . implode(', ', $conflictSeats));
    }
    
    // ============================================
    // VERIFICAR ASIENTOS BLOQUEADOS
    // ============================================
    $layout = json_decode($showtimeLocked['seat_layout'], true);
    $blockedSeats = $layout['blockedSeats'] ?? [];
    $blockedRequested = array_intersect($seatArray, $blockedSeats);
    if (!empty($blockedRequested)) {
        throw new Exception("Asientos bloqueados: " . implode(', ', $blockedRequested));
    }
    
    // ============================================
    // VERIFICAR COMPRAS PENDIENTES DE OTROS
    // ============================================
    $stmt = $pdo->prepare("
        SELECT seats FROM purchases 
        WHERE showtime_id = ? 
        AND status = 'pending' 
        AND user_id != ?
        AND expires_at > NOW()
        FOR UPDATE
    ");
    $stmt->execute([$showtimeId, $_SESSION['user_id']]);
    $otherPending = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $allOtherSeats = [];
    foreach ($otherPending as $seatsString) {
        if (!empty($seatsString)) {
            $seatsArrayTemp = array_map('trim', explode(',', $seatsString));
            $allOtherSeats = array_merge($allOtherSeats, $seatsArrayTemp);
        }
    }
    
    $conflictWithOther = array_intersect($seatArray, $allOtherSeats);
    if (!empty($conflictWithOther)) {
        throw new Exception("Asientos reservados por otro usuario: " . implode(', ', $conflictWithOther));
    }
    
    // ============================================
    // CREAR/ACTUALIZAR COMPRA PENDIENTE
    // ============================================
    $stmt = $pdo->prepare("
        SELECT id FROM purchases 
        WHERE user_id = ? AND showtime_id = ? AND status = 'pending'
        FOR UPDATE
    ");
    $stmt->execute([$_SESSION['user_id'], $showtimeId]);
    $existing = $stmt->fetch();
    
    $newSessionToken = bin2hex(random_bytes(32));
    
    if (!$existing) {
        $stmt = $pdo->prepare("
            INSERT INTO purchases (
                user_id, showtime_id, seats, total_tickets, total_food, 
                total_amount, session_token, expires_at, status, 
                subtotal, tax_amount, tax_rate
            ) VALUES (?, ?, ?, ?, 0, 0, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 'pending', 0, 0, 0)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $showtimeId,
            $seats,
            $seatCount,
            $newSessionToken
        ]);
        error_log("✅ Compra pendiente creada para usuario " . $_SESSION['user_id']);
    } else {
        $stmt = $pdo->prepare("
            UPDATE purchases 
            SET seats = ?, total_tickets = ?, expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE), session_token = ?
            WHERE id = ?
        ");
        $stmt->execute([$seats, $seatCount, $newSessionToken, $existing['id']]);
        error_log("✅ Compra pendiente actualizada para usuario " . $_SESSION['user_id']);
    }
    
    // ============================================
    // CREAR TICKETS TEMPORALES
    // ============================================
    
    // Eliminar tickets antiguos
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
    
    // Insertar nuevos tickets
    $stmtInsert = $pdo->prepare("
        INSERT IGNORE INTO tickets (user_id, showtime_id, seat_code, price_paid)
        VALUES (?, ?, ?, 0)
    ");
    
    $ticketsCreated = 0;
    foreach ($seatArray as $seat) {
        $stmtInsert->execute([$_SESSION['user_id'], $showtimeId, $seat]);
        if ($stmtInsert->rowCount() > 0) {
            $ticketsCreated++;
        }
    }
    
    error_log("✅ Tickets temporales creados: $ticketsCreated de " . count($seatArray));
    
    // ============================================
    // ESTABLECER SESIÓN DE COMIDA
    // ============================================
    $_SESSION['food_timeout_' . $showtimeId] = 600;
    $_SESSION['food_seats_' . $showtimeId] = $seats;
    $_SESSION['food_valid_' . $showtimeId] = true;
    $_SESSION['food_created_' . $showtimeId] = time();
    $_SESSION['purchase_token_' . $showtimeId] = $token;
    $_SESSION['purchase_expires_at_' . $showtimeId] = time() + 900;
    
    $pdo->commit();
    
    error_log("✅ create_food_session.php: Sesión de comida creada exitosamente");
    echo json_encode(['success' => true, 'tickets_created' => $ticketsCreated]);
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("❌ create_food_session.php: " . $e->getMessage());
    http_response_code(409);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
?>