<?php
require_once 'config.php';
// ✅ Verificar si viene con expired
if (isset($_GET['expired']) && $_GET['expired'] === '1') {
    header('Location: index.php?expired=1');
    exit;
}
// ✅ Verificar sesión expirada
if (isset($_GET['session_expired']) ||
    (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'session_expired') !== false)) {
    $keys = array_keys($_SESSION);
    foreach ($keys as $key) {
        if (strpos($key, 'purchase_') === 0 ||
            strpos($key, 'food_') === 0 ||
            strpos($key, 'ticket_') === 0 ||
            strpos($key, 'total_') === 0 ||
            strpos($key, 'subtotal_') === 0 ||
            strpos($key, 'tax_') === 0 ||
            strpos($key, 'payment_') === 0) {
            unset($_SESSION[$key]);
        }
    }
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
checkSessionExpired($showtimeId);
// ============================================
// VERIFICAR SESIÓN DE COMIDA
// ============================================
$sessionValidKey = 'food_valid_' . $showtimeId;
$sessionSeatsKey = 'food_seats_' . $showtimeId;
$sessionTimeoutKey = 'food_timeout_' . $showtimeId;
$sessionFoodKey = 'food_order_' . $showtimeId;
// CORREGIDO: Si la sesión de comida no existe, volver a asientos
// (ya NO muestra "¡Sesión Expirada!" al recargar; solo ocurre en seats.php)
if (!isset($_SESSION[$sessionValidKey]) || $_SESSION[$sessionValidKey] !== true) {
    error_log("⚠️ food_menu.php - Sesión de comida NO válida para showtime $showtimeId. Regresando a asientos.");
    header('Location: seats.php?showtime_id=' . $showtimeId . '&from=food');
    exit;
}
if (isset($_SESSION[$sessionTimeoutKey]) && $_SESSION[$sessionTimeoutKey] <= 0) {
    error_log("⏰ food_menu.php - Timeout expirado para showtime $showtimeId");
    unset($_SESSION[$sessionTimeoutKey]);
    unset($_SESSION[$sessionSeatsKey]);
    unset($_SESSION[$sessionValidKey]);
    unset($_SESSION[$sessionFoodKey]);
    header('Location: index.php?timeout=1');
    exit;
}
if (!isset($_SESSION[$sessionTimeoutKey])) {
    $_SESSION[$sessionTimeoutKey] = 600;
}
// VERIFICAR TOKEN
$token = $_SESSION['purchase_token_' . $showtimeId] ?? '';
if (!verifyPurchaseToken($token, $showtimeId)) {
    error_log("❌ food_menu.php - Token inválido para showtime $showtimeId");
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Token+inválido+o+expirado');
    exit;
}
// OBTENER ASIENTOS
$seats = $_SESSION[$sessionSeatsKey] ?? '';
if (empty($seats)) {
    error_log("❌ food_menu.php - No hay asientos en sesión para showtime $showtimeId");
    header('Location: seats.php?showtime_id=' . $showtimeId . '&error=no_seats');
    exit;
}
// SI LLEGAMOS AQUÍ, LA SESIÓN ES VÁLIDA
$seatsArray = explode(',', $seats);
$ticketCount = count($seatsArray);
// ============================================
// NUEVO: VERIFICAR Y RESTAURAR LA RESERVA EN BD (MODO GRACIA)
// Si venimos de un unload (gracia de 20 s) o de un F5, la compra pending
// aún existe y la extendemos de nuevo a 10 minutos.
// Si ya no existe (gracia consumida por cierre o expirada), volvemos a
// asientos para re-seleccionar sin romper el flujo.
// ============================================
$stmtPending = $pdo->prepare("
    SELECT id FROM purchases
    WHERE user_id = ? AND showtime_id = ? AND status = 'pending' AND expires_at > NOW()
");
$stmtPending->execute([$_SESSION['user_id'], $showtimeId]);
$pendingPurchase = $stmtPending->fetch();
if (!$pendingPurchase) {
    error_log("⚠️ food_menu.php - Reserva no vigente para showtime $showtimeId. Regresando a asientos.");
    header('Location: seats.php?showtime_id=' . $showtimeId . '&from=food');
    exit;
}
// Cancela la gracia y restaura la reserva (10 min)
try {
    $stmtRestore = $pdo->prepare("UPDATE purchases SET expires_at = DATE_ADD(NOW(), INTERVAL 600 SECOND) WHERE id = ?");
    $stmtRestore->execute([$pendingPurchase['id']]);
} catch (Exception $e) {
    error_log("⚠️ food_menu.php: Error extendiendo reserva: " . $e->getMessage());
}
// ============================================
// RECUPERAR CARRITO DE COMIDA
// ============================================
$fromPayment = isset($_GET['from']) && $_GET['from'] === 'payment';
$foodOrder = isset($_SESSION[$sessionFoodKey]) ? json_decode($_SESSION[$sessionFoodKey], true) : [];
$foodCart = [];
if (!empty($foodOrder)) {
    foreach ($foodOrder as $item) {
        $foodCart[$item['id']] = $item['quantity'];
    }
}
// ============================================
// OBTENER DATOS DEL SHOWTIME
// ============================================
$stmt = $pdo->prepare("
    SELECT s.*, m.id as movie_id, m.title, m.poster_url, m.duration, r.name as room_name
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
// ✅ BLOQUEO: se permite comprar hasta 15 minutos después del inicio de la función
// ============================================
$showtimeDateTime = strtotime($showtime['show_date'] . ' ' . $showtime['show_time']);
$currentDateTime = time();
$safetyMargin = 15 * 60;
if (($showtimeDateTime + $safetyMargin) < $currentDateTime) {
    error_log("⛔ food_menu.php: Función iniciada hace más de 15 minutos");
    header('Location: index.php?error=Este+horario+ya+no+está+disponible');
    exit;
}
// ============================================
// OBTENER DATOS DE BOLETOS
// ============================================
$ticketsData = $_SESSION['ticket_quantities_' . $showtimeId] ?? null;
// ============================================
// CALCULAR SUBTOTAL BASE
// ============================================
$baseSubtotal = 0;
if ($ticketsData) {
    $priceAdult = floatval($showtime['price_adult'] ?? $showtime['price'] ?? 0);
    $priceChild = floatval($showtime['price_child'] ?? 0);
    $priceSenior = floatval($showtime['price_senior'] ?? 0);
    $promotions = $showtime['promotions'] ? explode(',', $showtime['promotions']) : [];
    if (in_array('lunes_mitad', $promotions) && date('N') == 1) {
        $priceAdult /= 2;
        $priceChild /= 2;
        $priceSenior /= 2;
    }
    $baseSubtotal = (intval($ticketsData['adult'] ?? 0) * $priceAdult) +
        (intval($ticketsData['child'] ?? 0) * $priceChild) +
        (intval($ticketsData['senior'] ?? 0) * $priceSenior);
} else {
    $baseSubtotal = $ticketCount * getShowtimePrice($showtime);
}
$stmt = $pdo->query("SELECT tax_rate FROM tax_config WHERE is_active = 1 LIMIT 1");
$tax = $stmt->fetch();
$taxRate = $tax ? floatval($tax['tax_rate']) : 16;
$_SESSION['base_subtotal_' . $showtimeId] = $baseSubtotal;
$_SESSION['tax_rate_' . $showtimeId] = $taxRate;
// ============================================
// OBTENER PRODUCTOS DE COMIDA
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
    $foodByCategory[$catName][] = $item;
}
// ============================================
// CALCULAR TOTALES INICIALES
// ============================================
$initialFoodTotal = 0;
foreach ($foodItems as $item) {
    $qty = $foodCart[$item['id']] ?? 0;
    if ($qty > 0) {
        $initialFoodTotal += $item['price'] * $qty;
    }
}
$initialSubtotal = $baseSubtotal + $initialFoodTotal;
$initialTax = $initialSubtotal * ($taxRate / 100);
$initialTotal = $initialSubtotal + $initialTax;
// ============================================
// DATOS PARA LA VISTA
// ============================================
$csrf_token = generateCSRFToken();
$siteConfig = getSiteConfig($pdo);
$pageTitle = "Comida - " . $showtime['title'];
$backUrl = 'seats.php?showtime_id=' . $showtimeId;
$promotions = $showtime['promotions'] ? explode(',', $showtime['promotions']) : [];
$hasMondayPromo = in_array('lunes_mitad', $promotions);
$hasPresale = in_array('preventa', $promotions);
$language = $showtime['language'] ?? 'español';
$lang_label = $language == 'español' ? 'Español' : 'Subtítulos en Español';
$format = $showtime['format'] ?? '2D';
require_once 'header.php';
?>
<style>
body { background-color: #ffffff !important; color: #1f2937 !important; }
.bg-\[\#14141e\] { background-color: #ffffff !important; }
.border-\[\#1e1e2e\] { border-color: #e2e8f0 !important; }
.timeout-warning { padding: 16px 24px; border-radius: 10px; font-size: 1rem; margin-top: 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 14px; position: sticky; top: 90px; z-index: 40; backdrop-filter: blur(12px); transition: all 0.5s ease; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
.timeout-warning.normal { background: #eef2ff; border: 1px solid #c7d2fe; color: #3730a3; }
.timeout-warning.warning { background: #fef3c7; border: 1px solid #fde68a; color: #92400e; }
.timeout-warning.danger { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; animation: pulse-danger 1s ease-in-out infinite; }
@keyframes pulse-danger { 0%, 100% { opacity: 1; } 50% { opacity: 0.75; } }
.timeout-warning .countdown { font-weight: 700; font-size: 1.3rem; min-width: 95px; text-align: center; }
.timeout-warning.normal .countdown { color: #4338ca; }
.timeout-warning.warning .countdown { color: #b45309; }
.timeout-warning.danger .countdown { color: #dc2626; animation: pulse-countdown 0.5s ease-in-out infinite; }
@keyframes pulse-countdown { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.6; transform: scale(1.1); } }
.food-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; transition: all 0.3s ease; cursor: pointer; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04); display: flex; flex-direction: column; justify-content: space-between; }
.food-card:hover { border-color: #6366f1; transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
.food-card.selected { border-color: #4f46e5; background: #f5f3ff; box-shadow: 0 0 15px rgba(99,102,241,0.15); }
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
.card-summary { background: #ffffff !important; border: 1px solid #cbd5e1 !important; box-shadow: 0 4px 12px rgba(0,0,0,0.06) !important; border-radius: 12px !important; padding: 24px; }
.summary-dotted-line { border-top: 2px dashed #94a3b8; margin: 14px 0; }
.summary-solid-line { border-top: 2px solid #6366f1; margin: 14px 0; }
.summary-plain-row { display: flex; justify-content: space-between; font-size: 1rem; color: #1f2937; margin-bottom: 8px; }
.summary-plain-row.bold-row { font-weight: 800; font-size: 1.15rem; }
.summary-movie-poster { width: 80px; height: 120px; object-fit: cover; border-radius: 8px; flex-shrink: 0; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.summary-movie-title { font-weight: 700; color: #0f172a; font-size: 1.1rem; line-height: 1.3; }
.promo-tag { display: inline-flex; align-items: center; gap: 6px; padding: 4px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; border: 1px solid; }
.promo-tag .promo-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
.promo-tag.monday { background: #dcfce7; color: #15803d; border-color: #bbf7d0; }
.promo-tag.monday .promo-dot { background: #15803d; }
.promo-tag.presale { background: #fef3c7; color: #b45309; border-color: #fde68a; }
.promo-tag.presale .promo-dot { background: #b45309; }
.started-tag { display: inline-flex; align-items: center; gap: 6px; padding: 4px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; border: 1px solid #fecaca; background: #fee2e2; color: #dc2626; text-transform: uppercase; }
.format-badge { display: inline-flex; align-items: center; justify-content: center; padding: 2px 10px; border-radius: 5px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; line-height: 1.4; background: transparent !important; border: 1px solid #4f5e71; color: #4f5e71; }
.btn-continue { background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #ffffff !important; padding: 14px 20px; border-radius: 8px; font-weight: 700; font-size: 1.1rem; border: none; cursor: pointer; transition: all 0.3s ease; width: 100%; text-align: center; display: block; }
.btn-continue:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(79,70,229,0.25); }
.btn-continue:disabled { opacity: 0.4; cursor: not-allowed; transform: none !important; }
.btn-back { background: #ffffff; border: 1px solid #cbd5e1; color: #334155 !important; padding: 11px 20px; border-radius: 8px; font-weight: 600; font-size: 0.95rem; transition: all 0.3s ease; cursor: pointer; width: 100%; text-align: center; text-decoration: none; display: block; }
.btn-back:hover { border-color: #6366f1; color: #4f46e5 !important; background: #eef2ff; }
.cart-item { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid #e2e8f0; font-size: 0.95rem; }
.cart-item:last-child { border-bottom: none; }
.cart-item .item-name { color: #1e293b; flex: 1; word-break: break-word; font-weight: 500; }
.cart-item .item-price { color: #16a34a; font-weight: 600; font-size: 0.95rem; }
.cart-item .remove-btn { color: #ef4444; cursor: pointer; transition: color 0.2s; background: none; border: none; font-size: 0.95rem; padding: 2px 4px; }
.cart-item .remove-btn:hover { color: #b91c1c; }
.cart-empty { color: #64748b; text-align: center; padding: 18px 0; font-size: 0.95rem; }
.cart-empty i { font-size: 1.8rem; display: block; margin-bottom: 6px; color: #cbd5e1; }
.ticket-summary-item { display: flex; justify-content: space-between; font-size: 0.9rem; color: #475569; padding: 2px 0; }
.ticket-summary-item .ticket-type { font-weight: 500; }
.ticket-summary-item .ticket-total { font-weight: 600; color: #16a34a; }
.seats-display { font-size: 0.95rem; font-weight: 500; color: #475569; word-break: break-word; }
@media (min-width: 1024px) { .card-summary { position: sticky; top: 100px; } }
@media (max-width: 768px) {
.food-card .food-image { height: 180px; }
.card-summary { padding: 18px; }
.timeout-warning { top: 85px; flex-direction: column; align-items: center; justify-content: center; text-align: center; gap: 6px; padding: 16px 18px; }
.timeout-warning .countdown { min-width: auto; }
}
</style>
<div class="container mx-auto px-2 sm:px-4 py-4 sm:py-8 max-w-7xl">
<div class="timeout-warning normal" id="timeoutWarning">
<div class="flex items-center gap-2">
<i class="fas fa-clock" id="timeoutIcon"></i>
<span>Tu sesión expirará en <span class="countdown" id="countdownTimer">10:00 min</span></span>
</div>
<span class="md:ml-auto text-xs sm:text-sm" id="timeoutStatus">Los asientos se liberarán automáticamente</span>
</div>
<div class="flex flex-col lg:flex-row gap-6">
<div class="flex-1 min-w-0">
<h2 class="text-2xl font-bold text-gray-800 mb-1">🍿 Elige tu comida</h2>
<p class="text-base text-gray-800 mb-6">Selecciona los productos que deseas agregar a tu pedido (opcional)</p>
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
<span class="qty" id="qty_<?= $item['id'] ?>"><?= $foodCart[$item['id']] ?? 0 ?></span>
<button type="button" class="qty-increase" data-id="<?= $item['id'] ?>">+</button>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
<div class="w-full lg:w-96 card-summary">
<div class="flex gap-3 mb-5 items-start bg-slate-50 border border-slate-200 rounded-xl p-2.5 px-3">
<img src="<?= htmlspecialchars($showtime['poster_url'] ?? '') ?>" alt="<?= htmlspecialchars($showtime['title'] ?? '') ?>" title="<?= htmlspecialchars($showtime['title'] ?? '') ?>" class="summary-movie-poster" data-error-hide>
<div class="flex flex-col justify-start text-left text-gray-900 flex-1 min-w-0">
<div class="font-extrabold text-lg leading-tight text-gray-900 summary-movie-title"><?= htmlspecialchars($showtime['title'] ?? '') ?></div>
<div class="text-sm text-gray-700 font-medium mt-1.5">Idioma: <?= htmlspecialchars($lang_label) ?></div>
<div class="text-sm text-gray-700 font-medium mt-1 whitespace-nowrap"><?= htmlspecialchars($showtime['room_name'] ?? '') ?> · <?= formatDateShort($showtime['show_date'] ?? '') ?> · <?= formatTimeVenezuela($showtime['show_time'] ?? '') ?></div>
<div class="mt-1.5"><span class="format-badge"><?= htmlspecialchars($format) ?></span></div>
<div class="flex flex-col gap-2 mt-3 items-start">
<?php if (strtotime($showtime['show_date'] . ' ' . $showtime['show_time']) < time()): ?><span class="started-tag"><i class="fas fa-clock"></i> Ya inició Función</span><?php endif; ?>
<?php if ($hasMondayPromo): ?><span class="promo-tag monday"><span class="promo-dot"></span> Lunes a mitad de precio</span><?php endif; ?>
<?php if ($hasPresale): ?><span class="promo-tag presale"><span class="promo-dot"></span> Preventa</span><?php endif; ?>
</div>
</div>
</div>
<div class="mb-3">
<p class="text-xs font-semibold uppercase mb-1" style="color: #4f46e5;">🎫 Boletos</p>
<?php
$ticketTypes = ['adult' => 'Adulto', 'child' => 'Niño', 'senior' => 'Tercera Edad'];
$hasTickets = false;
if ($ticketsData) {
    foreach ($ticketTypes as $key => $label) {
        $qty = $ticketsData[$key] ?? 0;
        if ($qty > 0) {
            $hasTickets = true;
            $price = ${'price' . ucfirst($key)} ?? 0;
            echo '<div class="ticket-summary-item"><span class="ticket-type">' . $qty . ' x ' . $label . '</span><span class="ticket-total">' . formatCurrency($qty * $price, $siteConfig) . '</span></div>';
        }
    }
}
if (!$hasTickets) echo '<p class="text-sm text-gray-500">No hay boletos seleccionados</p>';
?>
<div class="seats-display mt-1">Asientos: <span class="font-bold text-slate-800"><?= htmlspecialchars(implode(', ', $seatsArray)) ?></span></div>
</div>
<div id="cartItems" class="mt-4">
<?php if (empty($foodCart)): ?>
<div class="cart-empty" id="cartEmpty"><i class="fas fa-shopping-cart"></i> No has seleccionado comida</div>
<?php else: ?>
<div id="cartItemsList">
<?php foreach ($foodItems as $item): $qty = $foodCart[$item['id']] ?? 0; if ($qty > 0): ?>
<div class="cart-item" data-food-id="<?= $item['id'] ?>">
<span class="item-name"><?= $qty ?> x <?= htmlspecialchars($item['name']) ?></span>
<div class="item-details">
<span class="item-price"><?= formatCurrency($item['price'] * $qty, $siteConfig) ?></span>
<!-- CORREGIDO: sin onclick inline (CSP-safe), usa data-remove-food -->
<button type="button" class="remove-btn" data-remove-food="<?= $item['id'] ?>"><i class="fas fa-times"></i></button>
</div>
</div>
<?php endif; endforeach; ?>
</div>
<?php endif; ?>
</div>
<div class="summary-dotted-line"></div>
<div class="summary-plain-row"><span>Subtotal</span><span id="subtotalAmount"><?= formatCurrency($initialSubtotal, $siteConfig) ?></span></div>
<div class="summary-plain-row"><span>IVA (<?= $taxRate ?>%)</span><span id="taxAmount"><?= formatCurrency($initialTax, $siteConfig) ?></span></div>
<div class="summary-solid-line"></div>
<div class="summary-plain-row bold-row"><span>Total a Pagar</span><span id="totalAmount"><?= formatCurrency($initialTotal, $siteConfig) ?></span></div>
<div class="flex flex-col gap-2.5 mt-6">
<form action="save_food_order.php" method="POST" id="foodForm">
<input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
<input type="hidden" name="showtime_id" value="<?= $showtimeId ?>">
<input type="hidden" name="food_order" id="foodOrderInput" value='<?= json_encode($foodCart) ?>'>
<input type="hidden" name="purchase_token" id="purchaseTokenInput" value="<?= htmlspecialchars($token) ?>">
<input type="hidden" name="redirect" value="1">
<button type="submit" class="btn-continue" id="btnCheckout"><i class="fas fa-credit-card mr-2"></i> Ir a Pagar</button>
</form>
<button type="button" class="btn-back" id="btnBackToTickets"><i class="fas fa-arrow-left mr-2"></i> Volver a Boletos</button>
</div>
</div>
</div>
</div>
<?php require_once 'footer.php'; ?>
<script src="timeout_manager.js"></script>
<script nonce="<?= htmlspecialchars($cspNonce ?? '') ?>">
const baseSubtotal = <?= $baseSubtotal ?>;
const taxRate = <?= $taxRate ?>;
const showtimeId = <?= $showtimeId ?>;
const purchaseToken = '<?= htmlspecialchars($token) ?>';
const foodItems = <?= json_encode($foodItems) ?>;
const currencyConfig = {
    symbol: '<?= $siteConfig['currency_symbol'] ?? '$' ?>',
    position: '<?= $siteConfig['currency_position'] ?? 'left' ?>',
    thousands: '<?= $siteConfig['thousands_separator'] ?? '.' ?>',
    decimal: '<?= $siteConfig['decimal_separator'] ?? ',' ?>',
    decimals: <?= intval($siteConfig['decimal_places'] ?? 2) ?>
};
let cart = {};
<?php foreach ($foodCart as $id => $qty): ?>cart[<?= $id ?>] = { id: <?= $id ?>, quantity: <?= $qty ?> };
<?php endforeach; ?>
let totalFoodPrice = 0;
// Bandera para evitar liberar asientos al navegar hacia pagar
let skipUnloadRelease = false;
function formatCurrency(amount) {
    const formatted = Number(amount).toFixed(currencyConfig.decimals)
        .replace('.', currencyConfig.decimal)
        .replace(/\B(?=(\d{3})+(?!\d))/g, currencyConfig.thousands);
    return currencyConfig.position === 'left' ? currencyConfig.symbol + formatted : formatted + ' ' + currencyConfig.symbol;
}
function liberarAsientos(callback) {
    const formData = new FormData();
    formData.append('showtime_id', showtimeId);
    fetch('liberar_asientos.php', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(response => response.json())
        .then(data => { if (callback) callback(data.success); })
        .catch(() => { if (callback) callback(false); });
}
// ============================================
// NUEVO: GRACIA AL SALIR (cerrar pestaña / navegador)
// NO liberamos de inmediato: marcamos la compra para expirar en 20 s.
// - Si recargan (F5) o vuelven al flujo, el PHP restaura la reserva.
// - Si cerraron pestaña/navegador, la compra expira y la limpieza libera.
// ============================================
let unloadReleaseSent = false;
function releaseSeatsOnUnload() {
    if (skipUnloadRelease || unloadReleaseSent) return;
    unloadReleaseSent = true;
    const formData = new FormData();
    formData.append('showtime_id', showtimeId);
    formData.append('action', 'grace');
    if (navigator.sendBeacon) {
        navigator.sendBeacon('liberar_asientos.php', formData);
    } else {
        fetch('liberar_asientos.php', { method: 'POST', body: formData, keepalive: true });
    }
}
window.addEventListener('pagehide', releaseSeatsOnUnload);
window.addEventListener('beforeunload', releaseSeatsOnUnload);
// Liberación EXPLÍCITA (abandono del flujo): modo full
document.getElementById('btnBackToTickets').addEventListener('click', function() {
    if (!confirm('¿Estás seguro? Se liberarán los asientos seleccionados y perderás tu selección de comida.')) return;
    liberarAsientos(function(success) {
        if (success) {
            skipUnloadRelease = true;
            window.location.href = 'price_selection.php?showtime_id=' + showtimeId;
        } else {
            alert('Error al liberar asientos. Intenta nuevamente.');
        }
    });
});
function updateCartUI() {
    const items = Object.values(cart);
    totalFoodPrice = 0;
    items.forEach(item => {
        const foodItem = foodItems.find(f => f.id == item.id);
        if (foodItem) totalFoodPrice += foodItem.price * item.quantity;
    });
    const subtotal = baseSubtotal + totalFoodPrice;
    const tax = subtotal * (taxRate / 100);
    const total = subtotal + tax;
    document.querySelectorAll('.food-card').forEach(card => {
        const id = card.dataset.foodId;
        card.classList.toggle('selected', cart[id] && cart[id].quantity > 0);
    });
    document.querySelectorAll('.qty').forEach(el => {
        const id = el.id.replace('qty_', '');
        el.textContent = cart[id] ? cart[id].quantity : 0;
    });
    const container = document.getElementById('cartItems');
    if (items.length === 0) {
        container.innerHTML = '<div class="cart-empty"><i class="fas fa-shopping-cart"></i> No has seleccionado comida</div>';
    } else {
        let html = '<div id="cartItemsList">';
        items.forEach(item => {
            const foodItem = foodItems.find(f => f.id == item.id);
            if (!foodItem) return;
            html += `<div class="cart-item" data-food-id="${item.id}">
                <span class="item-name">${item.quantity} x ${escapeHtml(foodItem.name)}</span>
                <div class="item-details">
                    <span class="item-price">${formatCurrency(foodItem.price * item.quantity)}</span>
                    <button type="button" class="remove-btn" data-remove-food="${item.id}"><i class="fas fa-times"></i></button>
                </div>
            </div>`;
        });
        html += '</div>';
        container.innerHTML = html;
    }
    document.getElementById('subtotalAmount').textContent = formatCurrency(subtotal);
    document.getElementById('taxAmount').textContent = formatCurrency(tax);
    document.getElementById('totalAmount').textContent = formatCurrency(total);
    const foodOrderInput = document.getElementById('foodOrderInput');
    if (foodOrderInput) {
        foodOrderInput.value = JSON.stringify(items.map(item => ({ id: parseInt(item.id), quantity: item.quantity })));
    }
}
function removeFromCart(foodId) {
    delete cart[foodId];
    updateCartUI();
}
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
// CORREGIDO: Delegación de eventos para botones de eliminar
// (funciona también con los botones generados dinámicamente por updateCartUI)
document.getElementById('cartItems').addEventListener('click', function(e) {
    const btn = e.target.closest('[data-remove-food]');
    if (!btn) return;
    const foodId = parseInt(btn.getAttribute('data-remove-food'));
    if (!isNaN(foodId)) removeFromCart(foodId);
});
document.querySelectorAll('.qty-increase').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        const id = parseInt(this.dataset.id);
        const qtyEl = document.getElementById('qty_' + id);
        let qty = parseInt(qtyEl.textContent) || 0;
        qty++;
        qtyEl.textContent = qty;
        cart[id] = cart[id] || { id: id, quantity: 0 };
        cart[id].quantity = qty;
        updateCartUI();
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
            if (qty === 0) delete cart[id];
            else cart[id].quantity = qty;
            updateCartUI();
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
        cart[id] = cart[id] || { id: id, quantity: 0 };
        cart[id].quantity = qty;
        updateCartUI();
    });
});
document.getElementById('foodForm').addEventListener('submit', function(e) {
    e.preventDefault();
    skipUnloadRelease = true;
    const items = Object.values(cart);
    document.getElementById('foodOrderInput').value = JSON.stringify(items.map(item => ({ id: parseInt(item.id), quantity: item.quantity })));
    const tokenInput = document.getElementById('purchaseTokenInput');
    if (tokenInput) tokenInput.value = purchaseToken;
    this.submit();
});
updateCartUI();
document.addEventListener('DOMContentLoaded', function() {
    if (window.TimeoutManager) {
        TimeoutManager.init({
            showtimeId: showtimeId,
            seats: '<?= $seats ?>',
            initialTimeout: 600,
            syncInterval: 10000,
            redirectOnExpire: true,
            redirectUrl: 'index.php?timeout=1'
        });
    }
});
</script>
</body>
</html>