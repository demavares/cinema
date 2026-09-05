<?php
require_once 'config.php';

checkSessionExpired();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$stmtUser = $pdo->prepare("SELECT is_blocked FROM users WHERE id = ?");
$stmtUser->execute([$_SESSION['user_id']]);
$userData = $stmtUser->fetch();

if (!$userData) {
    header('Location: login.php');
    exit;
}

if ($userData['is_blocked'] == 1) {
    error_log("🚫 Usuario bloqueado intentó acceder a confirmation.php: user_id " . $_SESSION['user_id']);
    header('Location: index.php?error=Cuenta+bloqueada');
    exit;
}

$purchaseId = isset($_GET['purchase_id']) ? intval($_GET['purchase_id']) : 0;

if ($purchaseId <= 0 && isset($_SESSION['last_order_id'])) {
    $purchaseId = intval($_SESSION['last_order_id']);
}

if ($purchaseId <= 0) {
    header('Location: index.php?error=no_purchase_id');
    exit;
}

$stmt = $pdo->prepare("SELECT showtime_id, status FROM purchases WHERE id = ? AND user_id = ?");
$stmt->execute([$purchaseId, $_SESSION['user_id']]);
$purchaseData = $stmt->fetch();

if (!$purchaseData) {
    error_log("Intento de acceso a purchase #$purchaseId por user_id " . $_SESSION['user_id'] . " (no autorizado)");
    header('Location: index.php?error=unauthorized');
    exit;
}

if ($purchaseData['status'] !== 'completed') {
    error_log("Intento de ver purchase #$purchaseId con status: " . $purchaseData['status']);
    header('Location: index.php?error=purchase_not_completed');
    exit;
}

$showtimeId = $purchaseData['showtime_id'];

clearPurchaseSession($showtimeId);

$stmt = $pdo->prepare("
    SELECT * FROM purchases
    WHERE id = ? AND user_id = ? AND status = 'completed'
");
$stmt->execute([$purchaseId, $_SESSION['user_id']]);
$purchase = $stmt->fetch();

if (!$purchase) {
    header('Location: index.php?error=purchase_not_found');
    exit;
}

$stmt = $pdo->prepare("
    SELECT s.*, m.id as movie_id, m.title, m.poster_url, m.duration, r.name as room_name, r.seat_layout
    FROM showtimes s
    JOIN movies m ON s.movie_id = m.id
    JOIN rooms r ON s.room_id = r.id
    WHERE s.id = ?
");
$stmt->execute([$showtimeId]);
$showtime = $stmt->fetch();

if (!$showtime) {
    error_log("Showtime #$showtimeId no encontrado para purchase #$purchaseId");
    header('Location: index.php?error=showtime_not_found');
    exit;
}

// ============================================
// ✅ UNIFICADO: Obtener tipos de boleto desde tickets (ya no purchase_tickets)
// ============================================
$stmt = $pdo->prepare("
    SELECT t.*, tt.name as ticket_type_name, tt.code as ticket_type_code
    FROM tickets t
    JOIN ticket_types tt ON t.ticket_type_id = tt.id
    WHERE t.purchase_id = ? AND t.status = 'confirmed'
    ORDER BY t.id ASC
");
$stmt->execute([$purchaseId]);
$purchaseTickets = $stmt->fetchAll();

// ✅ PROCESAR ASIENTOS Y DETECTAR ACCESIBLES
$seatsFromDB = $purchase['seats'] ?? '';
$seatsArray = !empty($seatsFromDB) ? explode(',', $seatsFromDB) : [];

$seatLayout = json_decode($showtime['seat_layout'] ?? '{}', true);
$accessibleSeatsFromLayout = $seatLayout['wheelchairSeats'] ?? ($seatLayout['accessibleSeats'] ?? []);

$accessibleSeats = [];
$cleanSeatsArray = [];

foreach ($seatsArray as $seat) {
    $seat = trim($seat);
    if (empty($seat)) continue;

    $cleanSeat = str_replace('♿', '', $seat);
    $cleanSeatsArray[] = $cleanSeat;

    if (in_array($cleanSeat, $accessibleSeatsFromLayout)) {
        $accessibleSeats[] = $cleanSeat;
    }
}

$ticketCount = count($cleanSeatsArray);

$foodOrders = [];
$totalFood = 0;

$stmt = $pdo->prepare("
    SELECT fo.*, fi.name as food_name
    FROM food_orders fo
    JOIN food_items fi ON fo.food_item_id = fi.id
    WHERE fo.purchase_id = ? AND fo.status = 'completed'
    ORDER BY fo.id ASC
");
$stmt->execute([$purchaseId]);
$foodOrders = $stmt->fetchAll();

foreach ($foodOrders as $food) {
    $totalFood += floatval($food['total_price']);
}

$taxRate = floatval($purchase['tax_rate'] ?? 16);

// ============================================
// UNIFICADO: Desglose por tipo usando price_paid
// ============================================
$ticketTypes = [];
$ticketTotal = 0;

foreach ($purchaseTickets as $pt) {
    $code = $pt['ticket_type_code'] ?? 'adult';
    $name = $pt['ticket_type_name'] ?? ucfirst($code);
    $price = floatval($pt['price_paid']);

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

$groupedFood = [];
$foodTotal = 0;
$hasFood = !empty($foodOrders);

if ($hasFood) {
    foreach ($foodOrders as $food) {
        $key = $food['food_name'];

        if (!isset($groupedFood[$key])) {
            $groupedFood[$key] = [
                'name' => $food['food_name'],
                'quantity' => 0,
                'total' => 0,
                'unit_price' => floatval($food['unit_price'])
            ];
        }

        $groupedFood[$key]['quantity'] += intval($food['quantity']);
        $groupedFood[$key]['total'] += floatval($food['total_price']);
        $foodTotal += floatval($food['total_price']);
    }
}

$calculatedSubtotal = $ticketTotal + $foodTotal;
$calculatedTaxAmount = $calculatedSubtotal * ($taxRate / 100);
$calculatedTotalAmount = $calculatedSubtotal + $calculatedTaxAmount;

$savedSubtotal = floatval($purchase['subtotal'] ?? 0);
$savedTaxAmount = floatval($purchase['tax_amount'] ?? 0);
$savedTotalAmount = floatval($purchase['total_amount'] ?? 0);
$savedDataHash = $purchase['data_hash'] ?? null;

$dataString = $calculatedSubtotal . '|' . $calculatedTaxAmount . '|' . $calculatedTotalAmount . '|' . $seatsFromDB . '|' . $ticketCount . '|' . $foodTotal;
$currentHash = hash('sha256', $dataString);

$dataIntegrity = true;
$integrityIssues = [];

if ($savedDataHash !== null && $savedDataHash !== $currentHash) {
    $dataIntegrity = false;
    $integrityIssues[] = "Hash de integridad no coincide";
    error_log(sprintf(
        "🚨 INTEGRIDAD COMPROMETIDA en purchase #%d: Hash guardado=%s, Hash calculado=%s",
        $purchaseId,
        $savedDataHash,
        $currentHash
    ));
}

$subtotalDiff = abs($calculatedSubtotal - $savedSubtotal);
$taxDiff = abs($calculatedTaxAmount - $savedTaxAmount);
$totalDiff = abs($calculatedTotalAmount - $savedTotalAmount);

if ($subtotalDiff > 0.01) {
    $dataIntegrity = false;
    $integrityIssues[] = "Subtotal no coincide (dif: " . number_format($subtotalDiff, 2) . ")";
    error_log(sprintf(
        "⚠️ DISCREPANCIA en purchase #%d: Subtotal guardado=%.2f, Calculado=%.2f (Dif=%.2f)",
        $purchaseId,
        $savedSubtotal,
        $calculatedSubtotal,
        $subtotalDiff
    ));
}

if ($taxDiff > 0.01) {
    $dataIntegrity = false;
    $integrityIssues[] = "IVA no coincide (dif: " . number_format($taxDiff, 2) . ")";
    error_log(sprintf(
        "⚠️ DISCREPANCIA en purchase #%d: IVA guardado=%.2f, Calculado=%.2f (Dif=%.2f)",
        $purchaseId,
        $savedTaxAmount,
        $calculatedTaxAmount,
        $taxDiff
    ));
}

if ($totalDiff > 0.01) {
    $dataIntegrity = false;
    $integrityIssues[] = "Total no coincide (dif: " . number_format($totalDiff, 2) . ")";
    error_log(sprintf(
        "⚠️ DISCREPANCIA en purchase #%d: Total guardado=%.2f, Calculado=%.2f (Dif=%.2f)",
        $purchaseId,
        $savedTotalAmount,
        $calculatedTotalAmount,
        $totalDiff
    ));
}

$displaySubtotal = $calculatedSubtotal;
$displayTaxAmount = $calculatedTaxAmount;
$displayTotalAmount = $calculatedTotalAmount;

if (!$dataIntegrity) {
    try {
        $stmt = $pdo->prepare("
            UPDATE purchases
            SET data_integrity_check = 0,
                integrity_issues = ?
            WHERE id = ?
        ");
        $issuesJson = json_encode($integrityIssues);
        $stmt->execute([$issuesJson, $purchaseId]);
        error_log("🔴 Purchase #$purchaseId marcada con problemas de integridad: " . implode(', ', $integrityIssues));
    } catch (Exception $e) {
        error_log("Error al actualizar flag de integridad: " . $e->getMessage());
    }
} else {
    try {
        $stmt = $pdo->prepare("
            UPDATE purchases
            SET data_integrity_check = 1
            WHERE id = ? AND (data_integrity_check = 0 OR data_integrity_check IS NULL)
        ");
        $stmt->execute([$purchaseId]);
    } catch (Exception $e) {
        error_log("Error al actualizar flag de integridad: " . $e->getMessage());
    }
}

$siteConfig = getSiteConfig($pdo);
$pageTitle = "¡Compra Exitosa! - " . ($siteConfig['site_name'] ?? 'Cinema Pro');

$display_poster = !empty($showtime['poster_url']) ? $showtime['poster_url'] : '';

$promotions = !empty($showtime['promotions']) ? explode(',', $showtime['promotions']) : [];
$hasMondayPromo = in_array('lunes_mitad', $promotions);
$hasPresale = in_array('preventa', $promotions);
$language = $showtime['language'] ?? 'español';
$languageLabel = $language == 'español' ? 'Español' : 'Subtítulos en Español';

$paymentMethod = $purchase['payment_method'] ?? 'movil';
$paymentLabels = [
    'movil' => 'Pago Móvil',
    'tarjeta' => 'Tarjeta de Crédito/Débito'
];
$paymentLabel = $paymentLabels[$paymentMethod] ?? ucfirst($paymentMethod);

$paymentReference = 'N/A';
$paymentDate = $purchase['purchase_date'] ?? date('Y-m-d H:i:s');

if (!empty($purchase['payment_data'])) {
    $paymentData = json_decode($purchase['payment_data'], true);
    if (is_array($paymentData)) {
        $paymentReference = $paymentData['reference'] ?? 'N/A';
        $paymentDate = $paymentData['date'] ?? $paymentDate;
    }
}

$movieFormat = $showtime['format'] ?? '2D';
$formatClass = 'format-2d';
if (!empty($movieFormat)) {
    $formatLower = strtolower($movieFormat);
    $formatClass = 'format-' . str_replace(' ', '-', $formatLower);
}

// La función se considera realizada solo cuando ya terminó la película (hora + duración)
$functionDone = false;
$durationMin = intval($showtime['duration'] ?? 0);
if ($durationMin > 0 && !empty($showtime['show_date']) && !empty($showtime['show_time'])) {
    $functionEndTs = strtotime($showtime['show_date'] . ' ' . $showtime['show_time']) + ($durationMin * 60);
    $functionDone = $functionEndTs < time();
}

$totalTickets = intval($purchase['total_tickets'] ?? 0);
$showIntegrityWarning = !$dataIntegrity;
$integrityMessage = $showIntegrityWarning ? implode(', ', $integrityIssues) : '';

require_once 'header.php';
?>

<style>
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

    .card-summary {
        background: #ffffff !important;
        border: 1px solid #cbd5e1 !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06) !important;
        border-radius: 12px !important;
        padding: 24px;
        margin-bottom: 20px;
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
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .summary-movie-title {
        font-weight: 700;
        color: #0f172a;
        font-size: 1.1rem;
        line-height: 1.3;
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

    .promo-tag.monday .promo-dot {
        background: #15803d;
    }

    .promo-tag.presale {
        background: #fef3c7;
        color: #b45309;
        border-color: #fde68a;
    }

    .promo-tag.presale .promo-dot {
        background: #b45309;
    }

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

    .ticket-summary-item {
        display: flex;
        justify-content: space-between;
        font-size: 0.9rem;
        color: #1f2937;
        padding: 2px 0;
    }

    .ticket-summary-item .ticket-type {
        font-weight: 500;
        color: #1f2937;
    }

    .ticket-summary-item .ticket-total {
        font-weight: 600;
        color: #16a34a;
    }

    .cart-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 6px 0;
        border-bottom: 1px solid #e2e8f0;
        font-size: 0.95rem;
    }

    .cart-item:last-child {
        border-bottom: none;
    }

    .cart-item .item-name {
        color: #1f2937;
        flex: 1;
        word-break: break-word;
        font-weight: 500;
    }

    .cart-item .item-price {
        color: #16a34a;
        font-weight: 600;
        font-size: 0.95rem;
    }

    .seats-display {
        font-size: 0.95rem;
        font-weight: 500;
        color: #1f2937;
        word-break: break-word;
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
        color: #1f2937;
        font-size: 0.9rem;
        font-weight: 600;
    }

    .payment-box .payment-header .payment-value {
        color: #0f172a;
        font-weight: 700;
        font-size: 0.95rem;
    }

    .payment-box .payment-detail {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        font-size: 0.85rem;
        color: #1f2937;
        border-bottom: 1px solid #f1f5f9;
    }

    .payment-box .payment-detail:last-child {
        border-bottom: none;
    }

    .payment-box .payment-detail .detail-label {
        color: #4b5563;
        font-weight: 500;
    }

    .payment-box .payment-detail .detail-value {
        color: #0f172a;
        font-weight: 600;
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

    .print-btn {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        color: #334155 !important;
        padding: 11px 20px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.95rem;
        text-align: center;
        transition: all 0.3s ease;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .print-btn:hover {
        border-color: #6366f1;
        color: #4f46e5 !important;
        background: #eef2ff;
    }

    .section-label {
        color: #1f2937 !important;
        font-weight: 700 !important;
        font-size: 0.8rem !important;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .integrity-warning {
        background: #fef3c7;
        border: 1px solid #f59e0b;
        border-radius: 8px;
        padding: 12px 16px;
        color: #92400e;
        font-size: 0.85rem;
        margin-bottom: 16px;
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }

    .integrity-warning .warning-icon {
        font-size: 1.2rem;
        flex-shrink: 0;
        margin-top: 1px;
    }

    .integrity-warning .warning-text {
        flex: 1;
    }

    .integrity-warning .warning-text strong {
        color: #78350f;
    }

    .function-done-note {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 10px;
        background: #f1f5f9;
        border: 1px dashed #94a3b8;
        color: #475569;
        font-size: 0.8rem;
        font-weight: 600;
        padding: 4px 12px;
        border-radius: 8px;
    }

    .function-done-note i {
        color: #6366f1;
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

        .card-summary {
            padding: 16px;
        }

        .summary-movie-poster {
            width: 60px;
            height: 90px;
        }

        .summary-movie-title {
            font-size: 0.95rem;
        }

        .payment-box {
            padding: 12px;
            margin-top: 12px;
        }

        .btn-actions {
            gap: 8px;
            margin-top: 18px;
        }

        .btn-actions .btn-primary,
        .btn-actions .btn-secondary,
        .print-btn {
            padding: 10px;
            font-size: 0.85rem;
        }

        .seat-list {
            gap: 4px 8px;
        }

        .seat-item {
            padding: 1px 8px 1px 6px;
            font-size: 0.75rem;
        }

        .ticket-summary-item {
            font-size: 0.8rem;
        }

        .payment-box .payment-detail {
            font-size: 0.75rem;
        }

        .integrity-warning {
            font-size: 0.75rem;
            padding: 10px 12px;
        }
    }

    @media print {
        body {
            background: white !important;
        }

        .btn-actions,
        .print-btn,
        header,
        footer {
            display: none !important;
        }

        .confirmation-card {
            box-shadow: none !important;
            border: 1px solid #000 !important;
            max-width: 100% !important;
        }

        .card-summary {
            box-shadow: none !important;
            border: 1px solid #ccc !important;
        }

        .integrity-warning {
            display: none !important;
        }
    }
</style>

<div class="container mx-auto px-4 py-6 sm:py-10 max-w-4xl">
    <div class="confirmation-card" id="confirmationCard">
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>

        <h1 class="confirmation-title">¡Compra Confirmada!</h1>
        <p class="confirmation-subtitle">
            Tu compra se ha realizado con éxito, presente este comprobante para su facturación y confirmación. Revisa los detalles a continuación.
        </p>

        <div class="text-center mb-4">
            <span class="text-xs text-gray-500 font-semibold uppercase tracking-wider">ID de Compra</span>
            <div class="purchase-id inline-block mt-1">#<?= str_pad($purchase['id'], 8, '0', STR_PAD_LEFT) ?></div>
        </div>

        <?php if ($showIntegrityWarning): ?>
            <div class="integrity-warning">
                <span class="warning-icon">⚠️</span>
                <div class="warning-text">
                    <strong>Advertencia de integridad:</strong>
                    Se detectaron discrepancias en los datos de esta compra.
                    Por favor, contacta con soporte si tienes dudas.
                    <?php if (!empty($integrityMessage)): ?>
                        <br><small>Detalles: <?= htmlspecialchars($integrityMessage) ?></small>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="card-summary">
            <div class="flex gap-3 mb-5 items-start bg-slate-50 border border-slate-200 rounded-xl p-2.5 px-3">
                <?php if (!empty($display_poster)): ?>
                    <img src="<?= htmlspecialchars($display_poster) ?>"
                        alt="<?= htmlspecialchars($showtime['title']) ?>"
                        title="<?= htmlspecialchars($showtime['title']) ?>"
                        class="summary-movie-poster"
                        data-error-fallback>
                    <div class="summary-movie-poster flex items-center justify-center text-4xl bg-gray-100 text-gray-400" style="display:none;">
                        🎬
                    </div>
                <?php else: ?>
                    <div class="summary-movie-poster flex items-center justify-center text-4xl bg-gray-100 text-gray-400">
                        🎬
                    </div>
                <?php endif; ?>

                <div class="flex flex-col justify-start text-left text-gray-900 flex-1 min-w-0">
                    <div class="font-extrabold text-lg leading-tight text-gray-900 summary-movie-title">
                        <?= htmlspecialchars($showtime['title']) ?>
                    </div>

                    <div class="text-sm text-gray-700 font-medium mt-1.5">
                        Idioma: <?= htmlspecialchars($languageLabel) ?>
                    </div>

                    <div class="text-sm text-gray-700 font-medium mt-1 whitespace-nowrap">
                        <?= htmlspecialchars($showtime['room_name']) ?> ·
                        <?= formatDateShort($showtime['show_date']) ?> ·
                        <?= formatTimeVenezuela($showtime['show_time']) ?>
                    </div>

                    <?php if ($functionDone): ?>
                        <div class="function-done-note">
                            <i class="fas fa-flag-checkered"></i>Función ya realizada
                        </div>
                    <?php endif; ?>

                    <div class="mt-1.5">
                        <span class="format-badge <?= $formatClass ?>"><?= htmlspecialchars($movieFormat) ?></span>
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

            <div class="mb-3">
                <p class="section-label">🎫 Boletos</p>
                <?php if (!empty($ticketTypes)): ?>
                    <?php foreach ($ticketTypes as $code => $info): ?>
                        <div class="ticket-summary-item">
                            <span class="ticket-type"><?= $info['count'] ?> x <?= htmlspecialchars($info['name']) ?></span>
                            <span class="ticket-total"><?= formatCurrency($info['total'], $siteConfig) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-sm text-gray-500">No hay boletos registrados</p>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <p class="section-label">Asientos</p>
                <div class="seats-display">
                    <div class="seat-list">
                        <?php foreach ($cleanSeatsArray as $seat):
                            $isAccessible = in_array($seat, $accessibleSeats);
                        ?>
                            <span class="seat-item <?= $isAccessible ? 'accessible' : '' ?>">
                                <span class="seat-label"><?= htmlspecialchars($seat) ?></span>
                                <?php if ($isAccessible): ?>
                                    <span class="accessible-icon" title="Asiento accesible para silla de ruedas">♿</span>
                                <?php endif; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <?php if ($hasFood && !empty($groupedFood)): ?>
                <div class="mb-3">
                    <p class="section-label">🍿 Comida</p>
                    <?php foreach ($groupedFood as $item): ?>
                        <div class="cart-item">
                            <span class="item-name"><?= $item['quantity'] ?> x <?= htmlspecialchars($item['name']) ?></span>
                            <span class="item-price"><?= formatCurrency($item['total'], $siteConfig) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="summary-dotted-line"></div>

            <div class="summary-plain-row">
                <span>Subtotal</span>
                <span><?= formatCurrency($displaySubtotal, $siteConfig) ?></span>
            </div>
            <div class="summary-plain-row">
                <span>IVA (<?= number_format($taxRate, 2) ?>%)</span>
                <span><?= formatCurrency($displayTaxAmount, $siteConfig) ?></span>
            </div>

            <div class="summary-solid-line"></div>

            <div class="summary-plain-row bold-row">
                <span>💰 Total Pagado</span>
                <span><?= formatCurrency($displayTotalAmount, $siteConfig) ?></span>
            </div>

            <div class="mt-3 text-xs text-gray-400 text-right">
                <?php if ($dataIntegrity): ?>
                    <span class="text-green-600">✓ Datos verificados</span>
                <?php else: ?>
                    <span class="text-red-500">⚠️ Datos no verificados</span>
                <?php endif; ?>
                <span class="mx-1">·</span>
                <span>ID: #<?= str_pad($purchase['id'], 8, '0', STR_PAD_LEFT) ?></span>
            </div>
        </div>

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
                    <span class="detail-value"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Cliente') ?></span>
                </div>
            <?php endif; ?>

            <div class="payment-detail">
                <span class="detail-label">Fecha de Pago</span>
                <span class="detail-value"><?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($paymentDate))) ?></span>
            </div>
        </div>

        <div class="info-box mt-4">
            <p class="flex items-center gap-2">
                <i class="fas fa-info-circle text-indigo-400"></i>
                <span>Se ha enviado un correo electrónico con los detalles de tu compra a <strong><?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></strong>.</span>
            </p>
            <p class="flex items-center gap-2 mt-1">
                <i class="fas fa-qrcode text-indigo-400"></i>
                <span>Presenta este código en taquilla: <strong class="font-mono">#<?= str_pad($purchase['id'], 6, '0', STR_PAD_LEFT) ?>-<?= str_pad($totalTickets, 2, '0', STR_PAD_LEFT) ?></strong></span>
            </p>
            <?php if (!empty($accessibleSeats)): ?>
                <p class="flex items-center gap-2 mt-1 text-sky-600">
                    <i class="fas fa-wheelchair"></i>
                    <span>Asientos de accesibilidad: <strong><?= htmlspecialchars(implode(', ', $accessibleSeats)) ?></strong></span>
                </p>
            <?php endif; ?>
        </div>

        <div class="btn-actions">
            <button type="button" class="print-btn" data-print-btn>
                <i class="fas fa-print"></i> Imprimir comprobante
            </button>
            <a href="index.php" class="btn-primary">
                <i class="fas fa-home mr-2"></i> Volver al Inicio
            </a>
            <a href="movie_detail.php?id=<?= intval($showtime['movie_id']) ?>" class="btn-secondary">
                <i class="fas fa-film mr-2"></i> Ver más funciones de esta película
            </a>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>

<script nonce="<?= htmlspecialchars($cspNonce ?? '') ?>">
    document.addEventListener('DOMContentLoaded', function() {
        const showtimeId = <?= intval($showtimeId) ?>;

        console.log('🗑️ Limpiando sessionStorage para showtime:', showtimeId);

        const keysToRemove = [];
        for (let i = 0; i < sessionStorage.length; i++) {
            const key = sessionStorage.key(i);
            if (key && (
                    key.startsWith('selected_seats_' + showtimeId) ||
                    key.startsWith('selected_seats_count_' + showtimeId) ||
                    key.startsWith('food_timeout_' + showtimeId) ||
                    key.startsWith('food_seats_' + showtimeId) ||
                    key.startsWith('ticket_selection_' + showtimeId) ||
                    key.startsWith('food_order_' + showtimeId) ||
                    key.startsWith('purchase_token_' + showtimeId)
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

        if (window.history && window.history.replaceState) {
            const url = new URL(window.location.href);
            if (url.searchParams.has('purchase_id')) {
                url.searchParams.delete('purchase_id');
                window.history.replaceState({}, document.title, url.toString());
                console.log('🧹 URL limpiada (purchase_id removido)');
            }
        }
    });
</script>

</body>

</html>