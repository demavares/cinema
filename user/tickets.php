<?php
require_once 'user_auth.php';

$siteConfig = getSiteConfig($pdo);
$pageTitle = "Mis Boletos - " . ($siteConfig['site_name'] ?? 'Cinema Pro');
$activePage = 'tickets';

$stmt = $pdo->prepare("
    SELECT p.id, p.seats, p.total_tickets, p.total_amount, p.purchase_date, p.status,
           m.id AS movie_id, m.title AS movie_title, m.poster_url, m.duration,
           r.name AS room_name, s.show_date, s.show_time, s.format
    FROM purchases p
    JOIN showtimes s ON p.showtime_id = s.id
    JOIN movies m ON s.movie_id = m.id
    JOIN rooms r ON s.room_id = r.id
    WHERE p.user_id = ? AND p.status = 'completed'
    ORDER BY p.purchase_date DESC
");
$stmt->execute([$_SESSION['user_id']]);
$purchases = $stmt->fetchAll();

$statusLabels = [
    'completed' => ['Completado', 'bg-green-100 text-green-800 border-green-300'],
    'pending' => ['Pendiente', 'bg-amber-100 text-amber-800 border-amber-300'],
    'expired' => ['Expirada', 'bg-gray-100 text-gray-600 border-gray-300'],
];

require_once 'includes/header.php';
?>
<style>
    body { background-color: #ffffff !important; color: #1f2937 !important; }
    .account-wrapper { max-width: 820px; margin: 0 auto; padding: 32px 16px; }
    .account-title { font-size: 1.5rem; font-weight: 800; color: #0f172a; }
    .account-subtitle { color: #6b7280; font-size: 0.9rem; }
    .ticket-row {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
        padding: 18px;
        margin-bottom: 16px;
        display: flex;
        gap: 16px;
        align-items: flex-start;
        transition: box-shadow 0.2s ease;
    }
    .ticket-row:hover { box-shadow: 0 8px 20px rgba(15, 23, 42, 0.12); }
    .ticket-poster {
        width: 64px;
        height: 96px;
        border-radius: 8px;
        object-fit: cover;
        flex-shrink: 0;
        background: #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        color: #94a3b8;
    }
    .ticket-title { font-weight: 800; color: #0f172a; font-size: 1.05rem; line-height: 1.3; }
    .ticket-meta { font-size: 0.85rem; color: #475569; margin-top: 4px; }
    .ticket-meta i { color: #6366f1; width: 16px; margin-right: 4px; }
    .seat-list { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
    .seat-item {
        display: inline-flex;
        align-items: center;
        font-weight: 600;
        color: #0f172a;
        background: #f1f5f9;
        padding: 2px 10px 2px 8px;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
        font-size: 0.8rem;
    }
    .ticket-total { font-weight: 800; color: #16a34a; font-size: 1.05rem; }
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 700;
        border: 1px solid;
    }
    .btn-print {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        color: #334155;
        padding: 9px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        transition: all 0.2s ease;
        cursor: pointer;
    }
    .btn-print:hover { border-color: #6366f1; color: #4f46e5; background: #eef2ff; }
    .btn-print:disabled { opacity: 0.4; cursor: not-allowed; }
    .empty-state { text-align: center; padding: 60px 20px; color: #6b7280; }
    .empty-state i { font-size: 3rem; color: #cbd5e1; margin-bottom: 16px; }
    .purchase-id {
        font-family: 'Courier New', monospace;
        background: #f1f5f9;
        padding: 2px 10px;
        border-radius: 6px;
        color: #4f46e5;
        font-size: 0.8rem;
        font-weight: 700;
        border: 1px solid #e2e8f0;
        white-space: nowrap;
    }
    .format-badge {
        display: inline-flex;
        align-items: center;
        padding: 1px 8px;
        border-radius: 5px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border: 1px solid #94a3b8;
        color: #475569;
        margin-left: 8px;
    }
    .function-done-note {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 10px;
        background: #f1f5f9;
        border: 1px dashed #94a3b8;
        color: #475569;
        font-size: 0.8rem;
        font-weight: 600;
        padding: 4px 12px;
        border-radius: 8px;
    }
    .function-done-note i { color: #6366f1; }
</style>

<div class="account-wrapper">
    <div class="flex items-center justify-between flex-wrap gap-3 mb-6">
        <div>
            <h1 class="account-title"><i class="fas fa-ticket-alt text-indigo-600 mr-2"></i>Mis Boletos</h1>
            <p class="account-subtitle">Tus compras, asientos y comprobantes.</p>
        </div>
        <a href="account.php" class="btn-print">
            <i class="fas fa-chevron-left"></i>Volver a Mi Cuenta
        </a>
    </div>

    <?php if (empty($purchases)): ?>
        <div class="account-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;">
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <p class="font-bold text-gray-800 text-lg">Aún no tienes boletos</p>
                <p class="text-sm">Cuando compres entradas, aquí podrás verlas e imprimir tus comprobantes.</p>
                <a href="../index.php" class="inline-block mt-4 text-indigo-600 font-semibold hover:underline">
                    <i class="fas fa-film mr-1"></i>Explorar cartelera
                </a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($purchases as $p): ?>
            <?php
            $badge = $statusLabels[$p['status']] ?? $statusLabels['expired'];
            $seatsArray = array_filter(array_map('trim', explode(',', $p['seats'] ?? '')));
            $completed = $p['status'] === 'completed';
            $posterOk = !empty($p['poster_url']);

            // La función se considera realizada solo cuando ya terminó la película (hora + duración)
            $functionDone = false;
            $durationMin = intval($p['duration'] ?? 0);
            if ($durationMin > 0 && !empty($p['show_date']) && !empty($p['show_time'])) {
                $functionEndTs = strtotime($p['show_date'] . ' ' . $p['show_time']) + ($durationMin * 60);
                $functionDone = $functionEndTs < time();
            }
            ?>
            <div class="ticket-row">
                <div class="flex flex-col items-center gap-2">
                    <?php if ($posterOk): ?>
                        <img src="<?= htmlspecialchars($p['poster_url']) ?>" alt="<?= htmlspecialchars($p['movie_title']) ?>"
                             class="ticket-poster" data-error-fallback>
                        <span class="ticket-poster" style="display:none;">🎬</span>
                    <?php else: ?>
                        <span class="ticket-poster">🎬</span>
                    <?php endif; ?>
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-2 flex-wrap">
                        <span class="ticket-title"><?= htmlspecialchars($p['movie_title']) ?>
                            <span class="format-badge"><?= htmlspecialchars($p['format'] ?? '2D') ?></span>
                        </span>
                        <span class="status-badge <?= $badge[1] ?>">
                            <?= $badge[0] ?>
                        </span>
                    </div>

                    <div class="ticket-meta">
                        <i class="fas fa-door-open"></i><?= htmlspecialchars($p['room_name']) ?>
                        <span class="mx-1.5 text-gray-300">·</span>
                        <i class="fas fa-calendar-day"></i><?= htmlspecialchars(formatDateShort($p['show_date'])) ?>
                        <span class="mx-1.5 text-gray-300">·</span>
                        <i class="fas fa-clock"></i><?= htmlspecialchars(formatTimeVenezuela($p['show_time'])) ?>
                    </div>

                    <?php if ($functionDone): ?>
                        <div class="function-done-note">
                            <i class="fas fa-flag-checkered"></i>Función ya realizada
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($seatsArray)): ?>
                        <div class="seat-list">
                            <?php foreach ($seatsArray as $seat): ?>
                                <span class="seat-item"><?= htmlspecialchars(trim($seat)) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="flex items-center justify-between gap-3 flex-wrap mt-4">
                        <div class="flex items-center gap-3">
                            <span class="purchase-id">#<?= str_pad((int)$p['id'], 8, '0', STR_PAD_LEFT) ?></span>
                            <span class="text-xs text-gray-500">
                                <i class="far fa-clock mr-1"></i><?= htmlspecialchars(date('d/m/Y H:i', strtotime($p['purchase_date']))) ?>
                            </span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="ticket-total"><?= formatCurrency($p['total_amount'], $siteConfig) ?></span>
                            <?php if ($completed): ?>
                                <a href="../confirmation.php?purchase_id=<?= (int)$p['id'] ?>" class="btn-print" title="Ver e imprimir comprobante">
                                    <i class="fas fa-print"></i>Imprimir comprobante
                                </a>
                            <?php else: ?>
                                <span class="btn-print" disabled title="Solo las compras completadas tienen comprobante">
                                    <i class="fas fa-print"></i>Imprimir comprobante
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
</body>
</html>