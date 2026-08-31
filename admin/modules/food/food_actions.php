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

// Determinar la acción (POST para formularios, GET para acciones directas)
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Raíz del sitio para operaciones de archivos (determinista, no depende del CWD)
$siteRoot = dirname(__DIR__, 3);
$upload_dir = $siteRoot . '/img/';

// ============================================
// AGREGAR PRODUCTO
// ============================================
if ($action === 'add_food') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf_token)) {
        header('Location: ../../index.php?tab=food&error=' . urlencode('Token CSRF inválido'));
        exit;
    }

    $name = sanitizeInput($_POST['food_name'] ?? '');
    $description = $_POST['food_description'] ?? '';
    $price = filter_var($_POST['food_price'] ?? 0, FILTER_VALIDATE_FLOAT);
    $category_id = !empty($_POST['category_id']) ? filter_var($_POST['category_id'], FILTER_VALIDATE_INT) : null;
    $image_path = '';

    $error = '';
    if (empty($name) || $price <= 0) {
        $error = "Nombre y precio son obligatorios.";
    } else {
        try {
            if (isset($_FILES['food_image']) && $_FILES['food_image']['error'] === UPLOAD_ERR_OK) {
                $upload_result = secureFileUpload($_FILES['food_image'], ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'], 2097152);
                if (!$upload_result['success']) {
                    $error = $upload_result['error'];
                } else {
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    $filename = 'food_' . time() . '_' . uniqid() . '.' . $upload_result['extension'];
                    $destination = $upload_dir . $filename;
                    if (move_uploaded_file($_FILES['food_image']['tmp_name'], $destination)) {
                        $image_path = 'img/' . $filename;
                    } else {
                        $error = "Error al subir la imagen.";
                    }
                }
            }

            if (empty($error)) {
                $stmt = $pdo->prepare("INSERT INTO food_items (name, description, price, image_url, category_id, is_active) VALUES (?, ?, ?, ?, ?, 0)");
                $stmt->execute([$name, $description, $price, $image_path, $category_id]);
                $success_msg = "Producto «" . $name . "» agregado exitosamente (oculto por defecto).";
                header('Location: ../../index.php?tab=food&msg=' . urlencode($success_msg));
                exit;
            }
        } catch (PDOException $e) {
            error_log("Error al guardar producto: " . $e->getMessage());
            $error = "Error al guardar el producto. Por favor, intente nuevamente.";
        }
    }

    header('Location: ../../index.php?tab=food&action=register&error=' . urlencode($error));
    exit;
}

// ============================================
// EDITAR PRODUCTO
// ============================================
if ($action === 'edit_food') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf_token)) {
        header('Location: ../../index.php?tab=food&error=' . urlencode('Token CSRF inválido'));
        exit;
    }

    $id = filter_var($_POST['food_id'] ?? 0, FILTER_VALIDATE_INT);
    $name = sanitizeInput($_POST['food_name'] ?? '');
    $description = $_POST['food_description'] ?? '';
    $price = filter_var($_POST['food_price'] ?? 0, FILTER_VALIDATE_FLOAT);
    $category_id = !empty($_POST['category_id']) ? filter_var($_POST['category_id'], FILTER_VALIDATE_INT) : null;

    $error = '';
    if ($id <= 0) {
        $error = "ID de producto inválido.";
    } elseif (empty($name) || $price <= 0) {
        $error = "Nombre y precio son obligatorios.";
    } else {
        try {
            $image_path = null;
            if (isset($_FILES['food_image']) && $_FILES['food_image']['error'] === UPLOAD_ERR_OK) {
                $upload_result = secureFileUpload($_FILES['food_image'], ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'], 2097152);
                if (!$upload_result['success']) {
                    $error = $upload_result['error'];
                } else {
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    $stmt = $pdo->prepare("SELECT image_url FROM food_items WHERE id = ?");
                    $stmt->execute([$id]);
                    $old_image = $stmt->fetchColumn();
                    if (!empty($old_image) && file_exists($siteRoot . '/' . $old_image)) {
                        @unlink($siteRoot . '/' . $old_image);
                    }
                    $filename = 'food_' . time() . '_' . uniqid() . '.' . $upload_result['extension'];
                    $destination = $upload_dir . $filename;
                    if (move_uploaded_file($_FILES['food_image']['tmp_name'], $destination)) {
                        $image_path = 'img/' . $filename;
                    } else {
                        $error = "Error al subir la imagen.";
                    }
                }
            }

            if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
                $stmt = $pdo->prepare("SELECT image_url FROM food_items WHERE id = ?");
                $stmt->execute([$id]);
                $old_image = $stmt->fetchColumn();
                if (!empty($old_image) && file_exists($siteRoot . '/' . $old_image)) {
                    @unlink($siteRoot . '/' . $old_image);
                }
                $image_path = '';
            }

            if (empty($error)) {
                if ($image_path !== null) {
                    $stmt = $pdo->prepare("UPDATE food_items SET name=?, description=?, price=?, image_url=?, category_id=? WHERE id=?");
                    $stmt->execute([$name, $description, $price, $image_path, $category_id, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE food_items SET name=?, description=?, price=?, category_id=? WHERE id=?");
                    $stmt->execute([$name, $description, $price, $category_id, $id]);
                }
                header('Location: ../../index.php?tab=food&msg=' . urlencode('Producto actualizado exitosamente.'));
                exit;
            }
        } catch (PDOException $e) {
            error_log("Error al actualizar producto: " . $e->getMessage());
            $error = "Error al actualizar el producto. Por favor, intente nuevamente.";
        }
    }

    $register_url = $id > 0
        ? '../../index.php?tab=food&action=register&edit_food_id=' . $id
        : '../../index.php?tab=food&action=register';
    header('Location: ' . $register_url . '&error=' . urlencode($error));
    exit;
}

// ============================================
// ELIMINAR PRODUCTO (GET)
// ============================================
if ($action === 'delete_food') {
    $id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
    if (!$id || $id <= 0 || !verifyCSRFToken($_GET['csrf_token'] ?? '')) {
        header('Location: ../../index.php?tab=food&error=' . urlencode('Solicitud inválida.'));
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT image_url FROM food_items WHERE id = ?");
        $stmt->execute([$id]);
        $image = $stmt->fetchColumn();
        if (!empty($image) && file_exists($siteRoot . '/' . $image)) {
            @unlink($siteRoot . '/' . $image);
        }

        $stmt = $pdo->prepare("DELETE FROM food_items WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: ../../index.php?tab=food&msg=' . urlencode('Producto eliminado correctamente.'));
        exit;
    } catch (PDOException $e) {
        error_log("Error al eliminar producto: " . $e->getMessage());
        header('Location: ../../index.php?tab=food&error=' . urlencode('Error al eliminar el producto. Por favor, intente nuevamente.'));
        exit;
    }
}

// ============================================
// PUBLICAR/OCULTAR PRODUCTO (GET)
// ============================================
if ($action === 'toggle_food') {
    $id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
    if (!$id || $id <= 0 || !verifyCSRFToken($_GET['csrf_token'] ?? '')) {
        header('Location: ../../index.php?tab=food&error=' . urlencode('Solicitud inválida.'));
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE food_items SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: ../../index.php?tab=food&msg=' . urlencode('Estado del producto actualizado.'));
        exit;
    } catch (PDOException $e) {
        error_log("Error al cambiar estado de producto: " . $e->getMessage());
        header('Location: ../../index.php?tab=food&error=' . urlencode('Error al actualizar el estado. Por favor, intente nuevamente.'));
        exit;
    }
}

// ============================================
// REDIRECCIÓN POR DEFECTO
// ============================================
header('Location: ../../index.php?tab=food');
exit;