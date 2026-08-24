<?php
require_once 'config.php';

// ============================================
// REGISTRAR LOGOUT ANTES DE DESTRUIR LA SESIÓN
// ============================================
$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['user_name'] ?? 'Desconocido';
$userEmail = $_SESSION['user_email'] ?? 'N/A';

// Log de logout (solo si hay usuario)
if ($userId) {
    error_log(sprintf(
        "👋 Logout: user_id=%d, name=%s, email=%s, IP=%s",
        $userId,
        $userName,
        $userEmail,
        $_SERVER['REMOTE_ADDR']
    ));
}

// ============================================
// LIMPIAR SESIONES DE COMPRA Y COMIDA (AUTOCONTENIDO)
// ============================================
$sessionPrefixes = ['food_', 'purchase_', 'ticket_', 'total_', 'subtotal_', 'tax_', 'payment_'];

foreach ($_SESSION as $key => $value) {
    foreach ($sessionPrefixes as $prefix) {
        if (strpos($key, $prefix) === 0) {
            unset($_SESSION[$key]);
            break;
        }
    }
}

// ============================================
// ACTUALIZAR COMPRAS PENDIENTES DEL USUARIO
// ============================================
if ($userId) {
    try {
        // Marcar compras pendientes expiradas
        $stmt = $pdo->prepare("
            UPDATE purchases 
            SET status = 'expired' 
            WHERE user_id = ? 
            AND status = 'pending' 
            AND expires_at < NOW()
        ");
        $stmt->execute([$userId]);

        // Limpiar tokens de sesión activos
        $stmt = $pdo->prepare("
            UPDATE purchases 
            SET session_token = NULL 
            WHERE user_id = ? 
            AND status = 'pending'
        ");
        $stmt->execute([$userId]);
    } catch (PDOException $e) {
        error_log("Error limpiando compras pendientes en logout: " . $e->getMessage());
    }
}

// ============================================
// ✅ DESTRUIR LA SESIÓN COMPLETAMENTE
// ============================================

// 1. Limpiar todas las variables de sesión
$_SESSION = [];

// 2. Eliminar la cookie de sesión si existe
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 3. Destruir la sesión
session_destroy();

// 4. Iniciar una nueva sesión limpia
session_start();

// 5. Regenerar el ID de sesión para prevenir Session Fixation
session_regenerate_id(true);

// ============================================
// ✅ ESTABLECER BANDERA DE LOGOUT
// ============================================
$_SESSION['just_logged_out'] = true;
$_SESSION['logout_time'] = time();

// ============================================
// REDIRIGIR AL INDEX
// ============================================
header('Location: index.php?logout=1');
exit;
