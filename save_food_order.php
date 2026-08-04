<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$showtimeId = isset($_POST['showtime_id']) ? intval($_POST['showtime_id']) : 0;
$foodOrder = isset($_POST['food_order']) ? $_POST['food_order'] : '';

if ($showtimeId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid showtime_id']);
    exit;
}

$sessionFoodKey = 'food_order_' . $showtimeId;

// Limpiar pedido anterior
if (isset($_SESSION[$sessionFoodKey])) {
    unset($_SESSION[$sessionFoodKey]);
}

if (!empty($foodOrder) && $foodOrder !== '[]') {
    // Validar que sea un JSON válido
    $decoded = json_decode($foodOrder, true);
    if ($decoded !== null && is_array($decoded)) {
        $_SESSION[$sessionFoodKey] = $foodOrder;
    }
}

// ⬇️ NUEVO: NO redirigir, solo devolver JSON
echo json_encode(['success' => true]);
exit;
?>