<?php
require_once 'user_auth.php';

$siteConfig = getSiteConfig($pdo);
$pageTitle = "Mi Cuenta - " . ($siteConfig['site_name'] ?? 'Cinema Pro');
$activePage = 'account';

$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';
$csrf_token = generateCSRFToken();

$avatarPath = null;
if (!empty($userAuth['avatar'])) {
    $full = dirname(__DIR__) . '/' . $userAuth['avatar'];
    if (is_file($full)) {
        $avatarPath = '../' . $userAuth['avatar'] . '?v=' . filemtime($full);
    }
}

$deleteRequested = !empty($userAuth['delete_requested_at']);

require_once 'includes/header.php';
?>
<style>
    body { background-color: #ffffff !important; color: #1f2937 !important; }
    .account-wrapper { max-width: 760px; margin: 0 auto; padding: 32px 16px; }
    .account-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
        padding: 24px;
        margin-bottom: 20px;
    }
    .account-heading { display: flex; align-items: center; gap: 16px; margin-bottom: 24px; }
    .avatar-circle {
        width: 84px;
        height: 84px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
        border: 3px solid #eef2ff;
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
        background: #4f46e5;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-weight: 800;
        font-size: 2rem;
    }
    .avatar-circle.preview { border: 3px solid #c7d2fe; }
    .account-title { font-size: 1.5rem; font-weight: 800; color: #0f172a; margin: 0; }
    .account-subtitle { color: #6b7280; font-size: 0.9rem; }
    .auth-label { display: block; font-size: 0.85rem; color: #475569; margin-bottom: 4px; font-weight: 600; }
    .auth-input {
        width: 100%;
        padding: 10px 12px;
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        color: #0f172a;
        font-size: 0.95rem;
        transition: border-color 0.3s ease;
    }
    .auth-input:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
    .auth-input:disabled { background: #f8fafc; color: #94a3b8; }
    .auth-btn {
        background: linear-gradient(135deg, #4f46e5, #7c3aed);
        color: #ffffff;
        padding: 12px;
        border-radius: 8px;
        font-weight: 700;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .auth-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(79, 70, 229, 0.25); }
    .auth-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; box-shadow: none !important; }
    .btn-danger {
        background: #ffffff;
        border: 1px solid #fca5a5;
        color: #dc2626;
        padding: 11px 16px;
        border-radius: 8px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .btn-danger:hover { background: #fef2f2; border-color: #ef4444; }
    .btn-secondary {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        color: #334155;
        padding: 11px 16px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .btn-secondary:hover { border-color: #6366f1; color: #4f46e5; background: #eef2ff; }
    .msg { padding: 12px 16px; border-radius: 8px; font-size: 0.875rem; text-align: center; font-weight: 600; margin-bottom: 16px; }
    .msg-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    .msg-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    .form-group-inline { display: flex; gap: 8px; align-items: center; }
    .form-group-inline select { flex: 0 0 80px; }
    .form-group-inline input { flex: 1; }
    .phone-group-inline { display: flex; gap: 8px; align-items: center; }
    .phone-group-inline select { flex: 0 0 90px; }
    .phone-group-inline input { flex: 1; }
    .password-wrapper { position: relative; }
    .password-wrapper input { padding-right: 40px; }
    .password-toggle {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #6b7280;
        cursor: pointer;
        background: none;
        border: none;
        font-size: 1rem;
        padding: 4px;
    }
    .password-toggle:hover { color: #1f2937; }
    .input-error { border-color: #ef4444 !important; box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2) !important; }
    .input-success { border-color: #22c55e !important; box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.2) !important; }
    .password-hint { font-size: 0.75rem; color: #6b7280; margin-top: 4px; }
    .delete-request-banner {
        background: #fef3c7;
        border: 1px solid #f59e0b;
        color: #92400e;
        padding: 14px 16px;
        border-radius: 10px;
        font-size: 0.875rem;
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }
    .danger-box { border-color: #fecaca; }
    .section-title { font-size: 1rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; }
    .file-label {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: 1px dashed #cbd5e1;
        color: #4f46e5;
        font-weight: 600;
        font-size: 0.85rem;
        padding: 10px 14px;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.2s ease;
        background: #ffffff;
    }
    .file-label:hover { border-color: #6366f1; background: #eef2ff; }
    .avatar-small {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }
</style>

<div class="account-wrapper">
    <?php if ($msg): ?>
        <div class="msg msg-success"><i class="fas fa-check-circle mr-1"></i><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="msg msg-error"><i class="fas fa-exclamation-triangle mr-1"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="account-heading">
        <?php if ($avatarPath): ?>
            <img src="<?= htmlspecialchars($avatarPath) ?>" alt="Foto de perfil" class="avatar-circle">
        <?php else: ?>
            <div class="avatar-circle"><?= htmlspecialchars(strtoupper(substr($userAuth['name'] ?? 'U', 0, 1))) ?></div>
        <?php endif; ?>
        <div>
            <h1 class="account-title">Mi Cuenta</h1>
            <p class="account-subtitle"><?= htmlspecialchars($userAuth['name'] ?? '') ?></p>
            <p class="account-subtitle"><?= htmlspecialchars($userAuth['email'] ?? '') ?></p>
        </div>
    </div>

    <?php if ($deleteRequested): ?>
    <div class="account-card">
        <div class="delete-request-banner">
            <i class="fas fa-exclamation-triangle mt-0.5"></i>
            <div>
                <strong>Solicitud de eliminación en revisión.</strong>
                <br>
                Enviaste la solicitud el <?= htmlspecialchars(formatDateVenezuela($userAuth['delete_requested_at'])) ?>. Un administrador la revisará y decidirá la eliminación de la cuenta. Mientras tanto, puedes cancelar la solicitud si cambias de opinión.
            </div>
        </div>
        <form action="user_actions.php" method="POST" class="mt-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="action" value="cancel_delete">
            <button type="submit" class="btn-secondary">
                <i class="fas fa-undo mr-2"></i>Cancelar solicitud
            </button>
        </form>
    </div>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- FOTO DE PERFIL (AVATAR)                        -->
    <!-- ============================================ -->
    <div class="account-card">
        <p class="section-title">👤 Foto de perfil</p>
        <p class="account-subtitle mb-4">Sube o actualiza tu avatar (JPG, PNG, GIF o WebP · máx. 2MB).</p>
        <form action="user_actions.php" method="POST" enctype="multipart/form-data" id="avatarForm" class="flex items-center gap-4 flex-wrap">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="action" value="upload_avatar">

            <div class="avatar-circle preview" id="avatarPreview">
                <?php if ($avatarPath): ?>
                    <img src="<?= htmlspecialchars($avatarPath) ?>" alt="Vista previa" class="avatar-circle preview" width="84" height="84" id="avatarPreviewImg">
                <?php else: ?>
                    <span id="avatarPreviewFallback"><?= htmlspecialchars(strtoupper(substr($userAuth['name'] ?? 'U', 0, 1))) ?></span>
                <?php endif; ?>
            </div>

            <label for="avatarFile" class="file-label">
                <i class="fas fa-camera"></i>
                <span id="avatarFileName"><?= $avatarPath ? 'Cambiar avatar' : 'Elegir imagen' ?></span>
            </label>
            <input type="file" name="avatar" id="avatarFile" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden" data-avatar-input>

            <button type="submit" class="auth-btn" id="avatarSubmitBtn" disabled style="flex: 0 0 auto; padding: 10px 20px;">
                <i class="fas fa-upload"></i>Subir avatar
            </button>
        </form>
    </div>

    <!-- ============================================ -->
    <!-- MIS DATOS PERSONALES                          -->
    <!-- ============================================ -->
    <div class="account-card">
        <p class="section-title">📋 Mis datos personales</p>
        <p class="account-subtitle mb-4">Mantén tu información actualizada. Los campos con * son obligatorios.</p>

        <form action="user_actions.php" method="POST" class="space-y-4" id="profileForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="action" value="update_profile">

            <div>
                <label class="auth-label"><i class="fas fa-user mr-1"></i>Nombres y Apellidos *</label>
                <input type="text" name="name" required maxlength="100"
                       value="<?= htmlspecialchars($userAuth['name'] ?? '') ?>"
                       placeholder="Ej: Juan Pérez" class="auth-input">
            </div>

            <div>
                <label class="auth-label"><i class="fas fa-id-card mr-1"></i>Cédula de Identidad *</label>
                <div class="form-group-inline">
                    <select name="cedula_type" class="auth-input" required>
                        <?php foreach (['V', 'E', 'P', 'J'] as $ctype): ?>
                            <option value="<?= $ctype ?>" <?= ($userAuth['cedula_type'] ?? '') === $ctype ? 'selected' : '' ?>><?= $ctype ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="cedula_number" required data-only-numbers minlength="7" maxlength="20"
                           value="<?= htmlspecialchars($userAuth['cedula_number'] ?? '') ?>"
                           placeholder="Número de cédula" inputmode="numeric" class="auth-input">
                </div>
            </div>

            <div>
                <label class="auth-label"><i class="fas fa-phone mr-1"></i>Teléfono Móvil *</label>
                <div class="phone-group-inline">
                    <select name="phone_prefix" class="auth-input" required>
                        <?php foreach (['412' => '0412', '414' => '0414', '416' => '0416', '424' => '0424', '426' => '0426', '422' => '0422'] as $pvalue => $plabel): ?>
                            <option value="<?= $pvalue ?>" <?= ($userAuth['phone_prefix'] ?? '') === $pvalue ? 'selected' : '' ?>><?= $plabel ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="phone_number" required data-only-numbers minlength="7" maxlength="20"
                           value="<?= htmlspecialchars($userAuth['phone_number'] ?? '') ?>"
                           placeholder="Número de teléfono" inputmode="numeric" class="auth-input">
                </div>
            </div>

            <div>
                <label class="auth-label"><i class="fas fa-calendar-alt mr-1"></i>Fecha de Nacimiento *</label>
                <input type="date" name="birth_date" required max="<?= date('Y-m-d') ?>"
                       value="<?= htmlspecialchars($userAuth['birth_date'] ?? '') ?>" class="auth-input">
            </div>

            <div>
                <label class="auth-label"><i class="fas fa-envelope mr-1"></i>Correo Electrónico *</label>
                <input type="email" name="email" required maxlength="100"
                       value="<?= htmlspecialchars($userAuth['email'] ?? '') ?>"
                       placeholder="ejemplo@email.com" class="auth-input">
            </div>

            <div>
                <label class="auth-label"><i class="fas fa-lock mr-1"></i>Nueva Contraseña</label>
                <div class="password-wrapper">
                    <input type="password" name="new_password" id="newPassword" minlength="8" maxlength="255"
                           placeholder="Déjalo vacío para no cambiarla" class="auth-input" style="padding-right: 40px;">
                    <button type="button" class="password-toggle" data-password-toggle="newPassword" aria-label="Mostrar contraseña">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <p class="password-hint">Mínimo 8 caracteres con mayúscula, minúscula y número.</p>
            </div>

            <div>
                <label class="auth-label"><i class="fas fa-check-circle mr-1"></i>Confirmar Nueva Contraseña</label>
                <div class="password-wrapper">
                    <input type="password" name="confirm_new_password" id="confirmNewPassword" minlength="8" maxlength="255"
                           placeholder="Repite tu contraseña" class="auth-input" style="padding-right: 40px;">
                    <button type="button" class="password-toggle" data-password-toggle="confirmNewPassword" aria-label="Mostrar contraseña">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" id="saveBtn" class="auth-btn w-full">
                <i class="fas fa-save"></i>Guardar cambios
            </button>
        </form>
    </div>

    <!-- ============================================ -->
    <!-- ZONA PELIGROSA: SOLICITAR ELIMINACIÓN          -->
    <!-- ============================================ -->
    <div class="account-card danger-box">
        <p class="section-title" style="color: #dc2626;">⚠️ Zona peligrosa</p>
        <p class="account-subtitle mb-4">
            Puedes solicitar la eliminación de tu cuenta. La petición queda en revisión de un administrador y
            puede cancelarse mientras tanto. La cuenta no se elimina automáticamente.
        </p>
        <?php if (!$deleteRequested): ?>
            <form action="user_actions.php" method="POST" id="deleteForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="action" value="request_delete">
                <button type="button" class="btn-danger" data-confirm-delete>
                    <i class="fas fa-user-minus mr-2"></i>Solicitar eliminación de cuenta
                </button>
            </form>
        <?php endif; ?>
    </div>

    <p class="text-center text-sm text-gray-500 mt-6">
        <a href="../index.php" class="text-indigo-600 hover:underline font-semibold">
            <i class="fas fa-arrow-left mr-1"></i>Volver al inicio
        </a>
    </p>
</div>

<?php require_once 'includes/footer.php'; ?>

<script nonce="<?= htmlspecialchars($cspNonce ?? '') ?>">
document.addEventListener('DOMContentLoaded', function() {
    // Nota: el toggle de visibilidad de contraseñas lo maneja admin.js ([data-password-toggle])

    // ============================================
    // SOLO NÚMEROS EN CÉDULA Y TELÉFONO
    // ============================================
    document.querySelectorAll('[data-only-numbers]').forEach(function(input) {
        input.addEventListener('input', function() { this.value = this.value.replace(/[^0-9]/g, ''); });
        input.addEventListener('paste', function(e) {
            e.preventDefault();
            const pasted = (e.clipboardData || window.clipboardData).getData('text');
            this.value = pasted.replace(/[^0-9]/g, '');
        });
        input.addEventListener('keydown', function(e) {
            const allowed = ['Backspace', 'Delete', 'Tab', 'Escape', 'Enter', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'];
            if (allowed.includes(e.key) || e.ctrlKey || e.metaKey) return;
            if (!/^[0-9]$/.test(e.key)) e.preventDefault();
        });
    });

    // ============================================
    // VALIDACIÓN DE CONTRASEÑA EN TIEMPO REAL
    // ============================================
    const newPassword = document.getElementById('newPassword');
    const confirmNewPassword = document.getElementById('confirmNewPassword');
    const profileForm = document.getElementById('profileForm');
    const saveBtn = document.getElementById('saveBtn');

    function validatePasswords() {
        if (!newPassword || !confirmNewPassword) return;
        if (newPassword.value.length > 0) {
            if (newPassword.value === confirmNewPassword.value && confirmNewPassword.value.length >= 8) {
                confirmNewPassword.classList.remove('input-error');
                confirmNewPassword.classList.add('input-success');
            } else if (confirmNewPassword.value.length > 0) {
                confirmNewPassword.classList.remove('input-success');
                confirmNewPassword.classList.add('input-error');
            } else {
                confirmNewPassword.classList.remove('input-error', 'input-success');
            }
        } else {
            confirmNewPassword.classList.remove('input-error', 'input-success');
        }
    }
    if (newPassword && confirmNewPassword) {
        newPassword.addEventListener('input', validatePasswords);
        confirmNewPassword.addEventListener('input', validatePasswords);
        profileForm.addEventListener('submit', function(e) {
            if (newPassword.value.length > 0 && newPassword.value.length < 8) {
                e.preventDefault();
                alert('La contraseña debe tener al menos 8 caracteres.');
                newPassword.classList.add('input-error');
                return false;
            }
            if (newPassword.value.length > 0 && newPassword.value !== confirmNewPassword.value) {
                e.preventDefault();
                alert('Las contraseñas no coinciden.');
                confirmNewPassword.classList.add('input-error');
                return false;
            }
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        });
    }

    // ============================================
    // AVATAR: PREVIEW DEL ARCHIVO SELECCIONADO
    // ============================================
    const avatarInput = document.getElementById('avatarFile');
    const avatarSubmitBtn = document.getElementById('avatarSubmitBtn');
    const avatarFileName = document.getElementById('avatarFileName');

    if (avatarInput) {
        avatarInput.addEventListener('change', function() {
            const file = this.files[0];
            if (!file) { avatarSubmitBtn.disabled = true; return; }
            if (file.size > 2 * 1024 * 1024) {
                alert('El archivo excede el tamaño máximo permitido (2MB).');
                this.value = '';
                avatarSubmitBtn.disabled = true;
                return;
            }
            if (!['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type)) {
                alert('Tipo de archivo no permitido.');
                this.value = '';
                avatarSubmitBtn.disabled = true;
                return;
            }
            const reader = new FileReader();
            reader.onload = function(ev) {
                const preview = document.getElementById('avatarPreviewImg');
                const fallback = document.getElementById('avatarPreviewFallback');
                if (preview) {
                    preview.src = ev.target.result;
                } else {
                    const img = document.createElement('img');
                    img.id = 'avatarPreviewImg';
                    img.src = ev.target.result;
                    img.alt = 'Vista previa';
                    img.className = 'avatar-circle preview';
                    img.width = 84;
                    img.height = 84;
                    const previewBox = document.getElementById('avatarPreview');
                    if (fallback) fallback.style.display = 'none';
                    previewBox.appendChild(img);
                }
                avatarFileName.textContent = file.name;
                avatarSubmitBtn.disabled = false;
            };
            reader.readAsDataURL(file);
        });
    }

    // ============================================
    // ZONA PELIGROSA: CONFIRMACIÓN DE ELIMINACIÓN
    // ============================================
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('[data-confirm-delete]');
        if (!btn) return;
        if (confirm('¿Estás seguro de que deseas solicitar la eliminación de tu cuenta? Esta acción queda en revisión. Puedes cancelarla mientras tanto.')) {
            btn.closest('form').submit();
        }
    });
});
</script>
</body>
</html>