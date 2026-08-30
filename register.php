<?php
require_once 'config.php';
// Redirigir si ya está logueado
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Error de seguridad: Token inválido. Por favor, recarga la página.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');
        $cedula_type = trim($_POST['cedula_type'] ?? 'V');
        $cedula_number = trim($_POST['cedula_number'] ?? '');
        $phone_prefix = trim($_POST['phone_prefix'] ?? '412');
        $phone_number = trim($_POST['phone_number'] ?? '');
        $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
        // ✅ Validación segura de longitud de contraseña
        $passwordLength = function_exists('mb_strlen')
            ? mb_strlen($password, 'UTF-8')
            : strlen($password);
        // ✅ Validaciones mejoradas
        if (
            empty($name) ||
            empty($email) ||
            empty($password) ||
            empty($confirm_password) ||
            empty($cedula_number) ||
            empty($phone_number) ||
            empty($birth_date)
        ) {
            $error = "Todos los campos son obligatorios.";
        } elseif ($password !== $confirm_password) {
            $error = "Las contraseñas no coinciden.";
        } elseif ($passwordLength < 8) {
            $error = "La contraseña debe tener al menos 8 caracteres.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "El formato del correo electrónico no es válido.";
        } elseif (!preg_match('/^[0-9]+$/', $cedula_number)) {
            $error = "La cédula solo debe contener números.";
        } elseif (strlen($cedula_number) < 7) {
            $error = "La Cédula de Identidad debe tener al menos 7 dígitos.";
        } elseif (!preg_match('/^[0-9]+$/', $phone_number)) {
            $error = "El número de teléfono solo debe contener números.";
        } elseif (strlen($phone_number) < 7) {
            $error = "El número de teléfono debe tener al menos 7 dígitos.";
        } else {
            try {
                // 🛡️ PREVENCIÓN DE ENUMERACIÓN DE CUENTAS
                $stmt_check_cedula = $pdo->prepare("
                    SELECT id
                    FROM users
                    WHERE cedula_type = ? AND cedula_number = ?
                ");
                $stmt_check_cedula->execute([$cedula_type, $cedula_number]);
                $cedula_exists = $stmt_check_cedula->rowCount() > 0;
                $stmt_check_email = $pdo->prepare("
                    SELECT id
                    FROM users
                    WHERE email = ?
                ");
                $stmt_check_email->execute([$email]);
                $email_exists = $stmt_check_email->rowCount() > 0;
                if ($cedula_exists || $email_exists) {
                    $error = "Los datos ingresados (cédula o correo) ya se encuentran registrados. Por favor, verifica tu información o inicia sesión.";
                }
                // Si pasa las validaciones, procede al registro
                if (empty($error)) {
                    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                    $pdo->beginTransaction();
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO users (
                                name, email, cedula_type, cedula_number,
                                phone_prefix, phone_number, birth_date, password, role
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'user')
                        ");
                        $stmt->execute([
                            $name, $email, $cedula_type, $cedula_number,
                            $phone_prefix, $phone_number, $birth_date, $passwordHash
                        ]);
                        $userId = $pdo->lastInsertId();
                        $pdo->commit();
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $userId;
                        $_SESSION['user_name'] = $name;
                        $_SESSION['user_role'] = 'user';
                        $_SESSION['user_email'] = $email;
                        $_SESSION['login_time'] = time();
                        $sessionPrefixes = [
                            'food_', 'purchase_', 'ticket_', 'total_',
                            'subtotal_', 'tax_', 'payment_'
                        ];
                        foreach ($_SESSION as $key => $value) {
                            foreach ($sessionPrefixes as $prefix) {
                                if (strpos($key, $prefix) === 0) {
                                    unset($_SESSION[$key]);
                                    break;
                                }
                            }
                        }
                        error_log(sprintf(
                            "✅ Registro exitoso: user_id=%d, email=%s, IP=%s",
                            $userId, $email, $_SERVER['REMOTE_ADDR']
                        ));
                        header('Location: index.php?msg=' . urlencode("¡Registro exitoso! Bienvenido " . htmlspecialchars($name) . "."));
                        exit;
                    } catch (PDOException $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        // 🛡️ EVITAR FUGA DE CONTRASEÑAS EN LOGS
                        $safePost = $_POST;
                        unset($safePost['password'], $safePost['confirm_password'], $safePost['csrf_token']);
                        error_log("❌ Error en registro DB: " . $e->getMessage() . " - Data: " . print_r($safePost, true));
                        if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062) {
                            $error = "Ya existe un usuario registrado con esos datos. Por favor, intenta con otros.";
                        } else {
                            $error = "Ocurrió un error interno al registrar el usuario. Por favor, intenta nuevamente más tarde.";
                        }
                    }
                }
            } catch (PDOException $e) {
                error_log("Error en registro (PDO): " . $e->getMessage());
                $error = "Ocurrió un error al procesar tu solicitud. Por favor, intenta nuevamente.";
            } catch (Exception $e) {
                error_log("Error en registro (General): " . $e->getMessage());
                $error = "Ocurrió un error inesperado. Por favor, intenta nuevamente.";
            }
        }
    }
}
// ============================================
// DATOS PARA LA VISTA
// ============================================
$csrf_token = generateCSRFToken();
$siteConfig = getSiteConfig($pdo);
$pageTitle = "Registro de Usuario - " . ($siteConfig['site_name'] ?? 'Cinema Pro');
// ✅ Logo seguro (evita imágenes rotas)
$authLogo = $siteConfig['site_logo'] ?? '';
$hasAuthLogo = !empty($authLogo) && (filter_var($authLogo, FILTER_VALIDATE_URL) || file_exists($authLogo));
$authLogoSrc = $authLogo;
if ($hasAuthLogo && !filter_var($authLogo, FILTER_VALIDATE_URL) && file_exists($authLogo)) {
    $authLogoSrc = $authLogo . '?v=' . filemtime($authLogo);
}
$siteName = $siteConfig['site_name'] ?? 'Cinema Pro';
$backUrl = 'index.php';
// ============================================
// ✅ FRONTEND UNIFICADO: HEADER DEL SITIO
// ============================================
require_once 'header.php';
?>
<style>
/* ============================================
✅ TEMA CLARO (igual que index.php)
============================================ */
body { background-color: #ffffff !important; color: #1f2937 !important; }
.auth-wrapper {
    min-height: calc(100vh - 320px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 16px;
}
.auth-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
    padding: 32px 28px;
    width: 100%;
    max-width: 480px;
}
.auth-logo {
    max-height: 70px;
    max-width: 100%;
    object-fit: contain;
    margin: 0 auto 8px auto;
    display: block;
}
.auth-title { font-size: 1.5rem; font-weight: 800; color: #4f46e5; text-align: center; }
.auth-subtitle { color: #6b7280; font-size: 0.9rem; text-align: center; }
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
.auth-btn {
    width: 100%;
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
.msg-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
    padding: 12px;
    border-radius: 8px;
    font-size: 0.875rem;
    text-align: center;
    font-weight: 600;
    margin-bottom: 16px;
}
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
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
    20%, 40%, 60%, 80% { transform: translateX(5px); }
}
.error-shake { animation: shake 0.5s ease-in-out; }
.input-error { border-color: #ef4444 !important; box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2) !important; }
.input-success { border-color: #22c55e !important; box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.2) !important; }
</style>

<div class="auth-wrapper">
    <div class="auth-card">
        <!-- ✅ LOGO + TÍTULO (sin imágenes rotas) -->
        <div class="text-center mb-6">
            <?php if ($hasAuthLogo): ?>
            <img
                src="<?= htmlspecialchars($authLogoSrc) ?>"
                alt="<?= htmlspecialchars($siteName) ?>"
                title="<?= htmlspecialchars($siteName) ?>"
                class="auth-logo"
                data-error-hide
            >
            <?php endif; ?>
            <h1 class="auth-title">Crear Cuenta</h1>
            <p class="auth-subtitle">Regístrate para comprar tus entradas</p>
        </div>

        <?php if ($error): ?>
        <div class="msg-error error-shake" id="errorBox">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-4" id="registerForm">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

            <div>
                <label class="auth-label">
                    <i class="fas fa-user mr-1"></i>Nombres y Apellidos *
                </label>
                <input
                    type="text"
                    name="name"
                    id="name"
                    required
                    value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                    maxlength="100"
                    placeholder="Ej: Juan Pérez"
                    class="auth-input"
                >
            </div>

            <div>
                <label class="auth-label">
                    <i class="fas fa-id-card mr-1"></i>Cédula de Identidad *
                </label>
                <div class="form-group-inline">
                    <select name="cedula_type" class="auth-input" style="flex: 0 0 80px;">
                        <option value="V" <?= (($_POST['cedula_type'] ?? '') == 'V') ? 'selected' : '' ?>>V</option>
                        <option value="E" <?= (($_POST['cedula_type'] ?? '') == 'E') ? 'selected' : '' ?>>E</option>
                        <option value="P" <?= (($_POST['cedula_type'] ?? '') == 'P') ? 'selected' : '' ?>>P</option>
                        <option value="J" <?= (($_POST['cedula_type'] ?? '') == 'J') ? 'selected' : '' ?>>J</option>
                    </select>
                    <!-- ✅ CSP-safe: sin oninput inline, se maneja en JS con data-only-numbers -->
                    <input
                        type="text"
                        name="cedula_number"
                        id="cedula_number"
                        required
                        value="<?= htmlspecialchars($_POST['cedula_number'] ?? '') ?>"
                        placeholder="Ej: 1234567"
                        pattern="[0-9]{7,}"
                        minlength="7"
                        maxlength="20"
                        inputmode="numeric"
                        data-only-numbers
                        title="Debe ingresar al menos 7 números"
                        class="auth-input"
                    >
                </div>
            </div>

            <div>
                <label class="auth-label">
                    <i class="fas fa-phone mr-1"></i>Teléfono Móvil *
                </label>
                <div class="phone-group-inline">
                    <select name="phone_prefix" class="auth-input" style="flex: 0 0 90px;">
                        <option value="412" <?= (($_POST['phone_prefix'] ?? '') == '412') ? 'selected' : '' ?>>0412</option>
                        <option value="414" <?= (($_POST['phone_prefix'] ?? '') == '414') ? 'selected' : '' ?>>0414</option>
                        <option value="416" <?= (($_POST['phone_prefix'] ?? '') == '416') ? 'selected' : '' ?>>0416</option>
                        <option value="424" <?= (($_POST['phone_prefix'] ?? '') == '424') ? 'selected' : '' ?>>0424</option>
                        <option value="426" <?= (($_POST['phone_prefix'] ?? '') == '426') ? 'selected' : '' ?>>0426</option>
                        <option value="422" <?= (($_POST['phone_prefix'] ?? '') == '422') ? 'selected' : '' ?>>0422</option>
                    </select>
                    <!-- ✅ CSP-safe: sin oninput inline, se maneja en JS con data-only-numbers -->
                    <input
                        type="text"
                        name="phone_number"
                        id="phone_number"
                        required
                        value="<?= htmlspecialchars($_POST['phone_number'] ?? '') ?>"
                        placeholder="Ej: 123456"
                        pattern="[0-9]{7,}"
                        minlength="7"
                        maxlength="20"
                        inputmode="numeric"
                        data-only-numbers
                        title="Debe ingresar al menos 7 números"
                        class="auth-input"
                    >
                </div>
            </div>

            <div>
                <label class="auth-label">
                    <i class="fas fa-calendar-alt mr-1"></i>Fecha de Nacimiento *
                </label>
                <input
                    type="date"
                    name="birth_date"
                    id="birth_date"
                    required
                    value="<?= htmlspecialchars($_POST['birth_date'] ?? '') ?>"
                    max="<?= date('Y-m-d', strtotime('-12 years')) ?>"
                    class="auth-input"
                >
            </div>

            <div>
                <label class="auth-label">
                    <i class="fas fa-envelope mr-1"></i>Correo Electrónico *
                </label>
                <input
                    type="email"
                    name="email"
                    id="email"
                    required
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    maxlength="100"
                    placeholder="ejemplo@email.com"
                    class="auth-input"
                >
            </div>

            <div>
                <label class="auth-label">
                    <i class="fas fa-lock mr-1"></i>Contraseña *
                </label>
                <div class="password-wrapper">
                    <input
                        type="password"
                        name="password"
                        id="regPassword"
                        required
                        placeholder="Mínimo 8 caracteres"
                        minlength="8"
                        maxlength="255"
                        pattern=".{8,}"
                        title="La contraseña debe tener al menos 8 caracteres"
                        class="auth-input"
                        style="padding-right: 40px;"
                    >
                    <!-- ✅ CSP-safe: sin onclick inline -->
                    <button
                        type="button"
                        class="password-toggle"
                        data-password-toggle="regPassword"
                        aria-label="Mostrar contraseña"
                    >
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <div>
                <label class="auth-label">
                    <i class="fas fa-check-circle mr-1"></i>Confirmar Contraseña *
                </label>
                <div class="password-wrapper">
                    <input
                        type="password"
                        name="confirm_password"
                        id="regConfirmPassword"
                        required
                        placeholder="Repite tu contraseña"
                        minlength="8"
                        maxlength="255"
                        pattern=".{8,}"
                        title="La contraseña debe tener al menos 8 caracteres"
                        class="auth-input"
                        style="padding-right: 40px;"
                    >
                    <!-- ✅ CSP-safe: sin onclick inline -->
                    <button
                        type="button"
                        class="password-toggle"
                        data-password-toggle="regConfirmPassword"
                        aria-label="Mostrar contraseña"
                    >
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" id="submitBtn" class="auth-btn mt-2">
                <i class="fas fa-user-plus"></i>
                Registrarse
            </button>
        </form>

        <div class="mt-6 text-center text-sm text-gray-500 border-t border-gray-200 pt-4">
            ¿Ya tienes una cuenta?
            <a href="login.php" class="text-indigo-600 hover:text-indigo-700 hover:underline font-semibold">
                Inicia Sesión
            </a>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>

<!-- ✅ Script con nonce CSP -->
<script nonce="<?= htmlspecialchars($cspNonce ?? '') ?>">
// ============================================
// TOGGLE PASSWORD VISIBILITY (delegación, CSP-safe)
// ============================================
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (!input || !icon) return;
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
        btn.setAttribute('aria-label', 'Ocultar contraseña');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
        btn.setAttribute('aria-label', 'Mostrar contraseña');
    }
}
document.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-password-toggle]');
    if (!btn) return;
    const inputId = btn.getAttribute('data-password-toggle');
    if (!inputId) return;
    togglePasswordVisibility(inputId, btn);
});
// ============================================
// ✅ SOLO NÚMEROS EN CÉDULA Y TELÉFONO
// Reemplaza el oninput inline que violaba la CSP
// ============================================
document.querySelectorAll('[data-only-numbers]').forEach(function(input) {
    input.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
    input.addEventListener('paste', function(e) {
        e.preventDefault();
        const pastedText = (e.clipboardData || window.clipboardData).getData('text');
        this.value = pastedText.replace(/[^0-9]/g, '');
    });
    input.addEventListener('keydown', function(e) {
        const allowedKeys = [
            'Backspace', 'Delete', 'Tab', 'Escape', 'Enter',
            'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown',
            'Home', 'End'
        ];
        if (allowedKeys.includes(e.key)) return;
        if (e.ctrlKey || e.metaKey) return;
        if (!/^[0-9]$/.test(e.key)) {
            e.preventDefault();
        }
    });
});
// ============================================
// VALIDACIÓN EN TIEMPO REAL DE CONTRASEÑAS
// ============================================
document.addEventListener('DOMContentLoaded', function () {
    const password = document.getElementById('regPassword');
    const confirmPassword = document.getElementById('regConfirmPassword');
    const submitBtn = document.getElementById('submitBtn');
    const form = document.getElementById('registerForm');
    function validatePasswords() {
        if (password.value.length > 0 && confirmPassword.value.length > 0) {
            if (password.value === confirmPassword.value) {
                confirmPassword.classList.remove('input-error');
                confirmPassword.classList.add('input-success');
            } else {
                confirmPassword.classList.remove('input-success');
                confirmPassword.classList.add('input-error');
            }
        } else {
            confirmPassword.classList.remove('input-error', 'input-success');
        }
    }
    password.addEventListener('input', validatePasswords);
    confirmPassword.addEventListener('input', validatePasswords);
    // ✅ Prevenir envío inválido
    form.addEventListener('submit', function (e) {
        if (password.value.length < 8) {
            e.preventDefault();
            alert('La contraseña debe tener al menos 8 caracteres.');
            password.classList.add('input-error');
            return false;
        }
        if (password.value !== confirmPassword.value) {
            e.preventDefault();
            alert('Las contraseñas no coinciden. Por favor, verifica.');
            confirmPassword.classList.add('input-error');
            return false;
        }
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registrando...';
    });
    // ✅ Autofocus en el primer campo
    const nameInput = document.getElementById('name');
    if (!nameInput.value) {
        nameInput.focus();
    }
});
// ============================================
// REMOVER ERROR DESPUÉS DE 5 SEGUNDOS
// ============================================
const errorBox = document.getElementById('errorBox');
if (errorBox) {
    setTimeout(function () {
        errorBox.style.opacity = '0';
        errorBox.style.transition = 'opacity 0.5s ease';
        setTimeout(function () {
            errorBox.style.display = 'none';
        }, 500);
    }, 5000);
}
</script>
</body>
</html>