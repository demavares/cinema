<?php
require_once 'config.php';

$password = 'admin123';
$hash = password_hash($password, PASSWORD_BCRYPT);

try {
    // Verificar si el admin existe
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute(['admin@cinema.com']);
    $user = $stmt->fetch();
    
    if ($user) {
        // Actualizar contraseña
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->execute([$hash, 'admin@cinema.com']);
        echo "✅ Contraseña de administrador actualizada!\n";
    } else {
        // Crear nuevo admin
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
        $stmt->execute(['Administrador', 'admin@cinema.com', $hash]);
        echo "✅ Administrador creado exitosamente!\n";
    }
    
    echo "\n📧 Email: admin@cinema.com\n";
    echo "🔑 Contraseña: admin123\n";
    echo "\n🔐 Hash generado: " . $hash . "\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Verifica que la base de datos esté configurada correctamente.\n";
}
?>