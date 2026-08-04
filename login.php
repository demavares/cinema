<?php
require_once 'config.php';

// Si ya tiene una sesión activa, redirigir según su rol
if (isset($_SESSION['user_id'])) {
    // Actualizar último acceso
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    header('Location: ' . ($_SESSION['user_role'] === 'admin' ? 'admin.php' : 'index.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Buscar al usuario por correo electrónico
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Validar contraseña y verificar si está bloqueado
    if ($user && password_verify($password, $user['password'])) {
        // Verificar si el usuario está bloqueado
        if ($user['is_blocked'] == 1) {
            $error = "⚠️ Tu cuenta ha sido bloqueada por el administrador. Contacta con soporte.";
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            
            // Actualizar último acceso
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            header('Location: ' . ($user['role'] === 'admin' ? 'admin.php' : 'index.php'));
            exit;
        }
    } else {
        $error = "El correo electrónico o la contraseña son incorrectos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Cinema Pro</title>
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
    </style>
</head>
<body class="bg-gray-900 text-white flex items-center justify-center min-h-screen p-4">
    <div class="bg-gray-800 p-8 rounded-lg shadow-xl w-full max-w-sm border border-gray-700">
        <h2 class="text-2xl font-bold mb-6 text-center text-indigo-500">Iniciar Sesión</h2>
        
        <?php if (isset($_GET['registered'])): ?>
            <p class="bg-green-600/20 text-green-400 p-2 rounded text-sm mb-4 text-center font-semibold">
                ¡Registro exitoso! Ya puedes ingresar.
            </p>
        <?php endif; ?>

        <?php if($error): ?>
            <p class="bg-red-600/20 text-red-400 p-2 rounded text-sm mb-4 text-center font-semibold">
                <?= htmlspecialchars($error) ?>
            </p>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-4">
            <div>
                <label class="block text-sm text-gray-400 mb-1">Correo Electrónico</label>
                <input type="email" name="email" required class="w-full p-2.5 bg-gray-700 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm text-gray-400 mb-1">Contraseña</label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="loginPassword" required class="w-full p-2.5 bg-gray-700 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('loginPassword', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 p-2.5 rounded-lg font-bold transition-colors mt-2">
                Entrar
            </button>
        </form>
        
        <p class="text-sm text-gray-400 mt-6 text-center">
            ¿No tienes una cuenta? <a href="register.php" class="text-indigo-400 hover:underline">Regístrate aquí</a>
        </p>
    </div>

    <script>
        function togglePasswordVisibility(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');
            
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