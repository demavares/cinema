<?php
require_once 'config.php';

// ============================================
// PREVENIR CACHÉ DEL NAVEGADOR
// ============================================
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// ============================================
// VERIFICAR AUTENTICACIÓN
// ============================================
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$showtimeId = isset($_GET['showtime_id']) ? intval($_GET['showtime_id']) : 0;
if ($showtimeId <= 0) {
    header('Location: index.php');
    exit;
}

// ============================================
// VALIDAR TOKEN DE COMPRA DESDE SESIÓN
// ============================================
$purchaseToken = $_SESSION['purchase_token_' . $showtimeId] ?? '';

if (empty($purchaseToken) || !verifyPurchaseTokenWithTimeout($purchaseToken, $showtimeId)) {
    error_log("❌ Token inválido en food_menu.php: " . ($purchaseToken ?? 'NULL'));
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Token+inválido+o+expirado');
    exit;
}

// ============================================
// VERIFICAR SESIÓN DE COMIDA
// ============================================
$sessionValidKey = 'food_valid_' . $showtimeId;
$sessionSeatsKey = 'food_seats_' . $showtimeId;
$sessionTimeoutKey = 'food_timeout_' . $showtimeId;

if (!isset($_SESSION[$sessionValidKey]) || $_SESSION[$sessionValidKey] !== true) {
    header('Location: seats.php?showtime_id=' . $showtimeId . '&error=session_expired');
    exit;
}

if (isset($_SESSION[$sessionTimeoutKey]) && $_SESSION[$sessionTimeoutKey] <= 0) {
    unset($_SESSION[$sessionSeatsKey]);
    unset($_SESSION[$sessionValidKey]);
    unset($_SESSION[$sessionTimeoutKey]);
    header('Location: index.php?timeout=1');
    exit;
}

if (!isset($_SESSION[$sessionTimeoutKey])) {
    $_SESSION[$sessionTimeoutKey] = 600;
}

$seats = isset($_SESSION[$sessionSeatsKey]) ? $_SESSION[$sessionSeatsKey] : '';
if (empty($seats)) {
    header('Location: seats.php?showtime_id=' . $showtimeId . '&error=no_seats');
    exit;
}

$seatsArray = explode(',', $seats);
$ticketCount = count($seatsArray);

// ============================================
// NO LIMPIAR EL CARRITO SI VIENE DE PAYMENT.PHP
// ============================================
$sessionFoodKey = 'food_order_' . $showtimeId;
$fromPayment = isset($_GET['from']) && $_GET['from'] === 'payment';

if (!$fromPayment) {
    if (isset($_SESSION[$sessionFoodKey])) {
        unset($_SESSION[$sessionFoodKey]);
    }
}

// ============================================
// RECUPERAR CARRITO DE COMIDA DESDE SESIÓN
// ============================================
$foodOrder = isset($_SESSION[$sessionFoodKey]) ? json_decode($_SESSION[$sessionFoodKey], true) : [];
$foodCart = [];
if (!empty($foodOrder)) {
    foreach ($foodOrder as $item) {
        $foodCart[$item['id']] = $item['quantity'];
    }
}

// ============================================
// OBTENER DATOS DEL SHOWTIME Y PELÍCULA
// ============================================
$stmt = $pdo->prepare("
    SELECT s.*, m.id as movie_id, m.title, m.poster_url, m.duration,
           r.name as room_name
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

// ============================================
// OBTENER DATOS DE BOLETOS DESDE LA SESIÓN
// ============================================
$ticketsData = isset($_SESSION['ticket_quantities_' . $showtimeId]) 
    ? $_SESSION['ticket_quantities_' . $showtimeId] 
    : null;

// ============================================
// CALCULAR SUBTOTAL DE BOLETOS (SIN COMIDA)
// ============================================
$baseSubtotal = 0;
if ($ticketsData) {
    $priceAdult = floatval($showtime['price_adult'] ?? $showtime['price'] ?? 0);
    $priceChild = floatval($showtime['price_child'] ?? 0);
    $priceSenior = floatval($showtime['price_senior'] ?? 0);
    
    $promotions = $showtime['promotions'] ? explode(',', $showtime['promotions']) : [];
    $currentDay = date('N');
    if (in_array('lunes_mitad', $promotions) && $currentDay == 1) {
        $priceAdult = $priceAdult / 2;
        $priceChild = $priceChild / 2;
        $priceSenior = $priceSenior / 2;
    }
    
    $baseSubtotal = (intval($ticketsData['adult'] ?? 0) * $priceAdult) +
                    (intval($ticketsData['child'] ?? 0) * $priceChild) +
                    (intval($ticketsData['senior'] ?? 0) * $priceSenior);
} else {
    $basePrice = getShowtimePrice($showtime);
    $baseSubtotal = $ticketCount * $basePrice;
}

$stmt = $pdo->query("SELECT tax_rate FROM tax_config WHERE is_active = 1 LIMIT 1");
$tax = $stmt->fetch();
$taxRate = $tax ? floatval($tax['tax_rate']) : 16;

// Guardar el subtotal base en sesión
$_SESSION['base_subtotal_' . $showtimeId] = $baseSubtotal;
$_SESSION['tax_rate_' . $showtimeId] = $taxRate;

// Calcular precios por tipo
$priceAdult = floatval($showtime['price_adult'] ?? $showtime['price'] ?? 0);
$priceChild = floatval($showtime['price_child'] ?? 0);
$priceSenior = floatval($showtime['price_senior'] ?? 0);

$promotions = $showtime['promotions'] ? explode(',', $showtime['promotions']) : [];
$currentDay = date('N');
if (in_array('lunes_mitad', $promotions) && $currentDay == 1) {
    $priceAdult = $priceAdult / 2;
    $priceChild = $priceChild / 2;
    $priceSenior = $priceSenior / 2;
}

$language = $showtime['language'] ?? 'español';
$languageLabel = $language == 'español' ? 'Español' : 'Subtítulos en Español';

// ============================================
// OBTENER PRODUCTOS DE COMIDA ACTIVOS
// ============================================
$stmt = $pdo->prepare("
    SELECT f.*, c.name as category_name
    FROM food_items f
    LEFT JOIN food_categories c ON f.category_id = c.id
    WHERE f.is_active = 1
    ORDER BY c.name, f.name
");
$stmt->execute();
$foodItems = $stmt->fetchAll();

$foodByCategory = [];
foreach ($foodItems as $item) {
    $catName = $item['category_name'] ?? 'Sin categoría';
    if (!isset($foodByCategory[$catName])) {
        $foodByCategory[$catName] = [];
    }
    $foodByCategory[$catName][] = $item;
}

$csrf_token = generateCSRFToken();
$siteConfig = getSiteConfig($pdo);
$pageTitle = "Comida - " . $showtime['title'];

$backUrl = 'seats.php?showtime_id=' . $showtimeId;

$currency_symbol = $siteConfig['currency_symbol'] ?? '$';
$currency_position = $siteConfig['currency_position'] ?? 'left';
$thousands_separator = $siteConfig['thousands_separator'] ?? '.';
$decimal_separator = $siteConfig['decimal_separator'] ?? ',';
$decimal_places = intval($siteConfig['decimal_places'] ?? 2);

// ============================================
// PROMOCIONES Y FORMATO
// ============================================
$hasMondayPromo = in_array('lunes_mitad', $promotions);
$hasPresale = in_array('preventa', $promotions);

$movieFormat = $showtime['format'] ?? '2D';
$formatClass = 'format-2d';
if (!empty($movieFormat)) {
    $formatLower = strtolower($movieFormat);
    $formatClass = 'format-' . str_replace(' ', '-', $formatLower);
}

// Calcular totales iniciales con el carrito actual
$initialFoodTotal = 0;
foreach ($foodItems as $item) {
    $qty = isset($foodCart[$item['id']]) ? intval($foodCart[$item['id']]) : 0;
    if ($qty > 0) {
        $initialFoodTotal += $item['price'] * $qty;
    }
}
$initialSubtotal = $baseSubtotal + $initialFoodTotal;
$initialTax = $initialSubtotal * ($taxRate / 100);
$initialTotal = $initialSubtotal + $initialTax;

require_once 'header.php';
?>

<style>
body {
    background-color: #ffffff !important;
    color: #1f2937 !important;
}
.bg-\[\#14141e\] {
    background-color: #ffffff !important;
}
.border-\[\#1e1e2e\] {
    border-color: #e2e8f0 !important;
}
.timeout-warning {
    padding: 16px 24px;
    border-radius: 10px;
    font-size: 1rem;
    margin-top: 16px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 14px;
    position: sticky;
    top: 90px;
    z-index: 50;
    backdrop-filter: blur(12px);
    transition: all 0.5s ease;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
}
.timeout-warning.normal { background: #eef2ff; border: 1px solid #c7d2fe; color: #3730a3; }
.timeout-warning.warning { background: #fef3c7; border: 1px solid #fde68a; color: #92400e; }
.timeout-warning.danger { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; animation: pulse-danger 1s ease-in-out infinite; }
@keyframes pulse-danger { 0%, 100% { opacity: 1; } 50% { opacity: 0.75; } }
.timeout-warning .countdown { font-weight: 700; font-size: 1.3rem; min-width: 60px; text-align: center; }
.timeout-warning.normal .countdown { color: #4338ca; }
.timeout-warning.warning .countdown { color: #b45309; }
.timeout-warning.danger .countdown { color: #dc2626; animation: pulse-countdown 0.5s ease-in-out infinite; }
@keyframes pulse-countdown { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.6; transform: scale(1.1); } }
.food-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    transition: all 0.3s ease;
    cursor: pointer;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.food-card:hover { border-color: #6366f1; transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
.food-card.selected { border-color: #4f46e5; background: #f5f3ff; box-shadow: 0 0 15px rgba(99, 102, 241, 0.15); }
.food-card .food-image { width: 100%; height: 210px; max-height: 233px; object-fit: cover; background: #f1f5f9; }
.food-card .food-info { padding: 14px 16px 16px 16px; display: flex; flex-direction: column; justify-content: space-between; flex: 1; }
.food-card .food-name { font-weight: 700; color: #0f172a; font-size: 1.1rem; }
.food-card .food-price { color: #16a34a; font-weight: 700; font-size: 1.2rem; }
.food-card .food-desc { color: #475569; font-size: 0.95rem; margin-top: 6px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; }
.food-card .quantity-controls { display: flex; align-items: center; justify-content: center; gap: 14px; margin-top: 12px; padding: 6px 0; }
.food-card .quantity-controls button { background: #f1f5f9; border: 1px solid #cbd5e1; color: #1e293b; width: 34px; height: 34px; border-radius: 50%; cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; font-weight: 700; }
.food-card .quantity-controls button:hover { background: #4f46e5; border-color: #4f46e5; color: #ffffff; }
.food-card .quantity-controls .qty { font-weight: 700; color: #0f172a; min-width: 24px; text-align: center; font-size: 1.1rem; }
.category-title { color: #334155; font-size: 1.15rem; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; margin: 24px 0 14px 0; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
.category-title i { margin-right: 8px; color: #4f46e5; }
.card-summary {
    background: #ffffff !important;
    border: 1px solid #cbd5e1 !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.06) !important;
    border-radius: 12px !important;
    padding: 24px;
}
.summary-dotted-line {
    border-top: 2px dashed #94a3b8;
    margin: 14px 0;
}
.summary-solid-line {
    border-top: 2px solid #6366f1;
    margin: 14px 0;
}
.summary-plain-row {
    display: flex;
    justify-content: space-between;
    font-size: 1rem;
    color: #1f2937;
    margin-bottom: 8px;
}
.summary-plain-row.bold-row {
    font-weight: 800;
    font-size: 1.15rem;
}
.summary-movie-poster {
    width: 80px;
    height: 120px;
    object-fit: cover;
    border-radius: 8px;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.summary-movie-title {
    font-weight: 700;
    color: #0f172a;
    font-size: 1.1rem;
    line-height: 1.3;
}
.promo-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    border: 1px solid;
}
.promo-tag .promo-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
    flex-shrink: 0;
}
.promo-tag.monday {
    background: #dcfce7;
    color: #15803d;
    border-color: #bbf7d0;
}
.promo-tag.monday .promo-dot {
    background: #15803d;
}
.promo-tag.presale {
    background: #fef3c7;
    color: #b45309;
    border-color: #fde68a;
}
.promo-tag.presale .promo-dot {
    background: #b45309;
}
.format-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 2px 10px;
    border-radius: 5px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    line-height: 1.4;
    background: transparent !important;
    border: 1px solid #4f5e71;
    color: #4f5e71;
}
.format-badge.format-2d,
.format-badge.format-3d,
.format-badge.format-imax,
.format-badge.format-imax-3d,
.format-badge.format-4dx,
.format-badge.format-screenx,
.format-badge.format-d-box {
    border-color: #4f5e71;
    color: #4f5e71;
}
.btn-continue {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: #ffffff !important;
    padding: 14px 20px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 1.1rem;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 100%;
    text-align: center;
    display: block;
}
.btn-continue:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(79, 70, 229, 0.25);
}
.btn-continue:disabled { opacity: 0.4; cursor: not-allowed; transform: none !important; }
.btn-back {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #334155 !important;
    padding: 11px 20px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    cursor: pointer;
    width: 100%;
    text-align: center;
    text-decoration: none;
    display: block;
}
.btn-back:hover { border-color: #6366f1; color: #4f46e5 !important; background: #eef2ff; }
.cart-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 0;
    border-bottom: 1px solid #e2e8f0;
    font-size: 0.95rem;
}
.cart-item:last-child { border-bottom: none; }
.cart-item .item-name { color: #1e293b; flex: 1; word-break: break-word; font-weight: 500; }
.cart-item .item-price { color: #16a34a; font-weight: 600; font-size: 0.95rem; }
.cart-item .remove-btn { color: #ef4444; cursor: pointer; transition: color 0.2s; background: none; border: none; font-size: 0.95rem; padding: 2px 4px; }
.cart-item .remove-btn:hover { color: #b91c1c; }
.cart-empty {
    color: #64748b;
    text-align: center;
    padding: 18px 0;
    font-size: 0.95rem;
}
.cart-empty i { font-size: 1.8rem; display: block; margin-bottom: 6px; color: #cbd5e1; }
.ticket-summary-item {
    display: flex;
    justify-content: space-between;
    font-size: 0.9rem;
    color: #475569;
    padding: 2px 0;
}
.ticket-summary-item .ticket-type {
    font-weight: 500;
}
.ticket-summary-item .ticket-total {
    font-weight: 600;
    color: #16a34a;
}
.seats-display {
    font-size: 0.95rem;
    font-weight: 500;
    color: #475569;
    word-break: break-word;
}
@media (min-width: 1024px) {
    .card-summary {
        position: sticky;
        top: 100px;
    }
}
@media (max-width: 1024px) {
    .timeout-warning { top: 90px; }
}
@media (max-width: 768px) {
    .food-card .food-image { height: 180px; max-height: 180px; }
    .card-summary { padding: 18px; position: relative; top: auto; }
    .timeout-warning {
        top: 85px;
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 6px;
        padding: 16px 18px;
        margin-top: 12px;
    }
    .timeout-warning .ml-auto { margin-left: 0 !important; }
}
@media (max-width: 480px) {
    .food-card .food-image { height: 150px; max-height: 150px; }
    .timeout-warning { top: 75px; padding: 14px 16px; }
}
</style>

<div class="container mx-auto px-2 sm:px-4 py-4 sm:py-8 max-w-7xl">
    <!-- Timeout Warning -->
    <div class="timeout-warning normal" id="timeoutWarning">
        <div class="flex items-center gap-2">
            <i class="fas fa-clock" id="timeoutIcon"></i>
            <span>Tu sesión expirará en <span class="countdown" id="countdownTimer">10:00</span></span>
        </div>
        <span class="ml-auto text-xs sm:text-sm" id="timeoutStatus">Los asientos se liberarán automáticamente</span>
    </div>

    <div class="flex flex-col lg:flex-row gap-6">
        <!-- Menú de Comida -->
        <div class="flex-1 min-w-0">
            <h2 class="text-2xl font-bold text-gray-800 mb-1">🍿 Elige tu comida</h2>
            <p class="text-base text-gray-400 mb-6">Selecciona los productos que deseas agregar a tu pedido (opcional)</p>

            <?php if (empty($foodItems)): ?>
            <div class="bg-slate-50 p-8 rounded-xl border border-slate-200 text-center">
                <div class="text-4xl mb-3">🍿</div>
                <p class="text-gray-500 text-base">No hay productos de comida disponibles en este momento.</p>
            </div>
            <?php else: ?>
            <?php foreach ($foodByCategory as $category => $items): ?>
            <div class="category-title"><i class="fas fa-tag"></i> <?= htmlspecialchars($category) ?></div>
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
                <?php foreach ($items as $item): ?>
                <div class="food-card <?= isset($foodCart[$item['id']]) && $foodCart[$item['id']] > 0 ? 'selected' : '' ?>" data-food-id="<?= $item['id'] ?>">
                    <?php if (!empty($item['image_url']) && file_exists($item['image_url'])): ?>
                    <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="food-image">
                    <?php else: ?>
                    <div class="food-image flex items-center justify-center text-5xl text-slate-400 bg-slate-100">🍿</div>
                    <?php endif; ?>
                    <div class="food-info">
                        <div>
                            <div class="flex justify-between items-start gap-2">
                                <span class="food-name"><?= htmlspecialchars($item['name']) ?></span>
                                <span class="food-price"><?= formatCurrency($item['price'], $siteConfig) ?></span>
                            </div>
                            <?php if (!empty($item['description'])): ?>
                            <p class="food-desc"><?= htmlspecialchars($item['description']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="quantity-controls">
                            <button type="button" class="qty-decrease" data-id="<?= $item['id'] ?>">−</button>
                            <span class="qty" id="qty_<?= $item['id'] ?>"><?= isset($foodCart[$item['id']]) ? $foodCart[$item['id']] : 0 ?></span>
                            <button type="button" class="qty-increase" data-id="<?= $item['id'] ?>">+</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- CARD SUMMARY -->
        <div class="w-full lg:w-96 card-summary">
            <!-- SECCIÓN DE PELÍCULA -->
            <div class="flex gap-3 mb-5 items-start bg-slate-50 border border-slate-200 rounded-xl p-2.5 px-3">
                <img src="<?= htmlspecialchars($showtime['poster_url'] ?? '') ?>" 
                     alt="<?= htmlspecialchars($showtime['title'] ?? '') ?>" 
                     title="<?= htmlspecialchars($showtime['title'] ?? '') ?>"
                     class="summary-movie-poster"
                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22150%22 viewBox=%220 0 100 150%22%3E%3Crect fill=%22%231a1a2e%22 width=%22100%22 height=%22150%22/%3E%3Ctext x=%2250%22 y=%2275%22 text-anchor=%22middle%22 fill=%22%236b7280%22 font-size=%2240%22 font-family=%22Arial%22%3E🎬%3C/text%3E%3C/svg%3E'">

                <div class="flex flex-col justify-start text-left text-gray-900 flex-1 min-w-0">
                    <div class="font-extrabold text-lg leading-tight text-gray-900 summary-movie-title">
                        <?= htmlspecialchars($showtime['title'] ?? '') ?>
                    </div>

                    <div class="text-sm text-gray-700 font-medium mt-1.5">
                        Idioma: <?= htmlspecialchars($languageLabel) ?>
                    </div>

                    <div class="text-sm text-gray-700 font-medium mt-1 whitespace-nowrap">
                        <?= htmlspecialchars($showtime['room_name'] ?? 'Sala no disponible') ?> · 
                        <?= formatDateShort($showtime['show_date'] ?? '') ?> · 
                        <?= formatTimeVenezuela($showtime['show_time'] ?? '') ?>
                    </div>

                    <div class="mt-1.5">
                        <span class="format-badge <?= $formatClass ?>"><?= htmlspecialchars($movieFormat) ?></span>
                    </div>

                    <div class="flex flex-col gap-2 mt-3 items-start">
                        <?php if ($hasMondayPromo): ?>
                            <span class="promo-tag monday">
                                <span class="promo-dot"></span>
                                Lunes a mitad de precio
                            </span>
                        <?php endif; ?>
                        <?php if ($hasPresale): ?>
                            <span class="promo-tag presale">
                                <span class="promo-dot"></span>
                                Preventa
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- RESUMEN DE BOLETOS POR TIPO -->
            <div class="mb-3">
                <p class="text-xs text-gray-400 font-semibold uppercase mb-1">🎫 Boletos</p>
                <?php
                $ticketTypes = [
                    'adult' => ['label' => 'Adulto', 'price' => $priceAdult],
                    'child' => ['label' => 'Niño', 'price' => $priceChild],
                    'senior' => ['label' => 'Tercera Edad', 'price' => $priceSenior]
                ];
                $hasTickets = false;
                if ($ticketsData) {
                    foreach ($ticketTypes as $key => $type) {
                        $qty = isset($ticketsData[$key]) ? intval($ticketsData[$key]) : 0;
                        if ($qty > 0 && $type['price'] > 0) {
                            $hasTickets = true;
                            echo '<div class="ticket-summary-item">';
                            echo '<span class="ticket-type">' . $qty . ' x ' . $type['label'] . '</span>';
                            echo '<span class="ticket-total">' . formatCurrency($qty * $type['price'], $siteConfig) . '</span>';
                            echo '</div>';
                        }
                    }
                }
                if (!$hasTickets) {
                    echo '<p class="text-sm text-gray-500">No hay boletos seleccionados</p>';
                }
                ?>
                <div class="seats-display mt-1">
                    Asientos: <span class="font-bold text-slate-800"><?= htmlspecialchars(implode(', ', $seatsArray)) ?></span>
                </div>
            </div>

            <!-- DETALLE DE COMIDA SELECCIONADA -->
            <div id="cartItems" class="mt-4">
                <?php if (empty($foodCart)): ?>
                <div class="cart-empty" id="cartEmpty">
                    <i class="fas fa-shopping-cart"></i>
                    No has seleccionado comida
                </div>
                <?php else: ?>
                <div id="cartItemsList">
                    <?php 
                    $totalFoodPriceDisplay = 0;
                    foreach ($foodItems as $item):
                        $qty = isset($foodCart[$item['id']]) ? intval($foodCart[$item['id']]) : 0;
                        if ($qty > 0):
                            $totalFoodPriceDisplay += $item['price'] * $qty;
                    ?>
                    <div class="cart-item" data-food-id="<?= $item['id'] ?>">
                        <span class="item-name"><?= $qty ?> x <?= htmlspecialchars($item['name']) ?></span>
                        <div class="item-details">
                            <span class="item-price"><?= formatCurrency($item['price'] * $qty, $siteConfig) ?></span>
                            <button type="button" class="remove-btn" onclick="removeFromCart(<?= $item['id'] ?>)" title="Eliminar">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="summary-dotted-line"></div>

            <!-- CÁLCULOS - SE ACTUALIZAN DINÁMICAMENTE CON JS -->
            <div class="summary-plain-row">
                <span>Subtotal</span>
                <span id="subtotalAmount"><?= formatCurrency($initialSubtotal, $siteConfig) ?></span>
            </div>
            <div class="summary-plain-row">
                <span>IVA (<?= $taxRate ?>%)</span>
                <span id="taxAmount"><?= formatCurrency($initialTax, $siteConfig) ?></span>
            </div>

            <div class="summary-solid-line"></div>

            <div class="summary-plain-row bold-row">
                <span>Total a Pagar</span>
                <span id="totalAmount"><?= formatCurrency($initialTotal, $siteConfig) ?></span>
            </div>

            <div class="flex flex-col gap-2.5 mt-6">
                <form action="save_food_order.php" method="POST" id="foodForm">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="showtime_id" value="<?= $showtimeId ?>">
                    <input type="hidden" name="food_order" id="foodOrderInput" value='<?= json_encode($foodCart) ?>'>
                    <input type="hidden" name="purchase_token" value="<?= htmlspecialchars($purchaseToken) ?>">
                    <input type="hidden" name="redirect" value="1">
                    <button type="submit" class="btn-continue" id="btnCheckout">
                        <i class="fas fa-credit-card mr-2"></i> Ir a Pagar
                    </button>
                </form>
                <a href="<?= $backUrl ?>&from=food" class="btn-back">
                    <i class="fas fa-arrow-left mr-2"></i> Volver a Asientos
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>

<script src="timeout_manager.js"></script>

<script>
// ============================================
// INICIALIZAR TIMEOUT MANAGER
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const showtimeId = <?= $showtimeId ?>;
    const seats = '<?= $seats ?>';
    
    TimeoutManager.init({
        showtimeId: showtimeId,
        seats: seats,
        initialTimeout: 600,
        syncInterval: 10000,
        redirectOnExpire: true,
        redirectUrl: 'index.php?timeout=1'
    });
});

// ============================================
// CONFIGURACIÓN DESDE PHP
// ============================================
const currencyConfig = {
    symbol: '<?= $currency_symbol ?>',
    position: '<?= $currency_position ?>',
    thousands: '<?= $thousands_separator ?>',
    decimal: '<?= $decimal_separator ?>',
    decimals: <?= $decimal_places ?>
};

const baseSubtotal = <?= $baseSubtotal ?>;
const taxRate = <?= $taxRate ?>;
const showtimeId = <?= $showtimeId ?>;
const purchaseToken = '<?= htmlspecialchars($purchaseToken) ?>';
const foodItems = <?= json_encode($foodItems) ?>;

// ============================================
// ESTADO DEL CARRITO - INICIALIZAR DESDE PHP
// ============================================
let cart = {};
<?php foreach ($foodCart as $id => $qty): ?>
cart[<?= $id ?>] = { 
    id: <?= $id ?>, 
    quantity: <?= $qty ?>
};
<?php endforeach; ?>

let totalFoodPrice = 0;

console.log('🛒 Carrito inicializado:', cart);
console.log('📊 Base Subtotal (boletos):', baseSubtotal);

// ============================================
// FUNCIONES DE FORMATO
// ============================================
function formatCurrency(amount) {
    const symbol = currencyConfig.symbol;
    const position = currencyConfig.position;
    const thousands = currencyConfig.thousands;
    const decimal = currencyConfig.decimal;
    const decimals = currencyConfig.decimals;
    let formatted = Number(amount).toFixed(decimals)
        .replace('.', decimal)
        .replace(/\B(?=(\d{3})+(?!\d))/g, thousands);
    return position === 'right' ? formatted + ' ' + symbol : symbol + formatted;
}

// ============================================
// ACTUALIZAR UI DEL CARRITO Y TOTALES
// ============================================
function updateCartUI() {
    const cartContainer = document.getElementById('cartItems');
    const totalAmountEl = document.getElementById('totalAmount');
    const subtotalAmountEl = document.getElementById('subtotalAmount');
    const taxAmountEl = document.getElementById('taxAmount');
    const items = Object.values(cart);
    
    totalFoodPrice = 0;
    items.forEach(item => {
        const foodItem = foodItems.find(f => f.id == item.id);
        if (foodItem) {
            totalFoodPrice += foodItem.price * item.quantity;
        }
    });
    
    const subtotal = baseSubtotal + totalFoodPrice;
    const tax = subtotal * (taxRate / 100);
    const total = subtotal + tax;
    
    document.querySelectorAll('.food-card').forEach(card => {
        const id = card.dataset.foodId;
        if (cart[id] && cart[id].quantity > 0) {
            card.classList.add('selected');
        } else {
            card.classList.remove('selected');
        }
    });
    
    document.querySelectorAll('.qty').forEach(el => {
        const id = el.id.replace('qty_', '');
        if (cart[id]) {
            el.textContent = cart[id].quantity;
        } else {
            el.textContent = '0';
        }
    });
    
    if (items.length === 0) {
        cartContainer.innerHTML = `
            <div class="cart-empty" id="cartEmpty">
                <i class="fas fa-shopping-cart"></i>
                No has seleccionado comida
            </div>
        `;
    } else {
        let html = '<div id="cartItemsList">';
        items.forEach(item => {
            const foodItem = foodItems.find(f => f.id == item.id);
            if (!foodItem) return;
            const itemName = item.quantity + ' x ' + foodItem.name;
            html += `
            <div class="cart-item" data-food-id="${item.id}">
                <span class="item-name">${escapeHtml(itemName)}</span>
                <div class="item-details">
                    <span class="item-price">${formatCurrency(foodItem.price * item.quantity)}</span>
                    <button type="button" class="remove-btn" onclick="removeFromCart(${item.id})" title="Eliminar">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            `;
        });
        html += '</div>';
        cartContainer.innerHTML = html;
    }
    
    subtotalAmountEl.textContent = formatCurrency(subtotal);
    taxAmountEl.textContent = formatCurrency(tax);
    totalAmountEl.textContent = formatCurrency(total);
    
    const foodOrderInput = document.getElementById('foodOrderInput');
    if (foodOrderInput) {
        const orderData = items.map(item => ({
            id: parseInt(item.id),
            quantity: item.quantity
        }));
        foodOrderInput.value = JSON.stringify(orderData);
        console.log('📝 foodOrderInput actualizado:', foodOrderInput.value);
    }
    
    try {
        const orderData = items.map(item => ({
            id: parseInt(item.id),
            quantity: item.quantity
        }));
        sessionStorage.setItem('food_order_' + showtimeId, JSON.stringify(orderData));
    } catch(e) {}
}

// ============================================
// FUNCIONES DEL CARRITO
// ============================================
function removeFromCart(foodId) {
    delete cart[foodId];
    updateCartUI();
}

function updateCart(foodId, quantity) {
    const foodItem = foodItems.find(f => f.id == foodId);
    if (!foodItem) return;
    
    if (quantity > 0) {
        cart[foodId] = { 
            id: foodItem.id,
            quantity: quantity 
        };
    } else {
        delete cart[foodId];
    }
    updateCartUI();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ============================================
// EVENT LISTENERS
// ============================================
document.querySelectorAll('.qty-increase').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        const id = parseInt(this.dataset.id);
        const qtyEl = document.getElementById('qty_' + id);
        let qty = parseInt(qtyEl.textContent) || 0;
        qty++;
        qtyEl.textContent = qty;
        updateCart(id, qty);
    });
});

document.querySelectorAll('.qty-decrease').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        const id = parseInt(this.dataset.id);
        const qtyEl = document.getElementById('qty_' + id);
        let qty = parseInt(qtyEl.textContent) || 0;
        if (qty > 0) {
            qty--;
            qtyEl.textContent = qty;
            updateCart(id, qty);
        }
    });
});

document.querySelectorAll('.food-card').forEach(card => {
    card.addEventListener('click', function(e) {
        if (e.target.closest('.quantity-controls')) return;
        const id = parseInt(this.dataset.foodId);
        const qtyEl = document.getElementById('qty_' + id);
        let qty = parseInt(qtyEl.textContent) || 0;
        qty++;
        qtyEl.textContent = qty;
        updateCart(id, qty);
    });
});

// ============================================
// MANEJAR ENVÍO DEL FORMULARIO (CORREGIDO)
// ============================================
document.getElementById('foodForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const items = Object.values(cart);
    const orderData = items.map(item => ({
        id: parseInt(item.id),
        quantity: item.quantity
    }));
    
    console.log('📤 Enviando pedido a save_food_order.php:', orderData);
    
    const foodOrderInput = document.getElementById('foodOrderInput');
    if (foodOrderInput) {
        foodOrderInput.value = JSON.stringify(orderData);
    }
    
    const tokenInput = document.querySelector('input[name="purchase_token"]');
    if (tokenInput) {
        tokenInput.value = purchaseToken;
    }
    
    const btn = document.getElementById('btnCheckout');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Procesando...';
    
    this.submit();
});

// ============================================
// INICIALIZAR UI
// ============================================
updateCartUI();
</script>
</body>
</html>