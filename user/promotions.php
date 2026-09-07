<?php
require_once __DIR__ . '/../config.php';

$siteConfig = getSiteConfig($pdo);
$pageTitle = "Promociones - " . ($siteConfig['site_name'] ?? 'Cinema Pro');
$backUrl = '../index.php';

// Si el visitante está autenticado, mostrar con el sidebar del módulo de usuario
$promoLoggedIn = isset($_SESSION['user_id']);
if ($promoLoggedIn) {
    $activePage = 'promotions';
    try {
        $stmtU = $pdo->prepare("SELECT id, name, email, avatar, delete_requested_at FROM users WHERE id = ?");
        $stmtU->execute([$_SESSION['user_id']]);
        $promoUser = $stmtU->fetch();
        if (!$promoUser || !empty($promoUser['delete_requested_at'])) {
            unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email']);
            header('Location: ../login.php');
            exit;
        }
        $userAuth = $promoUser;
    } catch (Throwable $e) {
    }
}

// Promociones disponibles (código => etiqueta)
$promoLabels = [
    'lunes_mitad' => ['Lunes a mitad de precio', 'monday'],
    'preventa' => ['Preventa', 'presale'],
];

$stmt = $pdo->prepare("
    SELECT s.id, s.show_date, s.show_time, s.promotions, s.format,
           m.id AS movie_id, m.title, m.poster_url, r.name AS room_name
    FROM showtimes s
    JOIN movies m ON s.movie_id = m.id AND m.is_active = 1
    JOIN rooms r ON s.room_id = r.id
    WHERE s.promotions IS NOT NULL AND s.promotions <> ''
      AND s.show_date >= CURDATE()
    ORDER BY s.show_date ASC, s.show_time ASC
");
$stmt->execute();
$promoShowtimes = $stmt->fetchAll();

$getPromos = function ($raw) {
    return array_filter(array_map('trim', explode(',', $raw ?? '')));
};

$anyPromoActive = !empty($promoShowtimes);

if ($promoLoggedIn) {
    require_once 'includes/header.php';
} else {
    require_once '../header.php';
}
if (!$promoLoggedIn) {
    // Cargar los estilos compartidos del módulo de usuario (la vista pública usa el header del sitio)
    echo '<link rel="stylesheet" href="assets/css/user.css">'
        . '<link rel="stylesheet" href="assets/css/promotions.css">';
}
?>
<div class="account-wrapper">
    <h1 class="account-title"><i class="fas fa-tags text-indigo-600 mr-2"></i>Promociones</h1>
    <p class="account-subtitle mb-6">Aprovecha nuestras ofertas especiales en funciones seleccionadas.</p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="promo-hero monday">
            <h2><i class="fas fa-percent mr-2"></i>Lunes a mitad de precio</h2>
            <p>Todos los lunes, en las funciones marcadas, tus boletos cuestan la mitad del precio regular. La promoción aplica a cualquier tipo de entrada.</p>
        </div>
        <div class="promo-hero presale">
            <h2><i class="fas fa-rocket mr-2"></i>Preventa</h2>
            <p>Consigue tus boletos antes que nadie en funciones especialmente seleccionadas, con precios preferenciales de lanzamiento.</p>
        </div>
    </div>

    <div class="promo-card">
        <h2 class="text-lg font-extrabold text-gray-900 mb-1">Funciones con promoción</h2>
        <p class="account-subtitle mb-4">Estas son las próximas funciones con beneficios disponibles.</p>

        <?php if (!$anyPromoActive): ?>
            <div class="empty-state">
                <i class="fas fa-tags"></i>
                <p class="font-bold text-gray-800">Próximamente</p>
                <p class="text-sm">No hay funciones con promociones vigentes por ahora. ¡Vuelve pronto!</p>
            </div>
        <?php else: ?>
            <?php foreach ($promoShowtimes as $st): ?>
                <?php
                $promos = array_intersect_key($promoLabels, array_flip($getPromos($st['promotions'])));
                $unknown = array_diff($getPromos($st['promotions']), array_keys($promoLabels));
                $posterOk = !empty($st['poster_url']);
                ?>
                <a href="../movie_detail.php?id=<?= (int)$st['movie_id'] ?>" class="showtime-row">
                    <?php if ($posterOk): ?>
                        <img src="<?= htmlspecialchars($st['poster_url']) ?>" alt="<?= htmlspecialchars($st['title']) ?>"
                             class="showtime-poster" data-error-fallback>
                        <span class="showtime-poster" style="display:none;">🎬</span>
                    <?php else: ?>
                        <span class="showtime-poster">🎬</span>
                    <?php endif; ?>

                    <div class="flex-1 min-w-0">
                        <div class="showtime-title"><?= htmlspecialchars($st['title']) ?>
                            <span class="format-badge"><?= htmlspecialchars($st['format'] ?? '2D') ?></span>
                        </div>
                        <div class="showtime-meta">
                            <i class="fas fa-door-open"></i><?= htmlspecialchars($st['room_name']) ?>
                            <span class="mx-1.5 text-gray-300">·</span>
                            <i class="fas fa-calendar-day"></i><?= htmlspecialchars(formatDateShort($st['show_date'])) ?>
                            <span class="mx-1.5 text-gray-300">·</span>
                            <i class="fas fa-clock"></i><?= htmlspecialchars(formatTimeVenezuela($st['show_time'])) ?>
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5 items-end shrink-0">
                        <?php foreach ($promos as $code => $info): ?>
                            <span class="promo-tag <?= $info[1] ?>"><span class="promo-dot"></span><?= htmlspecialchars($info[0]) ?></span>
                        <?php endforeach; ?>
                        <?php foreach ($unknown as $code): ?>
                            <span class="promo-tag generic"><span class="promo-dot"></span><?= htmlspecialchars(ucfirst($code)) ?></span>
                        <?php endforeach; ?>
                    </div>

                    <i class="fas fa-chevron-right text-gray-400 shrink-0"></i>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <p class="text-center text-sm text-gray-500 mt-6">
        <a href="../index.php" class="text-indigo-600 hover:underline font-semibold">
            <i class="fas fa-arrow-left mr-1"></i>Volver al inicio
        </a>
    </p>
</div>

<?php if ($promoLoggedIn) { require_once 'includes/footer.php'; } else { require_once '../footer.php'; } ?>