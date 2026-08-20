<?php
// ============================================
// cron_cleanup.php - Limpieza automática programada
// ============================================
// Ejecutar vía cron (recomendado: cada hora):
//   0 * * * * /usr/bin/php /ruta/completa/cron_cleanup.php >> /var/log/cinema_cleanup.log 2>&1
//
// Qué hace:
//   1. Marca como 'expired' las compras pending cuyo expires_at ya pasó.
//   2. Elimina tickets 'hold' que no tienen una compra pending vigente (zombies).
//   3. Elimina compras 'expired' con más de 30 días (higiene de BD).
//   4. Elimina tickets huérfanos.
//   5. Elimina food_orders pending antiguos.
//   6. Elimina ticket_logs antiguos.
//   7. Limpia registros antiguos de login_rate_limits.
//   8. Registra la última ejecución en site_config.
// ============================================

// Solo permitir ejecución por línea de comandos (CLI), no vía web
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Acceso denegado: este script solo puede ejecutarse desde la línea de comandos.\n");
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

// ============================================
// LOCK FILE: evitar ejecución concurrente
// ============================================
$lockFile = sys_get_temp_dir() . '/cinema_cleanup.lock';
$lockHandle = fopen($lockFile, 'w');

if (!$lockHandle) {
    exit("❌ No se pudo crear el archivo de bloqueo.\n");
}

if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    echo "[" . date('Y-m-d H:i:s') . "] ⚠️ Otra instancia del cleanup ya está en ejecución. Saliendo.\n";
    fclose($lockHandle);
    exit(0);
}

$start = microtime(true);
$timestamp = date('Y-m-d H:i:s');

echo "[$timestamp] 🚀 Iniciando limpieza automática de cinema_db\n";

try {
    require_once 'config.php';

    $pdo->beginTransaction();

    // ============================================
    // PASO 1: Marcar compras pending expiradas
    // ============================================
    $stmtExpire = $pdo->prepare("
        UPDATE purchases
        SET status = 'expired', expires_at = NOW()
        WHERE status = 'pending' AND expires_at < NOW()
    ");

    $stmtExpire->execute();

    $expiredPurchases = $stmtExpire->rowCount();

    echo "  ✅ Marcadas $expiredPurchases compras pending como expired\n";

    // ============================================
    // PASO 2: Eliminar tickets 'hold' zombies
    // ============================================
    $stmtDeleteZombies = $pdo->prepare("
        DELETE t FROM tickets t
        LEFT JOIN purchases p
            ON p.user_id = t.user_id
            AND p.showtime_id = t.showtime_id
            AND p.status = 'pending'
            AND p.expires_at > NOW()
        WHERE t.status = 'hold'
        AND p.id IS NULL
    ");

    $stmtDeleteZombies->execute();

    $deletedZombies = $stmtDeleteZombies->rowCount();

    echo "  🧹 Eliminados $deletedZombies tickets hold zombies\n";

    // ============================================
    // PASO 3: Eliminar compras 'expired' muy antiguas (> 30 días)
    // ============================================
    $stmtDeleteOld = $pdo->prepare("
        DELETE FROM purchases
        WHERE status = 'expired'
        AND purchase_date < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");

    $stmtDeleteOld->execute();

    $deletedOldPurchases = $stmtDeleteOld->rowCount();

    echo "  🗑️  Eliminadas $deletedOldPurchases compras expired antiguas (>30 días)\n";

    // ============================================
    // PASO 4: Eliminar tickets con purchase_id huérfano
    // ============================================
    $stmtDeleteOrphans = $pdo->prepare("
        DELETE t FROM tickets t
        LEFT JOIN purchases p ON p.id = t.purchase_id
        WHERE t.purchase_id IS NOT NULL
        AND p.id IS NULL
    ");

    $stmtDeleteOrphans->execute();

    $deletedOrphanTickets = $stmtDeleteOrphans->rowCount();

    if ($deletedOrphanTickets > 0) {
        echo "  🗑️  Eliminados $deletedOrphanTickets tickets huérfanos (sin compra asociada)\n";
    }

    // ============================================
    // PASO 5: Eliminar food_orders pending antiguos (> 30 días)
    // ============================================
    $stmtDeleteOldFood = $pdo->prepare("
        DELETE FROM food_orders
        WHERE status = 'pending'
        AND order_date < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");

    $stmtDeleteOldFood->execute();

    $deletedOldFood = $stmtDeleteOldFood->rowCount();

    if ($deletedOldFood > 0) {
        echo "  🗑️  Eliminados $deletedOldFood pedidos de comida pending antiguos (>30 días)\n";
    }

    // ============================================
    // PASO 6: Eliminar ticket_logs antiguos (> 90 días)
    // ============================================
    $stmtDeleteOldLogs = $pdo->prepare("
        DELETE FROM ticket_logs
        WHERE released_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
    ");

    $stmtDeleteOldLogs->execute();

    $deletedOldLogs = $stmtDeleteOldLogs->rowCount();

    if ($deletedOldLogs > 0) {
        echo "  🗑️  Eliminados $deletedOldLogs logs antiguos (>90 días)\n";
    }

    // ============================================
    // PASO 7: Limpiar login_rate_limits antiguos
    // ============================================
    $deletedRateLimits = 0;

    try {
        $deletedRateLimits = cleanupLoginRateLimits($pdo, 86400);

        if ($deletedRateLimits > 0) {
            echo "  🗑️  Eliminados $deletedRateLimits registros antiguos de login_rate_limits\n";
        }
    } catch (Throwable $e) {
        echo "  ⚠️ No se pudo limpiar login_rate_limits: " . $e->getMessage() . "\n";
        error_log("cron_cleanup login_rate_limits ERROR: " . $e->getMessage());
    }

    // ============================================
    // PASO 8: Registrar última ejecución en site_config
    // ============================================
    $stmtConfig = $pdo->prepare("
        UPDATE site_config
        SET value = NOW(), updated_at = NOW()
        WHERE key_name = 'last_cleanup_expired_purchases'
    ");

    $stmtConfig->execute();

    $pdo->commit();

    $duration = round((microtime(true) - $start) * 1000, 2);

    echo "[$timestamp] ✅ Limpieza completada en {$duration}ms\n";
    echo "  Resumen:\n";
    echo "    - $expiredPurchases compras marcadas como expired\n";
    echo "    - $deletedZombies tickets hold zombies eliminados\n";
    echo "    - $deletedOldPurchases compras expired antiguas eliminadas\n";

    if ($deletedOrphanTickets > 0) {
        echo "    - $deletedOrphanTickets tickets huérfanos eliminados\n";
    }

    if ($deletedOldFood > 0) {
        echo "    - $deletedOldFood pedidos de comida antiguos eliminados\n";
    }

    if ($deletedOldLogs > 0) {
        echo "    - $deletedOldLogs logs antiguos eliminados\n";
    }

    if ($deletedRateLimits > 0) {
        echo "    - $deletedRateLimits registros de rate limiting eliminados\n";
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $duration = round((microtime(true) - $start) * 1000, 2);

    echo "[$timestamp] ❌ ERROR: " . $e->getMessage() . " (duración: {$duration}ms)\n";

    error_log("cron_cleanup ERROR: " . $e->getMessage());

    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);

    exit(1);
}

// Liberar el lock
flock($lockHandle, LOCK_UN);
fclose($lockHandle);

exit(0);
?>