<?php
// ============================================
// MÓDULO: GESTIÓN DE HORARIOS - LISTA
// ============================================

// Obtener datos para funciones
$search_showtime = isset($_GET['search_showtime']) ? trim($_GET['search_showtime']) : '';
$showtimes_sql = "
    SELECT s.*, m.title as movie_title, m.duration, COALESCE(m.is_active, 0) as movie_active,
           r.name as room_name, COALESCE(s.format, '2D') as format
    FROM showtimes s
    LEFT JOIN movies m ON s.movie_id = m.id
    JOIN rooms r ON s.room_id = r.id
    WHERE 1=1
";
if (!empty($search_showtime)) {
    $showtimes_sql .= " AND m.title LIKE ?";
    $showtimes_sql .= " ORDER BY m.title ASC, s.show_date DESC, s.show_time";
    $stmt = $pdo->prepare($showtimes_sql);
    $stmt->execute(['%' . $search_showtime . '%']);
} else {
    $showtimes_sql .= " ORDER BY m.title ASC, s.show_date DESC, s.show_time";
    $stmt = $pdo->query($showtimes_sql);
}
$showtimes = $stmt->fetchAll();

// Verificar si hay mensaje de error o éxito
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';
?>

<!-- Lista de Funciones con buscador -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">Funciones (<?= count($showtimes) ?> registrados)</h3>
        <a href="index.php?tab=showtimes&action=register" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg transition-all duration-200 shadow-sm hover:shadow-md text-sm no-underline">
            <i class="fas fa-plus-circle"></i> Registrar Función
        </a>
    </div>
    <div class="admin-card-body">
        <!-- Buscador -->
        <div class="search-box" data-tab="showtimes">
            <div class="search-group">
                <label><i class="fas fa-search mr-1"></i> Buscar por Película</label>
                <input type="text" id="searchShowtime" placeholder="Ej: Spider-Man, Avatar..." value="<?= htmlspecialchars($search_showtime) ?>">
            </div>
            <div class="search-actions">
                <button class="btn-search" id="searchBtn"><i class="fas fa-search"></i> Buscar</button>
                <button class="btn-clear" id="clearBtn"><i class="fas fa-times"></i> Limpiar</button>
            </div>
        </div>

        <!-- Contenedor de tabla con overflow-x para scroll horizontal -->
        <div class="overflow-x-auto">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Película</th>
                        <th>Sala</th>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Formato</th>
                        <th>Adulto</th>
                        <th>Niño</th>
                        <th>Abuelo</th>
                        <th>Idioma</th>
                        <th>Promociones</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $rowIndex = 0; ?>
                    <?php foreach ($showtimes as $s):
                        $promo_labels = [];
                        $promotions = $s['promotions'] ? explode(',', $s['promotions']) : [];
                        if (in_array('lunes_mitad', $promotions)) $promo_labels[] = 'Lunes ½ Precio';
                        if (in_array('preventa', $promotions)) $promo_labels[] = 'Preventa';

                        $movie_exists = $s['movie_title'] !== null;
                        $is_inactive = $s['is_active'] == 0;
                        $showtime_ts = strtotime($s['show_date'] . ' ' . $s['show_time']);
                        $is_past = $showtime_ts !== false && $showtime_ts < time();
                        $language = $s['language'] ?? 'español';
                        $lang_label = $language == 'español' ? 'Español' : 'Subtítulos';
                        $lang_class = $language == 'español' ? 'espanol' : 'subtitulos';

                        $price_adult = $s['price_adult'] ?? $s['price'] ?? 0;
                        $price_child = $s['price_child'] ?? 0;
                        $price_senior = $s['price_senior'] ?? 0;
                        $enable_child = $s['enable_child_price'] ?? 1;
                        $enable_senior = $s['enable_senior_price'] ?? 1;
                        $format = $s['format'] ?? '2D';

                        $formatClass = 'format-2d';
                        if (!empty($format)) {
                            $formatLower = strtolower($format);
                            $formatClass = 'format-' . str_replace(' ', '-', $formatLower);
                        }
                    ?>
                        <tr class="<?= $rowIndex % 2 === 0 ? 'row-even' : 'row-odd' ?> <?= $is_inactive ? 'showtime-inactive' : '' ?> <?= $is_past ? 'showtime-past' : '' ?>">
                            <td class="font-medium <?= $movie_exists ? 'text-gray-900' : 'movie-deleted' ?>">
                                <?= htmlspecialchars($s['movie_title'] ?? 'Película eliminada') ?>
                                <?php if ($is_inactive): ?><span class="text-xs text-gray-500 ml-1">(Inactiva)</span><?php endif; ?>
                                <?php if ($is_past): ?><span class="text-xs text-gray-500 ml-1">(Cumplida)</span><?php endif; ?>
                                <?php if (!$movie_exists): ?><span class="text-xs text-gray-500 ml-1">(Eliminada)</span><?php endif; ?>
                            </td>
                            <td class="text-gray-600"><?= htmlspecialchars($s['room_name']) ?></td>
                            <td class="text-gray-600"><?= formatDateShort($s['show_date']) ?></td>
                            <td class="font-semibold text-indigo-600 time-display"><?= formatTimeVenezuela($s['show_time']) ?></td>
                            <td><span class="format-badge <?= $formatClass ?>"><?= htmlspecialchars($format) ?></span></td>
                            <td class="font-semibold text-green-600"><?= formatCurrency($price_adult, $siteConfig) ?></td>
                            <td class="font-semibold <?= $enable_child ? 'text-green-600' : 'text-gray-400' ?>">
                                <?= $enable_child ? formatCurrency($price_child, $siteConfig) : '—' ?>
                            </td>
                            <td class="font-semibold <?= $enable_senior ? 'text-green-600' : 'text-gray-400' ?>">
                                <?= $enable_senior ? formatCurrency($price_senior, $siteConfig) : '—' ?>
                            </td>
                            <td><span class="language-badge <?= $lang_class ?>"><?= $lang_label ?></span></td>
                            <td>
                                <?php foreach ($promo_labels as $label): ?>
                                    <span class="promotion-tag <?= strpos($label, 'Lunes') !== false ? 'lunes' : 'preventa' ?>"><?= $label ?></span>
                                <?php endforeach; ?>
                                <?php if (empty($promo_labels)): ?><span class="text-gray-500 text-xs">—</span><?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($s['is_active']): ?>
                                    <span class="status-badge status-active">Activo</span>
                                <?php else: ?>
                                    <span class="status-badge status-inactive">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="table-actions">
                                    <?php if ($s['is_active']): ?>
<a href="index.php?tab=showtimes&action=register&edit_showtime_id=<?= htmlspecialchars($s['id']) ?>&csrf_token=<?= htmlspecialchars($csrf_token) ?>"
                                            class="action-btn action-edit" title="Editar función"><i class="fas fa-pen"></i></a>
                                    <?php endif; ?>
                                    <a href="modules/showtime/showtime_actions.php?action=toggle_showtime&id=<?= htmlspecialchars($s['id']) ?>&csrf_token=<?= htmlspecialchars($csrf_token) ?>&return=../../index.php?tab=showtimes"
                                       class="action-btn action-toggle"
                                       data-confirm="¿Cambiar estado de esta función?">
                                        <i class="fas <?= $s['is_active'] ? 'fa-eye-slash' : 'fa-eye' ?>" title="<?= $s['is_active'] ? 'Ocultar función' : 'Mostrar función' ?>"></i>
                                    </a>
                                    <a href="modules/showtime/showtime_actions.php?action=delete_showtime&id=<?= htmlspecialchars($s['id']) ?>&csrf_token=<?= htmlspecialchars($csrf_token) ?>&return=../../index.php?tab=showtimes"
                                       class="action-btn action-delete"
                                       data-confirm="¿Eliminar esta función?">
<i class="fas fa-trash" title="Eliminar función"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php $rowIndex++; ?>
                    <?php endforeach; ?>
                    <?php if (empty($showtimes)): ?>
                        <tr>
                            <td colspan="12" class="text-center py-8 text-gray-500">
                                <p class="text-4xl mb-2">🕐</p>
                                <p>No se encontraron funciones<?= !empty($search_showtime) ? ' con el filtro aplicado' : '' ?>.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>