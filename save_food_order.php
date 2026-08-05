<?php
require_once 'config.php';

// ============================================
// VERIFICAR AUTENTICACIÓN
// ============================================
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// ============================================
// ✅ VERIFICAR CSRF
// ============================================
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    die("Error de seguridad: Token CSRF inválido.");
}

// ============================================
// OBTENER DATOS DEL POST
// ============================================
$showtimeId = isset($_POST['showtime_id']) ? intval($_POST['showtime_id']) : 0;
$foodOrder = isset($_POST['food_order']) ? $_POST['food_order'] : '';
$token = $_POST['purchase_token'] ?? '';
$redirect = isset($_POST['redirect']) ? true : false;

// ============================================
// VALIDAR DATOS BÁSICOS
// ============================================
if ($showtimeId <= 0) {
    if ($redirect) {
        header('Location: food_menu.php?showtime_id=' . $showtimeId . '&error=showtime_invalido');
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid showtime_id']);
    }
    exit;
}

// ============================================
// ✅ VALIDAR TOKEN DE COMPRA
// ============================================
if (empty($token) || !verifyPurchaseTokenWithTimeout($token, $showtimeId)) {
    if ($redirect) {
        header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Token+inválido+o+expirado');
    } else {
        http_response_code(403);
        echo json_encode(['error' => 'Token de compra inválido o expirado']);
    }
    exit;
}

// ============================================
// ✅ VALIDAR SESIÓN DE COMIDA
// ============================================
$sessionValidKey = 'food_valid_' . $showtimeId;
if (!isset($_SESSION[$sessionValidKey]) || $_SESSION[$sessionValidKey] !== true) {
    if ($redirect) {
        header('Location: seats.php?showtime_id=' . $showtimeId . '&error=session_expired');
    } else {
        http_response_code(403);
        echo json_encode(['error' => 'Sesión de comida inválida']);
    }
    exit;
}

// ============================================
// ✅ PROCESAR EL PEDIDO DE COMIDA
// ============================================
$sessionFoodKey = 'food_order_' . $showtimeId;

// Limpiar pedido anterior
if (isset($_SESSION[$sessionFoodKey])) {
    unset($_SESSION[$sessionFoodKey]);
}

if (!empty($foodOrder) && $foodOrder !== '[]') {
    $decoded = json_decode($foodOrder, true);
    if ($decoded !== null && is_array($decoded)) {
        $foodIds = array_column($decoded, 'id');
        if (!empty($foodIds)) {
            $placeholders = implode(',', array_fill(0, count($foodIds), '?'));
            $stmt = $pdo->prepare("SELECT id, is_active FROM food_items WHERE id IN ($placeholders) AND is_active = 1");
            $stmt->execute($foodIds);
            $validItems = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $filteredOrder = array_filter($decoded, function($item) use ($validItems) {
                return in_array($item['id'], $validItems) && intval($item['quantity']) > 0;
            });
            
            if (!empty($filteredOrder)) {
                $_SESSION[$sessionFoodKey] = json_encode(array_values($filteredOrder));
            }
        }
    }
}

// ============================================
// ✅ REDIRIGIR O RESPONDER CON JSON
// ============================================
if ($redirect) {
    // Envío tradicional - redirigir a payment.php
    header('Location: payment.php?showtime_id=' . $showtimeId);
    exit;
} else {
    // Petición AJAX - devolver JSON
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}
?>