<?php
require_once 'config.php';

// ============================================
// VERIFICAR AUTENTICACIÓN
// ============================================
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Verificar CSRF
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    die("Error de seguridad: Token inválido.");
}

// ============================================
// OBTENER DATOS DEL POST
// ============================================
$userId = $_SESSION['user_id'];
$showtimeId = intval($_POST['showtime_id'] ?? 0);
$seats = trim($_POST['seats'] ?? '');
$paymentMethod = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
$foodOrder = isset($_POST['food_order']) ? $_POST['food_order'] : '[]';
$sessionToken = isset($_POST['session_token']) ? trim($_POST['session_token']) : '';

// ============================================
// LOGS DE DEBUG
// ============================================
error_log("=== CHECKOUT START ===");
error_log("showtime_id: " . $showtimeId);
error_log("seats: " . $seats);
error_log("payment_method: " . $paymentMethod);
error_log("session_token: " . $sessionToken);

// Validaciones básicas
if ($showtimeId <= 0 || empty($seats) || empty($paymentMethod)) {
    error_log("ERROR: Datos incompletos");
    header('Location: index.php?error=Datos+incompletos');
    exit;
}

// ============================================
// OBTENER DATOS DE TICKETS DESDE SESIÓN
// ============================================
$ticketQuantities = isset($_SESSION['ticket_quantities_' . $showtimeId]) 
    ? $_SESSION['ticket_quantities_' . $showtimeId] 
    : null;

if (!$ticketQuantities) {
    error_log("ERROR: No hay datos de tickets en sesión");
    header('Location: price_selection.php?showtime_id=' . $showtimeId);
    exit;
}

// Obtener subtotal, IVA y total desde sesión
$subtotal = isset($_SESSION['subtotal_' . $showtimeId]) ? floatval($_SESSION['subtotal_' . $showtimeId]) : 0;
$taxAmount = isset($_SESSION['tax_amount_' . $showtimeId]) ? floatval($_SESSION['tax_amount_' . $showtimeId]) : 0;
$totalTicketsAmount = isset($_SESSION['total_amount_' . $showtimeId]) ? floatval($_SESSION['total_amount_' . $showtimeId]) : 0;

// Obtener total de asientos desde sesión
$totalSeats = isset($_SESSION['total_seats_' . $showtimeId]) ? intval($_SESSION['total_seats_' . $showtimeId]) : 0;

// Obtener tasa de IVA
$stmt = $pdo->query("SELECT tax_rate FROM tax_config WHERE is_active = 1 LIMIT 1");
$tax = $stmt->fetch();
$taxRate = $tax ? floatval($tax['tax_rate']) : 16;

// ============================================
// OBTENER DATOS DEL SHOWTIME
// ============================================
$stmt = $pdo->prepare("
    SELECT s.*, m.title, m.duration, r.seat_layout
    FROM showtimes s
    JOIN movies m ON s.movie_id = m.id
    JOIN rooms r ON s.room_id = r.id
    WHERE s.id = ? AND s.is_active = 1
");
$stmt->execute([$showtimeId]);
$showtime = $stmt->fetch();

if (!$showtime) {
    error_log("ERROR: Showtime no encontrado");
    header('Location: index.php?error=Horario+no+disponible');
    exit;
}

// ============================================
// PROCESAR ASIENTOS
// ============================================
$seatsArray = array_map('trim', explode(',', $seats));
$ticketCount = count($seatsArray);

// Verificar que el número de asientos coincida con el total esperado
if ($ticketCount != $totalSeats) {
    error_log("ERROR: Número de asientos no coincide. Esperado: $totalSeats, Recibido: " . $ticketCount);
    header('Location: seats.php?showtime_id=' . $showtimeId . '&error=Asientos+no+coinciden');
    exit;
}

// Obtener asientos de discapacidad del layout
$accessibleSeats = [];
if (!empty($showtime['seat_layout'])) {
    $layout = json_decode($showtime['seat_layout'], true);
    $accessibleSeats = $layout['wheelchairSeats'] ?? $layout['accessibleSeats'] ?? [];
}

// ============================================
// PROCESAR COMIDA
// ============================================
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

// ============================================
// CALCULAR TOTAL GENERAL
// ============================================
$totalGeneral = $totalTicketsAmount + $totalFood;

error_log("subtotal: $subtotal");
error_log("tax_amount: $taxAmount");
error_log("total_tickets_amount: $totalTicketsAmount");
error_log("total_food: $totalFood");
error_log("total_general: $totalGeneral");

// ============================================
// GENERAR TOKEN SI ES NECESARIO
// ============================================
if (empty($sessionToken)) {
    $sessionToken = bin2hex(random_bytes(16));
    error_log("Nuevo token generado: " . $sessionToken);
}

// ============================================
// PROCESAR INFORMACIÓN DEL MÉTODO DE PAGO
// ============================================
$paymentInfo = '';

switch ($paymentMethod) {
    case 'movil':
    case 'pago_movil':
    case 'pago_móvil':
        $referencia = isset($_POST['pm_referencia']) ? trim($_POST['pm_referencia']) : '123456';
        $banco = isset($_POST['pm_banco']) ? trim($_POST['pm_banco']) : 'Banco de Venezuela';
        $telefono = isset($_POST['pm_telefono']) ? trim($_POST['pm_telefono']) : '0412-1234567';
        $paymentInfo = json_encode([
            'method' => 'movil',
            'bank' => $banco,
            'phone' => $telefono,
            'reference' => $referencia
        ]);
        break;

    case 'tarjeta':
    case 'card':
        $cardName = isset($_POST['card_name']) ? trim($_POST['card_name']) : 'Cliente Prueba';
        $cardNumber = isset($_POST['card_number']) ? trim($_POST['card_number']) : '1234567890123456';
        $last4 = strlen($cardNumber) >= 4 ? substr($cardNumber, -4) : '1234';
        $paymentInfo = json_encode([
            'method' => 'tarjeta',
            'holder' => $cardName,
            'last4' => $last4
        ]);
        break;

    default:
        $paymentInfo = json_encode([
            'method' => $paymentMethod,
            'reference' => 'N/A'
        ]);
        break;
}

error_log("paymentInfo: " . $paymentInfo);

// ============================================
// MAPEO DE TIPOS DE BOLETO
// ============================================
$ticketTypeMap = [
    'adult' => 1,
    'child' => 2,
    'senior' => 3
];

// ============================================
// INICIAR TRANSACCIÓN
// ============================================
$pdo->beginTransaction();

try {
    // ============================================
    // 1. VERIFICAR ASIENTOS DISPONIBLES
    // ============================================
    $placeholders = implode(',', array_fill(0, count($seatsArray), '?'));
    $stmtCheck = $pdo->prepare("SELECT seat_code FROM tickets WHERE showtime_id = ? AND seat_code IN ($placeholders)");
    $stmtCheck->execute(array_merge([$showtimeId], $seatsArray));
    $existingSeats = $stmtCheck->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($existingSeats)) {
        throw new Exception("Asientos ocupados: " . implode(', ', $existingSeats));
    }
    
    // ============================================
    // 2. INSERTAR TICKETS EN tickets
    // ============================================
    $pricePerTicket = $totalTicketsAmount > 0 && count($seatsArray) > 0 ? $totalTicketsAmount / count($seatsArray) : 0;
    
    $stmtInsertTicket = $pdo->prepare("INSERT INTO tickets (user_id, showtime_id, seat_code, price_paid) VALUES (?, ?, ?, ?)");
    $ticketIds = [];
    
    foreach ($seatsArray as $seat) {
        $stmtInsertTicket->execute([$userId, $showtimeId, $seat, $pricePerTicket]);
        $ticketIds[] = $pdo->lastInsertId();
        error_log("Ticket insertado: " . $seat);
    }
    error_log("Total tickets insertados: " . count($ticketIds));
    
    // ============================================
    // 3. INSERTAR PEDIDOS DE COMIDA EN food_orders
    // ============================================
    if (!empty($foodItems)) {
        $stmtFood = $pdo->prepare("
            INSERT INTO food_orders (user_id, ticket_id, showtime_id, food_item_id, quantity, unit_price, total_price, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'completed')
        ");
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
            error_log("Pedido de comida insertado: food_id=" . $item['id'] . ", quantity=" . $item['quantity']);
        }
    }
    
    // ============================================
    // 4. INSERTAR PURCHASE_TICKETS (tipos de boleto)
    // ============================================
    $stmtPurchaseTicket = $pdo->prepare("
        INSERT INTO purchase_tickets (purchase_id, showtime_id, ticket_type_id, seat_code, price) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    // Guardar temporalmente los datos para insertar después del purchase_id
    $purchaseTicketData = [];
    
    // ============================================
    // 5. GUARDAR ASIENTOS CON MARCADOR ♿
    // ============================================
    $seatsWithMarkers = [];
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
        $priceByType = getTicketPriceByType($showtime, $type);
        
        // Guardar datos para insertar después
        $purchaseTicketData[] = [
            'seat' => $seat,
            'ticket_type_id' => $ticketTypeId,
            'price' => $priceByType
        ];
        
        // Agregar marcador ♿ si es accesible
        if (in_array($seat, $accessibleSeats)) {
            $seatsWithMarkers[] = $seat . '♿';
        } else {
            $seatsWithMarkers[] = $seat;
        }
    }
    $seatsFormatted = implode(',', $seatsWithMarkers);
    
    // ============================================
    // 6. INSERTAR LA COMPRA EN purchases
    // ============================================
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // Generar referencia única
    $reference = 'CMP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    
    // Enriquecer paymentInfo con la referencia y datos adicionales
    $paymentDataArray = json_decode($paymentInfo, true);
    $paymentDataArray['reference'] = $reference;
    $paymentDataArray['simulated'] = true;
    $paymentDataArray['date'] = date('Y-m-d H:i:s');
    $paymentDataArray['ip'] = $_SERVER['REMOTE_ADDR'] ?? null;
    $paymentDataArray['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $paymentDataFinal = json_encode($paymentDataArray);
    
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
        $paymentDataFinal
    ]);
    
    $purchaseId = $pdo->lastInsertId();
    error_log("Compra insertada - ID: " . $purchaseId);
    
    // ============================================
    // 7. INSERTAR PURCHASE_TICKETS CON EL PURCHASE_ID
    // ============================================
    foreach ($purchaseTicketData as $data) {
        $stmtPurchaseTicket->execute([
            $purchaseId,
            $showtimeId,
            $data['ticket_type_id'],
            $data['seat'],
            $data['price']
        ]);
        error_log("Purchase ticket insertado: seat=" . $data['seat'] . ", type_id=" . $data['ticket_type_id']);
    }
    
    // ============================================
    // 8. LIMPIAR SESIONES
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
        'total_amount_' . $showtimeId
    ];
    foreach ($sessionKeys as $key) {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
    
    // ============================================
    // 9. CONFIRMAR TRANSACCIÓN
    // ============================================
    $pdo->commit();
    error_log("=== CHECKOUT COMPLETED SUCCESSFULLY ===");
    error_log("Purchase ID: " . $purchaseId);
    error_log("Session Token: " . $sessionToken);
    
    // ============================================
    // 10. REDIRIGIR A CONFIRMACIÓN
    // ============================================
    // ✅ Solo pasamos el showtime_id y purchase_id (seguro)
    header('Location: confirmation.php?showtime_id=' . $showtimeId . '&purchase_id=' . $purchaseId);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("ERROR en checkout.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    header('Location: index.php?error=' . urlencode($e->getMessage()));
    exit;
}
?>