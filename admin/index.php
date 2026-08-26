<?php
require_once '../config.php';
require_once 'includes/functions.php';

// Verificar autenticación y rol
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Determinar qué tab mostrar
$activeTab = $_GET['tab'] ?? 'dashboard';
$subAction = $_GET['action'] ?? 'list';
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';
$csrf_token = generateCSRFToken();

// Obtener estadísticas para el dashboard
$stats = getAdminStats($pdo);

// Obtener configuración del sitio
$siteConfig = getSiteConfig($pdo);

$pageTitle = "Panel de Control - " . ($siteConfig['site_name'] ?? 'Cinema Pro');

// Incluir header
require_once 'includes/header.php';
?>

<!-- Contenido principal -->
<div class="admin-content">
    <?php if ($msg): ?>
        <div class="admin-alert admin-alert-success" id="adminAlert">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="admin-alert admin-alert-error" id="adminAlert">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($activeTab === 'dashboard'): ?>
        <!-- ============================================ -->
        <!-- DASHBOARD                                     -->
        <!-- ============================================ -->
        <div class="admin-content-header">
            <h1 class="admin-content-title">📊 Dashboard</h1>
            <p class="admin-content-subtitle">Resumen general de tu sistema de cine</p>
        </div>

        <!-- Tarjetas de estadísticas (2 columnas) -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card-icon bg-indigo-100 text-indigo-600">
                    <i class="fas fa-film"></i>
                </div>
                <div class="stat-card-info">
                    <span class="stat-card-value"><?= $stats['total_movies'] ?></span>
                    <span class="stat-card-label">Películas</span>
                </div>
                <div class="stat-card-change positive">
                    <i class="fas fa-arrow-up"></i> <?= $stats['active_movies'] ?> activas
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-icon bg-green-100 text-green-600">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-card-info">
                    <span class="stat-card-value"><?= $stats['total_showtimes'] ?></span>
                    <span class="stat-card-label">Funciones</span>
                </div>
                <div class="stat-card-change positive">
                    <i class="fas fa-calendar"></i> Próximas funciones
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-icon bg-blue-100 text-blue-600">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-card-info">
                    <span class="stat-card-value"><?= $stats['total_users'] ?></span>
                    <span class="stat-card-label">Usuarios</span>
                </div>
                <div class="stat-card-change <?= $stats['blocked_users'] > 0 ? 'negative' : 'positive' ?>">
                    <?php if ($stats['blocked_users'] > 0): ?>
                        <i class="fas fa-exclamation-triangle"></i> <?= $stats['blocked_users'] ?> bloqueados
                    <?php else: ?>
                        <i class="fas fa-check-circle"></i> Todos activos
                    <?php endif; ?>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-icon bg-yellow-100 text-yellow-600">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <div class="stat-card-info">
                    <span class="stat-card-value"><?= $stats['total_tickets_sold'] ?></span>
                    <span class="stat-card-label">Boletos vendidos</span>
                </div>
                <div class="stat-card-change positive">
                    <i class="fas fa-money-bill-wave"></i> <?= formatCurrency($stats['total_revenue'], $siteConfig) ?>
                </div>
            </div>
        </div>

        <!-- Acciones Rápidas (debajo de los cards) -->
        <div class="admin-card quick-actions-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">⚡ Acciones Rápidas</h3>
            </div>
            <div class="admin-card-body">
                <div class="quick-actions">
                    <a href="index.php?tab=movies&action=register" class="quick-action-btn">
                        <i class="fas fa-plus-circle"></i> Registrar Película
                    </a>
                    <a href="index.php?tab=showtimes" class="quick-action-btn">
                        <i class="fas fa-plus-circle"></i> Agregar Horario
                    </a>
                    <a href="index.php?tab=rooms" class="quick-action-btn">
                        <i class="fas fa-plus-circle"></i> Crear Sala
                    </a>
                    <a href="index.php?tab=food" class="quick-action-btn">
                        <i class="fas fa-plus-circle"></i> Agregar Producto
                    </a>
                    <a href="index.php?tab=users" class="quick-action-btn">
                        <i class="fas fa-user-plus"></i> Registrar Usuario
                    </a>
                </div>
            </div>
        </div>

        <!-- Actividad reciente y gráficos -->
        <div class="admin-grid-2 mt-6">
            <!-- Actividad reciente -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3 class="admin-card-title">🕐 Actividad Reciente</h3>
                    <a href="index.php?tab=history" class="admin-card-link">Ver todo</a>
                </div>
                <div class="admin-card-body">
                    <?php $recentActivity = getRecentActivity($pdo); ?>
                    <?php if (empty($recentActivity)): ?>
                        <p class="text-gray-500 text-sm text-center py-4">No hay actividad reciente</p>
                    <?php else: ?>
                        <ul class="activity-list">
                            <?php foreach ($recentActivity as $activity): ?>
                                <li class="activity-item">
                                    <span class="activity-icon"><?= $activity['icon'] ?? '📌' ?></span>
                                    <div class="activity-content">
                                        <p class="activity-text"><?= htmlspecialchars($activity['text']) ?></p>
                                        <span class="activity-time"><?= timeAgo($activity['created_at']) ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Próximas funciones -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3 class="admin-card-title">🎬 Próximas Funciones</h3>
                    <a href="index.php?tab=showtimes" class="admin-card-link">Gestionar</a>
                </div>
                <div class="admin-card-body">
                    <?php $upcomingShowtimes = getUpcomingShowtimes($pdo, 5); ?>
                    <?php if (empty($upcomingShowtimes)): ?>
                        <p class="text-gray-500 text-sm text-center py-4">No hay funciones próximas</p>
                    <?php else: ?>
                        <ul class="showtime-list">
                            <?php foreach ($upcomingShowtimes as $st): ?>
                                <li class="showtime-item">
                                    <div class="showtime-movie">
                                        <span class="showtime-title"><?= htmlspecialchars($st['title']) ?></span>
                                        <span class="showtime-room"><?= htmlspecialchars($st['room_name']) ?></span>
                                    </div>
                                    <div class="showtime-datetime">
                                        <span class="showtime-date"><?= formatDateShort($st['show_date']) ?></span>
                                        <span class="showtime-time"><?= formatTimeVenezuela($st['show_time']) ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php elseif ($activeTab === 'movies'): ?>
        <!-- ============================================ -->
        <!-- TAB: PELÍCULAS                               -->
        <!-- ============================================ -->
        <?php if ($subAction === 'register'): ?>
            <?php require_once 'modules/movie/movie_register.php'; ?>
        <?php else: ?>
            <?php require_once 'modules/movie/movie.php'; ?>
        <?php endif; ?>

    <?php elseif ($activeTab === 'showtimes'): ?>
        <!-- ============================================ -->
        <!-- TAB: HORARIOS (Pendiente de implementar)     -->
        <!-- ============================================ -->
        <div class="admin-content-header">
            <h1 class="admin-content-title">🕐 Gestión de Horarios</h1>
            <p class="admin-content-subtitle">Módulo en construcción</p>
        </div>

    <?php elseif ($activeTab === 'rooms'): ?>
        <!-- ============================================ -->
        <!-- TAB: SALAS (Pendiente de implementar)        -->
        <!-- ============================================ -->
        <div class="admin-content-header">
            <h1 class="admin-content-title">🏠 Gestión de Salas</h1>
            <p class="admin-content-subtitle">Módulo en construcción</p>
        </div>

    <?php elseif ($activeTab === 'users'): ?>
        <!-- ============================================ -->
        <!-- TAB: USUARIOS (Pendiente de implementar)     -->
        <!-- ============================================ -->
        <div class="admin-content-header">
            <h1 class="admin-content-title">👥 Gestión de Usuarios</h1>
            <p class="admin-content-subtitle">Módulo en construcción</p>
        </div>

    <?php elseif ($activeTab === 'food'): ?>
        <!-- ============================================ -->
        <!-- TAB: COMIDA (Pendiente de implementar)       -->
        <!-- ============================================ -->
        <div class="admin-content-header">
            <h1 class="admin-content-title">🍿 Gestión de Comida</h1>
            <p class="admin-content-subtitle">Módulo en construcción</p>
        </div>

    <?php elseif ($activeTab === 'history'): ?>
        <!-- ============================================ -->
        <!-- TAB: HISTORIAL (Pendiente de implementar)    -->
        <!-- ============================================ -->
        <div class="admin-content-header">
            <h1 class="admin-content-title">📊 Historial de Funciones</h1>
            <p class="admin-content-subtitle">Módulo en construcción</p>
        </div>

    <?php elseif ($activeTab === 'config'): ?>
        <!-- ============================================ -->
        <!-- TAB: CONFIGURACIÓN (Pendiente de implementar)-->
        <!-- ============================================ -->
        <div class="admin-content-header">
            <h1 class="admin-content-title">⚙️ Configuración del Sitio</h1>
            <p class="admin-content-subtitle">Módulo en construcción</p>
        </div>

    <?php endif; ?>
</div>

<!-- Script con nonce para CSP -->
<script nonce="<?= $cspNonce ?>">
// Auto-cerrar mensajes después de 5 segundos y limpiar URL
(function() {
    const alert = document.getElementById('adminAlert');
    if (alert) {
        setTimeout(function() {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s ease';
            setTimeout(function() {
                alert.style.display = 'none';
                try {
                    const url = new URL(window.location.href);
                    url.searchParams.delete('msg');
                    url.searchParams.delete('error');
                    window.history.replaceState({}, document.title, url.toString());
                } catch (e) {}
            }, 500);
        }, 5000);
    }
})();
</script>

<?php require_once 'includes/footer.php'; ?>