<?php
require_once 'config.php';

// ============================================
// VERIFICAR AUTENTICACIÓN
// ============================================
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Verificar CSRF
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    die("Error de seguridad: Token inválido.");
}

// ============================================
// OBTENER DATOS DEL POST
// ============================================
$showtimeId = intval($_POST['showtime_id'] ?? 0);
$ticketsJson = $_POST['tickets'] ?? '';
$totalSeats = intval($_POST['total_seats'] ?? 0);
$subtotal = floatval($_POST['subtotal'] ?? 0);
$taxAmount = floatval($_POST['tax_amount'] ?? 0);
$totalAmount = floatval($_POST['total_amount'] ?? 0);

// ============================================
// LOGS DE DEBUG
// ============================================
error_log("=== PROCESS_SELECTION START ===");
error_log("showtime_id: " . $showtimeId);
error_log("tickets: " . $ticketsJson);
error_log("total_seats: " . $totalSeats);
error_log("subtotal: " . $subtotal);
error_log("tax_amount: " . $taxAmount);
error_log("total_amount: " . $totalAmount);

// ============================================
// VALIDACIONES
// ============================================
if ($showtimeId <= 0 || empty($ticketsJson) || $totalSeats <= 0) {
    error_log("ERROR: Datos incompletos");
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Datos+incompletos');
    exit;
}

// Decodificar tickets
$tickets = json_decode($ticketsJson, true);
if (!$tickets || !is_array($tickets)) {
    error_log("ERROR: Tickets inválidos");
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Datos+invalidos');
    exit;
}

// Verificar que haya al menos un boleto
$total = ($tickets['adult'] ?? 0) + ($tickets['child'] ?? 0) + ($tickets['senior'] ?? 0);
if ($total != $totalSeats) {
    error_log("ERROR: No coincide el número de boletos. Esperado: $totalSeats, Recibido: $total");
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=No+coincide+el+número+de+boletos');
    exit;
}

if ($total == 0) {
    error_log("ERROR: No hay boletos seleccionados");
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Selecciona+al+menos+un+boleto');
    exit;
}

// ============================================
// GUARDAR EN SESIÓN
// ============================================
$_SESSION['ticket_quantities_' . $showtimeId] = $tickets;
$_SESSION['total_seats_' . $showtimeId] = $totalSeats;
$_SESSION['subtotal_' . $showtimeId] = $subtotal;
$_SESSION['tax_amount_' . $showtimeId] = $taxAmount;
$_SESSION['total_amount_' . $showtimeId] = $totalAmount;

error_log("=== DATOS GUARDADOS EN SESIÓN ===");
error_log("ticket_quantities_" . $showtimeId . ": " . print_r($tickets, true));
error_log("total_seats_" . $showtimeId . ": " . $totalSeats);
error_log("subtotal_" . $showtimeId . ": " . $subtotal);
error_log("tax_amount_" . $showtimeId . ": " . $taxAmount);
error_log("total_amount_" . $showtimeId . ": " . $totalAmount);

// ============================================
// ✅ LIMPIAR ASIENTOS GUARDADOS EN SESSIONSTORAGE
// (el cliente los limpiará, pero aseguramos también desde el servidor)
// ============================================
// Los asientos se guardan en sessionStorage (cliente), no en sesión PHP
// Por lo tanto, el cliente debe limpiarlos al cambiar la selección

// ============================================
// REDIRIGIR A SEATS.PHP (SIN DATOS EN URL)
// ============================================
header('Location: seats.php?showtime_id=' . $showtimeId);
exit;
?>