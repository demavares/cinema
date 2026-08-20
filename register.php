<?php
require_once 'config.php';

if (!function_exists('getFaviconHref')) {
    function getFaviconHref($siteConfig)
    {
        $favicon = trim($siteConfig['site_favicon'] ?? ($siteConfig['favicon'] ?? ''));

        if ($favicon === '') {
            return 'favicon.png';
        }

        $urlPath = parse_url($favicon, PHP_URL_PATH);
        $localPath = ltrim($urlPath ?: $favicon, '/');

        if ($localPath !== '' && is_file($localPath)) {
            return $favicon . '?v=' . filemtime($localPath);
        }

        if (filter_var($favicon, FILTER_VALIDATE_URL)) {
            return $favicon;
        }

        return 'favicon.png';
    }
}

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
                // 🛡️ CORRECCIÓN: PREVENCIÓN DE ENUMERACIÓN DE CUENTAS
                // Se verifica si existe la cédula o el correo, pero se devuelve
                // un mensaje genérico para no revelar cuál de los dos está registrado.
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

                    // ✅ Iniciar transacción
                    $pdo->beginTransaction();

                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO users (
                                name,
                                email,
                                cedula_type,
                                cedula_number,
                                phone_prefix,
                                phone_number,
                                birth_date,
                                password,
                                role
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'user')
                        ");

                        $stmt->execute([
                            $name,
                            $email,
                            $cedula_type,
                            $cedula_number,
                            $phone_prefix,
                            $phone_number,
                            $birth_date,
                            $passwordHash
                        ]);

                        $userId = $pdo->lastInsertId();

                        // ✅ Confirmar transacción
                        $pdo->commit();

                        // ✅ REGENERAR ID DE SESIÓN PARA PREVENIR SESSION FIXATION
                        session_regenerate_id(true);

                        $_SESSION['user_id'] = $userId;
                        $_SESSION['user_name'] = $name;
                        $_SESSION['user_role'] = 'user';
                        $_SESSION['user_email'] = $email;
                        $_SESSION['login_time'] = time();

                        // ✅ Limpiar cualquier sesión residual
                        $sessionPrefixes = [
                            'food_',
                            'purchase_',
                            'ticket_',
                            'total_',
                            'subtotal_',
                            'tax_',
                            'payment_'
                        ];

                        foreach ($_SESSION as $key => $value) {
                            foreach ($sessionPrefixes as $prefix) {
                                if (strpos($key, $prefix) === 0) {
                                    unset($_SESSION[$key]);
                                    break;
                                }
                            }
                        }

                        // ✅ Log de registro exitoso
                        error_log(sprintf(
                            "✅ Registro exitoso: user_id=%d, email=%s, IP=%s",
                            $userId,
                            $email,
                            $_SERVER['REMOTE_ADDR']
                        ));

                        header('Location: index.php?msg=' . urlencode("¡Registro exitoso! Bienvenido " . htmlspecialchars($name) . "."));
                        exit;

                    } catch (PDOException $e) {
                        // ✅ Rollback en caso de error
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }

                        // 🛡️ CORRECCIÓN: EVITAR FUGA DE CONTRASEÑAS EN LOGS
                        $safePost = $_POST;
                        unset(
                            $safePost['password'],
                            $safePost['confirm_password'],
                            $safePost['csrf_token']
                        );

                        error_log("❌ Error en registro DB: " . $e->getMessage() . " - Data: " . print_r($safePost, true));

                        if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062) {
                            // Duplicado a nivel de BD, posiblemente por race condition
                            $error = "Ya existe un usuario registrado con esos datos. Por favor, intenta con otros.";
                        } else {
                            // 🛡️ CORRECCIÓN: EVITAR INFORMATION DISCLOSURE
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

$csrf_token = generateCSRFToken();
$siteConfig = getSiteConfig($pdo);

$pageTitle = "Registro de Usuario - " . ($siteConfig['site_name'] ?? 'Cinema Pro');

// ✅ Obtener logo del sitio
$siteLogo = $siteConfig['site_logo'] ?? '';

$hasLogo = !empty($siteLogo) && (
    filter_var($siteLogo, FILTER_VALIDATE_URL) || file_exists($siteLogo)
);

$logoSrc = $siteLogo;

if (!filter_var($siteLogo, FILTER_VALIDATE_URL) && file_exists($siteLogo)) {
    $logoSrc = $siteLogo . '?v=' . filemtime($siteLogo);
}

$siteName = $siteConfig['site_name'] ?? 'Cinema Pro';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- Favicon dinámico desde configuración de admin -->
    <link rel="icon" href="<?= htmlspecialchars(getFaviconHref($siteConfig)) ?>">
    <link rel="shortcut icon" href="<?= htmlspecialchars(getFaviconHref($siteConfig)) ?>">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        .password-wrapper {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            cursor: pointer;
            background: none;
            border: none;
            padding: 4px;
        }

        .password-toggle:hover {
            color: #e5e7eb;
        }

        .form-group-inline {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .form-group-inline select {
            flex: 0 0 80px;
        }

        .form-group-inline input {
            flex: 1;
        }

        .phone-group-inline {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .phone-group-inline select {
            flex: 0 0 90px;
        }

        .phone-group-inline input {
            flex: 1;
        }

        .register-logo {
            max-height: 60px;
            max-width: 100%;
            object-fit: contain;
            margin-bottom: 8px;
        }

        @keyframes shake {
            0%, 100% {
                transform: translateX(0);
            }
            10%, 30%, 50%, 70%, 90% {
                transform: translateX(-5px);
            }
            20%, 40%, 60%, 80% {
                transform: translateX(5px);
            }
        }

        .error-shake {
            animation: shake 0.5s ease-in-out;
        }

        .input-error {
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2) !important;
        }

        .input-success {
            border-color: #22c55e !important;
            box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.2) !important;
        }
    </style>
</head>

<body class="bg-gray-900 text-white min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-gray-800 rounded-lg shadow-xl p-8 border border-gray-700 my-8">

        <!-- ✅ LOGO + TÍTULO -->
        <div class="text-center mb-6">
            <?php if ($hasLogo): ?>
                <img
                    src="<?= htmlspecialchars($logoSrc) ?>"
                    alt="<?= htmlspecialchars($siteName) ?>"
                    title="<?= htmlspecialchars($siteName) ?>"
                    class="register-logo mx-auto"
                >
            <?php endif; ?>

            <h1 class="text-2xl font-bold text-indigo-400">
                Crear Cuenta
            </h1>

            <p class="text-sm text-gray-400 mt-1">
                Regístrate para comprar tus entradas
            </p>
        </div>

        <?php if ($error): ?>
            <div
                class="bg-red-600/20 text-red-400 p-3 rounded-lg mb-6 text-sm border border-red-500/30 error-shake"
                id="errorBox"
            >
                <i class="fas fa-exclamation-triangle mr-1"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-4" id="registerForm">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

            <div>
                <label class="block text-sm text-gray-400 mb-1">
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
                    class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
            </div>

            <div>
                <label class="block text-sm text-gray-400 mb-1">
                    <i class="fas fa-id-card mr-1"></i>Cédula de Identidad *
                </label>
                <div class="form-group-inline">
                    <select
                        name="cedula_type"
                        class="bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <option value="V" <?= (($_POST['cedula_type'] ?? '') == 'V') ? 'selected' : '' ?>>V</option>
                        <option value="E" <?= (($_POST['cedula_type'] ?? '') == 'E') ? 'selected' : '' ?>>E</option>
                        <option value="P" <?= (($_POST['cedula_type'] ?? '') == 'P') ? 'selected' : '' ?>>P</option>
                        <option value="J" <?= (($_POST['cedula_type'] ?? '') == 'J') ? 'selected' : '' ?>>J</option>
                    </select>

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
                        oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                        title="Debe ingresar al menos 7 números"
                        class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                </div>
            </div>

            <div>
                <label class="block text-sm text-gray-400 mb-1">
                    <i class="fas fa-phone mr-1"></i>Teléfono Móvil *
                </label>
                <div class="phone-group-inline">
                    <select
                        name="phone_prefix"
                        class="bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <option value="412" <?= (($_POST['phone_prefix'] ?? '') == '412') ? 'selected' : '' ?>>0412</option>
                        <option value="414" <?= (($_POST['phone_prefix'] ?? '') == '414') ? 'selected' : '' ?>>0414</option>
                        <option value="416" <?= (($_POST['phone_prefix'] ?? '') == '416') ? 'selected' : '' ?>>0416</option>
                        <option value="424" <?= (($_POST['phone_prefix'] ?? '') == '424') ? 'selected' : '' ?>>0424</option>
                        <option value="426" <?= (($_POST['phone_prefix'] ?? '') == '426') ? 'selected' : '' ?>>0426</option>
                        <option value="422" <?= (($_POST['phone_prefix'] ?? '') == '422') ? 'selected' : '' ?>>0422</option>
                    </select>

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
                        oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                        title="Debe ingresar al menos 7 números"
                        class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                </div>
            </div>

            <div>
                <label class="block text-sm text-gray-400 mb-1">
                    <i class="fas fa-calendar-alt mr-1"></i>Fecha de Nacimiento *
                </label>
                <input
                    type="date"
                    name="birth_date"
                    id="birth_date"
                    required
                    value="<?= htmlspecialchars($_POST['birth_date'] ?? '') ?>"
                    max="<?= date('Y-m-d', strtotime('-12 years')) ?>"
                    class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
            </div>

            <div>
                <label class="block text-sm text-gray-400 mb-1">
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
                    class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
            </div>

            <div>
                <label class="block text-sm text-gray-400 mb-1">
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
                        class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 pr-10"
                    >

                    <button
                        type="button"
                        class="password-toggle"
                        onclick="togglePasswordVisibility('regPassword', this)"
                        aria-label="Mostrar contraseña"
                    >
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <div>
                <label class="block text-sm text-gray-400 mb-1">
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
                        class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 pr-10"
                    >

                    <button
                        type="button"
                        class="password-toggle"
                        onclick="togglePasswordVisibility('regConfirmPassword', this)"
                        aria-label="Mostrar contraseña"
                    >
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button
                type="submit"
                id="submitBtn"
                class="w-full bg-indigo-600 hover:bg-indigo-700 p-3 rounded-lg font-bold transition-colors shadow-md mt-2 flex items-center justify-center gap-2"
            >
                <i class="fas fa-user-plus"></i>
                Registrarse
            </button>
        </form>

        <div class="mt-6 text-center text-sm text-gray-400 border-t border-gray-700 pt-4">
            ¿Ya tienes una cuenta?
            <a href="login.php" class="text-indigo-400 hover:underline font-semibold">
                Inicia Sesión
            </a>
        </div>
    </div>

    <script>
        // ============================================
        // TOGGLE PASSWORD VISIBILITY
        // ============================================
        function togglePasswordVisibility(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');

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