<?php
require_once 'config.php';

// ============================================
// CERRAR SESIÓN COMPLETAMENTE
// ============================================

// Limpiar el array de sesión
$_SESSION = [];

// Destruir la cookie de sesión si existe
if (ini_get("session_use_cookies")) {
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

// Destruir la sesión en el servidor
session_destroy();

// Redirigir al inicio con parámetro para limpiar sessionStorage
header('Location: index.php?logout=1');
exit;
?>