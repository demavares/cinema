<?php
// ============================================
// MÓDULO: GESTIÓN DE PELÍCULAS - LISTA
// ============================================

// Obtener datos para películas
$search_title = isset($_GET['search_title']) ? trim($_GET['search_title']) : '';
$movies_sql = "SELECT * FROM movies WHERE 1=1";
$movies_params = [];
if (!empty($search_title)) {
    $movies_sql .= " AND title LIKE ?";
    $movies_params[] = '%' . $search_title . '%';
}
$movies_sql .= " ORDER BY title ASC";
$stmt = $pdo->prepare($movies_sql);
$stmt->execute($movies_params);
$movies = $stmt->fetchAll();

// Verificar si hay mensaje de error o éxito
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';
$total_movies = count($movies);
?>

<!-- Lista de Películas con buscador -->
<div class="admin-card">
<div class="admin-card-header">
    <h3 class="admin-card-title">📋 Películas (<?= $total_movies ?> registrados)</h3>
    <a href="index.php?tab=movies&action=register" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg transition-all duration-200 shadow-sm hover:shadow-md text-sm no-underline">
        <i class="fas fa-plus-circle"></i> Registrar Película
    </a>
</div
    <div class="admin-card-body">
        <!-- Buscador -->
        <div class="search-box" data-tab="movies">
            <div class="search-group">
                <label><i class="fas fa-search mr-1"></i> Buscar por Título</label>
                <input type="text" id="searchTitle" placeholder="Ej: Spider-Man, Avatar..." value="<?= htmlspecialchars($search_title) ?>">
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
                        <th>Póster</th>
                        <th>Título</th>
                        <th>Año</th>
                        <th>Duración</th>
                        <th>Clasificación</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $rowIndex = 0; ?>
                    <?php foreach($movies as $m): ?>
                        <tr class="<?= $rowIndex % 2 === 0 ? 'row-even' : 'row-odd' ?>">
                            <td>
                                <?php if (!empty($m['poster_url'])): ?>
                                    <img src="<?= htmlspecialchars($m['poster_url']) ?>" alt="<?= htmlspecialchars($m['title']) ?>"
                                         class="w-10 h-14 object-cover rounded bg-gray-200 shadow-sm">
                                <?php else: ?>
                                    <div class="w-10 h-14 bg-gray-200 rounded flex items-center justify-center text-gray-400 text-sm">🎬</div>
                                <?php endif; ?>
                            </td>
                            <td class="font-medium text-gray-900"><?= htmlspecialchars($m['title']) ?></td>
                            <td class="text-gray-600"><?= htmlspecialchars($m['year'] ?? '-') ?></td>
                            <td class="text-gray-600"><?= $m['duration'] ? htmlspecialchars($m['duration']) . ' min' : '-' ?></td>
                            <td>
                                <?php if($m['classification']): ?>
                                    <?php if(strpos($m['classification'], 'A') !== false): ?>
                                        <span class="badge-a"><?= htmlspecialchars($m['classification']) ?></span>
                                    <?php elseif(strpos($m['classification'], 'B') !== false): ?>
                                        <span class="badge-b"><?= htmlspecialchars($m['classification']) ?></span>
                                    <?php elseif(strpos($m['classification'], 'C') !== false): ?>
                                        <span class="badge-c"><?= htmlspecialchars($m['classification']) ?></span>
                                    <?php else: ?>
                                        <span class="badge-b"><?= htmlspecialchars($m['classification']) ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-gray-400 text-xs">No definida</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if($m['is_active']): ?>
                                    <span class="status-badge status-active">Activa</span>
                                <?php else: ?>
                                    <span class="status-badge status-inactive">Oculta</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="table-actions">
<a href="index.php?tab=movies&action=register&edit_movie_id=<?= htmlspecialchars($m['id']) ?>&csrf_token=<?= htmlspecialchars($csrf_token) ?>"
                                        class="action-btn action-edit" title="Editar película"><i class="fas fa-pen"></i></a>
                                   <a href="modules/movie/movie_actions.php?action=update_movie&id=<?= htmlspecialchars($m['id']) ?>&csrf_token=<?= htmlspecialchars($csrf_token) ?>&return=../../index.php?tab=movies" class="action-btn action-update" onclick="return confirm('¿Actualizar los datos de la película desde TMDb?')">
    <i class="fas fa-sync-alt" title="Actualizar datos desde TMDb"></i>
</a>
<a href="modules/movie/movie_actions.php?action=toggle_movie&id=<?= htmlspecialchars($m['id']) ?>&csrf_token=<?= htmlspecialchars($csrf_token) ?>&return=../../index.php?tab=movies" class="action-btn action-toggle" onclick="return confirm('¿Cambiar estado de esta película?')">
    <i class="fas <?= $m['is_active'] ? 'fa-eye-slash' : 'fa-eye' ?>" title="<?= $m['is_active'] ? 'Ocultar película' : 'Mostrar película' ?>"></i>
</a>
<a href="modules/movie/movie_actions.php?action=delete_movie&id=<?= htmlspecialchars($m['id']) ?>&csrf_token=<?= htmlspecialchars($csrf_token) ?>&return=../../index.php?tab=movies" class="action-btn action-delete" onclick="return confirm('¿Eliminar esta película permanentemente? Se eliminarán también todos los horarios y boletos asociados.')">
<i class="fas fa-trash" title="Eliminar película"></i>
</a>
                                </div>
                            </td>
                        </tr>
                        <?php $rowIndex++; ?>
                    <?php endforeach; ?>
                    <?php if(empty($movies)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-8 text-gray-500">
                                <p class="text-4xl mb-2">🎬</p>
                                <p>No se encontraron películas<?= !empty($search_title) ? ' con el filtro aplicado' : '' ?>.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>