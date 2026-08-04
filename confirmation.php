<?php
require_once 'config.php';

// ============================================
// VERIFICAR QUE EL USUARIO TENGA SESIÓN
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

$purchaseId = isset($_GET['purchase_id']) ? intval($_GET['purchase_id']) : 0;
if ($purchaseId <= 0) {
    header('Location: index.php');
    exit;
}

// ============================================
// OBTENER DATOS DE LA COMPRA DESDE LA BD
// ============================================
$stmt = $pdo->prepare("
    SELECT * FROM purchases 
    WHERE id = ? AND user_id = ? AND status = 'completed'
");
$stmt->execute([$purchaseId, $_SESSION['user_id']]);
$purchase = $stmt->fetch();

if (!$purchase) {
    header('Location: index.php');
    exit;
}

// Obtener datos del showtime y película
$stmt = $pdo->prepare("
    SELECT s.*, m.title, m.poster_url, m.duration, r.name as room_name
    FROM showtimes s
    JOIN movies m ON s.movie_id = m.id
    JOIN rooms r ON s.room_id = r.id
    WHERE s.id = ?
");
$stmt->execute([$showtimeId]);
$showtime = $stmt->fetch();

if (!$showtime) {
    header('Location: index.php');
    exit;
}

// ============================================
// OBTENER TICKET TYPES DESDE purchase_tickets
// ============================================
$stmt = $pdo->prepare("
    SELECT pt.*, tt.name as ticket_type_name, tt.code as ticket_type_code
    FROM purchase_tickets pt
    JOIN ticket_types tt ON pt.ticket_type_id = tt.id
    WHERE pt.purchase_id = ?
");
$stmt->execute([$purchaseId]);
$purchaseTickets = $stmt->fetchAll();

// Procesar asientos desde la BD
$seatsFromDB = $purchase['seats'];
$seatsArray = explode(',', $seatsFromDB);

$accessibleSeats = [];
foreach ($seatsArray as $seat) {
    if (strpos($seat, '♿') !== false) {
        $accessibleSeats[] = str_replace('♿', '', $seat);
    }
}

$ticketCount = count($seatsArray);

// Obtener datos del showtime
$price = getShowtimePrice($showtime);

// Obtener pedidos de comida
$foodOrders = [];
$totalFood = 0;

$stmt = $pdo->prepare("
    SELECT fo.*, fi.name as food_name
    FROM food_orders fo
    JOIN food_items fi ON fo.food_item_id = fi.id
    WHERE fo.user_id = ? AND fo.showtime_id = ? AND fo.status = 'completed'
");
$stmt->execute([$_SESSION['user_id'], $showtimeId]);
$foodOrders = $stmt->fetchAll();

foreach ($foodOrders as $food) {
    $totalFood += $food['total_price'];
}

// Obtener totales de la compra
$subtotal = floatval($purchase['subtotal'] ?? 0);
$taxAmount = floatval($purchase['tax_amount'] ?? 0);
$totalAmount = floatval($purchase['total_amount'] ?? 0);

// Obtener configuración del sitio
$siteConfig = getSiteConfig($pdo);
$pageTitle = "¡Compra Exitosa! - " . ($siteConfig['site_name'] ?? 'Cinema Pro');

// Obtener póster
$tmdb_data = getMovieFromTMDB($showtime['title'], date('Y', strtotime($showtime['show_date'] ?? '')));
$poster_url = $tmdb_data['poster_path'] ?? null;
$display_poster = $poster_url ? 'https://image.tmdb.org/t/p/w300' . $poster_url : ($showtime['poster_url'] ?? '');

// Promociones
$promotions = $showtime['promotions'] ? explode(',', $showtime['promotions']) : [];
$hasMondayPromo = in_array('lunes_mitad', $promotions);
$hasPresale = in_array('preventa', $promotions);

// Idioma
$language = $showtime['language'] ?? 'español';
$languageLabel = $language == 'español' ? 'Español' : 'Subtítulos en Español';

// Método de pago
$paymentMethod = $purchase['payment_method'] ?? 'movil';
$paymentLabels = [
    'movil' => 'Pago Móvil',
    'tarjeta' => 'Tarjeta de Crédito/Débito'
];
$paymentLabel = $paymentLabels[$paymentMethod] ?? $paymentMethod;

// Referencia de pago
$paymentReference = 'N/A';
if (!empty($purchase['payment_data'])) {
    $paymentData = json_decode($purchase['payment_data'], true);
    $paymentReference = is_array($paymentData) && isset($paymentData['reference']) ? $paymentData['reference'] : 'N/A';
}

// Limpiar sesiones
$sessionKeys = [
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
foreach ($sessionKeys as $key) {
    if (isset($_SESSION[$key])) {
        unset($_SESSION[$key]);
    }
}

require_once 'header.php';
?>

<style>
    .confirmation-card {
        background: #14141e;
        border: 1px solid #1e1e2e;
        border-radius: 16px;
        padding: 32px;
        max-width: 640px;
        margin: 0 auto;
        box-shadow: 0 20px 60px rgba(0,0,0,0.5);
    }
    
    .success-icon {
        width: 72px;
        height: 72px;
        background: #22c55e20;
        border: 2px solid #22c55e40;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 32px;
        color: #22c55e;
        margin: 0 auto 20px auto;
    }
    
    .movie-summary {
        background: #0f0f1a;
        border-radius: 12px;
        padding: 16px;
        border: 1px solid #1a1a2e;
        margin-bottom: 20px;
    }
    
    .movie-summary .movie-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #ffffff;
    }
    
    .movie-summary .movie-details {
        display: flex;
        flex-wrap: wrap;
        gap: 4px 16px;
        margin-top: 4px;
        font-size: 0.9rem;
        color: #9ca3af;
    }
    
    .movie-summary .movie-details span {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .movie-summary .movie-details .separator {
        color: #374151;
    }
    
    .movie-summary .movie-details .language-text {
        font-weight: 400;
        color: #9ca3af;
    }
    
    .movie-summary .promo-badge {
        display: inline-block;
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
        margin-top: 6px;
    }
    
    .movie-summary .promo-badge.lunes {
        background: #22c55e20;
        color: #86efac;
        border: 1px solid #22c55e40;
    }
    
    .movie-summary .promo-badge.preventa {
        background: #f59e0b20;
        color: #fbbf24;
        border: 1px solid #f59e0b40;
    }
    
    .movie-summary .promo-badge.none {
        background: #6b728030;
        color: #9ca3af;
        border: 1px solid #6b728040;
    }
    
    .detail-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #1a1a2e;
        font-size: 0.95rem;
    }
    
    .detail-row .label {
        color: #9ca3af;
    }
    
    .detail-row .value {
        color: #e5e7eb;
        font-weight: 500;
    }
    
    .detail-row.total {
        border-top: 2px solid #4f46e5;
        border-bottom: none;
        padding-top: 16px;
        margin-top: 8px;
        font-size: 1.2rem;
    }
    
    .detail-row.total .value {
        color: #22c55e;
        font-weight: 700;
    }
    
    .tax-row {
        display: flex;
        justify-content: space-between;
        padding: 4px 0;
        font-size: 0.9rem;
        color: #9ca3af;
        border-bottom: 1px solid #1a1a2e;
    }
    
    .tax-row .tax-label {
        color: #9ca3af;
    }
    
    .tax-row .tax-value {
        color: #fbbf24;
        font-weight: 600;
    }
    
    .food-item {
        display: flex;
        justify-content: space-between;
        padding: 4px 0 4px 20px;
        font-size: 0.9rem;
        color: #9ca3af;
        border-bottom: 1px solid #111827;
    }
    
    .food-item .food-name {
        color: #d1d5db;
    }
    
    .food-item .food-total {
        color: #e5e7eb;
        font-weight: 500;
    }
    
    .btn-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-top: 24px;
    }
    
    .btn-actions .btn-primary {
        background: linear-gradient(135deg, #4f46e5, #7c3aed);
        color: white;
        padding: 12px;
        border-radius: 8px;
        font-weight: 700;
        text-align: center;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        text-decoration: none;
        display: block;
    }
    
    .btn-actions .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(79, 70, 229, 0.3);
    }
    
    .btn-actions .btn-secondary {
        background: transparent;
        border: 1px solid #374151;
        color: #9ca3af;
        padding: 12px;
        border-radius: 8px;
        font-weight: 600;
        text-align: center;
        transition: all 0.3s ease;
        text-decoration: none;
        display: block;
    }
    
    .btn-actions .btn-secondary:hover {
        border-color: #4f46e5;
        color: #e5e7eb;
        background: rgba(79, 70, 229, 0.1);
    }
    
    .confirmation-poster {
        width: 100px;
        height: 150px;
        object-fit: cover;
        border-radius: 8px;
        background: #1a1a2e;
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(0,0,0,0.4);
    }
    
    .payment-box {
        background: #0f0f1a;
        border-radius: 12px;
        padding: 16px;
        border: 1px solid #1a1a2e;
        margin-top: 16px;
    }
    
    .payment-box .payment-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 8px;
        border-bottom: 1px solid #1a1a2e;
    }
    
    .payment-box .payment-header .payment-label {
        color: #9ca3af;
        font-size: 0.9rem;
    }
    
    .payment-box .payment-header .payment-value {
        color: #e5e7eb;
        font-weight: 600;
        font-size: 0.95rem;
    }
    
    .payment-box .payment-detail {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        font-size: 0.85rem;
        color: #9ca3af;
        border-bottom: 1px solid #111827;
    }
    
    .payment-box .payment-detail:last-child {
        border-bottom: none;
    }
    
    .payment-box .payment-detail .detail-label {
        color: #6b7280;
    }
    
    .payment-box .payment-detail .detail-value {
        color: #d1d5db;
        font-weight: 500;
    }
    
    .info-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 6px;
        font-size: 0.85rem;
        color: #9ca3af;
    }
    
    .info-tags .tag-item {
        color: #9ca3af;
    }
    
    .info-tags .tag-item strong {
        color: #e5e7eb;
        font-weight: 600;
    }
    
    .purchase-id {
        font-family: 'Courier New', monospace;
        background: #1a1a2e;
        padding: 2px 10px;
        border-radius: 4px;
        color: #818cf8;
        font-size: 0.85rem;
    }

    .seat-accessible-badge {
        display: inline-block;
        background: #0284c720;
        color: #38bdf8;
        border: 1px solid #0284c740;
        padding: 0px 8px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
        margin-left: 4px;
    }
    
    .seat-list {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 12px;
        margin-top: 4px;
    }
    
    .seat-item {
        display: inline-flex;
        align-items: center;
        gap: 2px;
        font-weight: 500;
        color: #e5e7eb;
        background: #1a1a2e;
        padding: 2px 10px 2px 8px;
        border-radius: 6px;
        border: 1px solid #2a2a3e;
    }
    
    .seat-item.accessible {
        color: #38bdf8;
        border-color: #0284c740;
        background: #0f1a2e;
    }
    
    .seat-item .accessible-icon {
        font-size: 0.8rem;
    }
    
    .seat-item .seat-label {
        font-weight: 600;
    }
    
    .ticket-type-badge {
        display: inline-block;
        padding: 1px 10px;
        border-radius: 10px;
        font-size: 0.7rem;
        font-weight: 600;
        margin-left: 4px;
    }
    .ticket-type-badge.adult {
        background: #4f46e520;
        color: #818cf8;
        border: 1px solid #4f46e540;
    }
    .ticket-type-badge.child {
        background: #22c55e20;
        color: #86efac;
        border: 1px solid #22c55e40;
    }
    .ticket-type-badge.senior {
        background: #f59e0b20;
        color: #fbbf24;
        border: 1px solid #f59e0b40;
    }

    @media (max-width: 640px) {
        .confirmation-card {
            padding: 16px;
            margin: 0 8px;
            border-radius: 12px;
        }
        
        .success-icon {
            width: 48px;
            height: 48px;
            font-size: 20px;
            margin-bottom: 14px;
        }
        
        .confirmation-card h1 {
            font-size: 1.2rem;
        }
        
        .confirmation-card p {
            font-size: 0.8rem;
            margin-bottom: 14px;
        }
        
        .movie-summary {
            padding: 12px;
        }
        
        .movie-summary .flex {
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        
        .movie-summary .flex .flex-1 {
            width: 100%;
            text-align: center;
        }
        
        .confirmation-poster {
            width: 80px;
            height: 120px;
        }
        
        .movie-summary .movie-title {
            font-size: 0.95rem;
            text-align: center;
        }
        
        .movie-summary .movie-details {
            font-size: 0.8rem;
            gap: 2px 8px;
            justify-content: center;
        }
        
        .movie-summary .promo-badge {
            font-size: 0.6rem;
            padding: 1px 10px;
        }
        
        .info-tags {
            font-size: 0.75rem;
            gap: 6px;
            justify-content: center;
        }
        
        .detail-row {
            font-size: 0.82rem;
            padding: 6px 0;
            flex-direction: column;
            align-items: flex-start;
            gap: 2px;
        }
        
        .detail-row .value {
            width: 100%;
        }
        
        .detail-row.total {
            font-size: 0.95rem;
            padding-top: 12px;
            flex-direction: row;
            align-items: center;
        }
        
        .tax-row {
            font-size: 0.78rem;
            padding: 3px 0;
        }
        
        .food-item {
            font-size: 0.78rem;
            padding: 3px 0 3px 14px;
        }
        
        .payment-box {
            padding: 12px;
            margin-top: 12px;
        }
        
        .payment-box .payment-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
            padding-bottom: 6px;
        }
        
        .payment-box .payment-header .payment-label {
            font-size: 0.8rem;
        }
        
        .payment-box .payment-header .payment-value {
            font-size: 0.85rem;
        }
        
        .payment-box .payment-detail {
            font-size: 0.75rem;
            padding: 4px 0;
        }
        
        .btn-actions {
            gap: 8px;
            margin-top: 16px;
        }
        
        .btn-actions .btn-primary,
        .btn-actions .btn-secondary {
            padding: 10px;
            font-size: 0.85rem;
        }
        
        .mt-4.p-3 {
            padding: 10px;
            font-size: 0.7rem;
        }
        
        .seat-list {
            gap: 4px 8px;
        }
        
        .seat-item {
            padding: 1px 8px 1px 6px;
            font-size: 0.8rem;
        }
        
        .ticket-type-badge {
            font-size: 0.6rem;
            padding: 0px 6px;
        }
    }
    
    @media (max-width: 400px) {
        .confirmation-card {
            padding: 12px;
            margin: 0 4px;
        }
        
        .confirmation-poster {
            width: 65px;
            height: 98px;
        }
        
        .movie-summary .movie-title {
            font-size: 0.85rem;
        }
        
        .movie-summary .movie-details {
            font-size: 0.7rem;
        }
        
        .detail-row {
            font-size: 0.75rem;
            padding: 4px 0;
        }
        
        .detail-row.total {
            font-size: 0.85rem;
            padding-top: 10px;
        }
        
        .tax-row {
            font-size: 0.7rem;
            padding: 2px 0;
        }
        
        .payment-box .payment-detail {
            font-size: 0.7rem;
            padding: 3px 0;
        }
        
        .btn-actions .btn-primary,
        .btn-actions .btn-secondary {
            padding: 8px;
            font-size: 0.75rem;
        }
        
        .seat-item {
            font-size: 0.7rem;
            padding: 1px 6px 1px 4px;
        }
    }
</style>

<div class="container mx-auto px-4 py-8 max-w-4xl">
    <div class="confirmation-card">
        <!-- Icono de éxito -->
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>
        
        <h1 class="text-2xl font-bold text-center text-white mb-2">¡Compra Confirmada!</h1>
        <p class="text-center text-gray-400 text-sm mb-6">
            Tu compra se ha realizado con éxito. Revisa los detalles a continuación.
        </p>
        
        <!-- ID de Compra -->
        <div class="text-center mb-4">
            <span class="text-xs text-gray-500">ID de Compra</span>
            <div class="purchase-id">#<?= str_pad($purchase['id'], 8, '0', STR_PAD_LEFT) ?></div>
        </div>
        
        <!-- Resumen de la Película -->
        <div class="movie-summary">
            <div class="flex gap-4">
                <?php if ($display_poster): ?>
                    <img src="<?= htmlspecialchars($display_poster) ?>" 
                         alt="<?= htmlspecialchars($showtime['title']) ?>"
                         class="confirmation-poster">
                <?php else: ?>
                    <div class="confirmation-poster flex items-center justify-center text-4xl bg-gray-800">
                        🎬
                    </div>
                <?php endif; ?>
                
                <div class="flex-1 min-w-0">
                    <div class="movie-title"><?= htmlspecialchars($showtime['title']) ?></div>
                    
                    <div class="movie-details">
                        <span>Idioma: <span class="language-text"><?= htmlspecialchars($languageLabel) ?></span></span>
                    </div>
                    
                    <div class="movie-details">
                        <span><?= htmlspecialchars($showtime['room_name']) ?></span>
                        <span class="separator">·</span>
                        <span><?= formatDateShort($showtime['show_date']) ?></span>
                        <span class="separator">·</span>
                        <span><?= formatTimeVenezuela($showtime['show_time']) ?></span>
                    </div>
                    
                    <div class="movie-details">
                        <?php if ($hasMondayPromo): ?>
                            <span class="promo-badge lunes">Lunes ½ Precio</span>
                        <?php endif; ?>
                        <?php if ($hasPresale): ?>
                            <span class="promo-badge preventa">Preventa</span>
                        <?php endif; ?>
                        <?php if (!$hasMondayPromo && !$hasPresale): ?>
                            <span class="promo-badge none">Sin promociones</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="info-tags">
                        <span class="tag-item"><strong><?= $ticketCount ?></strong> boleto<?= $ticketCount > 1 ? 's' : '' ?></span>
                        <?php if (!empty($foodOrders)): ?>
                            <span class="tag-item"><strong><?= count($foodOrders) ?></strong> producto<?= count($foodOrders) > 1 ? 's' : '' ?> de comida</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Detalle de la Compra -->
        <div class="bg-[#0f0f1a] p-4 rounded-xl border border-[#1a1a2e]">
            <h3 class="text-sm font-semibold text-gray-400 mb-3">📋 Detalle de tu compra</h3>
            
            <!-- ✅ BOLETOS POR TIPO -->
            <?php if (!empty($purchaseTickets)): ?>
                <div class="mb-2">
                    <p class="text-xs text-gray-500 font-semibold uppercase mb-1">🎟️ Boletos</p>
                    <?php 
                    $ticketTypes = [];
                    foreach ($purchaseTickets as $pt) {
                        $code = $pt['ticket_type_code'] ?? 'adult';
                        if (!isset($ticketTypes[$code])) {
                            $ticketTypes[$code] = ['count' => 0, 'name' => $pt['ticket_type_name'] ?? ucfirst($code), 'price' => $pt['price']];
                        }
                        $ticketTypes[$code]['count']++;
                    }
                    foreach ($ticketTypes as $code => $info): 
                        $badgeClass = $code == 'adult' ? 'adult' : ($code == 'child' ? 'child' : 'senior');
                    ?>
                        <div class="detail-row" style="border-bottom: 1px solid #1a1a2e;">
                            <span class="label">
                                <?= htmlspecialchars($info['name']) ?> x<?= $info['count'] ?>
                                <span class="ticket-type-badge <?= $badgeClass ?>"><?= $code == 'adult' ? '👤' : ($code == 'child' ? '🧒' : '👴') ?></span>
                            </span>
                            <span class="value"><?= formatCurrency($info['count'] * $info['price'], $siteConfig) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Asientos -->
            <div class="detail-row" style="flex-direction: column; align-items: stretch; gap: 4px; border-bottom: 1px solid #1a1a2e;">
                <span class="label">🎫 Asientos</span>
                <div class="seat-list">
                    <?php foreach ($seatsArray as $seat): 
                        $isAccessible = strpos($seat, '♿') !== false;
                        $cleanSeat = str_replace('♿', '', $seat);
                    ?>
                        <span class="seat-item <?= $isAccessible ? 'accessible' : '' ?>">
                            <span class="seat-label"><?= htmlspecialchars($cleanSeat) ?></span>
                            <?php if ($isAccessible): ?>
                                <span class="accessible-icon">♿</span>
                                <span class="seat-accessible-badge">Accesible</span>
                            <?php endif; ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Comida -->
            <?php if (!empty($foodOrders)): ?>
                <div class="mt-2 pt-2 border-t border-[#1a1a2e]">
                    <p class="text-sm font-semibold text-gray-400 mb-1">🍿 Comida</p>
                    <?php foreach ($foodOrders as $food): ?>
                        <div class="food-item">
                            <span class="food-name"><?= $food['quantity'] ?> x <?= htmlspecialchars($food['food_name']) ?></span>
                            <span class="food-total"><?= formatCurrency($food['total_price'], $siteConfig) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- ✅ SUBTOTAL, IVA Y TOTAL -->
            <div class="mt-2 pt-2 border-t border-[#1a1a2e]">
                <div class="detail-row" style="border-bottom: 1px solid #1a1a2e;">
                    <span class="label">Subtotal</span>
                    <span class="value"><?= formatCurrency($subtotal, $siteConfig) ?></span>
                </div>
                <div class="tax-row">
                    <span class="tax-label">IVA (<?= $purchase['tax_rate'] ?? 16 ?>%)</span>
                    <span class="tax-value"><?= formatCurrency($taxAmount, $siteConfig) ?></span>
                </div>
                <div class="detail-row total" style="border-top: 2px solid #4f46e5; margin-top: 4px; padding-top: 12px;">
                    <span class="label font-bold">💰 Total Pagado</span>
                    <span class="value"><?= formatCurrency($totalAmount, $siteConfig) ?></span>
                </div>
            </div>
        </div>
        
        <!-- Método de Pago -->
        <div class="payment-box">
            <div class="payment-header">
                <span class="payment-label">💳 Método de Pago</span>
                <span class="payment-value"><?= htmlspecialchars($paymentLabel) ?></span>
            </div>
            
            <div class="payment-detail">
                <span class="detail-label">Referencia</span>
                <span class="detail-value"><?= htmlspecialchars($paymentReference) ?></span>
            </div>
            
            <?php if ($paymentMethod === 'movil'): ?>
                <div class="payment-detail">
                    <span class="detail-label">Banco</span>
                    <span class="detail-value">Banco de Venezuela</span>
                </div>
                <div class="payment-detail">
                    <span class="detail-label">Cuenta</span>
                    <span class="detail-value">0102-0123-45-1234567890</span>
                </div>
                <div class="payment-detail">
                    <span class="detail-label">Teléfono</span>
                    <span class="detail-value">0412-1234567</span>
                </div>
            <?php elseif ($paymentMethod === 'tarjeta'): ?>
                <div class="payment-detail">
                    <span class="detail-label">Tarjeta</span>
                    <span class="detail-value">•••• •••• •••• 1234</span>
                </div>
                <div class="payment-detail">
                    <span class="detail-label">Titular</span>
                    <span class="detail-value">Cliente Prueba</span>
                </div>
            <?php endif; ?>
            
            <div class="payment-detail">
                <span class="detail-label">Fecha de Pago</span>
                <span class="detail-value"><?= date('d/m/Y H:i:s') ?></span>
            </div>
        </div>
        
        <!-- Información adicional -->
        <div class="mt-4 p-3 bg-indigo-600/10 border border-indigo-500/20 rounded-xl text-xs text-gray-400">
            <p class="flex items-center gap-2">
                <i class="fas fa-info-circle text-indigo-400"></i>
                <span>Se ha enviado un correo electrónico con los detalles de tu compra a <strong class="text-white"><?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></strong>.</span>
            </p>
            <p class="flex items-center gap-2 mt-1">
                <i class="fas fa-qrcode text-indigo-400"></i>
                <span>Presenta este código en taquilla: <strong class="text-white font-mono">#<?= str_pad($purchase['id'], 6, '0', STR_PAD_LEFT) ?>-<?= str_pad($ticketCount, 2, '0', STR_PAD_LEFT) ?></strong></span>
            </p>
            <?php if (!empty($accessibleSeats)): ?>
                <p class="flex items-center gap-2 mt-1 text-sky-400">
                    <i class="fas fa-wheelchair"></i>
                    <span>Asientos de accesibilidad: <strong class="text-white"><?= implode(', ', $accessibleSeats) ?></strong></span>
                </p>
            <?php endif; ?>
        </div>
        
        <div class="btn-actions">
            <a href="index.php" class="btn-primary">
                <i class="fas fa-home mr-2"></i> Volver al Inicio
            </a>
            <a href="movie_detail.php?id=<?= $showtime['movie_id'] ?>" class="btn-secondary">
                <i class="fas fa-film mr-2"></i> Ver más funciones de esta película
            </a>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>