<?php
require_once 'config.php';

// Verificar sesión de administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Acceso denegado']);
    exit;
}

// Configurar headers para JSON
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'check_conflict') {
    echo json_encode(['error' => 'Petición inválida']);
    exit;
}

$room_id = intval($_POST['room_id'] ?? 0);
$show_date = $_POST['show_date'] ?? '';
$show_time = $_POST['show_time'] ?? '';
$duration = intval($_POST['duration'] ?? 0);
$exclude_id = isset($_POST['exclude_id']) ? intval($_POST['exclude_id']) : null;

// ============================================
// VALIDAR DATOS DE ENTRADA
// ============================================
if (empty($room_id) || empty($show_date) || empty($show_time) || $duration <= 0) {
    echo json_encode(['error' => 'Datos incompletos']);
    exit;
}

// ✅ Validar formato de fecha
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $show_date)) {
    echo json_encode(['error' => 'Formato de fecha inválido']);
    exit;
}

// ✅ Validar formato de hora (acepta HH:MM o HH:MM:SS)
if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $show_time)) {
    echo json_encode(['error' => 'Formato de hora inválido']);
    exit;
}

// Normalizar a HH:MM:SS
if (strlen($show_time) === 5) {
    $show_time .= ':00';
}

// Validar duración razonable (1 minuto a 12 horas)
if ($duration < 1 || $duration > 720) {
    echo json_encode(['error' => 'Duración inválida']);
    exit;
}

// ============================================
// OBTENER EL NOMBRE DE LA SALA
// ============================================
try {
    $stmt = $pdo->prepare("SELECT name FROM rooms WHERE id = ?");
    $stmt->execute([$room_id]);
    $room = $stmt->fetch();

    if (!$room) {
        echo json_encode(['error' => 'Sala no encontrada']);
        exit;
    }

    $room_name = htmlspecialchars($room['name'], ENT_QUOTES, 'UTF-8');
} catch (PDOException $e) {
    error_log("Error en check_conflict.php: " . $e->getMessage());
    echo json_encode(['error' => 'Error interno del servidor']);
    exit;
}

// ============================================
// VERIFICAR CONFLICTOS
// ============================================
try {
    // ✅ Verificar que la función checkShowtimeConflict existe
    if (!function_exists('checkShowtimeConflict')) {
        error_log("ERROR: checkShowtimeConflict no está definida");
        echo json_encode(['error' => 'Función de verificación no disponible']);
        exit;
    }

    $result = checkShowtimeConflict($pdo, $room_id, $show_date, $show_time, $duration, $exclude_id);

    // Agregar mensaje personalizado según el resultado
    if (!$result['conflict']) {
        if (empty($result['message'])) {
            $result['message'] = '✅ No hay conflictos. La ' . $room_name . ' está disponible en el horario seleccionado.';
        }
    } else {
        if (!empty($result['message'])) {
            $result['message'] = str_replace('Sala Sala', 'Sala', $result['message']);
            $result['message'] = str_replace('sala sala', 'sala', $result['message']);
        }
    }

    if (isset($result['message'])) {
        $result['message'] = htmlspecialchars($result['message'], ENT_QUOTES, 'UTF-8');
    }

    if (isset($result['conflicting_showtime']['title'])) {
        $result['conflicting_showtime']['title'] = htmlspecialchars($result['conflicting_showtime']['title'], ENT_QUOTES, 'UTF-8');
    }
    if (isset($result['conflicting_showtime']['room_name'])) {
        $result['conflicting_showtime']['room_name'] = htmlspecialchars($result['conflicting_showtime']['room_name'], ENT_QUOTES, 'UTF-8');
    }

    unset($result['debug']);

    echo json_encode($result);
    exit;
} catch (Exception $e) {
    error_log("Error verificando conflictos: " . $e->getMessage());
    echo json_encode(['error' => 'Error al verificar conflictos: ' . $e->getMessage()]);
    exit;
}
