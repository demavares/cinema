<?php
// ============================================
// HELPERS - FORMATO (MONEDA Y FECHAS)
// ============================================

// ============================================
// FORMATEAR MONEDA
// ============================================
function formatCurrency($amount, $config = null)
{
    if ($config === null) {
        global $pdo;
        $config = getSiteConfig($pdo);
    }

    $symbol = $config['currency_symbol'] ?? '$';
    $position = $config['currency_position'] ?? 'left';
    $thousands = $config['thousands_separator'] ?? '.';
    $decimal = $config['decimal_separator'] ?? ',';
    $decimals = intval($config['decimal_places'] ?? 2);

    $formatted = number_format($amount, $decimals, $decimal, $thousands);

    return $position === 'right' ? $formatted . ' ' . $symbol : $symbol . $formatted;
}

// ============================================
// FECHAS
// ============================================
function getCurrentDate()
{
    return date('Y-m-d');
}

function getCurrentDateTime()
{
    return date('Y-m-d H:i:s');
}

function formatTimeVenezuela($time)
{
    if (empty($time)) return '';
    return date('h:i A', strtotime($time));
}

function formatDateShort($date)
{
    if (empty($date)) return '';
    return date('d/m/Y', strtotime($date));
}

function formatDateVenezuela($date)
{
    if (empty($date)) return '';

    $months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    $days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

    $timestamp = strtotime($date);

    $dayName = $days[date('w', $timestamp)];
    $day = date('d', $timestamp);
    $month = $months[date('n', $timestamp) - 1];
    $year = date('Y', $timestamp);

    return "$dayName, $day de $month de $year";
}

function getDateInSpanish($date)
{
    if (empty($date)) return '';

    $timestamp = strtotime($date);

    $days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    $months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    $dayName = $days[date('w', $timestamp)];
    $day = date('d', $timestamp);
    $month = $months[date('n', $timestamp) - 1];
    $year = date('Y', $timestamp);

    return "$dayName, $day de $month de $year";
}

function formatDuration($minutes)
{
    if ($minutes <= 0) return 'No disponible';

    $hours = floor($minutes / 60);
    $mins = $minutes % 60;

    if ($hours > 0 && $mins > 0) {
        return $hours . 'h ' . $mins . 'min';
    } elseif ($hours > 0) {
        return $hours . 'h';
    } else {
        return $mins . 'min';
    }
}

function isDatePast($date)
{
    return strtotime($date) < strtotime(date('Y-m-d'));
}

function formatDateDayMonth($date)
{
    $timestamp = strtotime($date);

    $months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    $days = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

    return [
        'day' => $days[date('w', $timestamp)],
        'number' => date('d', $timestamp),
        'month' => $months[date('n', $timestamp) - 1]
    ];
}