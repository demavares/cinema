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

// ============================================
// ✅ OBTENER SHOWTIME_ID DESDE SESIÓN O GET (CON VALIDACIÓN)
// ============================================
$showtimeId = isset($_GET['showtime_id']) ? intval($_GET['showtime_id']) : 0;
if ($showtimeId <= 0) {
    header('Location: index.php');
    exit;
}

// ============================================
// ✅ VALIDAR TOKEN DE COMPRA
// ============================================
$token = $_GET['token'] ?? '';
if (empty($token) || !verifyPurchaseToken($token, $showtimeId)) {
    // Verificar si hay sesión de comida válida como respaldo
    $foodValidKey = 'food_valid_' . $showtimeId;
    if (!isset($_SESSION[$foodValidKey]) || $_SESSION[$foodValidKey] !== true) {
        header('Location: price_selection.php?showtime_id=' . $showtimeId);
        exit;
    }
    // Regenerar token si es necesario
    $_SESSION['purchase_token_' . $showtimeId] = generatePurchaseToken();
    $token = $_SESSION['purchase_token_' . $showtimeId];
}

// ============================================
// ✅ LEER ASIENTOS DESDE SESIÓN (SEGURO)
// ============================================
$sessionSeatsKey = 'food_seats_' . $showtimeId;
$sessionValidKey = 'food_valid_' . $showtimeId;
$sessionTimeoutKey = 'food_timeout_' . $showtimeId;

// Verificar que la sesión de comida sea válida
if (!isset($_SESSION[$sessionValidKey]) || $_SESSION[$sessionValidKey] !== true) {
    unset($_SESSION[$sessionSeatsKey]);
    unset($_SESSION[$sessionValidKey]);
    unset($_SESSION[$sessionTimeoutKey]);
    header('Location: price_selection.php?showtime_id=' . $showtimeId);
    exit;
}

// Obtener asientos desde sesión (NO desde GET)
$seats = isset($_SESSION[$sessionSeatsKey]) ? $_SESSION[$sessionSeatsKey] : '';
if (empty($seats)) {
    // Intentar recuperar desde GET solo si hay sesión válida
    if (isset($_GET['seats']) && !empty($_GET['seats'])) {
        $seats = trim($_GET['seats']);
        $_SESSION[$sessionSeatsKey] = $seats;
    } else {
        header('Location: price_selection.php?showtime_id=' . $showtimeId);
        exit;
    }
}

// Verificar si el timeout expiró
if (isset($_SESSION[$sessionTimeoutKey]) && $_SESSION[$sessionTimeoutKey] <= 0) {
    unset($_SESSION[$sessionSeatsKey]);
    unset($_SESSION[$sessionValidKey]);
    unset($_SESSION[$sessionTimeoutKey]);
    header('Location: index.php?timeout=1');
    exit;
}

// Si no hay sesión de timeout, crear una
if (!isset($_SESSION[$sessionTimeoutKey])) {
    $_SESSION[$sessionTimeoutKey] = 600;
}

// ============================================
// OBTENER DATOS DEL SHOWTIME Y PELÍCULA
// ============================================
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

// ============================================
// ✅ RECALCULAR PRECIO EN EL SERVIDOR
// ============================================
$finalPrice = getShowtimePrice($showtime);
$totalTicketsPrice = $ticketCount * $finalPrice;

// Obtener tasa de IVA
$stmt = $pdo->query("SELECT tax_rate FROM tax_config WHERE is_active = 1 LIMIT 1");
$tax = $stmt->fetch();
$taxRate = $tax ? floatval($tax['tax_rate']) : 16;
$taxAmount = $totalTicketsPrice * ($taxRate / 100);
$totalAmount = $totalTicketsPrice + $taxAmount;

// Guardar en sesión para usar en payment
$_SESSION['total_tickets_price_' . $showtimeId] = $totalTicketsPrice;
$_SESSION['tax_rate_' . $showtimeId] = $taxRate;
$_SESSION['tax_amount_' . $showtimeId] = $taxAmount;
$_SESSION['total_amount_' . $showtimeId] = $totalAmount;

// ============================================
// IDIOMA DE LA PELÍCULA
// ============================================
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

$backFrom = isset($_GET['from']) ? $_GET['from'] : '';
$backUrl = 'seats.php?showtime_id=' . $showtimeId . '&from=' . $backFrom . '&token=' . $token;

$currency_symbol = $siteConfig['currency_symbol'] ?? '$';
$currency_position = $siteConfig['currency_position'] ?? 'left';
$thousands_separator = $siteConfig['thousands_separator'] ?? '.';
$decimal_separator = $siteConfig['decimal_separator'] ?? ',';
$decimal_places = intval($siteConfig['decimal_places'] ?? 2);

// Crear token de sesión única para esta compra si no existe
if (!isset($_SESSION['purchase_token_' . $showtimeId])) {
    $_SESSION['purchase_token_' . $showtimeId] = generatePurchaseToken();
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
.food-card .food-price { color: #16a34a; font-weight: 700; font-size: 1.2rem; whitespace: nowrap; }
.food-card .food-desc { color: #475569; font-size: 0.95rem; margin-top: 6px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; }
.food-card .quantity-controls { display: flex; align-items: center; justify-content: center; gap: 14px; margin-top: 12px; padding: 6px 0; }
.food-card .quantity-controls button { background: #f1f5f9; border: 1px solid #cbd5e1; color: #1e293b; width: 34px; height: 34px; border-radius: 50%; cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; font-weight: 700; }
.food-card .quantity-controls button:hover { background: #4f46e5; border-color: #4f46e5; color: #ffffff; }
.food-card .quantity-controls .qty { font-weight: 700; color: #0f172a; min-width: 24px; text-align: center; font-size: 1.1rem; }
.category-title { color: #334155; font-size: 1.15rem; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; margin: 24px 0 14px 0; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
.category-title i { margin-right: 8px; color: #4f46e5; }
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
.cart-item .item-details { display: flex; align-items: center; gap: 10px; }
.cart-item .item-price { color: #16a34a; font-weight: 600; font-size: 0.95rem; }
.cart-item .remove-btn { color: #ef4444; cursor: pointer; transition: color 0.2s; background: none; border: none; font-size: 0.95rem; padding: 2px 4px; }
.cart-item .remove-btn:hover { color: #b91c1c; }
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
.cart-empty { color: #64748b; text-align: center; padding: 18px 0; font-size: 0.95rem; }
.cart-empty i { font-size: 1.8rem; display: block; margin-bottom: 6px; color: #cbd5e1; }
.btn-continue {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: #ffffff !important;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 1rem;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 100%;
    text-align: center;
    display: block;
}
.btn-continue:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(79, 70, 229, 0.25); }
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
.floating-cart {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 16px;
    padding: 16px 20px;
    min-width: 200px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
    z-index: 100;
    display: none;
}
.floating-cart .cart-total { font-size: 1.25rem; font-weight: 700; color: #16a34a; }
.floating-cart .cart-count { color: #475569; font-size: 0.9rem; }
.floating-cart .btn-continue { padding: 10px 18px; font-size: 0.9rem; width: auto; }
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
    .food-card .food-image { height: 180px; max-height: 180px; }
    .floating-cart { bottom: 10px; right: 10px; left: 10px; min-width: auto; padding: 12px 16px; }
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
        <!-- SECCIÓN IZQUIERDA: MENÚ DE COMIDA -->
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
            <div class="category-title">
                <i class="fas fa-tag"></i> <?= htmlspecialchars($category) ?>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
                <?php foreach ($items as $item): ?>
                <div class="food-card" data-food-id="<?= $item['id'] ?>">
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
                            <span class="qty" id="qty_<?= $item['id'] ?>">0</span>
                            <button type="button" class="qty-increase" data-id="<?= $item['id'] ?>">+</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- SECCIÓN DERECHA: CARD-SUMMARY / RESUMEN DEL PEDIDO -->
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

            <!-- Comida seleccionada -->
            <div class="mb-4">
                <p class="text-xs text-gray-400 font-semibold uppercase mb-2">🍿 Comida Añadida</p>
                <div id="cartItems">
                    <div class="cart-empty">
                        <i class="fas fa-shopping-cart"></i>
                        No has seleccionado comida
                    </div>
                </div>
            </div>

            <!-- Total Final -->
            <div class="order-total">
                <span class="total-label">Total a Pagar</span>
                <span class="total-amount" id="totalAmount"><?= formatCurrency($totalTicketsPrice, $siteConfig) ?></span>
            </div>

            <!-- Botones de Acción -->
            <div class="flex flex-col gap-2.5 mt-5">
                <form action="save_food_order.php" method="POST" id="foodForm">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="showtime_id" value="<?= $showtimeId ?>">
                    <input type="hidden" name="food_order" id="foodOrderInput" value="[]">
                    <input type="hidden" name="purchase_token" value="<?= htmlspecialchars($token) ?>">
                    <button type="submit" class="btn-continue" id="btnCheckout">
                        <i class="fas fa-credit-card mr-2"></i> Ir a Pagar
                    </button>
                </form>

                <a href="<?= $backUrl ?>" class="btn-back">
                    <i class="fas fa-arrow-left mr-2"></i> Volver a Asientos
                </a>
            </div>
        </div>
    </div>

    <!-- Floating Cart Mobile -->
    <div class="floating-cart" id="floatingCart">
        <div class="flex justify-between items-center">
            <div>
                <div class="cart-count" id="floatingCount">0 productos</div>
                <div class="cart-total" id="floatingTotal"><?= formatCurrency($totalTicketsPrice, $siteConfig) ?></div>
            </div>
            <button class="btn-continue" onclick="submitFoodForm()">
                Pagar
            </button>
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

const totalTicketsPrice = <?= $totalTicketsPrice ?>;
const pricePerTicket = <?= $finalPrice ?>;
const ticketCount = <?= $ticketCount ?>;
const showtimeId = <?= $showtimeId ?>;
const seats = '<?= $seats ?>';
const purchaseToken = '<?= htmlspecialchars($token) ?>';
const taxRate = <?= $taxRate ?>;

let cart = {};
let totalFoodPrice = 0;

// ============================================
// FORMATO DE MONEDA
// ============================================
function formatCurrency(amount) {
    const symbol = currencyConfig.symbol;
    const position = currencyConfig.position;
    const thousands = currencyConfig.thousands;
    const decimal = currencyConfig.decimal;
    const decimals = currencyConfig.decimals;
    let formatted = amount.toFixed(decimals)
        .replace('.', decimal)
        .replace(/\B(?=(\d{3})+(?!\d))/g, thousands);
    return position === 'right' ? formatted + ' ' + symbol : symbol + formatted;
}

// ============================================
// ENVIAR FORMULARIO DE COMIDA
// ============================================
function submitFoodForm() {
    const items = Object.values(cart);
    const orderData = items.map(item => ({
        id: parseInt(item.id),
        quantity: item.quantity
    }));
    
    const form = document.getElementById('foodForm');
    document.getElementById('foodOrderInput').value = JSON.stringify(orderData);
    form.submit();
}

// ============================================
// CARRITO DE COMIDA
// ============================================
document.querySelectorAll('.qty-increase').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        const id = this.dataset.id;
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
        const id = this.dataset.id;
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
        const id = this.dataset.foodId;
        const qtyEl = document.getElementById('qty_' + id);
        let qty = parseInt(qtyEl.textContent) || 0;
        qty++;
        qtyEl.textContent = qty;
        updateCart(id, qty);
    });
});

function updateCart(foodId, quantity) {
    const foodItems = <?= json_encode($foodItems) ?>;
    const item = foodItems.find(f => f.id == foodId);
    if (!item) return;
    
    if (quantity > 0) {
        cart[foodId] = { ...item, quantity: quantity };
    } else {
        delete cart[foodId];
    }
    updateCartUI();
}

function updateCartUI() {
    const cartContainer = document.getElementById('cartItems');
    const totalAmountEl = document.getElementById('totalAmount');
    const floatingCart = document.getElementById('floatingCart');
    const floatingTotal = document.getElementById('floatingTotal');
    const floatingCount = document.getElementById('floatingCount');
    const items = Object.values(cart);
    
    totalFoodPrice = items.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    
    // ✅ CALCULAR TOTAL CON IVA EN EL FRONTEND SOLO PARA MOSTRAR
    // EL BACKEND RECALCULARÁ EN checkout.php
    const subtotal = totalTicketsPrice + totalFoodPrice;
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
    
    if (items.length === 0) {
        cartContainer.innerHTML = `
        <div class="cart-empty">
            <i class="fas fa-shopping-cart"></i>
            No has seleccionado comida
        </div>
        `;
        floatingCart.style.display = 'none';
    } else {
        let html = '';
        items.forEach(item => {
            const itemName = item.quantity + ' x ' + item.name;
            html += `
            <div class="cart-item">
                <span class="item-name">${escapeHtml(itemName)}</span>
                <div class="item-details">
                    <span class="item-price">${formatCurrency(item.price * item.quantity)}</span>
                    <button type="button" class="remove-btn" onclick="removeFromCart(${item.id})" title="Eliminar">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            `;
        });
        cartContainer.innerHTML = html;
        
        if (window.innerWidth < 1024) {
            floatingCart.style.display = 'block';
            floatingTotal.textContent = formatCurrency(total);
            const count = items.reduce((sum, item) => sum + item.quantity, 0);
            floatingCount.textContent = count + ' producto' + (count > 1 ? 's' : '');
        }
    }
    
    totalAmountEl.textContent = formatCurrency(total);
}

function removeFromCart(foodId) {
    const qtyEl = document.getElementById('qty_' + foodId);
    if (qtyEl) qtyEl.textContent = '0';
    delete cart[foodId];
    updateCartUI();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ============================================
// MANEJAR ENVÍO DEL FORMULARIO
// ============================================
document.getElementById('foodForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const items = Object.values(cart);
    const orderData = items.map(item => ({
        id: parseInt(item.id),
        quantity: item.quantity
    }));
    
    const btn = document.getElementById('btnCheckout');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Procesando...';
    
    // ✅ ENVIAR SOLO IDs Y CANTIDADES, NO PRECIOS
    fetch('save_food_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'showtime_id=' + showtimeId + 
              '&food_order=' + encodeURIComponent(JSON.stringify(orderData)) +
              '&purchase_token=' + encodeURIComponent(purchaseToken) +
              '&csrf_token=' + encodeURIComponent('<?= $csrf_token ?>')
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'payment.php?showtime_id=' + showtimeId + '&token=' + purchaseToken;
        } else {
            alert('Error al guardar el pedido. Intenta nuevamente.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-credit-card mr-2"></i> Ir a Pagar';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        window.location.href = 'payment.php?showtime_id=' + showtimeId + '&token=' + purchaseToken;
    });
});
</script>
</body>
</html>