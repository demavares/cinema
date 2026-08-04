<?php
require_once 'config.php';

// ============================================
// VERIFICAR AUTENTICACIÓN
// ============================================
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ============================================
// ✅ VERIFICAR CSRF
// ============================================
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF token inválido']);
    exit;
}

$showtimeId = isset($_POST['showtime_id']) ? intval($_POST['showtime_id']) : 0;
$foodOrder = isset($_POST['food_order']) ? $_POST['food_order'] : '';
$token = $_POST['purchase_token'] ?? '';

if ($showtimeId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid showtime_id']);
    exit;
}

// ============================================
// ✅ VALIDAR TOKEN DE COMPRA
// ============================================
if (empty($token) || !verifyPurchaseToken($token, $showtimeId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token de compra inválido']);
    exit;
}

// ============================================
// ✅ VALIDAR SESIÓN DE COMIDA
// ============================================
$sessionValidKey = 'food_valid_' . $showtimeId;
if (!isset($_SESSION[$sessionValidKey]) || $_SESSION[$sessionValidKey] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Sesión de comida inválida']);
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
        // ✅ Validar que los items existen en la base de datos
        $foodIds = array_column($decoded, 'id');
        if (!empty($foodIds)) {
            $placeholders = implode(',', array_fill(0, count($foodIds), '?'));
            $stmt = $pdo->prepare("SELECT id, is_active FROM food_items WHERE id IN ($placeholders) AND is_active = 1");
            $stmt->execute($foodIds);
            $validItems = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Filtrar solo items válidos
            $filteredOrder = array_filter($decoded, function($item) use ($validItems) {
                return in_array($item['id'], $validItems) && intval($item['quantity']) > 0;
            });
            
            if (!empty($filteredOrder)) {
                $_SESSION[$sessionFoodKey] = json_encode(array_values($filteredOrder));
            }
        }
    }
}

echo json_encode(['success' => true]);
exit;
?>