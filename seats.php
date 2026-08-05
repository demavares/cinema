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

// ============================================
// ✅ VERIFICAR TIMEOUT DEL TOKEN DE COMPRA
// ============================================
if (isPurchaseTokenExpired($showtimeId)) {
    clearPurchaseSession($showtimeId);
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=El+tiempo+para+la+reserva+ha+expirado');
    exit;
}

// ============================================
// ✅ VALIDAR TOKEN DE COMPRA DESDE SESIÓN
// ============================================
$purchaseToken = $_SESSION['purchase_token_' . $showtimeId] ?? '';
$foodValidKey = 'food_valid_' . $showtimeId;
$hasFoodSession = isset($_SESSION[$foodValidKey]) && $_SESSION[$foodValidKey] === true;

if (empty($purchaseToken) || !verifyPurchaseTokenWithTimeout($purchaseToken, $showtimeId)) {
    if (!$hasFoodSession) {
        header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Token+inválido+o+expirado');
        exit;
    }
    $purchaseToken = generatePurchaseTokenWithTimeout($showtimeId, 900);
}

// ============================================
// VERIFICAR QUE LA COMPRA NO ESTÉ COMPLETADA
// ============================================
$stmt = $pdo->prepare("
    SELECT id, status FROM purchases
    WHERE user_id = ? AND showtime_id = ? AND status IN ('completed', 'pending')
    ORDER BY id DESC LIMIT 1
");
$stmt->execute([$_SESSION['user_id'], $showtimeId]);
$purchase = $stmt->fetch();

// Si hay una compra pendiente (sin completar), limpiarla para permitir nueva compra
if ($purchase && $purchase['status'] === 'pending') {
    $stmt = $pdo->prepare("DELETE FROM purchases WHERE id = ?");
    $stmt->execute([$purchase['id']]);
    
    $stmt = $pdo->prepare("DELETE FROM tickets WHERE showtime_id = ? AND user_id = ?");
    $stmt->execute([$showtimeId, $_SESSION['user_id']]);
    
    $sessionKeys = [
        'ticket_quantities_' . $showtimeId,
        'total_seats_' . $showtimeId,
        'subtotal_' . $showtimeId,
        'tax_amount_' . $showtimeId,
        'total_amount_' . $showtimeId,
        'food_seats_' . $showtimeId,
        'food_timeout_' . $showtimeId,
        'food_valid_' . $showtimeId,
        'food_order_' . $showtimeId,
        'purchase_token_' . $showtimeId
    ];
    foreach ($sessionKeys as $key) {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
}

// ============================================
// LEER DATOS DE SESIÓN
// ============================================
$ticketsData = isset($_SESSION['ticket_quantities_' . $showtimeId]) 
    ? $_SESSION['ticket_quantities_' . $showtimeId] 
    : null;
$totalSeats = isset($_SESSION['total_seats_' . $showtimeId]) 
    ? intval($_SESSION['total_seats_' . $showtimeId]) 
    : 0;
$subtotal = isset($_SESSION['subtotal_' . $showtimeId]) 
    ? floatval($_SESSION['subtotal_' . $showtimeId]) 
    : 0;
$taxAmount = isset($_SESSION['tax_amount_' . $showtimeId]) 
    ? floatval($_SESSION['tax_amount_' . $showtimeId]) 
    : 0;
$totalAmount = isset($_SESSION['total_amount_' . $showtimeId]) 
    ? floatval($_SESSION['total_amount_' . $showtimeId]) 
    : 0;

// ============================================
// ✅ RECUPERAR DATOS DESDE SESIÓN DE COMIDA SI ES NECESARIO
// ============================================
$foodSeatsKey = 'food_seats_' . $showtimeId;
$foodTimeoutKey = 'food_timeout_' . $showtimeId;

if (!$ticketsData || $totalSeats <= 0) {
    if ($hasFoodSession && isset($_SESSION[$foodSeatsKey]) && !empty($_SESSION[$foodSeatsKey])) {
        $foodSeats = $_SESSION[$foodSeatsKey];
        $seatsArrayTemp = array_filter(array_map('trim', explode(',', $foodSeats)));
        $totalSeatsFromFood = count($seatsArrayTemp);
        
        if ($totalSeatsFromFood > 0) {
            // Obtener datos del showtime para calcular precios
            $stmtTemp = $pdo->prepare("
                SELECT s.*, m.title, m.poster_url, m.description, m.duration,
                       r.name as room_name, r.capacity, r.seat_layout, r.seat_image, r.aisle_config
                FROM showtimes s
                JOIN movies m ON s.movie_id = m.id
                JOIN rooms r ON s.room_id = r.id
                WHERE s.id = ? AND s.is_active = 1
            ");
            $stmtTemp->execute([$showtimeId]);
            $showtimeTemp = $stmtTemp->fetch();
            
            if ($showtimeTemp) {
                $ticketsData = ['adult' => $totalSeatsFromFood, 'child' => 0, 'senior' => 0];
                $priceTemp = getShowtimePrice($showtimeTemp);
                $subtotal = $totalSeatsFromFood * $priceTemp;
                
                $stmtTax = $pdo->query("SELECT tax_rate FROM tax_config WHERE is_active = 1 LIMIT 1");
                $taxTemp = $stmtTax->fetch();
                $taxRateTemp = $taxTemp ? floatval($taxTemp['tax_rate']) : 16;
                
                $taxAmount = $subtotal * ($taxRateTemp / 100);
                $totalAmount = $subtotal + $taxAmount;
                $totalSeats = $totalSeatsFromFood;
                
                $_SESSION['ticket_quantities_' . $showtimeId] = $ticketsData;
                $_SESSION['total_seats_' . $showtimeId] = $totalSeats;
                $_SESSION['subtotal_' . $showtimeId] = $subtotal;
                $_SESSION['tax_amount_' . $showtimeId] = $taxAmount;
                $_SESSION['total_amount_' . $showtimeId] = $totalAmount;
                
                error_log("✅ Datos de tickets recuperados desde sesión de comida para showtime $showtimeId");
            }
        }
    }
}

// Si aún no hay datos y no hay sesión de comida válida, redirigir a price_selection
if (!$ticketsData || $totalSeats <= 0) {
    if ($hasFoodSession) {
        unset($_SESSION[$foodValidKey]);
        unset($_SESSION[$foodSeatsKey]);
        unset($_SESSION[$foodTimeoutKey]);
    }
    header('Location: price_selection.php?showtime_id=' . $showtimeId);
    exit;
}

// ============================================
// OBTENER DATOS DEL SHOWTIME
// ============================================
$stmt = $pdo->prepare("
    SELECT s.*, m.id as movie_id, m.title, m.poster_url, m.description, m.duration,
           r.name as room_name, r.capacity, r.seat_layout, r.seat_image, r.aisle_config
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

$finalPrice = getShowtimePrice($showtime);

// Obtener asientos ocupados
$stmtSeats = $pdo->prepare("SELECT seat_code FROM tickets WHERE showtime_id = ?");
$stmtSeats->execute([$showtimeId]);
$occupiedSeats = $stmtSeats->fetchAll(PDO::FETCH_COLUMN);

// Decodificar layout
$seatLayout = null;
if (!empty($showtime['seat_layout'])) {
    $seatLayout = json_decode($showtime['seat_layout'], true);
}

if (!$seatLayout || !isset($seatLayout['rows']) || !isset($seatLayout['seatMap'])) {
    $rows = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
    $seatsPerRow = 21;
    $seatLayout = [
        'rows' => $rows,
        'seatsPerRow' => $seatsPerRow,
        'seatMap' => [],
        'totalSeats' => count($rows) * $seatsPerRow,
        'blockedSeats' => [],
        'wheelchairSeats' => []
    ];
    foreach ($rows as $row) {
        $seatLayout['seatMap'][$row] = range(1, $seatsPerRow);
    }
}

$blockedSeats = $seatLayout['blockedSeats'] ?? [];
$accessibleSeats = $seatLayout['wheelchairSeats'] ?? ($seatLayout['accessibleSeats'] ?? []);
$totalSeatsRoom = $seatLayout['totalSeats'] ?? 0;
$availableSeatsCount = $totalSeatsRoom - count($blockedSeats);
$occupiedCount = count($occupiedSeats);
$realAvailable = $availableSeatsCount - $occupiedCount;

if ($realAvailable < $totalSeats) {
    header('Location: index.php?error=No+hay+suficientes+asientos+disponibles');
    exit;
}

$csrf_token = generateCSRFToken();

$tmdb_data = getMovieFromTMDB($showtime['title']);
$tmdb_poster = $tmdb_data['poster_path'] ?? null;

$promotions = $showtime['promotions'] ? explode(',', $showtime['promotions']) : [];
$hasMondayPromo = in_array('lunes_mitad', $promotions);
$hasPresale = in_array('preventa', $promotions);

$language = $showtime['language'] ?? 'español';
$lang_label = $language == 'español' ? 'Español' : 'Subtítulos en Español';

$pageTitle = "Selección de Asientos - " . $showtime['title'];
$backUrl = 'price_selection.php?showtime_id=' . $showtimeId;

$siteConfig = getSiteConfig($pdo);

// Asegurar que el token existe en sesión
if (!isset($_SESSION['purchase_token_' . $showtimeId])) {
    $_SESSION['purchase_token_' . $showtimeId] = generatePurchaseTokenWithTimeout($showtimeId, 900);
}
$purchaseToken = $_SESSION['purchase_token_' . $showtimeId];

require_once 'header.php';
?>

<style>
body {
    background-color: #ffffff !important;
    color: #1f2937 !important;
}
.bg-\[\#14141e\] { background-color: #ffffff !important; }
.border-\[\#1e1e2e\] { border-color: #e2e8f0 !important; }
.section-title { color: #374151 !important; font-weight: 700; }
.section-subtitle { color: #6b7280 !important; }
.cinema-screen {
    box-shadow: none !important;
    background: #4f46e5 !important;
    border: none !important;
    color: #ffffff;
    text-align: center;
    padding: 6px;
    border-radius: 8px;
    margin-top: 28px;
    font-weight: bold;
    letter-spacing: 4px;
    font-size: clamp(0.7rem, 2vw, 1rem);
    width: 100%;
    order: 2;
}
.seat {
    width: clamp(1.2rem, 2.2vw, 1.8rem);
    height: clamp(1.2rem, 2.2vw, 1.8rem);
    border-radius: 0.3rem 0.3rem 0.2rem 0.2rem;
    transition: all 0.2s ease;
    cursor: pointer;
    border: none !important;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    padding: 0 !important;
    overflow: hidden;
}
.seat:disabled { cursor: not-allowed; }
.seat-available { background-color: #cbd5e1 !important; }
.seat-available:hover:not(.seat-occupied):not(.seat-blocked):not(.seat-accessible):not(.seat-selected) {
    background-color: #6366f1 !important;
    transform: scale(1.1);
    box-shadow: 0 0 12px rgba(99, 102, 241, 0.4);
}
.seat-selected {
    background-color: #4f46e5 !important;
    box-shadow: 0 0 10px rgba(79, 70, 229, 0.5);
    transform: scale(1.05);
}
.seat-occupied {
    background-color: #ef4444 !important;
    cursor: not-allowed !important;
    opacity: 0.65;
}
.seat-accessible { background-color: #0284c7 !important; }
.seat-accessible:hover:not(.seat-occupied):not(.seat-blocked):not(.seat-selected) {
    background-color: #0369a1 !important;
    transform: scale(1.1);
    box-shadow: 0 0 12px rgba(2, 132, 199, 0.4);
}
.seat-accessible .seat-label {
    position: static !important;
    transform: none !important;
    width: 100% !important;
    height: 100% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: clamp(0.9rem, 1.8vw, 1.4rem) !important;
    line-height: 1 !important;
    color: #ffffff !important;
}
.seat-blocked {
    background-color: #f1f5f9 !important;
    cursor: not-allowed !important;
    opacity: 0.2;
    box-shadow: none !important;
    transform: none !important;
}
.seat-blocked .seat-label { display: none !important; }
.seat-label {
    font-size: clamp(0.5rem, 1vw, 0.7rem);
    color: #0f172a;
    text-align: center;
    position: absolute;
    bottom: 1px;
    left: 50%;
    transform: translateX(-50%);
    font-weight: bold;
    white-space: nowrap;
}
.seat-selected .seat-label,
.seat-occupied .seat-label {
    color: #ffffff !important;
    text-shadow: 0 1px 2px rgba(0,0,0,0.4);
}
.bg-\[\#1a1a2e\].border-\[\#2a2a3e\].text-gray-500 {
    background-color: #4f46e5 !important;
    color: #ffffff !important;
    border: none !important;
    font-weight: 600;
}
.legend {
    display: flex;
    gap: clamp(8px, 2vw, 20px);
    justify-content: center;
    flex-wrap: wrap;
    margin-top: 20px;
}
.legend-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: clamp(0.65rem, 1.2vw, 0.8rem);
    color: #475569;
    font-weight: 500;
}
.legend-item .color-box {
    width: clamp(14px, 2vw, 20px);
    height: clamp(14px, 2vw, 20px);
    border-radius: 4px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    color: #fff;
}
.seat-row {
    display: flex;
    gap: clamp(2px, 0.4vw, 4px);
    align-items: center;
    justify-content: center;
    flex-wrap: nowrap;
}
.row-label {
    width: clamp(20px, 2.5vw, 28px);
    font-size: clamp(0.6rem, 1vw, 0.75rem);
    color: #475569;
    font-weight: bold;
    text-align: right;
    padding-right: clamp(4px, 0.6vw, 8px);
    flex-shrink: 0;
    position: sticky;
    left: 0;
    background: #ffffff;
    z-index: 5;
}
.seat-grid-wrapper { display: inline-block; min-width: 100%; }
.seats-container { display: flex; flex-direction: column; align-items: center; width: 100%; }
.seat-grid-scroll-wrapper {
    width: 100%;
    max-height: 60vh;
    overflow: auto;
    -webkit-overflow-scrolling: touch;
    padding: 8px;
    position: relative;
}
.seat-grid-container {
    display: grid;
    gap: clamp(2px, 0.4vw, 4px);
    padding: clamp(4px, 0.8vw, 10px);
    width: max-content;
    margin: 0 auto;
    transform-origin: top left;
    transition: transform 0.2s ease-out;
}
.seat-grid-scroll-wrapper::-webkit-scrollbar { width: 6px; height: 6px; }
.seat-grid-scroll-wrapper::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
.seat-grid-scroll-wrapper::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.selected-info { background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; min-height: 60px; }
#subtotal { color: #0f172a !important; }
.summary-sticky {
    background-color: #f8fafc !important;
    border: 1px solid #cbd5e1 !important;
    border-top: 4px solid #4f46e5 !important;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08) !important;
    border-radius: 12px !important;
    padding: 24px;
    position: sticky;
    top: 100px;
    align-self: flex-start;
}
.summary-sticky .text-white { color: #0f172a !important; }
.summary-sticky .text-gray-400 { color: #475569 !important; font-weight: 500; }
.summary-sticky .text-gray-500 { color: #64748b !important; }
.summary-sticky .text-indigo-400 { color: #334155 !important; font-size: 1rem !important; font-weight: 700 !important; }
.btn-continue-food {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: #ffffff !important;
    padding: 10px 30px;
    border-radius: 8px;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 100%;
    font-size: 1rem;
}
.btn-continue-food:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(79, 70, 229, 0.25); }
.btn-continue-food:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; box-shadow: none !important; }
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
.promotion-tag { display: inline-block; padding: 3px 12px; border-radius: 12px; font-size: 12px !important; font-weight: 600; margin: 2px; }
.promotion-tag.lunes { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
.promotion-tag.preventa { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
.language-tag { display: inline-block; padding: 3px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; background: #dbeafe; color: #1d4ed8; border: 1px solid #bfdbfe; }
.info-text { font-size: 13px; color: #475569; }
.info-text strong { color: #0f172a; }
.summary-movie-poster { width: 80px; height: 120px; object-fit: cover; border-radius: 8px; flex-shrink: 0; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.summary-movie-title { font-weight: 700; color: #0f172a; font-size: 1.1rem; line-height: 1.3; }
.summary-movie-details { font-size: 0.85rem; color: #475569; margin-top: 2px; }
.summary-movie-details strong { color: #0f172a; }
.summary-promo-badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 0.65rem; font-weight: 600; margin-top: 4px; }
.summary-promo-badge.lunes { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
.summary-promo-badge.preventa { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
.summary-language-tag { display: inline-block; padding: 2px 12px; border-radius: 12px; font-size: 0.7rem; font-weight: 600; margin-top: 4px; background: #dbeafe; color: #1d4ed8; border: 1px solid #bfdbfe; }
.summary-total-price { font-size: 1.3rem; font-weight: 700; color: #16a34a; margin-top: 2px; }
.selected-info-box { background: #f1f5f9 !important; border: 1px solid #e2e8f0 !important; border-radius: 8px !important; padding: 14px !important; margin-top: 12px; margin-bottom: 16px; }
.summary-sticky .bg-\[\#1a1a2e\] { background-color: #f1f5f9 !important; }
.summary-sticky .border-\[\#2a2a3e\] { border-color: #cbd5e1 !important; }
@media (max-width: 768px) {
    .seat-grid-scroll-wrapper { max-height: 50vh; }
    .summary-sticky { padding: 16px; position: relative; top: auto; }
    .summary-movie-poster { width: 60px; height: 90px; }
    .summary-movie-title { font-size: 0.95rem; }
}
@media (max-width: 640px) {
    .seat { width: 1.35rem; height: 1.35rem; border-radius: 3px; }
    .seat-label { font-size: 0.5rem; bottom: 0px; }
    .row-label { width: 18px; font-size: 0.55rem; padding-right: 4px; }
    .seat-grid-container { gap: 3px; padding: 4px; }
    .seat-row { gap: 3px; }
    .cinema-screen { margin-top: 20px; padding: 8px; font-size: 0.6rem; }
}
@media (max-width: 480px) {
    .seat { width: 1.2rem; height: 1.2rem; border-radius: 2px; }
    .seat-label { font-size: 0.45rem; bottom: 0px; }
    .row-label { width: 16px; font-size: 0.5rem; padding-right: 3px; }
    .seat-grid-container { gap: 2.5px; padding: 3px; }
    .seat-row { gap: 2.5px; }
    .cinema-screen { margin-top: 16px; padding: 6px; font-size: 0.5rem; letter-spacing: 2px; }
}
</style>

<div class="container mx-auto px-2 sm:px-4 py-4 sm:py-8 max-w-7xl">
    <div class="flex flex-col xl:flex-row gap-4 sm:gap-8 mt-2">
        <!-- Mapa de Asientos -->
        <div class="flex-1 bg-[#14141e] p-3 sm:p-6 rounded-xl border border-[#1e1e2e]">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-2">
                <div>
                    <h2 class="text-xl font-bold section-title">🎫 Selecciona tus asientos</h2>
                    <p class="text-sm section-subtitle">
                        <?= htmlspecialchars($showtime['room_name']) ?> · 
                        <?= formatDateShort($showtime['show_date']) ?> · 
                        <?= formatTimeVenezuela($showtime['show_time']) ?>
                    </p>
                </div>
                <span class="text-xs text-gray-500 bg-[#1a1a2e] px-3 py-1 rounded-full border border-[#2a2a3e]">
                    <?= $realAvailable ?> asientos disponibles
                </span>
            </div>

            <div class="flex sm:hidden justify-end gap-2 mb-3 items-center">
                <span class="text-xs text-gray-400 mr-auto"><i class="fas fa-search-plus mr-1"></i> Zoom:</span>
                <button type="button" id="btn-zoom-out" class="bg-[#1a1a2e] border border-[#2a2a3e] text-gray-300 hover:text-white px-3 py-1.5 rounded-lg text-xs font-bold active:scale-95 transition-all">
                    <i class="fas fa-minus"></i>
                </button>
                <button type="button" id="btn-zoom-reset" class="bg-[#1a1a2e] border border-[#2a2a3e] text-gray-400 hover:text-white px-2.5 py-1.5 rounded-lg text-xs font-bold active:scale-95 transition-all">
                    100%
                </button>
                <button type="button" id="btn-zoom-in" class="bg-[#1a1a2e] border border-[#2a2a3e] text-gray-300 hover:text-white px-3 py-1.5 rounded-lg text-xs font-bold active:scale-95 transition-all">
                    <i class="fas fa-plus"></i>
                </button>
            </div>

            <div class="seats-container">
                <div class="seat-grid-scroll-wrapper">
                    <div class="seat-grid-wrapper">
                        <div class="seat-grid-container">
                            <?php
                            $rows = $seatLayout['rows'] ?? [];
                            $seatMap = $seatLayout['seatMap'] ?? [];
                            $reversedRows = array_reverse($rows);
                            
                            foreach ($reversedRows as $row):
                                $seatNumbers = $seatMap[$row] ?? range(1, 21);
                            ?>
                            <div class="seat-row">
                                <span class="row-label"><?= $row ?></span>
                                <?php foreach ($seatNumbers as $seatNumber): 
                                    $seatId = $row . $seatNumber;
                                    $isOccupied = in_array($seatId, $occupiedSeats);
                                    $isBlocked = in_array($seatId, $blockedSeats);
                                    $isAccessible = in_array($seatId, $accessibleSeats);
                                    
                                    $seatClass = 'seat-available';
                                    if ($isBlocked) {
                                        $seatClass = 'seat-blocked';
                                    } elseif ($isOccupied) {
                                        $seatClass = 'seat-occupied';
                                    } elseif ($isAccessible) {
                                        $seatClass = 'seat-accessible';
                                    }
                                    
                                    $seatTitle = $isBlocked ? 'Pasillo' : ($isOccupied ? 'Ocupado' : ($isAccessible ? "Asiento $seatId (Discapacidad ♿)" : "Asiento $seatId"));
                                ?>
                                <button 
                                    data-seat="<?= $seatId ?>" 
                                    class="seat <?= $seatClass ?>"
                                    <?= ($isOccupied || $isBlocked) ? 'disabled' : '' ?>
                                    title="<?= htmlspecialchars($seatTitle) ?>"
                                >
                                    <?php if(!$isBlocked): ?>
                                    <span class="seat-label"><?= $isAccessible ? '♿' : $seatNumber ?></span>
                                    <?php endif; ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="cinema-screen">PANTALLA</div>
            </div>

            <div class="legend">
                <div class="legend-item"><div class="color-box bg-gray-500"></div> Disponible</div>
                <div class="legend-item"><div class="color-box bg-sky-600">♿</div> Discapacidad</div>
                <div class="legend-item"><div class="color-box bg-indigo-500"></div> Seleccionado</div>
                <div class="legend-item"><div class="color-box bg-red-600"></div> Ocupado</div>
            </div>
        </div>

        <!-- Panel de Reserva -->
        <div class="w-full xl:w-96 summary-sticky">
            <div>
                <div class="flex gap-4 mb-4">
                    <img src="<?= $tmdb_poster ? 'https://image.tmdb.org/t/p/w200' . $tmdb_poster : ($showtime['poster_url'] ? htmlspecialchars($showtime['poster_url']) : '') ?>" 
                         alt="<?= htmlspecialchars($showtime['title']) ?>" 
                         class="summary-movie-poster"
                         onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22150%22 viewBox=%220 0 100 150%22%3E%3Crect fill=%22%231a1a2e%22 width=%22100%22 height=%22150%22/%3E%3Ctext x=%2250%22 y=%2275%22 text-anchor=%22middle%22 fill=%22%236b7280%22 font-size=%2240%22 font-family=%22Arial%22%3E🎬%3C/text%3E%3C/svg%3E'">
                    <div class="flex-1 min-w-0">
                        <h3 class="summary-movie-title"><?= htmlspecialchars($showtime['title']) ?></h3>
                        <div class="flex flex-wrap gap-1 mt-1">
                            <?php if($hasMondayPromo): ?><span class="promotion-tag lunes">Lunes ½ Precio</span><?php endif; ?>
                            <?php if($hasPresale): ?><span class="promotion-tag preventa">Preventa</span><?php endif; ?>
                            <?php if(!$hasMondayPromo && !$hasPresale): ?><span class="text-gray-500 text-xs">Sin promociones</span><?php endif; ?>
                        </div>
                        <div class="mt-2"><span class="language-tag"><?= $lang_label ?></span></div>
                        <div class="mt-2 info-text">
                            <strong><?= formatDateShort($showtime['show_date']) ?></strong> · 
                            <strong><?= formatTimeVenezuela($showtime['show_time']) ?></strong>
                        </div>
                        <p class="text-sm text-indigo-400 font-semibold mt-1"><?= formatCurrency($totalAmount, $siteConfig) ?></p>
                        <p class="text-xs text-gray-400 mt-1 truncate"><?= htmlspecialchars($showtime['room_name']) ?></p>
                    </div>
                </div>

                <hr class="border-[#2a2a3e] my-4">

                <div class="selected-info-box">
                    <p class="text-sm text-gray-600">
                        Asientos elegidos: <span id="selected-seats-list" class="font-bold text-slate-900">-</span>
                    </p>
                    <p class="text-sm text-gray-600 mt-1">
                        Cantidad de boletos: <span id="ticket-count" class="font-bold text-slate-900">0 de <?= $totalSeats ?></span>
                    </p>
                </div>
            </div>

            <div class="mt-4 flex flex-col gap-2.5">
                <form action="create_food_session.php" method="POST" id="foodForm" onsubmit="return handleFormSubmit(event)">
                    <input type="hidden" name="showtime_id" value="<?= $showtime['id'] ?>">
                    <input type="hidden" name="seats" id="seats-input" value="">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="purchase_token" value="<?= htmlspecialchars($purchaseToken) ?>">
                    <button type="submit" id="btn-continue" disabled class="btn-continue-food">
                        <i class="fas fa-utensils mr-2"></i> Continuar a Comida
                    </button>
                </form>
                <a href="price_selection.php?showtime_id=<?= $showtimeId ?>" class="btn-back">
                    <i class="fas fa-arrow-left mr-2"></i> Regresar a Boletos
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>

<script>
// ============================================
// CONFIGURACIÓN DESDE PHP (SEGURO)
// ============================================
const totalSeatsNeeded = <?= $totalSeats ?>;
const showtimeId = <?= $showtime['id'] ?>;
const pricePerTicket = <?= $finalPrice ?>;
const totalAmount = <?= $totalAmount ?>;
const occupiedSeats = <?= json_encode($occupiedSeats) ?>;
const blockedSeats = <?= json_encode($blockedSeats) ?>;
const accessibleSeats = <?= json_encode($accessibleSeats) ?>;
const purchaseToken = '<?= htmlspecialchars($purchaseToken) ?>';
const currencyConfig = {
    symbol: '<?= $siteConfig['currency_symbol'] ?? '$' ?>',
    position: '<?= $siteConfig['currency_position'] ?? 'left' ?>',
    thousands: '<?= $siteConfig['thousands_separator'] ?? '.' ?>',
    decimal: '<?= $siteConfig['decimal_separator'] ?? ',' ?>',
    decimals: <?= intval($siteConfig['decimal_places'] ?? 2) ?>
};
let selectedSeats = [];
const maxSeats = totalSeatsNeeded;

// ============================================
// FUNCIONES DE ALMACENAMIENTO
// ============================================
function saveSeatsToStorage() {
    try {
        sessionStorage.setItem('selected_seats_' + showtimeId, JSON.stringify(selectedSeats));
        sessionStorage.setItem('selected_seats_count_' + showtimeId, selectedSeats.length);
    } catch (e) { console.warn('Error guardando en sessionStorage:', e); }
}

function loadSeatsFromStorage() {
    try {
        const saved = sessionStorage.getItem('selected_seats_' + showtimeId);
        if (saved) {
            const parsed = JSON.parse(saved);
            if (Array.isArray(parsed) && parsed.length > 0) {
                const validSeats = parsed.filter(seat => !occupiedSeats.includes(seat) && !blockedSeats.includes(seat));
                if (validSeats.length > 0) {
                    selectedSeats = validSeats;
                    return true;
                }
            }
        }
    } catch (e) { console.warn('Error cargando desde sessionStorage:', e); }
    return false;
}

function clearSeatsStorage() {
    try {
        sessionStorage.removeItem('selected_seats_' + showtimeId);
        sessionStorage.removeItem('selected_seats_count_' + showtimeId);
    } catch (e) { console.warn('Error limpiando sessionStorage:', e); }
}

// ============================================
// FUNCIONES DE FORMATO
// ============================================
function formatCurrency(amount) {
    const symbol = currencyConfig.symbol;
    const position = currencyConfig.position;
    const thousands = currencyConfig.thousands;
    const decimal = currencyConfig.decimal;
    const decimals = currencyConfig.decimals;
    let formatted = amount.toFixed(decimals).replace('.', decimal).replace(/\B(?=(\d{3})+(?!\d))/g, thousands);
    return position === 'right' ? formatted + ' ' + symbol : symbol + formatted;
}

// ============================================
// ACTUALIZAR RESUMEN
// ============================================
function updateSummary() {
    const count = selectedSeats.length;
    const selectedSeatsList = document.getElementById('selected-seats-list');
    const ticketCountEl = document.getElementById('ticket-count');
    const seatsInput = document.getElementById('seats-input');
    const btnContinue = document.getElementById('btn-continue');
    
    selectedSeatsList.innerText = count > 0 ? selectedSeats.join(', ') : '-';
    ticketCountEl.innerText = count + ' de ' + maxSeats;
    seatsInput.value = selectedSeats.join(',');
    
    if (count >= maxSeats) {
        btnContinue.removeAttribute('disabled');
        btnContinue.classList.remove('opacity-50', 'cursor-not-allowed');
        btnContinue.innerHTML = '<i class="fas fa-utensils mr-2"></i> Continuar a Comida';
    } else {
        btnContinue.setAttribute('disabled', 'true');
        btnContinue.classList.add('opacity-50', 'cursor-not-allowed');
        btnContinue.innerHTML = '<i class="fas fa-utensils mr-2"></i> Selecciona ' + (maxSeats - count) + ' asiento' + (maxSeats - count !== 1 ? 's' : '') + ' más';
    }
    saveSeatsToStorage();
}

// ============================================
// VALIDAR ASIENTOS
// ============================================
function validateSeats() {
    if (selectedSeats.length === 0) { showNotification('Por favor, selecciona al menos un asiento.', 'warning'); return false; }
    if (selectedSeats.length < maxSeats) { showNotification('Debes seleccionar ' + maxSeats + ' asientos. Has seleccionado ' + selectedSeats.length + '.', 'warning'); return false; }
    const stillOccupied = selectedSeats.filter(seat => occupiedSeats.includes(seat));
    if (stillOccupied.length > 0) { showNotification('Asientos no disponibles: ' + stillOccupied.join(', '), 'error'); return false; }
    return true;
}

// ============================================
// NOTIFICACIONES
// ============================================
function showNotification(message, type = 'info') {
    const colors = { info: 'bg-blue-600', success: 'bg-green-600', warning: 'bg-yellow-600', error: 'bg-red-600' };
    const icons = { info: 'fa-info-circle', success: 'fa-check-circle', warning: 'fa-exclamation-triangle', error: 'fa-times-circle' };
    const notification = document.createElement('div');
    notification.className = 'fixed bottom-4 left-1/2 transform -translate-x-1/2 ' + (colors[type] || 'bg-gray-600') + ' text-white px-4 sm:px-6 py-2 sm:py-3 rounded-lg shadow-lg z-50 transition-all duration-300 max-w-[90%] sm:max-w-md text-center text-sm flex items-center gap-3';
    notification.innerHTML = '<i class="fas ' + (icons[type] || 'fa-info-circle') + '"></i><span>' + message + '</span>';
    document.body.appendChild(notification);
    setTimeout(() => { notification.style.opacity = '0'; notification.style.transform = 'translate(-50%, 20px)'; setTimeout(() => notification.remove(), 300); }, 3500);
}

// ============================================
// MANEJAR ENVÍO DEL FORMULARIO
// ============================================
window.handleFormSubmit = function(event) {
    event.preventDefault();
    if (!validateSeats()) return false;
    
    const form = document.getElementById('foodForm');
    const formData = new FormData(form);
    
    const btnContinue = document.getElementById('btn-continue');
    btnContinue.disabled = true;
    btnContinue.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Procesando...';
    
    fetch('create_food_session.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'food_menu.php?showtime_id=' + showtimeId;
        } else {
            showNotification('Error al crear la sesión. Intenta nuevamente.', 'error');
            btnContinue.disabled = false;
            btnContinue.innerHTML = '<i class="fas fa-utensils mr-2"></i> Continuar a Comida';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        window.location.href = 'food_menu.php?showtime_id=' + showtimeId;
    });
    return false;
};

// ============================================
// INICIALIZACIÓN
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const seats = document.querySelectorAll('.seat:not(.seat-occupied):not(.seat-blocked)');
    
    console.log('🔍 Inicializando seats.php, totalSeatsNeeded:', totalSeatsNeeded);
    console.log('🔍 Ocupados:', occupiedSeats);
    console.log('🔍 Bloqueados:', blockedSeats);
    
    // Intentar cargar asientos guardados
    const hasSavedSeats = loadSeatsFromStorage();
    if (hasSavedSeats) {
        console.log('✅ Asientos restaurados desde sessionStorage (' + selectedSeats.length + ' asientos):', selectedSeats);
    } else {
        // Si no hay asientos guardados, verificar si hay sesión de comida activa
        <?php if ($hasFoodSession && isset($_SESSION[$foodSeatsKey]) && !empty($_SESSION[$foodSeatsKey])): ?>
        const foodSeats = '<?= addslashes($_SESSION[$foodSeatsKey]) ?>';
        console.log('🔍 Asientos desde sesión de comida:', foodSeats);
        if (foodSeats) {
            const seatsFromFood = foodSeats.split(',').filter(s => s.trim());
            const validSeats = seatsFromFood.filter(seat => !occupiedSeats.includes(seat) && !blockedSeats.includes(seat));
            if (validSeats.length > 0) {
                selectedSeats = validSeats;
                saveSeatsToStorage();
                console.log('✅ Asientos restaurados desde sesión de comida (' + selectedSeats.length + ' asientos):', selectedSeats);
            }
        }
        <?php endif; ?>
    }
    
    // Restaurar estado visual
    seats.forEach(seat => {
        const seatId = seat.getAttribute('data-seat');
        if (selectedSeats.includes(seatId)) {
            seat.classList.remove('seat-available', 'seat-accessible');
            seat.classList.add('seat-selected');
        }
    });
    updateSummary();
    
    // Eventos de clic en asientos
    seats.forEach(seat => {
        seat.addEventListener('click', function() {
            const seatId = this.getAttribute('data-seat');
            console.log('🖱️ Clic en asiento:', seatId);
            
            if (blockedSeats.includes(seatId)) { 
                showNotification('Este es un pasillo, no se puede seleccionar', 'warning'); 
                return; 
            }
            if (occupiedSeats.includes(seatId)) { 
                this.classList.add('seat-occupied'); 
                this.disabled = true; 
                showNotification('Este asiento ya ha sido reservado.', 'error'); 
                return; 
            }
            
            const index = selectedSeats.indexOf(seatId);
            const isAccessible = accessibleSeats.includes(seatId);
            
            if (index > -1) {
                selectedSeats.splice(index, 1);
                this.classList.remove('seat-selected');
                this.classList.add(isAccessible ? 'seat-accessible' : 'seat-available');
                console.log('➖ Asiento removido:', seatId);
            } else {
                if (selectedSeats.length >= maxSeats) { 
                    showNotification('Máximo ' + maxSeats + ' asientos.', 'warning'); 
                    return; 
                }
                selectedSeats.push(seatId);
                this.classList.remove('seat-available', 'seat-accessible');
                this.classList.add('seat-selected');
                console.log('➕ Asiento agregado:', seatId);
            }
            console.log('📋 Asientos seleccionados:', selectedSeats);
            updateSummary();
        });
    });
    
    // Zoom para móviles
    let currentZoom = 1;
    const minZoom = 0.8, maxZoom = 1.8, stepZoom = 0.2;
    const seatGridContainer = document.querySelector('.seat-grid-container');
    const seatGridWrapper = document.querySelector('.seat-grid-wrapper');
    const btnZoomIn = document.getElementById('btn-zoom-in');
    const btnZoomOut = document.getElementById('btn-zoom-out');
    const btnZoomReset = document.getElementById('btn-zoom-reset');
    
    function applyZoom(newZoom) {
        currentZoom = Math.min(Math.max(newZoom, minZoom), maxZoom);
        seatGridContainer.style.transform = 'scale(' + currentZoom + ')';
        if (currentZoom > 1) {
            seatGridWrapper.style.paddingRight = (seatGridContainer.offsetWidth * (currentZoom - 1)) + 'px';
            seatGridWrapper.style.paddingBottom = (seatGridContainer.offsetHeight * (currentZoom - 1)) + 'px';
        } else {
            seatGridWrapper.style.paddingRight = '0px';
            seatGridWrapper.style.paddingBottom = '0px';
        }
        if (btnZoomReset) btnZoomReset.innerText = Math.round(currentZoom * 100) + '%';
    }
    
    if (btnZoomIn && btnZoomOut && btnZoomReset) {
        btnZoomIn.addEventListener('click', () => applyZoom(currentZoom + stepZoom));
        btnZoomOut.addEventListener('click', () => applyZoom(currentZoom - stepZoom));
        btnZoomReset.addEventListener('click', () => applyZoom(1));
    }
    
    // Actualizar asientos cada 30 segundos
    setInterval(function() {
        fetch('check_seats.php?showtime_id=<?= $showtime['id'] ?>')
            .then(response => response.json())
            .then(data => {
                data.occupied.forEach(seatId => {
                    const seatEl = document.querySelector('[data-seat="' + seatId + '"]');
                    if (seatEl && !seatEl.classList.contains('seat-occupied')) {
                        seatEl.classList.remove('seat-selected', 'seat-available', 'seat-accessible');
                        seatEl.classList.add('seat-occupied');
                        seatEl.disabled = true;
                        const index = selectedSeats.indexOf(seatId);
                        if (index > -1) selectedSeats.splice(index, 1);
                    }
                });
                updateSummary();
            })
            .catch(err => console.log('Error checking seats:', err));
    }, 30000);
});
</script>
</body>
</html>