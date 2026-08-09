<?php
require_once 'config.php';

// ============================================
// ✅ CONFIGURAR RESPUESTA (si es AJAX)
// ============================================
$redirect = isset($_POST['redirect']) ? true : false;

// ============================================
// VERIFICAR AUTENTICACIÓN
// ============================================
if (!isset($_SESSION['user_id'])) {
    if ($redirect) {
        header('Location: login.php');
    } else {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'No autenticado']);
    }
    exit;
}

$userId = $_SESSION['user_id'];

// ============================================
// ✅ VERIFICAR CSRF
// ============================================
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    if ($redirect) {
        die("Error de seguridad: Token CSRF inválido.");
    } else {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Token CSRF inválido']);
    }
    exit;
}

// ============================================
// OBTENER DATOS DEL POST
// ============================================
$showtimeId = isset($_POST['showtime_id']) ? intval($_POST['showtime_id']) : 0;
$foodOrder = isset($_POST['food_order']) ? $_POST['food_order'] : '';
$token = $_POST['purchase_token'] ?? '';

// ============================================
// VALIDAR DATOS BÁSICOS
// ============================================
if ($showtimeId <= 0) {
    if ($redirect) {
        header('Location: food_menu.php?showtime_id=' . $showtimeId . '&error=showtime_invalido');
    } else {
        http_response_code(400);
        header('Content-Type: application/json');
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
        header('Content-Type: application/json');
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
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Sesión de comida inválida']);
    }
    exit;
}

// ============================================
// ✅ NUEVA VALIDACIÓN: VERIFICAR PROPIEDAD DEL SHOWTIME
// El usuario debe tener una compra pendiente o una sesión de asientos
// activa para este showtime específico
// ============================================
try {
    // Verificar que el showtime existe y está activo
    $stmt = $pdo->prepare("
        SELECT id FROM showtimes 
        WHERE id = ? AND is_active = 1
    ");
    $stmt->execute([$showtimeId]);
    if (!$stmt->fetch()) {
        if ($redirect) {
            header('Location: index.php?error=Showtime+no+disponible');
        } else {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Showtime no encontrado o inactivo']);
        }
        exit;
    }

    // ✅ Verificar que el usuario tiene una compra pendiente para este showtime
    // O que los asientos en sesión coinciden con una reserva válida
    $foodSeatsKey = 'food_seats_' . $showtimeId;
    $hasValidSession = false;

    // Opción 1: Verificar compra pendiente en BD
    $stmt = $pdo->prepare("
        SELECT id, seats FROM purchases 
        WHERE user_id = ? AND showtime_id = ? AND status = 'pending'
        LIMIT 1
    ");
    $stmt->execute([$userId, $showtimeId]);
    $pendingPurchase = $stmt->fetch();

    if ($pendingPurchase) {
        $hasValidSession = true;
    }

    // Opción 2: Verificar que hay asientos en la sesión de comida
    if (!$hasValidSession && isset($_SESSION[$foodSeatsKey]) && !empty($_SESSION[$foodSeatsKey])) {
        $hasValidSession = true;
    }

    if (!$hasValidSession) {
        error_log(sprintf(
            "⚠️ Intento de guardar comida sin sesión válida: user_id=%d, showtime_id=%d, IP=%s",
            $userId,
            $showtimeId,
            $_SERVER['REMOTE_ADDR']
        ));
        
        if ($redirect) {
            header('Location: seats.php?showtime_id=' . $showtimeId . '&error=no_active_session');
        } else {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'No tienes una sesión activa para este showtime']);
        }
        exit;
    }

} catch (PDOException $e) {
    error_log("Error validando propiedad de showtime: " . $e->getMessage());
    if ($redirect) {
        header('Location: food_menu.php?showtime_id=' . $showtimeId . '&error=error_interno');
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Error interno del servidor']);
    }
    exit;
}

// ============================================
// ✅ PROCESAR EL PEDIDO DE COMIDA - SOBRESCRIBIR
// ============================================
$sessionFoodKey = 'food_order_' . $showtimeId;

// ✅ ELIMINAR COMPLETAMENTE EL PEDIDO ANTERIOR
unset($_SESSION[$sessionFoodKey]);

// ✅ PROCESAR NUEVO PEDIDO SOLO SI HAY DATOS
if (!empty($foodOrder) && $foodOrder !== '[]') {
    $decoded = json_decode($foodOrder, true);
    
    // ✅ Validar que el JSON sea válido
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON inválido en save_food_order.php: " . json_last_error_msg());
        if ($redirect) {
            header('Location: food_menu.php?showtime_id=' . $showtimeId . '&error=datos_invalidos');
        } else {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Formato de pedido inválido']);
        }
        exit;
    }
    
    if (is_array($decoded) && !empty($decoded)) {
        // ✅ Validar estructura de cada item
        $validStructure = true;
        foreach ($decoded as $item) {
            if (!isset($item['id']) || !isset($item['quantity'])) {
                $validStructure = false;
                break;
            }
            // ✅ Limitar cantidad máxima por item (evitar abusos)
            $item['quantity'] = max(1, min(50, intval($item['quantity'])));
        }
        
        if (!$validStructure) {
            if ($redirect) {
                header('Location: food_menu.php?showtime_id=' . $showtimeId . '&error=estructura_invalida');
            } else {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Estructura de pedido inválida']);
            }
            exit;
        }
        
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
                // ✅ SOBRESCRIBIR (no sumar)
                $_SESSION[$sessionFoodKey] = json_encode(array_values($filteredOrder));
                error_log("✅ Pedido guardado en sesión: " . $_SESSION[$sessionFoodKey]);
            } else {
                error_log("⚠️ No hay items válidos en el pedido");
            }
        }
    }
} else {
    error_log("ℹ️ Pedido vacío o sin datos");
}

// ============================================
// ✅ REDIRIGIR O RESPONDER CON JSON
// ============================================
if ($redirect) {
    header('Location: payment.php?showtime_id=' . $showtimeId);
    exit;
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}
?>