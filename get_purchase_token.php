<?php
// ============================================
// get_purchase_token.php - Obtener token de compra
// ============================================
require_once 'config.php';

// Siempre devolver JSON
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

error_reporting(E_ALL);
ini_set('display_errors', 0);

// ============================================
// VERIFICAR AUTENTICACIÓN
// ============================================
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

// ============================================
// GENERAR O RECUPERAR TOKEN
// ============================================
try {
    // ✅ Verificar si el token existe y no ha expirado
    if (!isset($_SESSION['purchase_token_' . $showtimeId]) || isPurchaseTokenExpired($showtimeId)) {
        error_log("🔄 get_purchase_token.php: Generando nuevo token para showtime $showtimeId");
        $token = generatePurchaseTokenWithTimeout($showtimeId, 900);
        $_SESSION['purchase_token_' . $showtimeId] = $token;
    } else {
        $token = $_SESSION['purchase_token_' . $showtimeId];
        error_log("✅ get_purchase_token.php: Token existente para showtime $showtimeId: " . substr($token, 0, 10) . "...");
    }

    echo json_encode([
        'success' => true,
        'token' => $token,
        'timeLeft' => getPurchaseTokenTimeLeft($showtimeId)
    ]);
    exit;
} catch (Exception $e) {
    error_log("❌ get_purchase_token.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
    exit;
}
