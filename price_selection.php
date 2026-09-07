<?php
require_once 'config.php';
// ✅ Regenerar id de sesión para un nuevo intento limpio
if (!isset($_SESSION['last_activity'])) {
    session_regenerate_id(true);
    $_SESSION['last_activity'] = time();
}
// ✅ Verificar si viene con expired (redirigir limpio)
if (isset($_GET['expired']) && $_GET['expired'] === '1') {
    header('Location: index.php?expired=1');
    exit;
}
// ✅ Verificar si viene con session_expired
if (isset($_GET['session_expired'])) {
    $_SESSION = array();
    session_destroy();
    session_start();
    header('Location: index.php?expired=1');
    exit;
}
checkSessionExpired();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$showtimeId = isset($_GET['showtime_id']) ? intval($_GET['showtime_id']) : 0;
if ($showtimeId <= 0) {
    header('Location: index.php');
    exit;
}
// ✅ LIMPIAR COMPLETAMENTE CUALQUIER SESIÓN RESIDUAL
clearPurchaseSession($showtimeId);
unset($_SESSION['food_valid_' . $showtimeId]);
unset($_SESSION['food_seats_' . $showtimeId]);
unset($_SESSION['food_timeout_' . $showtimeId]);
unset($_SESSION['food_order_' . $showtimeId]);
unset($_SESSION['base_subtotal_' . $showtimeId]);
// ✅ REGENERAR TOKEN NUEVO
$purchaseToken = generatePurchaseTokenWithTimeout($showtimeId, 900);
$_SESSION['purchase_token_' . $showtimeId] = $purchaseToken;
// Obtener datos del showtime
$stmt = $pdo->prepare("
SELECT s.*, m.title, m.poster_url, m.duration, r.name as room_name
FROM showtimes s
JOIN movies m ON s.movie_id = m.id
JOIN rooms r ON s.room_id = r.id
WHERE s.id = ? AND s.is_active = 1
");
$stmt->execute([$showtimeId]);
$showtime = $stmt->fetch();
if (!$showtime) {
    header('Location: index.php');
    exit;
}
// Obtener precios
$priceAdult = floatval($showtime['price_adult'] ?? $showtime['price'] ?? 0);
$priceChild = floatval($showtime['price_child'] ?? 0);
$priceSenior = floatval($showtime['price_senior'] ?? 0);
if ($priceAdult == 0) {
    $priceAdult = 50.00;
}
if ($priceChild == 0 && $priceAdult > 0) {
    $priceChild = $priceAdult * 0.5;
}
if ($priceSenior == 0 && $priceAdult > 0) {
    $priceSenior = $priceAdult * 0.7;
}
$enableChild = isset($showtime['enable_child_price']) && $showtime['enable_child_price'] == 1;
$enableSenior = isset($showtime['enable_senior_price']) && $showtime['enable_senior_price'] == 1;
$promotions = $showtime['promotions'] ? explode(',', $showtime['promotions']) : [];
$hasMondayPromo = in_array('lunes_mitad', $promotions);
$hasPresale = in_array('preventa', $promotions);
$currentDay = date('N');
if ($hasMondayPromo && $currentDay == 1) {
    $priceAdult = $priceAdult / 2;
    $priceChild = $priceChild / 2;
    $priceSenior = $priceSenior / 2;
}
$stmt = $pdo->query("SELECT tax_rate FROM tax_config WHERE is_active = 1 LIMIT 1");
$tax = $stmt->fetch();
$taxRate = $tax ? floatval($tax['tax_rate']) : 16;
$stmtRoom = $pdo->prepare("
SELECT r.capacity, r.seat_layout
FROM showtimes s
JOIN rooms r ON s.room_id = r.id
WHERE s.id = ?
");
$stmtRoom->execute([$showtimeId]);
$roomData = $stmtRoom->fetch();
$totalAvailableSeats = 0;
if ($roomData) {
    $layout = json_decode($roomData['seat_layout'], true);
    if ($layout && isset($layout['totalSeats'])) {
        $blockedSeats = $layout['blockedSeats'] ?? [];
        $totalAvailableSeats = $layout['totalSeats'] - count($blockedSeats);
    } else {
        $totalAvailableSeats = intval($roomData['capacity'] ?? 50);
    }
}
$stmtOccupied = $pdo->prepare("SELECT COUNT(*) as occupied FROM tickets WHERE showtime_id = ?");
$stmtOccupied->execute([$showtimeId]);
$occupied = $stmtOccupied->fetch();
$occupiedCount = intval($occupied['occupied'] ?? 0);
$realAvailableSeats = max(0, $totalAvailableSeats - $occupiedCount);
$language = $showtime['language'] ?? 'español';
$languageLabel = $language == 'español' ? 'Español' : 'Subtítulos en Español';
$format = $showtime['format'] ?? '2D';
$csrf_token = generateCSRFToken();
$siteConfig = getSiteConfig($pdo);
$pageTitle = "Selección de Boletos - " . $showtime['title'];
$backUrl = 'movie_detail.php?id=' . $showtime['movie_id'];
require_once 'header.php';
?>
<link rel="stylesheet" href="assets/css/shared-panel.css">
<link rel="stylesheet" href="assets/css/price_selection.css">
<div class="container mx-auto px-2 sm:px-4 py-4 sm:py-8 max-w-6xl">
    <div class="flex flex-col lg:flex-row gap-6">
        <div class="flex-1 min-w-0">
            <h2 class="text-2xl font-bold text-gray-900 mb-1">🎫 Selecciona tus Boletos</h2>
            <p class="text-base text-gray-700 font-medium mb-4">Elige la cantidad de boletos por tipo de tarifa</p>
            <div class="total-seats-info-top">
                <div class="font-medium text-gray-900">Has seleccionado <strong id="totalSeatsCount">0</strong> boleto(s)</div>
                <?php if ($realAvailableSeats > 0): ?>
                    <div class="text-sm text-gray-700 font-semibold mt-1"><?= $realAvailableSeats ?> asientos disponibles en esta función</div>
                <?php endif; ?>
            </div>
            <div class="grid grid-cols-1 gap-4" id="priceGrid">
                <div class="price-card" id="card-adult" data-type="adult">
                    <div class="price-info"><span class="label">👤 Adulto</span><span class="description">Precio estándar</span></div>
                    <div class="price-amount"><?= formatCurrency($priceAdult, $siteConfig) ?></div>
                    <div class="quantity-controls">
                        <button type="button" class="qty-decrease" data-type="adult">−</button>
                        <span class="qty" id="qty_adult">0</span>
                        <button type="button" class="qty-increase" data-type="adult">+</button>
                    </div>
                </div>
                <?php if ($enableChild && $priceChild > 0): ?>
                    <div class="price-card" id="card-child" data-type="child">
                        <div class="price-info"><span class="label">🧒 Niño</span><span class="description">Menores de 12 años</span></div>
                        <div class="price-amount"><?= formatCurrency($priceChild, $siteConfig) ?></div>
                        <div class="quantity-controls">
                            <button type="button" class="qty-decrease" data-type="child">−</button>
                            <span class="qty" id="qty_child">0</span>
                            <button type="button" class="qty-increase" data-type="child">+</button>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($enableSenior && $priceSenior > 0): ?>
                    <div class="price-card" id="card-senior" data-type="senior">
                        <div class="price-info"><span class="label">👴 Tercera Edad</span><span class="description">Mayores de 60 años</span></div>
                        <div class="price-amount"><?= formatCurrency($priceSenior, $siteConfig) ?></div>
                        <div class="quantity-controls">
                            <button type="button" class="qty-decrease" data-type="senior">−</button>
                            <span class="qty" id="qty_senior">0</span>
                            <button type="button" class="qty-increase" data-type="senior">+</button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="w-full lg:w-96 card-summary">
            <div class="flex gap-3 mb-5 items-start bg-slate-50 border border-slate-200 rounded-xl p-2.5 px-3">
                <?php if (!empty($showtime['poster_url'])): ?>
                    <img src="<?= htmlspecialchars($showtime['poster_url']) ?>" alt="<?= htmlspecialchars($showtime['title']) ?>" title="<?= htmlspecialchars($showtime['title']) ?>" class="w-24 h-36 object-cover rounded-lg shadow-sm flex-shrink-0" referrerpolicy="no-referrer">
                <?php endif; ?>
                <div class="flex flex-col justify-start text-left text-gray-900 flex-1 min-w-0">
                    <div class="font-extrabold text-lg leading-tight text-gray-900"><?= htmlspecialchars($showtime['title']) ?></div>
                    <div class="text-sm text-gray-700 font-medium mt-1.5">Idioma: <?= htmlspecialchars($languageLabel) ?></div>
                    <div class="text-sm text-gray-700 font-medium mt-1 whitespace-nowrap"><?= htmlspecialchars($showtime['room_name']) ?> · <?= formatDateShort($showtime['show_date']) ?> · <?= formatTimeVenezuela($showtime['show_time']) ?></div>
                    <div class="mt-1.5"><span class="format-badge"><?= htmlspecialchars($format) ?></span></div>
                    <div class="flex flex-col gap-2 mt-3 items-start">
                        <?php if (strtotime($showtime['show_date'] . ' ' . $showtime['show_time']) < time()): ?><span class="started-tag"><i class="fas fa-clock"></i> Ya inició Función</span><?php endif; ?>
                        <?php if ($hasMondayPromo): ?><span class="promo-tag monday"><span class="promo-dot"></span> Lunes a mitad de precio</span><?php endif; ?>
                        <?php if ($hasPresale): ?><span class="promo-tag presale"><span class="promo-dot"></span> Preventa</span><?php endif; ?>
                    </div>
                </div>
            </div>
            <div id="summaryItems" class="mt-4">
                <div class="text-sm text-gray-500 text-center py-2">No has seleccionado boletos</div>
            </div>
            <div class="summary-dotted-line"></div>
            <div class="summary-plain-row"><span>Subtotal</span><span id="subtotalAmount"><?= formatCurrency(0, $siteConfig) ?></span></div>
            <div class="summary-plain-row"><span>IVA (<?= $taxRate ?>%)</span><span id="taxAmount"><?= formatCurrency(0, $siteConfig) ?></span></div>
            <div class="summary-solid-line"></div>
            <div class="summary-plain-row bold-row"><span>Total a Pagar</span><span id="totalAmount"><?= formatCurrency(0, $siteConfig) ?></span></div>
            <div class="flex flex-col gap-2.5 mt-6">
                <form action="process_selection.php" method="POST" id="seatsForm">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="showtime_id" value="<?= $showtimeId ?>">
                    <input type="hidden" name="tickets" id="ticketsInput" value="">
                    <input type="hidden" name="total_seats" id="totalSeatsInput" value="0">
                    <input type="hidden" name="subtotal" id="subtotalHidden" value="0">
                    <input type="hidden" name="tax_amount" id="taxHidden" value="0">
                    <input type="hidden" name="total_amount" id="totalHidden" value="0">
                    <input type="hidden" name="purchase_token" value="<?= htmlspecialchars($purchaseToken) ?>">
                    <button type="submit" class="btn-continue" id="btnContinue" disabled>Elegir <span id="btnSeatsCount">0</span> Asiento(s)</button>
                </form>
                <a href="movie_detail.php?id=<?= $showtime['movie_id'] ?>" class="btn-back"><i class="fas fa-arrow-left mr-2"></i> Volver a Funciones</a>
            </div>
        </div>
    </div>
</div>
<?php require_once 'footer.php'; ?>
<script nonce="<?= htmlspecialchars($cspNonce ?? '') ?>">
    const priceAdult = <?= floatval($priceAdult) ?>;
    const priceChild = <?= floatval($priceChild) ?>;
    const priceSenior = <?= floatval($priceSenior) ?>;
    const enableChild = <?= $enableChild ? 'true' : 'false' ?>;
    const enableSenior = <?= $enableSenior ? 'true' : 'false' ?>;
    const taxRate = <?= floatval($taxRate) ?>;
    const showtimeId = <?= $showtimeId ?>;
    const maxAvailableSeats = <?= intval($realAvailableSeats) ?>;
    const purchaseToken = '<?= htmlspecialchars($purchaseToken) ?>';
    const currencyConfig = {
        symbol: '<?= $siteConfig['currency_symbol'] ?? '$' ?>',
        position: '<?= $siteConfig['currency_position'] ?? 'left' ?>',
        thousands: '<?= $siteConfig['thousands_separator'] ?? '.' ?>',
        decimal: '<?= $siteConfig['decimal_separator'] ?? ',' ?>',
        decimals: <?= intval($siteConfig['decimal_places'] ?? 2) ?>
    };</script>
<script src="assets/js/price_selection.js"></script>
</body>

</html>