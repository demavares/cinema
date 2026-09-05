<?php
require_once '../../../config.php';
require_once '../../includes/security.php';

// ============================================
// VERIFICAR AUTENTICACIÓN Y ROL
// ============================================
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../../../login.php');
    exit;
}

// Obtener acción
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Determinar la URL de retorno - SIEMPRE a admin/index.php
$return_url = $_POST['return'] ?? $_GET['return'] ?? '../../index.php?tab=showtimes';

// Verificar CSRF
$csrf_token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
if (!verifyCSRFToken($csrf_token)) {
    header('Location: ' . $return_url . '&error=Token+CSRF+inválido');
    exit;
}

// ============================================
// FORMATOS DISPONIBLES (para validación)
// ============================================
$formats_stmt = $pdo->query("SELECT DISTINCT format FROM showtimes WHERE format IS NOT NULL AND format != '' ORDER BY format ASC");
$formatos_bd = $formats_stmt->fetchAll(PDO::FETCH_COLUMN);
$formatos = array_unique(array_merge(['2D', '3D', 'IMAX', 'IMAX 3D', '4DX', 'ScreenX', 'D-BOX'], $formatos_bd));
sort($formatos);

// ============================================
// AGREGAR FUNCIÓN
// ============================================
if ($action === 'add_showtime') {
    $movie_id = filter_var($_POST['movie_id'] ?? 0, FILTER_VALIDATE_INT);
    $room_id = filter_var($_POST['room_id'] ?? 0, FILTER_VALIDATE_INT);
    $show_date = $_POST['show_date'] ?? '';
    $show_time = $_POST['show_time'] ?? '';
    $format = sanitizeInput($_POST['format'] ?? '2D');
    $price_adult = filter_var($_POST['price_adult'] ?? 0, FILTER_VALIDATE_FLOAT);

    // Manejar precios de niño y tercera edad
    $enable_child_price = isset($_POST['enable_child_price']) ? 1 : 0;
    $enable_senior_price = isset($_POST['enable_senior_price']) ? 1 : 0;

    $posted_child = $_POST['price_child'] ?? '';
    $posted_senior = $_POST['price_senior'] ?? '';

    $price_child = ($enable_child_price && is_numeric($posted_child)) ? floatval($posted_child) : 0;
    $price_senior = ($enable_senior_price && is_numeric($posted_senior)) ? floatval($posted_senior) : 0;

    $language = sanitizeInput($_POST['language'] ?? 'español');
    $half_price_monday = isset($_POST['half_price_monday']) ? 1 : 0;
    $preventa = isset($_POST['preventa']) ? 1 : 0;

    $promotions = [];
    if ($half_price_monday) $promotions[] = 'lunes_mitad';
    if ($preventa) $promotions[] = 'preventa';
    $promotions_str = !empty($promotions) ? implode(',', $promotions) : '';

    if ($movie_id <= 0 || $room_id <= 0) {
        header('Location: ' . $return_url . '&error=Selecciona+una+pel%C3%ADcula+y+una+sala.');
        exit;
    } elseif (empty($show_date) || empty($show_time) || $price_adult <= 0 || empty($format)) {
        header('Location: ' . $return_url . '&error=Todos+los+campos+son+obligatorios.');
        exit;
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $show_date)) {
        header('Location: ' . $return_url . '&error=Formato+de+fecha+inv%C3%A1lido.');
        exit;
    } elseif (!preg_match('/^\d{2}:\d{2}$/', $show_time)) {
        header('Location: ' . $return_url . '&error=Formato+de+hora+inv%C3%A1lido.');
        exit;
    } elseif (!in_array($format, $formatos)) {
        header('Location: ' . $return_url . '&error=Formato+de+proyecci%C3%B3n+no+v%C3%A1lido.');
        exit;
    } else {
        $stmt = $pdo->prepare("SELECT duration FROM movies WHERE id = ?");
        $stmt->execute([$movie_id]);
        $movie = $stmt->fetch();

        if (!$movie) {
            header('Location: ' . $return_url . '&error=Pel%C3%ADcula+no+encontrada+o+inactiva.');
            exit;
        }

        $conflict = checkShowtimeConflict($pdo, $room_id, $show_date, $show_time, $movie['duration']);

        if ($conflict['conflict']) {
            header('Location: ' . $return_url . '&error=' . urlencode('❌ ' . $conflict['message']));
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO showtimes (
                    movie_id, room_id, show_date, show_time,
                    price, price_adult, price_child, price_senior,
                    enable_child_price, enable_senior_price,
                    half_price_monday, promotions, language, format
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $movie_id, $room_id, $show_date, $show_time,
                $price_adult, $price_adult, $price_child, $price_senior,
                $enable_child_price, $enable_senior_price,
                $half_price_monday, $promotions_str, $language,
                $format
            ]);
            header('Location: ' . $return_url . '&msg=' . urlencode('Función agregada exitosamente.'));
            exit;
        } catch (PDOException $e) {
            error_log("Error al agregar función: " . $e->getMessage());
            header('Location: ' . $return_url . '&error=' . urlencode('Error al agregar la función. Por favor, intente nuevamente.'));
            exit;
        }
    }
}

// ============================================
// EDITAR FUNCIÓN
// ============================================
if ($action === 'edit_showtime') {
    $old_id = filter_var($_POST['showtime_id'] ?? 0, FILTER_VALIDATE_INT);

    // Obtener precios actuales ANTES de procesar para no destruirlos
    $old_prices = ['price_child' => 0, 'price_senior' => 0, 'enable_child_price' => 0, 'enable_senior_price' => 0];
    if ($old_id > 0) {
        $stmtOldPrices = $pdo->prepare("SELECT price_child, price_senior, enable_child_price, enable_senior_price FROM showtimes WHERE id = ?");
        $stmtOldPrices->execute([$old_id]);
        $tmpPrices = $stmtOldPrices->fetch();
        if ($tmpPrices) {
            $old_prices = $tmpPrices;
        }
    }

    $movie_id = filter_var($_POST['movie_id'] ?? 0, FILTER_VALIDATE_INT);
    $room_id = filter_var($_POST['room_id'] ?? 0, FILTER_VALIDATE_INT);
    $show_date = $_POST['show_date'] ?? '';
    $show_time = $_POST['show_time'] ?? '';
    $format = sanitizeInput($_POST['format'] ?? '2D');
    $price_adult = filter_var($_POST['price_adult'] ?? 0, FILTER_VALIDATE_FLOAT);

    // Capturar switches
    $enable_child_price = isset($_POST['enable_child_price']) ? 1 : 0;
    $enable_senior_price = isset($_POST['enable_senior_price']) ? 1 : 0;

    // No destruir precios si el input no fue enviado o viene vacío
    $posted_child = $_POST['price_child'] ?? '';
    $posted_senior = $_POST['price_senior'] ?? '';

    if ($enable_child_price) {
        // Si está habilitado y el admin escribió un precio, usar ese precio.
        // Si el campo vino vacío, conservar el precio anterior.
        $price_child = is_numeric($posted_child) ? floatval($posted_child) : floatval($old_prices['price_child']);
    } else {
        // Si el switch está apagado, conservamos el precio en BD,
        // pero enable_child_price = 0 indica que no debe usarse.
        $price_child = floatval($old_prices['price_child']);
    }

    if ($enable_senior_price) {
        $price_senior = is_numeric($posted_senior) ? floatval($posted_senior) : floatval($old_prices['price_senior']);
    } else {
        $price_senior = floatval($old_prices['price_senior']);
    }

    $language = sanitizeInput($_POST['language'] ?? 'español');
    $half_price_monday = isset($_POST['half_price_monday']) ? 1 : 0;
    $preventa = isset($_POST['preventa']) ? 1 : 0;

    $promotions = [];
    if ($half_price_monday) $promotions[] = 'lunes_mitad';
    if ($preventa) $promotions[] = 'preventa';
    $promotions_str = !empty($promotions) ? implode(',', $promotions) : '';

    if ($old_id <= 0 || $movie_id <= 0 || $room_id <= 0) {
        header('Location: ' . $return_url . '&error=Datos+inv%C3%A1lidos.');
        exit;
    } elseif (empty($show_date) || empty($show_time) || $price_adult <= 0 || empty($format)) {
        header('Location: ' . $return_url . '&error=Todos+los+campos+son+obligatorios.');
        exit;
    } elseif (!in_array($format, $formatos)) {
        header('Location: ' . $return_url . '&error=Formato+de+proyecci%C3%B3n+no+v%C3%A1lido.');
        exit;
    } else {
        $stmt = $pdo->prepare("SELECT * FROM showtimes WHERE id = ?");
        $stmt->execute([$old_id]);
        $old_showtime = $stmt->fetch();

        if (!$old_showtime) {
            header('Location: ' . $return_url . '&error=Función+no+encontrada.');
            exit;
        }

        $has_schedule_change = (
            $old_showtime['room_id'] != $room_id ||
            $old_showtime['show_date'] != $show_date ||
            $old_showtime['show_time'] != $show_time
        );

        $has_other_changes = (
            $old_showtime['movie_id'] != $movie_id ||
            $old_showtime['price_adult'] != $price_adult ||
            $old_showtime['price_child'] != $price_child ||
            $old_showtime['price_senior'] != $price_senior ||
            $old_showtime['enable_child_price'] != $enable_child_price ||
            $old_showtime['enable_senior_price'] != $enable_senior_price ||
            $old_showtime['half_price_monday'] != $half_price_monday ||
            $old_showtime['promotions'] != $promotions_str ||
            ($old_showtime['language'] ?? 'español') != $language ||
            ($old_showtime['format'] ?? '2D') != $format
        );

        if (!$has_schedule_change && !$has_other_changes) {
            header('Location: ' . $return_url . '&msg=' . urlencode('No se detectaron cambios en la función.'));
            exit;
        } elseif (!$has_schedule_change && $has_other_changes) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE showtimes SET
                        movie_id = ?, price_adult = ?, price_child = ?, price_senior = ?,
                        enable_child_price = ?, enable_senior_price = ?,
                        half_price_monday = ?, promotions = ?, language = ?, format = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $movie_id, $price_adult, $price_child, $price_senior,
                    $enable_child_price, $enable_senior_price,
                    $half_price_monday, $promotions_str, $language,
                    $format, $old_id
                ]);
                header('Location: ' . $return_url . '&msg=' . urlencode('Función actualizada exitosamente.'));
                exit;
            } catch (PDOException $e) {
                error_log("Error al actualizar función: " . $e->getMessage());
                header('Location: ' . $return_url . '&error=' . urlencode('Error al actualizar la función. Por favor, intente nuevamente.'));
                exit;
            }
        } else {
            $stmt = $pdo->prepare("SELECT duration FROM movies WHERE id = ?");
            $stmt->execute([$movie_id]);
            $movie_duration = $stmt->fetch();

            if (!$movie_duration) {
                header('Location: ' . $return_url . '&error=Pel%C3%ADcula+no+encontrada+o+inactiva.');
                exit;
            }

            $conflict = checkShowtimeConflict($pdo, $room_id, $show_date, $show_time, $movie_duration['duration'], $old_id);

            if ($conflict['conflict']) {
                header('Location: ' . $return_url . '&error=' . urlencode('❌ ' . $conflict['message']));
                exit;
            }

            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("UPDATE showtimes SET is_active = 0 WHERE id = ?");
                $stmt->execute([$old_id]);

                $stmt = $pdo->prepare("
                    INSERT INTO showtimes (
                        movie_id, room_id, show_date, show_time,
                        price, price_adult, price_child, price_senior,
                        enable_child_price, enable_senior_price,
                        half_price_monday, promotions, language, is_active, format
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
                ");
                $stmt->execute([
                    $movie_id, $room_id, $show_date, $show_time,
                    $price_adult, $price_adult, $price_child, $price_senior,
                    $enable_child_price, $enable_senior_price,
                    $half_price_monday, $promotions_str, $language,
                    $format
                ]);
                $new_id = $pdo->lastInsertId();

                $stmt = $pdo->prepare("UPDATE tickets SET showtime_id = ? WHERE showtime_id = ?");
                $stmt->execute([$new_id, $old_id]);

                $stmt = $pdo->prepare("UPDATE ticket_logs SET showtime_id = ? WHERE showtime_id = ?");
                $stmt->execute([$new_id, $old_id]);

                try {
                    $stmt = $pdo->prepare("UPDATE food_orders SET showtime_id = ? WHERE showtime_id = ?");
                    $stmt->execute([$new_id, $old_id]);
                } catch (PDOException $e) {
                    error_log("Nota: food_orders no migrados: " . $e->getMessage());
                }

                try {
                    $stmt = $pdo->prepare("UPDATE purchases SET showtime_id = ? WHERE showtime_id = ? AND status = 'completed'");
                    $stmt->execute([$new_id, $old_id]);
                } catch (PDOException $e) {
                    error_log("Nota: purchases no migradas: " . $e->getMessage());
                }

                $pdo->commit();
                $success_msg = "Función actualizada exitosamente. Se creó una nueva función con los cambios.";
                header('Location: ' . $return_url . '&msg=' . urlencode($success_msg));
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Error al actualizar función: " . $e->getMessage());
                header('Location: ' . $return_url . '&error=' . urlencode('Error al actualizar la función. Por favor, intente nuevamente.'));
                exit;
            }
        }
    }
}

// ============================================
// ELIMINAR FUNCIÓN
// ============================================
if ($action === 'delete_showtime') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($id <= 0) {
        header('Location: ' . $return_url . '&error=ID+inv%C3%A1lido.');
        exit;
    }

    try {
        // Solo contar tickets confirmados (pagados).
        // Los tickets 'hold' (temporales) no deben impedir la eliminación.
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE showtime_id = ? AND status = 'confirmed'");
        $stmt->execute([$id]);
        $confirmedCount = $stmt->fetchColumn();

        if ($confirmedCount > 0) {
            header('Location: ' . $return_url . '&error=' . urlencode("No se puede eliminar la función porque tiene $confirmedCount boleto(s) confirmado(s). Mejor desactívalo."));
            exit;
        }

        // Limpiar tickets temporales 'hold' antes de eliminar el showtime
        $stmtClean = $pdo->prepare("DELETE FROM tickets WHERE showtime_id = ? AND status = 'hold'");
        $stmtClean->execute([$id]);
        $cleanedHolds = $stmtClean->rowCount();

        if ($cleanedHolds > 0) {
            error_log("🧹 showtime_actions.php: Limpiados $cleanedHolds tickets hold del showtime $id antes de eliminarlo");
        }

        $stmt = $pdo->prepare("DELETE FROM showtimes WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: ' . $return_url . '&msg=' . urlencode('Función eliminada correctamente.'));
        exit;
    } catch (PDOException $e) {
        error_log("Error al eliminar función: " . $e->getMessage());
        header('Location: ' . $return_url . '&error=' . urlencode('Error al eliminar la función. Por favor, intente nuevamente.'));
        exit;
    }
}

// ============================================
// TOGGLE FUNCIÓN (Activar/Desactivar)
// ============================================
if ($action === 'toggle_showtime') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($id <= 0) {
        header('Location: ' . $return_url . '&error=ID+inv%C3%A1lido.');
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE showtimes SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: ' . $return_url . '&msg=' . urlencode('Estado de función actualizado.'));
        exit;
    } catch (PDOException $e) {
        error_log("Error al cambiar estado de función: " . $e->getMessage());
        header('Location: ' . $return_url . '&error=' . urlencode('Error al actualizar el estado. Por favor, intente nuevamente.'));
        exit;
    }
}

// ============================================
// REDIRECCIÓN POR DEFECTO
// ============================================
header('Location: ' . $return_url);
exit;