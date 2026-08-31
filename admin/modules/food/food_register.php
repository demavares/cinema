<?php
// ============================================
// MÓDULO: COMIDA — REGISTRO / EDICIÓN
// ============================================
$edit_food_id = isset($_GET['edit_food_id']) ? filter_var($_GET['edit_food_id'], FILTER_VALIDATE_INT) : null;
$edit_food = null;

if ($edit_food_id && $edit_food_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM food_items WHERE id = ?");
    $stmt->execute([$edit_food_id]);
    $edit_food = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$edit_food) {
        $edit_food_id = null;
    }
}

$food_categories = $pdo->query("SELECT * FROM food_categories ORDER BY name ASC")->fetchAll();
$siteRoot = dirname(__DIR__, 3);
$has_current_image = $edit_food && !empty($edit_food['image_url']) && file_exists($siteRoot . '/' . $edit_food['image_url']);
?>

<!-- Formulario Agregar/Editar Producto -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <?= $edit_food ? '✏️ Editar Producto' : '➕ Registrar Nuevo Producto' ?>
        </h3>
        <a href="index.php?tab=food" class="admin-card-link">
            <i class="fas fa-arrow-left"></i> Volver al listado
        </a>
    </div>
    <div class="admin-card-body">
        <div class="movie-form-help">
            <p>🖼️ Formatos de imagen admitidos: <strong>JPG, PNG, GIF, WEBP y SVG</strong>. Tamaño máximo: 2MB.</p>
            <p class="mt-1 warning">🔒 Los productos nuevos se registran como <strong>OCULTOS</strong> por defecto. Debes activarlos manualmente.</p>
            <p class="mt-1 danger">⚠️ Los campos con <strong class="field-required">*</strong> son obligatorios.</p>
        </div>

        <form action="modules/food/food_actions.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="action" value="<?= $edit_food ? 'edit_food' : 'add_food' ?>">
            <?php if ($edit_food): ?>
                <input type="hidden" name="food_id" value="<?= htmlspecialchars($edit_food['id']) ?>">
            <?php endif; ?>

            <!-- ============================================ -->
            <!-- FILA 1: Nombre del Producto (completo)        -->
            <!-- ============================================ -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del Producto <span class="field-required">*</span></label>
                <input type="text" name="food_name" required maxlength="100"
                       value="<?= $edit_food ? htmlspecialchars($edit_food['name']) : '' ?>"
                       placeholder="Ej: Palomitas Grandes"
                       class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <!-- ============================================ -->
            <!-- FILA 2: Categoría (completo)                  -->
            <!-- ============================================ -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Categoría <span class="field-required">*</span></label>
                <select name="category_id" required class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Seleccionar Categoría</option>
                    <?php foreach ($food_categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['id']) ?>" <?= ($edit_food && isset($edit_food['category_id']) && $edit_food['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($food_categories)): ?>
                    <p class="text-xs text-red-500 mt-1">No hay categorías disponibles. Agrégalas directamente en la base de datos.</p>
                <?php endif; ?>
            </div>

            <!-- ============================================ -->
            <!-- FILA 3: Precio (completo)                     -->
            <!-- ============================================ -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Precio <span class="field-required">*</span></label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm font-semibold"><?= htmlspecialchars($siteConfig['currency_symbol'] ?? '$') ?></span>
                    <input type="number" step="0.01" min="0.01" name="food_price" id="foodPriceInput" required
                           value="<?= $edit_food ? htmlspecialchars($edit_food['price']) : '' ?>"
                           placeholder="0.00"
                           class="w-full bg-gray-50 border border-gray-300 rounded-lg pl-7 pr-3 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>

            <!-- ============================================ -->
            <!-- FILA 4: Imagen del Producto                   -->
            <!-- ============================================ -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Imagen del Producto</label>
                <input type="file" name="food_image" id="foodImageInput" accept="image/*"
                       class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700">
                <img id="foodImagePreview" src="#" alt="Vista previa" style="display: none;" class="mt-3 h-16 w-16 object-cover rounded bg-gray-900">
                <?php if ($has_current_image): ?>
                    <div class="mt-3 flex items-center gap-4 bg-gray-50 p-3 rounded-lg border border-gray-200">
                        <?php $img_src = preg_match('#^(https?:)?//#', $edit_food['image_url']) ? $edit_food['image_url'] : '../' . $edit_food['image_url']; ?>
                        <img src="<?= htmlspecialchars($img_src) . '?v=' . time() ?>" alt="Imagen actual" class="h-16 w-16 object-cover rounded bg-gray-900">
                        <div class="flex-1">
                            <p class="text-xs text-gray-500">Imagen actual</p>
                            <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars(basename($edit_food['image_url'])) ?></p>
                            <button type="submit" name="remove_image" value="1" class="text-xs text-red-500 hover:text-red-700 transition-colors mt-1" data-confirm="¿Eliminar la imagen actual?">
                                <i class="fas fa-trash mr-1"></i> Eliminar imagen
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ============================================ -->
            <!-- FILA 5: Descripción (completa)                -->
            <!-- ============================================ -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                <textarea name="food_description" rows="4" placeholder="Descripción del producto..."
                          class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"><?= $edit_food ? htmlspecialchars($edit_food['description'] ?? '') : '' ?></textarea>
            </div>

            <!-- ============================================ -->
            <!-- FILA 6: Botón de envío (completo)             -->
            <!-- ============================================ -->
            <button type="submit" class="md:col-span-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-lg transition-colors shadow-md">
                <?= $edit_food ? 'Actualizar Producto' : 'Guardar Producto (Oculto)' ?>
            </button>
        </form>
    </div>
</div>