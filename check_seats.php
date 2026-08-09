<?php
require_once 'config.php';

// ============================================
// CONFIGURAR RESPUESTA JSON
// ============================================
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// ============================================
// VERIFICAR AUTENTICACIÓN
// ============================================
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'No autenticado', 'occupied' => []]);
    exit;
}

// ============================================
// VERIFICAR PARÁMETROS
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
    // VERIFICAR QUE EL SHOWTIME EXISTE Y ESTÁ ACTIVO
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

    if ($showtime['is_active'] == 0) {
        echo json_encode(['occupied' => [], 'inactive' => true]);
        exit;
    }

    // ============================================
    // VERIFICAR QUE EL USUARIO TENGA UNA SESIÓN ACTIVA
    // ============================================
    $hasActiveSession = false;

    if (isset($_SESSION['purchase_token_' . $showtimeId])) {
        $hasActiveSession = true;
    }

    if (isset($_SESSION['food_valid_' . $showtimeId]) && $_SESSION['food_valid_' . $showtimeId] === true) {
        $hasActiveSession = true;
    }

    if (isset($_SESSION['ticket_quantities_' . $showtimeId])) {
        $hasActiveSession = true;
    }

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

    // Si no hay sesión activa, permitir la consulta pero solo para mostrar asientos ocupados
    // No bloqueamos la consulta, solo devolvemos los asientos ocupados

    // ============================================
    // OBTENER ASIENTOS OCUPADOS (CORREGIDO - INCLUYE ASIENTOS DEL USUARIO CON COMPRAS COMPLETADAS)
    // ============================================

    // 1. Asientos de compras completadas (tickets definitivos) - TODOS los usuarios
    $stmtCompleted = $pdo->prepare("
        SELECT t.seat_code, p.user_id
        FROM tickets t
        JOIN purchases p ON t.user_id = p.user_id AND t.showtime_id = p.showtime_id
        WHERE t.showtime_id = ? 
        AND p.status = 'completed'
        GROUP BY t.seat_code
    ");
    $stmtCompleted->execute([$showtimeId]);
    $completedSeatsData = $stmtCompleted->fetchAll(PDO::FETCH_ASSOC);

    $occupiedSeats = [];
    $userCompletedSeats = [];
    foreach ($completedSeatsData as $row) {
        $occupiedSeats[] = $row['seat_code'];
        if ($row['user_id'] == $_SESSION['user_id']) {
            $userCompletedSeats[] = $row['seat_code'];
        }
    }

    // 2. Asientos de compras pendientes de OTROS usuarios
    $stmtPending = $pdo->prepare("
        SELECT seats 
        FROM purchases 
        WHERE showtime_id = ? 
        AND status = 'pending' 
        AND user_id != ?
    ");
    $stmtPending->execute([$showtimeId, $_SESSION['user_id']]);
    $pendingPurchases = $stmtPending->fetchAll(PDO::FETCH_COLUMN);

    foreach ($pendingPurchases as $seatsString) {
        if (!empty($seatsString)) {
            $seatsArray = array_map('trim', explode(',', $seatsString));
            $seatsArray = array_map(function($seat) {
                return str_replace('♿', '', $seat);
            }, $seatsArray);
            $occupiedSeats = array_merge($occupiedSeats, $seatsArray);
        }
    }

    // Eliminar duplicados
    $occupiedSeats = array_unique($occupiedSeats);

    // ============================================
    // OBTENER ASIENTOS DEL USUARIO (SOLO PENDIENTES)
    // ============================================
    $userSeats = [];
    $stmtUser = $pdo->prepare("
        SELECT t.seat_code 
        FROM tickets t
        JOIN purchases p ON t.user_id = p.user_id AND t.showtime_id = p.showtime_id
        WHERE t.showtime_id = ? 
        AND t.user_id = ? 
        AND p.status = 'pending'
        AND p.expires_at > NOW()
    ");
    $stmtUser->execute([$showtimeId, $_SESSION['user_id']]);
    $userSeats = $stmtUser->fetchAll(PDO::FETCH_COLUMN);

    // También verificar purchases pendientes del usuario
    $stmtUserPending = $pdo->prepare("
        SELECT seats 
        FROM purchases 
        WHERE showtime_id = ? 
        AND user_id = ? 
        AND status = 'pending'
        AND expires_at > NOW()
    ");
    $stmtUserPending->execute([$showtimeId, $_SESSION['user_id']]);
    $userPendingPurchases = $stmtUserPending->fetchAll(PDO::FETCH_COLUMN);

    foreach ($userPendingPurchases as $seatsString) {
        if (!empty($seatsString)) {
            $seatsArray = array_map('trim', explode(',', $seatsString));
            $seatsArray = array_map(function($seat) {
                return str_replace('♿', '', $seat);
            }, $seatsArray);
            $userSeats = array_merge($userSeats, $seatsArray);
        }
    }
    $userSeats = array_unique($userSeats);

    // ============================================
    // OBTENER ASIENTOS BLOQUEADOS POR DISEÑO
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
    // LOG DE DEPURACIÓN
    // ============================================
    error_log("🔍 check_seats.php - Ocupados: " . implode(', ', $occupiedSeats));
    error_log("🔍 check_seats.php - Usuario pendientes: " . implode(', ', $userSeats));
    error_log("🔍 check_seats.php - Usuario completados: " . implode(', ', $userCompletedSeats));

    // ============================================
    // RESPONDER CON DATOS
    // ============================================
    echo json_encode([
        'success' => true,
        'occupied' => $occupiedSeats,
        'blocked' => $blockedSeats,
        'user_seats' => $userSeats,
        'user_completed_seats' => $userCompletedSeats,
        'count' => count($occupiedSeats),
        'timestamp' => time()
    ]);
    exit;

} catch (PDOException $e) {
    error_log("Error en check_seats.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor: ' . $e->getMessage(), 'occupied' => []]);
    exit;
} catch (Exception $e) {
    error_log("Error inesperado en check_seats.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error inesperado: ' . $e->getMessage(), 'occupied' => []]);
    exit;
}
?>