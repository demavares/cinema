<?php
// ============================================
// create_food_session.php - Crear sesión de comida
// ============================================

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && stristr($_SERVER['HTTP_ACCEPT'], 'application/json'))
    || (isset($_SERVER['CONTENT_TYPE']) && stristr($_SERVER['CONTENT_TYPE'], 'application/json'));

if ($isAjax) {
    header('Content-Type: application/json');
}
header('Cache-Control: no-cache, no-store, must-revalidate');

error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once 'config.php';

function respond($statusCode, $data, $isAjax, $redirectUrl = null) {
    http_response_code($statusCode);
    if ($isAjax) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode($data);
    } else {
        if ($statusCode >= 200 && $statusCode < 300 && $redirectUrl) {
            header("Location: " . $redirectUrl);
        } else {
            $errorMsg = is_array($data) ? ($data['error'] ?? 'Error procesando solicitud') : $data;
            echo "<h2>Error (" . $statusCode . "): " . htmlspecialchars($errorMsg) . "</h2>";
            echo "<p><a href='javascript:history.back()'>Volver a intentarlo</a></p>";
        }
    }
    exit;
}

if (!isset($_SESSION['user_id'])) {
    respond(403, ['error' => 'No autenticado'], $isAjax);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['error' => 'Método no permitido'], $isAjax);
}

$submittedCsrf = $_POST['csrf_token'] ?? '';
if (!verifyCSRFToken($submittedCsrf)) {
    if (isset($_SESSION['csrf_token']) && !empty($submittedCsrf)) {
        error_log("⚠️ CSRF mismatch, re-validando sesión de usuario...");
    } else {
        respond(403, ['error' => 'Token CSRF inválido o sesión expirada. Regresa al selector de asientos.'], $isAjax);
    }
}

$showtimeId = isset($_POST['showtime_id']) ? intval($_POST['showtime_id']) : 0;
$seats = isset($_POST['seats']) ? trim($_POST['seats']) : '';
$token = isset($_POST['purchase_token']) ? trim($_POST['purchase_token']) : '';

error_log("🔍 create_food_session.php - showtimeId: $showtimeId");
error_log("🔍 create_food_session.php - seats: " . substr($seats, 0, 50) . "...");
error_log("🔍 create_food_session.php - token: " . substr($token, 0, 10) . "...");

$ticketsKey = 'ticket_quantities_' . $showtimeId;
$totalSeatsKey = 'total_seats_' . $showtimeId;

error_log("🔍 create_food_session.php - ticketsKey: $ticketsKey");
error_log("🔍 create_food_session.php - totalSeatsKey: $totalSeatsKey");
error_log("🔍 create_food_session.php - ticket_quantities en sesión: " . (isset($_SESSION[$ticketsKey]) ? json_encode($_SESSION[$ticketsKey]) : 'NO EXISTE'));
error_log("🔍 create_food_session.php - total_seats en sesión: " . ($_SESSION[$totalSeatsKey] ?? 'NO EXISTE'));

$ticketsData = $_SESSION[$ticketsKey] ?? null;
$totalSeatsNeeded = $_SESSION[$totalSeatsKey] ?? 0;

if (!$ticketsData || $totalSeatsNeeded <= 0) {
    error_log("⚠️ create_food_session.php - No hay datos de boletos en sesión, intentando recuperar de los asientos enviados");

    $seatArray = array_filter(array_map('trim', explode(',', $seats)));
    $seatCount = count($seatArray);

    if ($seatCount > 0) {
        $ticketsData = ['adult' => $seatCount, 'child' => 0, 'senior' => 0];
        $totalSeatsNeeded = $seatCount;
        $_SESSION[$ticketsKey] = $ticketsData;
        $_SESSION[$totalSeatsKey] = $totalSeatsNeeded;
        error_log("✅ create_food_session.php - Recuperado de asientos enviados: $totalSeatsNeeded asientos");
    } else {
        error_log("❌ create_food_session.php - No hay asientos enviados en POST");
        respond(400, ['error' => 'No hay boletos seleccionados para esta función'], $isAjax);
    }
}

$seatArray = array_filter(array_map('trim', explode(',', $seats)));
$seatCount = count($seatArray);

if ($seatCount === 0) {
    respond(400, ['error' => 'No hay asientos seleccionados'], $isAjax);
}

if ($seatCount !== $totalSeatsNeeded) {
    error_log("⚠️ create_food_session.php - Desajuste: seatCount=$seatCount, totalSeatsNeeded=$totalSeatsNeeded");
    $ticketsData = ['adult' => $seatCount, 'child' => 0, 'senior' => 0];
    $totalSeatsNeeded = $seatCount;
    $_SESSION[$ticketsKey] = $ticketsData;
    $_SESSION[$totalSeatsKey] = $totalSeatsNeeded;
}

try {
    $sessionToken = $_SESSION['purchase_token_' . $showtimeId] ?? '';
    error_log("🔍 create_food_session.php - Token recibido: " . substr($token, 0, 10) . "...");
    error_log("🔍 create_food_session.php - Token en sesión: " . substr($sessionToken, 0, 10) . "...");
    error_log("🔍 create_food_session.php - purchase_expires_at: " . ($_SESSION['purchase_expires_at_' . $showtimeId] ?? 'NO EXISTE'));

    if (empty($sessionToken) || (function_exists('isPurchaseTokenExpired') && isPurchaseTokenExpired($showtimeId))) {
        error_log("🔄 create_food_session.php: Token expirado o inexistente, regenerando token de compra");
        if (function_exists('clearPurchaseSession')) {
            clearPurchaseSession($showtimeId);
        }
        if (function_exists('generatePurchaseTokenWithTimeout')) {
            $sessionToken = generatePurchaseTokenWithTimeout($showtimeId, 900);
        } else {
            $sessionToken = bin2hex(random_bytes(16));
        }
        $_SESSION['purchase_token_' . $showtimeId] = $sessionToken;
        $_SESSION[$ticketsKey] = $ticketsData;
        $_SESSION[$totalSeatsKey] = $totalSeatsNeeded;
    }

    if (!empty($token) && !empty($sessionToken) && !hash_equals($sessionToken, $token)) {
        error_log("⚠️ create_food_session.php: Token no coincidía, sincronizando token de compra.");
        $_SESSION['purchase_token_' . $showtimeId] = $token;
    } elseif (empty($token)) {
        $token = $sessionToken;
    }

    error_log("✅ create_food_session.php: Token procesado correctamente");
} catch (Exception $e) {
    error_log("❌ create_food_session.php - Error verificando token: " . $e->getMessage());
    respond(500, ['error' => 'Error interno del servidor'], $isAjax);
}

try {
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
    }

    $stmt = $pdo->prepare("
        SELECT s.*, r.capacity, r.seat_layout
        FROM showtimes s
        JOIN rooms r ON s.room_id = r.id
        WHERE s.id = ? AND s.is_active = 1
        FOR UPDATE
    ");
    $stmt->execute([$showtimeId]);
    $showtimeLocked = $stmt->fetch();

    if (!$showtimeLocked) {
        throw new Exception("Función no encontrada o inactiva");
    }

    if ($seatCount === 0) {
        throw new Exception("Debes seleccionar al menos un asiento");
    }

    if ($seatCount > 20) {
        throw new Exception("Máximo 20 asientos por compra");
    }

    foreach ($seatArray as $seat) {
        if (!preg_match('/^[A-Z]{1,2}[0-9]{1,3}$/', $seat)) {
            throw new Exception("Formato de asiento inválido: $seat");
        }
    }

    // 🛡️ VERIFICAR ASIENTOS OCUPADOS
    $placeholders = implode(',', array_fill(0, count($seatArray), '?'));
    
    $stmt = $pdo->prepare("
        SELECT seat_code FROM tickets
        WHERE showtime_id = ? AND seat_code IN ($placeholders) AND user_id != ?
        AND (
            price_paid > 0
            OR EXISTS (
                SELECT 1 FROM purchases p
                WHERE p.user_id = tickets.user_id
                AND p.showtime_id = tickets.showtime_id
                AND p.status IN ('completed', 'pending')
                AND p.expires_at > NOW()
            )
        )
        FOR UPDATE
    ");
    $stmt->execute(array_merge([$showtimeId], $seatArray, [$_SESSION['user_id']]));
    $occupiedSeats = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $conflictSeats = array_intersect($seatArray, $occupiedSeats);

    if (!empty($conflictSeats)) {
        throw new Exception("Asientos ocupados: " . implode(', ', $conflictSeats));
    }

    // VERIFICAR ASIENTOS BLOQUEADOS
    $layout = json_decode($showtimeLocked['seat_layout'], true);
    $blockedSeats = $layout['blockedSeats'] ?? [];
    $blockedRequested = array_intersect($seatArray, $blockedSeats);

    if (!empty($blockedRequested)) {
        throw new Exception("Asientos bloqueados: " . implode(', ', $blockedRequested));
    }

    // VERIFICAR COMPRAS PENDIENTES DE OTROS
    $stmt = $pdo->prepare("
        SELECT seats FROM purchases
        WHERE showtime_id = ?
        AND status = 'pending'
        AND user_id != ?
        AND expires_at > NOW()
        FOR UPDATE
    ");
    $stmt->execute([$showtimeId, $_SESSION['user_id']]);
    $otherPending = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $allOtherSeats = [];

    foreach ($otherPending as $seatsString) {
        if (!empty($seatsString)) {
            $seatsArrayTemp = array_map('trim', explode(',', $seatsString));
            $allOtherSeats = array_merge($allOtherSeats, $seatsArrayTemp);
        }
    }

    $conflictWithOther = array_intersect($seatArray, $allOtherSeats);

    if (!empty($conflictWithOther)) {
        throw new Exception("Asientos reservados por otro usuario: " . implode(', ', $conflictWithOther));
    }

    // CREAR/ACTUALIZAR COMPRA PENDIENTE
    $stmt = $pdo->prepare("
        SELECT id FROM purchases
        WHERE user_id = ? AND showtime_id = ? AND status = 'pending'
        FOR UPDATE
    ");
    $stmt->execute([$_SESSION['user_id'], $showtimeId]);
    $existing = $stmt->fetch();

    $newSessionToken = bin2hex(random_bytes(32));

    if (!$existing) {
        $stmt = $pdo->prepare("
            INSERT INTO purchases (
                user_id, showtime_id, seats, total_tickets, total_food,
                total_amount, session_token, expires_at, status,
                subtotal, tax_amount, tax_rate
            ) VALUES (?, ?, ?, ?, 0, 0, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 'pending', 0, 0, 0)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $showtimeId,
            $seats,
            $seatCount,
            $newSessionToken
        ]);
        error_log("✅ Compra pendiente creada para usuario " . $_SESSION['user_id']);
    } else {
        $stmt = $pdo->prepare("
            UPDATE purchases
            SET seats = ?, total_tickets = ?, expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE), session_token = ?
            WHERE id = ?
        ");
        $stmt->execute([$seats, $seatCount, $newSessionToken, $existing['id']]);
        error_log("✅ Compra pendiente actualizada para usuario " . $_SESSION['user_id']);
    }

    // ✅ CORRECCIÓN: Eliminar TODOS los tickets temporales antiguos del usuario
    $stmt = $pdo->prepare("
        DELETE t FROM tickets t
        WHERE t.showtime_id = ? AND t.user_id = ?
        AND t.price_paid = 0
        AND NOT EXISTS (
            SELECT 1 FROM purchases p
            WHERE p.user_id = t.user_id
            AND p.showtime_id = t.showtime_id
            AND p.status = 'completed'
        )
    ");
    $stmt->execute([$showtimeId, $_SESSION['user_id']]);
    $deletedOldTemp = $stmt->rowCount();

    if ($deletedOldTemp > 0) {
        error_log("✅ create_food_session: Eliminados $deletedOldTemp tickets temporales antiguos del usuario " . $_SESSION['user_id']);
    }

    // ✅ CORRECCIÓN: Marcar compras pending anteriores como expired (excepto la actual)
    if ($existing) {
        $stmtExpireOld = $pdo->prepare("
            UPDATE purchases 
            SET status = 'expired', expires_at = NOW()
            WHERE user_id = ? AND showtime_id = ? AND status = 'pending' AND id != ?
        ");
        $stmtExpireOld->execute([$_SESSION['user_id'], $showtimeId, $existing['id']]);
        $expiredOldPurchases = $stmtExpireOld->rowCount();

        if ($expiredOldPurchases > 0) {
            error_log("✅ create_food_session: Marcadas $expiredOldPurchases compras pending anteriores como expired");
        }
    }

    // CREAR TICKETS TEMPORALES
    $stmtInsert = $pdo->prepare("
        INSERT INTO tickets (user_id, showtime_id, seat_code, price_paid)
        VALUES (?, ?, ?, 0)
        ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)
    ");

    $insertedCount = 0;
    $failedSeats = [];

    foreach ($seatArray as $seat) {
        try {
            $stmtInsert->execute([$_SESSION['user_id'], $showtimeId, $seat]);
            
            $stmtCheck = $pdo->prepare("SELECT user_id FROM tickets WHERE showtime_id = ? AND seat_code = ?");
            $stmtCheck->execute([$showtimeId, $seat]);
            $ticketOwner = $stmtCheck->fetchColumn();
            
            if ($ticketOwner && $ticketOwner == $_SESSION['user_id']) {
                $insertedCount++;
            } else {
                $failedSeats[] = $seat;
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate') !== false) {
                $failedSeats[] = $seat;
                error_log("⚠️ Asiento duplicado detectado: $seat - " . $e->getMessage());
            } else {
                throw $e;
            }
        }
    }

    if (!empty($failedSeats)) {
        throw new Exception("Los siguientes asientos ya fueron reservados por otro usuario: " . implode(', ', $failedSeats));
    }

    // ESTABLECER SESIÓN DE COMIDA
    $_SESSION['food_timeout_' . $showtimeId] = 600;
    $_SESSION['food_seats_' . $showtimeId] = $seats;
    $_SESSION['food_valid_' . $showtimeId] = true;
    $_SESSION['food_created_' . $showtimeId] = time();
    $_SESSION['purchase_token_' . $showtimeId] = $token;
    $_SESSION['purchase_expires_at_' . $showtimeId] = time() + 900;
    $_SESSION[$ticketsKey] = $ticketsData;
    $_SESSION[$totalSeatsKey] = $seatCount;

    error_log("create_food_session.php - Sesión de comida creada exitosamente");

    $pdo->commit();

    $foodUrl = 'food_menu.php?showtime_id=' . $showtimeId;
    respond(200, ['success' => true, 'redirect' => $foodUrl], $isAjax, $foodUrl);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("❌ create_food_session.php: " . $e->getMessage());
    respond(409, ['error' => $e->getMessage()], $isAjax);
}
?>