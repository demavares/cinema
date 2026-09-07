<?php
// ============================================
// HELPERS - MANTENIMIENTO / LIMPIEZA AUTOMÁTICA
// ============================================

// ============================================
// LIBERAR ASIENTOS EXPIRADOS
// ============================================
function releaseExpiredSeats($pdo)
{
    $currentDateTime = date('Y-m-d H:i:s');
    $total_released = 0;

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT id, seats, showtime_id
            FROM purchases
            WHERE status = 'pending' AND expires_at < ?
            FOR UPDATE
        ");
        $stmt->execute([$currentDateTime]);

        $expired_purchases = $stmt->fetchAll();

        foreach ($expired_purchases as $purchase) {
            $stmt = $pdo->prepare("UPDATE purchases SET status = 'expired' WHERE id = ?");
            $stmt->execute([$purchase['id']]);

            $seatsArray = explode(',', $purchase['seats']);
            $placeholders = implode(',', array_fill(0, count($seatsArray), '?'));

            $stmt = $pdo->prepare("DELETE FROM tickets WHERE showtime_id = ? AND seat_code IN ($placeholders)");
            $stmt->execute(array_merge([$purchase['showtime_id']], $seatsArray));

            $total_released += count($seatsArray);
        }

        $stmt = $pdo->prepare("
            SELECT DISTINCT s.id, s.show_date, s.show_time, m.duration, COUNT(t.id) as ticket_count
            FROM showtimes s
            JOIN movies m ON s.movie_id = m.id
            LEFT JOIN tickets t ON t.showtime_id = s.id
            WHERE DATE_ADD(CONCAT(s.show_date, ' ', s.show_time), INTERVAL m.duration MINUTE) < ?
            AND s.is_active = 1
            GROUP BY s.id
        ");
        $stmt->execute([$currentDateTime]);

        $expired_showtimes = $stmt->fetchAll();

        foreach ($expired_showtimes as $showtime) {
            $stmt_log = $pdo->prepare("INSERT INTO ticket_logs (showtime_id, ticket_count) VALUES (?, ?)");
            $stmt_log->execute([$showtime['id'], $showtime['ticket_count']]);

            $stmt_update = $pdo->prepare("UPDATE showtimes SET is_active = 0 WHERE id = ?");
            $stmt_update->execute([$showtime['id']]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error liberando asientos expirados: " . $e->getMessage());
    }

    return $total_released;
}

function releaseExpiredSeatsOptimized($pdo)
{
    $cacheKey = 'last_seat_release_time';
    $cacheInterval = 60;

    $lastRelease = $_SESSION[$cacheKey] ?? 0;
    $currentTime = time();

    if (($currentTime - $lastRelease) < $cacheInterval) {
        return 0;
    }

    $released = releaseExpiredSeats($pdo);

    $_SESSION[$cacheKey] = $currentTime;

    return $released;
}

// ============================================
// LIMPIEZA AUTOMÁTICA
// ============================================
function cleanupExpiredPurchasesPeriodic($pdo)
{
    try {
        $lastCleanupKey = 'last_cleanup_expired_purchases';

        $stmt = $pdo->prepare("SELECT value FROM site_config WHERE key_name = ?");
        $stmt->execute([$lastCleanupKey]);
        $lastCleanup = $stmt->fetch();

        $now = time();
        $fiveDaysInSeconds = 5 * 24 * 60 * 60;
        $currentHour = (int)date('H');
        $inMaintenanceWindow = ($currentHour >= 1 && $currentHour < 6);

        if (!$inMaintenanceWindow) {
            return;
        }

        $shouldCleanup = false;

        if (!$lastCleanup || empty($lastCleanup['value'])) {
            $shouldCleanup = true;
        } else {
            $lastCleanupTime = strtotime($lastCleanup['value']);

            if (($now - $lastCleanupTime) >= $fiveDaysInSeconds) {
                $shouldCleanup = true;
            }
        }

        if (!$shouldCleanup) {
            return;
        }

        $stmtDelete = $pdo->prepare("
            DELETE FROM purchases
            WHERE status = 'expired'
            AND purchase_date < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmtDelete->execute();
        $deletedPurchases = $stmtDelete->rowCount();

        $stmtOrphanTickets = $pdo->prepare("
            DELETE t FROM tickets t
            WHERE NOT EXISTS (
                SELECT 1 FROM purchases p
                WHERE p.user_id = t.user_id
                AND p.showtime_id = t.showtime_id
            )
            AND t.created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmtOrphanTickets->execute();
        $deletedOrphanTickets = $stmtOrphanTickets->rowCount();

        $stmtOrphanFood = $pdo->prepare("
            DELETE FROM food_orders
            WHERE status = 'pending'
            AND order_date < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmtOrphanFood->execute();
        $deletedOrphanFood = $stmtOrphanFood->rowCount();

        $stmtOldLogs = $pdo->prepare("
            DELETE FROM ticket_logs
            WHERE released_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
        ");
        $stmtOldLogs->execute();
        $deletedOldLogs = $stmtOldLogs->rowCount();

        if ($lastCleanup && !empty($lastCleanup['value'])) {
            $stmtUpdate = $pdo->prepare("UPDATE site_config SET value = NOW(), updated_at = NOW() WHERE key_name = ?");
        } else {
            $stmtUpdate = $pdo->prepare("INSERT INTO site_config (key_name, value) VALUES (?, NOW())");
        }

        $stmtUpdate->execute([$lastCleanupKey]);

        error_log(sprintf(
            "🧹 Limpieza automática [%s]: %d compras expiradas, %d tickets huérfanos, %d pedidos comida, %d logs antiguos eliminados",
            date('Y-m-d H:i:s'),
            $deletedPurchases,
            $deletedOrphanTickets,
            $deletedOrphanFood,
            $deletedOldLogs
        ));
    } catch (Exception $e) {
        error_log("❌ Error en limpieza automática periódica: " . $e->getMessage());
    }
}

// ============================================
// 🎬 OCULTADO AUTOMÁTICO DE PELÍCULAS SIN FUNCIONES VIGENTES
// ============================================
// Una película se oculta automáticamente si no tiene funciones
// disponibles (fecha+hora >= ahora). Se ejecuta en cada petición.
function autoHideMoviesWithoutActiveShows($pdo)
{
    try {
        $stmt = $pdo->query("
            UPDATE movies m
            LEFT JOIN showtimes s
                ON s.movie_id = m.id
               AND CONCAT(s.show_date, ' ', s.show_time) >= NOW()
            SET m.is_active = 0
            WHERE m.is_active = 1
              AND s.id IS NULL
        ");
        $hidden = $stmt->rowCount();
        if ($hidden > 0) {
            error_log("🎬 Ocultadas automáticamente $hidden película(s) sin funciones vigentes");
        }
    } catch (Exception $e) {
        // No bloquear el sitio (instalación nueva, tabla ausente, etc.)
    }
}