<?php
// ============================================
// MÓDULO: REGISTRO/EDICIÓN DE PELÍCULAS
// ============================================

$countries = $pdo->query("SELECT * FROM countries ORDER BY name ASC")->fetchAll();

// Cargar datos de edición
$edit_movie = null;
if (isset($_GET['edit_movie_id']) && filter_var($_GET['edit_movie_id'], FILTER_VALIDATE_INT)) {
    $stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
    $stmt->execute([intval($_GET['edit_movie_id'])]);
    $edit_movie = $stmt->fetch();
}

// Verificar si hay mensaje de error o éxito
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';
?>

<div class="admin-content-header">
    <h1 class="admin-content-title"><?= $edit_movie ? '✏️ Editar Película' : '➕ Registrar Película' ?></h1>
    <p class="admin-content-subtitle"><?= $edit_movie ? 'Modifica los datos de la película existente' : 'Agrega una nueva película al catálogo' ?></p>
</div>

<!-- Formulario Agregar/Editar Película -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <?= $edit_movie ? '✏️ Editar Película' : '➕ Registrar Nueva Película' ?>
        </h3>
        <a href="index.php?tab=movies" class="admin-card-link">
            <i class="fas fa-arrow-left"></i> Volver al listado
        </a>
    </div>
    <div class="admin-card-body">
        <div class="movie-form-help">
            <p>ℹ️ Al colocar el <strong>título</strong> y opcionalmente el <strong>año</strong>, el sistema buscará automáticamente la información desde TMDb.</p>
            <p class="mt-1 warning">🔒 Las películas nuevas se registran como <strong>OCULTAS</strong> por defecto. Debes activarlas manualmente.</p>
            <p class="mt-1 danger">⚠️ Los campos con <strong class="field-required">*</strong> son obligatorios.</p>
        </div>

        <form action="modules/movie/movie_actions.php" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <?php if ($edit_movie): ?>
                <input type="hidden" name="movie_id" value="<?= htmlspecialchars($edit_movie['id']) ?>">
                <input type="hidden" name="action" value="edit_movie">
                <input type="hidden" name="return" value="../../index.php?tab=movies">
            <?php else: ?>
                <input type="hidden" name="action" value="add_movie">
                <input type="hidden" name="return" value="../../index.php?tab=movies">
            <?php endif; ?>

            <!-- ============================================ -->
            <!-- FILA 1: Título (completo)                     -->
            <!-- ============================================ -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Título <span class="field-required">*</span>
                </label>
                <input type="text" name="title" id="movieTitleInput" required maxlength="255" 
                       value="<?= $edit_movie ? htmlspecialchars($edit_movie['title']) : '' ?>"
                       class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <!-- ============================================ -->
            <!-- FILA 2: Clasificación, Año y URL Tráiler      -->
            <!-- ============================================ -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Clasificación <span class="field-required">*</span>
                </label>
                <select name="classification" required class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Seleccionar</option>
                    <option value="A (Todo público)" <?= ($edit_movie && $edit_movie['classification'] == 'A (Todo público)') ? 'selected' : '' ?>>A (Todo público)</option>
                    <option value="B (Mayores de 12)" <?= ($edit_movie && $edit_movie['classification'] == 'B (Mayores de 12)') ? 'selected' : '' ?>>B (Mayores de 12)</option>
                    <option value="C (Mayores de 18)" <?= ($edit_movie && $edit_movie['classification'] == 'C (Mayores de 18)') ? 'selected' : '' ?>>C (Mayores de 18)</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Año de Estreno
                </label>
                <input type="number" name="year" id="movieYearInput" min="1900" max="<?= date('Y') + 2 ?>"
                       value="<?= $edit_movie ? htmlspecialchars($edit_movie['year']) : '' ?>"
                       placeholder="Ej: 2024"
                       class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    URL del Tráiler (YouTube) <span class="field-required">*</span>
                </label>
                <input type="url" name="trailer_url" required 
                       value="<?= $edit_movie ? htmlspecialchars($edit_movie['trailer_url']) : '' ?>"
                       placeholder="https://www.youtube.com/watch?v=XXXXXX"
                       class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <!-- ============================================ -->
            <!-- FILA 3: URL Póster y URL Banner               -->
            <!-- ============================================ -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">URL del Póster</label>
                <input type="url" name="poster_url" id="posterUrlInput"
                       value="<?= $edit_movie ? htmlspecialchars($edit_movie['poster_url'] ?? '') : '' ?>"
                       placeholder="https://..."
                       class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <img id="posterPreview" class="movie-poster-preview" alt="Vista previa del póster">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">URL Fondo / Banner</label>
                <input type="url" name="banner_url" id="bannerUrlInput"
                       value="<?= $edit_movie ? htmlspecialchars($edit_movie['banner_url'] ?? '') : '' ?>"
                       placeholder="https://..."
                       class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <img id="bannerPreview" class="movie-banner-preview" alt="Vista previa del banner">
            </div>

            <!-- ============================================ -->
            <!-- FILA 4: Duración, Género y País               -->
            <!-- ============================================ -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Duración (minutos)</label>
                <input type="number" name="duration" min="0" max="999" 
                       value="<?= $edit_movie ? htmlspecialchars($edit_movie['duration']) : '' ?>"
                       placeholder="Ej: 120"
                       class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Género</label>
                <input type="text" name="genre" 
                       value="<?= $edit_movie ? htmlspecialchars($edit_movie['genre'] ?? '') : '' ?>" 
                       placeholder="Ej: Acción, Ciencia Ficción"
                       class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">País de Origen</label>
                <select name="country_id" class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Seleccionar País</option>
                    <?php foreach($countries as $country): ?>
                        <option value="<?= htmlspecialchars($country['id']) ?>" <?= ($edit_movie && isset($edit_movie['country_id']) && $edit_movie['country_id'] == $country['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($country['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- ============================================ -->
            <!-- FILA 5: Director (completo)                   -->
            <!-- ============================================ -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Director</label>
                <input type="text" name="director" 
                       value="<?= $edit_movie ? htmlspecialchars($edit_movie['director'] ?? '') : '' ?>" 
                       placeholder="Director de la película"
                       class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <!-- ============================================ -->
            <!-- FILA 6: Reparto Principal (completo)          -->
            <!-- ============================================ -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Reparto Principal</label>
                <input type="text" name="cast_members" 
                       value="<?= $edit_movie ? htmlspecialchars($edit_movie['cast_members'] ?? '') : '' ?>" 
                       placeholder="Ej: Actor 1, Actor 2, Actor 3"
                       class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <!-- ============================================ -->
            <!-- FILA 7: Sinopsis / Descripción (completo)     -->
            <!-- ============================================ -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Sinopsis / Descripción</label>
                <textarea name="description" rows="4" maxlength="5000" placeholder="Sinopsis detallada..."
                          class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"><?= $edit_movie ? htmlspecialchars($edit_movie['description'] ?? '') : '' ?></textarea>
            </div>

            <!-- ============================================ -->
            <!-- FILA 8: Botón de envío (completo)             -->
            <!-- ============================================ -->
            <button type="submit" class="md:col-span-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-lg transition-colors shadow-md">
                <?= $edit_movie ? 'Actualizar Película' : 'Guardar Película (Oculta)' ?>
            </button>
        </form>
    </div>
</div>