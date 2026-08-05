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

if (empty($token) || !verifyPurchaseTokenWithTimeout($token, $showtimeId)) {
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Token+inválido+o+expirado');
    exit;
}

if ($showtimeId <= 0 || empty($ticketsJson)) {
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Datos+incompletos');
    exit;
}

$ticketsData = json_decode($ticketsJson, true);
if (!$ticketsData || !is_array($ticketsData)) {
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Datos+invalidos');
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
    
    $stmt = $pdo->prepare("DELETE FROM purchases WHERE user_id = ? AND showtime_id = ? AND status = 'pending'");
    $stmt->execute([$_SESSION['user_id'], $showtimeId]);
    
    $purchaseToken = generatePurchaseTokenWithTimeout($showtimeId, 900);
    
    $_SESSION['ticket_quantities_' . $showtimeId] = $ticketsData;
    $_SESSION['total_seats_' . $showtimeId] = $totalSeats;
    $_SESSION['subtotal_' . $showtimeId] = $subtotal;
    $_SESSION['tax_amount_' . $showtimeId] = $taxAmount;
    $_SESSION['total_amount_' . $showtimeId] = $totalAmount;
    $_SESSION['tax_rate_' . $showtimeId] = $taxRate;
    $_SESSION['showtime_id_' . $showtimeId] = $showtimeId;
    
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