<?php
// ============================================
// liberar_asientos.php - Libera holds y expira compras pending
// ============================================
// Acepta POST normal (fetch) y navigator.sendBeacon().
// El beacon NO permite cabeceras personalizadas, por lo que
// este script NO exige X-Requested-With ni similares.
//
// MODOS:
//   action=full  (por defecto) → Liberación INMEDIATA.
//       Se usa en: botón "Volver a Boletos" y seats.php.
//       Expira la compra pending, elimina los holds y limpia la sesión.
//
//   action=grace → Liberación DIFERIDA (cerrar pestaña/navegador).
//       Marca la compra pending para expirar en 20 segundos.
//       - Si el usuario RECARGA (F5) o vuelve al flujo, la página
//         restaura la reserva (cancela la gracia) y todo continúa.
//       - Si el usuario CERRÓ la pestaña/navegador, nadie cancela la
//         gracia: la compra expira y la limpieza automática libera
//         los asientos (y quedan libres para otros usuarios al instante
//         pasado ese lapso).
// ============================================
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$showtimeId = intval($_POST['showtime_id'] ?? 0);
if ($showtimeId <= 0) {
    echo json_encode(['success' => false, 'error' => 'showtime_id inválido']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? 'full';

try {
    // ============================================
    // MODO GRACE (unload: cerrar / recargar / navegar)
    // ============================================
    if ($action === 'grace') {
        $stmtGrace = $pdo->prepare("
            UPDATE purchases
            SET expires_at = DATE_ADD(NOW(), INTERVAL 20 SECOND)
            WHERE user_id = ?
              AND showtime_id = ?
              AND status = 'pending'
        ");
        $stmtGrace->execute([$userId, $showtimeId]);

        echo json_encode(['success' => true, 'mode' => 'grace']);
        exit;
    }

    // ============================================
    // MODO FULL (liberación inmediata)
    // ============================================
    $pdo->beginTransaction();

    // 1) Expirar compras 'pending' del usuario en este showtime
    $stmtExpire = $pdo->prepare("
        UPDATE purchases
        SET status = 'expired', expires_at = NOW()
        WHERE user_id = ?
          AND showtime_id = ?
          AND status = 'pending'
    ");
    $stmtExpire->execute([$userId, $showtimeId]);

    // 2) Eliminar tickets 'hold' del usuario en este showtime
    $stmtDelete = $pdo->prepare("
        DELETE FROM tickets
        WHERE user_id = ?
          AND showtime_id = ?
          AND status = 'hold'
    ");
    $stmtDelete->execute([$userId, $showtimeId]);
    $released = $stmtDelete->rowCount();

    $pdo->commit();

    // 3) Limpiar claves de sesión del flujo
    clearPurchaseSession($showtimeId);

    echo json_encode(['success' => true, 'released' => $released]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("❌ liberar_asientos.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error interno']);
}