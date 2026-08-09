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

error_log("🔍 process_selection.php - Iniciando para showtimeId: $showtimeId");

if ($showtimeId <= 0 || empty($ticketsJson)) {
    error_log("❌ process_selection.php - Datos incompletos: showtimeId=$showtimeId, ticketsJson=" . substr($ticketsJson, 0, 50));
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Datos+incompletos');
    exit;
}

// ============================================
// ✅ CORREGIDO: VALIDACIÓN DE TOKEN MEJORADA
// ============================================

// Verificar si el token existe en sesión
$sessionToken = $_SESSION['purchase_token_' . $showtimeId] ?? '';
error_log("🔍 process_selection.php - Token en sesión: " . (empty($sessionToken) ? 'VACÍO' : substr($sessionToken, 0, 10) . "..."));

// Si no hay token en sesión o expiró, generar uno nuevo
if (empty($sessionToken) || isPurchaseTokenExpired($showtimeId)) {
    error_log("🆕 process_selection.php - Generando nuevo token porque no existe o expiró");
    $sessionToken = generatePurchaseTokenWithTimeout($showtimeId, 900);
    $_SESSION['purchase_token_' . $showtimeId] = $sessionToken;
    error_log("✅ process_selection.php - Nuevo token generado: " . substr($sessionToken, 0, 10) . "...");
}

// Validar el token recibido contra el de sesión
if (empty($token)) {
    error_log("❌ process_selection.php - Token recibido vacío");
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Token+no+encontrado');
    exit;
}

if (!hash_equals($sessionToken, $token)) {
    error_log("❌ process_selection.php - Token no coincide. Recibido: " . substr($token, 0, 10) . "... Esperado: " . substr($sessionToken, 0, 10) . "...");
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Token+inválido');
    exit;
}

// Verificar que el token no haya expirado
if (isPurchaseTokenExpired($showtimeId)) {
    error_log("❌ process_selection.php - Token expirado");
    clearPurchaseSession($showtimeId);
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Token+expirado');
    exit;
}

error_log("✅ process_selection.php - Token validado correctamente");

// ============================================
// VALIDACIÓN DEL JSON DE TICKETS
// ============================================
$ticketsData = json_decode($ticketsJson, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("❌ process_selection.php - JSON inválido: " . json_last_error_msg());
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Datos+inválidos');
    exit;
}

if (!is_array($ticketsData)) {
    error_log("❌ process_selection.php - Estructura JSON inválida");
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Estructura+inválida');
    exit;
}

// Validar y sanitizar datos
$requiredKeys = ['adult', 'child', 'senior'];
foreach ($requiredKeys as $key) {
    if (!isset($ticketsData[$key]) || !is_numeric($ticketsData[$key])) {
        $ticketsData[$key] = 0;
    }
    $ticketsData[$key] = max(0, min(100, intval($ticketsData[$key])));
}

$totalSeats = array_sum($ticketsData);
error_log("📊 process_selection.php - Total asientos: $totalSeats");

if ($totalSeats <= 0) {
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Debes+seleccionar+al+menos+un+boleto');
    exit;
}

if ($totalSeats > 20) {
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Máximo+20+boletos+por+compra');
    exit;
}

// ============================================
// VALIDAR PRECIOS Y DISPONIBILIDAD
// ============================================
$validation = validateAndRecalculatePrices($pdo, $showtimeId, $ticketsData);

if (isset($validation['error'])) {
    error_log("❌ process_selection.php - Error en validación: " . $validation['error']);
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=' . urlencode($validation['error']));
    exit;
}

$totalSeats = $validation['total_seats'];
$subtotal = $validation['subtotal'];
$taxRate = $validation['tax_rate'];
$taxAmount = $validation['tax_amount'];
$totalAmount = $validation['total_amount'];

error_log("📊 process_selection.php - Subtotal: $subtotal, Total: $totalAmount");

// ============================================
// GUARDAR DATOS EN SESIÓN
// ============================================
try {
    $pdo->beginTransaction();
    
    // Verificar disponibilidad de la sala
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
    
    // Contar asientos ocupados
    $stmt = $pdo->prepare("SELECT COUNT(*) as occupied FROM tickets WHERE showtime_id = ?");
    $stmt->execute([$showtimeId]);
    $occupied = $stmt->fetch();
    $occupiedCount = intval($occupied['occupied'] ?? 0);
    
    // Verificar disponibilidad
    $layout = json_decode($showtimeLocked['seat_layout'], true);
    $blockedSeats = $layout['blockedSeats'] ?? [];
    $totalAvailable = ($layout['totalSeats'] ?? 0) - count($blockedSeats) - $occupiedCount;
    
    if ($totalAvailable < $totalSeats) {
        throw new Exception("No hay suficientes asientos disponibles. Disponibles: $totalAvailable, Solicitados: $totalSeats");
    }
    
    // Eliminar compras pendientes antiguas
    $stmt = $pdo->prepare("DELETE FROM purchases WHERE user_id = ? AND showtime_id = ? AND status = 'pending'");
    $stmt->execute([$_SESSION['user_id'], $showtimeId]);
    
    // Guardar datos en sesión (sin regenerar token)
    $_SESSION['ticket_quantities_' . $showtimeId] = $ticketsData;
    $_SESSION['total_seats_' . $showtimeId] = $totalSeats;
    $_SESSION['subtotal_' . $showtimeId] = $subtotal;
    $_SESSION['tax_amount_' . $showtimeId] = $taxAmount;
    $_SESSION['total_amount_' . $showtimeId] = $totalAmount;
    $_SESSION['tax_rate_' . $showtimeId] = $taxRate;
    $_SESSION['showtime_id_' . $showtimeId] = $showtimeId;
    
    // ✅ CORREGIDO: Mantener el token existente, NO regenerar
    $_SESSION['purchase_token_' . $showtimeId] = $sessionToken;
    
    // Limpiar sesiones de comida antiguas
    unset($_SESSION['food_seats_' . $showtimeId]);
    unset($_SESSION['food_timeout_' . $showtimeId]);
    unset($_SESSION['food_valid_' . $showtimeId]);
    unset($_SESSION['food_order_' . $showtimeId]);
    
    $pdo->commit();
    
    error_log("✅ process_selection.php - Datos guardados correctamente, redirigiendo a seats.php");
    header('Location: seats.php?showtime_id=' . $showtimeId);
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("❌ process_selection.php - Error: " . $e->getMessage());
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=' . urlencode($e->getMessage()));
    exit;
}
?>