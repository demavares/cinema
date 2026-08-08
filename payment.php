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
// ✅ VALIDAR TOKEN DE COMPRA DESDE SESIÓN
// ============================================
$purchaseToken = $_SESSION['purchase_token_' . $showtimeId] ?? '';

if (empty($purchaseToken) || !verifyPurchaseTokenWithTimeout($purchaseToken, $showtimeId)) {
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Token+inválido+o+expirado');
    exit;
}

// ============================================
// VERIFICAR SESIÓN DE COMIDA
// ============================================
$sessionValidKey = 'food_valid_' . $showtimeId;
$sessionSeatsKey = 'food_seats_' . $showtimeId;
$sessionTimeoutKey = 'food_timeout_' . $showtimeId;
$sessionFoodKey = 'food_order_' . $showtimeId;

if (!isset($_SESSION[$sessionValidKey]) || $_SESSION[$sessionValidKey] !== true) {
    unset($_SESSION[$sessionTimeoutKey]);
    unset($_SESSION[$sessionSeatsKey]);
    unset($_SESSION[$sessionValidKey]);
    unset($_SESSION[$sessionFoodKey]);
    header('Location: seats.php?showtime_id=' . $showtimeId . '&error=session_expired');
    exit;
}

if (isset($_SESSION[$sessionTimeoutKey]) && $_SESSION[$sessionTimeoutKey] <= 0) {
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

// ============================================
// ✅ LEER ASIENTOS DESDE SESIÓN (SEGURO)
// ============================================
$seats = isset($_SESSION[$sessionSeatsKey]) ? $_SESSION[$sessionSeatsKey] : '';
if (empty($seats)) {
    header('Location: seats.php?showtime_id=' . $showtimeId . '&error=no_seats');
    exit;
}

// ============================================
// ✅ LEER PEDIDO DE COMIDA DESDE SESIÓN
// ============================================
$foodOrder = isset($_SESSION[$sessionFoodKey]) ? json_decode($_SESSION[$sessionFoodKey], true) : [];

// ============================================
// OBTENER DATOS DEL SHOWTIME
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

$seatsArray = explode(',', $seats);
$ticketCount = count($seatsArray);

// ============================================
// ✅ OBTENER DATOS DE BOLETOS DESDE LA SESIÓN
// ============================================
$ticketsData = isset($_SESSION['ticket_quantities_' . $showtimeId]) 
    ? $_SESSION['ticket_quantities_' . $showtimeId] 
    : null;

// ============================================
// ✅ CALCULAR SUBTOTAL DE BOLETOS (SIN COMIDA) - BASE SUBTOTAL
// ============================================
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

$baseSubtotal = 0;
if ($ticketsData) {
    $baseSubtotal = (intval($ticketsData['adult'] ?? 0) * $priceAdult) +
                    (intval($ticketsData['child'] ?? 0) * $priceChild) +
                    (intval($ticketsData['senior'] ?? 0) * $priceSenior);
} else {
    // Fallback: usar el precio base por boleto
    $basePrice = getShowtimePrice($showtime);
    $baseSubtotal = $ticketCount * $basePrice;
}

// Obtener tasa de IVA
$stmt = $pdo->query("SELECT tax_rate FROM tax_config WHERE is_active = 1 LIMIT 1");
$tax = $stmt->fetch();
$taxRate = $tax ? floatval($tax['tax_rate']) : 16;

// ============================================
// ✅ PROCESAR COMIDA DESDE SESIÓN
// ============================================
$totalFoodPrice = 0;
$foodItems = [];

if (!empty($foodOrder)) {
    $foodIds = array_column($foodOrder, 'id');
    if (!empty($foodIds)) {
        $placeholders = implode(',', array_fill(0, count($foodIds), '?'));
        $stmt = $pdo->prepare("SELECT * FROM food_items WHERE id IN ($placeholders) AND is_active = 1");
        $stmt->execute($foodIds);
        $availableFood = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
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

// ============================================
// ✅ RECALCULAR TOTALES: baseSubtotal + comida
// ============================================
$subtotalWithFood = $baseSubtotal + $totalFoodPrice;
$taxAmountWithFood = $subtotalWithFood * ($taxRate / 100);
$totalAmountWithFood = $subtotalWithFood + $taxAmountWithFood;

// Guardar en sesión para usar en checkout
$_SESSION['subtotal_' . $showtimeId] = $subtotalWithFood;
$_SESSION['tax_amount_' . $showtimeId] = $taxAmountWithFood;
$_SESSION['total_amount_' . $showtimeId] = $totalAmountWithFood;
$_SESSION['tax_rate_' . $showtimeId] = $taxRate;
$_SESSION['ticket_quantities_' . $showtimeId] = $ticketsData;
$_SESSION['total_seats_' . $showtimeId] = $ticketCount;

$language = $showtime['language'] ?? 'español';
$languageLabel = $language == 'español' ? 'Español' : 'Subtítulos en Español';

$csrf_token = generateCSRFToken();
$siteConfig = getSiteConfig($pdo);
$pageTitle = "Método de Pago - " . $showtime['title'];
$backUrl = 'food_menu.php?showtime_id=' . $showtimeId;

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

// Asegurar que el token existe en sesión y está vigente
if (!isset($_SESSION['purchase_token_' . $showtimeId])) {
    $_SESSION['purchase_token_' . $showtimeId] = generatePurchaseTokenWithTimeout($showtimeId, 900);
}
$purchaseToken = $_SESSION['purchase_token_' . $showtimeId];

// ✅ Preparar el JSON de foodOrder para el formulario
$foodOrderJson = json_encode($foodOrder);
$foodOrderEscaped = htmlspecialchars($foodOrderJson, ENT_QUOTES, 'UTF-8');

require_once 'header.php';
?>

<style>
/* Configuración Global - Fondo blanco y texto oscuro */
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

/* Timeout Warning */
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

/* ============================================
   CARD SUMMARY - MISMO ESTILO QUE PRICE_SELECTION
   ============================================ */
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

/* ✅ PROMOCIONES - BADGES BORDER AND DOT */
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

/* FORMATO BADGE */
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

/* Payment Methods */
.payment-methods {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-top: 20px;
}
.payment-method {
    background: #ffffff;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 24px 20px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.payment-method:hover {
    border-color: #6366f1;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.08);
}
.payment-method.selected {
    border-color: #4f46e5;
    background: #f5f3ff;
    box-shadow: 0 0 20px rgba(99, 102, 241, 0.15);
}
.payment-method .icon {
    font-size: 2.5rem;
    margin-bottom: 8px;
    display: block;
}
.payment-method .name {
    color: #0f172a;
    font-weight: 700;
    font-size: 1.1rem;
}
.payment-method .description {
    color: #475569;
    font-size: 0.9rem;
    margin-top: 4px;
}
.payment-method input[type="radio"] {
    display: none;
}

.payment-details {
    display: none;
    margin-top: 20px;
    padding: 20px;
    background: #f8fafc;
    border-radius: 12px;
    border: 1px solid #cbd5e1;
}
.payment-details.active {
    display: block;
}
.payment-details label {
    color: #334155;
    font-size: 0.95rem;
    font-weight: 600;
    display: block;
    margin-bottom: 6px;
}
.payment-details input,
.payment-details select {
    width: 100%;
    padding: 12px 14px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    color: #0f172a;
    font-size: 1rem;
    margin-bottom: 16px;
    transition: border-color 0.3s ease;
}
.payment-details input:focus,
.payment-details select:focus {
    outline: none;
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.btn-pay {
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: white;
    padding: 14px 30px;
    border-radius: 8px;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 100%;
    font-size: 1.15rem;
    margin-top: 24px;
}
.btn-pay:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(34, 197, 94, 0.3);
}
.btn-pay:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none !important;
    box-shadow: none !important;
}

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
.btn-back:hover {
    border-color: #6366f1;
    color: #4f46e5 !important;
    background: #eef2ff;
}

.cart-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 0;
    border-bottom: 1px solid #e2e8f0;
    font-size: 0.95rem;
}
.cart-item:last-child {
    border-bottom: none;
}
.cart-item .item-name {
    color: #1e293b;
    flex: 1;
    word-break: break-word;
    font-weight: 500;
}
.cart-item .item-price {
    color: #16a34a;
    font-weight: 600;
    font-size: 0.95rem;
}

/* Ticket summary items */
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
    .timeout-warning {
        top: 90px;
    }
}
@media (max-width: 768px) {
    .payment-methods {
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    .payment-method {
        padding: 18px 12px;
    }
    .card-summary {
        padding: 18px;
        position: relative;
        top: auto;
    }
    .timeout-warning {
        top: 85px;
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 6px;
        padding: 16px 18px;
        margin-top: 12px;
    }
    .timeout-warning .ml-auto {
        margin-left: 0 !important;
    }
}
@media (max-width: 480px) {
    .payment-methods {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    .timeout-warning {
        top: 75px;
        padding: 14px 16px;
    }
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
        <!-- SECCIÓN IZQUIERDA: Selección de Método de Pago -->
        <div class="flex-1 min-w-0">
            <h2 class="text-2xl font-bold text-gray-800 mb-1">💳 Método de Pago</h2>
            <p class="text-base text-gray-400 mb-6">Elige la forma de pago que prefieras para completar tu compra</p>

            <form action="checkout.php" method="POST" id="paymentForm">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="showtime_id" value="<?= $showtimeId ?>">
                <input type="hidden" name="seats" value="<?= htmlspecialchars($seats) ?>">
                <input type="hidden" name="payment_method" id="paymentMethodInput" value="">
                <input type="hidden" name="food_order" id="foodOrderInput" value='<?= $foodOrderEscaped ?>'>
                <input type="hidden" name="purchase_token" value="<?= htmlspecialchars($purchaseToken) ?>">

                <div class="payment-methods">
                    <!-- Pago Móvil -->
                    <div class="payment-method" onclick="selectPayment('movil')" id="method-movil">
                        <input type="radio" name="payment_method_radio" value="movil" id="radio-movil">
                        <span class="icon">📱</span>
                        <div class="name">Pago Móvil</div>
                        <div class="description">Paga desde tu teléfono</div>
                    </div>

                    <!-- Tarjeta de Crédito/Débito -->
                    <div class="payment-method" onclick="selectPayment('tarjeta')" id="method-tarjeta">
                        <input type="radio" name="payment_method_radio" value="tarjeta" id="radio-tarjeta">
                        <span class="icon">💳</span>
                        <div class="name">Tarjeta</div>
                        <div class="description">Crédito / Débito</div>
                    </div>
                </div>

                <!-- Detalles de Pago Móvil -->
                <div class="payment-details" id="details-movil">
                    <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 mb-4">
                        <p class="text-sm text-indigo-900 font-medium">
                            <i class="fas fa-info-circle mr-2 text-indigo-600"></i>
                            Para realizar el pago móvil, transfiere el monto total a la siguiente cuenta:
                        </p>
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

                <!-- Detalles de Tarjeta -->
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

        <!-- CARD SUMMARY - MISMO ESTILO QUE PRICE_SELECTION Y FOOD_MENU -->
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

                    <!-- ✅ IDIOMA - TEXTO PLANO -->
                    <div class="text-sm text-gray-700 font-medium mt-1.5">
                        Idioma: <?= htmlspecialchars($languageLabel) ?>
                    </div>

                    <!-- Sala · Fecha · Hora -->
                    <div class="text-sm text-gray-700 font-medium mt-1 whitespace-nowrap">
                        <?= htmlspecialchars($showtime['room_name'] ?? 'Sala no disponible') ?> · 
                        <?= formatDateShort($showtime['show_date'] ?? '') ?> · 
                        <?= formatTimeVenezuela($showtime['show_time'] ?? '') ?>
                    </div>

                    <!-- FORMATO -->
                    <div class="mt-1.5">
                        <span class="format-badge <?= $formatClass ?>"><?= htmlspecialchars($movieFormat) ?></span>
                    </div>

                    <!-- ✅ PROMOCIONES - BADGES BORDER AND DOT -->
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

            <!-- ✅ RESUMEN DE BOLETOS POR TIPO -->
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

            <!-- ✅ COMIDA SELECCIONADA -->
            <?php if (!empty($foodItems)): ?>
            <div class="mb-3">
                <p class="text-xs text-gray-400 font-semibold uppercase mb-1">🍿 Comida</p>
                <?php foreach ($foodItems as $item): ?>
                <div class="cart-item">
                    <span class="item-name"><?= $item['quantity'] ?> x <?= htmlspecialchars($item['name']) ?></span>
                    <span class="item-price"><?= formatCurrency($item['total'], $siteConfig) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="mb-3">
                <p class="text-xs text-gray-400 font-semibold uppercase mb-1">🍿 Comida</p>
                <p class="text-sm text-gray-500">No has seleccionado comida</p>
            </div>
            <?php endif; ?>

            <!-- LÍNEA PUNTEADA -->
            <div class="summary-dotted-line"></div>

            <!-- CÁLCULOS SUBTOTAL E IVA -->
            <div class="summary-plain-row">
                <span>Subtotal</span>
                <span id="subtotalAmount"><?= formatCurrency($subtotalWithFood, $siteConfig) ?></span>
            </div>
            <div class="summary-plain-row">
                <span>IVA (<?= $taxRate ?>%)</span>
                <span id="taxAmount"><?= formatCurrency($taxAmountWithFood, $siteConfig) ?></span>
            </div>

            <!-- LÍNEA SOLIDA MORADA -->
            <div class="summary-solid-line"></div>

            <!-- TOTAL -->
            <div class="summary-plain-row bold-row">
                <span>Total a Pagar</span>
                <span id="totalAmount"><?= formatCurrency($totalAmountWithFood, $siteConfig) ?></span>
            </div>

            <div class="flex flex-col gap-2.5 mt-6">
                <div class="text-xs text-gray-500 text-center font-medium">
                    <i class="fas fa-shield-alt text-green-600 mr-1"></i> Pago seguro y encriptado
                </div>
				<a href="<?= $backUrl ?>&from=payment" class="btn-back">
					<i class="fas fa-arrow-left mr-2"></i> Volver a Comida
				</a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>

<!-- ============================================ -->
<!-- TIMEOUT MANAGER                              -->
<!-- ============================================ -->
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
// CONFIGURACIÓN DE MONEDA
// ============================================
const currencyConfig = {
    symbol: '<?= $currency_symbol ?>',
    position: '<?= $currency_position ?>',
    thousands: '<?= $thousands_separator ?>',
    decimal: '<?= $decimal_separator ?>',
    decimals: <?= $decimal_places ?>
};

// ============================================
// SELECCIÓN DE MÉTODO DE PAGO
// ============================================
let selectedPayment = null;

function selectPayment(method) {
    selectedPayment = method;
    document.getElementById('paymentMethodInput').value = method;

    document.querySelectorAll('.payment-method').forEach(el => {
        el.classList.remove('selected');
    });
    document.getElementById('method-' + method).classList.add('selected');

    document.getElementById('radio-' + method).checked = true;

    document.querySelectorAll('.payment-details').forEach(el => {
        el.classList.remove('active');
    });
    document.getElementById('details-' + method).classList.add('active');

    document.getElementById('btnPay').disabled = false;
}

// ============================================
// VALIDAR FORMULARIO DE PAGO
// ============================================
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    if (!selectedPayment) {
        e.preventDefault();
        alert('Por favor, selecciona un método de pago.');
        return false;
    }

    if (selectedPayment === 'movil') {
        const reference = document.getElementById('movil_reference').value.trim();
        const phone = document.getElementById('movil_phone').value.trim();
        if (!reference || !phone) {
            e.preventDefault();
            alert('Por favor, completa todos los campos de Pago Móvil.');
            return false;
        }
        if (reference.length < 4) {
            e.preventDefault();
            alert('La referencia debe tener al menos 4 dígitos.');
            return false;
        }
    } else if (selectedPayment === 'tarjeta') {
        const cardNumber = document.getElementById('card_number').value.replace(/\s/g, '');
        const expiry = document.getElementById('card_expiry').value;
        const cvv = document.getElementById('card_cvv').value;
        const holder = document.getElementById('card_holder').value.trim();

        if (!cardNumber || !expiry || !cvv || !holder) {
            e.preventDefault();
            alert('Por favor, completa todos los campos de la tarjeta.');
            return false;
        }
        if (cardNumber.length < 16) {
            e.preventDefault();
            alert('Número de tarjeta inválido.');
            return false;
        }
        if (!/^\d{2}\/\d{2}$/.test(expiry)) {
            e.preventDefault();
            alert('Formato de fecha inválido. Usa MM/AA.');
            return false;
        }
        if (cvv.length < 3) {
            e.preventDefault();
            alert('CVV inválido.');
            return false;
        }
    }

    // ✅ Verificar que el token esté presente en el formulario
    const tokenInput = this.querySelector('input[name="purchase_token"]');
    if (!tokenInput || !tokenInput.value) {
        e.preventDefault();
        alert('Error de seguridad: Token de compra no encontrado.');
        return false;
    }

    console.log('✅ Formulario de pago enviado con token:', tokenInput.value);
    return true;
});
</script>
</body>
</html>