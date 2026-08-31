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

// Determinar la acción (GET para acciones directas)
$action = $_GET['action'] ?? '';

// ============================================
// ELIMINAR SALA (GET)
// ============================================
if ($action === 'delete_room') {
    $id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
    if (!$id || $id <= 0 || !verifyCSRFToken($_GET['csrf_token'] ?? '')) {
        header('Location: ../../index.php?tab=rooms&error=' . urlencode('Solicitud inválida.'));
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM showtimes WHERE room_id = ? AND is_active = 1");
        $stmt->execute([$id]);

        if ($stmt->fetchColumn() > 0) {
            header('Location: ../../index.php?tab=rooms&error=' . urlencode('No se puede eliminar la sala porque tiene funciones activas.'));
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: ../../index.php?tab=rooms&msg=' . urlencode('Sala eliminada correctamente.'));
        exit;
    } catch (PDOException $e) {
        error_log("Error al eliminar sala: " . $e->getMessage());
        header('Location: ../../index.php?tab=rooms&error=' . urlencode('Error al eliminar la sala. Por favor, intente nuevamente.'));
        exit;
    }
}

// ============================================
// ACTIVAR/DESACTIVAR SALA (GET)
// ============================================
if ($action === 'toggle_room') {
    $id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
    if (!$id || $id <= 0 || !verifyCSRFToken($_GET['csrf_token'] ?? '')) {
        header('Location: ../../index.php?tab=rooms&error=' . urlencode('Solicitud inválida.'));
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE rooms SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: ../../index.php?tab=rooms&msg=' . urlencode('Estado de sala actualizado.'));
        exit;
    } catch (PDOException $e) {
        error_log("Error al cambiar estado de sala: " . $e->getMessage());
        header('Location: ../../index.php?tab=rooms&error=' . urlencode('Error al actualizar el estado. Por favor, intente nuevamente.'));
        exit;
    }
}

// ============================================
// REDIRECCIÓN POR DEFECTO
// ============================================
header('Location: ../../index.php?tab=rooms');
exit;