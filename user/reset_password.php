<?php
require_once dirname(__DIR__) . '/config.php';

// Redirigir si ya está logueado
if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$error = '';
$success = '';
$token = trim((string)($_GET['token'] ?? ''));
$tokenValid = false;
$resetUserId = null;
$resetUserName = '';

// Simulación de plantillas para la vista
$showNewPasswordForm = false;

if ($token !== '') {
    $tokenHash = hash('sha256', $token);
    try {
        $stmt = $pdo->prepare("
            SELECT pr.user_id, pr.expires_at, u.name
            FROM password_resets pr
            JOIN users u ON u.id = pr.user_id
            WHERE pr.token_hash = ? AND pr.used = 0
            LIMIT 1
        ");
        $stmt->execute([$tokenHash]);
        $resetRow = $stmt->fetch();

        if ($resetRow) {
            // Evitar inyección de fechas: comparar en PHP
            $expiresTs = strtotime($resetRow['expires_at']);
            if ($expiresTs !== false && $expiresTs > time()) {
                $tokenValid = true;
                $resetUserId = (int)$resetRow['user_id'];
                $resetUserName = $resetRow['name'];
                $showNewPasswordForm = true;
            } else {
                $error = "El enlace ha caducado. Solicita uno nuevo.";
                // Eliminar tokens caducados
                $stmtDelExp = $pdo->prepare("DELETE FROM password_resets WHERE token_hash = ?");
                $stmtDelExp->execute([$tokenHash]);
            }
        } else {
            $error = "El enlace no es válido o ya ha sido utilizado. Solicita uno nuevo.";
        }
    } catch (Throwable $e) {
        error_log("Error validando token de reset: " . $e->getMessage());
        $error = "Ocurrió un error al validar el enlace. Por favor, intenta nuevamente.";
    }
} else {
    $error = "Falta el token de recuperación.";
}

// Procesar nueva contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postToken = trim((string)($_POST['token'] ?? ''));
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Error de seguridad: Token inválido. Por favor, recarga la página.";
    } else {
        $postTokenHash = hash('sha256', $postToken);
        try {
            $stmt = $pdo->prepare("
                SELECT pr.user_id, pr.expires_at, u.name
                FROM password_resets pr
                JOIN users u ON u.id = pr.user_id
                WHERE pr.token_hash = ? AND pr.used = 0
                LIMIT 1
            ");
            $stmt->execute([$postTokenHash]);
            $resetRow = $stmt->fetch();

            if (!$resetRow || strtotime((string)$resetRow['expires_at']) <= time()) {
                $error = "El enlace no es válido o ha caducado. Solicita uno nuevo.";
                $showNewPasswordForm = false;
                $tokenValid = false;
            } else {
                $postUserId = (int)$resetRow['user_id'];

                $passwordLength = function_exists('mb_strlen')
                    ? mb_strlen($newPassword, 'UTF-8')
                    : strlen($newPassword);

                if (empty($newPassword) || empty($confirmPassword)) {
                    $error = "Todos los campos son obligatorios.";
                } elseif ($newPassword !== $confirmPassword) {
                    $error = "Las contraseñas no coinciden.";
                } elseif ($passwordLength < 8) {
                    $error = "La contraseña debe tener al menos 8 caracteres.";
                } else {
                    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $pdo->beginTransaction();
                    try {
                        $stmtUpd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmtUpd->execute([$newHash, $postUserId]);

                        // Invalidar todos los tokens del usuario
                        $stmtInv = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
                        $stmtInv->execute([$postUserId]);

                        // Invalida cualquier sesión previa del usuario
                        $stmtSess = $pdo->prepare("DELETE FROM sessions WHERE user_id = ?");
                        try {
                            $stmtSess->execute([$postUserId]);
                        } catch (Throwable $e) {
                            // La tabla sessions puede no existir; no es bloqueante
                        }

                        $pdo->commit();
                        error_log("✅ Contraseña restablecida correctamente para user_id=" . $postUserId);
                        header('Location: ../login.php?reset=1');
                        exit;
                    } catch (PDOException $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        error_log("Error actualizando contraseña en reset: " . $e->getMessage());
                        $error = "Ocurrió un error al actualizar tu contraseña. Por favor, intenta nuevamente.";
                    }
                }
            }
        } catch (Throwable $e) {
            error_log("Error procesando reset: " . $e->getMessage());
            $error = "Ocurrió un error al procesar tu solicitud. Por favor, intenta nuevamente.";
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
$pageTitle = "Restablecer Contraseña - " . $siteName;
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
            <h2 class="auth-title">Restablecer Contraseña</h2>
            <?php if ($showNewPasswordForm): ?>
            <p class="auth-subtitle">Hola <?= htmlspecialchars($resetUserName) ?>, ingresa tu nueva contraseña</p>
            <?php else: ?>
            <p class="auth-subtitle"><?= htmlspecialchars($siteName) ?></p>
            <?php endif; ?>
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

        <?php if ($showNewPasswordForm): ?>
        <form action="reset_password.php" method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <div>
                <label class="auth-label" for="password">
                    <i class="fas fa-lock mr-1"></i>Nueva Contraseña
                </label>
                <div class="password-wrapper">
                    <input
                        type="password"
                        id="resetPassword"
                        name="password"
                        required
                        placeholder="Mínimo 8 caracteres"
                        minlength="8"
                        maxlength="255"
                        pattern=".{8,}"
                        title="La contraseña debe tener al menos 8 caracteres"
                        class="auth-input"
                        style="padding-right: 40px;"
                    >
                    <button
                        type="button"
                        class="password-toggle"
                        data-password-toggle="resetPassword"
                        aria-label="Mostrar contraseña"
                    >
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <div>
                <label class="auth-label" for="confirm_password">
                    <i class="fas fa-check-circle mr-1"></i>Confirmar Contraseña
                </label>
                <div class="password-wrapper">
                    <input
                        type="password"
                        id="resetConfirmPassword"
                        name="confirm_password"
                        required
                        placeholder="Repite tu contraseña"
                        minlength="8"
                        maxlength="255"
                        pattern=".{8,}"
                        title="Debe coincidir con la nueva contraseña"
                        class="auth-input"
                        style="padding-right: 40px;"
                    >
                    <button
                        type="button"
                        class="password-toggle"
                        data-password-toggle="resetConfirmPassword"
                        aria-label="Mostrar contraseña"
                    >
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" id="submitBtn" class="auth-btn mt-2">
                <i class="fas fa-key"></i>
                Guardar nueva contraseña
            </button>
        </form>
        <?php else: ?>
        <p class="text-sm text-gray-500 mt-6 text-center">
            <a href="forgot_password.php" class="text-indigo-600 hover:text-indigo-700 hover:underline font-semibold">
                Solicitar un nuevo enlace
            </a>
        </p>
        <?php endif; ?>

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