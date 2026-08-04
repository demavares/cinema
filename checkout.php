<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    die("Error de seguridad: Token inválido.");
}

$userId = $_SESSION['user_id'];
$showtimeId = intval($_POST['showtime_id'] ?? 0);
$seats = trim($_POST['seats'] ?? '');
$paymentMethod = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';
$foodOrder = isset($_POST['food_order']) ? $_POST['food_order'] : '[]';

if (empty($seats) || $showtimeId <= 0) {
    header('Location: index.php?error=Datos+incompletos');
    exit;
}

if (empty($paymentMethod)) {
    $paymentMethod = 'movil';
}

$seatsArray = array_map('trim', explode(',', $seats));

// ============================================
// OBTENER DATOS DE TICKETS DESDE SESIÓN
// ============================================
$ticketQuantities = isset($_SESSION['ticket_quantities_' . $showtimeId]) 
    ? $_SESSION['ticket_quantities_' . $showtimeId] 
    : null;

if (!$ticketQuantities) {
    header('Location: index.php');
    exit;
}

// Obtener subtotal, IVA y total desde sesión
$subtotal = isset($_SESSION['subtotal_' . $showtimeId]) ? floatval($_SESSION['subtotal_' . $showtimeId]) : 0;
$taxAmount = isset($_SESSION['tax_amount_' . $showtimeId]) ? floatval($_SESSION['tax_amount_' . $showtimeId]) : 0;
$totalTicketsAmount = isset($_SESSION['total_amount_' . $showtimeId]) ? floatval($_SESSION['total_amount_' . $showtimeId]) : 0;

// Obtener tasa de IVA
$stmt = $pdo->query("SELECT tax_rate FROM tax_config WHERE is_active = 1 LIMIT 1");
$tax = $stmt->fetch();
$taxRate = $tax ? floatval($tax['tax_rate']) : 16;

// Obtener información de la sala para verificar asientos de discapacidad
$stmt = $pdo->prepare("
    SELECT s.*, r.seat_layout
    FROM showtimes s
    JOIN rooms r ON s.room_id = r.id
    WHERE s.id = ?
");
$stmt->execute([$showtimeId]);
$showtimeData = $stmt->fetch();

$accessibleSeats = [];
if ($showtimeData && !empty($showtimeData['seat_layout'])) {
    $layout = json_decode($showtimeData['seat_layout'], true);
    $accessibleSeats = $layout['wheelchairSeats'] ?? $layout['accessibleSeats'] ?? [];
}

// Obtener datos del showtime
$stmt = $pdo->prepare("
    SELECT s.*, m.title, m.duration 
    FROM showtimes s
    JOIN movies m ON s.movie_id = m.id
    WHERE s.id = ? AND s.is_active = 1
");
$stmt->execute([$showtimeId]);
$showtime = $stmt->fetch();

if (!$showtime) {
    header('Location: index.php?error=Horario+no+disponible');
    exit;
}

$price = getShowtimePrice($showtime);
$ticketCount = count($seatsArray);

// Procesar comida
$foodItems = [];
$totalFood = 0;

$foodData = json_decode($foodOrder, true);
if (!empty($foodData) && is_array($foodData)) {
    $foodIds = array_column($foodData, 'id');
    if (!empty($foodIds)) {
        $placeholders = implode(',', array_fill(0, count($foodIds), '?'));
        $stmt = $pdo->prepare("SELECT * FROM food_items WHERE id IN ($placeholders) AND is_active = 1");
        $stmt->execute($foodIds);
        $availableFood = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($availableFood as $item) {
            foreach ($foodData as $order) {
                if ($order['id'] == $item['id']) {
                    $qty = intval($order['quantity']);
                    if ($qty > 0) {
                        $foodItems[] = [
                            'id' => $item['id'],
                            'name' => $item['name'],
                            'price' => $item['price'],
                            'quantity' => $qty,
                            'total' => $item['price'] * $qty
                        ];
                        $totalFood += $item['price'] * $qty;
                    }
                    break;
                }
            }
        }
    }
}

// Calcular total general (boletos + comida)
$totalGeneral = $totalTicketsAmount + $totalFood;

// Identificar asientos de discapacidad
$accessibleSeatsSelected = [];

foreach ($seatsArray as $seat) {
    if (in_array($seat, $accessibleSeats)) {
        $accessibleSeatsSelected[] = $seat;
    }
}

// Mapeo de tipos de boleto
$ticketTypeMap = [
    'adult' => 1,
    'child' => 2,
    'senior' => 3
];

$pdo->beginTransaction();

try {
    // Verificar asientos disponibles nuevamente
    $placeholders = implode(',', array_fill(0, count($seatsArray), '?'));
    $stmtCheck = $pdo->prepare("SELECT seat_code FROM tickets WHERE showtime_id = ? AND seat_code IN ($placeholders)");
    $stmtCheck->execute(array_merge([$showtimeId], $seatsArray));
    $existingSeats = $stmtCheck->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($existingSeats)) {
        throw new Exception("Asientos ocupados: " . implode(', ', $existingSeats));
    }
    
    // Insertar tickets
    $stmtInsert = $pdo->prepare("INSERT INTO tickets (user_id, showtime_id, seat_code, price_paid) VALUES (?, ?, ?, ?)");
    $ticketIds = [];
    foreach ($seatsArray as $seat) {
        $stmtInsert->execute([$userId, $showtimeId, $seat, $price]);
        $ticketIds[] = $pdo->lastInsertId();
    }
    
    // Insertar pedidos de comida
    if (!empty($foodItems)) {
        $stmtFood = $pdo->prepare("INSERT INTO food_orders (user_id, ticket_id, showtime_id, food_item_id, quantity, unit_price, total_price, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed')");
        foreach ($foodItems as $item) {
            $ticketId = !empty($ticketIds) ? $ticketIds[0] : null;
            $stmtFood->execute([
                $userId,
                $ticketId,
                $showtimeId,
                $item['id'],
                $item['quantity'],
                $item['price'],
                $item['total']
            ]);
        }
    }
    
    // Insertar purchase_tickets con los tipos
    $stmtPurchaseTicket = $pdo->prepare("
        INSERT INTO purchase_tickets (purchase_id, showtime_id, ticket_type_id, seat_code, price) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    // Guardar asientos con marcador ♿ en la BD
    $seatsWithMarkers = [];
    $ticketTypeCounts = ['adult' => 0, 'child' => 0, 'senior' => 0];
    
    foreach ($seatsArray as $index => $seat) {
        // Determinar qué tipo de ticket corresponde a este asiento
        $type = 'adult';
        $remainingAdult = $ticketQuantities['adult'] ?? 0;
        $remainingChild = $ticketQuantities['child'] ?? 0;
        
        if ($index < $remainingAdult) {
            $type = 'adult';
        } elseif ($index < $remainingAdult + $remainingChild) {
            $type = 'child';
        } else {
            $type = 'senior';
        }
        
        $ticketTypeId = $ticketTypeMap[$type] ?? 1;
        $ticketTypeCounts[$type]++;
        
        // Obtener precio por tipo
        $priceByType = getTicketPriceByType($showtime, $type);
        
        if (in_array($seat, $accessibleSeats)) {
            $seatsWithMarkers[] = $seat . '♿';
        } else {
            $seatsWithMarkers[] = $seat;
        }
    }
    $seatsFormatted = implode(',', $seatsWithMarkers);
    
    // Registrar la compra
    $sessionToken = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    $reference = 'CMP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    
    $paymentData = json_encode([
        'method' => $paymentMethod,
        'simulated' => true,
        'reference' => $reference,
        'date' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
    
    $stmtPurchase = $pdo->prepare("
        INSERT INTO purchases (
            user_id, showtime_id, seats, total_tickets, total_food, 
            subtotal, tax_amount, tax_rate, total_amount, 
            session_token, expires_at, status, payment_method, payment_data
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?, ?)
    ");
    $stmtPurchase->execute([
        $userId,
        $showtimeId,
        $seatsFormatted,
        $ticketCount,
        $totalFood,
        $subtotal,
        $taxAmount,
        $taxRate,
        $totalGeneral,
        $sessionToken,
        $expiresAt,
        $paymentMethod,
        $paymentData
    ]);
    
    $purchaseId = $pdo->lastInsertId();
    
    // Ahora insertar purchase_tickets con el purchase_id
    foreach ($seatsArray as $index => $seat) {
        $type = 'adult';
        $remainingAdult = $ticketQuantities['adult'] ?? 0;
        $remainingChild = $ticketQuantities['child'] ?? 0;
        
        if ($index < $remainingAdult) {
            $type = 'adult';
        } elseif ($index < $remainingAdult + $remainingChild) {
            $type = 'child';
        } else {
            $type = 'senior';
        }
        
        $ticketTypeId = $ticketTypeMap[$type] ?? 1;
        $priceByType = getTicketPriceByType($showtime, $type);
        
        $stmtPurchaseTicket->execute([
            $purchaseId,
            $showtimeId,
            $ticketTypeId,
            $seat,
            $priceByType
        ]);
    }
    
    // ============================================
    // LIMPIAR TODAS LAS SESIONES DE COMPRA
    // ============================================
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
        'total_amount_' . $showtimeId,
        'purchase_token_' . $showtimeId
    ];
    foreach ($sessionKeys as $key) {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
    
    // Limpiar sessionStorage (el cliente lo hará al cargar la página)
    echo '<script>
        sessionStorage.removeItem("selected_seats_' . $showtimeId . '");
        sessionStorage.removeItem("selected_seats_count_' . $showtimeId . '");
        sessionStorage.removeItem("food_timeout_' . $showtimeId . '");
        sessionStorage.removeItem("food_seats_' . $showtimeId . '");
        sessionStorage.removeItem("ticket_selection_' . $showtimeId . '");
        console.log("🗑️ Sesión de compra limpiada");
    </script>';
    
    $pdo->commit();
    
} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: seats.php?showtime_id=' . $showtimeId . '&error=' . urlencode($e->getMessage()));
    exit;
}

// ============================================
// REDIRIGIR A CONFIRMACIÓN
// ============================================
$foodOrderJson = !empty($foodItems) ? json_encode(array_map(function($item) {
    return ['id' => $item['id'], 'quantity' => $item['quantity']];
}, $foodItems)) : '[]';

header('Location: confirmation.php?showtime_id=' . $showtimeId . '&purchase_id=' . $purchaseId);
exit;
?>