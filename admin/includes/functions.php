<?php
/**
 * Funciones específicas del panel de administración
 */

function getAdminStats($pdo)
{
    $stats = [];

    // Total de películas
    $stmt = $pdo->query("SELECT COUNT(*) as total, SUM(is_active) as active FROM movies");
    $movies = $stmt->fetch();
    $stats['total_movies'] = $movies['total'] ?? 0;
    $stats['active_movies'] = $movies['active'] ?? 0;

    // Total de funciones
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM showtimes WHERE is_active = 1");
    $stats['total_showtimes'] = $stmt->fetchColumn() ?? 0;

    // Total de usuarios
    $stmt = $pdo->query("SELECT COUNT(*) as total, SUM(is_blocked) as blocked FROM users");
    $users = $stmt->fetch();
    $stats['total_users'] = $users['total'] ?? 0;
    $stats['blocked_users'] = $users['blocked'] ?? 0;

    // Boletos vendidos e ingresos
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_tickets,
            COALESCE(SUM(price_paid), 0) as total_revenue
        FROM tickets 
        WHERE status = 'confirmed'
    ");
    $tickets = $stmt->fetch();
    $stats['total_tickets_sold'] = $tickets['total_tickets'] ?? 0;
    $stats['total_revenue'] = $tickets['total_revenue'] ?? 0;

    return $stats;
}

function getRecentActivity($pdo, $limit = 10)
{
    $activities = [];

    // Compras recientes
    $stmt = $pdo->prepare("
        SELECT p.*, u.name as user_name 
        FROM purchases p
        JOIN users u ON p.user_id = u.id
        WHERE p.status = 'completed'
        ORDER BY p.purchase_date DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $purchases = $stmt->fetchAll();

    foreach ($purchases as $p) {
        $activities[] = [
            'text' => "{$p['user_name']} compró {$p['total_tickets']} boletos",
            'created_at' => $p['purchase_date'],
            'icon' => '🎫'
        ];
    }

    // Nuevos usuarios
    $stmt = $pdo->prepare("
        SELECT * FROM users
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $users = $stmt->fetchAll();

    foreach ($users as $u) {
        $activities[] = [
            'text' => "Nuevo usuario registrado: {$u['name']}",
            'created_at' => $u['created_at'],
            'icon' => '👤'
        ];
    }

    // Ordenar por fecha y limitar
    usort($activities, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    return array_slice($activities, 0, $limit);
}

function getUpcomingShowtimes($pdo, $limit = 5)
{
    $stmt = $pdo->prepare("
        SELECT s.*, m.title, r.name as room_name
        FROM showtimes s
        JOIN movies m ON s.movie_id = m.id
        JOIN rooms r ON s.room_id = r.id
        WHERE s.is_active = 1 
            AND CONCAT(s.show_date, ' ', s.show_time) > NOW()
        ORDER BY s.show_date ASC, s.show_time ASC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function timeAgo($datetime)
{
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) return 'hace ' . $diff . ' segundos';
    if ($diff < 3600) return 'hace ' . floor($diff / 60) . ' minutos';
    if ($diff < 86400) return 'hace ' . floor($diff / 3600) . ' horas';
    if ($diff < 604800) return 'hace ' . floor($diff / 86400) . ' días';
    return date('d/m/Y', $time);
}