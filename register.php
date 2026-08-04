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
        $error = "Error de seguridad: Token inválido.";
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

        if (empty($name) || empty($email) || empty($password) || empty($confirm_password) || empty($cedula_number) || empty($phone_number) || empty($birth_date)) {
            $error = "Todos los campos son obligatorios.";
        } elseif ($password !== $confirm_password) {
            $error = "Las contraseñas no coinciden.";
        } elseif (strlen($password) < 8) {
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
            // 1. Verificar si la cédula ya se encuentra registrada (ÚNICA restricción de duplicados)
            $stmt_check_cedula = $pdo->prepare("SELECT id FROM users WHERE cedula_type = ? AND cedula_number = ?");
            $stmt_check_cedula->execute([$cedula_type, $cedula_number]);
            if ($stmt_check_cedula->rowCount() > 0) {
                $error = "El usuario con numero de cedula " . $cedula_type .'-'. $cedula_number . " ya se encuentra registrado.";
            }

            // Si pasa las validaciones, procede al registro
            if (empty($error)) {
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                try {
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, cedula_type, cedula_number, phone_prefix, phone_number, birth_date, password, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'user')");
                    $stmt->execute([$name, $email, $cedula_type, $cedula_number, $phone_prefix, $phone_number, $birth_date, $passwordHash]);

                    $_SESSION['user_id'] = $pdo->lastInsertId();
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_role'] = 'user';

                    header('Location: index.php?msg=' . urlencode("¡Registro exitoso! Bienvenido."));
                    exit;
                } catch (PDOException $e) {
                    if ($e->errorInfo[1] == 1062) {
                        $error = "El usuario con numero de cedula " . $cedula_type . $cedula_number . " ya se encuentra registrado.";
                    } else {
                        $error = "Error al registrar el usuario: " . $e->getMessage();
                    }
                }
            }
        }
    }
}

$csrf_token = generateCSRFToken();
$siteConfig = getSiteConfig($pdo);
$pageTitle = "Registro de Usuario - " . ($siteConfig['site_name'] ?? 'Cinema Pro');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .password-wrapper { position: relative; }
        .password-toggle {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            color: #6b7280; cursor: pointer; background: none; border: none;
        }
        .password-toggle:hover { color: #e5e7eb; }
        .form-group-inline { display: flex; gap: 8px; align-items: center; }
        .form-group-inline select { flex: 0 0 80px; }
        .form-group-inline input { flex: 1; }
        .phone-group-inline { display: flex; gap: 8px; align-items: center; }
        .phone-group-inline select { flex: 0 0 90px; }
        .phone-group-inline input { flex: 1; }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-gray-800 rounded-lg shadow-xl p-8 border border-gray-700 my-8">
        <div class="text-center mb-6">
            <?php if(!empty($siteConfig['site_logo']) && file_exists($siteConfig['site_logo'])): ?>
                <img src="<?= htmlspecialchars($siteConfig['site_logo']) ?>" alt="Logo" class="h-16 mx-auto mb-3 object-contain">
            <?php endif; ?>
            <h1 class="text-2xl font-bold text-indigo-400">Crear Cuenta</h1>
            <p class="text-sm text-gray-400 mt-1">Registrate para comprar tus entradas</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-600/20 text-red-400 p-3 rounded-lg mb-6 text-sm border border-red-500/30">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

            <div>
                <label class="block text-sm text-gray-400 mb-1">Nombres Y Appelidos *</label>
                <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                       class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-sm text-gray-400 mb-1">Cédula de Identidad *</label>
                <div class="form-group-inline">
                    <select name="cedula_type" class="bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="V" <?= (($_POST['cedula_type'] ?? '') == 'V') ? 'selected' : '' ?>>V</option>
                        <option value="E" <?= (($_POST['cedula_type'] ?? '') == 'E') ? 'selected' : '' ?>>E</option>
                        <option value="P" <?= (($_POST['cedula_type'] ?? '') == 'P') ? 'selected' : '' ?>>P</option>
                    </select>
                    <input type="text" name="cedula_number" required value="<?= htmlspecialchars($_POST['cedula_number'] ?? '') ?>" 
                           placeholder="Ej: 1451113" pattern="[0-9]{7,}" minlength="7" inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                           title="Debe ingresar al menos 7 números"
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>

            <div>
                <label class="block text-sm text-gray-400 mb-1">Teléfono Móvil *</label>
                <div class="phone-group-inline">
                    <select name="phone_prefix" class="bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="412" <?= (($_POST['phone_prefix'] ?? '') == '412') ? 'selected' : '' ?>>0412</option>
                        <option value="414" <?= (($_POST['phone_prefix'] ?? '') == '414') ? 'selected' : '' ?>>0414</option>
                        <option value="424" <?= (($_POST['phone_prefix'] ?? '') == '424') ? 'selected' : '' ?>>0424</option>
                        <option value="426" <?= (($_POST['phone_prefix'] ?? '') == '426') ? 'selected' : '' ?>>0426</option>
                        <option value="4016" <?= (($_POST['phone_prefix'] ?? '') == '4016') ? 'selected' : '' ?>>04016</option>
                    </select>
                    <input type="text" name="phone_number" required value="<?= htmlspecialchars($_POST['phone_number'] ?? '') ?>" 
                           placeholder="Ej: 1234567" pattern="[0-9]{7,}" minlength="7" inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                           title="Debe ingresar al menos 7 números"
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>

            <div>
                <label class="block text-sm text-gray-400 mb-1">Fecha de Nacimiento *</label>
                <input type="date" name="birth_date" required value="<?= htmlspecialchars($_POST['birth_date'] ?? '') ?>"
                       class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-sm text-gray-400 mb-1">Correo Electrónico *</label>
                <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-sm text-gray-400 mb-1">Contraseña *</label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="regPassword" required placeholder="Mínimo 8 caracteres"
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 pr-10">
                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('regPassword', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <div>
                <label class="block text-sm text-gray-400 mb-1">Confirmar Contraseña *</label>
                <div class="password-wrapper">
                    <input type="password" name="confirm_password" id="regConfirmPassword" required placeholder="Repite tu contraseña"
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 pr-10">
                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('regConfirmPassword', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 p-3 rounded-lg font-bold transition-colors shadow-md mt-2">
                Registrarse
            </button>
        </form>

        <div class="mt-6 text-center text-sm text-gray-400 border-t border-gray-700 pt-4">
            ¿Ya tienes una cuenta? 
            <a href="login.php" class="text-indigo-400 hover:underline font-semibold">Inicia Sesión</a>
        </div>
    </div>

    <script>
        function togglePasswordVisibility(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>