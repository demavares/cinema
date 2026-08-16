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
//   4. Registra la última ejecución en site_config.
// ============================================

// Solo permitir ejecución por línea de comandos (CLI), no vía web
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Acceso denegado: este script solo puede ejecutarse desde la línea de comandos.");
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
    // (reservas temporales sin una compra pending vigente)
    // ============================================
    $stmtDeleteZombies = $pdo->prepare("
        DELETE t FROM tickets t
        WHERE t.status = 'hold'
          AND NOT EXISTS (
              SELECT 1 FROM purchases p
              WHERE p.user_id = t.user_id
                AND p.showtime_id = t.showtime_id
                AND p.status = 'pending'
                AND p.expires_at > NOW()
          )
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
    // PASO 4: Registrar última ejecución en site_config
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
    echo "  Resumen: $expiredPurchases expired | $deletedZombies zombies | $deletedOldPurchases antiguas\n";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $duration = round((microtime(true) - $start) * 1000, 2);
    echo "[$timestamp] ❌ ERROR: " . $e->getMessage() . " (duración: {$duration}ms)\n";
    error_log("cron_cleanup ERROR: " . $e->getMessage());

    // Liberar el lock antes de salir con error
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    exit(1);
}

// Liberar el lock
flock($lockHandle, LOCK_UN);
fclose($lockHandle);

exit(0);
?>