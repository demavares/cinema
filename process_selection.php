<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    die("Error de seguridad: Token CSRF inválido.");
}

$showtimeId = intval($_POST['showtime_id'] ?? 0);
$ticketsJson = $_POST['tickets'] ?? '';
$totalSeatsFromClient = intval($_POST['total_seats'] ?? 0);
$token = $_POST['purchase_token'] ?? '';

if ($showtimeId <= 0 || empty($ticketsJson)) {
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Datos+incompletos');
    exit;
}

// ✅ CORREGIDO: Validar token O generar uno nuevo si no existe
if (empty($token) || !verifyPurchaseTokenWithTimeout($token, $showtimeId)) {
    // Si no hay token o expiró, generar uno nuevo
    $token = generatePurchaseTokenWithTimeout($showtimeId, 900);
    $_SESSION['purchase_token_' . $showtimeId] = $token;
}

// ✅ VALIDACIÓN COMPLETA DEL JSON
$ticketsData = json_decode($ticketsJson, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON inválido en process_selection.php: " . json_last_error_msg());
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Datos+inválidos');
    exit;
}

if (!is_array($ticketsData)) {
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Estructura+inválida');
    exit;
}

$requiredKeys = ['adult', 'child', 'senior'];
foreach ($requiredKeys as $key) {
    if (!isset($ticketsData[$key]) || !is_numeric($ticketsData[$key])) {
        $ticketsData[$key] = 0;
    }
    $ticketsData[$key] = max(0, min(100, intval($ticketsData[$key])));
}

$totalSeats = array_sum($ticketsData);
if ($totalSeats <= 0) {
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Debes+seleccionar+al+menos+un+boleto');
    exit;
}

if ($totalSeats > 20) {
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Máximo+20+boletos+por+compra');
    exit;
}

$validation = validateAndRecalculatePrices($pdo, $showtimeId, $ticketsData);

if (isset($validation['error'])) {
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=' . urlencode($validation['error']));
    exit;
}

$totalSeats = $validation['total_seats'];
$subtotal = $validation['subtotal'];
$taxRate = $validation['tax_rate'];
$taxAmount = $validation['tax_amount'];
$totalAmount = $validation['total_amount'];

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
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as occupied FROM tickets WHERE showtime_id = ?");
    $stmt->execute([$showtimeId]);
    $occupied = $stmt->fetch();
    $occupiedCount = intval($occupied['occupied'] ?? 0);
    
    $layout = json_decode($showtimeLocked['seat_layout'], true);
    $blockedSeats = $layout['blockedSeats'] ?? [];
    $totalAvailable = ($layout['totalSeats'] ?? 0) - count($blockedSeats) - $occupiedCount;
    
    if ($totalAvailable < $totalSeats) {
        throw new Exception("No hay suficientes asientos disponibles. Disponibles: $totalAvailable, Solicitados: $totalSeats");
    }
    
    // ✅ CORREGIDO: NO eliminar la compra pendiente, actualizarla o mantenerla
    $stmt = $pdo->prepare("SELECT id FROM purchases WHERE user_id = ? AND showtime_id = ? AND status = 'pending'");
    $stmt->execute([$_SESSION['user_id'], $showtimeId]);
    $existingPurchase = $stmt->fetch();
    
    if (!$existingPurchase) {
        // Solo crear una nueva si no existe
        $stmt = $pdo->prepare("
            INSERT INTO purchases (user_id, showtime_id, seats, total_tickets, total_food, total_amount, session_token, expires_at, status) 
            VALUES (?, ?, '', ?, 0, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 'pending')
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $showtimeId,
            $totalSeats,
            $totalAmount,
            bin2hex(random_bytes(32))
        ]);
    }
    
    $_SESSION['ticket_quantities_' . $showtimeId] = $ticketsData;
    $_SESSION['total_seats_' . $showtimeId] = $totalSeats;
    $_SESSION['subtotal_' . $showtimeId] = $subtotal;
    $_SESSION['tax_amount_' . $showtimeId] = $taxAmount;
    $_SESSION['total_amount_' . $showtimeId] = $totalAmount;
    $_SESSION['tax_rate_' . $showtimeId] = $taxRate;
    $_SESSION['showtime_id_' . $showtimeId] = $showtimeId;
    
    // ✅ CORREGIDO: Mantener el token existente
    $_SESSION['purchase_token_' . $showtimeId] = $token;
    
    // Limpiar sesiones de comida antiguas
    unset($_SESSION['food_seats_' . $showtimeId]);
    unset($_SESSION['food_timeout_' . $showtimeId]);
    unset($_SESSION['food_valid_' . $showtimeId]);
    unset($_SESSION['food_order_' . $showtimeId]);
    
    $pdo->commit();
    
    header('Location: seats.php?showtime_id=' . $showtimeId);
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=' . urlencode($e->getMessage()));
    exit;
}
?>