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
// ✅ DETECTAR SI VIENE DE FOOD_MENU.PHP
// ============================================
$fromFood = isset($_GET['from']) && $_GET['from'] === 'food';

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
    if (!$hasFoodSession && !$fromFood) {
        header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Token+inválido+o+expirado');
        exit;
    }
    if ($fromFood) {
        $purchaseToken = generatePurchaseTokenWithTimeout($showtimeId, 900);
    }
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
if ($purchase && $purchase['status'] === 'pending' && !$fromFood) {
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
$taxRate = isset($_SESSION['tax_rate_' . $showtimeId]) 
    ? floatval($_SESSION['tax_rate_' . $showtimeId]) 
    : 16;

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
            $stmtTemp = $pdo->prepare("
                SELECT s.*, m.title, m.poster_url, m.description, m.duration,
                       r.name as room_name, r.capacity, r.seat_layout, r.aisle_config
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
                $taxRate = $taxTemp ? floatval($taxTemp['tax_rate']) : 16;
                
                $taxAmount = $subtotal * ($taxRate / 100);
                $totalAmount = $subtotal + $taxAmount;
                $totalSeats = $totalSeatsFromFood;
                
                $_SESSION['ticket_quantities_' . $showtimeId] = $ticketsData;
                $_SESSION['total_seats_' . $showtimeId] = $totalSeats;
                $_SESSION['subtotal_' . $showtimeId] = $subtotal;
                $_SESSION['tax_amount_' . $showtimeId] = $taxAmount;
                $_SESSION['total_amount_' . $showtimeId] = $totalAmount;
                $_SESSION['tax_rate_' . $showtimeId] = $taxRate;
            }
        }
    }
}

// Si aún no hay datos, redirigir a price_selection
if (!$ticketsData || $totalSeats <= 0) {
    if ($hasFoodSession) {
        unset($_SESSION[$foodValidKey]);
        unset($_SESSION[$foodSeatsKey]);
        unset($_SESSION[$foodTimeoutKey]);
    }
    if (!$fromFood) {
        header('Location: price_selection.php?showtime_id=' . $showtimeId);
        exit;
    }
}

// ============================================
// OBTENER DATOS DEL SHOWTIME (CORREGIDO - SIN seat_image)
// ============================================
$stmt = $pdo->prepare("
    SELECT s.*, m.id as movie_id, m.title, m.poster_url, m.description, m.duration,
           r.name as room_name, r.capacity, r.seat_layout, r.aisle_config
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

// ============================================
// ✅ OBTENER ASIENTOS OCUPADOS (EXCLUYENDO LOS DEL USUARIO ACTUAL)
// ============================================
$stmtSeats = $pdo->prepare("
    SELECT t.seat_code 
    FROM tickets t
    LEFT JOIN purchases p ON t.user_id = p.user_id AND t.showtime_id = p.showtime_id
    WHERE t.showtime_id = ? 
    AND (
        p.status = 'completed' 
        OR (p.status = 'pending' AND t.user_id != ?)
        OR p.status IS NULL
    )
    GROUP BY t.seat_code
");
$stmtSeats->execute([$showtimeId, $_SESSION['user_id']]);
$occupiedSeats = $stmtSeats->fetchAll(PDO::FETCH_COLUMN);

// ============================================
// ✅ OBTENER ASIENTOS SELECCIONADOS POR EL USUARIO ACTUAL
// ============================================
$stmtUserSeats = $pdo->prepare("
    SELECT t.seat_code 
    FROM tickets t
    JOIN purchases p ON t.user_id = p.user_id AND t.showtime_id = p.showtime_id
    WHERE t.showtime_id = ? 
    AND t.user_id = ? 
    AND p.status = 'pending'
    AND p.expires_at > NOW()
");
$stmtUserSeats->execute([$showtimeId, $_SESSION['user_id']]);
$userPendingSeats = $stmtUserSeats->fetchAll(PDO::FETCH_COLUMN);

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

// ✅ Para el usuario actual, los asientos pendientes NO cuentan como ocupados
if ($realAvailable < $totalSeats) {
    $userPendingCount = count($userPendingSeats);
    $availableForUser = $realAvailable + $userPendingCount;
    if ($availableForUser < $totalSeats) {
        header('Location: index.php?error=No+hay+suficientes+asientos+disponibles');
        exit;
    }
}

$csrf_token = generateCSRFToken();

$tmdb_data = getMovieFromTMDB($showtime['title']);
$tmdb_poster = $tmdb_data['poster_path'] ?? null;

$promotions = $showtime['promotions'] ? explode(',', $showtime['promotions']) : [];
$hasMondayPromo = in_array('lunes_mitad', $promotions);
$hasPresale = in_array('preventa', $promotions);

$language = $showtime['language'] ?? 'español';
$lang_label = $language == 'español' ? 'Español' : 'Subtítulos en Español';

$format = $showtime['format'] ?? '2D';
$formatClass = 'format-' . strtolower(str_replace([' ', '/'], '-', $format));

$pageTitle = "Selección de Asientos - " . $showtime['title'];
$backUrl = 'price_selection.php?showtime_id=' . $showtimeId;

$siteConfig = getSiteConfig($pdo);

// Asegurar que el token existe en sesión
if (!isset($_SESSION['purchase_token_' . $showtimeId])) {
    $_SESSION['purchase_token_' . $showtimeId] = generatePurchaseTokenWithTimeout($showtimeId, 900);
}
$purchaseToken = $_SESSION['purchase_token_' . $showtimeId];

// ✅ Inicializar selectedSeats con los asientos pendientes del usuario
$selectedSeats = $userPendingSeats;

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
    padding: 3px;
    border-radius: 8px;
    margin-top: 28px;
    font-weight: bold;
    letter-spacing: 4px;
    font-size: clamp(0.7rem, 2vw, 1rem);
    width: 100%;
    order: 2;
}
:root {
    --seat-size: clamp(1.2rem, 2.2vw, 1.8rem);
}
.seat {
    width: var(--seat-size);
    height: var(--seat-size);
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
    font-size: calc(var(--seat-size) * 0.6) !important;
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
    font-size: calc(var(--seat-size) * 0.35);
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
    overflow: auto;
    -webkit-overflow-scrolling: touch;
    padding: 8px;
    position: relative;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #f8fafc;
}
.seat-grid-container {
    display: grid;
    gap: clamp(2px, 0.4vw, 4px);
    padding: clamp(4px, 0.8vw, 10px);
    width: max-content;
    margin: 0 auto;
}
.zoom-controls {
    display: none;
}
.zoom-btn {
    width: 44px;
    height: 44px;
    border: 2px solid #6366f1;
    background: #ffffff;
    color: #6366f1;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: bold;
    transition: all 0.2s ease;
    touch-action: manipulation;
}
.zoom-btn:hover {
    background: #6366f1;
    color: #ffffff;
    transform: scale(1.05);
}
.zoom-btn:active {
    transform: scale(0.95);
}
.zoom-label {
    min-width: 60px;
    text-align: center;
    color: #4f46e5;
    font-size: 14px;
    font-weight: 700;
    background: #ffffff;
    padding: 8px 12px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
}
.selected-info {
    background: #f1f5f9 !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 8px !important;
    padding: 14px !important;
    margin-top: 12px;
    margin-bottom: 16px;
}
.selected-info .text-sm {
    font-size: 0.9rem !important;
}
.selected-info .font-bold {
    font-weight: 700 !important;
}
#subtotal { color: #0f172a !important; }

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
.summary-movie-details {
    font-size: 0.9rem;
    color: #475569;
    margin-top: 2px;
}
.summary-movie-details strong {
    color: #0f172a;
}

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
.promo-tag.monday .promo-dot { background: #15803d; }
.promo-tag.presale {
    background: #fef3c7;
    color: #b45309;
    border-color: #fde68a;
}
.promo-tag.presale .promo-dot { background: #b45309; }

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

.btn-continue-food {
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
.btn-continue-food:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(79, 70, 229, 0.25);
}
.btn-continue-food:disabled { opacity: 0.4; cursor: not-allowed; transform: none !important; }

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

@media (min-width: 1024px) {
    .card-summary {
        position: sticky;
        top: 100px;
    }
}

/* ============================================
   NOTIFICACIÓN CENTRAL
   ============================================ */
.notification-container {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 9999;
    pointer-events: none;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 100%;
    max-width: 500px;
    padding: 0 20px;
}
.notification-container .notification {
    pointer-events: auto;
    padding: 10px 22px;
    border-radius: 16px;
    font-size: 1.2rem;
    font-weight: 700;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,0.25);
    animation: notifFadeIn 0.4s ease, notifPulse 1.5s ease-in-out 0.4s 2;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
}
.notification-container .notification .notif-icon {
    font-size: 1.8rem;
    flex-shrink: 0;
}
.notification-container .notification.info {
    background: #4f46e5;
    color: #ffffff;
    border: 2px solid #818cf8;
}
.notification-container .notification.success {
    background: #16a34a;
    color: #ffffff;
    border: 2px solid #4ade80;
}
.notification-container .notification.warning {
    background: #d97706;
    color: #ffffff;
}
.notification-container .notification.error {
    background: #dc2626;
    color: #ffffff;
    border: 2px solid #f87171;
}
@keyframes notifFadeIn {
    from { opacity: 0; transform: scale(0.85); }
    to { opacity: 1; transform: scale(1); }
}
@keyframes notifPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.03); }
}
.notification-container .notification.fade-out {
    animation: notifFadeOut 0.3s ease forwards;
}
@keyframes notifFadeOut {
    from { opacity: 1; transform: scale(1); }
    to { opacity: 0; transform: scale(0.9); }
}

@media (max-width: 768px) {
    .zoom-controls {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
        justify-content: center;
        flex-wrap: wrap;
        padding: 10px;
        background: #ffffff;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        position: sticky;
        top: 0;
        z-index: 10;
    }
    .seat-grid-scroll-wrapper {
        max-height: 50vh;
        padding: 6px;
        overflow-y: auto !important;
        overflow-x: auto !important;
    }
    .seat-grid-scroll-wrapper::-webkit-scrollbar { 
        width: 12px; 
        height: 12px; 
    }
    .seat-grid-scroll-wrapper::-webkit-scrollbar-track { 
        background: #e2e8f0; 
        border-radius: 6px; 
    }
    .seat-grid-scroll-wrapper::-webkit-scrollbar-thumb { 
        background: #6366f1; 
        border-radius: 6px;
        border: 2px solid #e2e8f0;
    }
    .seat-grid-scroll-wrapper::-webkit-scrollbar-thumb:hover { 
        background: #4f46e5; 
    }
    .seat-grid-scroll-wrapper::-webkit-scrollbar-corner {
        background: #e2e8f0;
    }
    .seat-grid-scroll-wrapper {
        scrollbar-width: thin;
        scrollbar-color: #6366f1 #e2e8f0;
    }
    .zoom-btn {
        width: 48px;
        height: 48px;
        font-size: 22px;
    }
    .zoom-label {
        font-size: 16px;
        padding: 10px 16px;
    }
    .card-summary { 
        padding: 16px; 
        position: relative; 
        top: auto; 
    }
    .summary-movie-poster { 
        width: 60px; 
        height: 90px; 
    }
    .summary-movie-title { 
        font-size: 0.95rem; 
    }
    .notification-container .notification {
        padding: 16px 20px;
        font-size: 1rem;
        border-radius: 12px;
    }
    .notification-container .notification .notif-icon {
        font-size: 1.4rem;
    }
}
@media (max-width: 640px) {
    .seat-label { 
        font-size: calc(var(--seat-size) * 0.35);
    }
    .row-label { 
        width: 18px; 
        font-size: 0.55rem; 
        padding-right: 4px; 
    }
    .seat-grid-container { 
        gap: 3px; 
        padding: 4px; 
    }
    .seat-row { 
        gap: 3px; 
    }
    .cinema-screen { 
        margin-top: 20px; 
        padding: 8px; 
        font-size: 0.6rem; 
    }
    .zoom-controls {
        gap: 8px;
        padding: 8px;
    }
    .zoom-btn {
        width: 44px;
        height: 44px;
        font-size: 20px;
    }
}
@media (max-width: 480px) {
    .row-label { 
        width: 16px; 
        font-size: 0.5rem; 
        padding-right: 3px; 
    }
    .seat-grid-container { 
        gap: 2.5px; 
        padding: 3px; 
    }
    .seat-row { 
        gap: 2.5px; 
    }
    .cinema-screen { 
        margin-top: 16px; 
        padding: 6px; 
        font-size: 0.5rem; 
        letter-spacing: 2px; 
    }
    .seat-grid-scroll-wrapper { 
        max-height: 45vh;
        padding: 4px;
    }
    .seat-grid-scroll-wrapper::-webkit-scrollbar { 
        width: 14px; 
        height: 14px; 
    }
}
</style>

<div class="container mx-auto px-2 sm:px-4 py-4 sm:py-8 max-w-7xl">
    <!-- Contenedor de Notificaciones Central -->
    <div class="notification-container" id="notificationContainer"></div>

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
                    <?= $realAvailable + count($userPendingSeats) ?> asientos disponibles
                </span>
            </div>

            <!-- CONTROLES DE ZOOM (solo visibles en móvil) -->
            <div class="zoom-controls">
                <button type="button" class="zoom-btn" id="btn-zoom-out" title="Alejar">−</button>
                <span class="zoom-label" id="btn-zoom-reset">100%</span>
                <button type="button" class="zoom-btn" id="btn-zoom-in" title="Acercar">+</button>
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
                                    $isUserPending = in_array($seatId, $userPendingSeats);
                                    
                                    $seatClass = 'seat-available';
                                    if ($isBlocked) {
                                        $seatClass = 'seat-blocked';
                                    } elseif ($isOccupied && !$isUserPending) {
                                        $seatClass = 'seat-occupied';
                                    } elseif ($isUserPending) {
                                        $seatClass = 'seat-selected';
                                    } elseif ($isAccessible) {
                                        $seatClass = 'seat-accessible';
                                    }
                                    
                                    $seatTitle = $isBlocked ? 'Pasillo' : 
                                                ($isOccupied && !$isUserPending ? 'Ocupado' : 
                                                ($isUserPending ? "Asiento $seatId (Seleccionado)" : 
                                                ($isAccessible ? "Asiento $seatId (Discapacidad ♿)" : "Asiento $seatId")));
                                ?>
                                <button 
                                    data-seat="<?= $seatId ?>" 
                                    class="seat <?= $seatClass ?>"
                                    <?= ($isOccupied && !$isUserPending || $isBlocked) ? 'disabled' : '' ?>
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

        <!-- CARD SUMMARY -->
        <div class="w-full xl:w-96 card-summary">
            <!-- SECCIÓN DE PELÍCULA -->
            <div class="flex gap-3 mb-5 items-start bg-slate-50 border border-slate-200 rounded-xl p-2.5 px-3">
                <?php 
                $posterUrl = !empty($showtime['poster_url']) ? $showtime['poster_url'] : ($tmdb_poster ? 'https://image.tmdb.org/t/p/w200' . $tmdb_poster : '');
                if (!empty($posterUrl)): 
                ?>
                    <img src="<?= htmlspecialchars($posterUrl) ?>" 
                         alt="<?= htmlspecialchars($showtime['title']) ?>" 
                         title="<?= htmlspecialchars($showtime['title']) ?>" 
                         class="summary-movie-poster">
                <?php endif; ?>
                <div class="flex flex-col justify-start text-left text-gray-900 flex-1 min-w-0">
                    <div class="summary-movie-title"><?= htmlspecialchars($showtime['title']) ?></div>
                    
                    <div class="summary-movie-details">
                        <strong>Idioma:</strong> <?= htmlspecialchars($lang_label) ?>
                    </div>
                    
                    <div class="summary-movie-details">
                        <?= htmlspecialchars($showtime['room_name']) ?> · <?= formatDateShort($showtime['show_date']) ?> · <?= formatTimeVenezuela($showtime['show_time']) ?>
                    </div>
                    
                    <div class="mt-1.5">
                        <span class="format-badge <?= $formatClass ?>"><?= htmlspecialchars($format) ?></span>
                    </div>

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

            <!-- ASIENTOS ELEGIDOS -->
            <div class="selected-info">
                <p class="text-sm text-gray-600">
                    Asientos elegidos: <span id="selected-seats-list" class="font-bold text-slate-900">-</span>
                </p>
                <p class="text-sm text-gray-600 mt-1">
                    Cantidad de boletos: <span id="ticket-count" class="font-bold text-slate-900">0 de <?= $totalSeats ?></span>
                </p>
            </div>

            <div class="summary-dotted-line"></div>

            <div class="summary-plain-row">
                <span>Subtotal</span>
                <span id="subtotalAmount"><?= formatCurrency($subtotal, $siteConfig) ?></span>
            </div>
            <div class="summary-plain-row">
                <span>IVA (<?= $taxRate ?>%)</span>
                <span id="taxAmount"><?= formatCurrency($taxAmount, $siteConfig) ?></span>
            </div>

            <div class="summary-solid-line"></div>

            <div class="summary-plain-row bold-row">
                <span>Total a Pagar</span>
                <span id="totalAmount"><?= formatCurrency($totalAmount, $siteConfig) ?></span>
            </div>

            <div class="flex flex-col gap-2.5 mt-6">
                <form action="create_food_session.php" method="POST" id="foodForm" onsubmit="return handleFormSubmit(event)">
                    <input type="hidden" name="showtime_id" value="<?= $showtime['id'] ?>">
                    <input type="hidden" name="seats" id="seats-input" value="">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="purchase_token" value="<?= htmlspecialchars($purchaseToken) ?>">
                    <button type="submit" id="btn-continue" disabled class="btn-continue-food">
                        <i class="fas fa-chair mr-2"></i> Selecciona <span id="btnSeatsCount">0</span> asiento(s)
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
const subtotal = <?= $subtotal ?>;
const taxAmount = <?= $taxAmount ?>;
const taxRate = <?= $taxRate ?>;
const occupiedSeats = <?= json_encode($occupiedSeats) ?>;
const blockedSeats = <?= json_encode($blockedSeats) ?>;
const accessibleSeats = <?= json_encode($accessibleSeats) ?>;
const userPendingSeats = <?= json_encode($userPendingSeats) ?>;
const purchaseToken = '<?= htmlspecialchars($purchaseToken) ?>';
const fromFood = <?= $fromFood ? 'true' : 'false' ?>;

const currencyConfig = {
    symbol: '<?= $siteConfig['currency_symbol'] ?? '$' ?>',
    position: '<?= $siteConfig['currency_position'] ?? 'left' ?>',
    thousands: '<?= $siteConfig['thousands_separator'] ?? '.' ?>',
    decimal: '<?= $siteConfig['decimal_separator'] ?? ',' ?>',
    decimals: <?= intval($siteConfig['decimal_places'] ?? 2) ?>
};

// ✅ Inicializar selectedSeats con los asientos pendientes del usuario
let selectedSeats = [...userPendingSeats];
const maxSeats = totalSeatsNeeded;

console.log('🚀 Inicializando seats.php');
console.log('📊 maxSeats:', maxSeats);
console.log('📊 occupiedSeats:', occupiedSeats);
console.log('📊 userPendingSeats:', userPendingSeats);
console.log('📊 selectedSeats inicial:', selectedSeats);
console.log('📊 fromFood:', fromFood);

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

// ============================================
// FUNCIONES DE FORMATO
// ============================================
function formatCurrency(amount) {
    if (typeof amount !== 'number' || isNaN(amount)) {
        amount = 0;
    }
    const formatted = amount.toFixed(currencyConfig.decimals)
        .replace('.', ',')
        .replace(/\B(?=(\d{3})+(?!\d))/g, currencyConfig.thousands);
    if (currencyConfig.position === 'left') {
        return currencyConfig.symbol + formatted;
    } else {
        return formatted + currencyConfig.symbol;
    }
}

// ============================================
// NOTIFICACIÓN CENTRAL
// ============================================
function showNotification(message, type = 'info', duration = 3000) {
    const container = document.getElementById('notificationContainer');
    if (!container) return;
    container.innerHTML = '';
    const notif = document.createElement('div');
    notif.className = 'notification ' + type;
    const icons = {
        info: 'fa-info-circle',
        success: 'fa-check-circle',
        warning: 'fa-exclamation-triangle',
        error: 'fa-times-circle'
    };
    notif.innerHTML = `
        <span class="notif-icon"><i class="fas ${icons[type] || icons.info}"></i></span>
        <span>${message}</span>
    `;
    container.appendChild(notif);
    setTimeout(() => {
        notif.classList.add('fade-out');
        setTimeout(() => {
            if (notif.parentNode) notif.remove();
        }, 300);
    }, duration);
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
    const btnSeatsCount = document.getElementById('btnSeatsCount');

    if (selectedSeatsList) {
        selectedSeatsList.innerText = count > 0 ? selectedSeats.join(', ') : '-';
    }
    if (ticketCountEl) {
        ticketCountEl.innerText = count + ' de ' + maxSeats;
    }
    if (btnSeatsCount) {
        btnSeatsCount.textContent = count;
    }

    seatsInput.value = selectedSeats.join(',');

    document.getElementById('subtotalAmount').textContent = formatCurrency(subtotal);
    document.getElementById('taxAmount').textContent = formatCurrency(taxAmount);
    document.getElementById('totalAmount').textContent = formatCurrency(totalAmount);

    if (btnContinue) {
        if (count === maxSeats) {
            btnContinue.removeAttribute('disabled');
            btnContinue.innerHTML = '<i class="fas fa-utensils mr-2"></i> Continuar a Comida';
            btnContinue.classList.remove('opacity-50', 'cursor-not-allowed');
        } else {
            btnContinue.setAttribute('disabled', 'true');
            btnContinue.classList.add('opacity-50', 'cursor-not-allowed');
            const remaining = maxSeats - count;
            btnContinue.innerHTML = `<i class="fas fa-chair mr-2"></i> Selecciona ${remaining} asiento${remaining !== 1 ? 's' : ''}`;
        }
    }

    saveSeatsToStorage();
}

// ============================================
// VALIDAR ASIENTOS
// ============================================
function validateSeats() {
    if (selectedSeats.length === 0) {
        showNotification('⚠️ Por favor, selecciona al menos un asiento.', 'warning');
        return false;
    }
    if (selectedSeats.length < maxSeats) {
        showNotification(`⚠️ Debes seleccionar ${maxSeats} asientos. Has seleccionado ${selectedSeats.length}.`, 'warning');
        return false;
    }
    const stillOccupied = selectedSeats.filter(seat => occupiedSeats.includes(seat) && !userPendingSeats.includes(seat));
    if (stillOccupied.length > 0) {
        showNotification(`❌ Asientos no disponibles: ${stillOccupied.join(', ')}`, 'error');
        return false;
    }
    return true;
}

// ============================================
// ✅ MANEJAR ENVÍO DEL FORMULARIO (CORREGIDO CON AJAX)
// ============================================
window.handleFormSubmit = function(event) {
    event.preventDefault();
    
    // Validar asientos
    if (!validateSeats()) {
        return false;
    }

    const form = document.getElementById('foodForm');
    const formData = new FormData(form);
    
    // ✅ Verificar que los asientos estén seleccionados
    const seatsInput = document.getElementById('seats-input');
    if (!seatsInput || !seatsInput.value) {
        showNotification('⚠️ No hay asientos seleccionados.', 'warning');
        return false;
    }

    const btnContinue = document.getElementById('btn-continue');
    btnContinue.disabled = true;
    btnContinue.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Procesando...';

    // ✅ Enviar con fetch (AJAX)
    fetch('create_food_session.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        // ✅ Leer la respuesta como JSON
        return response.json();
    })
    .then(data => {
        console.log('✅ Respuesta de create_food_session:', data);
        
        if (data.success) {
            // ✅ Redirigir a food_menu.php
            window.location.href = 'food_menu.php?showtime_id=' + showtimeId;
        } else {
            // ✅ Mostrar error específico
            const errorMsg = data.error || 'Error al crear la sesión. Intenta nuevamente.';
            showNotification('❌ ' + errorMsg, 'error');
            btnContinue.disabled = false;
            btnContinue.innerHTML = '<i class="fas fa-utensils mr-2"></i> Continuar a Comida';
        }
    })
    .catch(error => {
        console.error('❌ Error en fetch:', error);
        showNotification('❌ Error de conexión. Intenta nuevamente.', 'error');
        btnContinue.disabled = false;
        btnContinue.innerHTML = '<i class="fas fa-utensils mr-2"></i> Continuar a Comida';
    });
    
    return false;
};

// ============================================
// INICIALIZACIÓN DE ASIENTOS
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const seats = document.querySelectorAll('.seat:not(.seat-blocked)');
    console.log('🪑 Total de asientos disponibles:', seats.length);

    // Si venimos de food_menu.php, intentar restaurar desde sessionStorage
    if (fromFood) {
        const hasSavedSeats = loadSeatsFromStorage();
        if (hasSavedSeats) {
            console.log('✅ Asientos restaurados desde sessionStorage:', selectedSeats);
        }
    }

    // Marcar visualmente los asientos seleccionados
    seats.forEach(seat => {
        const seatId = seat.getAttribute('data-seat');
        if (selectedSeats.includes(seatId)) {
            seat.classList.remove('seat-available', 'seat-accessible', 'seat-occupied');
            seat.classList.add('seat-selected');
            seat.disabled = false;
        }
    });
    updateSummary();

    // Agregar event listeners a TODOS los asientos
    seats.forEach(seat => {
        seat.addEventListener('click', function() {
            const seatId = this.getAttribute('data-seat');
            console.log('🖱️ Clic en asiento:', seatId);

            if (blockedSeats.includes(seatId)) {
                showNotification('🚫 Este es un pasillo, no se puede seleccionar', 'warning');
                return;
            }
            // Verificar si está ocupado por otro usuario
            if (occupiedSeats.includes(seatId) && !userPendingSeats.includes(seatId)) {
                this.classList.add('seat-occupied');
                this.disabled = true;
                showNotification('❌ Este asiento ya ha sido reservado.', 'error');
                return;
            }

            const index = selectedSeats.indexOf(seatId);
            const isAccessible = accessibleSeats.includes(seatId);

            if (index > -1) {
                // Deseleccionar
                selectedSeats.splice(index, 1);
                this.classList.remove('seat-selected');
                // Si es un asiento pendiente del usuario, debe volver a disponible
                if (userPendingSeats.includes(seatId)) {
                    this.classList.add('seat-available');
                } else {
                    this.classList.add(isAccessible ? 'seat-accessible' : 'seat-available');
                }
            } else {
                if (selectedSeats.length >= maxSeats) {
                    showNotification(`⚠️ Ya tienes ${maxSeats} asientos seleccionados.`, 'warning', 4000);
                    return;
                }
                // Seleccionar
                selectedSeats.push(seatId);
                this.classList.remove('seat-available', 'seat-accessible', 'seat-occupied');
                this.classList.add('seat-selected');
            }
            
            updateSummary();
        });
    });

    console.log('✅ Event listeners agregados a', seats.length, 'asientos');

    // ============================================
    // ✅ ZOOM POR VARIABLE CSS
    // ============================================
    let currentZoom = 1;
    const minZoom = 0.8, maxZoom = 1.8, stepZoom = 0.2;
    const seatGridScrollWrapper = document.querySelector('.seat-grid-scroll-wrapper');
    const btnZoomIn = document.getElementById('btn-zoom-in');
    const btnZoomOut = document.getElementById('btn-zoom-out');
    const btnZoomReset = document.getElementById('btn-zoom-reset');
    const baseSeatSizeRem = 1.8;

    function applyZoom(newZoom) {
        currentZoom = Math.min(Math.max(newZoom, minZoom), maxZoom);
        const newSize = baseSeatSizeRem * currentZoom;
        document.documentElement.style.setProperty('--seat-size', newSize + 'rem');
        if (btnZoomReset) btnZoomReset.innerText = Math.round(currentZoom * 100) + '%';
        console.log('🔍 Zoom aplicado:', currentZoom, '| Tamaño de asiento:', newSize + 'rem');
    }

    if (btnZoomIn && btnZoomOut && btnZoomReset) {
        btnZoomIn.addEventListener('click', () => applyZoom(currentZoom + stepZoom));
        btnZoomOut.addEventListener('click', () => applyZoom(currentZoom - stepZoom));
        btnZoomReset.addEventListener('click', () => applyZoom(1));
    }

    // Verificar asientos ocupados periódicamente
    setInterval(function() {
        fetch('check_seats.php?showtime_id=<?= $showtime['id'] ?>')
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.warn('Error verificando asientos:', data.error);
                    return;
                }
                
                if (data.occupied && Array.isArray(data.occupied)) {
                    data.occupied.forEach(seatId => {
                        const seatEl = document.querySelector('[data-seat="' + seatId + '"]');
                        if (seatEl && !seatEl.classList.contains('seat-occupied')) {
                            seatEl.classList.remove('seat-selected', 'seat-available', 'seat-accessible');
                            seatEl.classList.add('seat-occupied');
                            seatEl.disabled = true;
                            const index = selectedSeats.indexOf(seatId);
                            if (index > -1) {
                                selectedSeats.splice(index, 1);
                                showNotification('⚠️ El asiento ' + seatId + ' acaba de ser reservado por otro usuario.', 'warning');
                            }
                        }
                    });
                    updateSummary();
                }
            })
            .catch(err => {
                console.log('Error checking seats:', err);
            });
    }, 30000);
});
</script>
</body>
</html>