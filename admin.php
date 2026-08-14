<?php
require_once 'config.php';

// ============================================
// SEGURIDAD Y AUTENTICACIÓN
// ============================================
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$msg = $_GET['msg'] ?? '';
$error = '';
$activeTab = $_GET['tab'] ?? 'movies';
$csrf_token = generateCSRFToken();

// ============================================
// FUNCIONES DE SEGURIDAD
// ============================================
function sanitizeInput($data, $type = 'string') {
    $data = trim($data);
    $data = stripslashes($data);
    switch ($type) {
        case 'email':
            return filter_var($data, FILTER_SANITIZE_EMAIL);
        case 'int':
            return filter_var($data, FILTER_VALIDATE_INT) ? intval($data) : null;
        case 'float':
            return filter_var($data, FILTER_VALIDATE_FLOAT) ? floatval($data) : null;
        case 'url':
            return filter_var($data, FILTER_SANITIZE_URL);
        case 'alpha_numeric':
            return preg_replace('/[^a-zA-Z0-9]/', '', $data);
        case 'phone':
            return preg_replace('/[^0-9]/', '', $data);
        default:
            return $data;
    }
}

function validateGetAction($action, $id) {
    $allowed_actions = ['delete_movie', 'delete_room', 'delete_showtime', 'toggle_movie', 'toggle_room', 'toggle_showtime', 'delete_food', 'toggle_food', 'update_movie'];
    if (!in_array($action, $allowed_actions)) {
        return false;
    }
    if (!filter_var($id, FILTER_VALIDATE_INT) || $id <= 0) {
        return false;
    }
    if (!isset($_GET['csrf_token']) || !verifyCSRFToken($_GET['csrf_token'])) {
        return false;
    }
    return true;
}

function secureFileUpload($file, $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'], $max_size = 2097152) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Error al subir el archivo.'];
    }
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'El archivo excede el tamaño máximo permitido (2MB).'];
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime_type, $allowed_types)) {
        return ['success' => false, 'error' => 'Tipo de archivo no permitido.'];
    }
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    if (!in_array($extension, $allowed_extensions)) {
        return ['success' => false, 'error' => 'Extensión de archivo no permitida.'];
    }
    return ['success' => true, 'extension' => $extension, 'mime_type' => $mime_type];
}

function validatePasswordStrength($password) {
    if (strlen($password) < 8) {
        return "La contraseña debe tener al menos 8 caracteres.";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return "La contraseña debe contener al menos una letra mayúscula.";
    }
    if (!preg_match('/[a-z]/', $password)) {
        return "La contraseña debe contener al menos una letra minúscula.";
    }
    if (!preg_match('/[0-9]/', $password)) {
        return "La contraseña debe contener al menos un número.";
    }
    return null;
}

// ============================================
// FORMATOS PREDEFINIDOS
// ============================================
$formatos = ['2D', '3D', 'IMAX', 'IMAX 3D', '4DX', 'ScreenX', 'D-BOX'];

// ============================================
// PROCESAR FORMULARIOS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Error de seguridad: Token inválido.";
    } else {

        // -----------------------------------------
        // AGREGAR PELÍCULA (CON BÚSQUEDA TMDb)
        // -----------------------------------------
        if (isset($_POST['add_movie'])) {
            $title_raw = sanitizeInput($_POST['title'] ?? '');
            $poster_url = sanitizeInput($_POST['poster_url'] ?? '', 'url');
            $banner_url = sanitizeInput($_POST['banner_url'] ?? '', 'url');
            $classification = sanitizeInput($_POST['classification'] ?? '');
            $trailer_url = sanitizeInput($_POST['trailer_url'] ?? '', 'url');
            $year = !empty($_POST['year']) ? filter_var($_POST['year'], FILTER_VALIDATE_INT) : null;
            $description = $_POST['description'] ?? '';
            $duration = !empty($_POST['duration']) ? filter_var($_POST['duration'], FILTER_VALIDATE_INT) : 0;
            $genre = sanitizeInput($_POST['genre'] ?? '');
            $director = sanitizeInput($_POST['director'] ?? '');
            $cast_members = sanitizeInput($_POST['cast_members'] ?? '');
            $country_id = !empty($_POST['country_id']) ? filter_var($_POST['country_id'], FILTER_VALIDATE_INT) : null;

            $title = $title_raw;
            $extracted_year = null;

            if (preg_match('/,\s*(\d{4})$/', $title_raw, $matches)) {
                $extracted_year = intval($matches[1]);
                $title = trim(preg_replace('/,\s*\d{4}$/', '', $title_raw));
                if (empty($year)) {
                    $year = $extracted_year;
                }
            }

            if (empty($title) || empty($trailer_url) || empty($classification)) {
                $error = "Título, Clasificación y URL del tráiler son obligatorios.";
            } elseif (!empty($poster_url) && !filter_var($poster_url, FILTER_VALIDATE_URL)) {
                $error = "URL del póster no válida.";
            } elseif (!empty($trailer_url) && !filter_var($trailer_url, FILTER_VALIDATE_URL)) {
                $error = "URL del tráiler no válida.";
            } elseif ($year !== null && ($year < 1900 || $year > date('Y') + 2)) {
                $error = "Año inválido.";
            } elseif (strlen($description) > 5000) {
                $error = "La sinopsis no puede exceder los 5000 caracteres.";
            } else {
                $tmdb_data = getMovieFromTMDB($title, $year);

                if (!$tmdb_data && $year) {
                    error_log("Búsqueda con año falló para: $title, intentando sin año");
                    $tmdb_data = getMovieFromTMDB($title, null);
                }

                if (!$tmdb_data && $title_raw !== $title) {
                    error_log("Búsqueda con título original: $title_raw");
                    $tmdb_data = getMovieFromTMDB($title_raw, null);
                }

                if ($tmdb_data) {
                    if (!empty($tmdb_data['description'])) {
                        $description = $tmdb_data['description'];
                    }
                    if (!empty($tmdb_data['runtime'])) {
                        $duration = intval($tmdb_data['runtime']);
                    }
                    if (!empty($tmdb_data['genres'])) {
                        $genre = $tmdb_data['genres'];
                    }
                    if (!empty($tmdb_data['director']) && $tmdb_data['director'] !== 'No disponible') {
                        $director = $tmdb_data['director'];
                    }
                    if (!empty($tmdb_data['cast_members'])) {
                        $cast_members = $tmdb_data['cast_members'];
                    }
                    if (!empty($tmdb_data['year'])) {
                        $year = $tmdb_data['year'];
                    }
                    if (!empty($tmdb_data['poster_path'])) {
                        $poster_url = 'https://image.tmdb.org/t/p/w500' . $tmdb_data['poster_path'];
                    }
                    if (!empty($tmdb_data['backdrop_path'])) {
                        $banner_url = 'https://image.tmdb.org/t/p/original' . $tmdb_data['backdrop_path'];
                    }
                    if (!empty($tmdb_data['country'])) {
                        $country_name = $tmdb_data['country'];
                        $stmt = $pdo->prepare("SELECT id FROM countries WHERE name = ?");
                        $stmt->execute([$country_name]);
                        $country_result = $stmt->fetch();
                        if ($country_result) {
                            $country_id = $country_result['id'];
                        } else {
                            $country_code = array_search($country_name, [
                                'Estados Unidos de América' => 'US', 'Japón' => 'JP', 'Reino Unido' => 'GB',
                                'Francia' => 'FR', 'Alemania' => 'DE', 'Corea del Sur' => 'KR',
                                'China' => 'CN', 'Canadá' => 'CA', 'España' => 'ES', 'Italia' => 'IT',
                                'México' => 'MX', 'India' => 'IN', 'Australia' => 'AU', 'Venezuela' => 'VE',
                                'Argentina' => 'AR', 'Colombia' => 'CO', 'Chile' => 'CL', 'Perú' => 'PE', 'Brasil' => 'BR'
                            ]);
                            if ($country_code) {
                                $stmt = $pdo->prepare("SELECT id FROM countries WHERE code = ?");
                                $stmt->execute([$country_code]);
                                $country_result = $stmt->fetch();
                                if ($country_result) {
                                    $country_id = $country_result['id'];
                                }
                            }
                        }
                    }
                } else {
                    $error = "No se encontró la película en TMDb. Verifica el título y año.";
                }

                if (empty($error)) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO movies (title, description, poster_url, banner_url, duration, genre, year, director, cast_members, classification, trailer_url, country_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
                        $stmt->execute([$title, $description, $poster_url, $banner_url, $duration, $genre, $year, $director, $cast_members, $classification, $trailer_url, $country_id]);
                        $success_msg = "Película «" . $title . "» agregada exitosamente desde TMDb (oculta por defecto).";
                        header("Location: admin.php?tab=movies&msg=" . urlencode($success_msg));
                        exit;
                    } catch (PDOException $e) {
                        error_log("Error al guardar película: " . $e->getMessage());
                        $error = "Error al guardar la película. Por favor, intente nuevamente.";
                    }
                }
            }
        }

        // -----------------------------------------
        // EDITAR PELÍCULA (DIRECTO A BD)
        // -----------------------------------------
        elseif (isset($_POST['edit_movie'])) {
            $id = filter_var($_POST['movie_id'] ?? 0, FILTER_VALIDATE_INT);
            $title_raw = sanitizeInput($_POST['title'] ?? '');
            $poster_url = sanitizeInput($_POST['poster_url'] ?? '', 'url');
            $banner_url = sanitizeInput($_POST['banner_url'] ?? '', 'url');
            $classification = sanitizeInput($_POST['classification'] ?? '');
            $trailer_url = sanitizeInput($_POST['trailer_url'] ?? '', 'url');
            $year = !empty($_POST['year']) ? filter_var($_POST['year'], FILTER_VALIDATE_INT) : null;
            $description = $_POST['description'] ?? '';
            $duration = !empty($_POST['duration']) ? filter_var($_POST['duration'], FILTER_VALIDATE_INT) : 0;
            $genre = sanitizeInput($_POST['genre'] ?? '');
            $director = sanitizeInput($_POST['director'] ?? '');
            $cast_members = sanitizeInput($_POST['cast_members'] ?? '');
            $country_id = !empty($_POST['country_id']) ? filter_var($_POST['country_id'], FILTER_VALIDATE_INT) : null;

            $title = $title_raw;
            if (preg_match('/,\s*(\d{4})$/', $title_raw, $matches)) {
                $title = trim(preg_replace('/,\s*\d{4}$/', '', $title_raw));
                if (empty($year)) {
                    $year = intval($matches[1]);
                }
            }

            if ($id <= 0) {
                $error = "ID de película inválido.";
            } elseif (empty($title) || empty($trailer_url) || empty($classification)) {
                $error = "Título, Clasificación y URL del tráiler son obligatorios.";
            } elseif (!empty($poster_url) && !filter_var($poster_url, FILTER_VALIDATE_URL)) {
                $error = "URL del póster no válida.";
            } elseif (!empty($trailer_url) && !filter_var($trailer_url, FILTER_VALIDATE_URL)) {
                $error = "URL del tráiler no válida.";
            } elseif ($year !== null && ($year < 1900 || $year > date('Y') + 2)) {
                $error = "Año inválido.";
            } else {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE movies SET
                            title = ?, description = ?, poster_url = ?, banner_url = ?,
                            duration = ?, genre = ?, year = ?, director = ?,
                            cast_members = ?, classification = ?, trailer_url = ?, country_id = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $title, $description, $poster_url, $banner_url,
                        $duration, $genre, $year, $director,
                        $cast_members, $classification, $trailer_url,
                        $country_id, $id
                    ]);
                    $msg_text = "Película «" . $title . "» actualizada exitosamente.";
                    header("Location: admin.php?tab=movies&msg=" . urlencode($msg_text));
                    exit;
                } catch (PDOException $e) {
                    error_log("Error al actualizar película: " . $e->getMessage());
                    $error = "Error al actualizar la película. Por favor, intente nuevamente.";
                }
            }
        }

        // -----------------------------------------
        // REGISTRAR USUARIO
        // -----------------------------------------
        elseif (isset($_POST['add_user'])) {
            $name = sanitizeInput($_POST['user_name'] ?? '');
            $email = sanitizeInput($_POST['user_email'] ?? '', 'email');
            $password = $_POST['user_password'] ?? '';
            $role = sanitizeInput($_POST['user_role'] ?? 'user');
            $cedula_type = sanitizeInput($_POST['cedula_type'] ?? '');
            $cedula_number = sanitizeInput($_POST['cedula_number'] ?? '', 'alpha_numeric');
            $phone_prefix = sanitizeInput($_POST['phone_prefix'] ?? '');
            $phone_number = sanitizeInput($_POST['phone_number'] ?? '', 'phone');
            $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;

            if (!in_array($role, ['user', 'admin'])) {
                $role = 'user';
            }

            $password_error = validatePasswordStrength($password);

            if (empty($name) || empty($email) || empty($password)) {
                $error = "Todos los campos son obligatorios.";
            } elseif ($password_error) {
                $error = $password_error;
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Email no válido.";
            } elseif (strlen($name) < 2 || strlen($name) > 100) {
                $error = "El nombre debe tener entre 2 y 100 caracteres.";
            } elseif (!empty($cedula_number) && strlen($cedula_number) < 7) {
                $error = "La Cédula de Identidad debe tener al menos 7 dígitos.";
            } elseif (!empty($phone_number) && strlen($phone_number) < 7) {
                $error = "El número de teléfono debe tener al menos 7 dígitos.";
            } elseif ($birth_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
                $error = "Formato de fecha de nacimiento inválido.";
            } else {
                if (empty($error) && !empty($cedula_type) && !empty($cedula_number)) {
                    $stmt_check = $pdo->prepare("SELECT id FROM users WHERE cedula_type = ? AND cedula_number = ?");
                    $stmt_check->execute([$cedula_type, $cedula_number]);
                    if ($stmt_check->rowCount() > 0) {
                        $cedula_display = $cedula_type . '-' . $cedula_number;
                        $error = "El usuario con número de cédula " . $cedula_display . " ya se encuentra registrado.";
                    }
                }

                if (empty($error)) {
                    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                    try {
                        $stmt = $pdo->prepare("INSERT INTO users (name, email, cedula_type, cedula_number, phone_prefix, phone_number, birth_date, password, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$name, $email, $cedula_type, $cedula_number, $phone_prefix, $phone_number, $birth_date, $passwordHash, $role]);
                        $success_msg = "Usuario «" . $name . "» registrado exitosamente.";
                        header("Location: admin.php?tab=users&msg=" . urlencode($success_msg));
                        exit;
                    } catch (PDOException $e) {
                        error_log("Error al registrar usuario: " . $e->getMessage());
                        if ($e->errorInfo[1] == 1062) {
                            $cedula_display = !empty($cedula_type) && !empty($cedula_number) ? $cedula_type . '-' . $cedula_number : 'registrada';
                            $error = "El usuario con número de cédula " . $cedula_display . " ya se encuentra registrado.";
                        } else {
                            $error = "Error al registrar usuario. Por favor, intente nuevamente.";
                        }
                    }
                }
            }
        }

        // -----------------------------------------
        // EDITAR USUARIO
        // -----------------------------------------
        elseif (isset($_POST['edit_user'])) {
            $user_id = filter_var($_POST['user_id'] ?? 0, FILTER_VALIDATE_INT);
            $name = sanitizeInput($_POST['user_name'] ?? '');
            $email = sanitizeInput($_POST['user_email'] ?? '', 'email');
            $role = sanitizeInput($_POST['user_role'] ?? 'user');
            $new_password = $_POST['user_password'] ?? '';
            $cedula_type = sanitizeInput($_POST['cedula_type'] ?? '');
            $cedula_number = sanitizeInput($_POST['cedula_number'] ?? '', 'alpha_numeric');
            $phone_prefix = sanitizeInput($_POST['phone_prefix'] ?? '');
            $phone_number = sanitizeInput($_POST['phone_number'] ?? '', 'phone');
            $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;

            if (!in_array($role, ['user', 'admin'])) {
                $role = 'user';
            }

            if ($user_id <= 0) {
                $error = "ID de usuario inválido.";
            } elseif ($user_id == $_SESSION['user_id'] && $role !== 'admin') {
                $error = "⚠️ No puedes cambiar tu propio rol de administrador.";
            } elseif (empty($name) || empty($email)) {
                $error = "Nombre y email son obligatorios.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Email no válido.";
            } elseif (strlen($name) < 2 || strlen($name) > 100) {
                $error = "El nombre debe tener entre 2 y 100 caracteres.";
            } else {
                $stmt_check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt_check->execute([$email, $user_id]);
                if ($stmt_check->rowCount() > 0) {
                    $error = "El email ya está registrado por otro usuario.";
                }

                if (empty($error) && !empty($cedula_type) && !empty($cedula_number)) {
                    $stmt_check = $pdo->prepare("SELECT id FROM users WHERE cedula_type = ? AND cedula_number = ? AND id != ?");
                    $stmt_check->execute([$cedula_type, $cedula_number, $user_id]);
                    if ($stmt_check->rowCount() > 0) {
                        $cedula_display = $cedula_type . '-' . $cedula_number;
                        $error = "El usuario con número de cédula " . $cedula_display . " ya se encuentra registrado.";
                    }
                }

                if (empty($error)) {
                    try {
                        if (!empty($new_password)) {
                            $password_error = validatePasswordStrength($new_password);
                            if ($password_error) {
                                $error = $password_error;
                            } else {
                                $passwordHash = password_hash($new_password, PASSWORD_BCRYPT);
                                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, cedula_type = ?, cedula_number = ?, phone_prefix = ?, phone_number = ?, birth_date = ?, role = ?, password = ? WHERE id = ?");
                                $stmt->execute([$name, $email, $cedula_type, $cedula_number, $phone_prefix, $phone_number, $birth_date, $role, $passwordHash, $user_id]);
                                $success_msg = "Usuario actualizado exitosamente (contraseña actualizada).";
                                header("Location: admin.php?tab=users&msg=" . urlencode($success_msg));
                                exit;
                            }
                        } else {
                            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, cedula_type = ?, cedula_number = ?, phone_prefix = ?, phone_number = ?, birth_date = ?, role = ? WHERE id = ?");
                            $stmt->execute([$name, $email, $cedula_type, $cedula_number, $phone_prefix, $phone_number, $birth_date, $role, $user_id]);
                            $success_msg = "Usuario actualizado exitosamente.";
                            header("Location: admin.php?tab=users&msg=" . urlencode($success_msg));
                            exit;
                        }
                    } catch (PDOException $e) {
                        error_log("Error al actualizar usuario: " . $e->getMessage());
                        if ($e->errorInfo[1] == 1062) {
                            $cedula_display = !empty($cedula_type) && !empty($cedula_number) ? $cedula_type . '-' . $cedula_number : 'registrada';
                            $error = "El usuario con número de cédula " . $cedula_display . " ya se encuentra registrado.";
                        } else {
                            $error = "Error al actualizar usuario. Por favor, intente nuevamente.";
                        }
                    }
                }
            }
        }

        // -----------------------------------------
        // BLOQUEAR/DESBLOQUEAR USUARIO
        // -----------------------------------------
        elseif (isset($_POST['toggle_block_user'])) {
            $user_id = filter_var($_POST['user_id'] ?? 0, FILTER_VALIDATE_INT);
            $current_status = filter_var($_POST['current_status'] ?? 0, FILTER_VALIDATE_INT);

            if ($user_id <= 0) {
                $error = "ID de usuario inválido.";
            } elseif ($user_id == $_SESSION['user_id']) {
                $error = "No puedes bloquear tu propia cuenta.";
            } else {
                $new_status = $current_status == 1 ? 0 : 1;
                try {
                    $stmt = $pdo->prepare("UPDATE users SET is_blocked = ? WHERE id = ?");
                    $stmt->execute([$new_status, $user_id]);
                    $success_msg = $new_status == 1 ? "Usuario bloqueado exitosamente." : "Usuario desbloqueado exitosamente.";
                    header("Location: admin.php?tab=users&msg=" . urlencode($success_msg));
                    exit;
                } catch (PDOException $e) {
                    error_log("Error al cambiar estado de usuario: " . $e->getMessage());
                    $error = "Error al cambiar estado del usuario. Por favor, intente nuevamente.";
                }
            }
        }

        // -----------------------------------------
        // ELIMINAR USUARIO
        // -----------------------------------------
        elseif (isset($_POST['delete_user'])) {
            $user_id = filter_var($_POST['user_id'] ?? 0, FILTER_VALIDATE_INT);

            if ($user_id <= 0) {
                $error = "ID de usuario inválido.";
            } elseif ($user_id == $_SESSION['user_id']) {
                $error = "No puedes eliminar tu propia cuenta.";
            } else {
                try {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    header("Location: admin.php?tab=users&msg=" . urlencode("Usuario eliminado exitosamente."));
                    exit;
                } catch (PDOException $e) {
                    error_log("Error al eliminar usuario: " . $e->getMessage());
                    $error = "Error al eliminar usuario. Por favor, intente nuevamente.";
                }
            }
        }

        // ============================================
        // AGREGAR HORARIO
        // ============================================
        elseif (isset($_POST['add_showtime'])) {
            $movie_id = filter_var($_POST['movie_id'] ?? 0, FILTER_VALIDATE_INT);
            $room_id = filter_var($_POST['room_id'] ?? 0, FILTER_VALIDATE_INT);
            $show_date = $_POST['show_date'] ?? '';
            $show_time = $_POST['show_time'] ?? '';
            $format = sanitizeInput($_POST['format'] ?? '2D');
            $price_adult = filter_var($_POST['price_adult'] ?? 0, FILTER_VALIDATE_FLOAT);

            // ✅ CORREGIDO: Manejar precios de niño y tercera edad
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
                $error = "Selecciona una película y una sala.";
            } elseif (empty($show_date) || empty($show_time) || $price_adult <= 0 || empty($format)) {
                $error = "Todos los campos son obligatorios.";
            } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $show_date)) {
                $error = "Formato de fecha inválido.";
            } elseif (!preg_match('/^\d{2}:\d{2}$/', $show_time)) {
                $error = "Formato de hora inválido.";
            } elseif (!in_array($format, $formatos)) {
                $error = "Formato de proyección no válido.";
            } else {
                $stmt = $pdo->prepare("SELECT duration FROM movies WHERE id = ? AND is_active = 1");
                $stmt->execute([$movie_id]);
                $movie = $stmt->fetch();

                if (!$movie) {
                    $error = "Película no encontrada o inactiva.";
                } else {
                    $conflict = checkShowtimeConflict($pdo, $room_id, $show_date, $show_time, $movie['duration']);

                    if ($conflict['conflict']) {
                        $error = "❌ " . $conflict['message'];
                    } else {
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
                            header("Location: admin.php?tab=showtimes&msg=" . urlencode("Horario agregado exitosamente."));
                            exit;
                        } catch (PDOException $e) {
                            error_log("Error al agregar horario: " . $e->getMessage());
                            $error = "Error al agregar el horario. Por favor, intente nuevamente.";
                        }
                    }
                }
            }
        }

        // ============================================
        // EDITAR HORARIO
        // ============================================
        elseif (isset($_POST['edit_showtime'])) {
            $old_id = filter_var($_POST['showtime_id'] ?? 0, FILTER_VALIDATE_INT);

            // ✅ CORREGIDO: Obtener precios actuales ANTES de procesar para no destruirlos
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

            // ✅ CORREGIDO: Capturar switches
            $enable_child_price = isset($_POST['enable_child_price']) ? 1 : 0;
            $enable_senior_price = isset($_POST['enable_senior_price']) ? 1 : 0;

            // ✅ CORREGIDO: No destruir precios si el input no fue enviado o viene vacío
            $posted_child = $_POST['price_child'] ?? '';
            $posted_senior = $_POST['price_senior'] ?? '';

            if ($enable_child_price) {
                // Si está habilitado y el admin escribió un precio, usar ese precio.
                // Si el campo vino vacío, conservar el precio anterior.
                $price_child = is_numeric($posted_child) ? floatval($posted_child) : floatval($old_prices['price_child']);
            } else {
                // ✅ Si el switch está apagado, conservamos el precio en BD,
                // pero enable_child_price = 0 indica que no debe usarse.
                $price_child = floatval($old_prices['price_child']);
            }

            if ($enable_senior_price) {
                // Si está habilitado y el admin escribió un precio, usar ese precio.
                // Si el campo vino vacío, conservar el precio anterior.
                $price_senior = is_numeric($posted_senior) ? floatval($posted_senior) : floatval($old_prices['price_senior']);
            } else {
                // ✅ Si el switch está apagado, conservamos el precio en BD,
                // pero enable_senior_price = 0 indica que no debe usarse.
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
                $error = "Datos inválidos.";
            } elseif (empty($show_date) || empty($show_time) || $price_adult <= 0 || empty($format)) {
                $error = "Todos los campos son obligatorios.";
            } elseif (!in_array($format, $formatos)) {
                $error = "Formato de proyección no válido.";
            } else {
                $stmt = $pdo->prepare("SELECT * FROM showtimes WHERE id = ?");
                $stmt->execute([$old_id]);
                $old_showtime = $stmt->fetch();

                if (!$old_showtime) {
                    $error = "Horario no encontrado.";
                } else {
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
                        header("Location: admin.php?tab=showtimes&msg=" . urlencode("No se detectaron cambios en el horario."));
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
                            header("Location: admin.php?tab=showtimes&msg=" . urlencode("Horario actualizado exitosamente."));
                            exit;
                        } catch (PDOException $e) {
                            error_log("Error al actualizar horario: " . $e->getMessage());
                            $error = "Error al actualizar el horario. Por favor, intente nuevamente.";
                        }
                    } else {
                        $stmt = $pdo->prepare("SELECT duration FROM movies WHERE id = ? AND is_active = 1");
                        $stmt->execute([$movie_id]);
                        $movie_duration = $stmt->fetch();

                        if (!$movie_duration) {
                            $error = "Película no encontrada o inactiva.";
                        } else {
                            $conflict = checkShowtimeConflict($pdo, $room_id, $show_date, $show_time, $movie_duration['duration'], $old_id);

                            if ($conflict['conflict']) {
                                $error = "❌ " . $conflict['message'];
                            } else {
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
                                    $success_msg = "Horario actualizado exitosamente. Se creó una nueva función con los cambios.";
                                    header("Location: admin.php?tab=showtimes&msg=" . urlencode($success_msg));
                                    exit;
                                } catch (PDOException $e) {
                                    $pdo->rollBack();
                                    error_log("Error al actualizar horario: " . $e->getMessage());
                                    $error = "Error al actualizar el horario. Por favor, intente nuevamente.";
                                }
                            }
                        }
                    }
                }
            }
        }

        // ============================================
        // PROCESAR FORMULARIOS - COMIDA
        // ============================================
        if (isset($_POST['add_food'])) {
            $name = sanitizeInput($_POST['food_name'] ?? '');
            $description = $_POST['food_description'] ?? '';
            $price = filter_var($_POST['food_price'] ?? 0, FILTER_VALIDATE_FLOAT);
            $category_id = !empty($_POST['category_id']) ? filter_var($_POST['category_id'], FILTER_VALIDATE_INT) : null;

            if (empty($name) || $price <= 0) {
                $error = "Nombre y precio son obligatorios.";
            } else {
                try {
                    $image_path = '';
                    if (isset($_FILES['food_image']) && $_FILES['food_image']['error'] === UPLOAD_ERR_OK) {
                        $upload_result = secureFileUpload($_FILES['food_image'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'], 2097152);
                        if (!$upload_result['success']) {
                            $error = $upload_result['error'];
                        } else {
                            $upload_dir = 'img/';
                            if (!is_dir($upload_dir)) {
                                mkdir($upload_dir, 0755, true);
                            }
                            $filename = 'food_' . time() . '_' . uniqid() . '.' . $upload_result['extension'];
                            $destination = $upload_dir . $filename;
                            if (move_uploaded_file($_FILES['food_image']['tmp_name'], $destination)) {
                                $image_path = $destination;
                            } else {
                                $error = "Error al subir la imagen.";
                            }
                        }
                    }

                    if (empty($error)) {
                        $stmt = $pdo->prepare("INSERT INTO food_items (name, description, price, image_url, category_id, is_active) VALUES (?, ?, ?, ?, ?, 0)");
                        $stmt->execute([$name, $description, $price, $image_path, $category_id]);
                        $success_msg = "Producto «" . $name . "» agregado exitosamente (oculto por defecto).";
                        header("Location: admin.php?tab=food&msg=" . urlencode($success_msg));
                        exit;
                    }
                } catch (PDOException $e) {
                    error_log("Error al guardar producto: " . $e->getMessage());
                    $error = "Error al guardar el producto. Por favor, intente nuevamente.";
                }
            }
        }

        elseif (isset($_POST['edit_food'])) {
            $id = filter_var($_POST['food_id'] ?? 0, FILTER_VALIDATE_INT);
            $name = sanitizeInput($_POST['food_name'] ?? '');
            $description = $_POST['food_description'] ?? '';
            $price = filter_var($_POST['food_price'] ?? 0, FILTER_VALIDATE_FLOAT);
            $category_id = !empty($_POST['category_id']) ? filter_var($_POST['category_id'], FILTER_VALIDATE_INT) : null;

            if ($id <= 0) {
                $error = "ID de producto inválido.";
            } elseif (empty($name) || $price <= 0) {
                $error = "Nombre y precio son obligatorios.";
            } else {
                try {
                    $image_path = null;
                    if (isset($_FILES['food_image']) && $_FILES['food_image']['error'] === UPLOAD_ERR_OK) {
                        $upload_result = secureFileUpload($_FILES['food_image'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'], 2097152);
                        if (!$upload_result['success']) {
                            $error = $upload_result['error'];
                        } else {
                            $upload_dir = 'img/';
                            if (!is_dir($upload_dir)) {
                                mkdir($upload_dir, 0755, true);
                            }
                            $stmt = $pdo->prepare("SELECT image_url FROM food_items WHERE id = ?");
                            $stmt->execute([$id]);
                            $old_image = $stmt->fetchColumn();
                            if (!empty($old_image) && file_exists($old_image)) {
                                @unlink($old_image);
                            }
                            $filename = 'food_' . time() . '_' . uniqid() . '.' . $upload_result['extension'];
                            $destination = $upload_dir . $filename;
                            if (move_uploaded_file($_FILES['food_image']['tmp_name'], $destination)) {
                                $image_path = $destination;
                            } else {
                                $error = "Error al subir la imagen.";
                            }
                        }
                    }

                    if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
                        $stmt = $pdo->prepare("SELECT image_url FROM food_items WHERE id = ?");
                        $stmt->execute([$id]);
                        $old_image = $stmt->fetchColumn();
                        if (!empty($old_image) && file_exists($old_image)) {
                            @unlink($old_image);
                        }
                        $image_path = '';
                    }

                    if (empty($error)) {
                        if ($image_path !== null) {
                            $stmt = $pdo->prepare("UPDATE food_items SET name=?, description=?, price=?, image_url=?, category_id=? WHERE id=?");
                            $stmt->execute([$name, $description, $price, $image_path, $category_id, $id]);
                        } else {
                            $stmt = $pdo->prepare("UPDATE food_items SET name=?, description=?, price=?, category_id=? WHERE id=?");
                            $stmt->execute([$name, $description, $price, $category_id, $id]);
                        }
                        header("Location: admin.php?tab=food&msg=" . urlencode("Producto actualizado exitosamente."));
                        exit;
                    }
                } catch (PDOException $e) {
                    error_log("Error al actualizar producto: " . $e->getMessage());
                    $error = "Error al actualizar el producto. Por favor, intente nuevamente.";
                }
            }
        }

        // -----------------------------------------
        // CONFIGURACIÓN DEL SITIO
        // -----------------------------------------
        if (isset($_POST['save_config'])) {
            try {
                $config_keys = [
                    'site_name', 'currency_symbol', 'currency_position',
                    'thousands_separator', 'decimal_separator', 'decimal_places',
                    'address', 'phone', 'email',
                    'instagram', 'facebook', 'twitter', 'telegram', 'whatsapp'
                ];

                foreach ($config_keys as $key) {
                    if (isset($_POST[$key])) {
                        $value = sanitizeInput($_POST[$key]);
                        if ($key === 'email' && !empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $error = "Email de contacto no válido.";
                            break;
                        }
                        if (in_array($key, ['instagram', 'facebook', 'twitter', 'telegram', 'whatsapp']) && !empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                            $error = "URL de " . $key . " no válida.";
                            break;
                        }
                        $stmt = $pdo->prepare("UPDATE site_config SET value = ? WHERE key_name = ?");
                        $stmt->execute([$value, $key]);
                    }
                }

                if (isset($_POST['tax_rate'])) {
                    $tax_rate = filter_var($_POST['tax_rate'], FILTER_VALIDATE_FLOAT);
                    if ($tax_rate !== false && $tax_rate >= 0 && $tax_rate <= 100) {
                        $stmt = $pdo->prepare("UPDATE tax_config SET tax_rate = ?, updated_at = NOW() WHERE is_active = 1");
                        $stmt->execute([$tax_rate]);
                    }
                }

                if (empty($error) && isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
                    $upload_result = secureFileUpload($_FILES['site_logo']);
                    if (!$upload_result['success']) {
                        $error = $upload_result['error'];
                    } else {
                        $upload_dir = 'uploads/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        $filename = 'logo.' . $upload_result['extension'];
                        $destination = $upload_dir . $filename;

                        $stmt = $pdo->prepare("SELECT value FROM site_config WHERE key_name = 'site_logo'");
                        $stmt->execute();
                        $old_logo = $stmt->fetchColumn();
                        if (!empty($old_logo) && file_exists($old_logo) && $old_logo !== $destination) {
                            @unlink($old_logo);
                        }

                        if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $destination)) {
                            $stmt = $pdo->prepare("UPDATE site_config SET value = ? WHERE key_name = 'site_logo'");
                            $stmt->execute([$destination]);
                        } else {
                            $error = "Error al subir el logo del header.";
                        }
                    }
                }

                if (empty($error) && isset($_FILES['footer_logo']) && $_FILES['footer_logo']['error'] === UPLOAD_ERR_OK) {
                    $upload_result = secureFileUpload($_FILES['footer_logo']);
                    if (!$upload_result['success']) {
                        $error = $upload_result['error'];
                    } else {
                        $upload_dir = 'uploads/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        $filename = 'footer_logo.' . $upload_result['extension'];
                        $destination = $upload_dir . $filename;

                        $stmt = $pdo->prepare("SELECT value FROM site_config WHERE key_name = 'footer_logo'");
                        $stmt->execute();
                        $old_logo = $stmt->fetchColumn();
                        if (!empty($old_logo) && file_exists($old_logo) && $old_logo !== $destination) {
                            @unlink($old_logo);
                        }

                        if (move_uploaded_file($_FILES['footer_logo']['tmp_name'], $destination)) {
                            $stmt = $pdo->prepare("UPDATE site_config SET value = ? WHERE key_name = 'footer_logo'");
                            $stmt->execute([$destination]);
                        } else {
                            $error = "Error al subir el logo del footer.";
                        }
                    }
                }

                if (empty($error) && isset($_FILES['site_favicon']) && $_FILES['site_favicon']['error'] === UPLOAD_ERR_OK) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime_type = finfo_file($finfo, $_FILES['site_favicon']['tmp_name']);
                    finfo_close($finfo);

                    $allowed_mimes = ['image/png', 'image/x-icon', 'image/vnd.microsoft.icon'];
                    $allowed_extensions = ['png', 'ico'];
                    $extension = strtolower(pathinfo($_FILES['site_favicon']['name'], PATHINFO_EXTENSION));

                    if (!in_array($mime_type, $allowed_mimes) || !in_array($extension, $allowed_extensions)) {
                        $error = "Tipo de archivo no permitido para favicon. Solo PNG o ICO.";
                    } elseif ($_FILES['site_favicon']['size'] > 1048576) {
                        $error = "El favicon excede el tamaño máximo permitido (1MB).";
                    } else {
                        $upload_dir = 'uploads/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        $filename = 'favicon.' . $extension;
                        $destination = $upload_dir . $filename;

                        $stmt = $pdo->prepare("SELECT value FROM site_config WHERE key_name = 'site_favicon'");
                        $stmt->execute();
                        $old_favicon = $stmt->fetchColumn();
                        if (!empty($old_favicon) && file_exists($old_favicon) && $old_favicon !== $destination) {
                            @unlink($old_favicon);
                        }

                        if (move_uploaded_file($_FILES['site_favicon']['tmp_name'], $destination)) {
                            $stmt = $pdo->prepare("UPDATE site_config SET value = ? WHERE key_name = 'site_favicon'");
                            $stmt->execute([$destination]);
                        } else {
                            $error = "Error al subir el favicon.";
                        }
                    }
                }

                if (empty($error) && isset($_POST['remove_logo']) && $_POST['remove_logo'] == '1') {
                    $stmt = $pdo->prepare("SELECT value FROM site_config WHERE key_name = 'site_logo'");
                    $stmt->execute();
                    $old_logo = $stmt->fetchColumn();
                    if (!empty($old_logo) && file_exists($old_logo)) {
                        @unlink($old_logo);
                    }
                    $stmt = $pdo->prepare("UPDATE site_config SET value = '' WHERE key_name = 'site_logo'");
                    $stmt->execute([]);
                }

                if (empty($error) && isset($_POST['remove_footer_logo']) && $_POST['remove_footer_logo'] == '1') {
                    $stmt = $pdo->prepare("SELECT value FROM site_config WHERE key_name = 'footer_logo'");
                    $stmt->execute();
                    $old_logo = $stmt->fetchColumn();
                    if (!empty($old_logo) && file_exists($old_logo)) {
                        @unlink($old_logo);
                    }
                    $stmt = $pdo->prepare("UPDATE site_config SET value = '' WHERE key_name = 'footer_logo'");
                    $stmt->execute([]);
                }

                if (empty($error) && isset($_POST['remove_favicon']) && $_POST['remove_favicon'] == '1') {
                    $stmt = $pdo->prepare("SELECT value FROM site_config WHERE key_name = 'site_favicon'");
                    $stmt->execute();
                    $old_favicon = $stmt->fetchColumn();
                    if (!empty($old_favicon) && file_exists($old_favicon)) {
                        @unlink($old_favicon);
                    }
                    $stmt = $pdo->prepare("UPDATE site_config SET value = '' WHERE key_name = 'site_favicon'");
                    $stmt->execute([]);
                }

                if (empty($error)) {
                    $success_msg = "Configuración actualizada exitosamente.";
                    header("Location: admin.php?tab=config&msg=" . urlencode($success_msg));
                    exit;
                }
            } catch (PDOException $e) {
                error_log("Error al guardar configuración: " . $e->getMessage());
                $error = "Error al guardar configuración. Por favor, intente nuevamente.";
            }
        }
    }
}

// ============================================
// MANEJAR ACCIONES GET CON CSRF
// ============================================
$csrf_token_get = $_GET['csrf_token'] ?? '';

// ============================================
// ACTUALIZAR PELÍCULA DESDE TMDb (BOTÓN ACTUALIZAR)
// ============================================
if (isset($_GET['update_movie']) && validateGetAction('update_movie', $_GET['update_movie'])) {
    $id = intval($_GET['update_movie']);

    try {
        $stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
        $stmt->execute([$id]);
        $movie = $stmt->fetch();

        if (!$movie) {
            $error = "Película no encontrada.";
        } else {
            $tmdb_data = getMovieFromTMDB($movie['title'], $movie['year']);

            if (!$tmdb_data) {
                error_log("Búsqueda con año falló para: " . $movie['title'] . ", intentando sin año");
                $tmdb_data = getMovieFromTMDB($movie['title'], null);
            }

            if (!$tmdb_data) {
                $error = "No se pudieron obtener datos de TMDb para la película: " . $movie['title'];
            } else {
                $description = !empty($tmdb_data['description']) ? $tmdb_data['description'] : $movie['description'];
                $duration = !empty($tmdb_data['runtime']) ? intval($tmdb_data['runtime']) : $movie['duration'];
                $genre = !empty($tmdb_data['genres']) ? $tmdb_data['genres'] : $movie['genre'];
                $director = !empty($tmdb_data['director']) && $tmdb_data['director'] !== 'No disponible' ? $tmdb_data['director'] : $movie['director'];
                $cast_members = !empty($tmdb_data['cast_members']) ? $tmdb_data['cast_members'] : $movie['cast_members'];
                $year = !empty($tmdb_data['year']) ? $tmdb_data['year'] : $movie['year'];
                $poster_url = !empty($tmdb_data['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $tmdb_data['poster_path'] : $movie['poster_url'];
                $banner_url = !empty($tmdb_data['backdrop_path']) ? 'https://image.tmdb.org/t/p/original' . $tmdb_data['backdrop_path'] : $movie['banner_url'];
                $country_id = $movie['country_id'];

                if (empty($country_id) && !empty($tmdb_data['country'])) {
                    $country_name = $tmdb_data['country'];
                    $stmt = $pdo->prepare("SELECT id FROM countries WHERE name = ?");
                    $stmt->execute([$country_name]);
                    $country_result = $stmt->fetch();
                    if ($country_result) {
                        $country_id = $country_result['id'];
                    } else {
                        $country_code = array_search($country_name, [
                            'Estados Unidos de América' => 'US', 'Japón' => 'JP', 'Reino Unido' => 'GB',
                            'Francia' => 'FR', 'Alemania' => 'DE', 'Corea del Sur' => 'KR',
                            'China' => 'CN', 'Canadá' => 'CA', 'España' => 'ES', 'Italia' => 'IT',
                            'México' => 'MX', 'India' => 'IN', 'Australia' => 'AU', 'Venezuela' => 'VE',
                            'Argentina' => 'AR', 'Colombia' => 'CO', 'Chile' => 'CL', 'Perú' => 'PE', 'Brasil' => 'BR'
                        ]);
                        if ($country_code) {
                            $stmt = $pdo->prepare("SELECT id FROM countries WHERE code = ?");
                            $stmt->execute([$country_code]);
                            $country_result = $stmt->fetch();
                            if ($country_result) {
                                $country_id = $country_result['id'];
                            }
                        }
                    }
                }

                $updated_fields = [];
                if ($description != $movie['description']) $updated_fields[] = 'Sinopsis';
                if ($duration != $movie['duration']) $updated_fields[] = 'Duración';
                if ($genre != $movie['genre']) $updated_fields[] = 'Género';
                if ($director != $movie['director']) $updated_fields[] = 'Director';
                if ($cast_members != $movie['cast_members']) $updated_fields[] = 'Reparto';
                if ($year != $movie['year']) $updated_fields[] = 'Año';
                if ($poster_url != $movie['poster_url']) $updated_fields[] = 'Póster';
                if ($banner_url != $movie['banner_url']) $updated_fields[] = 'Banner';
                if ($country_id != $movie['country_id']) $updated_fields[] = 'País';

                $stmt = $pdo->prepare("
                    UPDATE movies SET
                        description = ?, duration = ?, genre = ?, director = ?,
                        cast_members = ?, year = ?, poster_url = ?, banner_url = ?, country_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $description, $duration, $genre, $director,
                    $cast_members, $year, $poster_url, $banner_url, $country_id, $id
                ]);

                if (!empty($updated_fields)) {
                    $msg_text = "Película «" . $movie['title'] . "» fue actualizada desde TMDb. Campos actualizados: " . implode(', ', $updated_fields) . ".";
                } else {
                    $msg_text = "Película «" . $movie['title'] . "» ya está actualizada. No se detectaron cambios.";
                }

                header("Location: admin.php?tab=movies&msg=" . urlencode($msg_text));
                exit;
            }
        }
    } catch (PDOException $e) {
        error_log("Error al actualizar película desde TMDb: " . $e->getMessage());
        $error = "Error al actualizar la película. Por favor, intente nuevamente.";
    }
}

// Eliminar Película
if (isset($_GET['delete_movie']) && validateGetAction('delete_movie', $_GET['delete_movie'])) {
    $id = intval($_GET['delete_movie']);

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE t FROM tickets t INNER JOIN showtimes s ON t.showtime_id = s.id WHERE s.movie_id = ?");
        $stmt->execute([$id]);

        $stmt = $pdo->prepare("DELETE tl FROM ticket_logs tl INNER JOIN showtimes s ON tl.showtime_id = s.id WHERE s.movie_id = ?");
        $stmt->execute([$id]);

        $stmt = $pdo->prepare("DELETE FROM showtimes WHERE movie_id = ?");
        $stmt->execute([$id]);

        $stmt = $pdo->prepare("DELETE FROM movies WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit();
        header("Location: admin.php?tab=movies&msg=" . urlencode("Película eliminada correctamente."));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error al eliminar película: " . $e->getMessage());
        $error = "Error al eliminar la película. Por favor, intente nuevamente.";
    }
}

// Toggle Película
if (isset($_GET['toggle_movie']) && validateGetAction('toggle_movie', $_GET['toggle_movie'])) {
    $id = intval($_GET['toggle_movie']);

    try {
        $stmt = $pdo->prepare("UPDATE movies SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: admin.php?tab=movies&msg=" . urlencode("Estado de película actualizado."));
        exit;
    } catch (PDOException $e) {
        error_log("Error al cambiar estado de película: " . $e->getMessage());
        $error = "Error al actualizar el estado. Por favor, intente nuevamente.";
    }
}

// Eliminar Sala
if (isset($_GET['delete_room']) && validateGetAction('delete_room', $_GET['delete_room'])) {
    $id = intval($_GET['delete_room']);

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM showtimes WHERE room_id = ? AND is_active = 1");
        $stmt->execute([$id]);

        if ($stmt->fetchColumn() > 0) {
            $error = "No se puede eliminar la sala porque tiene funciones activas.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: admin.php?tab=rooms&msg=" . urlencode("Sala eliminada correctamente."));
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error al eliminar sala: " . $e->getMessage());
        $error = "Error al eliminar la sala. Por favor, intente nuevamente.";
    }
}

// Toggle Sala
if (isset($_GET['toggle_room']) && validateGetAction('toggle_room', $_GET['toggle_room'])) {
    $id = intval($_GET['toggle_room']);

    try {
        $stmt = $pdo->prepare("UPDATE rooms SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: admin.php?tab=rooms&msg=" . urlencode("Estado de sala actualizado."));
        exit;
    } catch (PDOException $e) {
        error_log("Error al cambiar estado de sala: " . $e->getMessage());
        $error = "Error al actualizar el estado. Por favor, intente nuevamente.";
    }
}

// Eliminar Horario
if (isset($_GET['delete_showtime']) && validateGetAction('delete_showtime', $_GET['delete_showtime'])) {
    $id = intval($_GET['delete_showtime']);

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE showtime_id = ?");
        $stmt->execute([$id]);

        if ($stmt->fetchColumn() > 0) {
            $error = "No se puede eliminar el horario porque tiene boletos vendidos. Mejor desactívalo.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM showtimes WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: admin.php?tab=showtimes&msg=" . urlencode("Horario eliminado correctamente."));
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error al eliminar horario: " . $e->getMessage());
        $error = "Error al eliminar el horario. Por favor, intente nuevamente.";
    }
}

// Toggle Horario
if (isset($_GET['toggle_showtime']) && validateGetAction('toggle_showtime', $_GET['toggle_showtime'])) {
    $id = intval($_GET['toggle_showtime']);

    try {
        $stmt = $pdo->prepare("UPDATE showtimes SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: admin.php?tab=showtimes&msg=" . urlencode("Estado de horario actualizado."));
        exit;
    } catch (PDOException $e) {
        error_log("Error al cambiar estado de horario: " . $e->getMessage());
        $error = "Error al actualizar el estado. Por favor, intente nuevamente.";
    }
}

// ============================================
// MANEJAR ACCIONES GET PARA COMIDA
// ============================================
if (isset($_GET['delete_food']) && validateGetAction('delete_food', $_GET['delete_food'])) {
    $id = intval($_GET['delete_food']);

    try {
        $stmt = $pdo->prepare("SELECT image_url FROM food_items WHERE id = ?");
        $stmt->execute([$id]);
        $image = $stmt->fetchColumn();
        if (!empty($image) && file_exists($image)) {
            @unlink($image);
        }

        $stmt = $pdo->prepare("DELETE FROM food_items WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: admin.php?tab=food&msg=" . urlencode("Producto eliminado correctamente."));
        exit;
    } catch (PDOException $e) {
        error_log("Error al eliminar producto: " . $e->getMessage());
        $error = "Error al eliminar el producto. Por favor, intente nuevamente.";
    }
}

if (isset($_GET['toggle_food']) && validateGetAction('toggle_food', $_GET['toggle_food'])) {
    $id = intval($_GET['toggle_food']);

    try {
        $stmt = $pdo->prepare("UPDATE food_items SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: admin.php?tab=food&msg=" . urlencode("Estado del producto actualizado."));
        exit;
    } catch (PDOException $e) {
        error_log("Error al cambiar estado de producto: " . $e->getMessage());
        $error = "Error al actualizar el estado. Por favor, intente nuevamente.";
    }
}

// ============================================
// OBTENER DATOS (ORDEN ASCENDENTE)
// ============================================
$search_title = isset($_GET['search_title']) ? trim($_GET['search_title']) : '';
$movies_sql = "SELECT * FROM movies WHERE 1=1";
$movies_params = [];
if (!empty($search_title)) {
    $movies_sql .= " AND title LIKE ?";
    $movies_params[] = '%' . $search_title . '%';
}
$movies_sql .= " ORDER BY title ASC";
$stmt = $pdo->prepare($movies_sql);
$stmt->execute($movies_params);
$movies = $stmt->fetchAll();

$rooms = $pdo->query("SELECT * FROM rooms ORDER BY name ASC")->fetchAll();
$countries = $pdo->query("SELECT * FROM countries ORDER BY name ASC")->fetchAll();

$search_showtime = isset($_GET['search_showtime']) ? trim($_GET['search_showtime']) : '';
$showtimes_sql = "
    SELECT s.*, m.title as movie_title, m.duration, COALESCE(m.is_active, 0) as movie_active,
           r.name as room_name, COALESCE(s.format, '2D') as format
    FROM showtimes s
    LEFT JOIN movies m ON s.movie_id = m.id
    JOIN rooms r ON s.room_id = r.id
    WHERE 1=1
";
if (!empty($search_showtime)) {
    $showtimes_sql .= " AND m.title LIKE ?";
    $showtimes_sql .= " ORDER BY m.title ASC, s.show_date DESC, s.show_time";
    $stmt = $pdo->prepare($showtimes_sql);
    $stmt->execute(['%' . $search_showtime . '%']);
} else {
    $showtimes_sql .= " ORDER BY m.title ASC, s.show_date DESC, s.show_time";
    $stmt = $pdo->query($showtimes_sql);
}
$showtimes = $stmt->fetchAll();

$search_cedula = isset($_GET['search_cedula']) ? trim($_GET['search_cedula']) : '';
$users_sql = "SELECT * FROM users WHERE 1=1";
$users_params = [];
if (!empty($search_cedula)) {
    $users_sql .= " AND cedula_number LIKE ?";
    $users_params[] = '%' . $search_cedula . '%';
}
$users_sql .= " ORDER BY id DESC";
$stmt = $pdo->prepare($users_sql);
$stmt->execute($users_params);
$users = $stmt->fetchAll();

$food_items = $pdo->query("SELECT f.*, c.name as category_name FROM food_items f LEFT JOIN food_categories c ON f.category_id = c.id ORDER BY c.name ASC, f.name ASC")->fetchAll();
$food_categories = $pdo->query("SELECT * FROM food_categories ORDER BY name ASC")->fetchAll();

$taxConfig = $pdo->query("SELECT tax_rate FROM tax_config WHERE is_active = 1 LIMIT 1")->fetch();
$taxRate = $taxConfig ? floatval($taxConfig['tax_rate']) : 16;

$formats_stmt = $pdo->query("SELECT DISTINCT format FROM showtimes WHERE format IS NOT NULL AND format != '' ORDER BY format ASC");
$formatos_bd = $formats_stmt->fetchAll(PDO::FETCH_COLUMN);
$formatos = array_unique(array_merge(['2D', '3D', 'IMAX', 'IMAX 3D', '4DX', 'ScreenX', 'D-BOX'], $formatos_bd));
sort($formatos);

// ============================================
// CARGAR DATOS DE EDICIÓN
// ============================================
$edit_movie = null;
$edit_room = null;
$edit_showtime = null;
$edit_user = null;
$edit_food = null;

if (isset($_GET['edit_movie_id']) && filter_var($_GET['edit_movie_id'], FILTER_VALIDATE_INT)) {
    $stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
    $stmt->execute([intval($_GET['edit_movie_id'])]);
    $edit_movie = $stmt->fetch();
}

if (isset($_GET['edit_room_id']) && filter_var($_GET['edit_room_id'], FILTER_VALIDATE_INT)) {
    $stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
    $stmt->execute([intval($_GET['edit_room_id'])]);
    $edit_room = $stmt->fetch();
    if ($edit_room && $edit_room['seat_layout']) {
        $edit_room['seat_layout'] = json_decode($edit_room['seat_layout'], true);
    }
}

if (isset($_GET['edit_showtime_id']) && filter_var($_GET['edit_showtime_id'], FILTER_VALIDATE_INT)) {
    $stmt = $pdo->prepare("SELECT * FROM showtimes WHERE id = ?");
    $stmt->execute([intval($_GET['edit_showtime_id'])]);
    $edit_showtime = $stmt->fetch();
    if ($edit_showtime && $edit_showtime['promotions']) {
        $edit_showtime['promotions_array'] = explode(',', $edit_showtime['promotions']);
    }
    if ($edit_showtime && !isset($edit_showtime['language'])) {
        $edit_showtime['language'] = 'español';
    }
}

if (isset($_GET['edit_user_id']) && filter_var($_GET['edit_user_id'], FILTER_VALIDATE_INT)) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([intval($_GET['edit_user_id'])]);
    $edit_user = $stmt->fetch();
}

if (isset($_GET['edit_food_id']) && filter_var($_GET['edit_food_id'], FILTER_VALIDATE_INT)) {
    $stmt = $pdo->prepare("SELECT * FROM food_items WHERE id = ?");
    $stmt->execute([intval($_GET['edit_food_id'])]);
    $edit_food = $stmt->fetch();
}

$siteConfig = getSiteConfig($pdo);
$pageTitle = "Panel de Control - " . ($siteConfig['site_name'] ?? 'Cinema Pro');

// ============================================
// RENDERIZAR HTML
// ============================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <?php
    $favicon_path = $siteConfig['site_favicon'] ?? '';
    $favicon_exists = !empty($favicon_path) && file_exists($favicon_path);
    ?>
    <?php if($favicon_exists): ?>
        <link rel="icon" type="<?= mime_content_type($favicon_path) ?>" href="<?= htmlspecialchars($favicon_path) . '?v=' . filemtime($favicon_path) ?>">
    <?php else: ?>
        <link rel="icon" type="image/png" href="favicon.png">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
/* ============================================
   ESTILOS GENERALES
   ============================================ */
.tab-active { background-color: #4f46e5; color: white; }
.tab-inactive { background-color: #1f2937; color: #9ca3af; }
.tab-inactive:hover { background-color: #374151; color: white; }
.time-display { font-family: 'Courier New', monospace; font-weight: bold; }

.conflict-warning { background-color: #dc262620; border-color: #dc2626; color: #fca5a5; }
.conflict-checking { background-color: #3b82f620; border-color: #3b82f6; color: #93c5fd; }
.conflict-safe { background-color: #22c55e20; border-color: #22c55e; color: #86efac; }

/* ============================================
   TOGGLE SWITCH ESTILOS
   ============================================ */
.toggle-switch {
    position: relative;
    width: 44px;
    height: 24px;
    flex-shrink: 0;
    cursor: pointer;
}
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: #4b5563;
    transition: .3s;
    border-radius: 24px;
}
.toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px; width: 18px;
    left: 3px; bottom: 3px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
}
.toggle-switch input:checked + .toggle-slider { background-color: #4f46e5; }
.toggle-switch input:checked + .toggle-slider:before { transform: translateX(20px); }
.toggle-switch input:disabled + .toggle-slider { opacity: 0.4; cursor: not-allowed; }

.price-input-disabled { opacity: 0.5; cursor: not-allowed !important; }
.btn-disabled { opacity: 0.5 !important; cursor: not-allowed !important; pointer-events: none; }

/* ============================================
   FORMATOS Y BADGES
   ============================================ */
.format-badge {
    display: inline-block;
    padding: 1px 8px;
    border-radius: 4px;
    font-size: 9px;
    font-weight: 700;
    background: #1e293b;
    color: #94a3b8;
    border: 1px solid #334155;
    text-transform: uppercase;
}
.format-badge.format-2d { background: #1e293b; color: #94a3b8; border-color: #334155; }
.format-badge.format-3d { background: #1e1b4b; color: #818cf8; border-color: #4f46e5; }
.format-badge.format-imax { background: #1a1a2e; color: #fbbf24; border-color: #f59e0b; }
.format-badge.format-imax-3d { background: #1a1a2e; color: #f59e0b; border-color: #d97706; }
.format-badge.format-4dx { background: #1a1a2e; color: #34d399; border-color: #10b981; }
.format-badge.format-screenx { background: #1a1a2e; color: #60a5fa; border-color: #3b82f6; }
.format-badge.format-d-box { background: #1a1a2e; color: #f472b6; border-color: #ec4899; }

.badge-a { background: #22c55e20; color: #86efac; border: 1px solid #22c55e40; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
.badge-b { background: #3b82f620; color: #93c5fd; border: 1px solid #3b82f640; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
.badge-c { background: #ef444420; color: #fca5a5; border: 1px solid #ef444440; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }

.language-badge {
    display: inline-block;
    padding: 1px 8px;
    border-radius: 10px;
    font-size: 8px;
    font-weight: 600;
    margin-left: 4px;
}
.language-badge.espanol { background: #22c55e30; color: #86efac; border: 1px solid #22c55e40; }
.language-badge.subtitulos { background: #3b82f630; color: #93c5fd; border: 1px solid #3b82f640; }

.promotion-tag {
    display: inline-block;
    padding: 1px 8px;
    border-radius: 10px;
    font-size: 8px;
    font-weight: 600;
    margin: 1px;
}
.promotion-tag.lunes { background: #22c55e30; color: #86efac; border: 1px solid #22c55e40; }
.promotion-tag.preventa { background: #f59e0b20; color: #fbbf24; border: 1px solid #f59e0b40; }

.promotion-checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    background: #1a1a2e;
    border: 1px solid #2a2a3e;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}
.promotion-checkbox:hover { border-color: #4f46e5; }
.promotion-checkbox input[type="checkbox"] {
    width: 18px; height: 18px;
    accent-color: #4f46e5;
    cursor: pointer;
}
.promotion-checkbox label {
    color: #e5e7eb;
    font-size: 13px;
    cursor: pointer;
}

.aisle-badge {
    background-color: #1a1a2e;
    border: 1px solid #374151;
    color: #4b5563;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 9px;
}

.movie-deleted { color: #f59e0b; font-style: italic; }
.showtime-inactive { opacity: 0.6; }
.showtime-inactive td { text-decoration: line-through; text-decoration-color: #6b7280; }

.movie-status-badge {
    display: inline-block;
    padding: 1px 8px;
    border-radius: 10px;
    font-size: 9px;
    font-weight: 600;
    margin-left: 4px;
}
.movie-status-badge.active { background: #22c55e30; color: #86efac; border: 1px solid #22c55e40; }
.movie-status-badge.inactive { background: #6b728030; color: #9ca3af; border: 1px solid #6b728040; }

.tickets-sold-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
}
.tickets-sold-badge.sold { background: #22c55e30; color: #86efac; border: 1px solid #22c55e40; }
.tickets-sold-badge.none { background: #6b728030; color: #9ca3af; border: 1px solid #6b728040; }

.history-summary {
    background: #1a1a2e;
    border: 1px solid #374151;
    border-radius: 8px;
    padding: 12px 16px;
}

/* ============================================
   BÚSQUEDA (UNIFICADA)
   ============================================ */
.search-box {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: flex-end;
    background: #1f2937;
    padding: 16px;
    border-radius: 12px;
    border: 1px solid #374151;
    margin-bottom: 20px;
}
.search-box .search-group { flex: 1; min-width: 250px; }
.search-box .search-group label {
    display: block;
    font-size: 0.7rem;
    color: #9ca3af;
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 600;
}
.search-box .search-group input {
    width: 100%;
    background: #111827;
    border: 1px solid #374151;
    border-radius: 8px;
    padding: 8px 12px;
    color: #e5e7eb;
    font-size: 0.9rem;
    transition: border-color 0.3s ease;
}
.search-box .search-group input:focus {
    outline: none;
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
}
.search-box .search-group input::placeholder { color: #6b7280; }
.search-box .search-actions { display: flex; gap: 8px; align-items: center; }
.search-box .search-actions button {
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
    display: flex;
    align-items: center;
    gap: 6px;
}
.search-box .search-actions .btn-search { background: #4f46e5; color: white; }
.search-box .search-actions .btn-search:hover { background: #6366f1; transform: translateY(-1px); }
.search-box .search-actions .btn-clear { background: #374151; color: #9ca3af; }
.search-box .search-actions .btn-clear:hover { background: #4b5563; color: #e5e7eb; }

@media (max-width: 640px) {
    .search-box { flex-direction: column; gap: 10px; padding: 12px; }
    .search-box .search-group { min-width: 100%; width: 100%; }
    .search-box .search-actions { width: 100%; }
    .search-box .search-actions button { flex: 1; justify-content: center; }
}

/* ============================================
   LOGO PREVIEW (Configuración)
   ============================================ */
.logo-preview {
    max-height: 60px;
    max-width: 200px;
    object-fit: contain;
    background: #1a1a2e;
    padding: 4px;
    border-radius: 4px;
}
.logo-preview-footer {
    max-height: 60px;
    max-width: 277px;
    object-fit: contain;
    background: #1a1a2e;
    padding: 4px;
    border-radius: 4px;
}

/* ============================================
   USUARIOS (ESTILO UNIFICADO)
   ============================================ */
.user-status {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}
.user-status.active { background: #22c55e20; color: #86efac; border: 1px solid #22c55e40; }
.user-status.blocked { background: #dc262620; color: #fca5a5; border: 1px solid #dc262640; }

.password-wrapper { position: relative; }
.password-wrapper input { padding-right: 44px; }
.password-toggle {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #6b7280;
    cursor: pointer;
    padding: 4px;
    font-size: 1rem;
}
.password-toggle:hover { color: #e5e7eb; }

/* ============================================
   MODAL
   ============================================ */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(8px);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.modal-overlay.active { display: flex; }
.modal-box {
    background: #1a1a2e;
    border: 2px solid #22c55e;
    border-radius: 16px;
    padding: 40px;
    max-width: 440px;
    width: 100%;
    text-align: center;
    animation: modalFadeIn 0.4s ease;
    box-shadow: 0 20px 60px rgba(34, 197, 94, 0.15);
}
.modal-box .modal-icon { font-size: 4rem; color: #22c55e; margin-bottom: 16px; display: block; }
.modal-box .modal-title { color: #e5e7eb; font-size: 1.4rem; font-weight: 700; margin-bottom: 10px; }
.modal-box .modal-message { color: #9ca3af; font-size: 0.95rem; line-height: 1.6; margin-bottom: 24px; }
.modal-box .modal-btn {
    background: #22c55e;
    color: white;
    padding: 10px 32px;
    border-radius: 8px;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1rem;
}
.modal-box .modal-btn:hover { background: #16a34a; transform: scale(1.05); }

@keyframes modalFadeIn {
    from { opacity: 0; transform: scale(0.9) translateY(20px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

/* ============================================
   RESPONSIVE GENERAL
   ============================================ */
@media (max-width: 640px) {
    .modal-box { padding: 28px 20px; margin: 0 12px; }
    .modal-box .modal-icon { font-size: 3rem; }
    .modal-box .modal-title { font-size: 1.2rem; }
    .modal-box .modal-message { font-size: 0.85rem; }
}
@media (max-width: 480px) {
    .search-box .search-actions button { font-size: 0.75rem; padding: 6px 12px; }
    .modal-box { padding: 20px 16px; }
    .modal-box .modal-icon { font-size: 2.5rem; margin-bottom: 10px; }
}

/* ============================================
   CLASIFICACIÓN SELECT
   ============================================ */
.classification-select { background: #1a1a2e; border: 1px solid #2a2a3e; color: #e5e7eb; }
.classification-select:focus { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15); }

/* ============================================
   PULSING ANIMATION
   ============================================ */
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
.pulsing { animation: pulse 1.5s ease-in-out infinite; }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen p-4 md:p-8">
<div class="max-w-7xl mx-auto">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-center mb-8 gap-4 bg-gray-800 p-4 rounded-lg shadow-md border border-gray-700">
        <div>
            <h1 class="text-2xl font-bold text-indigo-400 flex items-center gap-2">🎬 Panel de Control</h1>
            <p class="text-xs text-gray-400 mt-1">Administrador: <?= htmlspecialchars($_SESSION['user_name']) ?></p>
        </div>
        <div class="flex gap-3">
            <a href="index.php" class="bg-gray-700 hover:bg-gray-600 px-4 py-2 rounded-lg text-sm font-semibold transition-colors">Ver Cartelera</a>
            <a href="logout.php" class="bg-red-600/20 hover:bg-red-600/40 text-red-400 px-4 py-2 rounded-lg text-sm font-semibold transition-colors">Cerrar Sesión</a>
        </div>
    </div>

    <!-- Mensajes -->
    <?php if($msg): ?>
        <div class="bg-green-600/20 text-green-400 p-3 rounded-lg mb-6 font-medium border border-green-500/30"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="bg-red-600/20 text-red-400 p-3 rounded-lg mb-6 font-medium border border-red-500/30"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Tabs de Navegación -->
    <div class="flex flex-wrap gap-2 mb-6">
        <a href="?tab=movies" class="px-4 py-2 rounded-lg transition-colors <?= $activeTab === 'movies' ? 'tab-active' : 'tab-inactive' ?>">🎬 Películas</a>
        <a href="?tab=showtimes" class="px-4 py-2 rounded-lg transition-colors <?= $activeTab === 'showtimes' ? 'tab-active' : 'tab-inactive' ?>">🕐 Horarios</a>
        <a href="?tab=rooms" class="px-4 py-2 rounded-lg transition-colors <?= $activeTab === 'rooms' ? 'tab-active' : 'tab-inactive' ?>">🏠 Salas</a>
        <a href="?tab=users" class="px-4 py-2 rounded-lg transition-colors <?= $activeTab === 'users' ? 'tab-active' : 'tab-inactive' ?>">👥 Usuarios</a>
        <a href="?tab=food" class="px-4 py-2 rounded-lg transition-colors <?= $activeTab === 'food' ? 'tab-active' : 'tab-inactive' ?>">🍿 Comida</a>
        <a href="?tab=history" class="px-4 py-2 rounded-lg transition-colors <?= $activeTab === 'history' ? 'tab-active' : 'tab-inactive' ?>">📊 Historial</a>
        <a href="?tab=config" class="px-4 py-2 rounded-lg transition-colors <?= $activeTab === 'config' ? 'tab-active' : 'tab-inactive' ?>">⚙️ Configuración</a>
    </div>

    <!-- ============================================ -->
    <!-- TAB: PELÍCULAS                               -->
    <!-- ============================================ -->
    <?php if($activeTab === 'movies'): ?>
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-700 mb-8">
            <h2 class="text-lg font-bold mb-4 text-indigo-300">
                <?= $edit_movie ? '✏️ Editar Película' : '➕ Agregar Película' ?>
            </h2>

            <div class="mb-4 p-3 rounded-lg bg-blue-600/10 border border-blue-500/30 text-blue-300 text-sm">
                <p>ℹ️ Al colocar el <strong>título</strong> y opcionalmente el <strong>año</strong>, el sistema buscará automáticamente la información desde TMDb.</p>
                <p class="mt-1 text-yellow-300">🔒 Las películas nuevas se registran como <strong>OCULTAS</strong> por defecto. Debes activarlas manualmente.</p>
                <p class="mt-1 text-red-300">⚠️ Los campos <strong>Clasificación</strong> y <strong>URL del Tráiler</strong> son obligatorios.</p>
                <p class="mt-1 text-green-300">💡 Puedes buscar por <strong>nombre</strong> (Ej: Superman) o <strong>nombre y año</strong> (Ej: Superman, 1979) para resultados más precisos.</p>
            </div>

            <form action="" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <?php if($edit_movie): ?>
                    <input type="hidden" name="movie_id" value="<?= htmlspecialchars($edit_movie['id']) ?>">
                    <input type="hidden" name="edit_movie" value="1">
                <?php else: ?>
                    <input type="hidden" name="add_movie" value="1">
                <?php endif; ?>

                <div class="md:col-span-2">
                    <label class="block text-sm text-gray-400 mb-1">Título *</label>
                    <input type="text" name="title" required maxlength="255" value="<?= $edit_movie ? htmlspecialchars($edit_movie['title']) : '' ?>"
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">URL del Póster</label>
                    <input type="url" name="poster_url" value="<?= $edit_movie ? htmlspecialchars($edit_movie['poster_url'] ?? '') : '' ?>"
                           placeholder="https://..."
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">URL Fondo / Banner</label>
                    <input type="url" name="banner_url" value="<?= $edit_movie ? htmlspecialchars($edit_movie['banner_url'] ?? '') : '' ?>"
                           placeholder="https://..."
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Duración (minutos)</label>
                    <input type="number" name="duration" min="0" max="999" value="<?= $edit_movie ? htmlspecialchars($edit_movie['duration']) : '' ?>"
                           placeholder="Ej: 120"
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Género</label>
                    <input type="text" name="genre" value="<?= $edit_movie ? htmlspecialchars($edit_movie['genre'] ?? '') : '' ?>" placeholder="Ej: Acción, Ciencia Ficción"
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Año de Estreno</label>
                    <input type="number" name="year" min="1900" max="<?= date('Y') + 2 ?>"
                           value="<?= $edit_movie ? htmlspecialchars($edit_movie['year']) : '' ?>"
                           placeholder="Ej: 2024"
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Clasificación *</label>
                    <select name="classification" required class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Seleccionar</option>
                        <option value="A (Todo público)" <?= ($edit_movie && $edit_movie['classification'] == 'A (Todo público)') ? 'selected' : '' ?>>A (Todo público)</option>
                        <option value="B (Mayores de 12)" <?= ($edit_movie && $edit_movie['classification'] == 'B (Mayores de 12)') ? 'selected' : '' ?>>B (Mayores de 12)</option>
                        <option value="C (Mayores de 18)" <?= ($edit_movie && $edit_movie['classification'] == 'C (Mayores de 18)') ? 'selected' : '' ?>>C (Mayores de 18)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Director</label>
                    <input type="text" name="director" value="<?= $edit_movie ? htmlspecialchars($edit_movie['director'] ?? '') : '' ?>" placeholder="Director de la película"
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm text-gray-400 mb-1">Reparto Principal</label>
                    <input type="text" name="cast_members" value="<?= $edit_movie ? htmlspecialchars($edit_movie['cast_members'] ?? '') : '' ?>" placeholder="Ej: Actor 1, Actor 2, Actor 3"
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">País de Origen</label>
                    <select name="country_id" class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Seleccionar País</option>
                        <?php foreach($countries as $country): ?>
                            <option value="<?= htmlspecialchars($country['id']) ?>" <?= ($edit_movie && isset($edit_movie['country_id']) && $edit_movie['country_id'] == $country['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($country['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">URL del Tráiler (YouTube) *</label>
                    <input type="url" name="trailer_url" required value="<?= $edit_movie ? htmlspecialchars($edit_movie['trailer_url']) : '' ?>"
                           placeholder="https://www.youtube.com/watch?v=XXXXXX"
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm text-gray-400 mb-1">Sinopsis / Descripción</label>
                    <textarea name="description" rows="4" maxlength="5000" placeholder="Sinopsis detallada..."
                              class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500"><?= $edit_movie ? htmlspecialchars($edit_movie['description'] ?? '') : '' ?></textarea>
                </div>

                <button type="submit" class="md:col-span-2 bg-indigo-600 hover:bg-indigo-700 p-3 rounded-lg font-bold transition-colors mt-2 shadow-md">
                    <?= $edit_movie ? 'Actualizar Película' : 'Guardar Película (Oculta)' ?>
                </button>

                <?php if($edit_movie): ?>
                    <a href="?tab=movies" class="md:col-span-2 text-center text-gray-400 hover:text-white text-sm">Cancelar edición</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Lista de Películas con buscador -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-700">
            <h2 class="text-lg font-bold mb-4 text-indigo-300">📋 Todas las Películas</h2>

            <div class="search-box">
                <div class="search-group">
                    <label><i class="fas fa-search mr-1"></i> Buscar por Título</label>
                    <input type="text" id="searchTitle" placeholder="Ej: Spider-Man, Avatar..." value="<?= htmlspecialchars($search_title) ?>">
                </div>
                <div class="search-actions">
                    <button class="btn-search" onclick="applyFilters('movies')"><i class="fas fa-search"></i> Buscar</button>
                    <button class="btn-clear" onclick="clearFilters('movies')"><i class="fas fa-times"></i> Limpiar</button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                    <tr class="border-b border-gray-700 text-gray-400 text-sm">
                        <th class="pb-3 font-semibold">Póster</th>
                        <th class="pb-3 font-semibold">Título</th>
                        <th class="pb-3 font-semibold">Año</th>
                        <th class="pb-3 font-semibold">Duración</th>
                        <th class="pb-3 font-semibold">Género</th>
                        <th class="pb-3 font-semibold">Clasificación</th>
                        <th class="pb-3 font-semibold text-center">Estado</th>
                        <th class="pb-3 font-semibold text-center">Acciones</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700/50 text-sm" id="moviesTableBody">
                    <?php foreach($movies as $m): ?>
                        <tr>
                            <td class="py-3">
                                <img src="<?= htmlspecialchars($m['poster_url']) ?>" alt="<?= htmlspecialchars($m['title']) ?>"
                                     class="w-10 h-14 object-cover rounded bg-gray-700 shadow"
                                     onerror="this.style.display='none'">
                            </td>
                            <td class="py-3 font-medium text-gray-200"><?= htmlspecialchars($m['title']) ?></td>
                            <td class="py-3 text-gray-400"><?= htmlspecialchars($m['year'] ?? '-') ?></td>
                            <td class="py-3 text-gray-400"><?= $m['duration'] ? htmlspecialchars($m['duration']) . ' min' : '-' ?></td>
                            <td class="py-3 text-gray-400"><?= htmlspecialchars($m['genre'] ?? '-') ?></td>
                            <td class="py-3">
                                <?php if($m['classification']): ?>
                                    <?php if(strpos($m['classification'], 'A') !== false): ?>
                                        <span class="badge-a"><?= htmlspecialchars($m['classification']) ?></span>
                                    <?php elseif(strpos($m['classification'], 'B') !== false): ?>
                                        <span class="badge-b"><?= htmlspecialchars($m['classification']) ?></span>
                                    <?php elseif(strpos($m['classification'], 'C') !== false): ?>
                                        <span class="badge-c"><?= htmlspecialchars($m['classification']) ?></span>
                                    <?php else: ?>
                                        <span class="badge-b"><?= htmlspecialchars($m['classification']) ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-gray-500 text-xs">No definida</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 text-center">
                                <?php if($m['is_active']): ?>
                                    <span class="bg-green-500/20 text-green-400 text-xs px-2 py-0.5 rounded-full font-bold border border-green-500/30">Activa</span>
                                <?php else: ?>
                                    <span class="bg-gray-500/20 text-gray-400 text-xs px-2 py-0.5 rounded-full font-bold border border-gray-500/30">Oculta</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 text-center">
                                <div class="flex justify-center gap-2 flex-wrap">
                                    <a href="?tab=movies&edit_movie_id=<?= htmlspecialchars($m['id']) ?>&csrf_token=<?= htmlspecialchars($csrf_token) ?>"
                                       class="text-xs px-2 py-1 rounded bg-blue-600/20 hover:bg-blue-600/40 text-blue-400 transition-colors">Editar</a>
                                    <a href="?update_movie=<?= htmlspecialchars($m['id']) ?>&tab=movies&csrf_token=<?= htmlspecialchars($csrf_token) ?>"
                                       class="text-xs px-2 py-1 rounded bg-indigo-600/20 hover:bg-indigo-600/40 text-indigo-400 transition-colors"
                                       onclick="return confirm('¿Actualizar los datos de la película desde TMDb?')">
                                        <i class="fas fa-sync-alt mr-1"></i> Actualizar
                                    </a>
                                    <a href="?toggle_movie=<?= htmlspecialchars($m['id']) ?>&tab=movies&csrf_token=<?= htmlspecialchars($csrf_token) ?>"
                                       class="text-xs px-2 py-1 rounded bg-gray-700 hover:bg-gray-600 transition-colors"
                                       onclick="return confirm('¿Cambiar estado de esta película?')">
                                        <?= $m['is_active'] ? 'Ocultar' : 'Mostrar' ?>
                                    </a>
                                    <a href="?delete_movie=<?= htmlspecialchars($m['id']) ?>&tab=movies&csrf_token=<?= htmlspecialchars($csrf_token) ?>"
                                       class="text-xs px-2 py-1 rounded bg-red-600/20 hover:bg-red-600/40 text-red-400 transition-colors"
                                       onclick="return confirm('¿Eliminar esta película permanentemente? Se eliminarán también todos los horarios y boletos asociados.')">
                                        Eliminar
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if(empty($movies)): ?>
                    <div class="text-center py-8 text-gray-400">
                        <p class="text-4xl mb-2">🎬</p>
                        <p>No se encontraron películas<?= !empty($search_title) ? ' con el filtro aplicado' : '' ?>.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- TAB: HORARIOS                                -->
    <!-- ============================================ -->
    <?php if($activeTab === 'showtimes'): ?>
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-700 mb-8">
            <h2 class="text-lg font-bold mb-4 text-indigo-300">
                <?= $edit_showtime ? '✏️ Editar Horario' : '➕ Agregar Horario' ?>
            </h2>

            <div class="mb-4 p-3 rounded-lg bg-blue-600/10 border border-blue-500/30 text-blue-300 text-sm">
                <p class="font-semibold">🧹 Tiempo de limpieza:</p>
                <p>El sistema considera un tiempo de <strong>15 minutos</strong> entre funciones para limpieza de la sala.</p>
                <p class="mt-1 text-indigo-300">📽️ Selecciona el <strong>Formato</strong> de proyección para esta función.</p>
            </div>

            <div id="conflictChecker" class="mb-4 p-3 rounded-lg border text-sm conflict-checking">
                <p class="font-semibold">🔍 Verificación de conflictos en tiempo real:</p>
                <p id="conflictStatus">Selecciona película, sala, fecha y hora para verificar automáticamente si hay conflictos</p>
            </div>

            <?php
            // ✅ CORREGIDO: Variables para controlar el estado de los campos de precio
            $child_checked = $edit_showtime && !empty($edit_showtime['enable_child_price']);
            $senior_checked = $edit_showtime && !empty($edit_showtime['enable_senior_price']);
            ?>

            <form action="" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4" id="showtimeForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <?php if($edit_showtime): ?>
                    <input type="hidden" name="showtime_id" id="showtimeIdInput" value="<?= htmlspecialchars($edit_showtime['id']) ?>">
                    <input type="hidden" name="edit_showtime" value="1">
                <?php else: ?>
                    <input type="hidden" name="add_showtime" value="1">
                    <input type="hidden" name="showtime_id" id="showtimeIdInput" value="0">
                <?php endif; ?>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Película *</label>
                    <select name="movie_id" required class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500" id="movieSelect">
                        <option value="">Seleccionar</option>
                        <?php
                        $movies_ordered = $pdo->query("SELECT * FROM movies WHERE is_active = 1 ORDER BY title ASC")->fetchAll();
                        foreach($movies_ordered as $m):
                        ?>
                            <option value="<?= htmlspecialchars($m['id']) ?>"
                                    data-duration="<?= htmlspecialchars($m['duration']) ?>"
                                <?= ($edit_showtime && $edit_showtime['movie_id'] == $m['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['title']) ?> (<?= htmlspecialchars($m['duration']) ?> min)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Sala *</label>
                    <select name="room_id" required class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500" id="roomSelect">
                        <option value="">Seleccionar</option>
                        <?php foreach($rooms as $r): ?>
                            <option value="<?= htmlspecialchars($r['id']) ?>" <?= ($edit_showtime && $edit_showtime['room_id'] == $r['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r['name']) ?> (Cap: <?= htmlspecialchars($r['capacity']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Fecha *</label>
                    <input type="date" name="show_date" required min="<?= date('Y-m-d') ?>" value="<?= $edit_showtime ? htmlspecialchars($edit_showtime['show_date']) : '' ?>"
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500" id="dateInput">
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Hora *</label>
                    <input type="time" name="show_time" required value="<?= $edit_showtime ? htmlspecialchars($edit_showtime['show_time']) : '' ?>"
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500" id="timeInput">
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Idioma *</label>
                    <select name="language" class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="español" <?= ($edit_showtime && ($edit_showtime['language'] ?? 'español') == 'español') || !$edit_showtime ? 'selected' : '' ?>>Español</option>
                        <option value="subtitulos" <?= ($edit_showtime && ($edit_showtime['language'] ?? '') == 'subtitulos') ? 'selected' : '' ?>>Subtítulos</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Formato *</label>
                    <select name="format" required class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Seleccionar Formato</option>
                        <?php foreach($formatos as $fmt): ?>
                            <option value="<?= htmlspecialchars($fmt) ?>" <?= ($edit_showtime && isset($edit_showtime['format']) && $edit_showtime['format'] == $fmt) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($fmt) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="md:col-span-3">
                    <label class="block text-sm text-gray-400 mb-2">💰 Precios</label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Adulto *</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-semibold"><?= htmlspecialchars($siteConfig['currency_symbol'] ?? '$') ?></span>
                                <input type="number" step="0.01" min="0.01" name="price_adult" required
                                       value="<?= $edit_showtime ? htmlspecialchars($edit_showtime['price_adult'] ?? $edit_showtime['price']) : '' ?>"
                                       class="w-full bg-gray-700 p-2.5 pl-7 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                       placeholder="0.00">
                            </div>
                        </div>

                        <!-- CORREGIDO: Campo Niño con estado condicional -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-sm text-gray-400">Niño</label>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="enable_child_price" id="enable_child_price" value="1"
                                        <?= $child_checked ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-semibold"><?= htmlspecialchars($siteConfig['currency_symbol'] ?? '$') ?></span>
                                <input type="number" step="0.01" min="0" name="price_child" id="price_child"
                                       value="<?= $edit_showtime ? htmlspecialchars($edit_showtime['price_child'] ?? '0.00') : '0.00' ?>"
                                       class="w-full bg-gray-700 p-2.5 pl-7 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 <?= $child_checked ? '' : 'price-input-disabled' ?>"
                                       placeholder="0.00"
                                    <?= $child_checked ? '' : 'disabled' ?>>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Menores de 12 años</p>
                        </div>

                        <!-- CORREGIDO: Campo Tercera Edad con estado condicional -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-sm text-gray-400">Tercera Edad</label>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="enable_senior_price" id="enable_senior_price" value="1"
                                        <?= $senior_checked ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-semibold"><?= htmlspecialchars($siteConfig['currency_symbol'] ?? '$') ?></span>
                                <input type="number" step="0.01" min="0" name="price_senior" id="price_senior"
                                       value="<?= $edit_showtime ? htmlspecialchars($edit_showtime['price_senior'] ?? '0.00') : '0.00' ?>"
                                       class="w-full bg-gray-700 p-2.5 pl-7 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 <?= $senior_checked ? '' : 'price-input-disabled' ?>"
                                       placeholder="0.00"
                                    <?= $senior_checked ? '' : 'disabled' ?>>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Mayores de 60 años</p>
                        </div>
                    </div>
                </div>

                <div class="md:col-span-3">
                    <label class="block text-sm text-gray-400 mb-2">🎯 Promociones</label>
                    <div class="flex flex-wrap gap-3">
                        <div class="promotion-checkbox">
                            <input type="checkbox" name="half_price_monday" id="half_price_monday"
                                <?= ($edit_showtime && in_array('lunes_mitad', $edit_showtime['promotions_array'] ?? [])) ? 'checked' : '' ?>>
                            <label for="half_price_monday">🌙 Lunes ½ Precio</label>
                        </div>
                        <div class="promotion-checkbox">
                            <input type="checkbox" name="preventa" id="preventa"
                                <?= ($edit_showtime && in_array('preventa', $edit_showtime['promotions_array'] ?? [])) ? 'checked' : '' ?>>
                            <label for="preventa">🎫 Preventa</label>
                        </div>
                    </div>
                </div>

                <button type="submit" class="md:col-span-3 bg-indigo-600 hover:bg-indigo-700 p-3 rounded-lg font-bold transition-colors mt-2 shadow-md" id="submitBtn">
                    <?= $edit_showtime ? 'Actualizar Horario' : 'Guardar Horario' ?>
                </button>

                <?php if($edit_showtime): ?>
                    <a href="?tab=showtimes" class="md:col-span-3 text-center text-gray-400 hover:text-white text-sm">Cancelar edición</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Lista de Horarios con buscador -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-700">
            <h2 class="text-lg font-bold mb-4 text-indigo-300">🕐 Todos los Horarios</h2>

            <div class="search-box">
                <div class="search-group">
                    <label><i class="fas fa-search mr-1"></i> Buscar por Película</label>
                    <input type="text" id="searchShowtime" placeholder="Ej: Spider-Man, Avatar..." value="<?= htmlspecialchars($_GET['search_showtime'] ?? '') ?>">
                </div>
                <div class="search-actions">
                    <button class="btn-search" onclick="applyFilters('showtimes')"><i class="fas fa-search"></i> Buscar</button>
                    <button class="btn-clear" onclick="clearFilters('showtimes')"><i class="fas fa-times"></i> Limpiar</button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                    <tr class="border-b border-gray-700 text-gray-400 text-sm">
                        <th class="pb-3 font-semibold">Película</th>
                        <th class="pb-3 font-semibold">Sala</th>
                        <th class="pb-3 font-semibold">Fecha</th>
                        <th class="pb-3 font-semibold">Hora</th>
                        <th class="pb-3 font-semibold">Formato</th>
                        <th class="pb-3 font-semibold">Adulto</th>
                        <th class="pb-3 font-semibold">Niño</th>
                        <th class="pb-3 font-semibold">Abuelo</th>
                        <th class="pb-3 font-semibold">Idioma</th>
                        <th class="pb-3 font-semibold">Promociones</th>
                        <th class="pb-3 font-semibold text-center">Estado</th>
                        <th class="pb-3 font-semibold text-center">Acciones</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700/50 text-sm">
                    <?php foreach($showtimes as $s):
                        $promo_labels = [];
                        $promotions = $s['promotions'] ? explode(',', $s['promotions']) : [];
                        if(in_array('lunes_mitad', $promotions)) $promo_labels[] = 'Lunes ½ Precio';
                        if(in_array('preventa', $promotions)) $promo_labels[] = 'Preventa';

                        $movie_exists = $s['movie_title'] !== null;
                        $is_inactive = $s['is_active'] == 0;
                        $language = $s['language'] ?? 'español';
                        $lang_label = $language == 'español' ? 'Español' : 'Subtítulos';
                        $lang_class = $language == 'español' ? 'espanol' : 'subtitulos';

                        $price_adult = $s['price_adult'] ?? $s['price'] ?? 0;
                        $price_child = $s['price_child'] ?? 0;
                        $price_senior = $s['price_senior'] ?? 0;
                        $enable_child = $s['enable_child_price'] ?? 1;
                        $enable_senior = $s['enable_senior_price'] ?? 1;
                        $format = $s['format'] ?? '2D';

                        $formatClass = 'format-2d';
                        if (!empty($format)) {
                            $formatLower = strtolower($format);
                            $formatClass = 'format-' . str_replace(' ', '-', $formatLower);
                        }
                    ?>
                        <tr class="<?= $is_inactive ? 'showtime-inactive' : '' ?>">
                            <td class="py-3 font-medium <?= $movie_exists ? 'text-gray-200' : 'movie-deleted' ?>">
                                <?= htmlspecialchars($s['movie_title'] ?? 'Película eliminada') ?>
                                <?php if($is_inactive): ?><span class="text-xs text-gray-500 ml-1">(Inactiva)</span><?php endif; ?>
                                <?php if(!$movie_exists): ?><span class="text-xs text-gray-500 ml-1">(Eliminada)</span><?php endif; ?>
                            </td>
                            <td class="py-3 text-gray-400"><?= htmlspecialchars($s['room_name']) ?></td>
                            <td class="py-3 text-gray-400"><?= formatDateShort($s['show_date']) ?></td>
                            <td class="py-3 text-indigo-300 font-semibold time-display"><?= formatTimeVenezuela($s['show_time']) ?></td>
                            <td class="py-3"><span class="format-badge <?= $formatClass ?>"><?= htmlspecialchars($format) ?></span></td>
                            <td class="py-3 text-green-400 font-semibold"><?= formatCurrency($price_adult, $siteConfig) ?></td>
                            <td class="py-3 <?= $enable_child ? 'text-green-400' : 'text-gray-500' ?> font-semibold">
                                <?= $enable_child ? formatCurrency($price_child, $siteConfig) : '—' ?>
                            </td>
                            <td class="py-3 <?= $enable_senior ? 'text-green-400' : 'text-gray-500' ?> font-semibold">
                                <?= $enable_senior ? formatCurrency($price_senior, $siteConfig) : '—' ?>
                            </td>
                            <td class="py-3"><span class="language-badge <?= $lang_class ?>"><?= $lang_label ?></span></td>
                            <td class="py-3">
                                <?php foreach($promo_labels as $label): ?>
                                    <span class="promotion-tag <?= strpos($label, 'Lunes') !== false ? 'lunes' : 'preventa' ?>"><?= $label ?></span>
                                <?php endforeach; ?>
                                <?php if(empty($promo_labels)): ?><span class="text-gray-500 text-xs">—</span><?php endif; ?>
                            </td>
                            <td class="py-3 text-center">
                                <?php if($s['is_active']): ?>
                                    <span class="bg-green-500/20 text-green-400 text-xs px-2 py-0.5 rounded-full font-bold border border-green-500/30">Activo</span>
                                <?php else: ?>
                                    <span class="bg-gray-500/20 text-gray-400 text-xs px-2 py-0.5 rounded-full font-bold border border-gray-500/30">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 text-center">
                                <div class="flex justify-center gap-2 flex-wrap">
                                    <?php if($s['is_active']): ?>
                                        <a href="?tab=showtimes&edit_showtime_id=<?= htmlspecialchars($s['id']) ?>&csrf_token=<?= htmlspecialchars($csrf_token) ?>"
                                           class="text-xs px-2 py-1 rounded bg-blue-600/20 hover:bg-blue-600/40 text-blue-400 transition-colors">Editar</a>
                                    <?php endif; ?>
                                    <a href="?toggle_showtime=<?= htmlspecialchars($s['id']) ?>&tab=showtimes&csrf_token=<?= htmlspecialchars($csrf_token) ?>"
                                       class="text-xs px-2 py-1 rounded bg-gray-700 hover:bg-gray-600 transition-colors"
                                       onclick="return confirm('¿Cambiar estado de este horario?')">
                                        <?= $s['is_active'] ? 'Ocultar' : 'Mostrar' ?>
                                    </a>
                                    <a href="?delete_showtime=<?= htmlspecialchars($s['id']) ?>&tab=showtimes&csrf_token=<?= htmlspecialchars($csrf_token) ?>"
                                       class="text-xs px-2 py-1 rounded bg-red-600/20 hover:bg-red-600/40 text-red-400 transition-colors"
                                       onclick="return confirm('¿Eliminar este horario?')">Eliminar</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- TAB: SALAS                                   -->
    <!-- ============================================ -->
    <?php if($activeTab === 'rooms'): ?>
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-lg font-bold text-indigo-300">🏠 Gestión de Salas</h2>
                <a href="room_builder.php" class="bg-indigo-600 hover:bg-indigo-700 px-4 py-2 rounded-lg text-sm font-bold transition-colors flex items-center gap-2">
                    🎨 <span>Crear Nueva Sala</span>
                </a>
            </div>

            <div class="mb-4 p-3 rounded-lg bg-blue-600/10 border border-blue-500/30 text-blue-300 text-sm">
                <p>💡 Las salas se crean y editan desde el <strong>Constructor Visual</strong>.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                    <tr class="border-b border-gray-700 text-gray-400 text-sm">
                        <th class="pb-3 font-semibold">Nombre</th>
                        <th class="pb-3 font-semibold">Capacidad</th>
                        <th class="pb-3 font-semibold">Distribución</th>
                        <th class="pb-3 font-semibold">Configuración</th>
                        <th class="pb-3 font-semibold text-center">Estado</th>
                        <th class="pb-3 font-semibold text-center">Acciones</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700/50 text-sm">
                    <?php foreach($rooms as $r):
                        $layout = $r['seat_layout'] ? json_decode($r['seat_layout'], true) : null;
                        $blockedSeats = $layout['blockedSeats'] ?? [];
                        $hasBlocked = count($blockedSeats) > 0;
                    ?>
                        <tr>
                            <td class="py-3 font-medium text-gray-200"><?= htmlspecialchars($r['name']) ?></td>
                            <td class="py-3 text-gray-400"><?= htmlspecialchars($r['capacity']) ?></td>
                            <td class="py-3 text-gray-400">
                                <?php if($layout): ?>
                                    <span class="text-xs"><?= count($layout['rows'] ?? []) ?> filas × <?= $layout['seatsPerRow'] ?? 0 ?> asientos</span>
                                    <br><span class="text-xs text-gray-500">Total: <?= $layout['totalSeats'] ?? 0 ?></span>
                                <?php else: ?>
                                    <span class="text-gray-500">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3">
                                <?php if($hasBlocked): ?>
                                    <span class="aisle-badge">🚫 <?= count($blockedSeats) ?> bloqueados</span>
                                <?php else: ?>
                                    <span class="text-gray-500 text-xs">Sin pasillos</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 text-center">
                                <?php if($r['is_active']): ?>
                                    <span class="bg-green-500/20 text-green-400 text-xs px-2 py-0.5 rounded-full font-bold border border-green-500/30">Activa</span>
                                <?php else: ?>
                                    <span class="bg-gray-500/20 text-gray-400 text-xs px-2 py-0.5 rounded-full font-bold border border-gray-500/30">Inactiva</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 text-center">
                                <div class="flex justify-center gap-2 flex-wrap">
                                    <a href="room_builder.php?room_id=<?= htmlspecialchars($r['id']) ?>"
                                       class="text-xs px-2 py-1 rounded bg-indigo-600/20 hover:bg-indigo-600/40 text-indigo-400 transition-colors flex items-center gap-1">
                                        🛠️ Diseñar
                                    </a>
                                    <a href="?toggle_room=<?= htmlspecialchars($r['id']) ?>&tab=rooms&csrf_token=<?= htmlspecialchars($csrf_token) ?>"
                                       class="text-xs px-2 py-1 rounded bg-gray-700 hover:bg-gray-600 transition-colors"
                                       onclick="return confirm('¿Cambiar estado de esta sala?')">
                                        <?= $r['is_active'] ? 'Ocultar' : 'Mostrar' ?>
                                    </a>
                                    <a href="?delete_room=<?= htmlspecialchars($r['id']) ?>&tab=rooms&csrf_token=<?= htmlspecialchars($csrf_token) ?>"
                                       class="text-xs px-2 py-1 rounded bg-red-600/20 hover:bg-red-600/40 text-red-400 transition-colors"
                                       onclick="return confirm('¿Eliminar esta sala permanentemente?')">Eliminar</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- TAB: USUARIOS (ESTILO UNIFICADO)             -->
    <!-- ============================================ -->
    <?php if($activeTab === 'users'): ?>
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-700 mb-8">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                <h2 class="text-lg font-bold text-indigo-300 flex items-center gap-2">
                    <i class="fas fa-users"></i>
                    <?= $edit_user ? '✏️ Editar Usuario' : '➕ Registrar Nuevo Usuario' ?>
                </h2>
                <?php if($edit_user): ?>
                    <a href="?tab=users" class="bg-gray-700 hover:bg-gray-600 px-4 py-2 rounded-lg text-sm font-semibold transition-colors flex items-center gap-2">
                        <i class="fas fa-arrow-left"></i> Volver a la lista
                    </a>
                <?php endif; ?>
            </div>

            <div class="bg-gray-800/50 p-6 rounded-lg border border-gray-700 mb-8">
                <form action="admin.php?tab=users" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <?php if($edit_user): ?>
                        <input type="hidden" name="edit_user" value="1">
                        <input type="hidden" name="user_id" value="<?= htmlspecialchars($edit_user['id']) ?>">
                    <?php else: ?>
                        <input type="hidden" name="add_user" value="1">
                    <?php endif; ?>

                    <div class="md:col-span-2">
                        <label class="block text-sm text-gray-400 mb-1">Nombres y Apellidos <span class="text-red-400">*</span></label>
                        <input type="text" name="user_name" required
                               value="<?= $edit_user ? htmlspecialchars($edit_user['name']) : '' ?>"
                               placeholder="Ej: Juan Pérez" maxlength="100"
                               class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm text-gray-400 mb-1">Correo Electrónico <span class="text-red-400">*</span></label>
                        <input type="email" name="user_email" required
                               value="<?= $edit_user ? htmlspecialchars($edit_user['email']) : '' ?>"
                               placeholder="ejemplo@email.com" maxlength="100"
                               class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Cédula de Identidad</label>
                        <div class="flex gap-2">
                            <select name="cedula_type" class="bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500" style="flex: 0 0 80px;">
                                <option value="V" <?= ($edit_user && $edit_user['cedula_type'] == 'V') ? 'selected' : '' ?>>V</option>
                                <option value="E" <?= ($edit_user && $edit_user['cedula_type'] == 'E') ? 'selected' : '' ?>>E</option>
                                <option value="P" <?= ($edit_user && $edit_user['cedula_type'] == 'P') ? 'selected' : '' ?>>P</option>
                                <option value="J" <?= ($edit_user && $edit_user['cedula_type'] == 'J') ? 'selected' : '' ?>>J</option>
                            </select>
                            <input type="text" name="cedula_number"
                                   value="<?= $edit_user ? htmlspecialchars((string)$edit_user['cedula_number']) : '' ?>"
                                   placeholder="Número de cédula" pattern="[0-9]*" inputmode="numeric"
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                   maxlength="20"
                                   class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Solo números</p>
                    </div>

                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Teléfono Móvil</label>
                        <div class="flex gap-2">
                            <select name="phone_prefix" class="bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500" style="flex: 0 0 90px;">
                                <option value="412" <?= ($edit_user && $edit_user['phone_prefix'] == '412') ? 'selected' : '' ?>>0412</option>
                                <option value="414" <?= ($edit_user && $edit_user['phone_prefix'] == '414') ? 'selected' : '' ?>>0414</option>
                                <option value="416" <?= ($edit_user && $edit_user['phone_prefix'] == '416') ? 'selected' : '' ?>>0416</option>
                                <option value="424" <?= ($edit_user && $edit_user['phone_prefix'] == '424') ? 'selected' : '' ?>>0424</option>
                                <option value="426" <?= ($edit_user && $edit_user['phone_prefix'] == '426') ? 'selected' : '' ?>>0426</option>
                            </select>
                            <input type="text" name="phone_number"
                                   value="<?= $edit_user ? htmlspecialchars((string)$edit_user['phone_number']) : '' ?>"
                                   placeholder="Número de teléfono" pattern="[0-9]*" inputmode="numeric"
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                   maxlength="20"
                                   class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Solo números</p>
                    </div>

                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Fecha de Nacimiento</label>
                        <input type="date" name="birth_date"
                               value="<?= $edit_user ? htmlspecialchars($edit_user['birth_date']) : '' ?>"
                               max="<?= date('Y-m-d', strtotime('-12 years')) ?>"
                               class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Contraseña <?= !$edit_user ? '<span class="text-red-400">*</span>' : '' ?></label>
                        <div class="password-wrapper relative">
                            <input type="password" name="user_password" id="userPassword"
                                <?= !$edit_user ? 'required' : '' ?>
                                   placeholder="<?= $edit_user ? 'Nueva contraseña (opcional)' : 'Mín. 8 caracteres, mayúscula y número' ?>"
                                   minlength="8" maxlength="255"
                                   class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 pr-10">
                            <button type="button" class="password-toggle absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white" onclick="togglePasswordVisibility('userPassword', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <?php if($edit_user): ?>
                            <p class="text-xs text-gray-500 mt-1">Deja vacío para mantener la contraseña actual</p>
                        <?php else: ?>
                            <p class="text-xs text-gray-500 mt-1">Mínimo 8 caracteres, una mayúscula, una minúscula y un número</p>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Rol</label>
                        <select name="user_role" class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            <?= ($edit_user && $edit_user['id'] == $_SESSION['user_id']) ? 'disabled' : '' ?>>
                            <option value="user" <?= ($edit_user && $edit_user['role'] == 'user') || !$edit_user ? 'selected' : '' ?>>Usuario</option>
                            <option value="admin" <?= ($edit_user && $edit_user['role'] == 'admin') ? 'selected' : '' ?>>Administrador</option>
                        </select>
                        <?php if($edit_user && $edit_user['id'] == $_SESSION['user_id']): ?>
                            <input type="hidden" name="user_role" value="admin">
                            <p class="text-xs text-yellow-400 mt-1">🔒 No puedes cambiar tu propio rol</p>
                        <?php endif; ?>
                    </div>

                    <?php if($edit_user): ?>
                        <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-4 bg-gray-900/50 p-4 rounded-lg border border-gray-700">
                            <div>
                                <p class="text-xs text-gray-500">Estado</p>
                                <div class="flex items-center gap-3 mt-1">
                                    <span class="user-status <?= $edit_user['is_blocked'] ? 'blocked' : 'active' ?>">
                                        <?= $edit_user['is_blocked'] ? '🚫 Bloqueado' : '✅ Activo' ?>
                                    </span>
                                </div>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Fecha de registro</p>
                                <p class="text-sm text-white mt-1"><?= formatDateVenezuela($edit_user['created_at']) ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Último acceso</p>
                                <p class="text-sm text-white mt-1"><?= $edit_user['last_login'] ? formatDateVenezuela($edit_user['last_login']) : 'Nunca' ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="md:col-span-2 flex flex-col sm:flex-row gap-3 mt-2">
                        <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 p-3 rounded-lg font-bold transition-colors shadow-md">
                            <i class="fas <?= $edit_user ? 'fa-save' : 'fa-user-plus' ?> mr-2"></i>
                            <?= $edit_user ? 'Actualizar Usuario' : 'Registrar Usuario' ?>
                        </button>
                        <?php if($edit_user): ?>
                            <a href="?tab=users" class="flex-1 text-center bg-gray-700 hover:bg-gray-600 p-3 rounded-lg font-semibold transition-colors text-gray-300">
                                <i class="fas fa-times mr-2"></i> Cancelar
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="bg-gray-800/50 rounded-lg border border-gray-700 overflow-hidden">
                <div class="p-6 border-b border-gray-700">
                    <div class="flex justify-between items-center mb-4 flex-wrap gap-2">
                        <h3 class="text-lg font-bold text-indigo-300">
                            <i class="fas fa-list-ul mr-2"></i> Todos los Usuarios (<?= count($users) ?>)
                        </h3>
                        <span class="text-xs text-gray-500">Total registrados</span>
                    </div>

                    <div class="search-box">
                        <div class="search-group">
                            <label><i class="fas fa-search mr-1"></i> Buscar por Cédula</label>
                            <input type="text" id="searchCedula" placeholder="Ej: 14511134..." value="<?= htmlspecialchars($search_cedula ?? '') ?>">
                        </div>
                        <div class="search-actions">
                            <button class="btn-search" onclick="applyFilters('users')"><i class="fas fa-search"></i> Buscar</button>
                            <button class="btn-clear" onclick="clearFilters('users')"><i class="fas fa-times"></i> Limpiar</button>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                        <tr class="border-b border-gray-700 text-gray-400 text-sm">
                            <th class="pb-3 font-semibold">Usuario</th>
                            <th class="pb-3 font-semibold">Cédula</th>
                            <th class="pb-3 font-semibold">Teléfono</th>
                            <th class="pb-3 font-semibold">Rol</th>
                            <th class="pb-3 font-semibold">Estado</th>
                            <th class="pb-3 font-semibold text-center">Acciones</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700/50 text-sm">
                        <?php foreach($users as $u):
                            $cedula_display = '';
                            if (!empty($u['cedula_type']) && !empty($u['cedula_number'])) {
                                $cedula_display = $u['cedula_type'] . '-' . $u['cedula_number'];
                            }
                            $phone_display = '';
                            if (!empty($u['phone_prefix']) && !empty($u['phone_number'])) {
                                $phone_display = '0' . $u['phone_prefix'] . '-' . $u['phone_number'];
                            }
                            $initial = strtoupper(substr($u['name'] ?? 'U', 0, 1));
                            $is_self = ($u['id'] == $_SESSION['user_id']);
                        ?>
                            <tr>
                                <td class="py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                                            <?= htmlspecialchars($initial) ?>
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-200"><?= htmlspecialchars($u['name']) ?></div>
                                            <div class="text-xs text-gray-500"><?= htmlspecialchars($u['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 text-gray-400"><?= htmlspecialchars($cedula_display) ?: '<span class="text-gray-500 text-xs">—</span>' ?></td>
                                <td class="py-3 text-gray-400"><?= htmlspecialchars($phone_display) ?: '<span class="text-gray-500 text-xs">—</span>' ?></td>
                                <td class="py-3">
                                    <span class="inline-block px-3 py-1 rounded-full text-xs font-bold <?= $u['role'] == 'admin' ? 'bg-indigo-600/20 text-indigo-400 border border-indigo-500/30' : 'bg-gray-700 text-gray-400 border border-gray-600' ?>">
                                        <?= $u['role'] == 'admin' ? 'Admin' : 'Usuario' ?>
                                    </span>
                                </td>
                                <td class="py-3">
                                    <span class="inline-block px-3 py-1 rounded-full text-xs font-bold <?= $u['is_blocked'] ? 'bg-red-600/20 text-red-400 border border-red-500/30' : 'bg-green-600/20 text-green-400 border border-green-500/30' ?>">
                                        <?= $u['is_blocked'] ? '🚫 Bloqueado' : '✅ Activo' ?>
                                    </span>
                                </td>
                                <td class="py-3">
                                    <div class="flex justify-center gap-2 flex-wrap">
                                        <a href="?tab=users&edit_user_id=<?= htmlspecialchars($u['id']) ?>&csrf_token=<?= htmlspecialchars($csrf_token) ?>"
                                           class="text-xs px-3 py-1.5 rounded bg-blue-600/20 hover:bg-blue-600/40 text-blue-400 transition-colors flex items-center gap-1">
                                            <i class="fas fa-edit"></i> Editar
                                        </a>
                                        <?php if(!$is_self): ?>
                                            <form action="admin.php?tab=users" method="POST" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                <input type="hidden" name="user_id" value="<?= htmlspecialchars($u['id']) ?>">
                                                <input type="hidden" name="current_status" value="<?= htmlspecialchars($u['is_blocked']) ?>">
                                                <input type="hidden" name="toggle_block_user" value="1">
                                                <button type="submit" class="text-xs px-3 py-1.5 rounded <?= $u['is_blocked'] ? 'bg-green-600/20 hover:bg-green-600/40 text-green-400' : 'bg-yellow-600/20 hover:bg-yellow-600/40 text-yellow-400' ?> transition-colors flex items-center gap-1">
                                                    <i class="fas <?= $u['is_blocked'] ? 'fa-unlock' : 'fa-lock' ?>"></i>
                                                    <?= $u['is_blocked'] ? 'Desbloquear' : 'Bloquear' ?>
                                                </button>
                                            </form>
                                            <form action="admin.php?tab=users" method="POST" class="inline"
                                                  onsubmit="return confirm('¿Eliminar este usuario permanentemente? Esta acción no se puede deshacer.')">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                <input type="hidden" name="user_id" value="<?= htmlspecialchars($u['id']) ?>">
                                                <input type="hidden" name="delete_user" value="1">
                                                <button type="submit" class="text-xs px-3 py-1.5 rounded bg-red-600/20 hover:bg-red-600/40 text-red-400 transition-colors flex items-center gap-1">
                                                    <i class="fas fa-trash"></i> Eliminar
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-500 px-2 py-1 bg-gray-700 rounded flex items-center gap-1">
                                                <i class="fas fa-user-shield"></i> Tú
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if(empty($users)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="text-center py-12 text-gray-400">
                                        <p class="text-4xl mb-3">👥</p>
                                        <p class="text-lg font-medium">No hay usuarios registrados</p>
                                        <p class="text-sm mt-1">Registra tu primer usuario utilizando el formulario superior.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- TAB: COMIDA                                  -->
    <!-- ============================================ -->
    <?php if($activeTab === 'food'): ?>
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-700 mb-8">
            <h2 class="text-lg font-bold mb-4 text-indigo-300">
                <?= $edit_food ? '✏️ Editar Producto' : '➕ Agregar Producto' ?>
            </h2>

            <form action="" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <?php if($edit_food): ?>
                    <input type="hidden" name="food_id" value="<?= htmlspecialchars($edit_food['id']) ?>">
                    <input type="hidden" name="edit_food" value="1">
                <?php else: ?>
                    <input type="hidden" name="add_food" value="1">
                <?php endif; ?>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Nombre del Producto *</label>
                    <input type="text" name="food_name" required maxlength="100" value="<?= $edit_food ? htmlspecialchars($edit_food['name']) : '' ?>"
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Categoría *</label>
                    <select name="category_id" required class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Seleccionar Categoría</option>
                        <?php foreach($food_categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['id']) ?>" <?= ($edit_food && isset($edit_food['category_id']) && $edit_food['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Precio *</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-semibold"><?= htmlspecialchars($siteConfig['currency_symbol'] ?? '$') ?></span>
                        <input type="number" step="0.01" min="0.01" name="food_price" required value="<?= $edit_food ? htmlspecialchars($edit_food['price']) : '' ?>"
                               class="w-full bg-gray-700 p-2.5 pl-7 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                               placeholder="0.00">
                    </div>
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Imagen del Producto</label>
                    <input type="file" name="food_image" accept="image/*"
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700">
                    <?php if($edit_food && !empty($edit_food['image_url']) && file_exists($edit_food['image_url'])): ?>
                        <div class="mt-3 flex items-center gap-4 bg-gray-900/50 p-3 rounded-lg border border-gray-700">
                            <img src="<?= htmlspecialchars($edit_food['image_url']) . '?v=' . time() ?>" alt="Imagen actual" class="h-16 w-16 object-cover rounded bg-gray-900">
                            <div class="flex-1">
                                <p class="text-xs text-gray-400">Imagen actual</p>
                                <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars(basename($edit_food['image_url'])) ?></p>
                                <button type="submit" name="remove_image" value="1"
                                        class="text-xs text-red-400 hover:text-red-300 transition-colors mt-1"
                                        onclick="return confirm('¿Eliminar la imagen actual?')">
                                    <i class="fas fa-trash mr-1"></i> Eliminar imagen
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                    <p class="text-xs text-gray-500 mt-1">Formatos: JPG, PNG, GIF, WEBP, SVG. Máx: 2MB</p>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm text-gray-400 mb-1">Descripción</label>
                    <textarea name="food_description" rows="3" placeholder="Descripción del producto..."
                              class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500"><?= $edit_food ? htmlspecialchars($edit_food['description'] ?? '') : '' ?></textarea>
                </div>

                <button type="submit" class="md:col-span-2 bg-indigo-600 hover:bg-indigo-700 p-3 rounded-lg font-bold transition-colors mt-2 shadow-md">
                    <?= $edit_food ? 'Actualizar Producto' : 'Guardar Producto (Oculto)' ?>
                </button>

                <?php if($edit_food): ?>
                    <a href="?tab=food" class="md:col-span-2 text-center text-gray-400 hover:text-white text-sm">Cancelar edición</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Lista de Productos -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-700">
            <h2 class="text-lg font-bold mb-4 text-indigo-300">📋 Todos los Productos</h2>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                    <tr class="border-b border-gray-700 text-gray-400 text-sm">
                        <th class="pb-3 font-semibold">Imagen</th>
                        <th class="pb-3 font-semibold">Nombre</th>
                        <th class="pb-3 font-semibold">Categoría</th>
                        <th class="pb-3 font-semibold">Precio</th>
                        <th class="pb-3 font-semibold">Descripción</th>
                        <th class="pb-3 font-semibold text-center">Estado</th>
                        <th class="pb-3 font-semibold text-center">Acciones</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700/50 text-sm">
                    <?php foreach($food_items as $f): ?>
                        <tr>
                            <td class="py-3">
                                <?php if(!empty($f['image_url']) && file_exists($f['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($f['image_url']) ?>" alt="<?= htmlspecialchars($f['name']) ?>"
                                         class="w-12 h-12 object-cover rounded bg-gray-700 shadow">
                                <?php else: ?>
                                    <div class="w-12 h-12 bg-gray-700 rounded flex items-center justify-center text-2xl">🍿</div>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 font-medium text-gray-200"><?= htmlspecialchars($f['name']) ?></td>
                            <td class="py-3 text-gray-400"><?= htmlspecialchars($f['category_name'] ?? '-') ?></td>
                            <td class="py-3 text-green-400 font-semibold"><?= formatCurrency($f['price'], $siteConfig) ?></td>
                            <td class="py-3 text-gray-400 text-sm max-w-xs truncate"><?= htmlspecialchars($f['description'] ?? '-') ?></td>
                            <td class="py-3 text-center">
                                <?php if($f['is_active']): ?>
                                    <span class="bg-green-500/20 text-green-400 text-xs px-2 py-0.5 rounded-full font-bold border border-green-500/30">Activo</span>
                                <?php else: ?>
                                    <span class="bg-gray-500/20 text-gray-400 text-xs px-2 py-0.5 rounded-full font-bold border border-gray-500/30">Oculto</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 text-center">
                                <div class="flex justify-center gap-2 flex-wrap">
                                    <a href="?tab=food&edit_food_id=<?= htmlspecialchars($f['id']) ?>&csrf_token=<?= htmlspecialchars($csrf_token) ?>"
                                       class="text-xs px-2 py-1 rounded bg-blue-600/20 hover:bg-blue-600/40 text-blue-400 transition-colors">Editar</a>
                                    <a href="?toggle_food=<?= htmlspecialchars($f['id']) ?>&tab=food&csrf_token=<?= htmlspecialchars($csrf_token) ?>"
                                       class="text-xs px-2 py-1 rounded bg-gray-700 hover:bg-gray-600 transition-colors"
                                       onclick="return confirm('¿Cambiar estado de este producto?')">
                                        <?= $f['is_active'] ? 'Ocultar' : 'Publicar' ?>
                                    </a>
                                    <a href="?delete_food=<?= htmlspecialchars($f['id']) ?>&tab=food&csrf_token=<?= htmlspecialchars($csrf_token) ?>"
                                       class="text-xs px-2 py-1 rounded bg-red-600/20 hover:bg-red-600/40 text-red-400 transition-colors"
                                       onclick="return confirm('¿Eliminar este producto permanentemente?')">Eliminar</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- TAB: HISTORIAL                               -->
    <!-- ============================================ -->
    <?php if($activeTab === 'history'): ?>
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-700 mb-8">
            <h2 class="text-lg font-bold mb-4 text-indigo-300">📊 Historial de Funciones</h2>

            <form method="GET" class="flex flex-wrap gap-4 items-end mb-6">
                <input type="hidden" name="tab" value="history">
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Desde</label>
                    <input type="date" name="history_start_date" value="<?= htmlspecialchars($_GET['history_start_date'] ?? '') ?>"
                           class="bg-gray-700 p-2 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Hasta</label>
                    <input type="date" name="history_end_date" value="<?= htmlspecialchars($_GET['history_end_date'] ?? '') ?>"
                           class="bg-gray-700 p-2 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 px-4 py-2 rounded-lg font-semibold transition-colors">🔍 Filtrar</button>
                <a href="?tab=history" class="bg-gray-600 hover:bg-gray-500 px-4 py-2 rounded-lg font-semibold transition-colors">🗑️ Limpiar filtros</a>
            </form>

            <?php
            $history_start_date = $_GET['history_start_date'] ?? '';
            $history_end_date = $_GET['history_end_date'] ?? '';

            $history_sql = "
                SELECT
                    s.id as showtime_id,
                    COALESCE(m.title, 'Película eliminada') as movie_title,
                    r.name as room_name,
                    s.show_date, s.show_time, m.duration,
                    DATE_ADD(CONCAT(s.show_date, ' ', s.show_time), INTERVAL m.duration MINUTE) as end_time,
                    (SELECT COUNT(*) FROM tickets t WHERE t.showtime_id = s.id) +
                    (SELECT COALESCE(SUM(ticket_count), 0) FROM ticket_logs tl WHERE tl.showtime_id = s.id) as tickets_sold,
                    (SELECT COALESCE(SUM(t.price_paid), 0) FROM tickets t WHERE t.showtime_id = s.id) +
                    (SELECT COALESCE(SUM(tl.ticket_count * s.price), 0) FROM ticket_logs tl WHERE tl.showtime_id = s.id) as total_revenue,
                    s.price as original_price, s.half_price_monday, s.promotions, s.is_active, s.language
                FROM showtimes s
                LEFT JOIN movies m ON s.movie_id = m.id
                JOIN rooms r ON s.room_id = r.id
                WHERE 1=1
            ";

            $params = [];
            if (!empty($history_start_date) && !empty($history_end_date)) {
                $history_sql .= " AND s.show_date BETWEEN ? AND ?";
                $params[] = $history_start_date;
                $params[] = $history_end_date;
            } elseif (!empty($history_start_date)) {
                $history_sql .= " AND s.show_date >= ?";
                $params[] = $history_start_date;
            } elseif (!empty($history_end_date)) {
                $history_sql .= " AND s.show_date <= ?";
                $params[] = $history_end_date;
            }

            $history_sql .= " GROUP BY s.id ORDER BY s.show_date DESC, s.show_time DESC";

            $stmt = $pdo->prepare($history_sql);
            $stmt->execute($params);
            $history_showtimes = $stmt->fetchAll();

            $history_total_tickets = 0;
            $history_total_revenue = 0;
            foreach ($history_showtimes as $h) {
                $history_total_tickets += $h['tickets_sold'];
                $history_total_revenue += $h['total_revenue'];
            }
            ?>

            <div class="history-summary mb-6 grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <p class="text-gray-400 text-xs">Funciones</p>
                    <p class="text-2xl font-bold text-white"><?= count($history_showtimes) ?></p>
                </div>
                <div>
                    <p class="text-gray-400 text-xs">Boletos vendidos</p>
                    <p class="text-2xl font-bold text-green-400"><?= number_format($history_total_tickets) ?></p>
                </div>
                <div>
                    <p class="text-gray-400 text-xs">Ingresos totales</p>
                    <p class="text-2xl font-bold text-yellow-400"><?= formatCurrency($history_total_revenue, $siteConfig) ?></p>
                </div>
                <div>
                    <p class="text-gray-400 text-xs">Promedio por función</p>
                    <p class="text-2xl font-bold text-blue-400">
                        <?= count($history_showtimes) > 0 ? formatCurrency($history_total_revenue / count($history_showtimes), $siteConfig) : formatCurrency(0, $siteConfig) ?>
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <?php if(empty($history_showtimes)): ?>
                    <div class="text-center py-8 text-gray-400">
                        <p class="text-4xl mb-2">📭</p>
                        <p>No hay funciones registradas<?= (!empty($history_start_date) || !empty($history_end_date)) ? ' en el período seleccionado' : '' ?>.</p>
                    </div>
                <?php else: ?>
                    <table class="w-full text-left border-collapse">
                        <thead>
                        <tr class="border-b border-gray-700 text-gray-400 text-sm">
                            <th class="pb-3 font-semibold">Película</th>
                            <th class="pb-3 font-semibold">Sala</th>
                            <th class="pb-3 font-semibold">Fecha</th>
                            <th class="pb-3 font-semibold">Hora</th>
                            <th class="pb-3 font-semibold text-center">Boletos</th>
                            <th class="pb-3 font-semibold text-right">Precio</th>
                            <th class="pb-3 font-semibold text-right">Total</th>
                            <th class="pb-3 font-semibold">Idioma</th>
                            <th class="pb-3 font-semibold">Promociones</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700/50 text-sm">
                        <?php foreach($history_showtimes as $h):
                            $promotions = $h['promotions'] ? explode(',', $h['promotions']) : [];
                            $promo_labels = [];
                            if(in_array('lunes_mitad', $promotions)) $promo_labels[] = '🌙 ½ Precio';
                            if(in_array('preventa', $promotions)) $promo_labels[] = '🎫 Preventa';

                            $display_price = $h['half_price_monday'] ? $h['original_price'] / 2 : $h['original_price'];
                            $has_tickets = $h['tickets_sold'] > 0;
                            $is_deleted = $h['movie_title'] == 'Película eliminada';
                            $language = $h['language'] ?? 'español';
                            $lang_label = $language == 'español' ? 'Español' : 'Subtítulos';
                            $lang_class = $language == 'español' ? 'espanol' : 'subtitulos';
                        ?>
                            <tr class="hover:bg-gray-700/30 transition-colors <?= $h['is_active'] == 0 ? 'showtime-inactive' : '' ?>">
                                <td class="py-3 font-medium <?= $is_deleted ? 'movie-deleted' : 'text-gray-200' ?>">
                                    <?= htmlspecialchars($h['movie_title']) ?>
                                    <?php if($h['is_active'] == 0): ?><span class="text-xs text-gray-500 ml-1">(Inactiva)</span><?php endif; ?>
                                    <?php if($is_deleted): ?><span class="text-xs text-gray-500 ml-1">(Eliminada)</span><?php endif; ?>
                                </td>
                                <td class="py-3 text-gray-400"><?= htmlspecialchars($h['room_name']) ?></td>
                                <td class="py-3 text-gray-400"><?= formatDateShort($h['show_date']) ?></td>
                                <td class="py-3 text-indigo-300 font-semibold time-display"><?= formatTimeVenezuela($h['show_time']) ?></td>
                                <td class="py-3 text-center">
                                    <span class="tickets-sold-badge <?= $has_tickets ? 'sold' : 'none' ?>">
                                        <?= number_format($h['tickets_sold']) ?>
                                    </span>
                                </td>
                                <td class="py-3 text-right text-gray-400"><?= formatCurrency($display_price, $siteConfig) ?></td>
                                <td class="py-3 text-right font-bold <?= $h['total_revenue'] > 0 ? 'text-yellow-400' : 'text-gray-500' ?>">
                                    <?= formatCurrency($h['total_revenue'], $siteConfig) ?>
                                </td>
                                <td class="py-3"><span class="language-badge <?= $lang_class ?>"><?= $lang_label ?></span></td>
                                <td class="py-3">
                                    <?php foreach($promo_labels as $label): ?>
                                        <span class="promotion-tag <?= strpos($label, '½') !== false ? 'lunes' : 'preventa' ?>"><?= $label ?></span>
                                    <?php endforeach; ?>
                                    <?php if(empty($promo_labels)): ?><span class="text-gray-500 text-xs">—</span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- TAB: CONFIGURACIÓN                           -->
    <!-- ============================================ -->
    <?php if($activeTab === 'config'):
        $config = getSiteConfig($pdo);
    ?>
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-700">
            <h2 class="text-lg font-bold mb-4 text-indigo-300">⚙️ Configuración del Sitio</h2>
            <p class="text-sm text-gray-400 mb-4">Configura los parámetros globales que afectan a todo el sitio web.</p>

            <form action="" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="save_config" value="1">

                <div class="md:col-span-2">
                    <h3 class="text-md font-semibold text-white mb-3 border-b border-gray-700 pb-2">🏢 Información General</h3>
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Nombre del Sitio</label>
                    <input type="text" name="site_name" value="<?= htmlspecialchars($config['site_name'] ?? 'Cinema Pro') ?>"
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Subir Logo del Header</label>
                    <input type="file" name="site_logo" accept="image/*"
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700">
                    <?php if(!empty($config['site_logo']) && file_exists($config['site_logo'])): ?>
                        <div class="mt-3 flex items-center gap-4 bg-gray-900/50 p-3 rounded-lg border border-gray-700">
                            <img src="<?= htmlspecialchars($config['site_logo']) . '?v=' . time() ?>" alt="Logo actual" class="logo-preview">
                            <div class="flex-1">
                                <p class="text-xs text-gray-400">Logo actual del Header</p>
                                <button type="submit" name="remove_logo" value="1"
                                        class="text-xs text-red-400 hover:text-red-300 transition-colors mt-1"
                                        onclick="return confirm('¿Eliminar el logo actual?')">
                                    <i class="fas fa-trash mr-1"></i> Eliminar logo
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Subir Logo del Footer</label>
                    <input type="file" name="footer_logo" accept="image/*"
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700">
                    <?php if(!empty($config['footer_logo']) && file_exists($config['footer_logo'])): ?>
                        <div class="mt-3 flex items-center gap-4 bg-gray-900/50 p-3 rounded-lg border border-gray-700">
                            <img src="<?= htmlspecialchars($config['footer_logo']) . '?v=' . time() ?>" alt="Logo footer actual" class="logo-preview-footer">
                            <div class="flex-1">
                                <p class="text-xs text-gray-400">Logo actual del Footer</p>
                                <button type="submit" name="remove_footer_logo" value="1"
                                        class="text-xs text-red-400 hover:text-red-300 transition-colors mt-1"
                                        onclick="return confirm('¿Eliminar el logo del footer actual?')">
                                    <i class="fas fa-trash mr-1"></i> Eliminar logo
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Subir Favicon</label>
                    <input type="file" name="site_favicon" accept="image/png,image/x-icon,image/vnd.microsoft.icon"
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700">
                    <?php if(!empty($config['site_favicon']) && file_exists($config['site_favicon'])): ?>
                        <div class="mt-3 flex items-center gap-4 bg-gray-900/50 p-3 rounded-lg border border-gray-700">
                            <img src="<?= htmlspecialchars($config['site_favicon']) . '?v=' . time() ?>" alt="Favicon actual" class="bg-gray-900 p-1 rounded" style="width: 32px; height: 32px; object-fit: contain;">
                            <div class="flex-1">
                                <p class="text-xs text-gray-400">Favicon actual</p>
                                <button type="submit" name="remove_favicon" value="1"
                                        class="text-xs text-red-400 hover:text-red-300 transition-colors mt-1"
                                        onclick="return confirm('¿Eliminar el favicon actual?')">
                                    <i class="fas fa-trash mr-1"></i> Eliminar favicon
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="md:col-span-2 mt-2">
                    <h3 class="text-md font-semibold text-white mb-3 border-b border-gray-700 pb-2">💰 Moneda y Formato</h3>
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Símbolo de Moneda</label>
                    <input type="text" name="currency_symbol" value="<?= htmlspecialchars($config['currency_symbol'] ?? '$') ?>"
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Posición del Símbolo</label>
                    <select name="currency_position" class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="left" <?= ($config['currency_position'] ?? 'left') === 'left' ? 'selected' : '' ?>>Izquierda (ej: $100)</option>
                        <option value="right" <?= ($config['currency_position'] ?? 'left') === 'right' ? 'selected' : '' ?>>Derecha (ej: 100 $)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Separador de Miles</label>
                    <input type="text" name="thousands_separator" value="<?= htmlspecialchars($config['thousands_separator'] ?? '.') ?>"
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500" maxlength="1">
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Separador Decimal</label>
                    <input type="text" name="decimal_separator" value="<?= htmlspecialchars($config['decimal_separator'] ?? ',') ?>"
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500" maxlength="1">
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Número de Decimales</label>
                    <select name="decimal_places" class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="0" <?= ($config['decimal_places'] ?? '2') == '0' ? 'selected' : '' ?>>0</option>
                        <option value="1" <?= ($config['decimal_places'] ?? '2') == '1' ? 'selected' : '' ?>>1</option>
                        <option value="2" <?= ($config['decimal_places'] ?? '2') == '2' ? 'selected' : '' ?>>2</option>
                        <option value="3" <?= ($config['decimal_places'] ?? '2') == '3' ? 'selected' : '' ?>>3</option>
                    </select>
                </div>

                <div class="md:col-span-2 mt-2">
                    <h3 class="text-md font-semibold text-white mb-3 border-b border-gray-700 pb-2">🧾 Impuestos</h3>
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Porcentaje de IVA (%)</label>
                    <input type="number" name="tax_rate" step="0.01" min="0" max="100"
                           value="<?= htmlspecialchars($taxRate) ?>"
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                           placeholder="16">
                    <p class="text-xs text-gray-500 mt-1">Ejemplo: 16 para 16%</p>
                </div>

                <div class="md:col-span-2 mt-2">
                    <h3 class="text-md font-semibold text-white mb-3 border-b border-gray-700 pb-2">📞 Información de Contacto</h3>
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Dirección</label>
                    <input type="text" name="address" value="<?= htmlspecialchars($config['address'] ?? '') ?>"
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Teléfono</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($config['phone'] ?? '') ?>"
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($config['email'] ?? '') ?>"
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="md:col-span-2 mt-2">
                    <h3 class="text-md font-semibold text-white mb-3 border-b border-gray-700 pb-2">📱 Redes Sociales</h3>
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1"><i class="fab fa-instagram text-pink-400"></i> Instagram</label>
                    <input type="url" name="instagram" value="<?= htmlspecialchars($config['instagram'] ?? '') ?>"
                           placeholder="https://instagram.com/tuusuario"
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1"><i class="fab fa-facebook text-blue-400"></i> Facebook</label>
                    <input type="url" name="facebook" value="<?= htmlspecialchars($config['facebook'] ?? '') ?>"
                           placeholder="https://facebook.com/tupagina"
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1"><i class="fa-brands fa-x-twitter text-white"></i> X (Twitter)</label>
                    <input type="url" name="twitter" value="<?= htmlspecialchars($config['twitter'] ?? '') ?>"
                           placeholder="https://x.com/tuusuario"
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1"><i class="fab fa-telegram text-blue-400"></i> Telegram</label>
                    <input type="url" name="telegram" value="<?= htmlspecialchars($config['telegram'] ?? '') ?>"
                           placeholder="https://t.me/tucanal"
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1"><i class="fab fa-whatsapp text-green-400"></i> WhatsApp</label>
                    <input type="url" name="whatsapp" value="<?= htmlspecialchars($config['whatsapp'] ?? '') ?>"
                           placeholder="https://wa.me/1234567890"
                           class="w-full bg-gray-700 p-2.5 rounded text-white border border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <button type="submit" class="md:col-span-2 bg-indigo-600 hover:bg-indigo-700 p-3 rounded-lg font-bold transition-colors mt-4 shadow-md">
                    💾 Guardar Configuración
                </button>
                <a href="?tab=config" class="md:col-span-2 text-center text-gray-400 hover:text-white text-sm">Volver</a>
            </form>
        </div>
    <?php endif; ?>

</div>

<!-- ============================================ -->
<!-- JAVASCRIPT                                   -->
<!-- ============================================ -->
<script>
// ============================================
// ✅ TOGGLE DE VISIBILIDAD DE CONTRASEÑA
// ============================================
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input || !btn) return;

    const icon = btn.querySelector('i');

    if (input.type === 'password') {
        input.type = 'text';
        if (icon) {
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        }
    } else {
        input.type = 'password';
        if (icon) {
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
}

// ============================================
// ✅ FUNCIONES DE BÚSQUEDA (Unificadas)
// ============================================
function applyFilters(tab) {
    const csrf = '<?= htmlspecialchars($csrf_token) ?>';
    let url = '?tab=' + tab + '&csrf_token=' + encodeURIComponent(csrf);

    if (tab === 'movies') {
        const title = document.getElementById('searchTitle');
        if (title && title.value.trim()) {
            url += '&search_title=' + encodeURIComponent(title.value.trim());
        }
    } else if (tab === 'showtimes') {
        const title = document.getElementById('searchShowtime');
        if (title && title.value.trim()) {
            url += '&search_showtime=' + encodeURIComponent(title.value.trim());
        }
    } else if (tab === 'users') {
        const cedula = document.getElementById('searchCedula');
        if (cedula && cedula.value.trim()) {
            url += '&search_cedula=' + encodeURIComponent(cedula.value.trim());
        }
    }

    window.location.href = url;
}

function clearFilters(tab) {
    const csrf = '<?= htmlspecialchars($csrf_token) ?>';
    window.location.href = '?tab=' + tab + '&csrf_token=' + encodeURIComponent(csrf);
}

// ============================================
// ✅ ENTER PARA BUSCAR
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const searchTitle = document.getElementById('searchTitle');
    if (searchTitle) {
        searchTitle.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); applyFilters('movies'); }
        });
    }

    const searchShowtime = document.getElementById('searchShowtime');
    if (searchShowtime) {
        searchShowtime.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); applyFilters('showtimes'); }
        });
    }

    const searchCedula = document.getElementById('searchCedula');
    if (searchCedula) {
        searchCedula.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); applyFilters('users'); }
        });
    }
});

// ============================================
// ✅ FUNCIÓN PARA VERIFICAR CONFLICTOS
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const movieSelect = document.getElementById('movieSelect');
    const roomSelect = document.getElementById('roomSelect');
    const dateInput = document.getElementById('dateInput');
    const timeInput = document.getElementById('timeInput');
    const conflictStatus = document.getElementById('conflictStatus');
    const conflictChecker = document.getElementById('conflictChecker');
    const submitBtn = document.getElementById('submitBtn');
    const showtimeIdInput = document.getElementById('showtimeIdInput');

    if (!movieSelect || !roomSelect || !dateInput || !timeInput) {
        return;
    }

    // ============================================
    // ✅ CORREGIDO: Inicializar toggles de precio
    // Ya NO borra el valor al apagar/encender el switch
    // ============================================
    const childCheckbox = document.getElementById('enable_child_price');
    const childInput = document.getElementById('price_child');
    const seniorCheckbox = document.getElementById('enable_senior_price');
    const seniorInput = document.getElementById('price_senior');

    function syncPriceToggle(checkbox, input) {
        if (!checkbox || !input) return;
        input.disabled = !checkbox.checked;
        input.classList.toggle('price-input-disabled', !checkbox.checked);
    }

    // ✅ Sincronizar estado inicial al cargar la página
    syncPriceToggle(childCheckbox, childInput);
    syncPriceToggle(seniorCheckbox, seniorInput);

    // ✅ Escuchar cambios sin borrar el precio
    if (childCheckbox) {
        childCheckbox.addEventListener('change', function() {
            syncPriceToggle(childCheckbox, childInput);
        });
    }

    if (seniorCheckbox) {
        seniorCheckbox.addEventListener('change', function() {
            syncPriceToggle(seniorCheckbox, seniorInput);
        });
    }

    function checkConflicts() {
        const movieId = movieSelect.value;
        const roomId = roomSelect.value;
        const date = dateInput.value;
        const time = timeInput.value;

        if (!movieId || !roomId || !date || !time) {
            if (conflictStatus) conflictStatus.textContent = 'Selecciona película, sala, fecha y hora para verificar automáticamente si hay conflictos.';
            if (conflictChecker) {
                conflictChecker.className = 'mb-4 p-3 rounded-lg border text-sm conflict-checking';
                conflictChecker.style.display = 'block';
            }
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('btn-disabled');
                submitBtn.style.opacity = '1';
                submitBtn.style.cursor = 'pointer';
            }
            return;
        }

        const selectedOption = movieSelect.options[movieSelect.selectedIndex];
        const duration = selectedOption ? parseInt(selectedOption.dataset.duration) || 0 : 0;

        if (duration === 0) {
            if (conflictStatus) conflictStatus.textContent = '⚠️ La película seleccionada no tiene duración definida.';
            if (conflictChecker) {
                conflictChecker.className = 'mb-4 p-3 rounded-lg border text-sm conflict-warning';
                conflictChecker.style.display = 'block';
            }
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('btn-disabled');
                submitBtn.style.opacity = '1';
                submitBtn.style.cursor = 'pointer';
            }
            return;
        }

        if (conflictStatus) conflictStatus.textContent = '⏳ Verificando conflictos...';
        if (conflictChecker) {
            conflictChecker.className = 'mb-4 p-3 rounded-lg border text-sm conflict-checking';
            conflictChecker.style.display = 'block';
        }
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.add('btn-disabled');
            submitBtn.style.opacity = '0.5';
            submitBtn.style.cursor = 'not-allowed';
        }

        const formData = new FormData();
        formData.append('action', 'check_conflict');
        formData.append('room_id', roomId);
        formData.append('show_date', date);
        formData.append('show_time', time);
        formData.append('duration', duration);

        const excludeId = showtimeIdInput ? showtimeIdInput.value : '0';
        if (excludeId && parseInt(excludeId) > 0) {
            formData.append('exclude_id', excludeId);
        }

        fetch('check_conflict.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                if (conflictStatus) conflictStatus.textContent = '⚠️ Error: ' + data.error;
                if (conflictChecker) {
                    conflictChecker.className = 'mb-4 p-3 rounded-lg border text-sm conflict-warning';
                    conflictChecker.style.display = 'block';
                }
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('btn-disabled');
                    submitBtn.style.opacity = '1';
                    submitBtn.style.cursor = 'pointer';
                }
                return;
            }

            if (data.conflict) {
                let message = data.message || '❌ Conflicto detectado';
                message = message.replace(/Sala\s+Sala/g, 'Sala');
                message = message.replace(/sala\s+sala/g, 'sala');

                if (conflictStatus) conflictStatus.textContent = '❌ ' + message;
                if (conflictChecker) {
                    conflictChecker.className = 'mb-4 p-3 rounded-lg border text-sm conflict-warning';
                    conflictChecker.style.display = 'block';
                }
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('btn-disabled');
                    submitBtn.style.opacity = '0.5';
                    submitBtn.style.cursor = 'not-allowed';
                }
            } else {
                let message = data.message || '✅ No hay conflictos. La sala está disponible en el horario seleccionado.';
                if (conflictStatus) conflictStatus.textContent = message;
                if (conflictChecker) {
                    conflictChecker.className = 'mb-4 p-3 rounded-lg border text-sm conflict-safe';
                    conflictChecker.style.display = 'block';
                }
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('btn-disabled');
                    submitBtn.style.opacity = '1';
                    submitBtn.style.cursor = 'pointer';
                }
            }
        })
        .catch(error => {
            console.error('Error verificando conflictos:', error);
            if (conflictStatus) conflictStatus.textContent = '⚠️ Error al verificar conflictos. Intenta nuevamente.';
            if (conflictChecker) {
                conflictChecker.className = 'mb-4 p-3 rounded-lg border text-sm conflict-warning';
                conflictChecker.style.display = 'block';
            }
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('btn-disabled');
                submitBtn.style.opacity = '1';
                submitBtn.style.cursor = 'pointer';
            }
        });
    }

    movieSelect.addEventListener('change', checkConflicts);
    roomSelect.addEventListener('change', checkConflicts);
    dateInput.addEventListener('change', checkConflicts);
    timeInput.addEventListener('change', checkConflicts);

    if (movieSelect.value && roomSelect.value && dateInput.value && timeInput.value) {
        setTimeout(checkConflicts, 300);
    }

    // ✅ Validación adicional al enviar el formulario
    document.getElementById('showtimeForm')?.addEventListener('submit', function(e) {
        if (submitBtn && submitBtn.disabled) {
            e.preventDefault();
            alert('❌ No puedes guardar el horario mientras haya un conflicto. Resuelve el conflicto primero.');
            return false;
        }
    });
});

// ============================================
// ✅ CORREGIDO: TOGGLE PRECIO (Niño / Tercera Edad)
// Ya NO borra el valor al apagar el switch
// ============================================
function togglePriceInput(checkbox, inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;

    input.disabled = !checkbox.checked;
    input.classList.toggle('price-input-disabled', !checkbox.checked);
}
</script>
</body>
</html>