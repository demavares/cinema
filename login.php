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
<link rel="stylesheet" href="assets/css/auth.css">
<link rel="stylesheet" href="assets/css/login.css">

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

        <p class="text-sm text-right mt-3">
            <a href="user/forgot_password.php" class="text-indigo-600 hover:text-indigo-700 hover:underline font-semibold">
                ¿Olvidaste tu contraseña?
            </a>
        </p>

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
<script src="assets/js/login.js"></script>
</body>
</html>