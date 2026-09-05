<?php
// ============================================
// MÓDULO DE USUARIO — ACCIONES (POST)
// ============================================
require_once '../config.php';
require_once 'user_auth.php';

$action = $_POST['action'] ?? '';

// Determinar la URL de retorno (solo dentro del módulo)
$return_url = 'account.php';

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    header('Location: ' . $return_url . '?error=' . urlencode('Error de seguridad: Token inválido. Por favor, recarga la página.'));
    exit;
}

$siteRoot = dirname(__DIR__);
$avatarDir = $siteRoot . '/uploads/avatars/';

// ============================================
// ACTUALIZAR PERFIL
// ============================================
if ($action === 'update_profile') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $cedula_type = trim($_POST['cedula_type'] ?? '');
    $cedula_number = trim($_POST['cedula_number'] ?? '');
    $phone_prefix = trim($_POST['phone_prefix'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
    $new_password = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';

    $cedula_type = in_array($cedula_type, ['V', 'E', 'P', 'J']) ? $cedula_type : '';
    $phone_prefix = in_array($phone_prefix, ['412', '414', '416', '424', '426', '422']) ? $phone_prefix : '';

    $error = '';
    if (empty($name) || empty($email) || empty($cedula_type) || empty($cedula_number) || empty($phone_prefix) || empty($phone_number)) {
        $error = "Todos los campos son obligatorios.";
    } elseif (strlen($name) < 2 || strlen($name) > 100) {
        $error = "El nombre debe tener entre 2 y 100 caracteres.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "El formato del correo electrónico no es válido.";
    } elseif (!preg_match('/^[0-9]+$/', $cedula_number)) {
        $error = "La cédula solo debe contener números.";
    } elseif (strlen($cedula_number) < 7) {
        $error = "La Cédula de Identidad debe tener al menos 7 dígitos.";
    } elseif (!preg_match('/^[0-9]+$/', $phone_number)) {
        $error = "El número de teléfono solo debe contener números.";
    } elseif (strlen($phone_number) < 7) {
        $error = "El número de teléfono debe tener al menos 7 dígitos.";
    } elseif ($birth_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
        $error = "Formato de fecha de nacimiento inválido.";
    } else {
        // Contraseña opcional: validar solo si viene completa
        if (!empty($new_password) || !empty($confirm_new_password)) {
            $passwordLength = function_exists('mb_strlen')
                ? mb_strlen($new_password, 'UTF-8')
                : strlen($new_password);
            if ($new_password !== $confirm_new_password) {
                $error = "Las contraseñas no coinciden.";
            } elseif ($passwordLength < 8) {
                $error = "La contraseña debe tener al menos 8 caracteres.";
            } elseif (!preg_match('/[A-Z]/', $new_password)) {
                $error = "La contraseña debe contener al menos una letra mayúscula.";
            } elseif (!preg_match('/[a-z]/', $new_password)) {
                $error = "La contraseña debe contener al menos una letra minúscula.";
            } elseif (!preg_match('/[0-9]/', $new_password)) {
                $error = "La contraseña debe contener al menos un número.";
            }
        }

        if (empty($error)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $stmt->execute([$email, $_SESSION['user_id']]);
            if ($stmt->rowCount() > 0) {
                $error = "El correo electrónico ya está registrado por otro usuario.";
            }
        }
        if (empty($error)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE cedula_type = ? AND cedula_number = ? AND id != ? LIMIT 1");
            $stmt->execute([$cedula_type, $cedula_number, $_SESSION['user_id']]);
            if ($stmt->rowCount() > 0) {
                $error = "El número de cédula ya está registrado por otro usuario.";
            }
        }

        if (empty($error)) {
            try {
                $pdo->beginTransaction();
                if (!empty($new_password)) {
                    $passwordHash = password_hash($new_password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET name = ?, email = ?, cedula_type = ?, cedula_number = ?,
                            phone_prefix = ?, phone_number = ?, birth_date = ?, password = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $email, $cedula_type, $cedula_number, $phone_prefix, $phone_number, $birth_date, $passwordHash, $_SESSION['user_id']]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET name = ?, email = ?, cedula_type = ?, cedula_number = ?,
                            phone_prefix = ?, phone_number = ?, birth_date = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $email, $cedula_type, $cedula_number, $phone_prefix, $phone_number, $birth_date, $_SESSION['user_id']]);
                }
                $pdo->commit();
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                header('Location: ' . $return_url . '?msg=' . urlencode('Perfil actualizado exitosamente.'));
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Error actualizando perfil: " . $e->getMessage());
                if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062) {
                    $error = "El correo o cédula ya están registrados por otro usuario.";
                } else {
                    $error = "Ocurrió un error interno al actualizar el perfil. Por favor, intenta nuevamente.";
                }
            }
        }
    }

    if (!empty($error)) {
        header('Location: ' . $return_url . '?error=' . urlencode($error));
        exit;
    }
}

// ============================================
// SUBIR / ACTUALIZAR AVATAR
// ============================================
if ($action === 'upload_avatar') {
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] === UPLOAD_ERR_NO_FILE) {
        header('Location: ' . $return_url . '?error=' . urlencode('Selecciona una imagen para tu avatar.'));
        exit;
    }
    $file = $_FILES['avatar'];
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $max_size = 2097152; // 2MB

    $error = '';
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Error al subir el archivo.";
    } elseif ($file['size'] > $max_size) {
        $error = "El archivo excede el tamaño máximo permitido (2MB).";
    } elseif ($file['size'] <= 0) {
        $error = "El archivo está vacío.";
    } else {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($mime_type, $allowed_types)) {
            $error = "Tipo de archivo no permitido.";
        } elseif (!in_array($extension, $allowed_extensions)) {
            $error = "Extensión de archivo no permitida.";
        }
    }

    if (!empty($error)) {
        header('Location: ' . $return_url . '?error=' . urlencode($error));
        exit;
    }

    try {
        if (!is_dir($avatarDir)) {
            mkdir($avatarDir, 0755, true);
        }
        $filename = 'avatar_' . intval($_SESSION['user_id']) . '.' . $extension;
        $destination = $avatarDir . $filename;

        // Eliminar avatares anteriores del usuario (cualquier extensión)
        foreach (glob($avatarDir . 'avatar_' . intval($_SESSION['user_id']) . '.*') as $oldFile) {
            if (is_file($oldFile) && realpath($oldFile) !== realpath($destination)) {
                @unlink($oldFile);
            }
        }

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            header('Location: ' . $return_url . '?error=' . urlencode('Error al guardar el avatar. Por favor, intenta nuevamente.'));
            exit;
        }

        $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
        $stmt->execute(['uploads/avatars/' . $filename, $_SESSION['user_id']]);
        header('Location: ' . $return_url . '?msg=' . urlencode('Avatar actualizado exitosamente.'));
        exit;
    } catch (Throwable $e) {
        error_log("Error subiendo avatar: " . $e->getMessage());
        header('Location: ' . $return_url . '?error=' . urlencode('Ocurrió un error al subir el avatar. Por favor, intenta nuevamente.'));
        exit;
    }
}

// ============================================
// SOLICITAR ELIMINACIÓN DE CUENTA
// ============================================
if ($action === 'request_delete') {
    try {
        $stmt = $pdo->prepare("UPDATE users SET delete_requested_at = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        error_log(sprintf(
            "🚨 Solicitud de eliminación de cuenta: user_id=%d, IP=%s",
            $_SESSION['user_id'],
            $_SERVER['REMOTE_ADDR']
        ));
        header('Location: ' . $return_url . '?msg=' . urlencode('Solicitud de eliminación enviada. Un administrador revisará tu petición.'));
        exit;
    } catch (PDOException $e) {
        error_log("Error en solicitud de eliminación: " . $e->getMessage());
        header('Location: ' . $return_url . '?error=' . urlencode('Ocurrió un error al enviar la solicitud. Por favor, intenta nuevamente.'));
        exit;
    }
}

// ============================================
// CANCELAR SOLICITUD DE ELIMINACIÓN
// ============================================
if ($action === 'cancel_delete') {
    try {
        $stmt = $pdo->prepare("UPDATE users SET delete_requested_at = NULL WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        header('Location: ' . $return_url . '?msg=' . urlencode('Solicitud de eliminación cancelada.'));
        exit;
    } catch (PDOException $e) {
        error_log("Error cancelando solicitud de eliminación: " . $e->getMessage());
        header('Location: ' . $return_url . '?error=' . urlencode('Ocurrió un error al cancelar la solicitud. Por favor, intenta nuevamente.'));
        exit;
    }
}

// ============================================
// REDIRECCIÓN POR DEFECTO
// ============================================
header('Location: ' . $return_url);
exit;