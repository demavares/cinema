<?php
require_once 'config.php';

// ============================================
// ¿EL HORARIO ESTÁ A ≤15 MINUTOS DE INICIAR?
// (Si es así, redirigir a index en lugar de seats.php para evitar rebotes)
// ============================================
function checkout_showtime_near_start($showtime)
{
    $showtimeDateTime = strtotime($showtime['show_date'] . ' ' . $showtime['show_time']);
    $safetyMargin = 15 * 60;
    return ($showtimeDateTime + $safetyMargin) < time();
}

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
$paymentMethod = $_POST['payment_method'] ?? 'movil';
$token = $_POST['purchase_token'] ?? '';

if (empty($seats) || $showtimeId <= 0) {
    header('Location: index.php?error=Datos+incompletos');
    exit;
}

$seatsArray = array_map('trim', explode(',', $seats));

if (!verifyPurchaseToken($token, $showtimeId)) {
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Token+inválido+o+expirado');
    exit;
}

$stmt = $pdo->prepare("SELECT s.*, m.title, m.duration FROM showtimes s JOIN movies m ON s.movie_id = m.id WHERE s.id = ? AND s.is_active = 1");
$stmt->execute([$showtimeId]);
$showtime = $stmt->fetch();

if (!$showtime) {
    header('Location: index.php?error=Horario+no+disponible');
    exit;
}

$ticketQuantities = $_SESSION['ticket_quantities_' . $showtimeId] ?? null;
if (!$ticketQuantities) {
    header('Location: price_selection.php?showtime_id=' . $showtimeId . '&error=Datos+de+boletos+no+encontrados');
    exit;
}

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
    if (checkout_showtime_near_start($showtime)) {
        header('Location: index.php?error=La+cantidad+de+asientos+no+coincide+y+el+horario+ya+no+está+disponible');
    } else {
        header('Location: seats.php?showtime_id=' . $showtimeId . '&error=La+cantidad+de+asientos+no+coincide');
    }
    exit;
}

$foodOrder = isset($_POST['food_order']) ? $_POST['food_order'] : '[]';
$foodItems = [];
$totalFood = 0;
$foodData = json_decode($foodOrder, true);

if (!empty($foodData) && is_array($foodData)) {
    $foodIds = array_column($foodData, 'id');
    if (!empty($foodIds)) {
        $placeholders = implode(',', array_fill(0, count($foodIds), '?'));
        $stmt = $pdo->prepare("SELECT * FROM food_items WHERE id IN ($placeholders) AND is_active = 1");
        $stmt->execute($foodIds);
        $availableFood = $stmt->fetchAll();

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

$subtotalGeneral = $ticketSubtotal + $totalFood;
$taxAmountGeneral = $subtotalGeneral * ($taxRate / 100);
$totalGeneral = $subtotalGeneral + $taxAmountGeneral;

$purchaseId = null;

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT s.*, r.capacity, r.seat_layout FROM showtimes s JOIN rooms r ON s.room_id = r.id WHERE s.id = ? FOR UPDATE");
    $stmt->execute([$showtimeId]);
    $showtimeLocked = $stmt->fetch();

    if (!$showtimeLocked) throw new Exception("Función no encontrada");

    // 🛡️ VERIFICAR ASIENTOS OCUPADOS
    $placeholders = implode(',', array_fill(0, count($seatsArray), '?'));
    $stmtCheck = $pdo->prepare("
        SELECT seat_code FROM tickets
        WHERE showtime_id = ? AND seat_code IN ($placeholders) AND user_id != ?
        AND status IN ('hold', 'confirmed')
        FOR UPDATE
    ");
    $stmtCheck->execute(array_merge([$showtimeId], $seatsArray, [$userId]));
    $existingSeats = $stmtCheck->fetchAll(PDO::FETCH_COLUMN);

    $stmtPending = $pdo->prepare("SELECT seats FROM purchases WHERE showtime_id = ? AND status = 'pending' AND user_id != ? AND expires_at > NOW() FOR UPDATE");
    $stmtPending->execute([$showtimeId, $userId]);
    $pendingPurchases = $stmtPending->fetchAll();
    $pendingSeats = [];

    foreach ($pendingPurchases as $p) {
        if (!empty($p['seats'])) {
            $pendingSeats = array_merge($pendingSeats, explode(',', $p['seats']));
        }
    }

    $conflictSeats = array_intersect($seatsArray, array_merge($existingSeats, $pendingSeats));

    if (!empty($conflictSeats)) {
        throw new Exception("Asientos ocupados: " . implode(', ', $conflictSeats));
    }

    $layout = json_decode($showtimeLocked['seat_layout'], true);
    $blockedSeats = $layout['blockedSeats'] ?? [];
    $blockedRequested = array_intersect($seatsArray, $blockedSeats);

    if (!empty($blockedRequested)) {
        throw new Exception("Asientos bloqueados: " . implode(', ', $blockedRequested));
    }

    $transactionId = generateTransactionId();

    // ✅ CORREGIDO: Crear compra como 'pending' PRIMERO
    $sessionToken = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $reference = 'CMP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

    $paymentData = json_encode([
        'transaction_id' => $transactionId,
        'method' => $paymentMethod,
        'reference' => $reference,
        'date' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null
    ]);

    $stmtPurchase = $pdo->prepare("
        INSERT INTO purchases (
            user_id, showtime_id, seats, total_tickets, total_food,
            subtotal, tax_amount, tax_rate, total_amount,
            session_token, expires_at, status, payment_method, payment_data
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)
    ");

    $stmtPurchase->execute([
        $userId, $showtimeId, implode(',', $seatsArray), $totalSeats, $totalFood,
        $subtotalGeneral, $taxAmountGeneral, $taxRate, $totalGeneral,
        $sessionToken, $expiresAt, $paymentMethod, $paymentData
    ]);

    $purchaseId = $pdo->lastInsertId();

    // ✅ CORREGIDO: Confirmar tickets hold → confirmed
    $stmtConfirm = $pdo->prepare("
        UPDATE tickets
        SET status = 'confirmed',
            price_paid = ?,
            purchase_id = ?,
            ticket_type_id = ?,
            confirmed_at = NOW()
        WHERE showtime_id = ? AND seat_code = ? AND user_id = ? AND status = 'hold'
    ");

    $stmtInsertFallback = $pdo->prepare("
        INSERT INTO tickets (user_id, showtime_id, seat_code, price_paid, status, purchase_id, ticket_type_id, confirmed_at)
        VALUES (?, ?, ?, ?, 'confirmed', ?, ?, NOW())
    ");

    $confirmedCount = 0;
    $insertErrors = [];

    foreach ($seatsArray as $index => $seat) {
        $totalAdults = intval($ticketQuantities['adult'] ?? 0);
        $totalChildren = intval($ticketQuantities['child'] ?? 0);

        if ($index < $totalAdults) {
            $ticketTypeId = 1;
            $price = $pricesByType['adult'];
        } elseif ($index < ($totalAdults + $totalChildren)) {
            $ticketTypeId = 2;
            $price = $pricesByType['child'];
        } else {
            $ticketTypeId = 3;
            $price = $pricesByType['senior'];
        }

        $stmtConfirm->execute([$price, $purchaseId, $ticketTypeId, $showtimeId, $seat, $userId]);

        if ($stmtConfirm->rowCount() > 0) {
            $confirmedCount++;
        } else {
            try {
                $stmtInsertFallback->execute([$userId, $showtimeId, $seat, $price, $purchaseId, $ticketTypeId]);
                $confirmedCount++;
            } catch (PDOException $e) {
                if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate') !== false) {
                    $insertErrors[] = $seat;
                    error_log("⚠️ Error duplicado en checkout: asiento $seat - " . $e->getMessage());
                } else {
                    throw $e;
                }
            }
        }
    }

    if (!empty($insertErrors)) {
        throw new Exception("Error al reservar asientos (ya ocupados): " . implode(', ', $insertErrors));
    }

    if ($confirmedCount !== count($seatsArray)) {
        throw new Exception("No se pudieron confirmar todos los asientos. Por favor, selecciona otros.");
    }

    // ✅ CORREGIDO: Insertar food_orders
    if (!empty($foodItems)) {
        $stmtFood = $pdo->prepare("INSERT INTO food_orders (user_id, showtime_id, food_item_id, quantity, unit_price, total_price, status, purchase_id) VALUES (?, ?, ?, ?, ?, ?, 'completed', ?)");

        foreach ($foodItems as $item) {
            $stmtFood->execute([$userId, $showtimeId, $item['id'], $item['quantity'], $item['price'], $item['total'], $purchaseId]);
        }
    }

    // ✅ CORREGIDO: AHORA marcar la compra como 'completed'
    $stmtUpdatePurchase = $pdo->prepare("
        UPDATE purchases 
        SET status = 'completed', expires_at = NOW()
        WHERE id = ?
    ");
    $stmtUpdatePurchase->execute([$purchaseId]);

    $_SESSION['last_order_id'] = $purchaseId;

    // Limpiar holds sobrantes
    $stmtCleanTemp = $pdo->prepare("
        DELETE FROM tickets
        WHERE showtime_id = ? AND user_id = ? AND status = 'hold'
    ");
    $stmtCleanTemp->execute([$showtimeId, $userId]);

    // Marcar compras pending anteriores como expired
    $stmtExpireOld = $pdo->prepare("
        UPDATE purchases
        SET status = 'expired', expires_at = NOW()
        WHERE user_id = ? AND showtime_id = ? AND status = 'pending' AND id != ?
    ");
    $stmtExpireOld->execute([$userId, $showtimeId, $purchaseId]);

    clearPurchaseSession($showtimeId);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("❌ checkout: " . $e->getMessage());
    if (checkout_showtime_near_start($showtime)) {
        header('Location: index.php?error=' . urlencode('No se pudo completar la compra y el horario ya no está disponible.'));
    } else {
        header('Location: seats.php?showtime_id=' . $showtimeId . '&error=' . urlencode($e->getMessage()));
    }
    exit;
}

header('Location: confirmation.php?purchase_id=' . $purchaseId);
exit;
?>