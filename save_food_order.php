<?php
require_once 'config.php';

$redirect = isset($_POST['redirect']) ? true : false;

if (!isset($_SESSION['user_id'])) {
    if ($redirect) { header('Location: login.php'); exit; }
    else { http_response_code(403); echo json_encode(['error' => 'No autenticado']); exit; }
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    if ($redirect) { die("Error de seguridad: Token CSRF inválido."); }
    else { http_response_code(403); echo json_encode(['error' => 'Token CSRF inválido']); exit; }
}

$showtimeId = isset($_POST['showtime_id']) ? intval($_POST['showtime_id']) : 0;
$foodOrder = $_POST['food_order'] ?? '';
$token = $_POST['purchase_token'] ?? '';

if ($showtimeId <= 0) {
    if ($redirect) { header('Location: food_menu.php?showtime_id=' . $showtimeId . '&error=showtime_invalido'); exit; }
    else { http_response_code(400); echo json_encode(['error' => 'Invalid showtime_id']); exit; }
}

// Verificar sesión de comida
$sessionValidKey = 'food_valid_' . $showtimeId;
if (!isset($_SESSION[$sessionValidKey]) || $_SESSION[$sessionValidKey] !== true) {
    if ($redirect) { header('Location: seats.php?showtime_id=' . $showtimeId . '&error=session_expired'); exit; }
    else { http_response_code(403); echo json_encode(['error' => 'Sesión de comida inválida']); exit; }
}

// Verificar token
if (!verifyPurchaseToken($token, $showtimeId)) {
    if ($redirect) { header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Token+inválido+o+expirado'); exit; }
    else { http_response_code(403); echo json_encode(['error' => 'Token inválido']); exit; }
}

try {
    // Verificar que el showtime existe
    $stmt = $pdo->prepare("SELECT id FROM showtimes WHERE id = ? AND is_active = 1");
    $stmt->execute([$showtimeId]);
    if (!$stmt->fetch()) {
        if ($redirect) { header('Location: index.php?error=Showtime+no+disponible'); exit; }
        else { http_response_code(404); echo json_encode(['error' => 'Showtime no encontrado']); exit; }
    }
    
    // Verificar compra pendiente
    $stmt = $pdo->prepare("SELECT id FROM purchases WHERE user_id = ? AND showtime_id = ? AND status = 'pending' LIMIT 1");
    $stmt->execute([$_SESSION['user_id'], $showtimeId]);
    if (!$stmt->fetch()) {
        if ($redirect) { header('Location: seats.php?showtime_id=' . $showtimeId . '&error=no_active_session'); exit; }
        else { http_response_code(403); echo json_encode(['error' => 'No tienes una sesión activa']); exit; }
    }
    
    // Guardar pedido de comida
    $sessionFoodKey = 'food_order_' . $showtimeId;
    unset($_SESSION[$sessionFoodKey]);
    
    if (!empty($foodOrder) && $foodOrder !== '[]') {
        $decoded = json_decode($foodOrder, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && !empty($decoded)) {
            $filtered = [];
            foreach ($decoded as $item) {
                if (isset($item['id']) && isset($item['quantity']) && intval($item['quantity']) > 0) {
                    $filtered[] = ['id' => intval($item['id']), 'quantity' => intval($item['quantity'])];
                }
            }
            if (!empty($filtered)) {
                $_SESSION[$sessionFoodKey] = json_encode($filtered);
            }
        }
    }
    
    if ($redirect) {
        header('Location: payment.php?showtime_id=' . $showtimeId);
        exit;
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
    
} catch (Exception $e) {
    error_log("❌ save_food_order: " . $e->getMessage());
    if ($redirect) {
        header('Location: food_menu.php?showtime_id=' . $showtimeId . '&error=error_interno');
        exit;
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Error interno del servidor']);
        exit;
    }
}
?>