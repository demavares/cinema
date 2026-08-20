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
if ($priceAdult == 0) { $priceAdult = 50.00; }
if ($priceChild == 0 && $priceAdult > 0) { $priceChild = $priceAdult * 0.5; }
if ($priceSenior == 0 && $priceAdult > 0) { $priceSenior = $priceAdult * 0.7; }
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
<style>
body { background-color: #ffffff !important; color: #1f2937 !important; }
.price-card {
background: #ffffff; border: 2px solid #e2e8f0; border-radius: 16px; padding: 24px;
transition: all 0.3s ease; display: flex; align-items: center; justify-content: space-between;
box-shadow: 0 2px 8px rgba(0,0,0,0.04); cursor: pointer;
}
.price-card:hover:not(.disabled) { border-color: #6366f1; box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
.price-card .price-info { display: flex; flex-direction: column; pointer-events: none; }
.price-card .price-info .label { font-weight: 700; font-size: 1.1rem; color: #0f172a; pointer-events: none; }
.price-card .price-info .description { font-size: 0.85rem; color: #4b5563; pointer-events: none; }
.price-card .price-amount { font-size: 1.5rem; font-weight: 700; color: #16a34a; pointer-events: none; }
.price-card .quantity-controls { display: flex; align-items: center; gap: 16px; pointer-events: none; }
.price-card .quantity-controls button { width: 36px; height: 36px; border-radius: 50%; border: 1px solid #cbd5e1; background: #f1f5f9; color: #1e293b; font-size: 1.2rem; font-weight: 700; cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; pointer-events: auto; }
.price-card .quantity-controls button:hover:not(:disabled) { background: #4f46e5; border-color: #4f46e5; color: #ffffff; }
.price-card .quantity-controls button:disabled { opacity: 0.3; cursor: not-allowed; }
.price-card .quantity-controls .qty { font-size: 1.3rem; font-weight: 700; color: #0f172a; min-width: 32px; text-align: center; pointer-events: none; }
.price-card.disabled { display: none !important; }
.card-summary { background: #ffffff !important; border: 1px solid #cbd5e1 !important; box-shadow: 0 4px 12px rgba(0,0,0,0.06) !important; border-radius: 12px !important; padding: 24px; }
.summary-dotted-line { border-top: 2px dashed #94a3b8; margin: 14px 0; }
.summary-solid-line { border-top: 2px solid #6366f1; margin: 14px 0; }
.summary-plain-row { display: flex; justify-content: space-between; font-size: 1rem; color: #1f2937; margin-bottom: 8px; }
.summary-plain-row.bold-row { font-weight: 800; font-size: 1.15rem; }
.btn-continue { background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #ffffff !important; padding: 14px 20px; border-radius: 8px; font-weight: 700; font-size: 1.1rem; border: none; cursor: pointer; transition: all 0.3s ease; width: 100%; text-align: center; display: block; }
.btn-continue:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(79,70,229,0.25); }
.btn-continue:disabled { opacity: 0.4; cursor: not-allowed; transform: none !important; }
.btn-back { background: #ffffff; border: 1px solid #cbd5e1; color: #334155 !important; padding: 11px 20px; border-radius: 8px; font-weight: 600; font-size: 0.95rem; transition: all 0.3s ease; cursor: pointer; width: 100%; text-align: center; text-decoration: none; display: block; }
.btn-back:hover { border-color: #6366f1; color: #4f46e5 !important; background: #eef2ff; }
.total-seats-info-top { text-align: center; padding: 14px; background: #f8fafc; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 1.05rem; color: #1f2937; margin-bottom: 20px; }
.total-seats-info-top strong { color: #0f172a; font-size: 1.25rem; font-weight: 800; }
.promo-tag { display: inline-flex; align-items: center; gap: 6px; padding: 4px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; border: 1px solid; }
.promo-tag .promo-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
.promo-tag.monday { background: #dcfce7; color: #15803d; border-color: #bbf7d0; }
.promo-tag.monday .promo-dot { background: #15803d; }
.promo-tag.presale { background: #fef3c7; color: #b45309; border-color: #fde68a; }
.promo-tag.presale .promo-dot { background: #b45309; }
.format-badge { display: inline-flex; align-items: center; justify-content: center; padding: 2px 10px; border-radius: 5px; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; line-height: 1.4; background: transparent !important; border: 1px solid #4f5e71; color: #4f5e71; }
@media (min-width: 1024px) { .card-summary { position: sticky; top: 100px; } }
@media (max-width: 640px) {
.price-card { flex-direction: column; align-items: stretch; gap: 12px; padding: 16px; }
.price-card .quantity-controls { justify-content: center; }
.price-card .price-amount { text-align: center; }
}
</style>
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
<?php if($enableChild && $priceChild > 0): ?>
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
<?php if($enableSenior && $priceSenior > 0): ?>
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
<img src="<?= htmlspecialchars($showtime['poster_url']) ?>" alt="<?= htmlspecialchars($showtime['title']) ?>" class="w-24 h-36 object-cover rounded-lg shadow-sm flex-shrink-0" referrerpolicy="no-referrer">
<?php endif; ?>
<div class="flex flex-col justify-start text-left text-gray-900 flex-1 min-w-0">
<div class="font-extrabold text-lg leading-tight text-gray-900"><?= htmlspecialchars($showtime['title']) ?></div>
<div class="text-sm text-gray-700 font-medium mt-1.5">Idioma: <?= htmlspecialchars($languageLabel) ?></div>
<div class="text-sm text-gray-700 font-medium mt-1 whitespace-nowrap"><?= htmlspecialchars($showtime['room_name']) ?> · <?= formatDateShort($showtime['show_date']) ?> · <?= formatTimeVenezuela($showtime['show_time']) ?></div>
<div class="mt-1.5"><span class="format-badge"><?= htmlspecialchars($format) ?></span></div>
<div class="flex flex-col gap-2 mt-3 items-start">
<?php if ($hasMondayPromo): ?><span class="promo-tag monday"><span class="promo-dot"></span> Lunes a mitad de precio</span><?php endif; ?>
<?php if ($hasPresale): ?><span class="promo-tag presale"><span class="promo-dot"></span> Preventa</span><?php endif; ?>
</div>
</div>
</div>
<div id="summaryItems" class="mt-4"><div class="text-sm text-gray-500 text-center py-2">No has seleccionado boletos</div></div>
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
};
let quantities = { adult: 0, child: 0, senior: 0 };
const prices = { adult: priceAdult, child: priceChild, senior: priceSenior };
const enabledTypes = { adult: true, child: enableChild && priceChild > 0, senior: enableSenior && priceSenior > 0 };
function formatCurrency(amount) {
const symbol = currencyConfig.symbol, position = currencyConfig.position;
const thousands = currencyConfig.thousands, decimal = currencyConfig.decimal;
const decimals = currencyConfig.decimals;
let formatted = Number(amount).toFixed(decimals).replace('.', decimal).replace(/\B(?=(\d{3})+(?!\d))/g, thousands);
return position === 'right' ? formatted + ' ' + symbol : symbol + formatted;
}
function updateUI() {
const total = quantities.adult + quantities.child + quantities.senior;
const subtotal = (quantities.adult * prices.adult) + (quantities.child * prices.child) + (quantities.senior * prices.senior);
const tax = subtotal * (taxRate / 100);
const totalAmount = subtotal + tax;
const qtyAdult = document.getElementById('qty_adult');
const qtyChild = document.getElementById('qty_child');
const qtySenior = document.getElementById('qty_senior');
if (qtyAdult) qtyAdult.textContent = quantities.adult;
if (qtyChild) qtyChild.textContent = quantities.child;
if (qtySenior) qtySenior.textContent = quantities.senior;
const totalSeatsCount = document.getElementById('totalSeatsCount');
const btnSeatsCount = document.getElementById('btnSeatsCount');
const totalSeatsInput = document.getElementById('totalSeatsInput');
if (totalSeatsCount) totalSeatsCount.textContent = total;
if (btnSeatsCount) btnSeatsCount.textContent = total;
if (totalSeatsInput) totalSeatsInput.value = total;
const subtotalHidden = document.getElementById('subtotalHidden');
const taxHidden = document.getElementById('taxHidden');
const totalHidden = document.getElementById('totalHidden');
if (subtotalHidden) subtotalHidden.value = subtotal.toFixed(2);
if (taxHidden) taxHidden.value = tax.toFixed(2);
if (totalHidden) totalHidden.value = totalAmount.toFixed(2);
const summaryItems = document.getElementById('summaryItems');
if (summaryItems) {
let html = '', hasItems = false;
if (quantities.adult > 0) { hasItems = true; html += `<div class="summary-plain-row"><span>Adulto x${quantities.adult}</span><span>${formatCurrency(quantities.adult * prices.adult)}</span></div>`; }
if (quantities.child > 0) { hasItems = true; html += `<div class="summary-plain-row"><span>Niño x${quantities.child}</span><span>${formatCurrency(quantities.child * prices.child)}</span></div>`; }
if (quantities.senior > 0) { hasItems = true; html += `<div class="summary-plain-row"><span>Tercera Edad x${quantities.senior}</span><span>${formatCurrency(quantities.senior * prices.senior)}</span></div>`; }
summaryItems.innerHTML = hasItems ? html : `<div class="text-sm text-gray-500 text-center py-2">No has seleccionado boletos</div>`;
}
const subtotalAmount = document.getElementById('subtotalAmount');
const taxAmount = document.getElementById('taxAmount');
const totalAmountEl = document.getElementById('totalAmount');
if (subtotalAmount) subtotalAmount.textContent = formatCurrency(subtotal);
if (taxAmount) taxAmount.textContent = formatCurrency(tax);
if (totalAmountEl) totalAmountEl.textContent = formatCurrency(totalAmount);
const btnContinue = document.getElementById('btnContinue');
if (btnContinue) {
if (total > 0 && total <= maxAvailableSeats) {
btnContinue.disabled = false;
btnContinue.innerHTML = `Elegir ${total} Asiento${total !== 1 ? 's' : ''}`;
} else if (total > maxAvailableSeats) {
btnContinue.disabled = true;
btnContinue.innerHTML = `⚠️ Solo ${maxAvailableSeats} asientos disponibles`;
} else {
btnContinue.disabled = true;
btnContinue.innerHTML = 'Elegir 0 Asientos';
}
}
const ticketsInput = document.getElementById('ticketsInput');
if (ticketsInput) ticketsInput.value = JSON.stringify(quantities);
}
function updateQuantity(type, change) {
if (!enabledTypes[type]) return;
const newValue = quantities[type] + change;
if (newValue < 0) return;
quantities[type] = newValue;
updateUI();
}
document.addEventListener('DOMContentLoaded', function() {
try { sessionStorage.removeItem('selected_seats_' + showtimeId); sessionStorage.removeItem('selected_seats_count_' + showtimeId); } catch(e) {}
document.querySelectorAll('.qty-increase').forEach(function(btn) {
btn.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation(); updateQuantity(this.dataset.type, 1); });
});
document.querySelectorAll('.qty-decrease').forEach(function(btn) {
btn.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation(); updateQuantity(this.dataset.type, -1); });
});
document.querySelectorAll('.price-card:not(.disabled)').forEach(function(card) {
card.addEventListener('click', function(e) {
if (e.target.closest('.quantity-controls button')) return;
const type = this.dataset.type;
if (type) updateQuantity(type, 1);
});
});
document.getElementById('seatsForm').addEventListener('submit', function(e) {
const total = quantities.adult + quantities.child + quantities.senior;
if (total === 0) { e.preventDefault(); alert('Por favor, selecciona al menos un boleto.'); return false; }
if (total > maxAvailableSeats) { e.preventDefault(); alert('Solo hay ' + maxAvailableSeats + ' asientos disponibles.'); return false; }
return true;
});
updateUI();
});
</script>
</body>
</html>