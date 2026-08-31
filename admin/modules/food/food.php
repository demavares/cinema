<?php
// ============================================
// MÓDULO: COMIDA — LISTADO
// ============================================
try {
    $food_items = $pdo->query("SELECT f.*, c.name as category_name FROM food_items f LEFT JOIN food_categories c ON f.category_id = c.id ORDER BY c.name ASC, f.name ASC")->fetchAll();
} catch (PDOException $e) {
    error_log("Error al consultar productos: " . $e->getMessage());
    echo '<div class="admin-alert admin-alert-error"><i class="fas fa-exclamation-circle"></i> Error al cargar los productos. Por favor, intente nuevamente.</div>';
    return;
}

$siteRoot = dirname(__DIR__, 3);
$total_products = count($food_items);
?>

<!-- Lista de Productos -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">Productos (<?= $total_products ?> registrados)</h3>
        <a href="index.php?tab=food&action=register" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg transition-all duration-200 shadow-sm hover:shadow-md text-sm no-underline">
            <i class="fas fa-plus-circle"></i> Registrar Producto
        </a>
    </div>
    <div class="admin-card-body">
        <!-- Contenedor de tabla con overflow-x para scroll horizontal -->
        <div class="overflow-x-auto">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Imagen</th>
                        <th>Nombre</th>
                        <th>Categoría</th>
                        <th>Precio</th>
                        <th>Descripción</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($food_items)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-8 text-gray-500">
                                <i class="fas fa-utensils text-2xl mb-2 block text-gray-300"></i>
                                <p>No hay productos registrados. Agrega uno con el botón «Registrar Producto».</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($food_items as $f): ?>
                            <?php $has_image = !empty($f['image_url']) && file_exists($siteRoot . '/' . $f['image_url']); ?>
                            <tr>
                                <td>
                                    <?php if ($has_image): ?>
                                        <?php $img_src = preg_match('#^(https?:)?//#', $f['image_url']) ? $f['image_url'] : '../' . $f['image_url']; ?>
                                        <img src="<?= htmlspecialchars($img_src) . '?v=' . time() ?>" alt="<?= htmlspecialchars($f['name']) ?>" class="w-12 h-12 object-cover rounded bg-gray-900 shadow">
                                    <?php else: ?>
                                        <div class="w-12 h-12 bg-gray-100 rounded flex items-center justify-center text-gray-400">
                                            <i class="fas fa-utensils"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="font-medium text-gray-900"><?= htmlspecialchars($f['name']) ?></td>
                                <td class="text-gray-500"><?= htmlspecialchars($f['category_name'] ?? '-') ?></td>
                                <td class="font-semibold text-green-600"><?= formatCurrency($f['price'], $siteConfig) ?></td>
                                <td class="text-gray-500 text-sm max-w-xs truncate"><?= htmlspecialchars($f['description'] ?? '-') ?></td>
                                <td class="text-center">
                                    <?php if ($f['is_active']): ?>
                                        <span class="status-badge status-active"><i class="fas fa-circle"></i> Activo</span>
                                    <?php else: ?>
                                        <span class="status-badge status-inactive"><i class="fas fa-circle"></i> Oculto</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="table-actions">
                                        <a href="index.php?tab=food&action=register&edit_food_id=<?= $f['id'] ?>" class="action-btn action-edit" title="Editar producto">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        <a href="modules/food/food_actions.php?action=toggle_food&id=<?= $f['id'] ?>&csrf_token=<?= htmlspecialchars($csrf_token) ?>"
                                           class="action-btn <?= $f['is_active'] ? 'action-toggle' : 'action-edit' ?>" data-confirm="¿Cambiar estado de este producto?" title="<?= $f['is_active'] ? 'Ocultar' : 'Publicar' ?>">
                                            <i class="fas <?= $f['is_active'] ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                                        </a>
                                        <a href="modules/food/food_actions.php?action=delete_food&id=<?= $f['id'] ?>&csrf_token=<?= htmlspecialchars($csrf_token) ?>"
                                           class="action-btn action-delete" data-delete-food data-food-name="<?= htmlspecialchars($f['name']) ?>" title="Eliminar producto">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>