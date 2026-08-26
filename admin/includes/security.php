<?php
// ============================================
// FUNCIONES DE SEGURIDAD COMPARTIDAS
// ============================================

function sanitizeInput($data, $type = 'string') {
    $data = trim($data);
    $data = stripslashes($data);
    switch ($type) {
        case 'email':
            return filter_var($data, FILTER_SANITIZE_EMAIL);
        case 'int':
            return filter_var($data, FILTER_VALIDATE_INT) ? intval($data) : null;
        case 'float':
            return filter_var($data, FILTER_VALIDATE_FLOAT) ? floatval($data) : null;
        case 'url':
            return filter_var($data, FILTER_SANITIZE_URL);
        case 'alpha_numeric':
            return preg_replace('/[^a-zA-Z0-9]/', '', $data);
        case 'phone':
            return preg_replace('/[^0-9]/', '', $data);
        default:
            return $data;
    }
}

function validateGetAction($action, $id) {
    $allowed_actions = ['delete_movie', 'delete_room', 'delete_showtime', 'toggle_movie', 'toggle_room', 'toggle_showtime', 'delete_food', 'toggle_food', 'update_movie'];
    if (!in_array($action, $allowed_actions)) {
        return false;
    }
    if (!filter_var($id, FILTER_VALIDATE_INT) || $id <= 0) {
        return false;
    }
    if (!isset($_GET['csrf_token']) || !verifyCSRFToken($_GET['csrf_token'])) {
        return false;
    }
    return true;
}

// ============================================
// VALIDAR Y SANITIZAR URL DE RETORNO
// ============================================
function validateReturnUrl($url) {
    // Si está vacía, usar la predeterminada
    if (empty($url)) {
        return 'admin.php?tab=movies';
    }
    
    // Eliminar posibles rutas de acciones
    $url = str_replace('actions/admin.php', 'admin.php', $url);
    $url = str_replace('admin/actions/admin.php', 'admin.php', $url);
    
    // Verificar que sea una URL relativa válida dentro del admin
    if (strpos($url, 'admin.php') === false && strpos($url, '?tab=') === false) {
        return 'admin.php?tab=movies';
    }
    
    // Eliminar posibles caracteres maliciosos
    $url = filter_var($url, FILTER_SANITIZE_URL);
    $url = strip_tags($url);
    
    return $url;
}
?>