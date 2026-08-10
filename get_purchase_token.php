<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$showtimeId = isset($_GET['showtime_id']) ? intval($_GET['showtime_id']) : 0;
if ($showtimeId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'showtime_id inválido']);
    exit;
}

// Verificar token o generar uno nuevo
if (!isset($_SESSION['purchase_token_' . $showtimeId]) || !verifyPurchaseToken($_SESSION['purchase_token_' . $showtimeId], $showtimeId)) {
    $token = generatePurchaseTokenWithTimeout($showtimeId, 900);
    $_SESSION['purchase_token_' . $showtimeId'] = $token;
} else {
    $token = $_SESSION['purchase_token_' . $showtimeId];
}

echo json_encode([
    'success' => true,
    'token' => $token,
    'timeLeft' => getPurchaseTokenTimeLeft($showtimeId)
]);
exit;
?>