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
$token = $_POST['purchase_token'] ?? '';

if ($showtimeId <= 0 || empty($ticketsJson)) {
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Datos+incompletos');
    exit;
}

// Validar token
if (!verifyPurchaseToken($token, $showtimeId)) {
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Token+inválido+o+expirado');
    exit;
}

$ticketsData = json_decode($ticketsJson, true);
if (!is_array($ticketsData)) {
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Datos+inválidos');
    exit;
}

foreach (['adult', 'child', 'senior'] as $key) {
    $ticketsData[$key] = max(0, min(100, intval($ticketsData[$key] ?? 0)));
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

// Validar precios y disponibilidad
$validation = validateAndRecalculatePrices($pdo, $showtimeId, $ticketsData);
if (isset($validation['error'])) {
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=' . urlencode($validation['error']));
    exit;
}

// Guardar en sesión
$_SESSION['ticket_quantities_' . $showtimeId] = $ticketsData;
$_SESSION['total_seats_' . $showtimeId] = $validation['total_seats'];
$_SESSION['subtotal_' . $showtimeId] = $validation['subtotal'];
$_SESSION['tax_amount_' . $showtimeId] = $validation['tax_amount'];
$_SESSION['total_amount_' . $showtimeId] = $validation['total_amount'];
$_SESSION['tax_rate_' . $showtimeId] = $validation['tax_rate'];
$_SESSION['purchase_token_' . $showtimeId] = $token;

// Limpiar sesiones de comida antiguas
unset($_SESSION['food_seats_' . $showtimeId]);
unset($_SESSION['food_timeout_' . $showtimeId]);
unset($_SESSION['food_valid_' . $showtimeId]);
unset($_SESSION['food_order_' . $showtimeId]);

header('Location: seats.php?showtime_id=' . $showtimeId);
exit;
?>