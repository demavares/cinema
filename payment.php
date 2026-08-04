<?php
require_once 'config.php';

// ============================================
// PREVENIR CACHÉ DEL NAVEGADOR
// ============================================
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// ============================================
// VERIFICAR QUE LA SESIÓN SEA VÁLIDA
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
// ✅ LEER ASIENTOS DESDE SESIÓN (SEGURO)
// ============================================
$seats = isset($_SESSION['food_seats_' . $showtimeId])
    ? $_SESSION['food_seats_' . $showtimeId]
    : '';

if (empty($seats)) {
    header('Location: index.php');
    exit;
}

// ============================================
// VERIFICAR SESIÓN DE COMIDA
// ============================================
$sessionKey = 'food_timeout_' . $showtimeId;
$sessionSeatsKey = 'food_seats_' . $showtimeId;
$sessionValidKey = 'food_valid_' . $showtimeId;
$sessionFoodKey = 'food_order_' . $showtimeId;

// Si la sesión no es válida para este showtime_id, redirigir a seats
if (!isset($_SESSION[$sessionValidKey]) || $_SESSION[$sessionValidKey] !== true) {
    unset($_SESSION[$sessionKey]);
    unset($_SESSION[$sessionSeatsKey]);
    unset($_SESSION[$sessionValidKey]);
    unset($_SESSION[$sessionFoodKey]);
    session_write_close();
    header('Location: seats.php?showtime_id=' . $showtimeId . '&error=session_expired');
    exit;
}

// Verificar que los asientos coincidan
if (!isset($_SESSION[$sessionSeatsKey]) || $_SESSION[$sessionSeatsKey] !== $seats) {
    $_SESSION[$sessionSeatsKey] = $seats;
}

// Verificar si el timeout expiró
if (isset($_SESSION[$sessionKey]) && $_SESSION[$sessionKey] <= 0) {
    unset($_SESSION[$sessionKey]);
    unset($_SESSION[$sessionSeatsKey]);
    unset($_SESSION[$sessionValidKey]);
    unset($_SESSION[$sessionFoodKey]);
    session_write_close();
    header('Location: index.php?timeout=1');
    exit;
}

// Si no hay timeout, crear uno
if (!isset($_SESSION[$sessionKey])) {
    $_SESSION[$sessionKey] = 600;
}

$foodOrder = isset($_SESSION[$sessionFoodKey]) ? json_decode($_SESSION[$sessionFoodKey], true) : [];

// Obtener datos del showtime
$stmt = $pdo->prepare("
    SELECT s.*, m.id as movie_id, m.title, m.poster_url, m.duration
    FROM showtimes s
    JOIN movies m ON s.movie_id = m.id
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
$finalPrice = getShowtimePrice($showtime);
$totalTicketsPrice = $ticketCount * $finalPrice;

// ============================================
// IDIOMA DE LA PELÍCULA
// ============================================
$language = $showtime['language'] ?? 'español';
$languageLabel = $language == 'español' ? 'Español' : 'Subtítulos en Español';

// Calcular total de comida
$totalFoodPrice = 0;
$foodItems = [];

if (!empty($foodOrder)) {
    $foodIds = array_column($foodOrder, 'id');
    if (!empty($foodIds)) {
        $placeholders = implode(',', array_fill(0, count($foodIds), '?'));
        $stmt = $pdo->prepare("SELECT * FROM food_items WHERE id IN ($placeholders)");
        $stmt->execute($foodIds);
        $availableFood = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($availableFood as $item) {
            foreach ($foodOrder as $order) {
                if ($order['id'] == $item['id']) {
                    $qty = intval($order['quantity']);
                    if ($qty > 0) {
                        $foodItems[] = [
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

$totalAmount = $totalTicketsPrice + $totalFoodPrice;

$csrf_token = generateCSRFToken();
$siteConfig = getSiteConfig($pdo);
$pageTitle = "Método de Pago - " . $showtime['title'];

// ============================================
// ✅ CORRECCIÓN: Enlace de regreso con parámetro from=payment
// ============================================
$backUrl = 'food_menu.php?showtime_id=' . $showtimeId . '&from=payment';

$currency_symbol = $siteConfig['currency_symbol'] ?? '$';
$currency_position = $siteConfig['currency_position'] ?? 'left';
$thousands_separator = $siteConfig['thousands_separator'] ?? '.';
$decimal_separator = $siteConfig['decimal_separator'] ?? ',';
$decimal_places = intval($siteConfig['decimal_places'] ?? 2);

$isTestMode = true;

// Crear token de sesión única para esta compra si no existe
if (!isset($_SESSION['purchase_token_' . $showtimeId])) {
    $_SESSION['purchase_token_' . $showtimeId] = bin2hex(random_bytes(32));
}

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

.payment-methods { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 20px; }

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

.payment-method:hover { border-color: #6366f1; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
.payment-method.selected { border-color: #4f46e5; background: #f5f3ff; box-shadow: 0 0 20px rgba(99, 102, 241, 0.15); }
.payment-method .icon { font-size: 2.5rem; margin-bottom: 8px; display: block; }
.payment-method .name { color: #0f172a; font-weight: 700; font-size: 1.1rem; }
.payment-method .description { color: #475569; font-size: 0.9rem; margin-top: 4px; }
.payment-method input[type="radio"] { display: none; }

.payment-details { display: none; margin-top: 20px; padding: 20px; background: #f8fafc; border-radius: 12px; border: 1px solid #cbd5e1; }
.payment-details.active { display: block; }
.payment-details label { color: #334155; font-size: 0.95rem; font-weight: 600; display: block; margin-bottom: 6px; }
.payment-details input, .payment-details select {
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
.payment-details input:focus, .payment-details select:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }

.test-mode-badge {
    display: inline-block;
    background: #fef3c7;
    color: #92400e;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    border: 1px solid #fde68a;
    margin-left: 8px;
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

.btn-pay:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(34, 197, 94, 0.3); }
.btn-pay:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; box-shadow: none !important; }

.summary-sticky {
    position: relative;
    top: auto;
    align-self: flex-start;
    max-height: none;
    overflow: visible;
    padding: 24px;
    box-sizing: border-box;
    background: #f8fafc !important;
    border: 1px solid #cbd5e1 !important;
    border-top: 4px solid #4f46e5 !important;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08) !important;
    border-radius: 12px !important;
}

@media (min-width: 1024px) {
    .summary-sticky {
        position: sticky;
        top: 100px;
    }
}

.selected-info-box {
    background: #f1f5f9 !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 8px !important;
    padding: 14px !important;
    margin-top: 10px;
    margin-bottom: 16px;
}

.ticket-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 4px 0;
    font-size: 0.95rem;
}

.ticket-line .ticket-label { color: #475569; }
.ticket-line .ticket-price { color: #16a34a; font-weight: 600; }

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

.order-total {
    border-top: 2px dashed #cbd5e1;
    padding-top: 12px;
    margin-top: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 1.25rem;
    font-weight: 700;
}

.order-total .total-label { color: #0f172a; }
.order-total .total-amount { color: #16a34a; }

.seats-display { font-size: 0.95rem; font-weight: 500; color: #475569; word-break: break-word; }

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

.movie-language {
    font-size: 0.9rem;
    color: #475569;
    margin-top: 2px;
    font-weight: 500;
}

.text-white { color: #0f172a !important; }
.text-gray-400 { color: #475569 !important; font-weight: 500; }

@media (max-width: 1024px) {
    .timeout-warning { top: 90px; }
}

@media (max-width: 768px) {
    .payment-methods { grid-template-columns: 1fr 1fr; gap: 12px; }
    .payment-method { padding: 18px 12px; }
    .summary-sticky { padding: 18px; }
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
    .payment-methods { grid-template-columns: 1fr 1fr; gap: 10px; }
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
        <!-- SECCIÓN IZQUIERDA: Selección de Método de Pago -->
        <div class="flex-1 min-w-0">
            <h2 class="text-2xl font-bold text-gray-800 mb-1">💳 Método de Pago</h2>
            <p class="text-base text-gray-400 mb-6">Elige la forma de pago que prefieras para completar tu compra</p>

            <form action="checkout.php" method="POST" id="paymentForm">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="showtime_id" value="<?= $showtimeId ?>">
                <input type="hidden" name="seats" value="<?= htmlspecialchars($seats) ?>">
                <input type="hidden" name="payment_method" id="paymentMethodInput" value="">
                <input type="hidden" name="food_order" id="foodOrderInput" value='<?= htmlspecialchars(json_encode($foodOrder)) ?>'>

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
                    <i class="fas fa-lock mr-2"></i> Pagar <?= formatCurrency($totalAmount, $siteConfig) ?>
                </button>
            </form>
        </div>

        <!-- SECCIÓN DERECHA: RESUMEN DEL PEDIDO -->
        <div class="w-full lg:w-80 summary-sticky">
            <h3 class="text-xl font-bold text-white mb-3 flex items-center gap-2">
                <i class="fas fa-receipt text-indigo-600"></i> Resumen del Pedido
            </h3>

            <div class="selected-info-box">
                <!-- Película -->
                <div class="mb-3">
                    <p class="text-xs text-gray-400 font-semibold uppercase">🎬 Película</p>
                    <div class="text-slate-900 font-bold text-base"><?= htmlspecialchars($showtime['title']) ?></div>
                    <div class="movie-language"><?= htmlspecialchars($languageLabel) ?></div>
                    <div class="text-sm text-gray-500 mt-0.5">
                        <?= formatDateShort($showtime['show_date']) ?> · <?= formatTimeVenezuela($showtime['show_time']) ?>
                    </div>
                </div>

                <!-- Boletos -->
                <div>
                    <p class="text-xs text-gray-400 font-semibold uppercase mb-1">🎫 Boletos</p>
                    <div class="ticket-line">
                        <span class="ticket-label text-sm"><?= $ticketCount ?> x <?= formatCurrency($finalPrice, $siteConfig) ?></span>
                        <span class="ticket-price text-sm"><?= formatCurrency($totalTicketsPrice, $siteConfig) ?></span>
                    </div>
                    <div class="seats-display mt-1">
                        Asientos: <span class="font-bold text-slate-800"><?= htmlspecialchars(implode(', ', $seatsArray)) ?></span>
                    </div>
                </div>
            </div>

            <!-- Comida -->
            <?php if (!empty($foodItems)): ?>
            <div class="mb-4">
                <p class="text-xs text-gray-400 font-semibold uppercase mb-2">🍿 Comida Añadida</p>
                <?php foreach ($foodItems as $item): ?>
                <div class="cart-item">
                    <span class="item-name"><?= $item['quantity'] ?> x <?= htmlspecialchars($item['name']) ?></span>
                    <span class="item-price"><?= formatCurrency($item['total'], $siteConfig) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Total Final -->
            <div class="order-total">
                <span class="total-label">Total a Pagar</span>
                <span class="total-amount"><?= formatCurrency($totalAmount, $siteConfig) ?></span>
            </div>

            <!-- Botones de Acción -->
            <div class="flex flex-col gap-2.5 mt-5">
                <div class="text-xs text-gray-500 text-center font-medium">
                    <i class="fas fa-shield-alt text-green-600 mr-1"></i> Pago seguro y encriptado
                </div>
                <!-- ✅ CORRECCIÓN: Enlace con parámetro from=payment para navegación correcta -->
                <a href="food_menu.php?showtime_id=<?= $showtimeId ?>&from=payment" class="btn-back">
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

    // Actualizar UI
    document.querySelectorAll('.payment-method').forEach(el => {
        el.classList.remove('selected');
    });
    document.getElementById('method-' + method).classList.add('selected');

    // Marcar radio
    document.getElementById('radio-' + method).checked = true;

    // Mostrar detalles
    document.querySelectorAll('.payment-details').forEach(el => {
        el.classList.remove('active');
    });
    document.getElementById('details-' + method).classList.add('active');

    // Habilitar botón de pago
    document.getElementById('btnPay').disabled = false;
}

// ============================================
// VALIDAR FORMULARIO DE PAGO
// ============================================
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    if (!selectedPayment) {
        e.preventDefault();
        showNotification('Por favor, selecciona un método de pago.', 'warning');
        return false;
    }

    // Validar campos según método
    if (selectedPayment === 'movil') {
        const reference = document.getElementById('movil_reference').value.trim();
        const phone = document.getElementById('movil_phone').value.trim();

        if (!reference || !phone) {
            e.preventDefault();
            showNotification('Por favor, completa todos los campos de Pago Móvil.', 'warning');
            return false;
        }

        if (reference.length < 4) {
            e.preventDefault();
            showNotification('La referencia debe tener al menos 4 dígitos.', 'warning');
            return false;
        }
    } else if (selectedPayment === 'tarjeta') {
        const cardNumber = document.getElementById('card_number').value.replace(/\s/g, '');
        const expiry = document.getElementById('card_expiry').value;
        const cvv = document.getElementById('card_cvv').value;
        const holder = document.getElementById('card_holder').value.trim();

        if (!cardNumber || !expiry || !cvv || !holder) {
            e.preventDefault();
            showNotification('Por favor, completa todos los campos de la tarjeta.', 'warning');
            return false;
        }

        if (cardNumber.length < 16) {
            e.preventDefault();
            showNotification('Número de tarjeta inválido.', 'warning');
            return false;
        }

        if (!/^\d{2}\/\d{2}$/.test(expiry)) {
            e.preventDefault();
            showNotification('Formato de fecha inválido. Usa MM/AA.', 'warning');
            return false;
        }

        if (cvv.length < 3) {
            e.preventDefault();
            showNotification('CVV inválido.', 'warning');
            return false;
        }
    }

    return true;
});

// ============================================
// NOTIFICACIONES
// ============================================
function showNotification(message, type = 'info') {
    const colors = {
        info: 'bg-blue-600',
        success: 'bg-green-600',
        warning: 'bg-yellow-600',
        error: 'bg-red-600'
    };

    const icons = {
        info: 'fa-info-circle',
        success: 'fa-check-circle',
        warning: 'fa-exclamation-triangle',
        error: 'fa-times-circle'
    };

    const notification = document.createElement('div');
    notification.className = `fixed bottom-4 left-1/2 transform -translate-x-1/2 ${colors[type] || 'bg-gray-600'} text-white px-4 sm:px-6 py-2 sm:py-3 rounded-lg shadow-lg z-50 transition-all duration-300 max-w-[90%] sm:max-w-md text-center text-sm flex items-center gap-3`;
    notification.innerHTML = `
        <i class="fas ${icons[type] || 'fa-info-circle'}"></i>
        <span>${message}</span>
    `;
    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translate(-50%, 20px)';
        setTimeout(() => notification.remove(), 300);
    }, 3500);
}
</script>
</body>
</html>