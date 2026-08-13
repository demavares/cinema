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

error_log("🔍 create_food_session.php - showtimeId: $showtimeId");
error_log("🔍 create_food_session.php - seats: " . substr($seats, 0, 50) . "...");
error_log("🔍 create_food_session.php - token: " . substr($token, 0, 10) . "...");

// ============================================
// ✅ VERIFICAR DATOS EN SESIÓN
// ============================================
$ticketsKey = 'ticket_quantities_' . $showtimeId;
$totalSeatsKey = 'total_seats_' . $showtimeId;

error_log("🔍 create_food_session.php - ticketsKey: $ticketsKey");
error_log("🔍 create_food_session.php - totalSeatsKey: $totalSeatsKey");
error_log("🔍 create_food_session.php - ticket_quantities en sesión: " . (isset($_SESSION[$ticketsKey]) ? json_encode($_SESSION[$ticketsKey]) : 'NO EXISTE'));
error_log("🔍 create_food_session.php - total_seats en sesión: " . ($_SESSION[$totalSeatsKey] ?? 'NO EXISTE'));

// ✅ OBTENER DATOS DE BOLETOS DE LA SESIÓN
$ticketsData = $_SESSION[$ticketsKey] ?? null;
$totalSeatsNeeded = $_SESSION[$totalSeatsKey] ?? 0;

// ✅ Si no hay datos de boletos, intentar recuperar del showtime usando los asientos enviados
if (!$ticketsData || $totalSeatsNeeded <= 0) {
    error_log("⚠️ create_food_session.php - No hay datos de boletos en sesión, intentando recuperar de los asientos enviados");
    
    // Intentar recuperar usando los asientos enviados en el POST
    $seatArray = array_filter(array_map('trim', explode(',', $seats)));
    $seatCount = count($seatArray);
    
    if ($seatCount > 0) {
        // Crear datos de boletos por defecto (todos adultos)
        $ticketsData = ['adult' => $seatCount, 'child' => 0, 'senior' => 0];
        $totalSeatsNeeded = $seatCount;
        
        // Guardar en sesión para futuras solicitudes
        $_SESSION[$ticketsKey] = $ticketsData;
        $_SESSION[$totalSeatsKey] = $totalSeatsNeeded;
        
        error_log("✅ create_food_session.php - Recuperado de asientos enviados: $totalSeatsNeeded asientos");
    } else {
        error_log("❌ create_food_session.php - No hay asientos enviados en POST");
        http_response_code(400);
        echo json_encode(['error' => 'No hay boletos seleccionados para esta función']);
        exit;
    }
}

// Validar que la cantidad de asientos coincida
$seatArray = array_filter(array_map('trim', explode(',', $seats)));
$seatCount = count($seatArray);

if ($seatCount === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'No hay asientos seleccionados']);
    exit;
}

if ($seatCount !== $totalSeatsNeeded) {
    error_log("⚠️ create_food_session.php - Desajuste: seatCount=$seatCount, totalSeatsNeeded=$totalSeatsNeeded");
    // Usar la cantidad real de asientos enviados
    $ticketsData = ['adult' => $seatCount, 'child' => 0, 'senior' => 0];
    $totalSeatsNeeded = $seatCount;
    // Actualizar sesión
    $_SESSION[$ticketsKey] = $ticketsData;
    $_SESSION[$totalSeatsKey] = $totalSeatsNeeded;
}

// ============================================
// VERIFICAR TOKEN (MEJORADO)
// ============================================
try {
    $sessionToken = $_SESSION['purchase_token_' . $showtimeId] ?? '';
    
    // ✅ LOG DE DEPURACIÓN
    error_log("🔍 create_food_session.php - Token recibido: " . substr($token, 0, 10) . "...");
    error_log("🔍 create_food_session.php - Token en sesión: " . substr($sessionToken, 0, 10) . "...");
    error_log("🔍 create_food_session.php - purchase_expires_at: " . ($_SESSION['purchase_expires_at_' . $showtimeId] ?? 'NO EXISTE'));
    
    // ✅ Si no hay token en sesión O está expirado, regenerar
    if (empty($sessionToken) || isPurchaseTokenExpired($showtimeId)) {
        error_log("🔄 create_food_session.php: Token expirado o inexistente, regenerando");
        clearPurchaseSession($showtimeId);
        $sessionToken = generatePurchaseTokenWithTimeout($showtimeId, 900);
        $_SESSION['purchase_token_' . $showtimeId] = $sessionToken;
        
        // Restaurar datos de boletos
        $_SESSION[$ticketsKey] = $ticketsData;
        $_SESSION[$totalSeatsKey] = $totalSeatsNeeded;
    }
    
    // ✅ Verificar que el token recibido coincida con el de sesión
    if (empty($token) || !hash_equals($sessionToken, $token)) {
        error_log("❌ create_food_session.php: Token no coincide");
        error_log("   Token recibido: " . substr($token, 0, 10) . "...");
        error_log("   Token esperado: " . substr($sessionToken, 0, 10) . "...");
        http_response_code(403);
        echo json_encode(['error' => 'Token inválido o expirado']);
        exit;
    }
    
    error_log("✅ create_food_session.php: Token verificado correctamente");
    
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
    if ($seatCount === 0) {
        throw new Exception("Debes seleccionar al menos un asiento");
    }
    
    if ($seatCount !== $totalSeatsNeeded) {
        error_log("⚠️ create_food_session.php - Seat count mismatch: $seatCount vs $totalSeatsNeeded");
        // No lanzar error, usar la cantidad real
    }
    
    if ($seatCount > 20) {
        throw new Exception("Máximo 20 asientos por compra");
    }
    
    foreach ($seatArray as $seat) {
        if (!preg_match('/^[A-Z]{1,2}[0-9]{1,3}$/', $seat)) {
            throw new Exception("Formato de asiento inválido: $seat");
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
    
    $stmtInsert = $pdo->prepare("
        INSERT IGNORE INTO tickets (user_id, showtime_id, seat_code, price_paid)
        VALUES (?, ?, ?, 0)
    ");
    
    foreach ($seatArray as $seat) {
        $stmtInsert->execute([$_SESSION['user_id'], $showtimeId, $seat]);
    }
    
    // ============================================
    // ✅ ESTABLECER SESIÓN DE COMIDA
    // ============================================
    $_SESSION['food_timeout_' . $showtimeId] = 600;
    $_SESSION['food_seats_' . $showtimeId] = $seats;
    $_SESSION['food_valid_' . $showtimeId] = true;
    $_SESSION['food_created_' . $showtimeId] = time();
    $_SESSION['purchase_token_' . $showtimeId] = $token;
    $_SESSION['purchase_expires_at_' . $showtimeId] = time() + 900;
    $_SESSION[$ticketsKey] = $ticketsData;
    $_SESSION[$totalSeatsKey] = $seatCount;
    
    error_log("✅ create_food_session.php - Sesión de comida creada exitosamente");
    error_log("✅ food_valid_" . $showtimeId . " = true");
    error_log("✅ food_seats_" . $showtimeId . " = $seats");
    error_log("✅ total_seats_" . $showtimeId . " = $seatCount");
    
    $pdo->commit();
    
    echo json_encode(['success' => true]);
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("❌ create_food_session.php: " . $e->getMessage());
    http_response_code(409);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
?>