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
// ============================================
// ✅ NUEVO: VERIFICAR Y RESTAURAR LA RESERVA EN BD (MODO GRACIA)
// Si venimos de un unload (gracia de 20 s), de un F5 o de "Volver a Comida",
// la compra pending aún existe y la extendemos de nuevo a 10 minutos.
// Si ya no existe, regresamos a comida (que a su vez redirige a asientos).
// ============================================
$stmtPending = $pdo->prepare("
    SELECT id FROM purchases
    WHERE user_id = ? AND showtime_id = ? AND status = 'pending' AND expires_at > NOW()
");
$stmtPending->execute([$_SESSION['user_id'], $showtimeId]);
$pendingPurchase = $stmtPending->fetch();
if (!$pendingPurchase) {
    error_log("⚠️ payment.php - Reserva no vigente para showtime $showtimeId. Regresando a comida.");
    header('Location: food_menu.php?showtime_id=' . $showtimeId . '&from=payment');
    exit;
}
// ✅ Cancela la gracia y restaura la reserva (10 min)
try {
    $stmtRestore = $pdo->prepare("UPDATE purchases SET expires_at = DATE_ADD(NOW(), INTERVAL 600 SECOND) WHERE id = ?");
    $stmtRestore->execute([$pendingPurchase['id']]);
} catch (Exception $e) {
    error_log("⚠️ payment.php: Error extendiendo reserva: " . $e->getMessage());
}
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
// ✅ BLOQUEO: se permite comprar hasta 15 minutos después del inicio de la función
// ============================================
$showtimeDateTime = strtotime($showtime['show_date'] . ' ' . $showtime['show_time']);
$currentDateTime = time();
$safetyMargin = 15 * 60;
if (($showtimeDateTime + $safetyMargin) < $currentDateTime) {
    error_log("⛔ payment.php: Función iniciada hace más de 15 minutos");
    header('Location: index.php?error=Este+horario+ya+no+está+disponible');
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
<link rel="stylesheet" href="assets/css/shared-panel.css">
<link rel="stylesheet" href="assets/css/payment.css">
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
<script src="assets/js/timeout_manager.js"></script>
<script nonce="<?= htmlspecialchars($cspNonce ?? '') ?>">
const showtimeId = <?= $showtimeId ?>;
const seats = '<?= $seats ?>';
const purchaseToken = '<?= htmlspecialchars($token) ?>';</script>
<script src="assets/js/payment.js"></script>
</body>
</html>