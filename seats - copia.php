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
// LEER DATOS DE SESIÓN (SEGURO)
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

// Si no hay datos en sesión, redirigir a price_selection
if (!$ticketsData || $totalSeats <= 0) {
    header('Location: price_selection.php?showtime_id=' . $showtimeId);
    exit;
}

// Obtener datos del showtime, película y sala con layout
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

// Obtener asientos ocupados para este showtime
$stmtSeats = $pdo->prepare("SELECT seat_code FROM tickets WHERE showtime_id = ?");
$stmtSeats->execute([$showtimeId]);
$occupiedSeats = $stmtSeats->fetchAll(PDO::FETCH_COLUMN);

// Decodificar layout de asientos
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

// Si no hay suficientes asientos disponibles, redirigir
if ($realAvailable < $totalSeats) {
    header('Location: index.php?error=No+hay+suficientes+asientos+disponibles');
    exit;
}

$csrf_token = generateCSRFToken();

// Obtener datos de TMDb para la película
$tmdb_data = getMovieFromTMDB($showtime['title']);
$tmdb_poster = $tmdb_data['poster_path'] ?? null;

// Obtener promociones del showtime
$promotions = $showtime['promotions'] ? explode(',', $showtime['promotions']) : [];
$hasMondayPromo = in_array('lunes_mitad', $promotions);
$hasPresale = in_array('preventa', $promotions);
$language = $showtime['language'] ?? 'español';
$lang_label = $language == 'español' ? 'Español' : 'Subtítulos en Español';

// Variables para configurar el header
$pageTitle = "Selección de Asientos - " . $showtime['title'];
$backUrl = 'price_selection.php?showtime_id=' . $showtimeId;

// Obtener configuración del sitio
$siteConfig = getSiteConfig($pdo);

require_once 'header.php';
?>

<style>
    /* DISEÑO ORIGINAL DE SEATS.PHP */
    .cinema-screen {
        box-shadow: 0 3px 20px rgba(255, 255, 255, 0.08);
        background: linear-gradient(to bottom, #ffffff, #f0f0f0);
        border: 1px solid #d1d5db;
        color: #1a1a2e;
        text-align: center;
        padding: 5px;
        border-radius: 8px;
        margin-top: 28px;
        font-weight: bold;
        letter-spacing: 4px;
        font-size: clamp(0.7rem, 2vw, 1rem);
        width: 100%;
        order: 2;
    }
    
    .seat-selected {
        background-color: #6366f1 !important;
        border-color: #818cf8 !important;
        box-shadow: 0 0 10px rgba(99, 102, 241, 0.5);
        transform: scale(1.05);
    }
    
    .seat-occupied {
        background-color: #dc2626 !important;
        cursor: not-allowed !important;
        opacity: 0.5;
        border-color: #991b1b !important;
    }
    
    .seat-available {
        background-color: #4b5563 !important;
    }

    .seat-accessible {
        background-color: #0284c7 !important;
        border-color: #38bdf8 !important;
    }

    .seat-accessible:hover:not(.seat-occupied):not(.seat-blocked):not(.seat-selected) {
        background-color: #0369a1 !important;
        transform: scale(1.1);
        box-shadow: 0 0 15px rgba(56, 189, 248, 0.5);
    }
    
    .seat-available:hover:not(.seat-occupied):not(.seat-blocked):not(.seat-accessible):not(.seat-selected) {
        background-color: #4f46e5 !important;
        transform: scale(1.1);
        box-shadow: 0 0 15px rgba(99, 102, 241, 0.4);
    }
    
    .seat-blocked {
        background-color: #1a1a2e !important;
        border-color: #374151 !important;
        cursor: not-allowed !important;
        opacity: 0.3;
        box-shadow: none !important;
        transform: none !important;
    }
    
    .seat-blocked .seat-label {
        display: none !important;
    }
    
    .seat {
        width: clamp(1.2rem, 2.2vw, 1.8rem);
        height: clamp(1.2rem, 2.2vw, 1.8rem);
        border-radius: 0.3rem 0.3rem 0.2rem 0.2rem;
        transition: all 0.2s ease;
        cursor: pointer;
        border: 2px solid transparent;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    
    .seat:disabled {
        cursor: not-allowed;
    }
    
    .seat-label {
        font-size: clamp(0.5rem, 1vw, 0.7rem);
        color: #e5e7eb;
        text-align: center;
        position: absolute;
        bottom: 1px;
        left: 50%;
        transform: translateX(-50%);
        font-weight: bold;
        white-space: nowrap;
        text-shadow: 0 1px 2px rgba(0,0,0,0.5);
    }
    
    .seat-occupied .seat-label {
        color: #fca5a5;
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
        font-size: clamp(0.6rem, 1.2vw, 0.75rem);
        color: #9ca3af;
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
    
    .promo-badge {
        animation: pulse-promo 2s ease-in-out infinite;
    }
    
    @keyframes pulse-promo {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.6; }
    }
    
    .selected-info {
        background: #1a1a2e;
        border-radius: 8px;
        padding: 12px;
        min-height: 60px;
    }
    
    .total-price {
        font-size: clamp(1.2rem, 3vw, 2rem);
        font-weight: bold;
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
        color: #6b7280;
        font-weight: bold;
        text-align: right;
        padding-right: clamp(4px, 0.6vw, 8px);
        flex-shrink: 0;
        position: sticky;
        left: 0;
        background: #14141e;
        z-index: 5;
    }
    
    .seat-grid-wrapper {
        display: inline-block;
        min-width: 100%;
    }
    
    .seats-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        width: 100%;
    }
    
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

    .seat-grid-scroll-wrapper::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }
    
    .seat-grid-scroll-wrapper::-webkit-scrollbar-track {
        background: #1f2937;
        border-radius: 4px;
    }
    
    .seat-grid-scroll-wrapper::-webkit-scrollbar-thumb {
        background: #4f46e5;
        border-radius: 4px;
    }

    /* ESTILOS DEL RESUMEN */
    .summary-sticky {
        position: sticky;
        top: 100px;
        align-self: flex-start;
        max-height: none;
        overflow: visible;
        padding: 24px;
        box-sizing: border-box;
        background: #f8fafc !important;
        border: 1px solid #cbd5e1 !important;
        border-top: 4px solid #4f46e5 !important;
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08) !important;
        border-radius: 12px !important;
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
        font-size: 0.85rem;
        color: #475569;
        margin-top: 2px;
    }

    .summary-movie-details strong {
        color: #0f172a;
    }

    .summary-promo-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 600;
        margin-top: 4px;
    }
    .summary-promo-badge.lunes {
        background: #dcfce7;
        color: #15803d;
        border: 1px solid #bbf7d0;
    }
    .summary-promo-badge.preventa {
        background: #fef3c7;
        color: #b45309;
        border: 1px solid #fde68a;
    }

    .summary-language-tag {
        display: inline-block;
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
        margin-top: 4px;
        background: #dbeafe;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
    }

    .summary-total-price {
        font-size: 1.3rem;
        font-weight: 700;
        color: #16a34a;
        margin-top: 2px;
    }

    .selected-info-box {
        background: #f1f5f9 !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 8px !important;
        padding: 14px !important;
        margin-top: 12px;
        margin-bottom: 16px;
    }

    .ticket-line {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 4px 0;
        font-size: 0.95rem;
    }
    .ticket-line .ticket-label { color: #475569; }
    .ticket-line .ticket-price { color: #16a34a; font-weight: 600; }

    .seats-display {
        font-size: 0.95rem;
        font-weight: 500;
        color: #475569;
        word-break: break-word;
    }

    .btn-continue-food {
        background: linear-gradient(135deg, #4f46e5, #7c3aed);
        color: #ffffff !important;
        padding: 12px 20px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 1rem;
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
    .btn-continue-food:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none !important;
        box-shadow: none !important;
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

    /* Tema claro */
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
    .text-white {
        color: #0f172a !important;
    }
    .text-gray-400 {
        color: #475569 !important;
        font-weight: 500;
    }

    @media (max-width: 768px) {
        .seat-grid-scroll-wrapper {
            max-height: 50vh;
        }
        .summary-sticky {
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
    }
    
    @media (max-width: 640px) {
        .seat {
            width: 1.35rem;
            height: 1.35rem;
            border-width: 1.5px;
            border-radius: 3px;
        }
        .seat-label {
            font-size: 0.5rem;
            bottom: 0px;
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
            padding: 10px;
            font-size: 0.6rem;
        }
    }
    
    @media (max-width: 480px) {
        .seat {
            width: 1.2rem;
            height: 1.2rem;
            border-width: 1px;
            border-radius: 2px;
        }
        .seat-label {
            font-size: 0.45rem;
            bottom: 0px;
        }
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
            padding: 8px;
            font-size: 0.5rem;
            letter-spacing: 2px;
        }
    }
</style>

<div class="container mx-auto px-2 sm:px-4 py-4 sm:py-8 max-w-7xl">
    <div class="flex flex-col xl:flex-row gap-4 sm:gap-8 mt-2">
        <!-- Mapa de Asientos -->
        <div class="flex-1 bg-[#14141e] p-3 sm:p-6 rounded-xl border border-[#1e1e2e]">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-2">
                <div>
                    <h2 class="text-xl font-bold text-white">Selecciona tus asientos</h2>
                    <p class="text-sm text-gray-400">
                        <?= htmlspecialchars($showtime['room_name']) ?> · 
                        <?= formatDateShort($showtime['show_date']) ?> · 
                        <?= formatTimeVenezuela($showtime['show_time']) ?>
                    </p>
                </div>
                <span class="text-xs text-gray-500 bg-[#1a1a2e] px-3 py-1 rounded-full border border-[#2a2a3e]">
                    <?= $realAvailable ?> asientos disponibles
                </span>
            </div>

            <!-- Botones de Zoom (Móviles) -->
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

            <!-- Contenedor con scroll -->
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
                
                <!-- PANTALLA -->
                <div class="cinema-screen">PANTALLA</div>
            </div>

            <div class="legend">
                <div class="legend-item">
                    <div class="color-box bg-gray-500"></div>
                    Disponible
                </div>
                <div class="legend-item">
                    <div class="color-box bg-sky-600">♿</div>
                    Discapacidad
                </div>
                <div class="legend-item">
                    <div class="color-box bg-indigo-500"></div>
                    Seleccionado
                </div>
                <div class="legend-item">
                    <div class="color-box bg-red-600"></div>
                    Ocupado
                </div>
            </div>
        </div>

        <!-- Panel de Reserva - DISEÑO ORIGINAL -->
        <div class="w-full xl:w-96 summary-sticky">
            <!-- Película -->
            <div class="flex gap-4 mb-4">
                <img src="<?= $tmdb_poster ? 'https://image.tmdb.org/t/p/w200' . $tmdb_poster : ($showtime['poster_url'] ? htmlspecialchars($showtime['poster_url']) : '') ?>" 
                     alt="<?= htmlspecialchars($showtime['title']) ?>"
                     class="summary-movie-poster"
                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22150%22 viewBox=%220 0 100 150%22%3E%3Crect fill=%22%231a1a2e%22 width=%22100%22 height=%22150%22/%3E%3Ctext x=%2250%22 y=%2275%22 text-anchor=%22middle%22 fill=%22%236b7280%22 font-size=%2240%22 font-family=%22Arial%22%3E🎬%3C/text%3E%3C/svg%3E'">
                
                <div class="flex-1 min-w-0">
                    <div class="summary-movie-title"><?= htmlspecialchars($showtime['title']) ?></div>
                    
                    <!-- Promociones -->
                    <div>
                        <?php if($hasMondayPromo): ?>
                            <span class="summary-promo-badge lunes">🌙 Lunes ½ Precio</span>
                        <?php endif; ?>
                        <?php if($hasPresale): ?>
                            <span class="summary-promo-badge preventa">🎫 Preventa</span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Idioma -->
                    <div>
                        <span class="summary-language-tag"><?= $lang_label ?></span>
                    </div>
                    
                    <!-- Sala, Fecha, Hora -->
                    <div class="summary-movie-details">
                        <?= htmlspecialchars($showtime['room_name']) ?>
                    </div>
                    <div class="summary-movie-details">
                        <?= formatDateShort($showtime['show_date']) ?> · <?= formatTimeVenezuela($showtime['show_time']) ?>
                    </div>
                    
                    <!-- Total de Boletos -->
                    <div class="summary-total-price">
                        <?= formatCurrency($totalAmount, $siteConfig) ?>
                    </div>
                </div>
            </div>
            
            <hr class="border-[#e2e8f0] my-3">
            
            <!-- Selección de Asientos -->
            <div class="selected-info-box">
                <p class="text-sm text-gray-600">
                    Asientos elegidos: 
                    <span id="selected-seats-list" class="font-bold text-slate-900">-</span>
                </p>
                <p class="text-sm text-gray-600 mt-1">
                    Cantidad de boletos: 
                    <span id="ticket-count" class="font-bold text-slate-900">0 de <?= $totalSeats ?></span>
                </p>
            </div>

            <div class="flex flex-col gap-2.5 mt-4">
                <form action="food_menu.php" method="GET" id="foodForm" onsubmit="return handleFormSubmit(event)">
                    <input type="hidden" name="showtime_id" value="<?= $showtime['id'] ?>">
                    <input type="hidden" name="seats" id="seats-input" value="">
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
// CONFIGURACIÓN
// ============================================
const totalSeatsNeeded = <?= $totalSeats ?>;
const showtimeId = <?= $showtimeId ?>;
const pricePerTicket = <?= $finalPrice ?>;
const totalAmount = <?= $totalAmount ?>;
const occupiedSeats = <?= json_encode($occupiedSeats) ?>;
const blockedSeats = <?= json_encode($blockedSeats) ?>;
const accessibleSeats = <?= json_encode($accessibleSeats) ?>;

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
// FUNCIÓN PARA GUARDAR EN SESSIONSTORAGE
// ============================================
function saveSeatsToStorage() {
    try {
        sessionStorage.setItem('selected_seats_' + showtimeId, JSON.stringify(selectedSeats));
        sessionStorage.setItem('selected_seats_count_' + showtimeId, selectedSeats.length);
        console.log('✅ Asientos guardados en sessionStorage:', selectedSeats);
    } catch (e) {
        console.warn('Error guardando en sessionStorage:', e);
    }
}

// ============================================
// FUNCIÓN PARA CARGAR DESDE SESSIONSTORAGE
// ============================================
function loadSeatsFromStorage() {
    try {
        const saved = sessionStorage.getItem('selected_seats_' + showtimeId);
        if (saved) {
            const parsed = JSON.parse(saved);
            if (Array.isArray(parsed) && parsed.length > 0) {
                // Verificar que los asientos guardados no estén ocupados
                const validSeats = parsed.filter(seat => !occupiedSeats.includes(seat));
                if (validSeats.length > 0) {
                    selectedSeats = validSeats;
                    console.log('✅ Asientos cargados desde sessionStorage:', selectedSeats);
                    return true;
                }
            }
        }
    } catch (e) {
        console.warn('Error cargando desde sessionStorage:', e);
    }
    return false;
}

// ============================================
// FORMATO DE MONEDA
// ============================================
function formatCurrency(amount) {
    const symbol = currencyConfig.symbol;
    const position = currencyConfig.position;
    const thousands = currencyConfig.thousands;
    const decimal = currencyConfig.decimal;
    const decimals = currencyConfig.decimals;

    let formatted = amount.toFixed(decimals)
        .replace('.', decimal)
        .replace(/\B(?=(\d{3})+(?!\d))/g, thousands);
    
    if (position === 'right') {
        return formatted + ' ' + symbol;
    } else {
        return symbol + formatted;
    }
}

// ============================================
// ACTUALIZAR UI
// ============================================
function updateSummary() {
    const count = selectedSeats.length;

    if (count > 0) {
        document.getElementById('selected-seats-list').innerText = selectedSeats.join(', ');
        document.getElementById('btn-continue').removeAttribute('disabled');
        document.getElementById('btn-continue').classList.remove('opacity-50', 'cursor-not-allowed');
    } else {
        document.getElementById('selected-seats-list').innerText = '-';
        document.getElementById('btn-continue').setAttribute('disabled', 'true');
        document.getElementById('btn-continue').classList.add('opacity-50', 'cursor-not-allowed');
    }

    document.getElementById('ticket-count').innerText = count + ' de ' + maxSeats;
    document.getElementById('seats-input').value = selectedSeats.join(',');

    // Guardar en sessionStorage cada vez que se actualiza
    saveSeatsToStorage();
}

function validateSeats() {
    if (selectedSeats.length === 0) {
        showNotification('Por favor, selecciona al menos un asiento.', 'warning');
        return false;
    }
    
    if (selectedSeats.length < maxSeats) {
        showNotification(`Debes seleccionar ${maxSeats} asientos. Has seleccionado ${selectedSeats.length}.`, 'warning');
        return false;
    }
    
    const stillOccupied = selectedSeats.filter(seat => occupiedSeats.includes(seat));
    if (stillOccupied.length > 0) {
        showNotification(`Asientos no disponibles: ${stillOccupied.join(', ')}`, 'error');
        return false;
    }
    
    return true;
}

// ============================================
// SELECCIÓN DE ASIENTOS
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const seats = document.querySelectorAll('.seat:not(.seat-occupied):not(.seat-blocked)');
    const selectedSeatsList = document.getElementById('selected-seats-list');
    const ticketCountEl = document.getElementById('ticket-count');
    const seatsInput = document.getElementById('seats-input');
    const btnContinue = document.getElementById('btn-continue');

    // ============================================
    // 1. CARGAR ASIENTOS GUARDADOS
    // ============================================
    const hasSavedSeats = loadSeatsFromStorage();

    // ============================================
    // 2. RESTAURAR ESTADO VISUAL DE LOS ASIENTOS
    // ============================================
    if (hasSavedSeats) {
        seats.forEach(seat => {
            const seatId = seat.getAttribute('data-seat');
            if (selectedSeats.includes(seatId)) {
                seat.classList.remove('seat-available', 'seat-accessible');
                seat.classList.add('seat-selected');
            }
        });
        // Actualizar contadores
        updateSummary();
    }

    // ============================================
    // 3. EVENTOS DE CLIC EN ASIENTOS
    // ============================================
    seats.forEach(seat => {
        seat.addEventListener('click', function() {
            const seatId = this.getAttribute('data-seat');

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
                
                if (isAccessible) {
                    this.classList.add('seat-accessible');
                } else {
                    this.classList.add('seat-available');
                }
            } else {
                if (selectedSeats.length >= maxSeats) {
                    showNotification(`Máximo ${maxSeats} boletos por compra.`, 'warning');
                    return;
                }
                selectedSeats.push(seatId);
                this.classList.remove('seat-available', 'seat-accessible');
                this.classList.add('seat-selected');
            }

            updateSummary();
        });
    });

    // ============================================
    // 4. LIMPIAR SESSIONSTORAGE AL COMPLETAR COMPRA
    // ============================================
    // Cuando el usuario hace clic en "Continuar a Comida", 
    // los asientos se mantienen en sessionStorage
    // Se limpiarán al finalizar la compra en checkout.php

    // ============================================
    // 5. MANEJO DEL FORMULARIO
    // ============================================
    window.handleFormSubmit = function(event) {
        event.preventDefault();
        
        if (!validateSeats()) {
            return false;
        }
        
        const seats = seatsInput.value;
        const showtimeId = document.querySelector('input[name="showtime_id"]').value;
        
        btnContinue.disabled = true;
        btnContinue.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Procesando...';
        
        fetch('create_food_session.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'showtime_id=' + encodeURIComponent(showtimeId) + '&seats=' + encodeURIComponent(seats)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                sessionStorage.setItem('food_timeout_' + showtimeId, '600');
                sessionStorage.setItem('food_seats_' + showtimeId, seats);
                // Los asientos seleccionados ya están guardados en sessionStorage
                window.location.href = 'food_menu.php?showtime_id=' + showtimeId + '&seats=' + encodeURIComponent(seats);
            } else {
                showNotification('Error al crear la sesión. Intenta nuevamente.', 'error');
                btnContinue.disabled = false;
                btnContinue.innerHTML = '<i class="fas fa-utensils mr-2"></i> Continuar a Comida';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            window.location.href = 'food_menu.php?showtime_id=' + showtimeId + '&seats=' + encodeURIComponent(seats);
        });
        
        return false;
    };

    // ============================================
    // 6. NOTIFICACIONES
    // ============================================
    function showNotification(message, type = 'info') {
        const colors = {
            info: 'bg-blue-600',
            success: 'bg-green-600',
            warning: 'bg-yellow-600',
            error: 'bg-red-600'
        };
        
        const icons = {
            info: 'fa-info-circle',
            success: 'fa-check-circle',
            warning: 'fa-exclamation-triangle',
            error: 'fa-times-circle'
        };
        
        const notification = document.createElement('div');
        notification.className = `fixed bottom-4 left-1/2 transform -translate-x-1/2 ${colors[type] || 'bg-gray-600'} text-white px-4 sm:px-6 py-2 sm:py-3 rounded-lg shadow-lg z-50 transition-all duration-300 max-w-[90%] sm:max-w-md text-center text-sm flex items-center gap-3`;
        notification.innerHTML = `
            <i class="fas ${icons[type] || 'fa-info-circle'}"></i>
            <span>${message}</span>
        `;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translate(-50%, 20px)';
            setTimeout(() => notification.remove(), 300);
        }, 3500);
    }

    // ============================================
    // 7. ZOOM PARA MÓVILES
    // ============================================
    let currentZoom = 1;
    const minZoom = 0.8;
    const maxZoom = 1.8;
    const stepZoom = 0.2;

    const seatGridContainer = document.querySelector('.seat-grid-container');
    const seatGridWrapper = document.querySelector('.seat-grid-wrapper');
    const btnZoomIn = document.getElementById('btn-zoom-in');
    const btnZoomOut = document.getElementById('btn-zoom-out');
    const btnZoomReset = document.getElementById('btn-zoom-reset');

    function applyZoom(newZoom) {
        currentZoom = Math.min(Math.max(newZoom, minZoom), maxZoom);
        seatGridContainer.style.transform = `scale(${currentZoom})`;
        
        if (currentZoom > 1) {
            const extraWidth = seatGridContainer.offsetWidth * (currentZoom - 1);
            const extraHeight = seatGridContainer.offsetHeight * (currentZoom - 1);
            
            seatGridWrapper.style.paddingRight = `${extraWidth}px`;
            seatGridWrapper.style.paddingBottom = `${extraHeight}px`;
        } else {
            seatGridWrapper.style.paddingRight = '0px';
            seatGridWrapper.style.paddingBottom = '0px';
        }

        if (btnZoomReset) {
            btnZoomReset.innerText = `${Math.round(currentZoom * 100)}%`;
        }
    }

    if (btnZoomIn && btnZoomOut && btnZoomReset) {
        btnZoomIn.addEventListener('click', () => applyZoom(currentZoom + stepZoom));
        btnZoomOut.addEventListener('click', () => applyZoom(currentZoom - stepZoom));
        btnZoomReset.addEventListener('click', () => applyZoom(1));
    }

    // ============================================
    // 8. ACTUALIZAR ASIENTOS CADA 30 SEGUNDOS
    // ============================================
    setInterval(function() {
        fetch('check_seats.php?showtime_id=<?= $showtime['id'] ?>')
            .then(response => response.json())
            .then(data => {
                data.occupied.forEach(seatId => {
                    const seatEl = document.querySelector(`[data-seat="${seatId}"]`);
                    if (seatEl && !seatEl.classList.contains('seat-occupied')) {
                        seatEl.classList.remove('seat-selected', 'seat-available', 'seat-accessible');
                        seatEl.classList.add('seat-occupied');
                        seatEl.disabled = true;
                        const index = selectedSeats.indexOf(seatId);
                        if (index > -1) {
                            selectedSeats.splice(index, 1);
                        }
                    }
                });
                updateSummary();
            })
            .catch(err => console.log('Error checking seats:', err));
    }, 30000);

    // ============================================
    // 9. LIMPIAR SESSIONSTORAGE AL SALIR
    // ============================================
    // Los asientos se mantienen en sessionStorage
    // Se limpiarán al finalizar la compra en checkout.php
    // o si el usuario navega a otra página que no sea food_menu.php
});
</script>
</body>
</html>