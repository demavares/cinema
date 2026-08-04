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

// Obtener configuración de IVA
$stmt = $pdo->query("SELECT tax_rate FROM tax_config WHERE is_active = 1 LIMIT 1");
$tax = $stmt->fetch();
$taxRate = $tax ? floatval($tax['tax_rate']) : 16;

// Obtener precios del showtime
$priceAdult = floatval($showtime['price_adult'] ?? $showtime['price'] ?? 0);
$priceChild = floatval($showtime['price_child'] ?? 0);
$priceSenior = floatval($showtime['price_senior'] ?? 0);

$enableChild = isset($showtime['enable_child_price']) && $showtime['enable_child_price'] == 1 ? 1 : 0;
$enableSenior = isset($showtime['enable_senior_price']) && $showtime['enable_senior_price'] == 1 ? 1 : 0;

// Promociones
$promotions = $showtime['promotions'] ? explode(',', $showtime['promotions']) : [];
$hasMondayPromo = in_array('lunes_mitad', $promotions);
$hasPresale = in_array('preventa', $promotions);

// Aplicar descuento de lunes si aplica
$currentDay = date('N');
if ($hasMondayPromo && $currentDay == 1) {
    $priceAdult = $priceAdult / 2;
    $priceChild = $priceChild / 2;
    $priceSenior = $priceSenior / 2;
}

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
    body {
        background-color: #ffffff !important;
        color: #1f2937 !important;
    }

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

    .price-card .price-info {
        display: flex;
        flex-direction: column;
        pointer-events: none;
    }

    .price-card .price-info .label {
        font-weight: 700;
        font-size: 1.1rem;
        color: #0f172a;
        pointer-events: none;
    }

    .price-card .price-info .description {
        font-size: 0.85rem;
        color: #64748b;
        pointer-events: none;
    }

    .price-card .price-amount {
        font-size: 1.5rem;
        font-weight: 700;
        color: #16a34a;
        pointer-events: none;
    }

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

    .price-card .quantity-controls button:disabled {
        opacity: 0.3;
        cursor: not-allowed;
    }

    .price-card .quantity-controls .qty {
        font-size: 1.3rem;
        font-weight: 700;
        color: #0f172a;
        min-width: 32px;
        text-align: center;
        pointer-events: none;
    }

    .price-card.disabled {
        display: none !important;
    }

    .selected-info-box {
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 16px;
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        border-bottom: 1px solid #e2e8f0;
        font-size: 0.95rem;
    }

    .summary-row:last-child {
        border-bottom: none;
    }

    .summary-row .label {
        color: #475569;
    }

    .summary-row .value {
        color: #0f172a;
        font-weight: 600;
    }

    .summary-total {
        border-top: 2px solid #4f46e5;
        padding-top: 12px;
        margin-top: 8px;
        display: flex;
        justify-content: space-between;
        font-size: 1.2rem;
        font-weight: 700;
    }

    .summary-total .label {
        color: #0f172a;
    }

    .summary-total .value {
        color: #16a34a;
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

    .btn-continue:disabled {
        opacity: 0.4;
        cursor: not-allowed;
        transform: none !important;
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

    .movie-language {
        font-size: 0.9rem;
        color: #475569;
        margin-top: 2px;
        font-weight: 500;
    }

    .total-seats-info {
        text-align: center;
        padding: 12px;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        font-size: 1rem;
        color: #475569;
    }

    .total-seats-info strong {
        color: #0f172a;
        font-size: 1.2rem;
    }

    .card-summary {
        background: #f8fafc !important;
        border: 1px solid #cbd5e1 !important;
        border-top: 4px solid #4f46e5 !important;
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08) !important;
        border-radius: 12px !important;
        padding: 24px;
    }

    .text-white {
        color: #0f172a !important;
    }
    .text-gray-400 {
        color: #475569 !important;
        font-weight: 500;
    }

    @media (min-width: 1024px) {
        .card-summary {
            position: sticky;
            top: 100px;
        }
    }

    @media (max-width: 640px) {
        .price-card {
            flex-direction: column;
            align-items: stretch;
            gap: 12px;
            padding: 16px;
        }
        .price-card .quantity-controls {
            justify-content: center;
        }
        .price-card .price-amount {
            text-align: center;
        }
    }
</style>

<div class="container mx-auto px-2 sm:px-4 py-4 sm:py-8 max-w-5xl">
    <div class="flex flex-col lg:flex-row gap-6">
        <!-- SECCIÓN IZQUIERDA: SELECCIÓN DE PRECIOS -->
        <div class="flex-1 min-w-0">
            <h2 class="text-2xl font-bold text-gray-800 mb-1">🎫 Selecciona tus Boletos</h2>
            <p class="text-base text-gray-400 mb-6">
                Elige la cantidad de boletos por tipo de tarifa
            </p>

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

                <!-- Niño - OCULTO si está deshabilitado -->
                <?php if($enableChild): ?>
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

                <!-- Tercera Edad - OCULTO si está deshabilitado -->
                <?php if($enableSenior): ?>
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

            <!-- Resumen de asientos seleccionados -->
            <div class="total-seats-info mt-6">
                Has seleccionado <strong id="totalSeatsCount">0</strong> boleto
            </div>
        </div>

        <!-- SECCIÓN DERECHA: RESUMEN -->
        <div class="w-full lg:w-80 card-summary">
            <h3 class="text-xl font-bold text-white mb-3 flex items-center gap-2">
                <i class="fas fa-receipt text-indigo-600"></i> Resumen
            </h3>

            <div class="selected-info-box">
                <!-- Película -->
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
                    <div class="text-sm text-gray-400 text-center py-4">
                        No has seleccionado boletos
                    </div>
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
                    <button type="submit" class="btn-continue" id="btnContinue" disabled>
                        Elegir <span id="btnSeatsCount">0</span> Asientos
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
// CONFIGURACIÓN
// ============================================
const priceAdult = <?= floatval($priceAdult) ?>;
const priceChild = <?= floatval($priceChild) ?>;
const priceSenior = <?= floatval($priceSenior) ?>;
const enableChild = <?= intval($enableChild) ?>;
const enableSenior = <?= intval($enableSenior) ?>;
const taxRate = <?= floatval($taxRate) ?>;
const showtimeId = <?= $showtimeId ?>;

const currencyConfig = {
    symbol: '<?= $currency_symbol ?>',
    position: '<?= $currency_position ?>',
    thousands: '<?= $thousands_separator ?>',
    decimal: '<?= $decimal_separator ?>',
    decimals: <?= intval($decimal_places) ?>
};

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
    child: enableChild === 1,
    senior: enableSenior === 1
};

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

function updateUI() {
    const total = quantities.adult + quantities.child + quantities.senior;
    const subtotal = (quantities.adult * prices.adult) + 
                     (quantities.child * prices.child) + 
                     (quantities.senior * prices.senior);
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
    
    if (subtotalHidden) subtotalHidden.value = subtotal;
    if (taxHidden) taxHidden.value = tax;
    if (totalHidden) totalHidden.value = totalAmount;

    const summaryItems = document.getElementById('summaryItems');
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
    if (summaryItems) summaryItems.innerHTML = html;

    const subtotalAmount = document.getElementById('subtotalAmount');
    const taxAmount = document.getElementById('taxAmount');
    const totalAmountEl = document.getElementById('totalAmount');
    
    if (subtotalAmount) subtotalAmount.textContent = formatCurrency(subtotal);
    if (taxAmount) taxAmount.textContent = formatCurrency(tax);
    if (totalAmountEl) totalAmountEl.textContent = formatCurrency(totalAmount);

    const btnContinue = document.getElementById('btnContinue');
    if (btnContinue) {
        if (total > 0) {
            btnContinue.disabled = false;
            btnContinue.innerHTML = `Elegir ${total} Asiento${total !== 1 ? 's' : ''}`;
        } else {
            btnContinue.disabled = true;
            btnContinue.innerHTML = 'Elegir 0 Asientos';
        }
    }

    const ticketsInput = document.getElementById('ticketsInput');
    if (ticketsInput) ticketsInput.value = JSON.stringify(quantities);
}

// ============================================
// FUNCIÓN PARA INCREMENTAR/DECREMENTAR
// ============================================
function updateQuantity(type, change) {
    if (!enabledTypes[type]) return;
    const newValue = quantities[type] + change;
    if (newValue < 0) return;
    quantities[type] = newValue;
    updateUI();
}

// ============================================
// RESETEAR ASIENTOS CUANDO SE CAMBIA LA SELECCIÓN
// ============================================
function resetSeatsStorage() {
    try {
        sessionStorage.removeItem('selected_seats_' + showtimeId);
        sessionStorage.removeItem('selected_seats_count_' + showtimeId);
        console.log('🗑️ Asientos eliminados de sessionStorage');
    } catch (e) {
        console.warn('Error limpiando sessionStorage:', e);
    }
}

// ============================================
// ✅ LIMPIAR TODO AL VOLVER A FUNCIONES
// ============================================
function clearAllSelection() {
    try {
        sessionStorage.removeItem('ticket_selection_' + showtimeId);
        sessionStorage.removeItem('selected_seats_' + showtimeId);
        sessionStorage.removeItem('selected_seats_count_' + showtimeId);
        console.log('🗑️ Toda la selección eliminada');
    } catch (e) {
        console.warn('Error limpiando sessionStorage:', e);
    }
}

// ============================================
// EVENT LISTENERS
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ price_selection.php cargado');

    // ✅ DETECTAR SI VIENE DE FUNCIONES (movie_detail.php) Y LIMPIAR TODO
    const referrer = document.referrer || '';
    if (referrer.includes('movie_detail.php')) {
        clearAllSelection();
        console.log('🗑️ Selección limpiada (viene de movie_detail)');
    }

    // Botones de incremento
    document.querySelectorAll('.qty-increase').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const type = this.dataset.type;
            console.log('➕ Incrementar:', type);
            resetSeatsStorage();
            updateQuantity(type, 1);
        });
    });

    // Botones de decremento
    document.querySelectorAll('.qty-decrease').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const type = this.dataset.type;
            console.log('➖ Decrementar:', type);
            resetSeatsStorage();
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
            console.log('🖱️ Clic en tarjeta:', type);
            resetSeatsStorage();
            updateQuantity(type, 1);
        });
    });

    // ✅ CARGAR DATOS GUARDADOS EN SESSIONSTORAGE (persistencia de boletos)
    const savedTickets = sessionStorage.getItem('ticket_selection_' + showtimeId);
    if (savedTickets) {
        try {
            const parsed = JSON.parse(savedTickets);
            if (parsed && typeof parsed === 'object') {
                quantities.adult = parsed.adult || 0;
                quantities.child = parsed.child || 0;
                quantities.senior = parsed.senior || 0;
                console.log('✅ Datos de boletos cargados desde sessionStorage:', quantities);
                updateUI();
            }
        } catch (e) {
            console.warn('Error cargando datos:', e);
        }
    }

    // ✅ GUARDAR EN SESSIONSTORAGE AL CAMBIAR
    const originalUpdateUI = updateUI;
    updateUI = function() {
        originalUpdateUI();
        try {
            sessionStorage.setItem('ticket_selection_' + showtimeId, JSON.stringify(quantities));
        } catch (e) {
            console.warn('Error guardando en sessionStorage:', e);
        }
    };

    // ✅ VALIDAR FORMULARIO ANTES DE ENVIAR
    document.getElementById('seatsForm').addEventListener('submit', function(e) {
        const total = quantities.adult + quantities.child + quantities.senior;
        if (total === 0) {
            e.preventDefault();
            alert('Por favor, selecciona al menos un boleto.');
            return false;
        }
        console.log('✅ Formulario enviado:', quantities);
        return true;
    });

    // Inicializar UI
    updateUI();
    console.log('✅ UI inicializada');
});
</script>
</body>
</html>