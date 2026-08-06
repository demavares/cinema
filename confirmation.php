<?php
require_once 'config.php';

// ============================================
// VERIFICAR QUE EL USUARIO TENGA SESIÓN
// ============================================
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$purchaseId = isset($_GET['purchase_id']) ? intval($_GET['purchase_id']) : 0;

if ($purchaseId <= 0 && isset($_SESSION['last_order_id'])) {
    $purchaseId = intval($_SESSION['last_order_id']);
}

if ($purchaseId <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT showtime_id FROM purchases WHERE id = ? AND user_id = ?");
$stmt->execute([$purchaseId, $_SESSION['user_id']]);
$purchaseData = $stmt->fetch();

if (!$purchaseData) {
    header('Location: index.php');
    exit;
}

$showtimeId = $purchaseData['showtime_id'];

// ============================================
// ✅ LIMPIAR SESIONES DE COMPRA
// ============================================
clearPurchaseSession($showtimeId);

// ============================================
// OBTENER DATOS DE LA COMPRA
// ============================================
$stmt = $pdo->prepare("SELECT * FROM purchases WHERE id = ? AND user_id = ? AND status = 'completed'");
$stmt->execute([$purchaseId, $_SESSION['user_id']]);
$purchase = $stmt->fetch();

if (!$purchase) {
    header('Location: index.php');
    exit;
}

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
// ✅ OBTENER TICKET TYPES DESDE purchase_tickets
// ============================================
$stmt = $pdo->prepare("
    SELECT pt.*, tt.name as ticket_type_name, tt.code as ticket_type_code
    FROM purchase_tickets pt
    JOIN ticket_types tt ON pt.ticket_type_id = tt.id
    WHERE pt.purchase_id = ?
");
$stmt->execute([$purchaseId]);
$purchaseTickets = $stmt->fetchAll();

$seatsFromDB = $purchase['seats'];
$seatsArray = explode(',', $seatsFromDB);

$accessibleSeats = [];
foreach ($seatsArray as $seat) {
    if (strpos($seat, '♿') !== false) {
        $accessibleSeats[] = str_replace('♿', '', $seat);
    }
}

$ticketCount = count($seatsArray);

// ============================================
// ✅ OBTENER PEDIDOS DE COMIDA FILTRADOS POR purchase_id
// ============================================
$foodOrders = [];
$totalFood = 0;

$stmt = $pdo->prepare("
    SELECT fo.*, fi.name as food_name
    FROM food_orders fo
    JOIN food_items fi ON fo.food_item_id = fi.id
    WHERE fo.purchase_id = ? AND fo.status = 'completed'
");
$stmt->execute([$purchaseId]);
$foodOrders = $stmt->fetchAll();

foreach ($foodOrders as $food) {
    $totalFood += $food['total_price'];
}

// ============================================
// ✅ CALCULAR TOTALES CORRECTAMENTE
// ============================================
$subtotal = floatval($purchase['subtotal'] ?? 0);
$taxAmount = floatval($purchase['tax_amount'] ?? 0);
$totalAmount = floatval($purchase['total_amount'] ?? 0);
$taxRate = floatval($purchase['tax_rate'] ?? 16);

// ✅ CALCULAR DESGLOSE DE BOLETOS
$ticketTypes = [];
$ticketTotal = 0;
foreach ($purchaseTickets as $pt) {
    $code = $pt['ticket_type_code'] ?? 'adult';
    $name = $pt['ticket_type_name'] ?? ucfirst($code);
    $price = floatval($pt['price']);
    
    if (!isset($ticketTypes[$code])) {
        $ticketTypes[$code] = [
            'count' => 0,
            'name' => $name,
            'price' => $price,
            'total' => 0
        ];
    }
    $ticketTypes[$code]['count']++;
    $ticketTypes[$code]['total'] += $price;
    $ticketTotal += $price;
}

// ✅ CALCULAR DESGLOSE DE COMIDA AGRUPADA
$groupedFood = [];
$foodTotal = 0;
foreach ($foodOrders as $food) {
    $key = $food['food_name'];
    if (!isset($groupedFood[$key])) {
        $groupedFood[$key] = [
            'name' => $food['food_name'],
            'quantity' => 0,
            'total' => 0,
            'unit_price' => $food['unit_price']
        ];
    }
    $groupedFood[$key]['quantity'] += $food['quantity'];
    $groupedFood[$key]['total'] += $food['total_price'];
    $foodTotal += $food['total_price'];
}

$siteConfig = getSiteConfig($pdo);
$pageTitle = "¡Compra Exitosa! - " . ($siteConfig['site_name'] ?? 'Cinema Pro');

$tmdb_data = getMovieFromTMDB($showtime['title'], date('Y', strtotime($showtime['show_date'] ?? '')));
$poster_url = $tmdb_data['poster_path'] ?? null;
$display_poster = $poster_url ? 'https://image.tmdb.org/t/p/w300' . $poster_url : ($showtime['poster_url'] ?? '');

$promotions = $showtime['promotions'] ? explode(',', $showtime['promotions']) : [];
$hasMondayPromo = in_array('lunes_mitad', $promotions);
$hasPresale = in_array('preventa', $promotions);

$language = $showtime['language'] ?? 'español';
$languageLabel = $language == 'español' ? 'Español' : 'Subtítulos en Español';

$paymentMethod = $purchase['payment_method'] ?? 'movil';
$paymentLabels = ['movil' => 'Pago Móvil', 'tarjeta' => 'Tarjeta de Crédito/Débito'];
$paymentLabel = $paymentLabels[$paymentMethod] ?? $paymentMethod;

$paymentReference = 'N/A';
if (!empty($purchase['payment_data'])) {
    $paymentData = json_decode($purchase['payment_data'], true);
    $paymentReference = is_array($paymentData) && isset($paymentData['reference']) ? $paymentData['reference'] : 'N/A';
}

$currency_symbol = $siteConfig['currency_symbol'] ?? '$';
$currency_position = $siteConfig['currency_position'] ?? 'left';
$thousands_separator = $siteConfig['thousands_separator'] ?? '.';
$decimal_separator = $siteConfig['decimal_separator'] ?? ',';
$decimal_places = intval($siteConfig['decimal_places'] ?? 2);

require_once 'header.php';
?>

<style>
/* ============================================
   ESTILOS UNIFICADOS - Fondo blanco y texto oscuro
   ============================================ */
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

.confirmation-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 32px;
    max-width: 680px;
    margin: 0 auto;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08);
}

.success-icon {
    width: 72px;
    height: 72px;
    background: #dcfce7;
    border: 2px solid #86efac;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    color: #16a34a;
    margin: 0 auto 20px auto;
}

.confirmation-title {
    color: #0f172a;
    font-size: 1.75rem;
    font-weight: 800;
    text-align: center;
}

.confirmation-subtitle {
    color: #475569;
    text-align: center;
    font-size: 0.95rem;
    margin-bottom: 24px;
}

.movie-summary {
    background: #f8fafc;
    border-radius: 12px;
    padding: 16px;
    border: 1px solid #e2e8f0;
    margin-bottom: 20px;
}

.movie-summary .movie-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #0f172a;
}

.movie-summary .movie-details {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 16px;
    margin-top: 4px;
    font-size: 0.9rem;
    color: #475569;
}

.movie-summary .movie-details span {
    display: flex;
    align-items: center;
    gap: 4px;
}

.movie-summary .movie-details .separator {
    color: #cbd5e1;
}

.movie-summary .movie-details .language-text {
    font-weight: 400;
    color: #475569;
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
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #86efac;
}

.movie-summary .promo-badge.preventa {
    background: #fef3c7;
    color: #b45309;
    border: 1px solid #fde68a;
}

.movie-summary .promo-badge.none {
    background: #f1f5f9;
    color: #64748b;
    border: 1px solid #e2e8f0;
}

.detail-section {
    background: #f8fafc;
    border-radius: 12px;
    padding: 16px;
    border: 1px solid #e2e8f0;
}

.detail-section .detail-title {
    font-size: 0.8rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 10px;
}

/* ============================================
   ✅ ESTILOS PARA BOLETOS Y COMIDA
   ============================================ */
.ticket-row {
    display: flex;
    justify-content: space-between;
    padding: 4px 0;
    font-size: 0.95rem;
    border-bottom: 1px solid #f1f5f9;
}

.ticket-row:last-child {
    border-bottom: none;
}

.ticket-row .ticket-label {
    color: #475569;
}

.ticket-row .ticket-price {
    color: #0f172a;
    font-weight: 600;
}

.ticket-type-badge {
    display: inline-block;
    padding: 1px 10px;
    border-radius: 10px;
    font-size: 0.65rem;
    font-weight: 600;
    margin-left: 4px;
}

.ticket-type-badge.adult {
    background: #eef2ff;
    color: #4f46e5;
    border: 1px solid #c7d2fe;
}

.ticket-type-badge.child {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #86efac;
}

.ticket-type-badge.senior {
    background: #fef3c7;
    color: #b45309;
    border: 1px solid #fde68a;
}

.food-item {
    display: flex;
    justify-content: space-between;
    padding: 4px 0 4px 0;
    font-size: 0.9rem;
    color: #475569;
    border-bottom: 1px solid #f1f5f9;
}

.food-item:last-child {
    border-bottom: none;
}

.food-item .food-name {
    color: #0f172a;
}

.food-item .food-total {
    color: #0f172a;
    font-weight: 500;
}

/* ============================================
   ✅ ESTILOS PARA TOTALES
   ============================================ */
.totals-section {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 2px solid #e2e8f0;
}

.total-row {
    display: flex;
    justify-content: space-between;
    padding: 4px 0;
    font-size: 0.95rem;
}

.total-row .total-label {
    color: #475569;
}

.total-row .total-value {
    color: #0f172a;
    font-weight: 600;
}

.total-row.tax-row .total-value {
    color: #b45309;
    font-weight: 600;
}

.total-row.grand-total {
    border-top: 2px solid #4f46e5;
    padding-top: 12px;
    margin-top: 4px;
    font-size: 1.15rem;
}

.total-row.grand-total .total-label {
    color: #0f172a;
    font-weight: 700;
}

.total-row.grand-total .total-value {
    color: #16a34a;
    font-weight: 700;
}

/* ============================================
   FIN ESTILOS
   ============================================ */
.seat-accessible-badge {
    display: inline-block;
    background: #dbeafe;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
    padding: 0px 8px;
    border-radius: 12px;
    font-size: 0.65rem;
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
    font-weight: 600;
    color: #0f172a;
    background: #f1f5f9;
    padding: 2px 10px 2px 8px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    font-size: 0.85rem;
}

.seat-item.accessible {
    color: #1d4ed8;
    border-color: #bfdbfe;
    background: #dbeafe;
}

.seat-item .accessible-icon {
    font-size: 0.8rem;
}

.seat-item .seat-label {
    font-weight: 700;
}

.payment-box {
    background: #f8fafc;
    border-radius: 12px;
    padding: 16px;
    border: 1px solid #e2e8f0;
    margin-top: 16px;
}

.payment-box .payment-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 8px;
    border-bottom: 1px solid #e2e8f0;
}

.payment-box .payment-header .payment-label {
    color: #475569;
    font-size: 0.9rem;
}

.payment-box .payment-header .payment-value {
    color: #0f172a;
    font-weight: 600;
    font-size: 0.95rem;
}

.payment-box .payment-detail {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    font-size: 0.85rem;
    color: #475569;
    border-bottom: 1px solid #f1f5f9;
}

.payment-box .payment-detail:last-child {
    border-bottom: none;
}

.payment-box .payment-detail .detail-label {
    color: #94a3b8;
}

.payment-box .payment-detail .detail-value {
    color: #0f172a;
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
    color: #ffffff !important;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 1rem;
    text-align: center;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: block;
}

.btn-actions .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(79, 70, 229, 0.25);
}

.btn-actions .btn-secondary {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #334155 !important;
    padding: 11px 20px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.95rem;
    text-align: center;
    transition: all 0.3s ease;
    text-decoration: none;
    display: block;
}

.btn-actions .btn-secondary:hover {
    border-color: #6366f1;
    color: #4f46e5 !important;
    background: #eef2ff;
}

.confirmation-poster {
    width: 80px;
    height: 120px;
    object-fit: cover;
    border-radius: 8px;
    background: #f1f5f9;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.purchase-id {
    font-family: 'Courier New', monospace;
    background: #f1f5f9;
    padding: 4px 14px;
    border-radius: 6px;
    color: #4f46e5;
    font-size: 0.9rem;
    font-weight: 700;
    border: 1px solid #e2e8f0;
}

.info-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 6px;
    font-size: 0.85rem;
    color: #475569;
}

.info-tags .tag-item {
    color: #475569;
}

.info-tags .tag-item strong {
    color: #0f172a;
    font-weight: 600;
}

.info-box {
    padding: 12px 16px;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    border-radius: 10px;
    font-size: 0.8rem;
    color: #3730a3;
}

.info-box strong {
    color: #1e1b4b;
}

.info-box .text-sky-400 {
    color: #0369a1;
}

@media (max-width: 640px) {
    .confirmation-card {
        padding: 20px;
        margin: 0 8px;
        border-radius: 12px;
    }

    .success-icon {
        width: 56px;
        height: 56px;
        font-size: 24px;
        margin-bottom: 14px;
    }

    .confirmation-title {
        font-size: 1.3rem;
    }

    .confirmation-subtitle {
        font-size: 0.85rem;
        margin-bottom: 18px;
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
        width: 70px;
        height: 105px;
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

    .ticket-row {
        font-size: 0.85rem;
        padding: 4px 0;
        flex-direction: column;
        align-items: flex-start;
        gap: 2px;
    }

    .ticket-row .ticket-price {
        width: 100%;
    }

    .food-item {
        font-size: 0.8rem;
        padding: 3px 0;
        flex-direction: column;
        align-items: flex-start;
        gap: 2px;
    }

    .food-item .food-total {
        width: 100%;
    }

    .total-row {
        font-size: 0.85rem;
        padding: 3px 0;
        flex-direction: column;
        align-items: flex-start;
        gap: 2px;
    }

    .total-row .total-value {
        width: 100%;
    }

    .total-row.grand-total {
        font-size: 1rem;
        padding-top: 10px;
        flex-direction: row;
        align-items: center;
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
        margin-top: 18px;
    }

    .btn-actions .btn-primary,
    .btn-actions .btn-secondary {
        padding: 10px;
        font-size: 0.85rem;
    }

    .info-box {
        padding: 10px 14px;
        font-size: 0.7rem;
    }

    .seat-list {
        gap: 4px 8px;
    }

    .seat-item {
        padding: 1px 8px 1px 6px;
        font-size: 0.75rem;
    }

    .ticket-type-badge {
        font-size: 0.55rem;
        padding: 0px 6px;
    }

    .purchase-id {
        font-size: 0.8rem;
        padding: 3px 10px;
    }
}

@media (max-width: 400px) {
    .confirmation-card {
        padding: 14px;
        margin: 0 4px;
    }

    .confirmation-poster {
        width: 60px;
        height: 90px;
    }

    .movie-summary .movie-title {
        font-size: 0.85rem;
    }

    .movie-summary .movie-details {
        font-size: 0.7rem;
    }

    .total-row.grand-total {
        font-size: 0.9rem;
        padding-top: 8px;
    }

    .ticket-type-badge {
        font-size: 0.5rem;
        padding: 0px 5px;
    }
}
</style>

<div class="container mx-auto px-4 py-6 sm:py-10 max-w-4xl">
    <div class="confirmation-card">
        <!-- Icono de éxito -->
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>

        <h1 class="confirmation-title">¡Compra Confirmada!</h1>
        <p class="confirmation-subtitle">
            Tu compra se ha realizado con éxito. Revisa los detalles a continuación.
        </p>

        <!-- ID de Compra -->
        <div class="text-center mb-4">
            <span class="text-xs text-gray-500 font-semibold uppercase tracking-wider">ID de Compra</span>
            <div class="purchase-id inline-block mt-1">#<?= str_pad($purchase['id'], 8, '0', STR_PAD_LEFT) ?></div>
        </div>

        <!-- Resumen de la Película -->
        <div class="movie-summary">
            <div class="flex gap-4">
                <?php if ($display_poster): ?>
                    <img src="<?= htmlspecialchars($display_poster) ?>"
                         alt="<?= htmlspecialchars($showtime['title']) ?>"
                         class="confirmation-poster">
                <?php else: ?>
                    <div class="confirmation-poster flex items-center justify-center text-4xl bg-gray-100 text-gray-400">
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
                            <span class="promo-badge lunes">🌙 Lunes ½ Precio</span>
                        <?php endif; ?>
                        <?php if ($hasPresale): ?>
                            <span class="promo-badge preventa">🎫 Preventa</span>
                        <?php endif; ?>
                        <?php if (!$hasMondayPromo && !$hasPresale): ?>
                            <span class="promo-badge none">Sin promociones</span>
                        <?php endif; ?>
                    </div>

                    <div class="info-tags">
                        <span class="tag-item"><strong><?= $ticketCount ?></strong> boleto<?= $ticketCount > 1 ? 's' : '' ?></span>
                        <?php if (!empty($foodOrders)): ?>
                            <span class="tag-item"><strong><?= count($groupedFood) ?></strong> producto<?= count($groupedFood) > 1 ? 's' : '' ?> de comida</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- ✅ DETALLE DE TU COMPRA - NUEVO DISEÑO       -->
        <!-- ============================================ -->
        <div class="detail-section">
            <p class="detail-title">📋 Detalle de tu compra</p>

            <!-- ✅ BOLETOS POR TIPO - DESGLOSADOS CORRECTAMENTE -->
            <?php if (!empty($ticketTypes)): ?>
                <div class="mb-2">
                    <?php foreach ($ticketTypes as $code => $info): 
                        $badgeClass = $code == 'adult' ? 'adult' : ($code == 'child' ? 'child' : 'senior');
                        $icon = $code == 'adult' ? '👤' : ($code == 'child' ? '🧒' : '👴');
                    ?>
                        <div class="ticket-row">
                            <span class="ticket-label">
                                <?= htmlspecialchars($info['name']) ?> x<?= $info['count'] ?>
                                <span class="ticket-type-badge <?= $badgeClass ?>"><?= $icon ?></span>
                            </span>
                            <span class="ticket-price"><?= formatCurrency($info['total'], $siteConfig) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- ✅ ASIENTOS -->
            <div class="mt-3 pt-3 border-t border-[#e2e8f0]">
                <p class="text-sm font-semibold text-gray-700 mb-1">🎫 Asientos</p>
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

            <!-- Separador -->
            <div class="my-3 border-t border-[#e2e8f0]"></div>

            <!-- ✅ COMIDA - DESGLOSADA -->
            <?php if (!empty($groupedFood)): ?>
                <div class="mt-2">
                    <p class="text-sm font-semibold text-gray-700 mb-1">🍿 Comida</p>
                    <?php foreach ($groupedFood as $item): ?>
                        <div class="food-item">
                            <span class="food-name"><?= $item['quantity'] ?> x <?= htmlspecialchars($item['name']) ?></span>
                            <span class="food-total"><?= formatCurrency($item['total'], $siteConfig) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- ✅ SUBTOTAL, IVA Y TOTAL -->
            <div class="totals-section">
                <!-- Subtotal -->
                <div class="total-row">
                    <span class="total-label">Subtotal</span>
                    <span class="total-value"><?= formatCurrency($subtotal, $siteConfig) ?></span>
                </div>

                <!-- IVA -->
                <div class="total-row tax-row">
                    <span class="total-label">IVA (<?= number_format($taxRate, 2) ?>%)</span>
                    <span class="total-value"><?= formatCurrency($taxAmount, $siteConfig) ?></span>
                </div>

                <!-- Total Pagado -->
                <div class="total-row grand-total">
                    <span class="total-label">💰 Total Pagado</span>
                    <span class="total-value"><?= formatCurrency($totalAmount, $siteConfig) ?></span>
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
        <div class="info-box mt-4">
            <p class="flex items-center gap-2">
                <i class="fas fa-info-circle text-indigo-400"></i>
                <span>Se ha enviado un correo electrónico con los detalles de tu compra a <strong><?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></strong>.</span>
            </p>
            <p class="flex items-center gap-2 mt-1">
                <i class="fas fa-qrcode text-indigo-400"></i>
                <span>Presenta este código en taquilla: <strong class="font-mono">#<?= str_pad($purchase['id'], 6, '0', STR_PAD_LEFT) ?>-<?= str_pad($ticketCount, 2, '0', STR_PAD_LEFT) ?></strong></span>
            </p>
            <?php if (!empty($accessibleSeats)): ?>
                <p class="flex items-center gap-2 mt-1 text-sky-600">
                    <i class="fas fa-wheelchair"></i>
                    <span>Asientos de accesibilidad: <strong><?= implode(', ', $accessibleSeats) ?></strong></span>
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

<script>
// ============================================
// ✅ LIMPIAR SESSIONSTORAGE AL CARGAR CONFIRMACIÓN
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const showtimeId = <?= $showtimeId ?>;

    console.log('🗑️ Limpiando sessionStorage para showtime:', showtimeId);

    // Limpiar todas las claves relacionadas con este showtime
    const keysToRemove = [];
    for (let i = 0; i < sessionStorage.length; i++) {
        const key = sessionStorage.key(i);
        if (key && (
            key.startsWith('selected_seats_' + showtimeId) ||
            key.startsWith('selected_seats_count_' + showtimeId) ||
            key.startsWith('food_timeout_' + showtimeId) ||
            key.startsWith('food_seats_' + showtimeId) ||
            key.startsWith('ticket_selection_' + showtimeId) ||
            key.startsWith('food_order_' + showtimeId)
        )) {
            keysToRemove.push(key);
        }
    }

    keysToRemove.forEach(key => {
        sessionStorage.removeItem(key);
        console.log('🗑️ Eliminado:', key);
    });

    if (keysToRemove.length === 0) {
        console.log('✅ No hay claves de sessionStorage para limpiar');
    } else {
        console.log('✅ SessionStorage limpiado correctamente (' + keysToRemove.length + ' claves)');
    }

    // ✅ Limpiar también el parámetro de la URL si existe
    if (window.history && window.history.replaceState) {
        const url = new URL(window.location.href);
        if (url.searchParams.has('purchase_id')) {
            const purchaseId = url.searchParams.get('purchase_id');
            url.search = '?purchase_id=' + purchaseId;
            window.history.replaceState({}, document.title, url.toString());
            console.log('🧹 URL limpiada');
        }
    }
});
</script>
</body>
</html>