<?php
// ============================================
// HELPERS - CONFIGURACIÓN DEL SITIO
// ============================================

function getSiteConfig($pdo)
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $defaults = [
        'site_name' => 'Cinema Pro',
        'site_logo' => '',
        'footer_logo' => '',
        'site_favicon' => '',
        'timezone' => 'America/Caracas',
        'footer_copyright' => '© ' . date('Y') . ' Cinema. Todos los derechos reservados.',
        'company_rif' => '',
        'currency_symbol' => '$',
        'currency_position' => 'left',
        'thousands_separator' => '.',
        'decimal_separator' => ',',
        'decimal_places' => '2',
        'address' => '',
        'phone' => '',
        'email' => '',
        'instagram' => '',
        'facebook' => '',
        'twitter' => '',
        'telegram' => '',
        'whatsapp' => ''
    ];

    try {
        $stmt = $pdo->query("SELECT key_name, value FROM site_config");
        $rows = $stmt->fetchAll();

        $config = [];

        foreach ($rows as $row) {
            $config[$row['key_name']] = $row['value'];
        }

        foreach ($defaults as $key => $default_value) {
            if (!isset($config[$key]) || $config[$key] === '') {
                $config[$key] = $default_value;
            }
        }

        return $config;
    } catch (PDOException $e) {
        error_log("Error cargando configuración del sitio: " . $e->getMessage());
        return $defaults;
    }
}

// ============================================
// 🕐 ZONAS HORARIAS SOPORTADAS (LATAM, Canadá y EEUU)
// ============================================
function getSupportedTimezones(): array
{
    return [
        'Latinoamérica' => [
            'America/Caracas' => 'Venezuela (UTC-4:00)',
            'America/Bogota' => 'Colombia (UTC-5:00)',
            'America/Lima' => 'Perú (UTC-5:00)',
            'America/Guayaquil' => 'Ecuador (UTC-5:00)',
            'America/Panama' => 'Panamá (UTC-5:00)',
            'America/Costa_Rica' => 'Costa Rica (UTC-6:00)',
            'America/Guatemala' => 'Guatemala (UTC-6:00)',
            'America/San_Salvador' => 'El Salvador (UTC-6:00)',
            'America/Managua' => 'Nicaragua (UTC-6:00)',
            'America/Tegucigalpa' => 'Honduras (UTC-6:00)',
            'America/Mexico_City' => 'México – Ciudad de México (UTC-6:00)',
            'America/Monterrey' => 'México – Monterrey (UTC-6:00)',
            'America/Havana' => 'Cuba (UTC-5:00)',
            'America/Santo_Domingo' => 'República Dominicana (UTC-4:00)',
            'America/Puerto_Rico' => 'Puerto Rico (UTC-4:00)',
            'America/Port-au-Prince' => 'Haití (UTC-5:00)',
            'America/La_Paz' => 'Bolivia (UTC-4:00)',
            'America/Asuncion' => 'Paraguay (UTC-4:00)',
            'America/Montevideo' => 'Uruguay (UTC-3:00)',
            'America/Santiago' => 'Chile (UTC-4:00)',
            'America/Argentina/Buenos_Aires' => 'Argentina (UTC-3:00)',
            'America/Sao_Paulo' => 'Brasil – São Paulo (UTC-3:00)',
            'America/Manaus' => 'Brasil – Manaos (UTC-4:00)',
            'America/Cuiaba' => 'Brasil – Cuiabá (UTC-4:00)',
        ],
        'Canadá' => [
            'America/Toronto' => 'Este – Toronto / Ottawa (UTC-5:00)',
            'America/Montreal' => 'Este – Montreal (UTC-5:00)',
            'America/Halifax' => 'Atlántico – Halifax (UTC-4:00)',
            "America/St_Johns" => "Terranova – St. John's (UTC-3:30)",
            'America/Winnipeg' => 'Central – Winnipeg (UTC-6:00)',
            'America/Regina' => 'Central – Regina (UTC-6:00)',
            'America/Edmonton' => 'Montaña – Edmonton (UTC-7:00)',
            'America/Vancouver' => 'Pacífico – Vancouver (UTC-8:00)',
            'America/Whitehorse' => 'Yukón – Whitehorse (UTC-7:00)',
            'America/Iqaluit' => 'Nunavut – Iqaluit (UTC-5:00)',
        ],
        'Estados Unidos' => [
            'America/New_York' => 'Este – New York (UTC-5:00)',
            'America/Detroit' => 'Este – Detroit (UTC-5:00)',
            'America/Indianapolis' => 'Este – Indianapolis (UTC-5:00)',
            'America/Chicago' => 'Central – Chicago (UTC-6:00)',
            'America/Denver' => 'Montaña – Denver (UTC-7:00)',
            'America/Phoenix' => 'Montaña – Phoenix (UTC-7:00, sin DST)',
            'America/Los_Angeles' => 'Pacífico – Los Ángeles (UTC-8:00)',
            'America/Anchorage' => 'Alaska – Anchorage (UTC-9:00)',
            'America/Juneau' => 'Alaska – Juneau (UTC-9:00)',
            'Pacific/Honolulu' => 'Hawái – Honolulu (UTC-10:00)',
        ],
        'España' => [
            'Atlantic/Canary' => 'Canarias – Las Palmas (UTC±0:00)',
            'Africa/Ceuta' => 'Ceuta y Melilla (UTC+1:00)',
            'Europe/Madrid' => 'Península – Madrid (UTC+1:00)',
        ],
    ];

    // Grupos (regiones) en orden alfabético A→Z y opciones ascendentes dentro de cada uno
    ksort($zones, SORT_STRING | SORT_FLAG_CASE);
    foreach ($zones as $groupKey => $groupZones) {
        asort($groupZones, SORT_STRING | SORT_FLAG_CASE);
        $zones[$groupKey] = $groupZones;
    }

    return $zones;
}

function getTimezoneIdentifiers(): array
{
    $ids = [];
    foreach (getSupportedTimezones() as $group) {
        $ids = array_merge($ids, array_keys($group));
    }
    return $ids;
}

function getSiteTimezone(array $siteConfig): string
{
    $tz = $siteConfig['timezone'] ?? 'America/Caracas';
    if (!in_array($tz, DateTimeZone::listIdentifiers(), true)) {
        $tz = 'America/Caracas';
    }
    return $tz;
}

function getFaviconHref($siteConfig)
{
    $favicon = trim($siteConfig['site_favicon'] ?? ($siteConfig['favicon'] ?? ''));

    if ($favicon === '') {
        return 'admin/img/favicon.png';
    }

    $urlPath = parse_url($favicon, PHP_URL_PATH);
    $localPath = ltrim($urlPath ?: $favicon, '/');

    if ($localPath !== '' && is_file($localPath)) {
        return $favicon . '?v=' . filemtime($localPath);
    }

    if (filter_var($favicon, FILTER_VALIDATE_URL)) {
        return $favicon;
    }

    return is_file('admin/img/favicon.png') ? 'admin/img/favicon.png' : $favicon;
}

// ============================================
// IMAGEN PLACEHOLDER (SVG data URI, sin dependencias externas)
// ============================================
function getPlaceholderImage(int $width = 300, int $height = 450, string $label = '🎬')
{
    $fontSize = max(24, intval($width * 0.2));

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">'
        . '<rect width="100%" height="100%" fill="#1f2937"/>'
        . '<text x="50%" y="50%" font-size="' . $fontSize . '" text-anchor="middle" dominant-baseline="central" fill="#ffffff">'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
        . '</text></svg>';

    return 'data:image/svg+xml;charset=utf-8,' . rawurlencode($svg);
}