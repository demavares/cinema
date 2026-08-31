<?php
// ============================================
// MÓDULO: USUARIOS — REGISTRO / EDICIÓN
// ============================================
$edit_user_id = isset($_GET['edit_user_id']) ? filter_var($_GET['edit_user_id'], FILTER_VALIDATE_INT) : null;
$edit_user = null;
$is_self = false;

if ($edit_user_id && $edit_user_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$edit_user_id]);
    $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$edit_user) {
        $edit_user_id = null;
    } else {
        $is_self = $edit_user['id'] == $_SESSION['user_id'];
    }
}
?>

<!-- Formulario Agregar/Editar Usuario -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <?= $edit_user ? '✏️ Editar Usuario' : '➕ Registrar Nuevo Usuario' ?>
        </h3>
        <a href="index.php?tab=users" class="admin-card-link">
            <i class="fas fa-arrow-left"></i> Volver al listado
        </a>
    </div>
    <div class="admin-card-body">
        <div class="movie-form-help">
            <p>🔑 La <strong>contraseña</strong> debe tener mínimo 8 caracteres con mayúscula, minúscula y número.</p>
            <?php if ($edit_user && !$is_self): ?>
                <p class="mt-1 warning">🔒 Deja el campo de contraseña vacío para mantener la actual.</p>
            <?php endif; ?>
            <?php if ($edit_user && $is_self): ?>
                <p class="mt-1 danger">⚠️ Este es tu propio usuario. No puedes cambiar tu rol.</p>
            <?php endif; ?>
            <p class="mt-1 danger">⚠️ Los campos con <strong class="field-required">*</strong> son obligatorios.</p>
        </div>

        <form action="modules/user/user_actions.php" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="action" value="<?= $edit_user ? 'edit_user' : 'add_user' ?>">
            <input type="hidden" name="return" value="../../index.php?tab=users">
            <?php if ($edit_user): ?>
                <input type="hidden" name="user_id" value="<?= htmlspecialchars($edit_user['id']) ?>">
            <?php endif; ?>

            <!-- ============================================ -->
            <!-- FILA 1: Nombre Completo (completo)            -->
            <!-- ============================================ -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre Completo <span class="field-required">*</span></label>
                <input type="text" name="user_name" required maxlength="100"
                       value="<?= $edit_user ? htmlspecialchars($edit_user['name']) : '' ?>"
                       placeholder="Nombre y apellido"
                       class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <!-- ============================================ -->
            <!-- FILA 2: Email (completo)                      -->
            <!-- ============================================ -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="field-required">*</span></label>
                <input type="email" name="user_email" required
                       value="<?= $edit_user ? htmlspecialchars($edit_user['email']) : '' ?>"
                       placeholder="ejemplo@correo.com"
                       class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <!-- ============================================ -->
            <!-- FILA 3: Cédula de Identidad                   -->
            <!-- ============================================ -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Cédula de Identidad <span class="field-required">*</span></label>
                <div class="flex gap-3">
                    <select name="cedula_type" required class="w-1/3 bg-gray-50 border border-gray-300 rounded-lg px-3 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Tipo</option>
                        <?php foreach (['V', 'E', 'J'] as $ctype): ?>
                            <option value="<?= $ctype ?>" <?= ($edit_user && $edit_user['cedula_type'] === $ctype) ? 'selected' : '' ?>><?= $ctype ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="cedula_number" id="cedulaNumber" data-only-numbers required maxlength="12"
                           value="<?= $edit_user ? htmlspecialchars($edit_user['cedula_number']) : '' ?>"
                           placeholder="Número de cédula"
                           class="w-2/3 bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>

            <!-- ============================================ -->
            <!-- FILA 4: Teléfono Móvil                              -->
            <!-- ============================================ -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono Móvil <span class="field-required">*</span></label>
                <div class="flex gap-3">
                    <select name="phone_prefix" required class="w-1/3 bg-gray-50 border border-gray-300 rounded-lg px-3 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <?php foreach (['412' => '0412', '414' => '0414', '416' => '0416', '424' => '0424', '426' => '0426'] as $prefix_value => $prefix_label): ?>
                            <option value="<?= $prefix_value ?>" <?= ($edit_user && $edit_user['phone_prefix'] === $prefix_value) ? 'selected' : '' ?>><?= $prefix_label ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="phone_number" data-only-numbers required maxlength="12"
                           value="<?= $edit_user ? htmlspecialchars($edit_user['phone_number']) : '' ?>"
                           placeholder="Número de Teléfono Móvil"
                           class="w-2/3 bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>

            <!-- ============================================ -->
            <!-- FILA 5: Fecha de Nacimiento                   -->
            <!-- ============================================ -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de Nacimiento</label>
                <input type="date" name="birth_date" max="<?= date('Y-m-d') ?>"
                       value="<?= $edit_user ? htmlspecialchars($edit_user['birth_date']) : '' ?>"
                       class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <!-- ============================================ -->
            <!-- FILA 6: Contraseña                            -->
            <!-- ============================================ -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Contraseña <?= $edit_user ? '' : '<span class="field-required">*</span>' ?>
                </label>
                <div class="relative">
                    <input type="password" name="user_password" id="userPassword" <?= $edit_user ? '' : 'required' ?>
                           class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 pr-12 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="<?= $edit_user ? 'Dejar vacío para no cambiar' : 'Mínimo 8 caracteres' ?>">
                    <button type="button" class="password-toggle-btn" data-password-toggle="userPassword" tabindex="-1">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- FILA 7: Rol                                   -->
            <!-- ============================================ -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Rol <span class="field-required">*</span></label>
                <select name="user_role" class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" <?= $is_self ? 'disabled' : '' ?>>
                    <option value="user" <?= ($edit_user && $edit_user['role'] === 'user') || !$edit_user ? 'selected' : '' ?>>Usuario</option>
                    <option value="admin" <?= ($edit_user && $edit_user['role'] === 'admin') ? 'selected' : '' ?>>Administrador</option>
                </select>
                <?php if ($is_self): ?>
                    <input type="hidden" name="user_role" value="admin">
                <?php endif; ?>
            </div>

            <!-- ============================================ -->
            <!-- FILA 8: Botón de envío (completo)             -->
            <!-- ============================================ -->
            <button type="submit" class="md:col-span-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-lg transition-colors shadow-md">
                <?= $edit_user ? 'Actualizar Usuario' : 'Registrar Usuario' ?>
            </button>
        </form>
    </div>
</div>