<?php
// ============================================
// MÓDULO: INFORME — REPORTE DE FUNCIONES
// ============================================

$report_start_date = isset($_GET['report_start_date']) ? trim($_GET['report_start_date']) : '';
$report_end_date = isset($_GET['report_end_date']) ? trim($_GET['report_end_date']) : '';

// ✅ UNIFICADO: Solo contar tickets confirmados (pagados).
// Los tickets 'hold' temporales no deben aparecer en las estadísticas de ventas.
$report_sql = "
    SELECT
        s.id as showtime_id,
        COALESCE(m.title, 'Película eliminada') as movie_title,
        r.name as room_name,
        s.show_date, s.show_time, m.duration,
        DATE_ADD(CONCAT(s.show_date, ' ', s.show_time), INTERVAL m.duration MINUTE) as end_time,
        (SELECT COUNT(*) FROM tickets t WHERE t.showtime_id = s.id AND t.status = 'confirmed') +
        (SELECT COALESCE(SUM(ticket_count), 0) FROM ticket_logs tl WHERE tl.showtime_id = s.id) as tickets_sold,
        (SELECT COALESCE(SUM(t.price_paid), 0) FROM tickets t WHERE t.showtime_id = s.id AND t.status = 'confirmed') +
        (SELECT COALESCE(SUM(tl.ticket_count * s.price), 0) FROM ticket_logs tl WHERE tl.showtime_id = s.id) as total_revenue,
        s.price as original_price, s.half_price_monday, s.promotions, s.is_active, s.language
    FROM showtimes s
    LEFT JOIN movies m ON s.movie_id = m.id
    JOIN rooms r ON s.room_id = r.id
    WHERE 1=1
";

$params = [];
if (!empty($report_start_date) && !empty($report_end_date)) {
    $report_sql .= " AND s.show_date BETWEEN ? AND ?";
    $params[] = $report_start_date;
    $params[] = $report_end_date;
} elseif (!empty($report_start_date)) {
    $report_sql .= " AND s.show_date >= ?";
    $params[] = $report_start_date;
} elseif (!empty($report_end_date)) {
    $report_sql .= " AND s.show_date <= ?";
    $params[] = $report_end_date;
}

$report_sql .= " GROUP BY s.id ORDER BY s.show_date DESC, s.show_time DESC";

try {
    $stmt = $pdo->prepare($report_sql);
    $stmt->execute($params);
    $report_rows = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error al consultar informe: " . $e->getMessage());
    echo '<div class="admin-alert admin-alert-error"><i class="fas fa-exclamation-circle"></i> Error al cargar el informe. Por favor, intente nuevamente.</div>';
    return;
}

$has_filter = !empty($report_start_date) || !empty($report_end_date);
$report_total_tickets = 0;
$report_total_revenue = 0;
foreach ($report_rows as $h) {
    $report_total_tickets += $h['tickets_sold'];
    $report_total_revenue += $h['total_revenue'];
}
$report_average = count($report_rows) > 0 ? $report_total_revenue / count($report_rows) : 0;
?>

<div class="admin-content-header">
    <h1 class="admin-content-title">Informe</h1>
    <p class="admin-content-subtitle">Reporte de funciones con ventas de boletos e ingresos</p>
</div>

<!-- Filtros -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><i class="fas fa-filter mr-1"></i> Filtros de Fecha</h3>
    </div>
    <div class="admin-card-body">
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <input type="hidden" name="tab" value="report">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Desde</label>
                <input type="date" name="report_start_date" value="<?= htmlspecialchars($report_start_date) ?>"
                       class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Hasta</label>
                <input type="date" name="report_end_date" value="<?= htmlspecialchars($report_end_date) ?>"
                       class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
            </div>
            <button type="submit" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-4 rounded-lg transition-colors text-sm">
                <i class="fas fa-search"></i> Filtrar
            </button>
            <?php if ($has_filter): ?>
                <a href="index.php?tab=report" class="inline-flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-700 border border-gray-300 font-semibold py-2.5 px-4 rounded-lg transition-colors text-sm no-underline">
                    <i class="fas fa-times"></i> Limpiar filtros
                </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Resumen -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 my-6">
    <div class="stat-card">
        <div class="stat-card-icon bg-indigo-100 text-indigo-600">
            <i class="fas fa-film"></i>
        </div>
        <div class="stat-card-info">
            <span class="stat-card-value"><?= count($report_rows) ?></span>
            <span class="stat-card-label">Funciones</span>
        </div>
        <div class="stat-card-change positive">
            <i class="fas fa-calendar-check"></i> En el periodo
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon bg-green-100 text-green-600">
            <i class="fas fa-ticket-alt"></i>
        </div>
        <div class="stat-card-info">
            <span class="stat-card-value"><?= number_format($report_total_tickets) ?></span>
            <span class="stat-card-label">Boletos vendidos</span>
        </div>
        <div class="stat-card-change positive">
            <i class="fas fa-check-circle"></i> Confirmados
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon bg-yellow-100 text-yellow-600">
            <i class="fas fa-money-bill-wave"></i>
        </div>
        <div class="stat-card-info">
            <span class="stat-card-value"><?= formatCurrency($report_total_revenue, $siteConfig) ?></span>
            <span class="stat-card-label">Ingresos totales</span>
        </div>
        <div class="stat-card-change positive">
            <i class="fas fa-arrow-up"></i> Acumulado
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon bg-blue-100 text-blue-600">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="stat-card-info">
            <span class="stat-card-value"><?= formatCurrency($report_average, $siteConfig) ?></span>
            <span class="stat-card-label">Promedio por función</span>
        </div>
        <div class="stat-card-change positive">
            <i class="fas fa-percentage"></i> Estimado
        </div>
    </div>
</div>

<!-- Tabla -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><i class="fas fa-table mr-1"></i> Detalle por Función</h3>
    </div>
    <div class="admin-card-body">
        <div class="overflow-x-auto">
            <?php if (empty($report_rows)): ?>
                <div class="text-center py-12 text-gray-500">
                    <p class="text-4xl mb-2">📭</p>
                    <p>No hay funciones registradas<?= $has_filter ? ' en el período seleccionado' : '' ?>.</p>
                </div>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Película</th>
                            <th>Sala</th>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th class="text-center">Boletos</th>
                            <th class="text-right">Precio</th>
                            <th class="text-right">Total</th>
                            <th>Idioma</th>
                            <th>Promociones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rowIndex = 0; ?>
                        <?php foreach ($report_rows as $h):
                            $promotions = $h['promotions'] ? explode(',', $h['promotions']) : [];
                            $promo_labels = [];
                            if (in_array('lunes_mitad', $promotions)) $promo_labels[] = 'Lunes ½ Precio';
                            if (in_array('preventa', $promotions)) $promo_labels[] = 'Preventa';

                            $display_price = $h['half_price_monday'] ? $h['original_price'] / 2 : $h['original_price'];
                            $has_tickets = $h['tickets_sold'] > 0;
                            $is_deleted = $h['movie_title'] == 'Película eliminada';
                            $movie_exists = $h['movie_title'] !== null && !$is_deleted;
                            $language = $h['language'] ?? 'español';
                            $lang_label = $language == 'español' ? 'Español' : 'Subtítulos';
                            $lang_class = $language == 'español' ? 'espanol' : 'subtitulos';
                        ?>
                            <tr class="<?= $rowIndex % 2 === 0 ? 'row-even' : 'row-odd' ?> <?= $h['is_active'] == 0 ? 'showtime-inactive' : '' ?>">
                                <td class="font-medium <?= $movie_exists ? 'text-gray-900' : 'movie-deleted' ?>">
                                    <?= htmlspecialchars($h['movie_title']) ?>
                                    <?php if ($h['is_active'] == 0): ?><span class="text-xs text-gray-500 ml-1">(Inactiva)</span><?php endif; ?>
                                    <?php if ($is_deleted): ?><span class="text-xs text-gray-500 ml-1">(Eliminada)</span><?php endif; ?>
                                </td>
                                <td class="text-gray-600"><?= htmlspecialchars($h['room_name']) ?></td>
                                <td class="text-gray-600"><?= formatDateShort($h['show_date']) ?></td>
                                <td class="font-semibold text-indigo-600 time-display"><?= formatTimeVenezuela($h['show_time']) ?></td>
                                <td class="text-center">
                                    <span class="tickets-sold-badge <?= $has_tickets ? 'sold' : 'none' ?>">
                                        <?= number_format($h['tickets_sold']) ?>
                                    </span>
                                </td>
                                <td class="text-right text-gray-600"><?= formatCurrency($display_price, $siteConfig) ?></td>
                                <td class="text-right font-bold <?= $h['total_revenue'] > 0 ? 'text-yellow-600' : 'text-gray-400' ?>">
                                    <?= formatCurrency($h['total_revenue'], $siteConfig) ?>
                                </td>
                                <td><span class="language-badge <?= $lang_class ?>"><?= $lang_label ?></span></td>
                                <td>
                                    <?php foreach ($promo_labels as $label): ?>
                                        <span class="promotion-tag <?= strpos($label, 'Lunes') !== false ? 'lunes' : 'preventa' ?>"><?= $label ?></span>
                                    <?php endforeach; ?>
                                    <?php if (empty($promo_labels)): ?><span class="text-gray-500 text-xs">—</span><?php endif; ?>
                                </td>
                            </tr>
                            <?php $rowIndex++; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>