<?php
// ============================================
// HELPERS - PRECIOS Y CONFLICTOS DE HORARIOS
// ============================================

// ============================================
// CONFLICTOS DE HORARIOS
// ============================================
function checkShowtimeConflict($pdo, $room_id, $show_date, $show_time, $duration, $exclude_id = null)
{
    $cleanup_time = 15;

    $start_minutes = strtotime($show_time) / 60;
    $end_minutes = $start_minutes + $duration;
    $end_minutes_with_cleanup = $end_minutes + $cleanup_time;

    $sql = "SELECT s.*, m.duration, m.title, r.name as room_name
            FROM showtimes s
            JOIN movies m ON s.movie_id = m.id
            JOIN rooms r ON s.room_id = r.id
            WHERE s.room_id = ?
            AND s.show_date = ?
            AND s.is_active = 1";

    if ($exclude_id) {
        $sql .= " AND s.id != ?";
    }

    $stmt = $pdo->prepare($sql);

    $params = [$room_id, $show_date];

    if ($exclude_id) {
        $params[] = $exclude_id;
    }

    $stmt->execute($params);

    $existing = $stmt->fetchAll();

    foreach ($existing as $e) {
        $existing_start = strtotime($e['show_time']) / 60;
        $existing_end = $existing_start + $e['duration'];

        $overlap = ($start_minutes < $existing_end && $end_minutes_with_cleanup > $existing_start);

        if ($overlap) {
            $overlap_start = max($start_minutes, $existing_start);
            $overlap_end = min($end_minutes_with_cleanup, $existing_end);
            $overlap_minutes = max(0, $overlap_end - $overlap_start);

            $conflict_minutes = ceil($overlap_minutes);

            $start_time_formatted = date('h:i A', strtotime($e['show_time']));
            $end_time_formatted = date('h:i A', strtotime("+{$e['duration']} minutes", strtotime($e['show_time'])));
            $room_name = $e['room_name'];

            $message = "Conflicto con: " . $e['title'] .
                " (" . $start_time_formatted . " - " . $end_time_formatted . ") - " .
                "Sala " . $room_name . " - Se superpone en " . $conflict_minutes . " minutos";

            return [
                'conflict' => true,
                'conflicting_showtime' => $e,
                'message' => $message
            ];
        }
    }

    return ['conflict' => false];
}

// ============================================
// PRECIOS
// ============================================
function getShowtimePrice($showtime)
{
    $currentDay = date('N');

    if (isset($showtime['half_price_monday']) && $showtime['half_price_monday'] == 1 && $currentDay == 1) {
        return $showtime['price'] / 2;
    }

    return $showtime['price'];
}

function getTicketPrice($movie)
{
    if (!isset($movie['price'])) {
        return 0;
    }

    $currentDay = date('N');

    if (isset($movie['half_price_monday']) && $movie['half_price_monday'] == 1 && $currentDay == 1) {
        return $movie['price'] / 2;
    }

    return $movie['price'];
}

function getTicketPriceByType($showtime, $type)
{
    $prices = [
        'adult' => $showtime['price_adult'] ?? $showtime['price'] ?? 0,
        'child' => $showtime['price_child'] ?? 0,
        'senior' => $showtime['price_senior'] ?? 0
    ];

    $currentDay = date('N');
    $promotions = $showtime['promotions'] ? explode(',', $showtime['promotions']) : [];

    if (in_array('lunes_mitad', $promotions) && $currentDay == 1) {
        $prices['adult'] = $prices['adult'] / 2;
        $prices['child'] = $prices['child'] / 2;
        $prices['senior'] = $prices['senior'] / 2;
    }

    return $prices[$type] ?? $prices['adult'];
}

// ============================================
// VALIDAR Y RECALCULAR PRECIOS
// ============================================
function validateAndRecalculatePrices($pdo, $showtimeId, $ticketsData)
{
    $stmt = $pdo->prepare("
        SELECT s.*, m.duration
        FROM showtimes s
        JOIN movies m ON s.movie_id = m.id
        WHERE s.id = ? AND s.is_active = 1
    ");

    $stmt->execute([$showtimeId]);
    $showtime = $stmt->fetch();

    if (!$showtime) {
        return ['error' => 'Función no encontrada o inactiva'];
    }

    $stmt = $pdo->query("SELECT tax_rate FROM tax_config WHERE is_active = 1 LIMIT 1");
    $tax = $stmt->fetch();
    $taxRate = $tax ? floatval($tax['tax_rate']) : 16;

    $priceAdult = floatval($showtime['price_adult'] ?? $showtime['price'] ?? 0);
    $priceChild = floatval($showtime['price_child'] ?? 0);
    $priceSenior = floatval($showtime['price_senior'] ?? 0);

    $currentDay = date('N');
    $promotions = $showtime['promotions'] ? explode(',', $showtime['promotions']) : [];

    if (in_array('lunes_mitad', $promotions) && $currentDay == 1) {
        $priceAdult = $priceAdult / 2;
        $priceChild = $priceChild / 2;
        $priceSenior = $priceSenior / 2;
    }

    $validTypes = ['adult', 'child', 'senior'];
    $totalSeats = 0;
    $subtotal = 0;

    foreach ($validTypes as $type) {
        $count = intval($ticketsData[$type] ?? 0);

        if ($count < 0) {
            $count = 0;
        }

        $totalSeats += $count;

        switch ($type) {
            case 'adult':
                $subtotal += $count * $priceAdult;
                break;
            case 'child':
                $subtotal += $count * $priceChild;
                break;
            case 'senior':
                $subtotal += $count * $priceSenior;
                break;
        }
    }

    if ($totalSeats <= 0) {
        return ['error' => 'Debes seleccionar al menos un boleto'];
    }

    $stmt = $pdo->prepare("
        SELECT r.capacity, r.seat_layout
        FROM showtimes s
        JOIN rooms r ON s.room_id = r.id
        WHERE s.id = ?
    ");

    $stmt->execute([$showtimeId]);
    $room = $stmt->fetch();

    if (!$room) {
        return ['error' => 'Sala no encontrada'];
    }

    $currentUserId = $_SESSION['user_id'] ?? 0;

    $stmt = $pdo->prepare("
        SELECT DISTINCT t.seat_code
        FROM tickets t
        WHERE t.showtime_id = ?
          AND NOT (t.user_id = ? AND t.status = 'hold')
          AND (
              t.status = 'confirmed'
              OR (
                  t.status = 'hold'
                  AND EXISTS (
                      SELECT 1
                      FROM purchases p
                      WHERE p.user_id = t.user_id
                        AND p.showtime_id = t.showtime_id
                        AND p.status = 'pending'
                        AND p.expires_at > NOW()
                  )
              )
          )
    ");

    $stmt->execute([$showtimeId, $currentUserId]);

    $occupiedSeats = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $layout = json_decode($room['seat_layout'], true);
    $blockedSeats = $layout['blockedSeats'] ?? [];

    $totalAvailable = ($layout['totalSeats'] ?? 0) - count($blockedSeats) - count($occupiedSeats);

    if ($totalAvailable < $totalSeats) {
        return ['error' => 'No hay suficientes asientos disponibles'];
    }

    $taxAmount = $subtotal * ($taxRate / 100);
    $totalAmount = $subtotal + $taxAmount;

    return [
        'success' => true,
        'subtotal' => $subtotal,
        'tax_rate' => $taxRate,
        'tax_amount' => $taxAmount,
        'total_amount' => $totalAmount,
        'total_seats' => $totalSeats,
        'prices' => [
            'adult' => $priceAdult,
            'child' => $priceChild,
            'senior' => $priceSenior
        ],
        'available_seats' => $totalAvailable
    ];
}