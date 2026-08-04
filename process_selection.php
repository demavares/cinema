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
$totalSeatsFromClient = intval($_POST['total_seats'] ?? 0);
$token = $_POST['purchase_token'] ?? '';

// ============================================
// ✅ VALIDAR TOKEN DE COMPRA
// ============================================
if (empty($token) || !verifyPurchaseToken($token, $showtimeId)) {
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Token+inválido');
    exit;
}

// ============================================
// ✅ VALIDACIÓN EN EL SERVIDOR - RECALCULAR PRECIOS
// ============================================
if ($showtimeId <= 0 || empty($ticketsJson)) {
    error_log("ERROR: Datos incompletos en process_selection");
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Datos+incompletos');
    exit;
}

// Decodificar tickets del cliente
$ticketsData = json_decode($ticketsJson, true);
if (!$ticketsData || !is_array($ticketsData)) {
    error_log("ERROR: Tickets inválidos en process_selection");
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Datos+invalidos');
    exit;
}

// ✅ RECALCULAR PRECIOS EN EL SERVIDOR (IGNORAR VALORES DEL CLIENTE)
$validation = validateAndRecalculatePrices($pdo, $showtimeId, $ticketsData);

if (isset($validation['error'])) {
    error_log("ERROR de validación: " . $validation['error']);
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=' . urlencode($validation['error']));
    exit;
}

// Usar los valores recalculados por el servidor
$totalSeats = $validation['total_seats'];
$subtotal = $validation['subtotal'];
$taxRate = $validation['tax_rate'];
$taxAmount = $validation['tax_amount'];
$totalAmount = $validation['total_amount'];

// Verificar que coincida con lo enviado por el cliente (opcional, pero buena práctica)
if ($totalSeats != $totalSeatsFromClient) {
    error_log("ADVERTENCIA: Discrepancia en número de asientos. Cliente: $totalSeatsFromClient, Servidor: $totalSeats");
    // Usar el valor del servidor
}

// ============================================
// ✅ VERIFICAR DISPONIBILIDAD CON BLOQUEO (FOR UPDATE)
// ============================================
try {
    $pdo->beginTransaction();
    
    // Bloquear la fila del showtime para lectura/escritura
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
    $stmt = $pdo->prepare("SELECT COUNT(*) as occupied FROM tickets WHERE showtime_id = ?");
    $stmt->execute([$showtimeId]);
    $occupied = $stmt->fetch();
    $occupiedCount = intval($occupied['occupied'] ?? 0);
    
    // Calcular disponibilidad real
    $layout = json_decode($showtimeLocked['seat_layout'], true);
    $blockedSeats = $layout['blockedSeats'] ?? [];
    $totalAvailable = ($layout['totalSeats'] ?? 0) - count($blockedSeats) - $occupiedCount;
    
    if ($totalAvailable < $totalSeats) {
        throw new Exception("No hay suficientes asientos disponibles. Disponibles: $totalAvailable, Solicitados: $totalSeats");
    }
    
    // ============================================
    // ELIMINAR COMPRAS PENDIENTES ANTERIORES
    // ============================================
    $stmt = $pdo->prepare("
        DELETE FROM purchases
        WHERE user_id = ? AND showtime_id = ? AND status = 'pending'
    ");
    $stmt->execute([$_SESSION['user_id'], $showtimeId]);
    
    // ============================================
    // ✅ GENERAR TOKEN DE COMPRA ÚNICO
    // ============================================
    $purchaseToken = generatePurchaseToken();
    $_SESSION['purchase_token_' . $showtimeId] = $purchaseToken;
    
    // ============================================
    // GUARDAR EN SESIÓN (SEGURO)
    // ============================================
    $_SESSION['ticket_quantities_' . $showtimeId] = $ticketsData;
    $_SESSION['total_seats_' . $showtimeId] = $totalSeats;
    $_SESSION['subtotal_' . $showtimeId] = $subtotal;
    $_SESSION['tax_amount_' . $showtimeId] = $taxAmount;
    $_SESSION['total_amount_' . $showtimeId] = $totalAmount;
    $_SESSION['tax_rate_' . $showtimeId] = $taxRate;
    
    // Limpiar asientos guardados
    unset($_SESSION['food_seats_' . $showtimeId]);
    unset($_SESSION['food_timeout_' . $showtimeId]);
    unset($_SESSION['food_valid_' . $showtimeId]);
    unset($_SESSION['food_order_' . $showtimeId]);
    
    // Commit de la transacción
    $pdo->commit();
    
    error_log("=== PROCESO SELECTION EXITOSO ===");
    error_log("showtime_id: $showtimeId");
    error_log("total_seats: $totalSeats");
    error_log("subtotal: $subtotal");
    error_log("total_amount: $totalAmount");
    
    // ============================================
    // REDIRIGIR A SEATS.PHP CON TOKEN
    // ============================================
    header('Location: seats.php?showtime_id=' . $showtimeId . '&token=' . $purchaseToken);
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("ERROR en process_selection: " . $e->getMessage());
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=' . urlencode($e->getMessage()));
    exit;
}
?>