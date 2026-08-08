<?php
require_once 'config.php';

// ============================================
// ✅ CONFIGURAR RESPUESTA JSON
// ============================================
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// ============================================
// ✅ VERIFICAR AUTENTICACIÓN
// ============================================
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'No autenticado', 'occupied' => []]);
    exit;
}

// ============================================
// ✅ VERIFICAR PARÁMETROS
// ============================================
if (!isset($_GET['showtime_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetro showtime_id requerido', 'occupied' => []]);
    exit;
}

$showtimeId = filter_var($_GET['showtime_id'], FILTER_VALIDATE_INT);

if ($showtimeId === false || $showtimeId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'showtime_id inválido', 'occupied' => []]);
    exit;
}

try {
    // ============================================
    // ✅ VERIFICAR QUE EL SHOWTIME EXISTE Y ESTÁ ACTIVO
    // ============================================
    $stmt = $pdo->prepare("
        SELECT s.id, s.is_active, s.show_date, s.show_time, m.duration
        FROM showtimes s
        JOIN movies m ON s.movie_id = m.id
        WHERE s.id = ?
    ");
    $stmt->execute([$showtimeId]);
    $showtime = $stmt->fetch();

    if (!$showtime) {
        http_response_code(404);
        echo json_encode(['error' => 'Showtime no encontrado', 'occupied' => []]);
        exit;
    }

    // ✅ Si el showtime no está activo, devolver vacío
    if ($showtime['is_active'] == 0) {
        echo json_encode(['occupied' => [], 'inactive' => true]);
        exit;
    }

    // ============================================
    // ✅ VERIFICAR QUE EL USUARIO TIENE UNA SESIÓN ACTIVA
    // Solo permitir consulta si el usuario está en medio de una compra
    // ============================================
    $hasActiveSession = false;

    // Verificar si tiene una sesión de compra activa para este showtime
    if (isset($_SESSION['purchase_token_' . $showtimeId])) {
        $hasActiveSession = true;
    }

    // Verificar si tiene una sesión de comida activa para este showtime
    if (isset($_SESSION['food_valid_' . $showtimeId]) && $_SESSION['food_valid_' . $showtimeId] === true) {
        $hasActiveSession = true;
    }

    // Verificar si tiene ticket_quantities en sesión (está en proceso de compra)
    if (isset($_SESSION['ticket_quantities_' . $showtimeId])) {
        $hasActiveSession = true;
    }

    // Verificar si tiene una compra completada para este showtime
    if (!$hasActiveSession) {
        $stmt = $pdo->prepare("
            SELECT id FROM purchases 
            WHERE user_id = ? AND showtime_id = ? AND status = 'completed'
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['user_id'], $showtimeId]);
        if ($stmt->fetch()) {
            $hasActiveSession = true;
        }
    }

    // ✅ Si no tiene sesión activa, devolver error
    if (!$hasActiveSession) {
        http_response_code(403);
        echo json_encode(['error' => 'No tienes una sesión activa para este showtime', 'occupied' => []]);
        exit;
    }

    // ============================================
    // ✅ OBTENER ASIENTOS OCUPADOS
    // ============================================
    $stmt = $pdo->prepare("
        SELECT seat_code 
        FROM tickets 
        WHERE showtime_id = ?
        ORDER BY seat_code ASC
    ");
    $stmt->execute([$showtimeId]);
    $occupied = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // ============================================
    // ✅ OBTENER ASIENTOS BLOQUEADOS (opcional, para optimización)
    // ============================================
    $blockedSeats = [];
    $stmt = $pdo->prepare("
        SELECT r.seat_layout
        FROM showtimes s
        JOIN rooms r ON s.room_id = r.id
        WHERE s.id = ?
    ");
    $stmt->execute([$showtimeId]);
    $roomData = $stmt->fetch();

    if ($roomData && !empty($roomData['seat_layout'])) {
        $layout = json_decode($roomData['seat_layout'], true);
        $blockedSeats = $layout['blockedSeats'] ?? [];
    }

    // ============================================
    // ✅ RESPONDER CON DATOS
    // ============================================
    echo json_encode([
        'success' => true,
        'occupied' => $occupied,
        'blocked' => $blockedSeats,
        'count' => count($occupied),
        'timestamp' => time()
    ]);
    exit;

} catch (PDOException $e) {
    error_log("Error en check_seats.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor', 'occupied' => []]);
    exit;
} catch (Exception $e) {
    error_log("Error inesperado en check_seats.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error inesperado', 'occupied' => []]);
    exit;
}
?>