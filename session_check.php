<?php
// ============================================
// SESSION_CHECK.PHP - Verificación centralizada de sesión
// ============================================

function checkAndCleanSession() {
    $limite_inactividad = 1800; // 30 minutos
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Verificar inactividad
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $limite_inactividad)) {
        // 1. Vaciar array de sesión
        $_SESSION = array();
        
        // 2. Eliminar cookie de sesión en el cliente
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
        
        // 3. Destruir la sesión actual
        session_destroy();
        
        // 4. Redirigir enviando la alerta solo mediante GET
        header("Location: index.php?expired=1");
        exit();
    }
    
    // Actualizar timestamp si la sesión sigue activa
    $_SESSION['last_activity'] = time();
}

// Función para limpiar completamente una sesión expirada
function clearExpiredSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION = array();
    
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
    
    session_destroy();
    session_start();
    $_SESSION['last_activity'] = time();
}
?>