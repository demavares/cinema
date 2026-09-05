<?php
// ============================================
// MÓDULO DE USUARIO — GUARD DE AUTENTICACIÓN
// ============================================
require_once __DIR__ . '/../config.php';

checkSessionExpired();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userAuth = $stmt->fetch();

if (!$userAuth) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

if ((int)$userAuth['is_blocked'] === 1) {
    error_log("🚫 Usuario bloqueado intentó acceder al módulo user: user_id " . $_SESSION['user_id']);
    header('Location: ../index.php?error=' . urlencode('Cuenta bloqueada'));
    exit;
}