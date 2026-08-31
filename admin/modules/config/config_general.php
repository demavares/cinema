<?php
// ============================================
// CONFIGURACIÓN — SECCIÓN: INFORMACIÓN GENERAL
// ============================================
$logo = $siteConfig['site_logo'] ?? '';
$footer_logo = $siteConfig['footer_logo'] ?? '';
$favicon = $siteConfig['site_favicon'] ?? '';
$has_logo = adminAssetExists($logo);
$has_footer_logo = adminAssetExists($footer_logo);
$has_favicon = adminAssetExists($favicon);
?>

<!-- Formulario Información General -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">🏢 Información General</h3>
    </div>
    <div class="admin-card-body">
        <div class="movie-form-help">
            <p>ℹ️ Estos datos (nombre, copyright, logo y favicon) afectan a todo el sitio web y se reflejan de inmediato en la cartelera.</p>
            <p class="mt-1 warning">🖼️ Los logos deben ser <strong>JPG, PNG, GIF o WEBP</strong> (máx. 2MB). El favicon solo <strong>PNG o ICO</strong> (máx. 1MB).</p>
            <p class="mt-1 danger">⚠️ Los campos con <strong class="field-required">*</strong> son obligatorios.</p>
        </div>

        <form action="modules/config/config_actions.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="action" value="save_config">
            <input type="hidden" name="section" value="general">

            <!-- Nombre del Sitio -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del Sitio <span class="field-required">*</span></label>
                <input type="text" name="site_name" required maxlength="60"
                       value="<?= htmlspecialchars($siteConfig['site_name'] ?? 'Cinema') ?>"
                       placeholder="Nombre del cine"
                       class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <!-- Copyright del Footer -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Copyright del Footer</label>
                <textarea name="footer_copyright" rows="2"
                          class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                          placeholder="© {year} Cinema. Todos los derechos reservados."><?= htmlspecialchars($siteConfig['footer_copyright'] ?? '') ?></textarea>
                <p class="text-xs text-gray-500 mt-1">Usa <code class="bg-gray-100 px-1 rounded">{year}</code> para que se coloque automáticamente el año actual.</p>
            </div>

            <!-- Logo del Header -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Logo del Header</label>
                <input type="file" name="site_logo" accept="image/*"
                       class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700">
                <?php if ($has_logo): ?>
                    <div class="mt-3 flex items-center gap-4 bg-gray-50 p-3 rounded-lg border border-gray-200">
                        <img src="<?= htmlspecialchars(adminAssetHref($logo)) . '?v=' . time() ?>" alt="Logo del header actual" class="h-10 w-auto object-contain rounded bg-gray-900">
                        <div class="flex-1">
                            <p class="text-xs text-gray-500">Logo actual del Header</p>
                            <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars(basename($logo)) ?></p>
                            <button type="submit" name="remove_logo" value="1" data-confirm="¿Eliminar el logo del header actual?" class="text-xs text-red-500 hover:text-red-700 transition-colors mt-1">
                                <i class="fas fa-trash mr-1"></i> Eliminar logo
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Logo del Footer -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Logo del Footer</label>
                <input type="file" name="footer_logo" accept="image/*"
                       class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700">
                <?php if ($has_footer_logo): ?>
                    <div class="mt-3 flex items-center gap-4 bg-gray-50 p-3 rounded-lg border border-gray-200">
                        <img src="<?= htmlspecialchars(adminAssetHref($footer_logo)) . '?v=' . time() ?>" alt="Logo del footer actual" class="h-10 w-auto object-contain rounded bg-gray-900">
                        <div class="flex-1">
                            <p class="text-xs text-gray-500">Logo actual del Footer</p>
                            <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars(basename($footer_logo)) ?></p>
                            <button type="submit" name="remove_footer_logo" value="1" data-confirm="¿Eliminar el logo del footer actual?" class="text-xs text-red-500 hover:text-red-700 transition-colors mt-1">
                                <i class="fas fa-trash mr-1"></i> Eliminar logo
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Favicon -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Favicon</label>
                <input type="file" name="site_favicon" accept=".png,.ico"
                       class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700">
                <?php if ($has_favicon): ?>
                    <div class="mt-3 flex items-center gap-4 bg-gray-50 p-3 rounded-lg border border-gray-200">
                        <img src="<?= htmlspecialchars(adminAssetHref($favicon)) . '?v=' . time() ?>" alt="Favicon actual" class="h-8 w-8 object-contain rounded bg-gray-900">
                        <div class="flex-1">
                            <p class="text-xs text-gray-500">Favicon actual</p>
                            <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars(basename($favicon)) ?></p>
                            <button type="submit" name="remove_favicon" value="1" data-confirm="¿Eliminar el favicon actual?" class="text-xs text-red-500 hover:text-red-700 transition-colors mt-1">
                                <i class="fas fa-trash mr-1"></i> Eliminar favicon
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <button type="submit" class="md:col-span-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-lg transition-colors shadow-md">
                Guardar Información General
            </button>
        </form>
    </div>
</div>