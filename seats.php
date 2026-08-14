<?php
require_once 'config.php';

// ✅ Verificar si viene con expired
if (isset($_GET['expired']) && $_GET['expired'] === '1') {
    header('Location: index.php?expired=1');
    exit;
}

if (isset($_GET['session_expired']) ||
    (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'session_expired') !== false)) {
    $keys = array_keys($_SESSION);
    foreach ($keys as $key) {
        if (strpos($key, 'purchase_') === 0 ||
            strpos($key, 'food_') === 0 ||
            strpos($key, 'ticket_') === 0 ||
            strpos($key, 'total_') === 0 ||
            strpos($key, 'subtotal_') === 0 ||
            strpos($key, 'tax_') === 0 ||
            strpos($key, 'payment_') === 0) {
            unset($_SESSION[$key]);
        }
    }
    header('Location: index.php?expired=1');
    exit;
}

checkSessionExpired();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$showtimeId = isset($_GET['showtime_id']) ? intval($_GET['showtime_id']) : 0;
if ($showtimeId <= 0) {
    header('Location: index.php');
    exit;
}

checkSessionExpired($showtimeId);

$fromFood = isset($_GET['from']) && $_GET['from'] === 'food';
$fromIndex = isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'index.php') !== false;

if ($fromIndex) {
    clearPurchaseSession($showtimeId);
    unset($_SESSION['food_valid_' . $showtimeId]);
    unset($_SESSION['food_seats_' . $showtimeId]);
    unset($_SESSION['food_timeout_' . $showtimeId]);
    unset($_SESSION['food_order_' . $showtimeId]);

    $purchaseToken = generatePurchaseTokenWithTimeout($showtimeId, 900);
    $_SESSION['purchase_token_' . $showtimeId] = $purchaseToken;
}

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
        $_SESSION['purchase_token_' . $showtimeId] = $purchaseToken;
    }
}

// VERIFICAR QUE HAYA DATOS DE BOLETOS EN SESIÓN
$ticketsData = isset($_SESSION['ticket_quantities_' . $showtimeId]) ? $_SESSION['ticket_quantities_' . $showtimeId] : null;
$totalSeats = isset($_SESSION['total_seats_' . $showtimeId]) ? intval($_SESSION['total_seats_' . $showtimeId]) : 0;
$subtotal = isset($_SESSION['subtotal_' . $showtimeId]) ? floatval($_SESSION['subtotal_' . $showtimeId]) : 0;
$taxAmount = isset($_SESSION['tax_amount_' . $showtimeId]) ? floatval($_SESSION['tax_amount_' . $showtimeId]) : 0;
$totalAmount = isset($_SESSION['total_amount_' . $showtimeId]) ? floatval($_SESSION['total_amount_' . $showtimeId]) : 0;
$taxRate = isset($_SESSION['tax_rate_' . $showtimeId]) ? floatval($_SESSION['tax_rate_' . $showtimeId]) : 16;

// LOG DE DEPURACIÓN
error_log("=== SESIÓN EN SEATS.PHP ===");
error_log("showtimeId: " . $showtimeId);
error_log("ticket_quantities_" . $showtimeId . " = " . (isset($_SESSION['ticket_quantities_' . $showtimeId]) ? json_encode($_SESSION['ticket_quantities_' . $showtimeId]) : 'NO EXISTE'));
error_log("total_seats_" . $showtimeId . " = " . ($_SESSION['total_seats_' . $showtimeId] ?? 'NO EXISTE'));
error_log("purchase_token_" . $showtimeId . " = " . ($_SESSION['purchase_token_' . $showtimeId] ?? 'NO EXISTE'));
error_log("food_seats_" . $showtimeId . " = " . ($_SESSION['food_seats_' . $showtimeId] ?? 'NO EXISTE'));
error_log("food_valid_" . $showtimeId . " = " . (isset($_SESSION['food_valid_' . $showtimeId]) ? ($_SESSION['food_valid_' . $showtimeId] ? 'true' : 'false') : 'NO EXISTE'));
error_log("============================");

$foodSeatsKey = 'food_seats_' . $showtimeId;
$foodTimeoutKey = 'food_timeout_' . $showtimeId;

// Si no hay datos de boletos en sesión, intentar recuperar de food_seats
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

                error_log("✅ seats.php - Recuperado de food_seats: $totalSeats asientos");
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
        error_log("❌ seats.php - No hay datos de boletos, redirigiendo a price_selection");
        header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Datos+de+boletos+no+encontrados');
        exit;
    }
}

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

$stmtCompleted = $pdo->prepare("
    SELECT DISTINCT t.seat_code
    FROM tickets t
    INNER JOIN purchases p ON t.user_id = p.user_id
        AND t.showtime_id = p.showtime_id
        AND t.seat_code IS NOT NULL
    WHERE t.showtime_id = ?
      AND p.status = 'completed'
");
$stmtCompleted->execute([$showtimeId]);
$completedSeats = $stmtCompleted->fetchAll(PDO::FETCH_COLUMN);

$userCompletedSeats = [];
$stmtUserCompleted = $pdo->prepare("
    SELECT DISTINCT t.seat_code
    FROM tickets t
    INNER JOIN purchases p ON t.user_id = p.user_id
        AND t.showtime_id = p.showtime_id
    WHERE t.showtime_id = ?
      AND p.user_id = ?
      AND p.status = 'completed'
");
$stmtUserCompleted->execute([$showtimeId, $_SESSION['user_id']]);
$userCompletedSeats = $stmtUserCompleted->fetchAll(PDO::FETCH_COLUMN);

$stmtPending = $pdo->prepare("
    SELECT seats
    FROM purchases
    WHERE showtime_id = ?
      AND status = 'pending'
      AND user_id != ?
      AND expires_at > NOW()
");
$stmtPending->execute([$showtimeId, $_SESSION['user_id']]);
$pendingPurchases = $stmtPending->fetchAll(PDO::FETCH_COLUMN);

$occupiedSeats = $completedSeats;
foreach ($pendingPurchases as $seatsString) {
    if (!empty($seatsString)) {
        $seatsArray = array_map('trim', explode(',', $seatsString));
        $seatsArray = array_map(function($seat) { return str_replace('♿', '', $seat); }, $seatsArray);
        $occupiedSeats = array_merge($occupiedSeats, $seatsArray);
    }
}
$occupiedSeats = array_unique($occupiedSeats);
$occupiedSeats = array_values($occupiedSeats);

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

$stmtUserPendingPurchases = $pdo->prepare("
    SELECT seats
    FROM purchases
    WHERE showtime_id = ?
      AND user_id = ?
      AND status = 'pending'
      AND expires_at > NOW()
");
$stmtUserPendingPurchases->execute([$showtimeId, $_SESSION['user_id']]);
$userPendingPurchases = $stmtUserPendingPurchases->fetchAll(PDO::FETCH_COLUMN);

foreach ($userPendingPurchases as $seatsString) {
    if (!empty($seatsString)) {
        $seatsArray = array_map('trim', explode(',', $seatsString));
        $seatsArray = array_map(function($seat) { return str_replace('♿', '', $seat); }, $seatsArray);
        $userPendingSeats = array_merge($userPendingSeats, $seatsArray);
    }
}
$userPendingSeats = array_unique($userPendingSeats);
$userPendingSeats = array_diff($userPendingSeats, $userCompletedSeats);
$userPendingSeats = array_values($userPendingSeats);

if ($fromFood) {
    $stmtRecover = $pdo->prepare("
        SELECT seat_code FROM tickets
        WHERE showtime_id = ? AND user_id = ?
          AND NOT EXISTS (
              SELECT 1 FROM purchases p
              WHERE p.user_id = tickets.user_id
                AND p.showtime_id = tickets.showtime_id
                AND p.status = 'completed'
          )
    ");
    $stmtRecover->execute([$showtimeId, $_SESSION['user_id']]);
    $recoveredSeats = $stmtRecover->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($recoveredSeats)) {
        $recoveredSeats = array_diff($recoveredSeats, $userCompletedSeats);
        $userPendingSeats = array_merge($userPendingSeats, $recoveredSeats);
        $userPendingSeats = array_unique($userPendingSeats);
    }
}

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

// ✅ Asegurar que el token esté actualizado antes de mostrar la página
if (!isset($_SESSION['purchase_token_' . $showtimeId]) || isPurchaseTokenExpired($showtimeId)) {
    $purchaseToken = generatePurchaseTokenWithTimeout($showtimeId, 900);
    $_SESSION['purchase_token_' . $showtimeId] = $purchaseToken;
} else {
    $purchaseToken = $_SESSION['purchase_token_' . $showtimeId];
}

// ✅ Si el token está por expirar (menos de 60 segundos), regenerar
$timeLeft = getPurchaseTokenTimeLeft($showtimeId);
if ($timeLeft < 60 && $timeLeft > 0) {
    error_log("🔄 seats.php: Token por expirar ($timeLeft segundos), regenerando");
    clearPurchaseSession($showtimeId);

    $purchaseToken = generatePurchaseTokenWithTimeout($showtimeId, 900);
    $_SESSION['purchase_token_' . $showtimeId] = $purchaseToken;

    // Restaurar datos de boletos
    $_SESSION['ticket_quantities_' . $showtimeId] = $ticketsData;
    $_SESSION['total_seats_' . $showtimeId] = $totalSeats;
    $_SESSION['subtotal_' . $showtimeId] = $subtotal;
    $_SESSION['tax_amount_' . $showtimeId] = $taxAmount;
    $_SESSION['total_amount_' . $showtimeId] = $totalAmount;
    $_SESSION['tax_rate_' . $showtimeId] = $taxRate;
}

$selectedSeats = $userPendingSeats;

require_once 'header.php';
?>

<style>
body { background-color: #ffffff !important; color: #1f2937 !important; }
.bg-\[\#14141e\] { background-color: #ffffff !important; }
.border-\[\#1e1e2e\] { border-color: #e2e8f0 !important; }

:root { --seat-size: clamp(1.2rem, 2.2vw, 1.8rem); }

.seat { width: var(--seat-size); height: var(--seat-size); border-radius: 0.3rem 0.3rem 0.2rem 0.2rem; transition: all 0.2s ease; cursor: pointer; border: none !important; position: relative; display: flex; align-items: center; justify-content: center; flex-shrink: 0; padding: 0 !important; overflow: hidden; }
.seat:disabled { cursor: not-allowed; }
.seat-available { background-color: #cbd5e1 !important; }
.seat-available:hover:not(.seat-occupied):not(.seat-blocked):not(.seat-accessible):not(.seat-selected) { background-color: #6366f1 !important; transform: scale(1.1); box-shadow: 0 0 12px rgba(99,102,241,0.4); }
.seat-selected { background-color: #4f46e5 !important; box-shadow: 0 0 10px rgba(79,70,229,0.5); transform: scale(1.05); }
.seat-occupied { background-color: #ef4444 !important; cursor: not-allowed !important; opacity: 0.85; }
.seat-accessible { background-color: #0284c7 !important; }
.seat-accessible .seat-label { position: static !important; transform: none !important; width: 100% !important; height: 100% !important; display: flex !important; align-items: center !important; justify-content: center !important; font-size: calc(var(--seat-size) * 0.6) !important; line-height: 1 !important; color: #ffffff !important; }
.seat-blocked { background-color: #f1f5f9 !important; cursor: not-allowed !important; opacity: 0.2; box-shadow: none !important; transform: none !important; }
.seat-blocked .seat-label { display: none !important; }
.seat-label { font-size: calc(var(--seat-size) * 0.35); color: #0f172a; text-align: center; position: absolute; bottom: 1px; left: 50%; transform: translateX(-50%); font-weight: bold; white-space: nowrap; }
.seat-selected .seat-label, .seat-occupied .seat-label { color: #ffffff !important; text-shadow: 0 1px 2px rgba(0,0,0,0.4); }

/* CORREGIDO: Pantalla en negro mate para no confundir con botón */
.cinema-screen { background: #1a1a1a !important; color: #6b7280; text-align: center; padding: 3px; border-radius: 8px; margin-top: 28px; font-weight: bold; letter-spacing: 4px; font-size: clamp(0.7rem, 2vw, 1rem); width: 100%; cursor: default; }

.legend { display: flex; gap: clamp(8px, 2vw, 20px); justify-content: center; flex-wrap: wrap; margin-top: 20px; }
.legend-item { display: flex; align-items: center; gap: 6px; font-size: clamp(0.65rem, 1.2vw, 0.8rem); color: #475569; font-weight: 500; }
.legend-item .color-box { width: clamp(14px, 2vw, 20px); height: clamp(14px, 2vw, 20px); border-radius: 4px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #fff; }

.seat-row { display: flex; gap: clamp(2px, 0.4vw, 4px); align-items: center; justify-content: center; flex-wrap: nowrap; }
.row-label { width: clamp(20px, 2.5vw, 28px); font-size: clamp(0.6rem, 1vw, 0.75rem); color: #475569; font-weight: bold; text-align: right; padding-right: clamp(4px, 0.6vw, 8px); flex-shrink: 0; position: sticky; left: 0; background: #ffffff; z-index: 5; }

.seat-grid-scroll-wrapper { width: 100%; overflow: auto; padding: 8px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; }
.seat-grid-container { display: grid; gap: clamp(2px, 0.4vw, 4px); padding: clamp(4px, 0.8vw, 10px); width: max-content; margin: 0 auto; }

.card-summary { background: #ffffff !important; border: 1px solid #cbd5e1 !important; box-shadow: 0 4px 12px rgba(0,0,0,0.06) !important; border-radius: 12px !important; padding: 24px; }
.summary-dotted-line { border-top: 2px dashed #94a3b8; margin: 14px 0; }
.summary-solid-line { border-top: 2px solid #6366f1; margin: 14px 0; }
.summary-plain-row { display: flex; justify-content: space-between; font-size: 1rem; color: #1f2937; margin-bottom: 8px; }
.summary-plain-row.bold-row { font-weight: 800; font-size: 1.15rem; }
.summary-movie-poster { width: 80px; height: 120px; object-fit: cover; border-radius: 8px; flex-shrink: 0; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.summary-movie-title { font-weight: 700; color: #0f172a; font-size: 1.1rem; line-height: 1.3; }

.promo-tag { display: inline-flex; align-items: center; gap: 6px; padding: 4px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; border: 1px solid; }
.promo-tag .promo-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
.promo-tag.monday { background: #dcfce7; color: #15803d; border-color: #bbf7d0; }
.promo-tag.monday .promo-dot { background: #15803d; }
.promo-tag.presale { background: #fef3c7; color: #b45309; border-color: #fde68a; }
.promo-tag.presale .promo-dot { background: #b45309; }

.format-badge { display: inline-flex; align-items: center; justify-content: center; padding: 2px 10px; border-radius: 5px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; line-height: 1.4; background: transparent !important; border: 1px solid #4f5e71; color: #4f5e71; }

.btn-continue-food { background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #ffffff !important; padding: 14px 20px; border-radius: 8px; font-weight: 700; font-size: 1.1rem; border: none; cursor: pointer; transition: all 0.3s ease; width: 100%; text-align: center; display: block; }
.btn-continue-food:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(79,70,229,0.25); }
.btn-continue-food:disabled { opacity: 0.4; cursor: not-allowed; transform: none !important; }

.btn-back { background: #ffffff; border: 1px solid #cbd5e1; color: #334155 !important; padding: 11px 20px; border-radius: 8px; font-weight: 600; font-size: 0.95rem; transition: all 0.3s ease; cursor: pointer; width: 100%; text-align: center; text-decoration: none; display: block; }
.btn-back:hover { border-color: #6366f1; color: #4f46e5 !important; background: #eef2ff; }

.selected-info { background: #f1f5f9 !important; border: 1px solid #e2e8f0 !important; border-radius: 8px !important; padding: 14px !important; margin-top: 12px; margin-bottom: 16px; }
.selected-info .text-sm { font-size: 0.9rem !important; }
.selected-info .font-bold { font-weight: 700 !important; }

.notification-container { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 9999; pointer-events: none; display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; max-width: 500px; padding: 0 20px; }
.notification-container .notification { pointer-events: auto; padding: 10px 22px; border-radius: 16px; font-size: 1.2rem; font-weight: 700; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.25); animation: notifFadeIn 0.4s ease, notifPulse 1.5s ease-in-out 0.4s 2; width: 100%; display: flex; align-items: center; justify-content: center; gap: 14px; }
.notification-container .notification .notif-icon { font-size: 1.8rem; flex-shrink: 0; }
.notification-container .notification.info { background: #4f46e5; color: #ffffff; border: 2px solid #818cf8; }
.notification-container .notification.success { background: #16a34a; color: #ffffff; border: 2px solid #4ade80; }
.notification-container .notification.warning { background: #d97706; color: #ffffff; }
.notification-container .notification.error { background: #dc2626; color: #ffffff; border: 2px solid #f87171; }

@keyframes notifFadeIn { from { opacity: 0; transform: scale(0.85); } to { opacity: 1; transform: scale(1); } }
@keyframes notifPulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.03); } }
.notification-container .notification.fade-out { animation: notifFadeOut 0.3s ease forwards; }
@keyframes notifFadeOut { from { opacity: 1; transform: scale(1); } to { opacity: 0; transform: scale(0.9); } }

@media (min-width: 1024px) { .card-summary { position: sticky; top: 100px; } }
@media (max-width: 768px) { .card-summary { padding: 16px; position: relative; top: auto; } .summary-movie-poster { width: 60px; height: 90px; } .seat-grid-scroll-wrapper { max-height: 50vh; } }
</style>

<div class="container mx-auto px-2 sm:px-4 py-4 sm:py-8 max-w-7xl">
    <div class="notification-container" id="notificationContainer"></div>

    <div class="flex flex-col xl:flex-row gap-4 sm:gap-8 mt-2">
        <div class="flex-1 bg-[#14141e] p-3 sm:p-6 rounded-xl border border-[#1e1e2e]">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-2">
                <div>
                    <h2 class="text-xl font-bold section-title">🎫 Selecciona tus asientos</h2>
                    <p class="text-sm section-subtitle"><?= htmlspecialchars($showtime['room_name']) ?> · <?= formatDateShort($showtime['show_date']) ?> · <?= formatTimeVenezuela($showtime['show_time']) ?></p>
                </div>
                <!-- CORREGIDO: Texto plano sin estilos de badge -->
                <span><?= $realAvailable + count($userPendingSeats) ?> asientos disponibles</span>
            </div>

            <div class="seats-container">
                <div class="seat-grid-scroll-wrapper">
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
                                    if ($isBlocked) $seatClass = 'seat-blocked';
                                    elseif ($isOccupied) $seatClass = 'seat-occupied';
                                    elseif ($isUserPending) $seatClass = 'seat-selected';
                                    elseif ($isAccessible) $seatClass = 'seat-accessible';
                                ?>
                                    <button data-seat="<?= $seatId ?>" class="seat <?= $seatClass ?>" <?= ($isOccupied || $isBlocked) ? 'disabled' : '' ?>>
                                        <?php if(!$isBlocked && !$isOccupied): ?>
                                            <span class="seat-label"><?= $isAccessible ? '♿' : $seatNumber ?></span>
                                        <?php elseif($isOccupied): ?>
                                            <span class="seat-label"><?= $seatNumber ?></span>
                                        <?php endif; ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="cinema-screen">PANTALLA</div>
            </div>

            <div class="legend">
                <!-- CORREGIDO: Color Disponible = #cbd5e1 (igual que los asientos) -->
                <div class="legend-item"><div class="color-box" style="background-color: #cbd5e1;"></div> Disponible</div>
                <div class="legend-item"><div class="color-box bg-sky-600">♿</div> Discapacidad</div>
                <div class="legend-item"><div class="color-box bg-indigo-500"></div> Seleccionado</div>
                <div class="legend-item"><div class="color-box bg-red-600"></div> Ocupado</div>
            </div>
        </div>

        <div class="w-full xl:w-96 card-summary">
            <div class="flex gap-3 mb-5 items-start bg-slate-50 border border-slate-200 rounded-xl p-2.5 px-3">
                <?php if (!empty($showtime['poster_url'])): ?>
                    <img src="<?= htmlspecialchars($showtime['poster_url']) ?>" alt="<?= htmlspecialchars($showtime['title']) ?>" class="summary-movie-poster">
                <?php endif; ?>

                <div class="flex flex-col justify-start text-left text-gray-900 flex-1 min-w-0">
                    <div class="summary-movie-title"><?= htmlspecialchars($showtime['title']) ?></div>
                    <div class="text-sm text-gray-700 font-medium mt-1.5">Idioma: <?= htmlspecialchars($lang_label) ?></div>
                    <div class="text-sm text-gray-700 font-medium mt-1"><?= htmlspecialchars($showtime['room_name']) ?> · <?= formatDateShort($showtime['show_date']) ?> · <?= formatTimeVenezuela($showtime['show_time']) ?></div>
                    <div class="mt-1.5"><span class="format-badge"><?= htmlspecialchars($format) ?></span></div>

                    <div class="flex flex-col gap-2 mt-3 items-start">
                        <?php if ($hasMondayPromo): ?><span class="promo-tag monday"><span class="promo-dot"></span> Lunes a mitad de precio</span><?php endif; ?>
                        <?php if ($hasPresale): ?><span class="promo-tag presale"><span class="promo-dot"></span> Preventa</span><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="selected-info">
                <p class="text-sm text-gray-600">Asientos elegidos: <span id="selected-seats-list" class="font-bold text-slate-900">-</span></p>
                <p class="text-sm text-gray-600 mt-1">Cantidad de boletos: <span id="ticket-count" class="font-bold text-slate-900">0 de <?= $totalSeats ?></span></p>
            </div>

            <div class="summary-dotted-line"></div>
            <div class="summary-plain-row"><span>Subtotal</span><span id="subtotalAmount"><?= formatCurrency($subtotal, $siteConfig) ?></span></div>
            <div class="summary-plain-row"><span>IVA (<?= $taxRate ?>%)</span><span id="taxAmount"><?= formatCurrency($taxAmount, $siteConfig) ?></span></div>
            <div class="summary-solid-line"></div>
            <div class="summary-plain-row bold-row"><span>Total a Pagar</span><span id="totalAmount"><?= formatCurrency($totalAmount, $siteConfig) ?></span></div>

            <div class="flex flex-col gap-2.5 mt-6">
                <form action="create_food_session.php" method="POST" id="foodForm">
                    <input type="hidden" name="showtime_id" value="<?= $showtime['id'] ?>">
                    <input type="hidden" name="seats" id="seats-input" value="">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="purchase_token" id="purchaseTokenInput" value="<?= htmlspecialchars($purchaseToken) ?>">
                    <button type="submit" id="btn-continue" disabled class="btn-continue-food"><i class="fas fa-chair mr-2"></i> Selecciona <span id="btnSeatsCount">0</span> asiento(s)</button>
                </form>
                <button type="button" class="btn-back" id="btnBackToPrices"><i class="fas fa-arrow-left mr-2"></i> Volver a Boletos</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>

<script>
// ============================================
// CONFIGURACIÓN DESDE PHP
// ============================================
const totalSeatsNeeded = <?= $totalSeats ?>;
const showtimeId = <?= $showtime['id'] ?>;
const totalAmount = <?= $totalAmount ?>;
const subtotal = <?= $subtotal ?>;
const taxAmount = <?= $taxAmount ?>;
const taxRate = <?= $taxRate ?>;
const occupiedSeats = <?= json_encode($occupiedSeats) ?>;
const blockedSeats = <?= json_encode($blockedSeats) ?>;
const accessibleSeats = <?= json_encode($accessibleSeats) ?>;
const userPendingSeats = <?= json_encode($userPendingSeats) ?>;
let purchaseToken = '<?= htmlspecialchars($purchaseToken) ?>';
const fromFood = <?= $fromFood ? 'true' : 'false' ?>;

const currencyConfig = {
    symbol: '<?= $siteConfig['currency_symbol'] ?? '$' ?>',
    position: '<?= $siteConfig['currency_position'] ?? 'left' ?>',
    thousands: '<?= $siteConfig['thousands_separator'] ?? '.' ?>',
    decimal: '<?= $siteConfig['decimal_separator'] ?? ',' ?>',
    decimals: <?= intval($siteConfig['decimal_places'] ?? 2) ?>
};

let selectedSeats = [...userPendingSeats];
const maxSeats = totalSeatsNeeded;

// Bandera para evitar liberar asientos cuando la navegación es hacia food_menu.php
let skipUnloadRelease = false;

// ============================================
// FUNCIONES
// ============================================
function formatCurrency(amount) {
    if (typeof amount !== 'number' || isNaN(amount)) amount = 0;
    const formatted = amount.toFixed(currencyConfig.decimals)
        .replace('.', currencyConfig.decimal)
        .replace(/\B(?=(\d{3})+(?!\d))/g, currencyConfig.thousands);
    return currencyConfig.position === 'left' ? currencyConfig.symbol + formatted : formatted + ' ' + currencyConfig.symbol;
}

function showNotification(message, type = 'info', duration = 3000) {
    const container = document.getElementById('notificationContainer');
    if (!container) return;

    container.innerHTML = '';

    const notif = document.createElement('div');
    notif.className = 'notification ' + type;

    const icons = { info: 'fa-info-circle', success: 'fa-check-circle', warning: 'fa-exclamation-triangle', error: 'fa-times-circle' };
    notif.innerHTML = `<span class="notif-icon"><i class="fas ${icons[type] || icons.info}"></i></span><span>${message}</span>`;

    container.appendChild(notif);

    setTimeout(() => {
        notif.classList.add('fade-out');
        setTimeout(() => { if (notif.parentNode) notif.remove(); }, 300);
    }, duration);
}

function saveSeatsToStorage() {
    try {
        sessionStorage.setItem('selected_seats_' + showtimeId, JSON.stringify(selectedSeats));
        sessionStorage.setItem('selected_seats_count_' + showtimeId, selectedSeats.length);
        sessionStorage.setItem('purchase_token_' + showtimeId, purchaseToken);
    } catch (e) {}
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
    } catch (e) {}
    return false;
}

function liberarAsientos(callback) {
    const formData = new FormData();
    formData.append('showtime_id', showtimeId);
    fetch('liberar_asientos.php', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(response => response.json())
        .then(data => { if (callback) callback(data.success); })
        .catch(() => { if (callback) callback(false); });
}

function updateSummary() {
    const count = selectedSeats.length;

    const selectedSeatsList = document.getElementById('selected-seats-list');
    const ticketCountEl = document.getElementById('ticket-count');
    const seatsInput = document.getElementById('seats-input');
    const btnContinue = document.getElementById('btn-continue');
    const btnSeatsCount = document.getElementById('btnSeatsCount');

    if (selectedSeatsList) selectedSeatsList.innerText = count > 0 ? selectedSeats.join(', ') : '-';
    if (ticketCountEl) ticketCountEl.innerText = count + ' de ' + maxSeats;
    if (btnSeatsCount) btnSeatsCount.textContent = count;
    if (seatsInput) seatsInput.value = selectedSeats.join(',');

    const subtotalEl = document.getElementById('subtotalAmount');
    const taxEl = document.getElementById('taxAmount');
    const totalEl = document.getElementById('totalAmount');

    if (subtotalEl) subtotalEl.textContent = formatCurrency(subtotal);
    if (taxEl) taxEl.textContent = formatCurrency(taxAmount);
    if (totalEl) totalEl.textContent = formatCurrency(totalAmount);

    if (btnContinue) {
        if (count === maxSeats) {
            btnContinue.disabled = false;
            btnContinue.innerHTML = '<i class="fas fa-utensils mr-2"></i> Continuar a Comida';
            btnContinue.classList.remove('opacity-50', 'cursor-not-allowed');
        } else {
            btnContinue.disabled = true;
            btnContinue.classList.add('opacity-50', 'cursor-not-allowed');
            const remaining = maxSeats - count;
            btnContinue.innerHTML = `<i class="fas fa-chair mr-2"></i> Selecciona ${remaining} asiento${remaining !== 1 ? 's' : ''}`;
        }
    }

    saveSeatsToStorage();
}

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
// EVENT LISTENERS
// ============================================
document.getElementById('btnBackToPrices').addEventListener('click', function() {
    if (!confirm('¿Estás seguro? Se liberarán los asientos seleccionados.')) return;

    liberarAsientos(function(success) {
        if (success) {
            skipUnloadRelease = true;
            window.location.href = 'price_selection.php?showtime_id=' + showtimeId;
        } else {
            alert('Error al liberar asientos. Intenta nuevamente.');
        }
    });
});

window.addEventListener('pageshow', function(event) {
    if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
        liberarAsientos();
    }
});

// CORREGIDO: beforeunload con protección
window.addEventListener('beforeunload', function() {
    if (skipUnloadRelease) return;

    const formData = new FormData();
    formData.append('showtime_id', showtimeId);
    navigator.sendBeacon('liberar_asientos.php', formData);
});

// ============================================
// ENVIAR FORMULARIO CON OBTENCIÓN DE TOKEN FRESCO
// ============================================
document.getElementById('foodForm').addEventListener('submit', function(e) {
    e.preventDefault();

    // Verificar que hay asientos seleccionados
    const count = selectedSeats.length;
    if (count === 0) {
        showNotification('⚠️ Por favor, selecciona al menos un asiento.', 'warning');
        return false;
    }

    if (count !== maxSeats) {
        showNotification(`⚠️ Debes seleccionar ${maxSeats} asientos. Has seleccionado ${count}.`, 'warning');
        return false;
    }

    const form = this;
    const btnContinue = document.getElementById('btn-continue');
    const tokenInput = document.getElementById('purchaseTokenInput');
    const seatsInput = document.getElementById('seats-input');

    // Actualizar campo de asientos
    if (seatsInput) {
        seatsInput.value = selectedSeats.join(',');
    }

    // OBTENER TOKEN FRESCO DESDE EL SERVIDOR
    btnContinue.disabled = true;
    btnContinue.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Obteniendo token...';

    fetch('get_purchase_token.php?showtime_id=' + showtimeId + '&t=' + Date.now())
        .then(response => response.json())
        .then(data => {
            if (data.success && data.token) {
                // Actualizar token en el formulario
                if (tokenInput) {
                    tokenInput.value = data.token;
                }

                // También actualizar la variable local
                purchaseToken = data.token;
                btnContinue.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Reservando asientos...';

                // ENVÍO POR AJAX PARA MEJOR MANEJO DE ERRORES
                const formData = new FormData(form);

                fetch('create_food_session.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(response => response.json())
                .then(sessionData => {
                    if (sessionData.success && sessionData.redirect) {
                        skipUnloadRelease = true;
                        window.location.href = sessionData.redirect;
                    } else {
                        showNotification('⚠️ ' + (sessionData.error || 'Error al procesar la compra'), 'error');
                        btnContinue.disabled = false;
                        btnContinue.innerHTML = '<i class="fas fa-chair mr-2"></i> Selecciona ' + count + ' asiento(s)';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('⚠️ Error de conexión al reservar.', 'error');
                    btnContinue.disabled = false;
                    btnContinue.innerHTML = '<i class="fas fa-chair mr-2"></i> Selecciona ' + count + ' asiento(s)';
                });
            } else {
                btnContinue.disabled = false;
                btnContinue.innerHTML = '<i class="fas fa-chair mr-2"></i> Selecciona ' + count + ' asiento(s)';
                showNotification('⚠️ Error al obtener token. Intenta nuevamente.', 'error');
            }
        })
        .catch(error => {
            console.error('Error obteniendo token:', error);
            btnContinue.disabled = false;
            btnContinue.innerHTML = '<i class="fas fa-chair mr-2"></i> Selecciona ' + count + ' asiento(s)';
            showNotification('⚠️ Error de conexión. Intenta nuevamente.', 'error');
        });
});

// ============================================
// INICIALIZACIÓN DE ASIENTOS
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const seats = document.querySelectorAll('.seat:not(.seat-blocked)');

    if (fromFood) loadSeatsFromStorage();

    seats.forEach(seat => {
        const seatId = seat.getAttribute('data-seat');
        if (selectedSeats.includes(seatId)) {
            seat.classList.add('seat-selected');
            seat.classList.remove('seat-available', 'seat-accessible');
        }
    });

    updateSummary();

    seats.forEach(seat => {
        seat.addEventListener('click', function() {
            const seatId = this.getAttribute('data-seat');

            if (blockedSeats.includes(seatId)) {
                showNotification('🚫 Este es un pasillo, no se puede seleccionar', 'warning');
                return;
            }

            if (occupiedSeats.includes(seatId) && !userPendingSeats.includes(seatId)) {
                showNotification('❌ Este asiento ya ha sido reservado.', 'error');
                return;
            }

            const index = selectedSeats.indexOf(seatId);
            const isAccessible = accessibleSeats.includes(seatId);

            if (index > -1) {
                selectedSeats.splice(index, 1);
                this.classList.remove('seat-selected');
                this.classList.add(isAccessible ? 'seat-accessible' : 'seat-available');
            } else {
                if (selectedSeats.length >= maxSeats) {
                    showNotification(`Ya tienes ${maxSeats} asientos seleccionados.`, 'warning', 4000);
                    return;
                }

                selectedSeats.push(seatId);
                this.classList.remove('seat-available', 'seat-accessible');
                this.classList.add('seat-selected');
            }

            updateSummary();
        });
    });

    // Verificar asientos en tiempo real
    setInterval(function() {
        fetch('check_seats.php?showtime_id=' + showtimeId)
            .then(response => response.json())
            .then(data => {
                if (data.occupied) {
                    data.occupied.forEach(seatId => {
                        const seatEl = document.querySelector('[data-seat="' + seatId + '"]');
                        if (seatEl && !seatEl.classList.contains('seat-occupied')) {
                            seatEl.classList.remove('seat-selected', 'seat-available', 'seat-accessible');
                            seatEl.classList.add('seat-occupied');
                            seatEl.disabled = true;

                            const index = selectedSeats.indexOf(seatId);
                            if (index > -1) {
                                selectedSeats.splice(index, 1);
                                showNotification('El asiento ' + seatId + ' acaba de ser reservado.', 'warning');
                            }
                        }
                    });
                    updateSummary();
                }
            })
            .catch(err => console.log('Error checking seats:', err));
    }, 15000);
});
</script>

</body>
</html>