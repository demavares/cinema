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
checkSessionExpired($showtimeId);
$fromFood = isset($_GET['from']) && $_GET['from'] === 'food';
$ticketsKey = 'ticket_quantities_' . $showtimeId;
$totalSeatsKey = 'total_seats_' . $showtimeId;
$ticketsData = $_SESSION[$ticketsKey] ?? null;
$totalSeats = $_SESSION[$totalSeatsKey] ?? 0;
if (!$ticketsData || $totalSeats <= 0) {
header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=No+hay+boletos+seleccionados');
exit;
}
$subtotal = $_SESSION['subtotal_' . $showtimeId] ?? 0;
$taxAmount = $_SESSION['tax_amount_' . $showtimeId] ?? 0;
$totalAmount = $_SESSION['total_amount_' . $showtimeId] ?? 0;
$taxRate = $_SESSION['tax_rate_' . $showtimeId] ?? 16;
$stmt = $pdo->prepare("
SELECT s.*, m.title, m.poster_url, r.name as room_name, r.seat_layout
FROM showtimes s
JOIN movies m ON s.movie_id = m.id
JOIN rooms r ON s.room_id = r.id
WHERE s.id = ? AND s.is_active = 1
");
$stmt->execute([$showtimeId]);
$showtime = $stmt->fetch();
if (!$showtime) {
header('Location: index.php?error=Función+no+encontrada');
exit;
}
$showtimeDateTime = strtotime($showtime['show_date'] . ' ' . $showtime['show_time']);
$currentDateTime = time();
$safetyMargin = 15 * 60;
if (($showtimeDateTime + $safetyMargin) < $currentDateTime) {
header('Location: index.php?error=Este+horario+ya+no+está+disponible');
exit;
}
// ============================================
// 🧹 UNIFICADO: Limpiar reservas 'hold' huérfanas (sin compra pending válida)
// ============================================
try {
$stmtCleanZombies = $pdo->prepare("
DELETE t FROM tickets t
WHERE t.showtime_id = ?
AND t.status = 'hold'
AND NOT EXISTS (
SELECT 1 FROM purchases p
WHERE p.user_id = t.user_id
AND p.showtime_id = t.showtime_id
AND p.status = 'pending'
AND p.expires_at > NOW()
)
");
$stmtCleanZombies->execute([$showtimeId]);
$zombiesDeleted = $stmtCleanZombies->rowCount();
if ($zombiesDeleted > 0) {
error_log("🧹 seats.php: Limpiadas $zombiesDeleted reservas hold huérfanas del showtime $showtimeId");
}
} catch (Exception $e) {
error_log("⚠️ seats.php: Error limpiando reservas hold: " . $e->getMessage());
}
// ============================================
// ✅ UNIFICADO: Asientos ocupados = cualquier ticket activo de OTRO usuario
// ============================================
$stmtOccupied = $pdo->prepare("
SELECT DISTINCT seat_code FROM tickets
WHERE showtime_id = ? AND user_id != ?
AND status IN ('hold', 'confirmed')
");
$stmtOccupied->execute([$showtimeId, $_SESSION['user_id']]);
$occupiedSeats = $stmtOccupied->fetchAll(PDO::FETCH_COLUMN);
// Asientos ya comprados (confirmed) por el propio usuario
$stmtCompleted = $pdo->prepare("
SELECT seat_code FROM tickets
WHERE showtime_id = ? AND user_id = ? AND status = 'confirmed'
");
$stmtCompleted->execute([$showtimeId, $_SESSION['user_id']]);
$userCompletedSeats = $stmtCompleted->fetchAll(PDO::FETCH_COLUMN);
$occupiedSeats = array_unique(array_merge($occupiedSeats, $userCompletedSeats));
// Recuperar reservas 'hold' activas del propio usuario (compras pending válidas)
$userPendingSeats = [];
$stmtUserPendingPurchases = $pdo->prepare("
SELECT seats FROM purchases
WHERE showtime_id = ? AND user_id = ? AND status = 'pending' AND expires_at > NOW()
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
// ✅ UNIFICADO: Recuperar asientos 'hold' del propio usuario
$stmtRecover = $pdo->prepare("
SELECT seat_code FROM tickets
WHERE showtime_id = ? AND user_id = ? AND status = 'hold'
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
if (!isset($_SESSION['purchase_token_' . $showtimeId]) || isPurchaseTokenExpired($showtimeId)) {
$purchaseToken = generatePurchaseTokenWithTimeout($showtimeId, 900);
$_SESSION['purchase_token_' . $showtimeId] = $purchaseToken;
} else {
$purchaseToken = $_SESSION['purchase_token_' . $showtimeId];
}
$timeLeft = getPurchaseTokenTimeLeft($showtimeId);
if ($timeLeft < 60 && $timeLeft > 0) {
clearPurchaseSession($showtimeId);
$purchaseToken = generatePurchaseTokenWithTimeout($showtimeId, 900);
$_SESSION['purchase_token_' . $showtimeId] = $purchaseToken;
$_SESSION['ticket_quantities_' . $showtimeId] = $ticketsData;
$_SESSION['total_seats_' . $showtimeId] = $totalSeats;
$_SESSION['subtotal_' . $showtimeId] = $subtotal;
$_SESSION['tax_amount_' . $showtimeId] = $taxAmount;
$_SESSION['total_amount_' . $showtimeId] = $totalAmount;
$_SESSION['tax_rate_' . $showtimeId] = $taxRate;
}
$selectedSeats = $userPendingSeats;
// ✅ NUEVO: Ilustración personalizada de asientos (solo si este horario tiene imagen subida)
$customSeatMap = $showtime['seat_map_image'] ?? '';
$hasCustomSeatMap = !empty($customSeatMap) && file_exists($customSeatMap);
require_once 'header.php';
?>
<link rel="stylesheet" href="assets/css/shared-panel.css">
<link rel="stylesheet" href="assets/css/seats.css">
<div class="container mx-auto px-2 sm:px-4 py-4 sm:py-8 max-w-7xl">
<div class="notification-container" id="notificationContainer"></div>
<div class="flex flex-col xl:flex-row gap-4 sm:gap-8 mt-2">
<div class="flex-1 bg-[#14141e] p-3 sm:p-6 rounded-xl border border-[#1e1e2e]">
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-2">
<div>
<h2 class="text-xl font-bold section-title">🎫 Selecciona tus asientos</h2>
<p class="text-sm section-subtitle"><?= htmlspecialchars($showtime['room_name']) ?> · <?= formatDateShort($showtime['show_date']) ?> · <?= formatTimeVenezuela($showtime['show_time']) ?></p>
</div>
<span><?= $realAvailable + count($userPendingSeats) ?> asientos disponibles</span>
</div>
<div class="seats-container">
<!-- ✅ NUEVO: Ilustración personalizada (solo si este horario tiene imagen subida) -->
<?php if ($hasCustomSeatMap): ?>
<div class="custom-seat-map">
<img src="<?= htmlspecialchars($customSeatMap) ?>?v=<?= filemtime($customSeatMap) ?>"
alt="Ilustración de la sala" class="custom-seat-map-img">
</div>
<?php endif; ?>
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
<button data-seat="<?= $seatId ?>" class="seat <?= $seatClass ?> <?= ($isAccessible && $isOccupied) ? 'seat-accessible' : '' ?>" <?= ($isOccupied || $isBlocked) ? 'disabled' : '' ?>>
<?php if(!$isBlocked): ?>
<span class="seat-label"><?= $isAccessible ? '♿' : $seatNumber ?></span>
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
<div class="legend-item"><div class="color-box" style="background-color: #cbd5e1;"></div> Disponible</div>
<?php if (!empty($accessibleSeats)): ?><div class="legend-item"><div class="color-box bg-sky-600">♿</div> Discapacitado</div><?php endif; ?>
<div class="legend-item"><div class="color-box bg-indigo-500"></div> Seleccionado</div>
<div class="legend-item"><div class="color-box bg-red-600"></div> Ocupado</div>
</div>
</div>
<div class="w-full xl:w-96 card-summary">
<div class="flex gap-3 mb-5 items-start bg-slate-50 border border-slate-200 rounded-xl p-2.5 px-3">
<?php if (!empty($showtime['poster_url'])): ?>
<img src="<?= htmlspecialchars($showtime['poster_url']) ?>" alt="<?= htmlspecialchars($showtime['title']) ?>" title="<?= htmlspecialchars($showtime['title']) ?>" class="summary-movie-poster">
<?php endif; ?>
<div class="flex flex-col justify-start text-left text-gray-900 flex-1 min-w-0">
<div class="summary-movie-title"><?= htmlspecialchars($showtime['title']) ?></div>
<div class="text-sm text-gray-700 font-medium mt-1.5">Idioma: <?= htmlspecialchars($lang_label) ?></div>
<div class="text-sm text-gray-700 font-medium mt-1"><?= htmlspecialchars($showtime['room_name']) ?> · <?= formatDateShort($showtime['show_date']) ?> · <?= formatTimeVenezuela($showtime['show_time']) ?></div>
<div class="mt-1.5"><span class="format-badge"><?= htmlspecialchars($format) ?></span></div>
<div class="flex flex-col gap-2 mt-3 items-start">
<?php if (strtotime($showtime['show_date'] . ' ' . $showtime['show_time']) < time()): ?><span class="started-tag"><i class="fas fa-clock"></i> Ya inició Función</span><?php endif; ?>
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
<script nonce="<?= htmlspecialchars($cspNonce ?? '') ?>">
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
};</script>
<script src="assets/js/seats.js"></script>
</body>
</html>