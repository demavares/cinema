<?php
require_once 'config.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$showtimeId = isset($_POST['showtime_id']) ? intval($_POST['showtime_id']) : 0;
$seats = isset($_POST['seats']) ? $_POST['seats'] : '';

if ($showtimeId <= 0 || empty($seats)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data']);
    exit;
}

// ============================================
// CREAR SESIÓN DE COMIDA VÁLIDA
// ============================================
$sessionKey = 'food_timeout_' . $showtimeId;
$sessionSeatsKey = 'food_seats_' . $showtimeId;
$sessionValidKey = 'food_valid_' . $showtimeId;
$sessionCreatedKey = 'food_created_' . $showtimeId;

// Limpiar sesiones anteriores
if (isset($_SESSION[$sessionKey])) unset($_SESSION[$sessionKey]);
if (isset($_SESSION[$sessionSeatsKey])) unset($_SESSION[$sessionSeatsKey]);
if (isset($_SESSION[$sessionValidKey])) unset($_SESSION[$sessionValidKey]);
if (isset($_SESSION[$sessionCreatedKey])) unset($_SESSION[$sessionCreatedKey]);

// Guardar timestamp de creación y tiempo inicial
$_SESSION[$sessionKey] = 600; // 10 minutos
$_SESSION[$sessionSeatsKey] = $seats;
$_SESSION[$sessionValidKey] = true;
$_SESSION[$sessionCreatedKey] = time(); // Timestamp de creación

// Guardar en variable de sesión global para debug
$_SESSION['food_debug'] = [
    'created' => date('Y-m-d H:i:s'),
    'showtime_id' => $showtimeId,
    'seats' => $seats,
    'timestamp' => time()
];

// Registrar en la base de datos para tracking
try {
    // Verificar si ya existe una sesión pendiente
    $stmt = $pdo->prepare("
        SELECT id FROM purchases 
        WHERE showtime_id = ? AND user_id = ? AND status = 'pending'
    ");
    $stmt->execute([$showtimeId, $_SESSION['user_id']]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        $stmt = $pdo->prepare("
            UPDATE purchases 
            SET seats = ?, expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE), session_token = ?
            WHERE id = ?
        ");
        $stmt->execute([$seats, bin2hex(random_bytes(32)), $existing['id']]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO purchases (user_id, showtime_id, seats, total_tickets, total_food, total_amount, session_token, expires_at, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 'pending')
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $showtimeId,
            $seats,
            count(explode(',', $seats)),
            0,
            0,
            bin2hex(random_bytes(32))
        ]);
    }
} catch (PDOException $e) {
    error_log("Error al registrar sesión: " . $e->getMessage());
}

// Asegurar que la sesión se guarde
session_write_close();

echo json_encode(['success' => true]);
exit;
?>