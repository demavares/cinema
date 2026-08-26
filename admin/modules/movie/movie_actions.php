<?php
require_once '../../../config.php';
require_once '../../includes/security.php';

// ============================================
// VERIFICAR AUTENTICACIÓN Y ROL
// ============================================
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Obtener acción
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Determinar la URL de retorno - SIEMPRE a admin/index.php
$return_url = $_POST['return'] ?? $_GET['return'] ?? '../../index.php?tab=movies';

// Verificar CSRF
$csrf_token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
if (!verifyCSRFToken($csrf_token)) {
    header('Location: ' . $return_url . '&error=Token+CSRF+inválido');
    exit;
}

// ============================================
// AGREGAR PELÍCULA
// ============================================
if ($action === 'add_movie') {
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
        header('Location: ' . $return_url . '&error=Título,+Clasificación+y+URL+del+tráiler+son+obligatorios.');
        exit;
    }

    if (!empty($poster_url) && !filter_var($poster_url, FILTER_VALIDATE_URL)) {
        header('Location: ' . $return_url . '&error=URL+del+póster+no+válida.');
        exit;
    }

    if (!empty($trailer_url) && !filter_var($trailer_url, FILTER_VALIDATE_URL)) {
        header('Location: ' . $return_url . '&error=URL+del+tráiler+no+válida.');
        exit;
    }

    if ($year !== null && ($year < 1900 || $year > date('Y') + 2)) {
        header('Location: ' . $return_url . '&error=Año+inválido.');
        exit;
    }

    if (strlen($description) > 5000) {
        header('Location: ' . $return_url . '&error=La+sinopsis+no+puede+exceder+los+5000+caracteres.');
        exit;
    }

    // Buscar en TMDb
    $tmdb_data = getMovieFromTMDB($title, $year);

    if (!$tmdb_data && $year) {
        $tmdb_data = getMovieFromTMDB($title, null);
    }

    if (!$tmdb_data && $title_raw !== $title) {
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
            }
        }
    } else {
        header('Location: ' . $return_url . '&error=No+se+encontró+la+película+en+TMDb.+Verifica+el+título+y+año.');
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO movies (title, description, poster_url, banner_url, duration, genre, year, director, cast_members, classification, trailer_url, country_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
        $stmt->execute([$title, $description, $poster_url, $banner_url, $duration, $genre, $year, $director, $cast_members, $classification, $trailer_url, $country_id]);
        $success_msg = "Película «" . $title . "» agregada exitosamente desde TMDb (oculta por defecto).";
        header('Location: ' . $return_url . '&msg=' . urlencode($success_msg));
        exit;
    } catch (PDOException $e) {
        error_log("Error al guardar película: " . $e->getMessage());
        header('Location: ' . $return_url . '&error=Error+al+guardar+la+película.');
        exit;
    }
}

// ============================================
// EDITAR PELÍCULA
// ============================================
if ($action === 'edit_movie') {
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
        header('Location: ' . $return_url . '&error=ID+de+película+inválido.');
        exit;
    }

    if (empty($title) || empty($trailer_url) || empty($classification)) {
        header('Location: ' . $return_url . '&error=Título,+Clasificación+y+URL+del+tráiler+son+obligatorios.');
        exit;
    }

    if (!empty($poster_url) && !filter_var($poster_url, FILTER_VALIDATE_URL)) {
        header('Location: ' . $return_url . '&error=URL+del+póster+no+válida.');
        exit;
    }

    if (!empty($trailer_url) && !filter_var($trailer_url, FILTER_VALIDATE_URL)) {
        header('Location: ' . $return_url . '&error=URL+del+tráiler+no+válida.');
        exit;
    }

    if ($year !== null && ($year < 1900 || $year > date('Y') + 2)) {
        header('Location: ' . $return_url . '&error=Año+inválido.');
        exit;
    }

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
        header('Location: ' . $return_url . '&msg=' . urlencode($msg_text));
        exit;
    } catch (PDOException $e) {
        error_log("Error al actualizar película: " . $e->getMessage());
        header('Location: ' . $return_url . '&error=Error+al+actualizar+la+película.');
        exit;
    }
}

// ============================================
// ACTUALIZAR DESDE TMDb
// ============================================
if ($action === 'update_movie') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($id <= 0) {
        header('Location: ' . $return_url . '&error=ID+inválido.');
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
        $stmt->execute([$id]);
        $movie = $stmt->fetch();

        if (!$movie) {
            header('Location: ' . $return_url . '&error=Película+no+encontrada.');
            exit;
        }

        $tmdb_data = getMovieFromTMDB($movie['title'], $movie['year']);

        if (!$tmdb_data) {
            $tmdb_data = getMovieFromTMDB($movie['title'], null);
        }

        if (!$tmdb_data) {
            header('Location: ' . $return_url . '&error=No+se+pudieron+obtener+datos+de+TMDb+para+la+película.');
            exit;
        }

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
            $msg_text = "Película «" . $movie['title'] . "» actualizada desde TMDb. Campos: " . implode(', ', $updated_fields) . ".";
        } else {
            $msg_text = "Película «" . $movie['title'] . "» ya está actualizada.";
        }

        header('Location: ' . $return_url . '&msg=' . urlencode($msg_text));
        exit;
    } catch (PDOException $e) {
        error_log("Error al actualizar película desde TMDb: " . $e->getMessage());
        header('Location: ' . $return_url . '&error=Error+al+actualizar+la+película.');
        exit;
    }
}

// ============================================
// ELIMINAR PELÍCULA
// ============================================
if ($action === 'delete_movie') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($id <= 0) {
        header('Location: ' . $return_url . '&error=ID+inválido.');
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE t FROM tickets t INNER JOIN showtimes s ON t.showtime_id = s.id WHERE s.movie_id = ?");
        $stmt->execute([$id]);

        $stmt = $pdo->prepare("DELETE FROM showtimes WHERE movie_id = ?");
        $stmt->execute([$id]);

        $stmt = $pdo->prepare("DELETE FROM movies WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit();
        header('Location: ' . $return_url . '&msg=' . urlencode("Película eliminada correctamente."));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error al eliminar película: " . $e->getMessage());
        header('Location: ' . $return_url . '&error=Error+al+eliminar+la+película.');
        exit;
    }
}

// ============================================
// TOGGLE PELÍCULA (Activar/Desactivar)
// ============================================
if ($action === 'toggle_movie') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($id <= 0) {
        header('Location: ' . $return_url . '&error=ID+inválido.');
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE movies SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: ' . $return_url . '&msg=' . urlencode("Estado de película actualizado."));
        exit;
    } catch (PDOException $e) {
        error_log("Error al cambiar estado de película: " . $e->getMessage());
        header('Location: ' . $return_url . '&error=Error+al+actualizar+el+estado.');
        exit;
    }
}

// ============================================
// REDIRECCIÓN POR DEFECTO
// ============================================
header('Location: ' . $return_url);
exit;
?>