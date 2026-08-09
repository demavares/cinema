<?php
require_once 'config.php';

// ============================================
// VERIFICAR AUTENTICACIÓN
// ============================================
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    die("Error de seguridad: Token CSRF inválido.");
}

$userId = $_SESSION['user_id'];
$showtimeId = intval($_POST['showtime_id'] ?? 0);
$seats = trim($_POST['seats'] ?? '');
$paymentMethod = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';
$foodOrder = isset($_POST['food_order']) ? $_POST['food_order'] : '[]';
$token = $_POST['purchase_token'] ?? '';

if (empty($seats) || $showtimeId <= 0) {
    header('Location: index.php?error=Datos+incompletos');
    exit;
}

if (empty($paymentMethod)) {
    $paymentMethod = 'movil';
}

$seatsArray = array_map('trim', explode(',', $seats));

// ============================================
// VERIFICAR TIMEOUT DEL TOKEN
// ============================================
if (isPurchaseTokenExpired($showtimeId)) {
    clearPurchaseSession($showtimeId);
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=El+tiempo+para+la+reserva+ha+expirado');
    exit;
}

// ============================================
// VALIDAR TOKEN DE COMPRA
// ============================================
if (empty($token) || !verifyPurchaseTokenWithTimeout($token, $showtimeId)) {
    error_log("❌ Token inválido en checkout.php: " . ($token ?? 'NULL'));
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Token+inválido+o+expirado');
    exit;
}

// ============================================
// OBTENER Y RECALCULAR TODO EN EL SERVIDOR
// ============================================

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

$ticketQuantities = isset($_SESSION['ticket_quantities_' . $showtimeId]) 
    ? $_SESSION['ticket_quantities_' . $showtimeId] 
    : null;

if (!$ticketQuantities) {
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Datos+de+boletos+no+encontrados');
    exit;
}

// Obtener precios de boletos
$validation = validateAndRecalculatePrices($pdo, $showtimeId, $ticketQuantities);

if (isset($validation['error'])) {
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=' . urlencode($validation['error']));
    exit;
}

$ticketSubtotal = $validation['subtotal'];
$taxRate = $validation['tax_rate'];
$ticketTaxAmount = $validation['tax_amount'];
$ticketTotalAmount = $validation['total_amount'];
$totalSeats = $validation['total_seats'];
$pricesByType = $validation['prices'];

if (count($seatsArray) != $totalSeats) {
    header('Location: seats.php?showtime_id=' . $showtimeId . '&error=La+cantidad+de+asientos+no+coincide');
    exit;
}

// ============================================
// VERIFICAR QUE LOS TICKETS TEMPORALES EXISTAN (CON RECUPERACIÓN)
// ============================================
$placeholders = implode(',', array_fill(0, count($seatsArray), '?'));
$stmtCheckTickets = $pdo->prepare("
    SELECT seat_code FROM tickets 
    WHERE showtime_id = ? AND user_id = ? AND seat_code IN ($placeholders)
");
$stmtCheckTickets->execute(array_merge([$showtimeId, $_SESSION['user_id']], $seatsArray));
$userTickets = $stmtCheckTickets->fetchAll(PDO::FETCH_COLUMN);

// Si faltan tickets, intentar recuperarlos
if (count($userTickets) !== count($seatsArray)) {
    error_log("⚠️ Tickets temporales faltantes en checkout para usuario " . $_SESSION['user_id'] . " en showtime $showtimeId");
    error_log("Esperados: " . print_r($seatsArray, true));
    error_log("Encontrados: " . print_r($userTickets, true));
    
    // Intentar crear los tickets faltantes
    $missingSeats = array_diff($seatsArray, $userTickets);
    
    if (!empty($missingSeats)) {
        $stmtInsert = $pdo->prepare("
            INSERT IGNORE INTO tickets (user_id, showtime_id, seat_code, price_paid)
            VALUES (?, ?, ?, 0)
        ");
        
        $recovered = 0;
        foreach ($missingSeats as $seat) {
            $stmtInsert->execute([$_SESSION['user_id'], $showtimeId, $seat]);
            $recovered++;
            error_log("🔄 Ticket recuperado: asiento $seat");
        }
        
        error_log("✅ $recovered tickets recuperados en checkout");
        
        // Verificar nuevamente
        $stmtCheckTickets->execute(array_merge([$showtimeId, $_SESSION['user_id']], $seatsArray));
        $userTickets = $stmtCheckTickets->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($userTickets) !== count($seatsArray)) {
            error_log("❌ Falló la recuperación de tickets para usuario " . $_SESSION['user_id']);
            header('Location: seats.php?showtime_id=' . $showtimeId . '&error=Sesión+expirada,+selecciona+los+asientos+nuevamente');
            exit;
        }
    }
}

// ============================================
// PROCESAR COMIDA DESDE SESIÓN
// ============================================
$sessionFoodKey = 'food_order_' . $showtimeId;
$foodOrderData = isset($_SESSION[$sessionFoodKey]) ? $_SESSION[$sessionFoodKey] : '[]';
$foodOrder = isset($_POST['food_order']) ? $_POST['food_order'] : $foodOrderData;

error_log("📦 checkout.php - foodOrder recibido: " . $foodOrder);

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
// CALCULAR TOTALES CORRECTAMENTE
// ============================================
$subtotalGeneral = $ticketSubtotal + $totalFood;
$taxAmountGeneral = $subtotalGeneral * ($taxRate / 100);
$totalGeneral = $subtotalGeneral + $taxAmountGeneral;

error_log("📊 checkout.php - TicketSubtotal: $ticketSubtotal, Food: $totalFood, Subtotal: $subtotalGeneral, Total: $totalGeneral");

$purchaseId = null;

try {
    $pdo->beginTransaction();
    
    // ============================================
    // 1. BLOQUEAR LA FILA DEL SHOWTIME
    // ============================================
    $stmt = $pdo->prepare("
        SELECT s.*, r.capacity, r.seat_layout
        FROM showtimes s
        JOIN rooms r ON s.room_id = r.id
        WHERE s.id = ? FOR UPDATE
    ");
    $stmt->execute([$showtimeId]);
    $showtimeLocked = $stmt->fetch();
    
    if (!$showtimeLocked) {
        throw new Exception("Función no encontrada");
    }
    
    // ============================================
    // 2. VERIFICAR ASIENTOS OCUPADOS
    // ============================================
    $placeholders = implode(',', array_fill(0, count($seatsArray), '?'));
    $stmtCheck = $pdo->prepare("SELECT seat_code FROM tickets WHERE showtime_id = ? AND seat_code IN ($placeholders) FOR UPDATE");
    $stmtCheck->execute(array_merge([$showtimeId], $seatsArray));
    $existingSeats = $stmtCheck->fetchAll(PDO::FETCH_COLUMN);
    
    $conflictSeats = array_diff($existingSeats, $userTickets);
    
    if (!empty($conflictSeats)) {
        throw new Exception("Asientos ocupados: " . implode(', ', $conflictSeats));
    }
    
    // ============================================
    // 3. VERIFICAR COMPRAS PENDIENTES DE OTROS USUARIOS
    // ============================================
    $stmtPending = $pdo->prepare("
        SELECT seats FROM purchases 
        WHERE showtime_id = ? AND status = 'pending' AND user_id != ?
        FOR UPDATE
    ");
    $stmtPending->execute([$showtimeId, $_SESSION['user_id']]);
    $pendingPurchases = $stmtPending->fetchAll();

    foreach ($pendingPurchases as $pending) {
        $pendingSeats = explode(',', $pending['seats']);
        $conflictSeats = array_intersect($seatsArray, $pendingSeats);
        if (!empty($conflictSeats)) {
            throw new Exception("Los siguientes asientos están siendo reservados por otro usuario: " . implode(', ', $conflictSeats));
        }
    }
    
    // ============================================
    // 4. VERIFICAR ASIENTOS BLOQUEADOS POR DISEÑO
    // ============================================
    $layout = json_decode($showtimeLocked['seat_layout'], true);
    $blockedSeats = $layout['blockedSeats'] ?? [];
    $blockedRequested = array_intersect($seatsArray, $blockedSeats);
    
    if (!empty($blockedRequested)) {
        throw new Exception("Los siguientes asientos están bloqueados: " . implode(', ', $blockedRequested));
    }
    
    $transactionId = generateTransactionId();
    
    // ============================================
    // 5. INSERTAR TICKETS CON PRECIO CORRECTO
    // ============================================
    $totalAdults = intval($ticketQuantities['adult'] ?? 0);
    $totalChildren = intval($ticketQuantities['child'] ?? 0);
    $totalSeniors = intval($ticketQuantities['senior'] ?? 0);
    
    // Eliminar los tickets temporales del usuario para este showtime
    $stmtDeleteTemp = $pdo->prepare("
        DELETE FROM tickets 
        WHERE showtime_id = ? AND user_id = ? AND seat_code IN ($placeholders)
    ");
    $stmtDeleteTemp->execute(array_merge([$showtimeId, $_SESSION['user_id']], $seatsArray));
    error_log("🗑️ Tickets temporales eliminados para usuario " . $_SESSION['user_id']);
    
    // Insertar los tickets definitivos
    $stmtInsert = $pdo->prepare("INSERT INTO tickets (user_id, showtime_id, seat_code, price_paid) VALUES (?, ?, ?, ?)");
    $ticketIds = [];
    
    foreach ($seatsArray as $index => $seat) {
        if ($index < $totalAdults) {
            $price = $pricesByType['adult'];
        } elseif ($index < $totalAdults + $totalChildren) {
            $price = $pricesByType['child'];
        } else {
            $price = $pricesByType['senior'];
        }
        
        $stmtInsert->execute([$userId, $showtimeId, $seat, $price]);
        $ticketIds[] = $pdo->lastInsertId();
    }
    error_log("✅ " . count($ticketIds) . " tickets definitivos creados");
    
    // ============================================
    // 6. ELIMINAR COMPRAS PENDIENTES DEL USUARIO ACTUAL
    // ============================================
    $stmtDelete = $pdo->prepare("
        DELETE FROM purchases 
        WHERE user_id = ? AND showtime_id = ? AND status = 'pending'
    ");
    $stmtDelete->execute([$userId, $showtimeId]);
    
    // ============================================
    // 7. REGISTRAR LA COMPRA (VERSIÓN CORREGIDA - SIN data_hash)
    // ============================================
    $seatsWithMarkers = [];
    $accessibleSeats = $layout['wheelchairSeats'] ?? ($layout['accessibleSeats'] ?? []);
    foreach ($seatsArray as $seat) {
        if (in_array($seat, $accessibleSeats)) {
            $seatsWithMarkers[] = $seat . '♿';
        } else {
            $seatsWithMarkers[] = $seat;
        }
    }
    $seatsFormatted = implode(',', $seatsWithMarkers);
    
    $sessionToken = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $reference = 'CMP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    
    $paymentData = json_encode([
        'transaction_id' => $transactionId,
        'method' => $paymentMethod,
        'simulated' => true,
        'reference' => $reference,
        'date' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
    
    // ✅ CORREGIDO: INSERT sin data_hash y data_integrity_check
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
        $totalSeats,
        $totalFood,
        $subtotalGeneral,
        $taxAmountGeneral,
        $taxRate,
        $totalGeneral,
        $sessionToken,
        $expiresAt,
        $paymentMethod,
        $paymentData
    ]);
    
    $purchaseId = $pdo->lastInsertId();
    error_log("✅ Compra #$purchaseId registrada en purchases");
    
    // ============================================
    // 8. INSERTAR purchase_tickets
    // ============================================
    $stmtPurchaseTicket = $pdo->prepare("
        INSERT INTO purchase_tickets (purchase_id, showtime_id, ticket_type_id, seat_code, price) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($seatsArray as $index => $seat) {
        if ($index < $totalAdults) {
            $ticketTypeId = 1;
            $price = $pricesByType['adult'];
        } elseif ($index < $totalAdults + $totalChildren) {
            $ticketTypeId = 2;
            $price = $pricesByType['child'];
        } else {
            $ticketTypeId = 3;
            $price = $pricesByType['senior'];
        }
        
        $stmtPurchaseTicket->execute([
            $purchaseId,
            $showtimeId,
            $ticketTypeId,
            $seat,
            $price
        ]);
    }
    error_log("✅ " . count($seatsArray) . " purchase_tickets creados");
    
    // ============================================
    // 9. INSERTAR food_orders
    // ============================================
    if (!empty($foodItems)) {
        $stmtFood = $pdo->prepare("
            INSERT INTO food_orders (
                user_id, ticket_id, showtime_id, food_item_id, 
                quantity, unit_price, total_price, status, purchase_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?)
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
                $item['total'],
                $purchaseId
            ]);
        }
        error_log("✅ " . count($foodItems) . " food_orders creados");
    }
    
    $_SESSION['last_order_id'] = $purchaseId;
    $_SESSION['last_showtime_id'] = $showtimeId;
    
    markPurchaseTokenAsUsed($showtimeId);
    clearPurchaseSession($showtimeId);
    
    $pdo->commit();
    
    error_log("✅ Compra #$purchaseId completada exitosamente");
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("❌ Error en checkout: " . $e->getMessage());
    header('Location: seats.php?showtime_id=' . $showtimeId . '&error=' . urlencode($e->getMessage()));
    exit;
}

header('Location: confirmation.php?purchase_id=' . $purchaseId);
exit;
?>