<?php
require_once 'config.php';

// Verificar sesión de administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
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

// Validar datos
if (empty($room_id) || empty($show_date) || empty($show_time) || $duration <= 0) {
    echo json_encode(['error' => 'Datos incompletos']);
    exit;
}

// Obtener el nombre de la sala
$stmt = $pdo->prepare("SELECT name FROM rooms WHERE id = ?");
$stmt->execute([$room_id]);
$room = $stmt->fetch();
$room_name = $room ? $room['name'] : 'Sala ' . $room_id;

$result = checkShowtimeConflict($pdo, $room_id, $show_date, $show_time, $duration, $exclude_id);

// Agregar mensaje personalizado según el resultado
if (!$result['conflict']) {
    // Mensaje cuando no hay conflicto
    if (empty($result['message'])) {
        $result['message'] = '✅ No hay conflictos. La ' . $room_name . ' está disponible en el horario seleccionado.';
    }
} else {
    // Si hay conflicto, limpiar mensaje para evitar duplicación de "Sala"
    if (!empty($result['message'])) {
        $result['message'] = str_replace('Sala Sala', 'Sala', $result['message']);
        $result['message'] = str_replace('sala sala', 'sala', $result['message']);
    }
}

// No enviar información de depuración
unset($result['debug']);

echo json_encode($result);
exit;
?>