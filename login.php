<?php
require_once 'config.php';
// ============================================
// Helper para aplicar rate limiting después de un fallo
// ============================================
if (!function_exists('applyLoginFailedRateLimiting')) {
    function applyLoginFailedRateLimiting(
        $pdo,
        string $ip,
        string $email,
        string &$error,
        bool &$showCaptcha,
        bool &$isLockedOut,
        int &$remainingLockoutMinutes
    ): void {
        try {
            recordFailedLogin($pdo, $ip, $email);
        } catch (Throwable $e) {
            error_log("Error registrando fallo de login: " . $e->getMessage());
        }
        $_SESSION['login_rate_email'] = $email;
        try {
            $status = getLoginRateLimitStatus($pdo, $ip, $email);
        } catch (Throwable $e) {
            error_log("Error obteniendo estado de rate limiting: " . $e->getMessage());
            $status = [
                'limited' => false,
                'retry_after' => 0,
                'attempts' => 0,
                'attempts_left' => 0,
                'warning' => false
            ];
        }
        if (!empty($status['limited'])) {
            $isLockedOut = true;
            $remainingLockoutMinutes = max(1, (int)ceil(($status['retry_after'] ?? 0) / 60));
            $showCaptcha = false;
            $error = '';
            return;
        }
        $attemptsLeft = (int)($status['attempts_left'] ?? 0);
        if (!empty($status['warning'])) {
            $error = "El correo electrónico o la contraseña son incorrectos. Te queda 1 intento antes del bloqueo temporal.";
            $showCaptcha = true;
            return;
        }
        if ($attemptsLeft > 1) {
            $error = "El correo electrónico o la contraseña son incorrectos. Te quedan {$attemptsLeft} intentos.";
            $showCaptcha = false;
            return;
        }
        $error = "El correo electrónico o la contraseña son incorrectos.";
        $showCaptcha = false;
    }
}
// ============================================
// Si ya tiene sesión activa, redirigir según rol
// ============================================
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (PDOException $e) {
        error_log("Error actualizando last_login: " . $e->getMessage());
    }
    header('Location: ' . ($_SESSION['user_role'] === 'admin' ? 'admin/index.php' : 'index.php'));
    exit;
}
$error = '';
$showCaptcha = false;
$isLockedOut = false;
$remainingLockoutMinutes = 0;
$ip = getLoginIp();
// ============================================
// Verificar bloqueo por IP al cargar la página
// ============================================
try {
    $ipRate = checkRateLimitKey(
        $pdo,
        getLoginIpKey($ip),
        LOGIN_IP_MAX_ATTEMPTS,
        LOGIN_IP_WINDOW_MINUTES,
        LOGIN_IP_BLOCK_MINUTES
    );
    if ($ipRate['limited']) {
        $isLockedOut = true;
        $remainingLockoutMinutes = max(1, (int)ceil($ipRate['retry_after'] / 60));
        $showCaptcha = false;
        $error = '';
    }
} catch (Throwable $e) {
    error_log("Error verificando rate limit IP en login: " . $e->getMessage());
}
// ============================================
// Verificar estado del último email usado
// ============================================
if (!$isLockedOut && !empty($_SESSION['login_rate_email'])) {
    try {
        $lastRateEmail = trim((string)$_SESSION['login_rate_email']);
        if ($lastRateEmail !== '') {
            $status = getLoginRateLimitStatus($pdo, $ip, $lastRateEmail);
            if (!empty($status['limited'])) {
                $isLockedOut = true;
                $remainingLockoutMinutes = max(1, (int)ceil(($status['retry_after'] ?? 0) / 60));
                $showCaptcha = false;
                $error = '';
            } else {
                if (!empty($status['warning'])) {
                    $showCaptcha = true;
                }
                if ((int)($status['attempts'] ?? 0) === 0) {
                    unset($_SESSION['login_rate_email']);
                }
            }
        }
    } catch (Throwable $e) {
        error_log("Error verificando estado de rate limit por email: " . $e->getMessage());
    }
}
// ============================================
// PROCESAR LOGIN
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Error de seguridad: Token inválido. Por favor, recarga la página.";
        error_log("CSRF token inválido en login desde IP: " . $ip);
    } else {
        try {
            $ipRate = checkRateLimitKey(
                $pdo,
                getLoginIpKey($ip),
                LOGIN_IP_MAX_ATTEMPTS,
                LOGIN_IP_WINDOW_MINUTES,
                LOGIN_IP_BLOCK_MINUTES
            );
            if ($ipRate['limited']) {
                $isLockedOut = true;
                $remainingLockoutMinutes = max(1, (int)ceil($ipRate['retry_after'] / 60));
                $showCaptcha = false;
                $error = '';
            }
        } catch (Throwable $e) {
            error_log("Error verificando rate limit IP en login POST: " . $e->getMessage());
        }
        if (!$isLockedOut) {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            if (empty($email) || empty($password)) {
                $error = "Por favor, completa todos los campos.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "El formato del correo electrónico no es válido.";
            } else {
                try {
                    $preStatus = getLoginRateLimitStatus($pdo, $ip, $email);
                    if (!empty($preStatus['limited'])) {
                        $isLockedOut = true;
                        $remainingLockoutMinutes = max(1, (int)ceil(($preStatus['retry_after'] ?? 0) / 60));
                        $showCaptcha = false;
                        $error = '';
                        $_SESSION['login_rate_email'] = $email;
                    } else {
                        $stmt = $pdo->prepare("
                            SELECT *
                            FROM users
                            WHERE email = ?
                            LIMIT 1
                        ");
                        $stmt->execute([$email]);
                        $user = $stmt->fetch();
                        $passwordValid = false;
                        if ($user && !empty($user['password'])) {
                            $passwordValid = password_verify($password, $user['password']);
                        }
                        if ($user && $passwordValid) {
                            if (!empty($user['is_blocked']) && (int)$user['is_blocked'] === 1) {
                                error_log(sprintf(
                                    "⚠️ Intento de login con cuenta bloqueada: user_id=%d, account_hash=%s, IP=%s",
                                    $user['id'],
                                    hash('sha256', strtolower($email)),
                                    $ip
                                ));
                                applyLoginFailedRateLimiting(
                                    $pdo,
                                    $ip,
                                    $email,
                                    $error,
                                    $showCaptcha,
                                    $isLockedOut,
                                    $remainingLockoutMinutes
                                );
                            } else {
                                // ✅ LOGIN EXITOSO
                                resetLoginRateLimit($pdo, $ip, $email);
                                if (isset($_SESSION['login_rate_email'])) {
                                    unset($_SESSION['login_rate_email']);
                                }
                                session_regenerate_id(true);
                                $_SESSION['user_id'] = $user['id'];
                                $_SESSION['user_name'] = $user['name'];
                                $_SESSION['user_role'] = $user['role'];
                                $_SESSION['user_email'] = $user['email'];
                                $_SESSION['login_time'] = time();
                                $_SESSION['last_activity'] = time();
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
                                try {
                                    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                                    $stmt->execute([$user['id']]);
                                } catch (PDOException $e) {
                                    error_log("Error actualizando last_login: " . $e->getMessage());
                                }
                                error_log(sprintf(
                                    "✅ Login exitoso: user_id=%d, role=%s, IP=%s",
                                    $user['id'],
                                    $user['role'],
                                    $ip
                                ));
                                $redirectUrl = ($user['role'] === 'admin') ? 'admin/index.php' : 'index.php';
                                if (isset($_POST['redirect_to']) && !empty($_POST['redirect_to'])) {
                                    $redirectTo = $_POST['redirect_to'];
                                    if (
                                        strpos($redirectTo, '/') === 0 &&
                                        strpos($redirectTo, '//') !== 0 &&
                                        strpos($redirectTo, '\\') === false
                                    ) {
                                        $redirectUrl = $redirectTo;
                                    }
                                }
                                header('Location: ' . $redirectUrl);
                                exit;
                            }
                        } else {
                            error_log(sprintf(
                                "❌ Login fallido: account_hash=%s, IP=%s",
                                hash('sha256', strtolower($email)),
                                $ip
                            ));
                            applyLoginFailedRateLimiting(
                                $pdo,
                                $ip,
                                $email,
                                $error,
                                $showCaptcha,
                                $isLockedOut,
                                $remainingLockoutMinutes
                            );
                        }
                    }
                } catch (PDOException $e) {
                    error_log("Error en login: " . $e->getMessage());
                    $error = "Ocurrió un error al procesar tu solicitud. Por favor, intenta nuevamente.";
                } catch (Throwable $e) {
                    error_log("Error general en login: " . $e->getMessage());
                    $error = "Ocurrió un error inesperado. Por favor, intenta nuevamente.";
                }
            }
        }
    }
}
// ============================================
// DATOS PARA LA VISTA
// ============================================
$csrf_token = generateCSRFToken();
$siteConfig = getSiteConfig($pdo);
// ✅ Logo seguro (evita imágenes rotas)
$authLogo = $siteConfig['site_logo'] ?? '';
$hasAuthLogo = !empty($authLogo) && (filter_var($authLogo, FILTER_VALIDATE_URL) || file_exists($authLogo));
$authLogoSrc = $authLogo;
if ($hasAuthLogo && !filter_var($authLogo, FILTER_VALIDATE_URL) && file_exists($authLogo)) {
    $authLogoSrc = $authLogo . '?v=' . filemtime($authLogo);
}
$siteName = $siteConfig['site_name'] ?? 'Cinema Pro';
$pageTitle = "Iniciar Sesión - " . $siteName;
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
    max-width: 430px;
}
.auth-logo {
    max-height: 80px;
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
.msg { padding: 12px; border-radius: 8px; font-size: 0.875rem; text-align: center; font-weight: 600; margin-bottom: 16px; }
.msg-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
.msg-info { background: #dbeafe; color: #1d4ed8; border: 1px solid #bfdbfe; }
.msg-warning { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
.msg-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
.lockout-box {
    background: #fee2e2;
    border: 1px solid #fca5a5;
    color: #991b1b;
    padding: 16px;
    border-radius: 8px;
    font-size: 0.875rem;
    text-align: center;
    margin-bottom: 16px;
}
.attempts-warning {
    background: #fef3c7;
    border: 1px solid #fde68a;
    color: #92400e;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 0.85rem;
    margin-top: 8px;
    text-align: center;
}
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
            <h2 class="auth-title">Iniciar Sesión</h2>
            <p class="auth-subtitle"><?= htmlspecialchars($siteName) ?></p>
        </div>

        <?php if (isset($_GET['registered'])): ?>
        <div class="msg msg-success">
            <i class="fas fa-check-circle mr-1"></i>
            ¡Registro exitoso! Ya puedes ingresar.
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['logout'])): ?>
        <div class="msg msg-info">
            <i class="fas fa-sign-out-alt mr-1"></i>
            Has cerrado sesión correctamente.
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['session_expired'])): ?>
        <div class="msg msg-warning">
            <i class="fas fa-clock mr-1"></i>
            Tu sesión ha expirado. Por favor, inicia sesión nuevamente.
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="msg msg-error error-shake" id="errorBox">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if ($isLockedOut): ?>
        <div class="lockout-box">
            <i class="fas fa-lock mr-2"></i>
            <p class="font-bold mb-1">Acceso bloqueado temporalmente</p>
            <p>
                Por seguridad, debes esperar
                <strong><?= (int)$remainingLockoutMinutes ?> minuto(s)</strong>
                antes de intentar nuevamente.
            </p>
        </div>
        <?php endif; ?>

        <form
            action="login.php"
            method="POST"
            class="space-y-4"
            id="loginForm"
            <?= $isLockedOut ? 'style="opacity: 0.5; pointer-events: none;"' : '' ?>
        >
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <?php if (isset($_GET['redirect'])): ?>
            <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($_GET['redirect']) ?>">
            <?php endif; ?>

            <div>
                <label class="auth-label" for="email">
                    <i class="fas fa-envelope mr-1"></i>Correo Electrónico
                </label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    required
                    autocomplete="email"
                    maxlength="255"
                    class="auth-input"
                    placeholder="tu@email.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                >
            </div>

            <div>
                <label class="auth-label" for="password">
                    <i class="fas fa-lock mr-1"></i>Contraseña
                </label>
                <div class="password-wrapper">
                    <input
                        type="password"
                        id="loginPassword"
                        name="password"
                        required
                        autocomplete="current-password"
                        maxlength="255"
                        class="auth-input"
                        placeholder="Contraseña"
                    >
                    <!-- ✅ CSP-safe: sin onclick inline -->
                    <button
                        type="button"
                        class="password-toggle"
                        data-password-toggle="loginPassword"
                        aria-label="Mostrar contraseña"
                    >
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button
                type="submit"
                id="submitBtn"
                class="auth-btn mt-2 <?= $isLockedOut ? 'btn-disabled' : '' ?>"
                <?= $isLockedOut ? 'disabled' : '' ?>
            >
                <i class="fas fa-sign-in-alt"></i>
                Entrar
            </button>
        </form>

        <?php if ($showCaptcha && !$isLockedOut): ?>
        <div class="attempts-warning">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            Último intento antes del bloqueo temporal
        </div>
        <?php endif; ?>

        <p class="text-sm text-gray-500 mt-6 text-center">
            ¿No tienes una cuenta?
            <a href="register.php" class="text-indigo-600 hover:text-indigo-700 hover:underline font-semibold">
                Regístrate aquí
            </a>
        </p>

        <div class="mt-4 pt-4 border-t border-gray-200 text-center">
            <a href="index.php" class="text-xs text-gray-500 hover:text-gray-700">
                <i class="fas fa-arrow-left mr-1"></i>Volver al inicio
            </a>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>

<!-- ✅ Script con nonce CSP -->
<script nonce="<?= htmlspecialchars($cspNonce ?? '') ?>">
// ============================================
// TOGGLE PASSWORD VISIBILITY
// ============================================
function togglePasswordVisibility(inputId, button) {
    const input = document.getElementById(inputId);
    const icon = button.querySelector('i');
    if (!input || !icon) return;
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
        button.setAttribute('aria-label', 'Ocultar contraseña');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
        button.setAttribute('aria-label', 'Mostrar contraseña');
    }
}
// ✅ Reemplaza el onclick inline por delegación de eventos (CSP-safe)
document.addEventListener('click', function(event) {
    const button = event.target.closest('[data-password-toggle]');
    if (!button) return;
    const inputId = button.getAttribute('data-password-toggle');
    if (!inputId) return;
    togglePasswordVisibility(inputId, button);
});
// ============================================
// PREVENIR REENVÍO DEL FORMULARIO AL RECARGAR
// ============================================
if (window.history && window.history.replaceState) {
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
}
// ============================================
// VALIDACIÓN EN TIEMPO REAL
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('loginPassword');
    const submitBtn = document.getElementById('submitBtn');
    const loginForm = document.getElementById('loginForm');
    emailInput.addEventListener('blur', function() {
        const email = this.value.trim();
        if (email && !email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
            this.style.borderColor = '#ef4444';
        } else {
            this.style.borderColor = '#cbd5e1';
        }
    });
    loginForm.addEventListener('submit', function(e) {
        if (submitBtn.disabled) {
            e.preventDefault();
            return false;
        }
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verificando...';
        submitBtn.disabled = true;
    });
    if (!emailInput.value) {
        emailInput.focus();
    } else {
        passwordInput.focus();
    }
});
// ============================================
// REMOVER ANIMACIÓN DE ERROR DESPUÉS DE 2 SEGUNDOS
// ============================================
const errorBox = document.getElementById('errorBox');
if (errorBox) {
    setTimeout(function() {
        errorBox.classList.remove('error-shake');
    }, 2000);
}
</script>
</body>
</html>