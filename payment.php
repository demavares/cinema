<?php
require_once 'config.php';
// ✅ Verificar sesión expirada
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
// ✅ Verificar sesión expirada específica para este showtime
checkSessionExpired($showtimeId);
// ============================================
// VERIFICAR SESIÓN DE COMIDA
// ============================================
$sessionValidKey = 'food_valid_' . $showtimeId;
$sessionSeatsKey = 'food_seats_' . $showtimeId;
$sessionTimeoutKey = 'food_timeout_' . $showtimeId;
$sessionFoodKey = 'food_order_' . $showtimeId;
if (!isset($_SESSION[$sessionValidKey]) || $_SESSION[$sessionValidKey] !== true) {
header('Location: seats.php?showtime_id=' . $showtimeId . '&error=session_expired');
exit;
}
if (isset($_SESSION[$sessionTimeoutKey]) && $_SESSION[$sessionTimeoutKey] <= 0) {
header('Location: index.php?timeout=1');
exit;
}
// Verificar token
$token = $_SESSION['purchase_token_' . $showtimeId] ?? '';
if (!verifyPurchaseToken($token, $showtimeId)) {
header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Token+inválido+o+expirado');
exit;
}
$seats = $_SESSION[$sessionSeatsKey] ?? '';
if (empty($seats)) {
header('Location: seats.php?showtime_id=' . $showtimeId . '&error=no_seats');
exit;
}
$seatsArray = explode(',', $seats);
$ticketCount = count($seatsArray);
// Obtener datos del showtime
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
// ✅ CORREGIDO: VALIDAR QUE EL SHOWTIME NO HAYA PASADO
// ============================================
$showtimeDateTime = strtotime($showtime['show_date'] . ' ' . $showtime['show_time']);
$currentDateTime = time();
if ($showtimeDateTime < $currentDateTime) {
error_log("❌ payment.php: Intento de acceder a showtime pasado");
header('Location: index.php?error=Este+horario+ya+no+está+disponible');
exit;
}
// Validar con margen de seguridad (15 minutos antes del inicio)
$safetyMargin = 15 * 60;
if (($showtimeDateTime - $safetyMargin) < $currentDateTime) {
error_log("⚠️ payment.php: Showtime muy próximo a iniciar");
header('Location: seats.php?showtime_id=' . $showtimeId . '&error=Este+horario+está+por+iniciar.+Selecciona+otro');
exit;
}
// Obtener datos de boletos
$ticketsData = $_SESSION['ticket_quantities_' . $showtimeId] ?? null;
// Calcular subtotal base
$baseSubtotal = 0;
if ($ticketsData) {
$priceAdult = floatval($showtime['price_adult'] ?? $showtime['price'] ?? 0);
$priceChild = floatval($showtime['price_child'] ?? 0);
$priceSenior = floatval($showtime['price_senior'] ?? 0);
$promotions = $showtime['promotions'] ? explode(',', $showtime['promotions']) : [];
if (in_array('lunes_mitad', $promotions) && date('N') == 1) {
$priceAdult /= 2; $priceChild /= 2; $priceSenior /= 2;
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
// Procesar comida
$foodOrder = isset($_SESSION[$sessionFoodKey]) ? json_decode($_SESSION[$sessionFoodKey], true) : [];
$totalFoodPrice = 0;
$foodItems = [];
if (!empty($foodOrder)) {
$foodIds = array_column($foodOrder, 'id');
if (!empty($foodIds)) {
$placeholders = implode(',', array_fill(0, count($foodIds), '?'));
$stmt = $pdo->prepare("SELECT * FROM food_items WHERE id IN ($placeholders) AND is_active = 1");
$stmt->execute($foodIds);
$availableFood = $stmt->fetchAll();
foreach ($availableFood as $item) {
foreach ($foodOrder as $order) {
if ($order['id'] == $item['id']) {
$qty = intval($order['quantity']);
if ($qty > 0) {
$foodItems[] = [
'id' => $item['id'],
'name' => $item['name'],
'quantity' => $qty,
'price' => $item['price'],
'total' => $item['price'] * $qty
];
$totalFoodPrice += $item['price'] * $qty;
}
break;
}
}
}
}
}
$subtotalWithFood = $baseSubtotal + $totalFoodPrice;
$taxAmountWithFood = $subtotalWithFood * ($taxRate / 100);
$totalAmountWithFood = $subtotalWithFood + $taxAmountWithFood;
$_SESSION['subtotal_' . $showtimeId] = $subtotalWithFood;
$_SESSION['tax_amount_' . $showtimeId] = $taxAmountWithFood;
$_SESSION['total_amount_' . $showtimeId] = $totalAmountWithFood;
$_SESSION['tax_rate_' . $showtimeId] = $taxRate;
$csrf_token = generateCSRFToken();
$siteConfig = getSiteConfig($pdo);
$pageTitle = "Método de Pago - " . $showtime['title'];
$backUrl = 'food_menu.php?showtime_id=' . $showtimeId;
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
.timeout-warning { padding: 16px 24px; border-radius: 10px; font-size: 1rem; margin-top: 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 14px; position: sticky; top: 90px; z-index: 50; backdrop-filter: blur(12px); transition: all 0.5s ease; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
.timeout-warning.normal { background: #eef2ff; border: 1px solid #c7d2fe; color: #3730a3; }
.timeout-warning.warning { background: #fef3c7; border: 1px solid #fde68a; color: #92400e; }
.timeout-warning.danger { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; animation: pulse-danger 1s ease-in-out infinite; }
@keyframes pulse-danger { 0%, 100% { opacity: 1; } 50% { opacity: 0.75; } }
.timeout-warning .countdown { font-weight: 700; font-size: 1.3rem; min-width: 60px; text-align: center; }
.timeout-warning.normal .countdown { color: #4338ca; }
.timeout-warning.warning .countdown { color: #b45309; }
.timeout-warning.danger .countdown { color: #dc2626; animation: pulse-countdown 0.5s ease-in-out infinite; }
@keyframes pulse-countdown { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.6; transform: scale(1.1); } }
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
.format-badge { display: inline-flex; align-items: center; justify-content: center; padding: 2px 10px; border-radius: 5px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; line-height: 1.4; background: transparent !important; border: 1px solid #4f5e71; color: #4f5e71; }
.payment-methods { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 20px; }
.payment-method { background: #ffffff; border: 2px solid #e2e8f0; border-radius: 12px; padding: 24px 20px; cursor: pointer; transition: all 0.3s ease; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
.payment-method:hover { border-color: #6366f1; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
.payment-method.selected { border-color: #4f46e5; background: #f5f3ff; box-shadow: 0 0 20px rgba(99, 102, 241, 0.15); }
.payment-method .icon { font-size: 2.5rem; margin-bottom: 8px; display: block; }
.payment-method .name { color: #0f172a; font-weight: 700; font-size: 1.1rem; }
.payment-method .description { color: #475569; font-size: 0.9rem; margin-top: 4px; }
.payment-method input[type="radio"] { display: none; }
.payment-details { display: none; margin-top: 20px; padding: 20px; background: #f8fafc; border-radius: 12px; border: 1px solid #cbd5e1; }
.payment-details.active { display: block; }
.payment-details label { color: #334155; font-size: 0.95rem; font-weight: 600; display: block; margin-bottom: 6px; }
.payment-details input, .payment-details select { width: 100%; padding: 12px 14px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; color: #0f172a; font-size: 1rem; margin-bottom: 16px; transition: border-color 0.3s ease; }
.payment-details input:focus, .payment-details select:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
.btn-pay { background: linear-gradient(135deg, #22c55e, #16a34a); color: white; padding: 14px 30px; border-radius: 8px; font-weight: 700; border: none; cursor: pointer; transition: all 0.3s ease; width: 100%; font-size: 1.15rem; margin-top: 24px; }
.btn-pay:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(34, 197, 94, 0.3); }
.btn-pay:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; box-shadow: none !important; }
.btn-back { background: #ffffff; border: 1px solid #cbd5e1; color: #334155 !important; padding: 11px 20px; border-radius: 8px; font-weight: 600; font-size: 0.95rem; transition: all 0.3s ease; cursor: pointer; width: 100%; text-align: center; text-decoration: none; display: block; }
.btn-back:hover { border-color: #6366f1; color: #4f46e5 !important; background: #eef2ff; }
.cart-item { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid #e2e8f0; font-size: 0.95rem; }
.cart-item:last-child { border-bottom: none; }
.cart-item .item-name { color: #1e293b; flex: 1; word-break: break-word; font-weight: 500; }
.cart-item .item-price { color: #16a34a; font-weight: 600; font-size: 0.95rem; }
.ticket-summary-item { display: flex; justify-content: space-between; font-size: 0.9rem; color: #475569; padding: 2px 0; }
.ticket-summary-item .ticket-type { font-weight: 500; }
.ticket-summary-item .ticket-total { font-weight: 600; color: #16a34a; }
.seats-display { font-size: 0.95rem; font-weight: 500; color: #475569; word-break: break-word; }
@media (min-width: 1024px) { .card-summary { position: sticky; top: 100px; } }
@media (max-width: 768px) {
.payment-methods { grid-template-columns: 1fr 1fr; gap: 12px; }
.payment-method { padding: 18px 12px; }
.card-summary { padding: 18px; }
.timeout-warning {
top: 85px;
flex-direction: column;
align-items: center;
justify-content: center;
text-align: center;
gap: 6px;
padding: 16px 18px;
}
.timeout-warning .countdown { min-width: auto; }
}
</style>
<div class="container mx-auto px-2 sm:px-4 py-4 sm:py-8 max-w-7xl">
<div class="timeout-warning normal" id="timeoutWarning">
<div class="flex items-center gap-2">
<i class="fas fa-clock" id="timeoutIcon"></i>
<span>Tu sesión expirará en <span class="countdown" id="countdownTimer">10:00</span></span>
</div>
<span class="md:ml-auto text-xs sm:text-sm" id="timeoutStatus">Los asientos se liberarán automáticamente</span>
</div>

<div class="flex flex-col lg:flex-row gap-6">
<div class="flex-1 min-w-0">
<h2 class="text-2xl font-bold text-gray-800 mb-1">💳 Método de Pago</h2>
<p class="text-base text-gray-400 mb-6">Elige la forma de pago que prefieras para completar tu compra</p>
<form action="checkout.php" method="POST" id="paymentForm">
<input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
<input type="hidden" name="showtime_id" value="<?= $showtimeId ?>">
<input type="hidden" name="seats" value="<?= htmlspecialchars($seats) ?>">
<input type="hidden" name="payment_method" id="paymentMethodInput" value="">
<input type="hidden" name="food_order" id="foodOrderInput" value='<?= json_encode($foodOrder) ?>'>
<input type="hidden" name="purchase_token" value="<?= htmlspecialchars($token) ?>">
<!-- ✅ CORREGIDO: AGREGAR PRECIOS CALCULADOS POR EL SERVIDOR PARA VALIDACIÓN -->
<input type="hidden" name="client_subtotal" value="<?= $subtotalWithFood ?>">
<input type="hidden" name="client_tax" value="<?= $taxAmountWithFood ?>">
<input type="hidden" name="client_total" value="<?= $totalAmountWithFood ?>">
<!-- ✅ CORREGIDO: Se reemplazó onclick inline por data-attribute -->
<div class="payment-methods">
<div class="payment-method" id="method-movil" data-payment-method="movil">
<input type="radio" name="payment_method_radio" value="movil" id="radio-movil">
<span class="icon">📱</span>
<div class="name">Pago Móvil</div>
<div class="description">Paga desde tu teléfono</div>
</div>
<div class="payment-method" id="method-tarjeta" data-payment-method="tarjeta">
<input type="radio" name="payment_method_radio" value="tarjeta" id="radio-tarjeta">
<span class="icon">💳</span>
<div class="name">Tarjeta</div>
<div class="description">Crédito / Débito</div>
</div>
</div>
<div class="payment-details" id="details-movil">
<div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 mb-4">
<p class="text-sm text-indigo-900 font-medium"><i class="fas fa-info-circle mr-2 text-indigo-600"></i>Para realizar el pago móvil, transfiere el monto total a la siguiente cuenta:</p>
<div class="mt-2 text-sm text-slate-700 space-y-1">
<p><strong class="text-slate-900">Banco:</strong> Banco de Venezuela</p>
<p><strong class="text-slate-900">Titular:</strong> Cinema Pro C.A.</p>
<p><strong class="text-slate-900">Cédula:</strong> J-12345678-9</p>
<p><strong class="text-slate-900">Cuenta:</strong> 0102-0123-45-1234567890</p>
</div>
</div>
<label for="movil_reference">Número de Referencia *</label>
<input type="text" id="movil_reference" name="movil_reference" placeholder="Ej: 1234567890" value="123456" required>
<label for="movil_phone">Teléfono de Origen *</label>
<input type="text" id="movil_phone" name="movil_phone" placeholder="0412-1234567" value="0412-1234567" required>
</div>
<div class="payment-details" id="details-tarjeta">
<label for="card_number">Número de Tarjeta *</label>
<input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" value="1234 5678 9012 3456" maxlength="19" required>
<div class="grid grid-cols-2 gap-4">
<div>
<label for="card_expiry">Fecha de Expiración *</label>
<input type="text" id="card_expiry" name="card_expiry" placeholder="MM/AA" value="12/25" maxlength="5" required>
</div>
<div>
<label for="card_cvv">CVV *</label>
<input type="password" id="card_cvv" name="card_cvv" placeholder="123" value="123" maxlength="4" required>
</div>
</div>
<label for="card_holder">Nombre del Titular *</label>
<input type="text" id="card_holder" name="card_holder" placeholder="Como aparece en la tarjeta" value="Cliente Prueba" required>
</div>
<button type="submit" class="btn-pay" id="btnPay" disabled>
<i class="fas fa-lock mr-2"></i> Pagar <?= formatCurrency($totalAmountWithFood, $siteConfig) ?>
</button>
</form>
</div>
<div class="w-full lg:w-96 card-summary">
<div class="flex gap-3 mb-5 items-start bg-slate-50 border border-slate-200 rounded-xl p-2.5 px-3">
<img src="<?= htmlspecialchars($showtime['poster_url'] ?? '') ?>" alt="<?= htmlspecialchars($showtime['title'] ?? '') ?>" class="summary-movie-poster" onerror="this.style.display='none'">
<div class="flex flex-col justify-start text-left text-gray-900 flex-1 min-w-0">
<div class="font-extrabold text-lg leading-tight text-gray-900 summary-movie-title"><?= htmlspecialchars($showtime['title'] ?? '') ?></div>
<div class="text-sm text-gray-700 font-medium mt-1.5">Idioma: <?= htmlspecialchars($lang_label) ?></div>
<div class="text-sm text-gray-700 font-medium mt-1 whitespace-nowrap"><?= htmlspecialchars($showtime['room_name'] ?? '') ?> · <?= formatDateShort($showtime['show_date'] ?? '') ?> · <?= formatTimeVenezuela($showtime['show_time'] ?? '') ?></div>
<div class="mt-1.5"><span class="format-badge"><?= htmlspecialchars($format) ?></span></div>
<div class="flex flex-col gap-2 mt-3 items-start">
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
<?php if (!empty($foodItems)): ?>
<div class="mb-3">
<p class="text-xs font-semibold uppercase mb-1" style="color: #4f46e5;">🍿 Comida</p>
<?php foreach ($foodItems as $item): ?>
<div class="cart-item"><span class="item-name"><?= $item['quantity'] ?> x <?= htmlspecialchars($item['name']) ?></span><span class="item-price"><?= formatCurrency($item['total'], $siteConfig) ?></span></div>
<?php endforeach; ?>
</div>
<?php else: ?>
<div class="mb-3">
<p class="text-xs font-semibold uppercase mb-1" style="color: #4f46e5;">🍿 Comida</p>
<p class="text-sm text-gray-500">No has seleccionado comida</p>
</div>
<?php endif; ?>
<div class="summary-dotted-line"></div>
<div class="summary-plain-row"><span>Subtotal</span><span id="subtotalAmount"><?= formatCurrency($subtotalWithFood, $siteConfig) ?></span></div>
<div class="summary-plain-row"><span>IVA (<?= $taxRate ?>%)</span><span id="taxAmount"><?= formatCurrency($taxAmountWithFood, $siteConfig) ?></span></div>
<div class="summary-solid-line"></div>
<div class="summary-plain-row bold-row"><span>Total a Pagar</span><span id="totalAmount"><?= formatCurrency($totalAmountWithFood, $siteConfig) ?></span></div>
<div class="flex flex-col gap-2.5 mt-6">
<div class="text-xs text-gray-500 text-center font-medium"><i class="fas fa-shield-alt text-green-600 mr-1"></i> Pago seguro y encriptado</div>
<a href="<?= $backUrl ?>&from=payment" class="btn-back"><i class="fas fa-arrow-left mr-2"></i> Volver a Comida</a>
</div>
</div>
</div>
</div>
<?php require_once 'footer.php'; ?>

<script src="timeout_manager.js"></script>

<script nonce="<?= htmlspecialchars($cspNonce ?? '') ?>">
const showtimeId = <?= $showtimeId ?>;
const seats = '<?= $seats ?>';
const purchaseToken = '<?= htmlspecialchars($token) ?>';
let selectedPayment = null;
// ✅ Bandera para evitar liberar asientos al pagar
let skipUnloadRelease = false;
document.addEventListener('DOMContentLoaded', function() {
if (window.TimeoutManager) {
TimeoutManager.init({
showtimeId: showtimeId,
seats: seats,
initialTimeout: 600,
syncInterval: 10000,
redirectOnExpire: true,
redirectUrl: 'index.php?timeout=1'
});
}
// ============================================
// ✅ CORREGIDO: Event listeners para métodos de pago
// Reemplaza los onclick inline que eran bloqueados por CSP
// ============================================
document.querySelectorAll('.payment-method[data-payment-method]').forEach(function(methodCard) {
methodCard.addEventListener('click', function() {
const method = this.getAttribute('data-payment-method');
if (method) {
selectPayment(method);
}
});
});
// Listener para el submit del formulario de pago
const paymentForm = document.getElementById('paymentForm');
if (paymentForm) {
paymentForm.addEventListener('submit', function(e) {
if (!selectedPayment) {
e.preventDefault();
alert('Por favor, selecciona un método de pago.');
return false;
}
// Activar bandera para evitar liberar asientos al pagar
skipUnloadRelease = true;
return true;
});
}
});
function selectPayment(method) {
selectedPayment = method;
const paymentMethodInput = document.getElementById('paymentMethodInput');
if (paymentMethodInput) {
paymentMethodInput.value = method;
}
// Remover selección de todos los métodos
document.querySelectorAll('.payment-method').forEach(function(el) {
el.classList.remove('selected');
});
// Seleccionar el método elegido
const selectedMethod = document.getElementById('method-' + method);
if (selectedMethod) {
selectedMethod.classList.add('selected');
}
// Ocultar todos los detalles de pago
document.querySelectorAll('.payment-details').forEach(function(el) {
el.classList.remove('active');
});
// Mostrar los detalles del método seleccionado
const selectedDetails = document.getElementById('details-' + method);
if (selectedDetails) {
selectedDetails.classList.add('active');
}
// Habilitar botón de pago
const btnPay = document.getElementById('btnPay');
if (btnPay) {
btnPay.disabled = false;
}
}
</script>
</body>
</html>