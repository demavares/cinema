<?php
require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/lib/mailer.php';

// Redirigir si ya está logueado
if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$error = '';
$success = '';
$forgotIpLimited = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rate limit por IP para evitar spam del formulario de recuperación (5 intentos / 15 min)
    try {
        $forgotIpLimited = !checkRateLimit('forgot_password', 5, 15);
    } catch (Throwable $e) {
        error_log("Error rate limit forgot_password: " . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$forgotIpLimited) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Error de seguridad: Token inválido. Por favor, recarga la página.";
    } else {
        $email = trim($_POST['email'] ?? '');
        if (empty($email)) {
            $error = "Por favor, ingresa tu correo electrónico.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "El formato del correo electrónico no es válido.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                // Siempre muestra el mismo mensaje (evita enumeración de cuentas)
                $success = "Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.";

                if ($user) {
                    // Limpiar tokens anteriores y caducados
                    $stmtDel = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ? OR expires_at < NOW()");
                    $stmtDel->execute([$user['id']]);

                    // Generar token único
                    $token = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $token);
                    $expiresAt = date('Y-m-d H:i:s', time() + 3600);

                    $stmtIns = $pdo->prepare("
                        INSERT INTO password_resets (user_id, token_hash, expires_at, used)
                        VALUES (?, ?, ?, 0)
                    ");
                    $stmtIns->execute([$user['id'], $tokenHash, $expiresAt]);

                    // Construir URL absoluta del enlace de restablecimiento
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
                    $resetUrl = $scheme . '://' . $host . rtrim($scriptDir, '/') . '/reset_password.php?token=' . $token;

                    $siteConfig = getSiteConfig($pdo);
                    $siteName = $siteConfig['site_name'] ?? 'Cinema Pro';

                    $subject = "Recuperación de contraseña - " . $siteName;
                    $htmlBody = "
                        <div style=\"font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;padding:24px;border:1px solid #e2e8f0;border-radius:12px;\">
                            <h2 style=\"color:#0f172a;margin-top:0;\">Hola, " . htmlspecialchars($user['name']) . "</h2>
                            <p style=\"color:#334155;\">Recibimos una solicitud para restablecer la contraseña de tu cuenta.</p>
                            <p style=\"color:#334155;\">Para continuar, haz clic en el botón (el enlace es válido por <strong>1 hora</strong>):</p>
                            <p style=\"text-align:center;margin:28px 0;\">
                                <a href=\"" . htmlspecialchars($resetUrl) . "\" style=\"background:#4f46e5;color:#ffffff;padding:14px 28px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block;\">Restablecer contraseña</a>
                            </p>
                            <p style=\"color:#64748b;font-size:13px;\">Si no solicitaste este cambio, puedes ignorar este correo y tu contraseña no cambiará.</p>
                            <p style=\"color:#94a3b8;font-size:12px;border-top:1px solid #e2e8f0;padding-top:14px;\">" . htmlspecialchars($siteName) . " — Envío automático, por favor no respondas a este mensaje.</p>
                        </div>
                    ";
                    $altBody = "Hola " . $user['name'] . ",\n\nRecibimos una solicitud para restablecer la contraseña de tu cuenta.\n\nAbre este enlace (válido por 1 hora):\n" . $resetUrl . "\n\nSi no solicitaste este cambio, ignora este correo.";

                    $mailResult = sendAppMail($user['email'], $user['name'], $subject, $htmlBody, $altBody);

                    if (!$mailResult['ok']) {
                        error_log("⚠️ Forgot password: no se pudo enviar correo a user_id=" . $user['id'] . " - " . $mailResult['error']);
                    }
                }
            } catch (Throwable $e) {
                error_log("Error en forgot_password: " . $e->getMessage());
                $error = "Ocurrió un error al procesar tu solicitud. Por favor, intenta nuevamente.";
            }
        }
    }
}

// DATOS PARA LA VISTA
$csrf_token = generateCSRFToken();
$siteConfig = getSiteConfig($pdo);
$siteName = $siteConfig['site_name'] ?? 'Cinema Pro';
$authLogo = $siteConfig['site_logo'] ?? '';
$hasAuthLogo = !empty($authLogo) && (filter_var($authLogo, FILTER_VALIDATE_URL) || file_exists($authLogo));
$authLogoSrc = $authLogo;
if ($hasAuthLogo && !filter_var($authLogo, FILTER_VALIDATE_URL) && file_exists($authLogo)) {
    $authLogoSrc = $authLogo . '?v=' . filemtime($authLogo);
}
$pageTitle = "Recuperar Contraseña - " . $siteName;
$backUrl = '../index.php';
require_once dirname(__DIR__) . '/header.php';
?>
<link rel="stylesheet" href="<?= $publicPathPrefix ?>assets/css/auth.css">
<link rel="stylesheet" href="<?= $publicPathPrefix ?>assets/css/login.css">

<div class="auth-wrapper">
    <div class="auth-card">
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
            <h2 class="auth-title">Recuperar Contraseña</h2>
            <p class="auth-subtitle">Ingresa tu correo y te enviaremos un enlace para restablecerla</p>
        </div>

        <?php if ($error): ?>
        <div class="msg msg-error error-shake" id="errorBox">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="msg msg-success">
            <i class="fas fa-check-circle mr-1"></i>
            <?= htmlspecialchars($success) ?>
        </div>
        <?php endif; ?>

        <?php if ($forgotIpLimited): ?>
        <div class="lockout-box">
            <i class="fas fa-lock mr-2"></i>
            <p class="font-bold mb-1">Demasiados intentos</p>
            <p>Espera unos minutos antes de volver a intentar.</p>
        </div>
        <?php endif; ?>

        <form action="forgot_password.php" method="POST" class="space-y-4" <?= $forgotIpLimited ? 'style="opacity: 0.5; pointer-events: none;"' : '' ?>>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

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

            <button type="submit" id="submitBtn" class="auth-btn mt-2">
                <i class="fas fa-paper-plane"></i>
                Enviar enlace de recuperación
            </button>
        </form>

        <p class="text-sm text-gray-500 mt-6 text-center">
            ¿Recordaste tu contraseña?
            <a href="../login.php" class="text-indigo-600 hover:text-indigo-700 hover:underline font-semibold">
                Inicia sesión aquí
            </a>
        </p>

        <div class="mt-4 pt-4 border-t border-gray-200 text-center">
            <a href="../index.php" class="text-xs text-gray-500 hover:text-gray-700">
                <i class="fas fa-arrow-left mr-1"></i>Volver al inicio
            </a>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/footer.php'; ?>
</body>
</html>