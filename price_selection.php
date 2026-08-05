<?php
require_once 'config.php';

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
// ✅ VERIFICAR TIMEOUT DEL TOKEN ANTERIOR
// ============================================
if (isPurchaseTokenExpired($showtimeId)) {
    clearPurchaseSession($showtimeId);
}

// ============================================
// ✅ GENERAR NUEVO TOKEN DE COMPRA CON TIMEOUT
// ============================================
$purchaseToken = generatePurchaseTokenWithTimeout($showtimeId, 900);

// ============================================
// LIMPIAR VARIABLES DE SESIÓN PARA FLUJO LIMPIO
// ============================================
$keysToClean = [
    'food_timeout_' . $showtimeId,
    'food_seats_' . $showtimeId,
    'food_valid_' . $showtimeId,
    'food_order_' . $showtimeId,
    'payment_method_' . $showtimeId,
    'ticket_quantities_' . $showtimeId,
    'total_seats_' . $showtimeId,
    'subtotal_' . $showtimeId,
    'tax_amount_' . $showtimeId,
    'total_amount_' . $showtimeId
];
foreach ($keysToClean as $key) {
    unset($_SESSION[$key]);
}

// ============================================
// OBTENER DATOS DEL SHOWTIME
// ============================================
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

// ============================================
// ✅ OBTENER PRECIOS DESDE BD - CON FALLBACKS
// ============================================
$priceAdult = floatval($showtime['price_adult'] ?? $showtime['price'] ?? 0);
$priceChild = floatval($showtime['price_child'] ?? 0);
$priceSenior = floatval($showtime['price_senior'] ?? 0);

// Si los precios específicos son 0, usar el precio general como adulto
if ($priceAdult == 0 && isset($showtime['price']) && $showtime['price'] > 0) {
    $priceAdult = floatval($showtime['price']);
}

// Si priceAdult sigue siendo 0, usar un valor por defecto
if ($priceAdult == 0) {
    $priceAdult = 50.00;
}

// Si child y senior son 0, usar porcentajes del precio adulto
if ($priceChild == 0 && $priceAdult > 0) {
    $priceChild = $priceAdult * 0.5;
}
if ($priceSenior == 0 && $priceAdult > 0) {
    $priceSenior = $priceAdult * 0.7;
}

$enableChild = isset($showtime['enable_child_price']) && $showtime['enable_child_price'] == 1 ? 1 : 0;
$enableSenior = isset($showtime['enable_senior_price']) && $showtime['enable_senior_price'] == 1 ? 1 : 0;

// ============================================
// PROMOCIONES
// ============================================
$promotions = $showtime['promotions'] ? explode(',', $showtime['promotions']) : [];
$hasMondayPromo = in_array('lunes_mitad', $promotions);
$hasPresale = in_array('preventa', $promotions);

$currentDay = date('N');
if ($hasMondayPromo && $currentDay == 1) {
    $priceAdult = $priceAdult / 2;
    $priceChild = $priceChild / 2;
    $priceSenior = $priceSenior / 2;
}

// ============================================
// OBTENER TASA DE IVA
// ============================================
$stmt = $pdo->query("SELECT tax_rate FROM tax_config WHERE is_active = 1 LIMIT 1");
$tax = $stmt->fetch();
$taxRate = $tax ? floatval($tax['tax_rate']) : 16;

// ============================================
// OBTENER ASIENTOS DISPONIBLES
// ============================================
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

// ============================================
// IDIOMA
// ============================================
$language = $showtime['language'] ?? 'español';
$languageLabel = $language == 'español' ? 'Español' : 'Subtítulos en Español';

$csrf_token = generateCSRFToken();
$siteConfig = getSiteConfig($pdo);
$pageTitle = "Selección de Boletos - " . $showtime['title'];
$backUrl = 'movie_detail.php?id=' . $showtime['movie_id'];

$currency_symbol = $siteConfig['currency_symbol'] ?? '$';
$currency_position = $siteConfig['currency_position'] ?? 'left';
$thousands_separator = $siteConfig['thousands_separator'] ?? '.';
$decimal_separator = $siteConfig['decimal_separator'] ?? ',';
$decimal_places = intval($siteConfig['decimal_places'] ?? 2);

require_once 'header.php';
?>

<style>
body { background-color: #ffffff !important; color: #1f2937 !important; }
.price-card {
    background: #ffffff;
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    padding: 24px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    cursor: pointer;
}
.price-card:hover:not(.disabled) {
    border-color: #6366f1;
    box-shadow: 0 8px 20px rgba(0,0,0,0.08);
}
.price-card .price-info { display: flex; flex-direction: column; pointer-events: none; }
.price-card .price-info .label { font-weight: 700; font-size: 1.1rem; color: #0f172a; pointer-events: none; }
.price-card .price-info .description { font-size: 0.85rem; color: #64748b; pointer-events: none; }
.price-card .price-amount { font-size: 1.5rem; font-weight: 700; color: #16a34a; pointer-events: none; }
.price-card .quantity-controls {
    display: flex;
    align-items: center;
    gap: 16px;
    pointer-events: none;
}
.price-card .quantity-controls button {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: 1px solid #cbd5e1;
    background: #f1f5f9;
    color: #1e293b;
    font-size: 1.2rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: auto;
}
.price-card .quantity-controls button:hover:not(:disabled) {
    background: #4f46e5;
    border-color: #4f46e5;
    color: #ffffff;
}
.price-card .quantity-controls button:disabled { opacity: 0.3; cursor: not-allowed; }
.price-card .quantity-controls .qty {
    font-size: 1.3rem;
    font-weight: 700;
    color: #0f172a;
    min-width: 32px;
    text-align: center;
    pointer-events: none;
}
.price-card.disabled { display: none !important; }
.selected-info-box { background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; }
.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    border-bottom: 1px solid #e2e8f0;
    font-size: 0.95rem;
}
.summary-row:last-child { border-bottom: none; }
.summary-row .label { color: #475569; }
.summary-row .value { color: #0f172a; font-weight: 600; }
.summary-total {
    border-top: 2px solid #4f46e5;
    padding-top: 12px;
    margin-top: 8px;
    display: flex;
    justify-content: space-between;
    font-size: 1.2rem;
    font-weight: 700;
}
.summary-total .label { color: #0f172a; }
.summary-total .value { color: #16a34a; }
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
.movie-language { font-size: 0.9rem; color: #475569; margin-top: 2px; font-weight: 500; }
.total-seats-info {
    text-align: center;
    padding: 12px;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    font-size: 1rem;
    color: #475569;
}
.total-seats-info strong { color: #0f172a; font-size: 1.2rem; }
.card-summary {
    background: #f8fafc !important;
    border: 1px solid #cbd5e1 !important;
    border-top: 4px solid #4f46e5 !important;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08) !important;
    border-radius: 12px !important;
    padding: 24px;
}
.text-white { color: #0f172a !important; }
.text-gray-400 { color: #475569 !important; font-weight: 500; }
@media (min-width: 1024px) { .card-summary { position: sticky; top: 100px; } }
@media (max-width: 640px) {
    .price-card { flex-direction: column; align-items: stretch; gap: 12px; padding: 16px; }
    .price-card .quantity-controls { justify-content: center; }
    .price-card .price-amount { text-align: center; }
}
</style>

<div class="container mx-auto px-2 sm:px-4 py-4 sm:py-8 max-w-5xl">
    <div class="flex flex-col lg:flex-row gap-6">
        <div class="flex-1 min-w-0">
            <h2 class="text-2xl font-bold text-gray-800 mb-1">🎫 Selecciona tus Boletos</h2>
            <p class="text-base text-gray-400 mb-6">Elige la cantidad de boletos por tipo de tarifa</p>
            
            <?php if ($priceAdult <= 0): ?>
            <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 p-4 rounded-lg mb-4">
                <p class="font-semibold">⚠️ Atención:</p>
                <p class="text-sm">Esta función no tiene precios configurados. Por favor, contacta al administrador.</p>
            </div>
            <?php endif; ?>
            
            <div class="grid grid-cols-1 gap-4" id="priceGrid">
                <!-- Adulto (siempre disponible) -->
                <div class="price-card" id="card-adult" data-type="adult">
                    <div class="price-info">
                        <span class="label">👤 Adulto</span>
                        <span class="description">Precio estándar</span>
                    </div>
                    <div class="price-amount"><?= formatCurrency($priceAdult, $siteConfig) ?></div>
                    <div class="quantity-controls">
                        <button type="button" class="qty-decrease" data-type="adult">−</button>
                        <span class="qty" id="qty_adult">0</span>
                        <button type="button" class="qty-increase" data-type="adult">+</button>
                    </div>
                </div>
                <!-- Niño -->
                <?php if($enableChild && $priceChild > 0): ?>
                <div class="price-card" id="card-child" data-type="child">
                    <div class="price-info">
                        <span class="label">🧒 Niño</span>
                        <span class="description">Menores de 12 años</span>
                    </div>
                    <div class="price-amount"><?= formatCurrency($priceChild, $siteConfig) ?></div>
                    <div class="quantity-controls">
                        <button type="button" class="qty-decrease" data-type="child">−</button>
                        <span class="qty" id="qty_child">0</span>
                        <button type="button" class="qty-increase" data-type="child">+</button>
                    </div>
                </div>
                <?php endif; ?>
                <!-- Tercera Edad -->
                <?php if($enableSenior && $priceSenior > 0): ?>
                <div class="price-card" id="card-senior" data-type="senior">
                    <div class="price-info">
                        <span class="label">👴 Tercera Edad</span>
                        <span class="description">Mayores de 60 años</span>
                    </div>
                    <div class="price-amount"><?= formatCurrency($priceSenior, $siteConfig) ?></div>
                    <div class="quantity-controls">
                        <button type="button" class="qty-decrease" data-type="senior">−</button>
                        <span class="qty" id="qty_senior">0</span>
                        <button type="button" class="qty-increase" data-type="senior">+</button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="total-seats-info mt-6">
                Has seleccionado <strong id="totalSeatsCount">0</strong> boleto(s)
            </div>
            <?php if ($realAvailableSeats > 0): ?>
            <p class="text-xs text-gray-400 text-center mt-2"><?= $realAvailableSeats ?> asientos disponibles en esta función</p>
            <?php endif; ?>
        </div>
        
        <div class="w-full lg:w-80 card-summary">
            <h3 class="text-xl font-bold text-white mb-3 flex items-center gap-2">
                <i class="fas fa-receipt text-indigo-600"></i> Resumen
            </h3>
            <div class="selected-info-box">
                <div class="mb-3">
                    <p class="text-xs text-gray-400 font-semibold uppercase">🎬 Película</p>
                    <div class="text-slate-900 font-bold text-base"><?= htmlspecialchars($showtime['title']) ?></div>
                    <div class="movie-language"><?= htmlspecialchars($languageLabel) ?></div>
                    <div class="text-sm text-gray-500 mt-0.5">
                        <?= htmlspecialchars($showtime['room_name']) ?> · <?= formatDateShort($showtime['show_date']) ?> · <?= formatTimeVenezuela($showtime['show_time']) ?>
                    </div>
                    <?php if ($hasMondayPromo): ?>
                    <div class="text-xs text-green-600 font-semibold mt-1">🌙 Lunes ½ Precio aplicado</div>
                    <?php endif; ?>
                    <?php if ($hasPresale): ?>
                    <div class="text-xs text-indigo-600 font-semibold mt-1">🎫 Preventa</div>
                    <?php endif; ?>
                </div>
                <div id="summaryItems">
                    <div class="text-sm text-gray-400 text-center py-4">No has seleccionado boletos</div>
                </div>
                <div class="summary-total">
                    <span class="label">Subtotal</span>
                    <span class="value" id="subtotalAmount"><?= formatCurrency(0, $siteConfig) ?></span>
                </div>
                <div class="summary-row" style="border-bottom: none; padding-top: 4px;">
                    <span class="label">IVA (<?= $taxRate ?>%)</span>
                    <span class="value" id="taxAmount"><?= formatCurrency(0, $siteConfig) ?></span>
                </div>
                <div class="summary-total" style="border-top-color: #16a34a; margin-top: 4px;">
                    <span class="label">Total</span>
                    <span class="value" id="totalAmount"><?= formatCurrency(0, $siteConfig) ?></span>
                </div>
            </div>
            <div class="flex flex-col gap-2.5 mt-5">
                <form action="process_selection.php" method="POST" id="seatsForm">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="showtime_id" value="<?= $showtimeId ?>">
                    <input type="hidden" name="tickets" id="ticketsInput" value="">
                    <input type="hidden" name="total_seats" id="totalSeatsInput" value="0">
                    <input type="hidden" name="subtotal" id="subtotalHidden" value="0">
                    <input type="hidden" name="tax_amount" id="taxHidden" value="0">
                    <input type="hidden" name="total_amount" id="totalHidden" value="0">
                    <input type="hidden" name="purchase_token" value="<?= htmlspecialchars($purchaseToken) ?>">
                    <button type="submit" class="btn-continue" id="btnContinue" disabled>
                        Elegir <span id="btnSeatsCount">0</span> Asiento(s)
                    </button>
                </form>
                <a href="movie_detail.php?id=<?= $showtime['movie_id'] ?>" class="btn-back">
                    <i class="fas fa-arrow-left mr-2"></i> Volver a Funciones
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>

<script>
// ============================================
// ✅ CONFIGURACIÓN DESDE PHP
// ============================================
const priceAdult = <?= floatval($priceAdult) ?>;
const priceChild = <?= floatval($priceChild) ?>;
const priceSenior = <?= floatval($priceSenior) ?>;
const enableChild = <?= intval($enableChild) ?>;
const enableSenior = <?= intval($enableSenior) ?>;
const taxRate = <?= floatval($taxRate) ?>;
const showtimeId = <?= $showtimeId ?>;
const purchaseToken = '<?= htmlspecialchars($purchaseToken) ?>';
const maxAvailableSeats = <?= intval($realAvailableSeats) ?>;

const currencyConfig = {
    symbol: '<?= $currency_symbol ?>',
    position: '<?= $currency_position ?>',
    thousands: '<?= $thousands_separator ?>',
    decimal: '<?= $decimal_separator ?>',
    decimals: <?= intval($decimal_places) ?>
};

console.log('=== PRICE_SELECTION JS ===');
console.log('priceAdult:', priceAdult);
console.log('priceChild:', priceChild);
console.log('priceSenior:', priceSenior);
console.log('enableChild:', enableChild);
console.log('enableSenior:', enableSenior);

// ============================================
// ESTADO
// ============================================
let quantities = {
    adult: 0,
    child: 0,
    senior: 0
};

const prices = {
    adult: priceAdult,
    child: priceChild,
    senior: priceSenior
};

const enabledTypes = {
    adult: true,
    child: enableChild === 1 && priceChild > 0,
    senior: enableSenior === 1 && priceSenior > 0
};

console.log('enabledTypes:', enabledTypes);

// ============================================
// FUNCIONES
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

function getElement(id) {
    const el = document.getElementById(id);
    if (!el) {
        console.warn('⚠️ Elemento no encontrado:', id);
    }
    return el;
}

function updateUI() {
    const total = quantities.adult + quantities.child + quantities.senior;
    const subtotal = (quantities.adult * prices.adult) +
                     (quantities.child * prices.child) +
                     (quantities.senior * prices.senior);
    const tax = subtotal * (taxRate / 100);
    const totalAmount = subtotal + tax;
    
    console.log('📊 Actualizando UI - Total:', total, 'Subtotal:', subtotal);
    
    // ✅ Actualizar contadores - con verificación de existencia
    const qtyAdult = getElement('qty_adult');
    const qtyChild = getElement('qty_child');
    const qtySenior = getElement('qty_senior');
    
    if (qtyAdult) qtyAdult.textContent = quantities.adult;
    if (qtyChild) qtyChild.textContent = quantities.child;
    if (qtySenior) qtySenior.textContent = quantities.senior;
    
    // ✅ Actualizar resumen
    const totalSeatsCount = getElement('totalSeatsCount');
    const btnSeatsCount = getElement('btnSeatsCount');
    const totalSeatsInput = getElement('totalSeatsInput');
    
    if (totalSeatsCount) totalSeatsCount.textContent = total;
    if (btnSeatsCount) btnSeatsCount.textContent = total;
    if (totalSeatsInput) totalSeatsInput.value = total;
    
    // ✅ Actualizar campos ocultos
    const subtotalHidden = getElement('subtotalHidden');
    const taxHidden = getElement('taxHidden');
    const totalHidden = getElement('totalHidden');
    
    if (subtotalHidden) subtotalHidden.value = subtotal.toFixed(2);
    if (taxHidden) taxHidden.value = tax.toFixed(2);
    if (totalHidden) totalHidden.value = totalAmount.toFixed(2);
    
    // ✅ Actualizar resumen visual
    const summaryItems = getElement('summaryItems');
    if (summaryItems) {
        let html = '';
        let hasItems = false;
        
        if (quantities.adult > 0) {
            hasItems = true;
            html += `
            <div class="summary-row">
                <span class="label">Adulto x${quantities.adult}</span>
                <span class="value">${formatCurrency(quantities.adult * prices.adult)}</span>
            </div>
            `;
        }
        if (quantities.child > 0) {
            hasItems = true;
            html += `
            <div class="summary-row">
                <span class="label">Niño x${quantities.child}</span>
                <span class="value">${formatCurrency(quantities.child * prices.child)}</span>
            </div>
            `;
        }
        if (quantities.senior > 0) {
            hasItems = true;
            html += `
            <div class="summary-row">
                <span class="label">Tercera Edad x${quantities.senior}</span>
                <span class="value">${formatCurrency(quantities.senior * prices.senior)}</span>
            </div>
            `;
        }
        
        if (!hasItems) {
            html = `<div class="text-sm text-gray-400 text-center py-4">No has seleccionado boletos</div>`;
        }
        summaryItems.innerHTML = html;
    }
    
    // ✅ Actualizar totales
    const subtotalAmount = getElement('subtotalAmount');
    const taxAmount = getElement('taxAmount');
    const totalAmountEl = getElement('totalAmount');
    
    if (subtotalAmount) subtotalAmount.textContent = formatCurrency(subtotal);
    if (taxAmount) taxAmount.textContent = formatCurrency(tax);
    if (totalAmountEl) totalAmountEl.textContent = formatCurrency(totalAmount);
    
    // ✅ Habilitar/deshabilitar botón
    const btnContinue = getElement('btnContinue');
    if (btnContinue) {
        if (total > 0 && total <= maxAvailableSeats) {
            btnContinue.disabled = false;
            btnContinue.innerHTML = `Elegir ${total} Asiento${total !== 1 ? 's' : ''}`;
            console.log('✅ Botón habilitado');
        } else if (total > maxAvailableSeats) {
            btnContinue.disabled = true;
            btnContinue.innerHTML = `⚠️ Solo ${maxAvailableSeats} asientos disponibles`;
            console.log('⚠️ Excede asientos disponibles');
        } else {
            btnContinue.disabled = true;
            btnContinue.innerHTML = 'Elegir 0 Asientos';
            console.log('❌ Botón deshabilitado - Sin asientos');
        }
    }
    
    // ✅ Actualizar campo de tickets
    const ticketsInput = getElement('ticketsInput');
    if (ticketsInput) ticketsInput.value = JSON.stringify(quantities);
    
    // Guardar en sessionStorage
    try {
        sessionStorage.setItem('ticket_selection_' + showtimeId, JSON.stringify(quantities));
    } catch (e) {
        console.warn('Error guardando en sessionStorage:', e);
    }
}

// ============================================
// FUNCIÓN PARA INCREMENTAR/DECREMENTAR
// ============================================
function updateQuantity(type, change) {
    if (!enabledTypes[type]) {
        console.warn('Tipo no habilitado:', type);
        return;
    }
    const newValue = quantities[type] + change;
    if (newValue < 0) return;
    quantities[type] = newValue;
    console.log('🔄 Cantidad actualizada:', type, '=', quantities[type]);
    updateUI();
}

// ============================================
// EVENT LISTENERS
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ price_selection.php cargado - DOM listo');
    
    // Limpiar sessionStorage al cargar
    try {
        sessionStorage.removeItem('selected_seats_' + showtimeId);
        sessionStorage.removeItem('selected_seats_count_' + showtimeId);
    } catch(e) {}
    
    // Botones de incremento
    document.querySelectorAll('.qty-increase').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const type = this.dataset.type;
            console.log('➕ Click + en:', type);
            updateQuantity(type, 1);
        });
    });
    
    // Botones de decremento
    document.querySelectorAll('.qty-decrease').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const type = this.dataset.type;
            console.log('➖ Click - en:', type);
            updateQuantity(type, -1);
        });
    });
    
    // Clic en la tarjeta
    document.querySelectorAll('.price-card:not(.disabled)').forEach(function(card) {
        card.addEventListener('click', function(e) {
            if (e.target.closest('.quantity-controls button')) {
                return;
            }
            const type = this.dataset.type;
            if (!type) return;
            console.log('🖱️ Click en tarjeta:', type);
            updateQuantity(type, 1);
        });
    });
    
    // Validar formulario antes de enviar
    document.getElementById('seatsForm').addEventListener('submit', function(e) {
        const total = quantities.adult + quantities.child + quantities.senior;
        console.log('📤 Enviando formulario - Total:', total);
        
        if (total === 0) {
            e.preventDefault();
            alert('Por favor, selecciona al menos un boleto.');
            return false;
        }
        if (total > maxAvailableSeats) {
            e.preventDefault();
            alert('Solo hay ' + maxAvailableSeats + ' asientos disponibles para esta función.');
            return false;
        }
        const tokenInput = this.querySelector('input[name="purchase_token"]');
        if (!tokenInput || !tokenInput.value) {
            e.preventDefault();
            alert('Error de seguridad: Token de compra no encontrado.');
            return false;
        }
        console.log('✅ Formulario enviado correctamente');
        return true;
    });
    
    // Inicializar UI
    updateUI();
    console.log('✅ UI inicializada correctamente');
});
</script>
</body>
</html>