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
    echo json_encode(['error' => 'Datos inválidos']);
    exit;
}

// Verificar token
if (!verifyPurchaseToken($token, $showtimeId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token de compra inválido o expirado']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Verificar showtime
    $stmt = $pdo->prepare("SELECT s.*, r.capacity, r.seat_layout FROM showtimes s JOIN rooms r ON s.room_id = r.id WHERE s.id = ? AND s.is_active = 1 FOR UPDATE");
    $stmt->execute([$showtimeId]);
    $showtime = $stmt->fetch();
    if (!$showtime) {
        throw new Exception("Función no encontrada o inactiva");
    }
    
    // Validar asientos
    $seatArray = array_filter(array_map('trim', explode(',', $seats)));
    $seatCount = count($seatArray);
    if ($seatCount === 0) throw new Exception("Debes seleccionar al menos un asiento");
    if ($seatCount !== count(array_unique($seatArray))) throw new Exception("Asientos duplicados");
    if ($seatCount > 20) throw new Exception("Máximo 20 asientos por compra");
    
    // Verificar asientos ocupados
    $placeholders = implode(',', array_fill(0, count($seatArray), '?'));
    $stmt = $pdo->prepare("SELECT seat_code FROM tickets WHERE showtime_id = ? AND seat_code IN ($placeholders) FOR UPDATE");
    $stmt->execute(array_merge([$showtimeId], $seatArray));
    $occupiedSeats = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $conflictSeats = array_intersect($seatArray, $occupiedSeats);
    if (!empty($conflictSeats)) throw new Exception("Asientos ocupados: " . implode(', ', $conflictSeats));
    
    // Verificar asientos bloqueados
    $layout = json_decode($showtime['seat_layout'], true);
    $blockedSeats = $layout['blockedSeats'] ?? [];
    $blockedRequested = array_intersect($seatArray, $blockedSeats);
    if (!empty($blockedRequested)) throw new Exception("Asientos bloqueados: " . implode(', ', $blockedRequested));
    
    // Verificar compras pendientes de otros
    $stmt = $pdo->prepare("SELECT seats FROM purchases WHERE showtime_id = ? AND status = 'pending' AND user_id != ? AND expires_at > NOW() FOR UPDATE");
    $stmt->execute([$showtimeId, $_SESSION['user_id']]);
    $otherPending = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $allOtherSeats = [];
    foreach ($otherPending as $seatsString) {
        if (!empty($seatsString)) {
            $allOtherSeats = array_merge($allOtherSeats, array_map('trim', explode(',', $seatsString)));
        }
    }
    $conflictOther = array_intersect($seatArray, $allOtherSeats);
    if (!empty($conflictOther)) throw new Exception("Asientos reservados por otro usuario: " . implode(', ', $conflictOther));
    
    // Verificar compra pendiente del usuario
    $stmt = $pdo->prepare("SELECT id FROM purchases WHERE user_id = ? AND showtime_id = ? AND status = 'pending' FOR UPDATE");
    $stmt->execute([$_SESSION['user_id'], $showtimeId]);
    $existing = $stmt->fetch();
    
    if (!$existing) {
        $stmt = $pdo->prepare("INSERT INTO purchases (user_id, showtime_id, seats, total_tickets, session_token, expires_at, status) VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 'pending')");
        $stmt->execute([$_SESSION['user_id'], $showtimeId, $seats, $seatCount, bin2hex(random_bytes(32))]);
    } else {
        $stmt = $pdo->prepare("UPDATE purchases SET seats = ?, total_tickets = ?, expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE id = ?");
        $stmt->execute([$seats, $seatCount, $existing['id']]);
    }
    
    // Crear tickets temporales
    $stmt = $pdo->prepare("DELETE t FROM tickets t WHERE t.showtime_id = ? AND t.user_id = ? AND NOT EXISTS (SELECT 1 FROM purchases p WHERE p.user_id = t.user_id AND p.showtime_id = t.showtime_id AND p.status = 'completed')");
    $stmt->execute([$showtimeId, $_SESSION['user_id']]);
    
    $stmtInsert = $pdo->prepare("INSERT IGNORE INTO tickets (user_id, showtime_id, seat_code, price_paid) VALUES (?, ?, ?, 0)");
    foreach ($seatArray as $seat) {
        $stmtInsert->execute([$_SESSION['user_id'], $showtimeId, $seat]);
    }
    
    // Establecer sesión de comida
    $_SESSION['food_timeout_' . $showtimeId] = 600;
    $_SESSION['food_seats_' . $showtimeId] = $seats;
    $_SESSION['food_valid_' . $showtimeId] = true;
    $_SESSION['food_created_' . $showtimeId] = time();
    $_SESSION['purchase_token_' . $showtimeId] = $token;
    $_SESSION['purchase_expires_at_' . $showtimeId] = time() + 900;
    
    $pdo->commit();
    echo json_encode(['success' => true]);
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("❌ create_food_session: " . $e->getMessage());
    http_response_code(409);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
?>