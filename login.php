<?php
require_once 'config.php';

// ============================================
// ✅ RATE LIMITING - PROTECCIÓN CONTRA FUERZA BRUTA
// ============================================
$maxAttempts = 5;                    // Máximo intentos permitidos
$lockoutMinutes = 10;                // Minutos de bloqueo
$attemptWindow = 30 * 60;            // Ventana de tiempo (30 minutos)

// Identificar al cliente (IP + User Agent)
$clientIdentifier = $_SERVER['REMOTE_ADDR'] . '_' . md5($_SERVER['HTTP_USER_AGENT'] ?? '');
$loginAttemptsKey = 'login_attempts_' . $clientIdentifier;

// Verificar si está bloqueado
$isLockedOut = false;
$remainingLockoutMinutes = 0;

if (isset($_SESSION[$loginAttemptsKey])) {
    $attemptData = $_SESSION[$loginAttemptsKey];
    
    // Verificar si los intentos expiraron (ventana de 30 minutos)
    if (time() - $attemptData['first_attempt'] > $attemptWindow) {
        // Resetear contador si expiró la ventana
        unset($_SESSION[$loginAttemptsKey]);
    } elseif ($attemptData['count'] >= $maxAttempts) {
        // Verificar si aún está en periodo de bloqueo
        $timeSinceLastAttempt = time() - $attemptData['last_attempt'];
        $lockoutSeconds = $lockoutMinutes * 60;
        
        if ($timeSinceLastAttempt < $lockoutSeconds) {
            $isLockedOut = true;
            $remainingLockoutMinutes = ceil(($lockoutSeconds - $timeSinceLastAttempt) / 60);
        } else {
            // El bloqueo expiró, resetear
            unset($_SESSION[$loginAttemptsKey]);
        }
    }
}

// Si ya tiene sesión activa, redirigir según su rol
if (isset($_SESSION['user_id'])) {
    // Actualizar último acceso
    try {
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (PDOException $e) {
        error_log("Error actualizando last_login: " . $e->getMessage());
    }
    
    header('Location: ' . ($_SESSION['user_role'] === 'admin' ? 'admin.php' : 'index.php'));
    exit;
}

$error = '';
$showCaptcha = false;

// ============================================
// PROCESAR EL FORMULARIO DE LOGIN
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ✅ Verificar CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Error de seguridad: Token inválido. Por favor, recarga la página.";
        error_log("CSRF token inválido en login desde IP: " . $_SERVER['REMOTE_ADDR']);
    } 
    // ✅ Verificar si está bloqueado
    elseif ($isLockedOut) {
        $error = "Demasiados intentos fallidos. Por favor, espera $remainingLockoutMinutes minuto(s) antes de intentar nuevamente.";
    } 
    else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Validaciones básicas
        if (empty($email) || empty($password)) {
            $error = "Por favor, completa todos los campos.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "El formato del correo electrónico no es válido.";
        } else {
            try {
                // Buscar al usuario por correo electrónico
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                // ✅ Validar contraseña (usando timing-safe comparison)
                $passwordValid = false;
                if ($user && !empty($user['password'])) {
                    $passwordValid = password_verify($password, $user['password']);
                }
                
                if ($user && $passwordValid) {
                    // ✅ Verificar si el usuario está bloqueado por el admin
                    if ($user['is_blocked'] == 1) {
                        $error = "⚠️ Tu cuenta ha sido bloqueada por el administrador. Contacta con soporte.";
                        error_log("Intento de login con cuenta bloqueada: $email desde IP: " . $_SERVER['REMOTE_ADDR']);
                    } else {
                        // ✅ LOGIN EXITOSO
                        
                        // Regenerar ID de sesión para prevenir Session Fixation
                        session_regenerate_id(true);
                        
                        // Limpiar datos de intentos fallidos
                        if (isset($_SESSION[$loginAttemptsKey])) {
                            unset($_SESSION[$loginAttemptsKey]);
                        }
                        
                        // Establecer variables de sesión
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['login_time'] = time();
                        
                        // ✅ Limpiar cualquier sesión de compra/comida residual
                        // ✅ Limpiar cualquier sesión de compra/comida residual
$sessionPrefixes = ['food_', 'purchase_', 'ticket_', 'total_', 'subtotal_', 'tax_', 'payment_'];
foreach ($_SESSION as $key => $value) {
    foreach ($sessionPrefixes as $prefix) {
        if (strpos($key, $prefix) === 0) {
            unset($_SESSION[$key]);
            break;
        }
    }
}
                        
                        // Actualizar último acceso
                        try {
                            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                            $stmt->execute([$user['id']]);
                        } catch (PDOException $e) {
                            error_log("Error actualizando last_login: " . $e->getMessage());
                        }
                        
                        // ✅ Log de acceso exitoso
                        error_log(sprintf(
                            "✅ Login exitoso: user_id=%d, email=%s, role=%s, IP=%s",
                            $user['id'],
                            $user['email'],
                            $user['role'],
                            $_SERVER['REMOTE_ADDR']
                        ));
                        
                        // Redirigir según el rol
                        $redirectUrl = ($user['role'] === 'admin') ? 'admin.php' : 'index.php';
                        
                        // Si hay un parámetro de redirección seguro, usarlo
                        if (isset($_POST['redirect_to']) && !empty($_POST['redirect_to'])) {
                            $redirectTo = $_POST['redirect_to'];
                            // ✅ Validar que la redirección sea a una URL local (prevenir Open Redirect)
                            if (strpos($redirectTo, '/') === 0 && strpos($redirectTo, '//') !== 0) {
                                $redirectUrl = $redirectTo;
                            }
                        }
                        
                        header('Location: ' . $redirectUrl);
                        exit;
                    }
                } else {
                    // ✅ LOGIN FALLIDO
                    
                    // Registrar intento fallido
                    if (!isset($_SESSION[$loginAttemptsKey])) {
                        $_SESSION[$loginAttemptsKey] = [
                            'count' => 0,
                            'first_attempt' => time(),
                            'last_attempt' => time()
                        ];
                    }
                    
                    $_SESSION[$loginAttemptsKey]['count']++;
                    $_SESSION[$loginAttemptsKey]['last_attempt'] = time();
                    
                    $attemptsLeft = $maxAttempts - $_SESSION[$loginAttemptsKey]['count'];
                    
                    // ✅ Log de intento fallido
                    error_log(sprintf(
                        "❌ Login fallido: email=%s, IP=%s, Intento %d/%d",
                        $email,
                        $_SERVER['REMOTE_ADDR'],
                        $_SESSION[$loginAttemptsKey]['count'],
                        $maxAttempts
                    ));
                    
                    // ✅ Mensaje genérico (no revela si el email existe)
                    if ($attemptsLeft > 1) {
                        $error = "El correo electrónico o la contraseña son incorrectos. Te quedan $attemptsLeft intentos.";
                    } elseif ($attemptsLeft === 1) {
                        $error = "El correo electrónico o la contraseña son incorrectos. Te queda 1 intento antes del bloqueo temporal.";
                        $showCaptcha = true;
                    } else {
                        $error = "Demasiados intentos fallidos. Tu acceso ha sido bloqueado temporalmente por $lockoutMinutes minutos.";
                        $isLockedOut = true;
                    }
                }
                
            } catch (PDOException $e) {
                error_log("Error en login: " . $e->getMessage());
                $error = "Ocurrió un error al procesar tu solicitud. Por favor, intenta nuevamente.";
            }
        }
    }
}

// Generar nuevo token CSRF para el formulario
$csrf_token = generateCSRFToken();
$siteConfig = getSiteConfig($pdo);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - <?= htmlspecialchars($siteConfig['site_name'] ?? 'Cinema Pro') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            cursor: pointer;
            transition: color 0.3s ease;
            background: none;
            border: none;
            font-size: 1rem;
            padding: 4px;
        }
        .password-toggle:hover {
            color: #e5e7eb;
        }
        .password-wrapper {
            position: relative;
        }
        .password-wrapper input {
            padding-right: 40px;
        }
        
        /* ✅ Animación de shake para errores */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        .error-shake {
            animation: shake 0.5s ease-in-out;
        }
        
        /* ✅ Indicador de intentos restantes */
        .attempts-warning {
            background: rgba(245, 158, 11, 0.2);
            border: 1px solid rgba(245, 158, 11, 0.4);
            color: #fbbf24;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            margin-top: 8px;
        }
        
        /* ✅ Botón deshabilitado durante lockout */
        .btn-disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
    </style>
</head>
<body class="bg-gray-900 text-white flex items-center justify-center min-h-screen p-4">
    <div class="bg-gray-800 p-8 rounded-lg shadow-xl w-full max-w-sm border border-gray-700">
        <h2 class="text-2xl font-bold mb-6 text-center text-indigo-500">
            <i class="fas fa-film mr-2"></i>Iniciar Sesión
        </h2>
        
        <?php if (isset($_GET['registered'])): ?>
            <div class="bg-green-600/20 border border-green-500/30 text-green-400 p-3 rounded text-sm mb-4 text-center font-semibold">
                <i class="fas fa-check-circle mr-1"></i>
                ¡Registro exitoso! Ya puedes ingresar.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['logout'])): ?>
            <div class="bg-blue-600/20 border border-blue-500/30 text-blue-400 p-3 rounded text-sm mb-4 text-center font-semibold">
                <i class="fas fa-sign-out-alt mr-1"></i>
                Has cerrado sesión correctamente.
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['session_expired'])): ?>
            <div class="bg-yellow-600/20 border border-yellow-500/30 text-yellow-400 p-3 rounded text-sm mb-4 text-center font-semibold">
                <i class="fas fa-clock mr-1"></i>
                Tu sesión ha expirado. Por favor, inicia sesión nuevamente.
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-600/20 border border-red-500/30 text-red-400 p-3 rounded text-sm mb-4 text-center font-semibold error-shake" id="errorBox">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($isLockedOut): ?>
            <div class="bg-red-600/30 border border-red-500/50 text-red-300 p-4 rounded text-sm mb-4 text-center">
                <i class="fas fa-lock mr-2"></i>
                <p class="font-bold mb-1">Cuenta bloqueada temporalmente</p>
                <p>Por seguridad, debes esperar <strong><?= $remainingLockoutMinutes ?> minuto(s)</strong> antes de intentar nuevamente.</p>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" class="space-y-4" id="loginForm" <?= $isLockedOut ? 'style="opacity: 0.5; pointer-events: none;"' : '' ?>>
            <!-- ✅ CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            
            <!-- ✅ Redirección segura (si viene de una página protegida) -->
            <?php if (isset($_GET['redirect'])): ?>
                <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($_GET['redirect']) ?>">
            <?php endif; ?>
            
            <div>
                <label class="block text-sm text-gray-400 mb-1" for="email">
                    <i class="fas fa-envelope mr-1"></i>Correo Electrónico
                </label>
                <input 
                    type="email" 
                    id="email"
                    name="email" 
                    required 
                    autocomplete="email"
                    maxlength="255"
                    class="w-full p-2.5 bg-gray-700 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="tu@email.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                >
            </div>
            
            <div>
                <label class="block text-sm text-gray-400 mb-1" for="password">
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
                        class="w-full p-2.5 bg-gray-700 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="••••••••"
                    >
                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('loginPassword', this)" aria-label="Mostrar contraseña">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <button 
                type="submit" 
                id="submitBtn"
                class="w-full bg-indigo-600 hover:bg-indigo-700 p-2.5 rounded-lg font-bold transition-colors mt-2 flex items-center justify-center gap-2 <?= $isLockedOut ? 'btn-disabled' : '' ?>"
                <?= $isLockedOut ? 'disabled' : '' ?>
            >
                <i class="fas fa-sign-in-alt"></i>
                Entrar
            </button>
        </form>
        
        <?php if ($showCaptcha && !$isLockedOut): ?>
            <div class="attempts-warning mt-4 text-center">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                Último intento antes del bloqueo temporal
            </div>
        <?php endif; ?>
        
        <p class="text-sm text-gray-400 mt-6 text-center">
            ¿No tienes una cuenta? 
            <a href="register.php" class="text-indigo-400 hover:underline font-semibold">
                Regístrate aquí
            </a>
        </p>
        
        <div class="mt-4 pt-4 border-t border-gray-700 text-center">
            <a href="index.php" class="text-xs text-gray-500 hover:text-gray-300">
                <i class="fas fa-arrow-left mr-1"></i>Volver al inicio
            </a>
        </div>
    </div>

    <script>
        // ============================================
        // TOGGLE PASSWORD VISIBILITY
        // ============================================
        function togglePasswordVisibility(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');
            
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
        
        // ============================================
        // ✅ PREVENIR REENVÍO DEL FORMULARIO AL RECARGAR
        // ============================================
        if (window.history && window.history.replaceState) {
            window.addEventListener('pageshow', function(event) {
                if (event.persisted) {
                    window.location.reload();
                }
            });
        }
        
        // ============================================
        // ✅ VALIDACIÓN EN TIEMPO REAL
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('loginPassword');
            const submitBtn = document.getElementById('submitBtn');
            const loginForm = document.getElementById('loginForm');
            
            // Validar email en tiempo real
            emailInput.addEventListener('blur', function() {
                const email = this.value.trim();
                if (email && !email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                    this.classList.add('border-red-500');
                    this.classList.remove('border-gray-600');
                } else {
                    this.classList.remove('border-red-500');
                    this.classList.add('border-gray-600');
                }
            });
            
            // Prevenir envío si el formulario está bloqueado
            loginForm.addEventListener('submit', function(e) {
                if (submitBtn.disabled) {
                    e.preventDefault();
                    return false;
                }
                
                // Mostrar indicador de carga
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verificando...';
                submitBtn.disabled = true;
            });
            
            // Autofocus en el primer campo vacío
            if (!emailInput.value) {
                emailInput.focus();
            } else {
                passwordInput.focus();
            }
        });
        
        // ============================================
        // ✅ REMOVER ANIMACIÓN DE ERROR DESPUÉS DE 2 SEGUNDOS
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