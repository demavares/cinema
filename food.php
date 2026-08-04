<?php
require_once 'config.php';

// Prevenir Caché del Navegador
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Obtener parámetros vía GET o Recuperar de Sesión
$showtimeId = isset($_GET['showtime_id']) ? intval($_GET['showtime_id']) : ($_SESSION['checkout']['showtime_id'] ?? 0);
$seatsRaw = isset($_GET['seats']) ? trim($_GET['seats']) : ($_SESSION['checkout']['seats'] ?? '');

if ($showtimeId <= 0 || empty($seatsRaw)) {
    header('Location: index.php');
    exit;
}

// Normalizar lista de asientos
$seatsArray = array_map('trim', explode(',', $seatsRaw));
sort($seatsArray);
$seats = implode(',', $seatsArray);

// Actualizar variable unificada de Checkout en Sesión
$_SESSION['checkout'] = [
    'showtime_id' => $showtimeId,
    'seats' => $seats,
    'seats_count' => count($seatsArray),
    'expire_time' => $_SESSION['checkout']['expire_time'] ?? (time() + 600)
];

// Validar expiración de reserva
if (time() > $_SESSION['checkout']['expire_time']) {
    unset($_SESSION['checkout']);
    header('Location: index.php?timeout=1');
    exit;
}

// Consultar Showtime y Película
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

$ticketCount = count($seatsArray);
$finalPrice = getShowtimePrice($showtime);
$totalTicketsPrice = $ticketCount * $finalPrice;

// Obtener Golosinas/Comida
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

$siteConfig = getSiteConfig($pdo);
$pageTitle = "Comida - " . $showtime['title'];

require_once 'header.php';
?>

<style>
    .timeout-warning {
        padding: 16px 24px;
        border-radius: 10px;
        font-size: 0.9rem;
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
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    }
    
    .timeout-warning.normal { background: #1e1b4b; border: 1px solid #4f46e5; color: #818cf8; }
    .timeout-warning.warning { background: #451a1a; border: 1px solid #f59e0b; color: #fbbf24; }
    .timeout-warning.danger { background: #7f1d1d; border: 1px solid #ef4444; color: #fca5a5; animation: pulse-danger 1s ease-in-out infinite; }
    
    @keyframes pulse-danger { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
    
    .timeout-warning .countdown { font-weight: 700; font-size: 1.2rem; min-width: 60px; text-align: center; }
    .timeout-warning.normal .countdown { color: #fbbf24; }
    .timeout-warning.warning .countdown { color: #f59e0b; }
    .timeout-warning.danger .countdown { color: #ef4444; animation: pulse-countdown 0.5s ease-in-out infinite; }
    
    @keyframes pulse-countdown { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.6; transform: scale(1.1); } }
    
    .food-card {
        background: #14141e;
        border: 1px solid #1e1e2e;
        border-radius: 12px;
        transition: all 0.3s ease;
        cursor: pointer;
        overflow: hidden;
    }
    .food-card:hover { border-color: #4f46e5; transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.4); }
    .food-card.selected { border-color: #4f46e5; background: #1e1b4b; box-shadow: 0 0 20px rgba(99, 102, 241, 0.2); }
    
    .food-card .food-image { width: 100%; height: 233px; max-height: 233px; object-fit: cover; background: #1a1a2e; }
    .food-card .food-info { padding: 12px 16px 16px 16px; }
    .food-card .food-name { font-weight: 700; color: #e5e7eb; font-size: 1.1rem; }
    .food-card .food-price { color: #22c55e; font-weight: 700; font-size: 1.2rem; }
    .food-card .food-desc { color: #9ca3af; font-size: 0.95rem; margin-top: 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; }
    
    .food-card .quantity-controls { display: flex; align-items: center; justify-content: center; gap: 14px; margin-top: 10px; padding: 6px 0; }
    .food-card .quantity-controls button { background: #1f2937; border: 1px solid #374151; color: #e5e7eb; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; font-weight: 700; }
    .food-card .quantity-controls button:hover { background: #4f46e5; border-color: #4f46e5; }
    .food-card .quantity-controls .qty { font-weight: 700; color: #e5e7eb; min-width: 24px; text-align: center; font-size: 1.1rem; }
    
    .category-title { color: #d1d5db; font-size: 1.1rem; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; margin: 24px 0 14px 0; border-bottom: 2px solid #1e1e2e; padding-bottom: 10px; }
    .category-title i { margin-right: 8px; color: #4f46e5; }
    
    .summary-sticky {
        position: relative;
        top: auto;
        align-self: flex-start;
        max-height: none;
        overflow: visible;
        padding: 24px;
        box-sizing: border-box;
    }

    .ticket-line {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 4px 0;
        font-size: 0.95rem;
    }
    .ticket-line .ticket-label { color: #9ca3af; }
    .ticket-line .ticket-price { color: #22c55e; font-weight: 600; }

    .cart-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 6px 0;
        border-bottom: 1px solid #1a1a2e;
        font-size: 0.9rem;
    }
    .cart-item:last-child { border-bottom: none; }
    .cart-item .item-name { color: #d1d5db; flex: 1; word-break: break-word; }
    .cart-item .item-details { display: flex; align-items: center; gap: 10px; }
    .cart-item .item-price { color: #22c55e; font-weight: 600; font-size: 0.9rem; }
    .cart-item .remove-btn { color: #ef4444; cursor: pointer; transition: color 0.2s; background: none; border: none; font-size: 0.9rem; padding: 2px 4px; }
    .cart-item .remove-btn:hover { color: #dc2626; }

    .order-total {
        border-top: 2px solid #4f46e5;
        padding-top: 12px;
        margin-top: 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 1.2rem;
        font-weight: 700;
    }
    .order-total .total-label { color: #e5e7eb; }
    .order-total .total-amount { color: #22c55e; }

    .seats-display { font-size: 0.9rem; font-weight: 400; color: #9ca3af; word-break: break-word; }

    .cart-empty { color: #6b7280; text-align: center; padding: 20px 0; font-size: 0.9rem; }
    .cart-empty i { font-size: 2rem; display: block; margin-bottom: 8px; color: #374151; }

    .btn-continue {
        background: linear-gradient(135deg, #4f46e5, #7c3aed);
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        font-weight: 700;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        width: 100%;
        text-align: center;
    }
    .btn-continue:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(79, 70, 229, 0.3); }

    .btn-back {
        background: transparent;
        border: 1px solid #374151;
        color: #9ca3af;
        padding: 12px 20px;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
        cursor: pointer;
        width: 100%;
        text-align: center;
        text-decoration: none;
        display: block;
    }
    .btn-back:hover { border-color: #4f46e5; color: #e5e7eb; background: rgba(79, 70, 229, 0.1); }

    @media (max-width: 1024px) {
        .timeout-warning { top: 90px; }
    }
    @media (max-width: 768px) {
        .food-card .food-image { height: 180px; max-height: 180px; }
        .summary-sticky { padding: 16px; }

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
        <span class="ml-auto text-xs" id="timeoutStatus">Los asientos se liberarán automáticamente</span>
    </div>

    <div class="flex flex-col lg:flex-row gap-6">
        <!-- Menú de Comida -->
        <div class="flex-1 min-w-0">
            <h2 class="text-xl font-bold text-white mb-4">🍿 Elige tu comida</h2>
            <p class="text-sm text-gray-400 mb-4">Selecciona los productos que deseas agregar a tu pedido (opcional)</p>
            
            <?php if (empty($foodItems)): ?>
                <div class="bg-[#14141e] p-8 rounded-xl border border-[#1e1e2e] text-center">
                    <div class="text-4xl mb-3">🍿</div>
                    <p class="text-gray-400">No hay productos de comida disponibles en este momento.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                    <?php foreach ($foodByCategory as $category => $items): ?>
                        <div class="col-span-1 sm:col-span-2 xl:col-span-3">
                            <div class="category-title">
                                <i class="fas fa-tag"></i> <?= htmlspecialchars($category) ?>
                            </div>
                        </div>
                        <?php foreach ($items as $item): ?>
                            <div class="food-card" data-food-id="<?= $item['id'] ?>">
                                <?php if (!empty($item['image_url']) && file_exists($item['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="food-image">
                                <?php else: ?>
                                    <div class="food-image flex items-center justify-center text-6xl bg-gray-800">🍿</div>
                                <?php endif; ?>
                                <div class="food-info">
                                    <div class="flex justify-between items-start">
                                        <span class="food-name"><?= htmlspecialchars($item['name']) ?></span>
                                        <span class="food-price"><?= formatCurrency($item['price'], $siteConfig) ?></span>
                                    </div>
                                    <?php if (!empty($item['description'])): ?>
                                        <p class="food-desc"><?= htmlspecialchars($item['description']) ?></p>
                                    <?php endif; ?>
                                    <div class="quantity-controls">
                                        <button class="qty-decrease" data-id="<?= $item['id'] ?>" data-price="<?= $item['price'] ?>" data-name="<?= htmlspecialchars($item['name']) ?>">−</button>
                                        <span class="qty" id="qty_<?= $item['id'] ?>">0</span>
                                        <button class="qty-increase" data-id="<?= $item['id'] ?>" data-price="<?= $item['price'] ?>" data-name="<?= htmlspecialchars($item['name']) ?>">+</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Resumen del Pedido -->
        <div class="w-full lg:w-80 bg-[#14141e] rounded-xl border border-[#1e1e2e] flex flex-col summary-sticky">
            <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
                <i class="fas fa-receipt text-indigo-400"></i> Resumen del Pedido
            </h3>
            
            <div class="mb-4">
                <p class="text-sm text-gray-400 mb-2">🎬 Película</p>
                <div class="text-white font-medium"><?= htmlspecialchars($showtime['title']) ?></div>
                <div class="text-sm text-gray-400">
                    <?= formatDateShort($showtime['show_date']) ?> · <?= formatTimeVenezuela($showtime['show_time']) ?>
                </div>
            </div>

            <div class="mb-4 border-t border-[#1e1e2e] pt-3">
                <div class="ticket-line">
                    <span class="ticket-label">Boletos (<?= $ticketCount ?>)</span>
                    <span class="ticket-price"><?= formatCurrency($totalTicketsPrice, $siteConfig) ?></span>
                </div>
                <div class="seats-display">Asientos: <?= htmlspecialchars($seats) ?></div>
            </div>

            <div class="mb-4 border-t border-[#1e1e2e] pt-3 flex-1">
                <p class="text-sm text-gray-400 mb-2">🍿 Comida seleccionada</p>
                <div id="cartItems">
                    <div class="cart-empty" id="cartEmpty">
                        <i class="fas fa-shopping-basket"></i>
                        No has seleccionado golosinas
                    </div>
                </div>
            </div>

            <div class="order-total mb-6">
                <span class="total-label">Total:</span>
                <span class="total-amount" id="grandTotal"><?= formatCurrency($totalTicketsPrice, $siteConfig) ?></span>
            </div>

            <form action="payment.php" method="POST" id="foodForm">
                <input type="hidden" name="showtime_id" value="<?= $showtimeId ?>">
                <input type="hidden" name="seats" value="<?= htmlspecialchars($seats) ?>">
                <input type="hidden" name="food_data" id="foodDataInput" value="[]">
                
                <div class="space-y-3">
                    <button type="submit" class="btn-continue">
                        Continuar al Pago <i class="fas fa-arrow-right ml-2"></i>
                    </button>
                    <a href="seats.php?showtime_id=<?= $showtimeId ?>" class="btn-back">
                        <i class="fas fa-arrow-left mr-2"></i> Volver a Asientos
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let remainingSeconds = <?= max(0, ($_SESSION['checkout']['expire_time'] ?? (time() + 600)) - time()) ?>;
const ticketTotal = <?= $totalTicketsPrice ?>;
let cart = {};

function updateTimerDisplay() {
    const minutes = Math.floor(remainingSeconds / 60);
    const seconds = remainingSeconds % 60;
    const formatted = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    
    const timerElem = document.getElementById('countdownTimer');
    const warningElem = document.getElementById('timeoutWarning');
    
    if (timerElem) timerElem.textContent = formatted;
    
    if (warningElem) {
        if (remainingSeconds <= 120) {
            warningElem.className = 'timeout-warning danger';
        } else if (remainingSeconds <= 300) {
            warningElem.className = 'timeout-warning warning';
        } else {
            warningElem.className = 'timeout-warning normal';
        }
    }

    if (remainingSeconds <= 0) {
        window.location.href = 'index.php?timeout=1';
    } else {
        remainingSeconds--;
    }
}

setInterval(updateTimerDisplay, 1000);
updateTimerDisplay();

// Manejo del Carrito de Comida
document.querySelectorAll('.qty-increase').forEach(btn => {
    btn.addEventListener('click', (e) => {
        e.preventDefault();
        const id = btn.dataset.id;
        const price = parseFloat(btn.dataset.price);
        const name = btn.dataset.name;
        
        if (!cart[id]) {
            cart[id] = { name, price, qty: 0 };
        }
        cart[id].qty++;
        updateCartUI();
    });
});

document.querySelectorAll('.qty-decrease').forEach(btn => {
    btn.addEventListener('click', (e) => {
        e.preventDefault();
        const id = btn.dataset.id;
        if (cart[id] && cart[id].qty > 0) {
            cart[id].qty--;
            if (cart[id].qty === 0) delete cart[id];
            updateCartUI();
        }
    });
});

function updateCartUI() {
    const container = document.getElementById('cartItems');
    const emptyElem = document.getElementById('cartEmpty');
    container.innerHTML = '';
    
    let foodTotal = 0;
    let keys = Object.keys(cart);
    
    if (keys.length === 0) {
        container.appendChild(emptyElem);
        emptyElem.style.display = 'block';
    } else {
        emptyElem.style.display = 'none';
        keys.forEach(id => {
            const item = cart[id];
            const itemTotal = item.price * item.qty;
            foodTotal += itemTotal;
            
            const div = document.createElement('div');
            div.className = 'cart-item';
            div.innerHTML = `
                <span class="item-name">${item.qty}x ${item.name}</span>
                <div class="item-details">
                    <span class="item-price">${itemTotal.toFixed(2)}</span>
                </div>
            `;
            container.appendChild(div);
            
            document.getElementById(`qty_${id}`).textContent = item.qty;
        });
    }

    // Resetear contadores en cero si fueron eliminados
    document.querySelectorAll('.qty').forEach(span => {
        const id = span.id.replace('qty_', '');
        if (!cart[id]) span.textContent = '0';
    });

    const grandTotal = ticketTotal + foodTotal;
    document.getElementById('grandTotal').textContent = `Bs ${grandTotal.toFixed(2)}`;
    
    // Preparar JSON para el formulario de pago
    const foodPayload = Object.keys(cart).map(id => ({
        id: parseInt(id),
        qty: cart[id].qty,
        price: cart[id].price
    }));
    document.getElementById('foodDataInput').value = JSON.stringify(foodPayload);
}
</script>

<?php require_once 'footer.php'; ?>