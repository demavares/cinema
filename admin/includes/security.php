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

// ============================================
// SUBIDA SEGURA DE ARCHIVOS (imágenes)
// ============================================
function secureFileUpload($file, $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'], $max_size = 2097152) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Error al subir el archivo.'];
    }
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'El archivo excede el tamaño máximo permitido (2MB).'];
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime_type, $allowed_types)) {
        return ['success' => false, 'error' => 'Tipo de archivo no permitido.'];
    }
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($extension, $allowed_extensions)) {
        return ['success' => false, 'error' => 'Extensión de archivo no permitida.'];
    }
    return ['success' => true, 'extension' => $extension, 'mime_type' => $mime_type];
}

// ============================================
// VALIDAR FORTALEZA DE CONTRASEÑA
// ============================================
function validatePasswordStrength($password) {
    if (strlen($password) < 8) {
        return "La contraseña debe tener al menos 8 caracteres.";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return "La contraseña debe contener al menos una letra mayúscula.";
    }
    if (!preg_match('/[a-z]/', $password)) {
        return "La contraseña debe contener al menos una letra minúscula.";
    }
    if (!preg_match('/[0-9]/', $password)) {
        return "La contraseña debe contener al menos un número.";
    }
    return null;
}
?>