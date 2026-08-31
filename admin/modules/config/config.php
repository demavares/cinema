<?php
// ============================================
// MÓDULO: CONFIGURACIÓN — ROUTER DE SECCIONES
// ============================================
$config_section = in_array($subAction, ['currency', 'contact']) ? $subAction : 'general';

$taxConfig = $pdo->query("SELECT tax_rate FROM tax_config WHERE is_active = 1 LIMIT 1")->fetch();
$taxRate = $taxConfig ? floatval($taxConfig['tax_rate']) : 16;

function adminAssetHref($path) {
    if (!is_string($path) || $path === '') {
        return '';
    }
    if (preg_match('#^(https?:)?//#', $path) || $path[0] === '/') {
        return $path;
    }
    return '../' . $path;
}

function adminAssetExists($path) {
    if (!is_string($path) || $path === '') {
        return false;
    }
    if (preg_match('#^(https?:)?//#', $path)) {
        return true;
    }
    $full = $path[0] === '/' ? $path : dirname(__DIR__, 3) . '/' . $path;
    return is_file($full);
}

if ($config_section === 'currency') {
    require __DIR__ . '/config_currency.php';
} elseif ($config_section === 'contact') {
    require __DIR__ . '/config_contact.php';
} else {
    require __DIR__ . '/config_general.php';
}