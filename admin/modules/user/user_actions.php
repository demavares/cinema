<?php
require_once '../../../config.php';
require_once '../../includes/security.php';

// ============================================
// VERIFICAR AUTENTICACIÓN Y ROL
// ============================================
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../../../login.php');
    exit;
}

// Obtener acción
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Determinar la URL de retorno - SIEMPRE a admin/index.php
$return_url = $_POST['return'] ?? $_GET['return'] ?? '../../index.php?tab=users';

// Verificar CSRF
$csrf_token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
if (!verifyCSRFToken($csrf_token)) {
    header('Location: ' . $return_url . '&error=Token+CSRF+inválido');
    exit;
}

// ============================================
// AGREGAR USUARIO
// ============================================
if ($action === 'add_user') {
    $name = sanitizeInput($_POST['user_name'] ?? '');
    $email = sanitizeInput($_POST['user_email'] ?? '', 'email');
    $password = $_POST['user_password'] ?? '';
    $role = sanitizeInput($_POST['user_role'] ?? 'user');
    $cedula_type = sanitizeInput($_POST['cedula_type'] ?? '');
    $cedula_number = sanitizeInput($_POST['cedula_number'] ?? '', 'alpha_numeric');
    $phone_prefix = sanitizeInput($_POST['phone_prefix'] ?? '');
    $phone_number = sanitizeInput($_POST['phone_number'] ?? '', 'phone');
    $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;

    if (!in_array($role, ['user', 'admin'])) {
        $role = 'user';
    }

    $password_error = validatePasswordStrength($password);

    $error = '';
    if (empty($name) || empty($email) || empty($password)) {
        $error = "Todos los campos son obligatorios.";
    } elseif ($password_error) {
        $error = $password_error;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email no válido.";
    } elseif (strlen($name) < 2 || strlen($name) > 100) {
        $error = "El nombre debe tener entre 2 y 100 caracteres.";
    } elseif (!empty($cedula_number) && strlen($cedula_number) < 7) {
        $error = "La Cédula de Identidad debe tener al menos 7 dígitos.";
    } elseif (!empty($phone_number) && strlen($phone_number) < 7) {
        $error = "El número de teléfono debe tener al menos 7 dígitos.";
    } elseif (empty($cedula_type) || empty($cedula_number)) {
        $error = "La Cédula de Identidad es obligatoria.";
    } elseif (empty($phone_prefix) || empty($phone_number)) {
        $error = "El Teléfono Móvil es obligatorio.";
    } elseif ($birth_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
        $error = "Formato de fecha de nacimiento inválido.";
    } else {
        if (!empty($cedula_type) && !empty($cedula_number)) {
            $stmt_check = $pdo->prepare("SELECT id FROM users WHERE cedula_type = ? AND cedula_number = ?");
            $stmt_check->execute([$cedula_type, $cedula_number]);
            if ($stmt_check->rowCount() > 0) {
                $cedula_display = $cedula_type . '-' . $cedula_number;
                $error = "El usuario con número de cédula " . $cedula_display . " ya se encuentra registrado.";
            }
        }

        if (empty($error)) {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            try {
                $stmt = $pdo->prepare("INSERT INTO users (name, email, cedula_type, cedula_number, phone_prefix, phone_number, birth_date, password, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $email, $cedula_type, $cedula_number, $phone_prefix, $phone_number, $birth_date, $passwordHash, $role]);
                $success_msg = "Usuario «" . $name . "» registrado exitosamente.";
                header('Location: ../../index.php?tab=users&msg=' . urlencode($success_msg));
                exit;
            } catch (PDOException $e) {
                error_log("Error al registrar usuario: " . $e->getMessage());
                if ($e->errorInfo[1] == 1062) {
                    $cedula_display = !empty($cedula_type) && !empty($cedula_number) ? $cedula_type . '-' . $cedula_number : 'registrada';
                    $error = "El usuario con número de cédula " . $cedula_display . " ya se encuentra registrado.";
                } else {
                    $error = "Error al registrar usuario. Por favor, intente nuevamente.";
                }
            }
        }
    }

    if (!empty($error)) {
        header('Location: ../../index.php?tab=users&action=register&error=' . urlencode($error));
        exit;
    }
}

// ============================================
// EDITAR USUARIO
// ============================================
if ($action === 'edit_user') {
    $user_id = filter_var($_POST['user_id'] ?? 0, FILTER_VALIDATE_INT);
    $name = sanitizeInput($_POST['user_name'] ?? '');
    $email = sanitizeInput($_POST['user_email'] ?? '', 'email');
    $role = sanitizeInput($_POST['user_role'] ?? 'user');
    $new_password = $_POST['user_password'] ?? '';
    $cedula_type = sanitizeInput($_POST['cedula_type'] ?? '');
    $cedula_number = sanitizeInput($_POST['cedula_number'] ?? '', 'alpha_numeric');
    $phone_prefix = sanitizeInput($_POST['phone_prefix'] ?? '');
    $phone_number = sanitizeInput($_POST['phone_number'] ?? '', 'phone');
    $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;

    if (!in_array($role, ['user', 'admin'])) {
        $role = 'user';
    }

    $error = '';
    if ($user_id <= 0) {
        $error = "ID de usuario inválido.";
    } elseif ($user_id == $_SESSION['user_id'] && $role !== 'admin') {
        $error = "⚠️ No puedes cambiar tu propio rol de administrador.";
    } elseif (empty($name) || empty($email)) {
        $error = "Nombre y email son obligatorios.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email no válido.";
    } elseif (strlen($name) < 2 || strlen($name) > 100) {
        $error = "El nombre debe tener entre 2 y 100 caracteres.";
    } elseif (empty($cedula_type) || empty($cedula_number)) {
        $error = "La Cédula de Identidad es obligatoria.";
    } elseif (strlen($cedula_number) < 7) {
        $error = "La Cédula de Identidad debe tener al menos 7 dígitos.";
    } elseif (empty($phone_prefix) || empty($phone_number)) {
        $error = "El Teléfono Móvil es obligatorio.";
    } elseif (strlen($phone_number) < 7) {
        $error = "El número de teléfono debe tener al menos 7 dígitos.";
    } else {
        $stmt_check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt_check->execute([$email, $user_id]);
        if ($stmt_check->rowCount() > 0) {
            $error = "El email ya está registrado por otro usuario.";
        }

        if (empty($error) && !empty($cedula_type) && !empty($cedula_number)) {
            $stmt_check = $pdo->prepare("SELECT id FROM users WHERE cedula_type = ? AND cedula_number = ? AND id != ?");
            $stmt_check->execute([$cedula_type, $cedula_number, $user_id]);
            if ($stmt_check->rowCount() > 0) {
                $cedula_display = $cedula_type . '-' . $cedula_number;
                $error = "El usuario con número de cédula " . $cedula_display . " ya se encuentra registrado.";
            }
        }

        if (empty($error)) {
            try {
                if (!empty($new_password)) {
                    $password_error = validatePasswordStrength($new_password);
                    if ($password_error) {
                        $error = $password_error;
                    } else {
                        $passwordHash = password_hash($new_password, PASSWORD_BCRYPT);
                        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, cedula_type = ?, cedula_number = ?, phone_prefix = ?, phone_number = ?, birth_date = ?, role = ?, password = ? WHERE id = ?");
                        $stmt->execute([$name, $email, $cedula_type, $cedula_number, $phone_prefix, $phone_number, $birth_date, $role, $passwordHash, $user_id]);
                        $success_msg = "Usuario actualizado exitosamente (contraseña actualizada).";
                        header('Location: ../../index.php?tab=users&msg=' . urlencode($success_msg));
                        exit;
                    }
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, cedula_type = ?, cedula_number = ?, phone_prefix = ?, phone_number = ?, birth_date = ?, role = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $cedula_type, $cedula_number, $phone_prefix, $phone_number, $birth_date, $role, $user_id]);
                    $success_msg = "Usuario actualizado exitosamente.";
                    header('Location: ../../index.php?tab=users&msg=' . urlencode($success_msg));
                    exit;
                }
            } catch (PDOException $e) {
                error_log("Error al actualizar usuario: " . $e->getMessage());
                if ($e->errorInfo[1] == 1062) {
                    $cedula_display = !empty($cedula_type) && !empty($cedula_number) ? $cedula_type . '-' . $cedula_number : 'registrada';
                    $error = "El usuario con número de cédula " . $cedula_display . " ya se encuentra registrado.";
                } else {
                    $error = "Error al actualizar usuario. Por favor, intente nuevamente.";
                }
            }
        }
    }

    if (!empty($error)) {
        $register_url = $user_id > 0
            ? '../../index.php?tab=users&action=register&edit_user_id=' . $user_id
            : '../../index.php?tab=users&action=register';
        header('Location: ' . $register_url . '&error=' . urlencode($error));
        exit;
    }
}

// ============================================
// BLOQUEAR/DESBLOQUEAR USUARIO
// ============================================
if ($action === 'toggle_block_user') {
    $user_id = filter_var($_POST['user_id'] ?? 0, FILTER_VALIDATE_INT);
    $current_status = filter_var($_POST['current_status'] ?? 0, FILTER_VALIDATE_INT);

    if ($user_id <= 0) {
        $error = "ID de usuario inválido.";
        header('Location: ' . $return_url . '&error=' . urlencode($error));
        exit;
    } elseif ($user_id == $_SESSION['user_id']) {
        $error = "No puedes bloquear tu propia cuenta.";
        header('Location: ' . $return_url . '&error=' . urlencode($error));
        exit;
    }

    $new_status = $current_status == 1 ? 0 : 1;
    try {
        $stmt = $pdo->prepare("UPDATE users SET is_blocked = ? WHERE id = ?");
        $stmt->execute([$new_status, $user_id]);
        $success_msg = $new_status == 1 ? "Usuario bloqueado exitosamente." : "Usuario desbloqueado exitosamente.";
        header('Location: ' . $return_url . '&msg=' . urlencode($success_msg));
        exit;
    } catch (PDOException $e) {
        error_log("Error al cambiar estado de usuario: " . $e->getMessage());
        header('Location: ' . $return_url . '&error=' . urlencode('Error al cambiar estado del usuario. Por favor, intente nuevamente.'));
        exit;
    }
}

// ============================================
// ELIMINAR USUARIO
// ============================================
if ($action === 'delete_user') {
    $user_id = filter_var($_POST['user_id'] ?? 0, FILTER_VALIDATE_INT);

    if ($user_id <= 0) {
        header('Location: ' . $return_url . '&error=' . urlencode('ID de usuario inválido.'));
        exit;
    } elseif ($user_id == $_SESSION['user_id']) {
        header('Location: ' . $return_url . '&error=' . urlencode('No puedes eliminar tu propia cuenta.'));
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        header('Location: ' . $return_url . '&msg=' . urlencode('Usuario eliminado exitosamente.'));
        exit;
    } catch (PDOException $e) {
        error_log("Error al eliminar usuario: " . $e->getMessage());
        header('Location: ' . $return_url . '&error=' . urlencode('Error al eliminar usuario. Por favor, intente nuevamente.'));
        exit;
    }
}

// ============================================
// REDIRECCIÓN POR DEFECTO
// ============================================
header('Location: ' . $return_url);
exit;