<?php
// ============================================
// MÓDULO: REGISTRO/EDICIÓN DE HORARIOS
// ============================================

$rooms = $pdo->query("SELECT * FROM rooms ORDER BY name ASC")->fetchAll();
$movies_ordered = $pdo->query("SELECT * FROM movies WHERE is_active = 1 ORDER BY title ASC")->fetchAll();

$formats_stmt = $pdo->query("SELECT DISTINCT format FROM showtimes WHERE format IS NOT NULL AND format != '' ORDER BY format ASC");
$formatos_bd = $formats_stmt->fetchAll(PDO::FETCH_COLUMN);
$formatos = array_unique(array_merge(['2D', '3D', 'IMAX', 'IMAX 3D', '4DX', 'ScreenX', 'D-BOX'], $formatos_bd));
sort($formatos);

// Cargar datos de edición
$edit_showtime = null;
if (isset($_GET['edit_showtime_id']) && filter_var($_GET['edit_showtime_id'], FILTER_VALIDATE_INT)) {
    $stmt = $pdo->prepare("SELECT * FROM showtimes WHERE id = ?");
    $stmt->execute([intval($_GET['edit_showtime_id'])]);
    $edit_showtime = $stmt->fetch();
    if ($edit_showtime && $edit_showtime['promotions']) {
        $edit_showtime['promotions_array'] = explode(',', $edit_showtime['promotions']);
    }
    if ($edit_showtime && !isset($edit_showtime['language'])) {
        $edit_showtime['language'] = 'español';
    }
}

// Verificar si hay mensaje de error o éxito
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

// Variables para controlar el estado de los campos de precio
$child_checked = $edit_showtime && !empty($edit_showtime['enable_child_price']);
$senior_checked = $edit_showtime && !empty($edit_showtime['enable_senior_price']);
?>

<!-- Formulario Agregar/Editar Función -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <?= $edit_showtime ? '✏️ Editar Función' : '➕ Registrar Nueva Función' ?>
        </h3>
        <a href="index.php?tab=showtimes" class="admin-card-link">
            <i class="fas fa-arrow-left"></i> Volver al listado
        </a>
    </div>
    <div class="admin-card-body">
        <div class="movie-form-help">
            <p>🧹 El sistema considera un tiempo de <strong>15 minutos</strong> entre funciones para limpieza de la sala.</p>
            <p class="mt-1 warning">📽️ Selecciona el <strong>Formato</strong> de proyección para esta función.</p>
            <p class="mt-1 danger">⚠️ Los campos con <strong class="field-required">*</strong> son obligatorios.</p>
        </div>

        <div id="conflictChecker" class="mb-4 p-3 rounded-lg border text-sm conflict-checking">
            <p class="font-semibold">🔍 Verificación de conflictos en tiempo real:</p>
            <p id="conflictStatus">Selecciona película, sala, fecha y hora para verificar automáticamente si hay conflictos</p>
        </div>

        <form action="modules/showtime/showtime_actions.php" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4" id="showtimeForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <?php if ($edit_showtime): ?>
                <input type="hidden" name="showtime_id" id="showtimeIdInput" value="<?= htmlspecialchars($edit_showtime['id']) ?>">
                <input type="hidden" name="action" value="edit_showtime">
                <input type="hidden" name="return" value="../../index.php?tab=showtimes">
            <?php else: ?>
                <input type="hidden" name="action" value="add_showtime">
                <input type="hidden" name="showtime_id" id="showtimeIdInput" value="0">
                <input type="hidden" name="return" value="../../index.php?tab=showtimes">
            <?php endif; ?>

            <!-- Película, Sala y Fecha -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Película <span class="field-required">*</span></label>
                <select name="movie_id" required class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" id="movieSelect">
                    <option value="">Seleccionar</option>
                    <?php foreach ($movies_ordered as $m): ?>
                        <option value="<?= htmlspecialchars($m['id']) ?>"
                                data-duration="<?= htmlspecialchars($m['duration']) ?>"
                                <?= ($edit_showtime && $edit_showtime['movie_id'] == $m['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['title']) ?> (<?= htmlspecialchars($m['duration']) ?> min)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sala <span class="field-required">*</span></label>
                <select name="room_id" required class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" id="roomSelect">
                    <option value="">Seleccionar</option>
                    <?php foreach ($rooms as $r): ?>
                        <option value="<?= htmlspecialchars($r['id']) ?>" <?= ($edit_showtime && $edit_showtime['room_id'] == $r['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($r['name']) ?> (Cap: <?= htmlspecialchars($r['capacity']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fecha <span class="field-required">*</span></label>
                <input type="date" name="show_date" required min="<?= date('Y-m-d') ?>" value="<?= $edit_showtime ? htmlspecialchars($edit_showtime['show_date']) : '' ?>"
                       class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" id="dateInput">
            </div>

            <!-- Hora, Idioma y Formato -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Hora <span class="field-required">*</span></label>
                <input type="time" name="show_time" required value="<?= $edit_showtime ? htmlspecialchars($edit_showtime['show_time']) : '' ?>"
                       class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" id="timeInput">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Idioma <span class="field-required">*</span></label>
                <select name="language" class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="español" <?= ($edit_showtime && ($edit_showtime['language'] ?? 'español') == 'español') || !$edit_showtime ? 'selected' : '' ?>>Español</option>
                    <option value="subtitulos" <?= ($edit_showtime && ($edit_showtime['language'] ?? '') == 'subtitulos') ? 'selected' : '' ?>>Subtítulos</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Formato <span class="field-required">*</span></label>
                <select name="format" required class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Seleccionar Formato</option>
                    <?php foreach ($formatos as $fmt): ?>
                        <option value="<?= htmlspecialchars($fmt) ?>" <?= ($edit_showtime && isset($edit_showtime['format']) && $edit_showtime['format'] == $fmt) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($fmt) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Precios -->
            <div class="md:col-span-3">
                <label class="block text-sm font-medium text-gray-700 mb-2">💰 Precios</label>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Adulto <span class="field-required">*</span></label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm font-semibold"><?= htmlspecialchars($siteConfig['currency_symbol'] ?? '$') ?></span>
                            <input type="number" step="0.01" min="0.01" name="price_adult" required
                                   value="<?= $edit_showtime ? htmlspecialchars($edit_showtime['price_adult'] ?? $edit_showtime['price']) : '' ?>"
                                   class="w-full bg-gray-50 border border-gray-300 rounded-lg pl-7 pr-3 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                   placeholder="0.00">
                        </div>
                    </div>

                    <!-- Campo Niño con estado condicional -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-sm font-medium text-gray-700">Niño</label>
                            <label class="toggle-switch">
                                <input type="checkbox" name="enable_child_price" id="enable_child_price" value="1"
                                    <?= $child_checked ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm font-semibold"><?= htmlspecialchars($siteConfig['currency_symbol'] ?? '$') ?></span>
                            <input type="number" step="0.01" min="0" name="price_child" id="price_child"
                                   value="<?= $edit_showtime ? htmlspecialchars($edit_showtime['price_child'] ?? '0.00') : '0.00' ?>"
                                   class="w-full bg-gray-50 border border-gray-300 rounded-lg pl-7 pr-3 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 <?= $child_checked ? '' : 'price-input-disabled' ?>"
                                   placeholder="0.00"
                                <?= $child_checked ? '' : 'disabled' ?>>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Menores de 12 años</p>
                    </div>

                    <!-- Campo Tercera Edad con estado condicional -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-sm font-medium text-gray-700">Tercera Edad</label>
                            <label class="toggle-switch">
                                <input type="checkbox" name="enable_senior_price" id="enable_senior_price" value="1"
                                    <?= $senior_checked ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm font-semibold"><?= htmlspecialchars($siteConfig['currency_symbol'] ?? '$') ?></span>
                            <input type="number" step="0.01" min="0" name="price_senior" id="price_senior"
                                   value="<?= $edit_showtime ? htmlspecialchars($edit_showtime['price_senior'] ?? '0.00') : '0.00' ?>"
                                   class="w-full bg-gray-50 border border-gray-300 rounded-lg pl-7 pr-3 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 <?= $senior_checked ? '' : 'price-input-disabled' ?>"
                                   placeholder="0.00"
                                <?= $senior_checked ? '' : 'disabled' ?>>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Mayores de 60 años</p>
                    </div>
                </div>
            </div>

            <!-- Promociones -->
            <div class="md:col-span-3">
                <label class="block text-sm font-medium text-gray-700 mb-2">🎯 Promociones</label>
                <div class="flex flex-wrap gap-3">
                    <div class="promotion-checkbox">
                        <input type="checkbox" name="half_price_monday" id="half_price_monday"
                            <?= ($edit_showtime && in_array('lunes_mitad', $edit_showtime['promotions_array'] ?? [])) ? 'checked' : '' ?>>
                        <label for="half_price_monday">🌙 Lunes ½ Precio</label>
                    </div>
                    <div class="promotion-checkbox">
                        <input type="checkbox" name="preventa" id="preventa"
                            <?= ($edit_showtime && in_array('preventa', $edit_showtime['promotions_array'] ?? [])) ? 'checked' : '' ?>>
                        <label for="preventa">🎫 Preventa</label>
                    </div>
                </div>
            </div>

            <div class="md:col-span-3">
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-lg transition-colors shadow-md" id="submitBtn">
                    <?= $edit_showtime ? 'Actualizar Función' : 'Guardar Función' ?>
                </button>
                <?php if ($edit_showtime): ?>
                    <a href="index.php?tab=showtimes" class="block text-center text-gray-400 hover:text-gray-600 text-sm mt-3 no-underline">Cancelar edición</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>