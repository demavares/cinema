<?php
require_once 'user_auth.php';

$siteConfig = getSiteConfig($pdo);
$pageTitle = "Mis Boletos - " . ($siteConfig['site_name'] ?? 'Cinema Pro');
$activePage = 'tickets';

$stmt = $pdo->prepare("
    SELECT p.id, p.sale_number, p.seats, p.total_tickets, p.total_amount, p.purchase_date, p.status,
           m.id AS movie_id, m.title AS movie_title, m.poster_url, m.duration,
           r.name AS room_name, s.show_date, s.show_time, s.format, s.promotions
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

            // Promociones del showtime (lunes_mitad / preventa)
            $purchasePromos = !empty($p['promotions']) ? explode(',', $p['promotions']) : [];
            $hasMondayPromo = in_array('lunes_mitad', $purchasePromos);
            $hasPresalePromo = in_array('preventa', $purchasePromos);
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

                    <?php if ($hasMondayPromo || $hasPresalePromo): ?>
                        <div class="promo-list">
                            <?php if ($hasMondayPromo): ?>
                                <span class="promo-tag monday" title="Boleto comprado con promoción de Lunes a mitad de precio">
                                    <span class="promo-dot"></span> Comprado en Lunes a mitad de precio
                                </span>
                            <?php endif; ?>
                            <?php if ($hasPresalePromo): ?>
                                <span class="promo-tag presale" title="Boleto comprado en preventa">
                                    <span class="promo-dot"></span> Comprado en Preventa
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($functionDone): ?>
                        <div class="function-done-note">
                            <i class="fas fa-flag-checkered"></i>Función ya realizada
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($seatsArray)): ?>
                        <div class="seat-list">
                            <?php foreach ($seatsArray as $seat): ?>
                                <span class="seat-item">Asiento: <?= htmlspecialchars(trim($seat)) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="flex items-center justify-between gap-3 flex-wrap mt-4">
                        <div class="flex items-center gap-3">
                            <span class="purchase-id">#<?= str_pad($p['sale_number'] ?? $p['id'], 8, '0', STR_PAD_LEFT) ?></span>
                            <span class="text-xs text-gray-500">
                                <i class="far fa-clock mr-1"></i>Comprado: <?= htmlspecialchars(formatDateShort($p['purchase_date'])) ?> - <?= htmlspecialchars(strtolower(formatTimeVenezuela($p['purchase_date']))) ?>
                            </span>
                        </div>
                        <div class="ticket-actions">
                            <span class="ticket-total"><?= formatCurrency($p['total_amount'], $siteConfig) ?></span>
                            <?php if ($completed): ?>
                                <a href="ticket_pdf.php?purchase_id=<?= (int)$p['id'] ?>" class="btn-print" target="_blank" rel="noopener" title="Descargar comprobante en PDF">
                                    <i class="fas fa-print"></i>Imprimir comprobante
                                </a>
                            <?php else: ?>
                                <span class="btn-print" disabled title="Solo las compras completadas tienen comprobante PDF">
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