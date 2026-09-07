<?php
// ============================================
// MÓDULO: CONFIGURACIÓN — ACCIONES (POST)
// ============================================
require_once '../../../config.php';
require_once '../../includes/security.php';

// ============================================
// VERIFICAR AUTENTICACIÓN Y ROL
// ============================================
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../../../login.php');
    exit;
}

$action = $_POST['action'] ?? '';

// Verificar CSRF
$csrf_token = $_POST['csrf_token'] ?? '';
if (!verifyCSRFToken($csrf_token)) {
    header('Location: ../../index.php?tab=config&error=' . urlencode('Token CSRF inválido'));
    exit;
}

// Sección a la que devolver el resultado
$section = ($action === 'save_config') ? ($_POST['section'] ?? 'general') : 'general';
if (!in_array($section, ['general', 'currency', 'contact'])) {
    $section = 'general';
}
$base_url = '../../index.php?tab=config&action=' . $section;

// ============================================
// GUARDAR CONFIGURACIÓN
// ============================================
if ($action === 'save_config') {
    $siteRoot = dirname(__DIR__, 3);
    $upload_dir = $siteRoot . '/uploads/';

    $config_keys = [
        'site_name', 'footer_copyright', 'company_rif', 'currency_symbol', 'currency_position',
        'thousands_separator', 'decimal_separator', 'decimal_places',
        'address', 'phone', 'email',
        'instagram', 'facebook', 'twitter', 'telegram', 'whatsapp'
    ];

    $error = '';
    try {
        foreach ($config_keys as $key) {
            if (!isset($_POST[$key])) {
                continue;
            }
            $value = sanitizeInput($_POST[$key]);
            if ($key === 'whatsapp') {
                // Guardar solo el número (quitar +, espacios, guiones); wa.me se integra en el footer
                $value = preg_replace('/[^0-9]/', '', $value);
            }
            if ($key === 'email' && !empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $error = "Email de contacto no válido.";
                break;
            }
            if (in_array($key, ['instagram', 'facebook', 'twitter', 'telegram']) && !empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                $error = "URL de " . $key . " no válida.";
                break;
            }
            $stmt = $pdo->prepare("UPDATE site_config SET value = ? WHERE key_name = ?");
            $stmt->execute([$value, $key]);
        }

        // ============================================
        // RIF DE LA EMPRESA (upsert)
        // ============================================
        if (empty($error) && isset($_POST['company_rif'])) {
            $rif = trim($_POST['company_rif']);
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM site_config WHERE key_name = 'company_rif'");
            $stmt->execute();
            $rifExists = (int)$stmt->fetchColumn() > 0;
            if ($rifExists) {
                $stmt = $pdo->prepare("UPDATE site_config SET value = ?, updated_at = NOW() WHERE key_name = 'company_rif'");
                $stmt->execute([$rif]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO site_config (key_name, value) VALUES ('company_rif', ?)");
                $stmt->execute([$rif]);
            }
        }

        // ============================================
        // ZONA HORARIA (validación + upsert)
        // ============================================
        if (empty($error) && isset($_POST['timezone'])) {
            $timezone = trim($_POST['timezone']);
            if (in_array($timezone, getTimezoneIdentifiers(), true)) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM site_config WHERE key_name = 'timezone'");
                $stmt->execute();
                $tzExists = (int)$stmt->fetchColumn() > 0;
                if ($tzExists) {
                    $stmt = $pdo->prepare("UPDATE site_config SET value = ?, updated_at = NOW() WHERE key_name = 'timezone'");
                    $stmt->execute([$timezone]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO site_config (key_name, value) VALUES ('timezone', ?)");
                    $stmt->execute([$timezone]);
                }
            } else {
                $error = "Zona horaria no válida.";
            }
        }

        if (empty($error) && isset($_POST['tax_rate'])) {
            $tax_rate = filter_var($_POST['tax_rate'], FILTER_VALIDATE_FLOAT);
            if ($tax_rate !== false && $tax_rate >= 0 && $tax_rate <= 100) {
                $stmt = $pdo->prepare("UPDATE tax_config SET tax_rate = ?, updated_at = NOW() WHERE is_active = 1");
                $stmt->execute([$tax_rate]);
            }
        }

        // ============================================
        // SUBIR LOGO DEL HEADER
        // ============================================
        if (empty($error) && isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
            $upload_result = secureFileUpload($_FILES['site_logo']);
            if (!$upload_result['success']) {
                $error = $upload_result['error'];
            } else {
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $filename = 'logo.' . $upload_result['extension'];
                $destination = $upload_dir . $filename;

                $stmt = $pdo->prepare("SELECT value FROM site_config WHERE key_name = 'site_logo'");
                $stmt->execute();
                $old_logo = $stmt->fetchColumn();
                if (!empty($old_logo) && $old_logo !== ('uploads/' . $filename) && is_file($siteRoot . '/' . $old_logo)) {
                    @unlink($siteRoot . '/' . $old_logo);
                }

                if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $destination)) {
                    $stmt = $pdo->prepare("UPDATE site_config SET value = ? WHERE key_name = 'site_logo'");
                    $stmt->execute(['uploads/' . $filename]);
                } else {
                    $error = "Error al subir el logo del header.";
                }
            }
        }

        // ============================================
        // SUBIR LOGO DEL FOOTER
        // ============================================
        if (empty($error) && isset($_FILES['footer_logo']) && $_FILES['footer_logo']['error'] === UPLOAD_ERR_OK) {
            $upload_result = secureFileUpload($_FILES['footer_logo']);
            if (!$upload_result['success']) {
                $error = $upload_result['error'];
            } else {
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $filename = 'footer_logo.' . $upload_result['extension'];
                $destination = $upload_dir . $filename;

                $stmt = $pdo->prepare("SELECT value FROM site_config WHERE key_name = 'footer_logo'");
                $stmt->execute();
                $old_logo = $stmt->fetchColumn();
                if (!empty($old_logo) && $old_logo !== ('uploads/' . $filename) && is_file($siteRoot . '/' . $old_logo)) {
                    @unlink($siteRoot . '/' . $old_logo);
                }

                if (move_uploaded_file($_FILES['footer_logo']['tmp_name'], $destination)) {
                    $stmt = $pdo->prepare("UPDATE site_config SET value = ? WHERE key_name = 'footer_logo'");
                    $stmt->execute(['uploads/' . $filename]);
                } else {
                    $error = "Error al subir el logo del footer.";
                }
            }
        }

        // ============================================
        // SUBIR FAVICON
        // ============================================
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
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $filename = 'favicon.' . $extension;
                $destination = $upload_dir . $filename;

                $stmt = $pdo->prepare("SELECT value FROM site_config WHERE key_name = 'site_favicon'");
                $stmt->execute();
                $old_favicon = $stmt->fetchColumn();
                if (!empty($old_favicon) && $old_favicon !== ('uploads/' . $filename) && is_file($siteRoot . '/' . $old_favicon)) {
                    @unlink($siteRoot . '/' . $old_favicon);
                }

                if (move_uploaded_file($_FILES['site_favicon']['tmp_name'], $destination)) {
                    $stmt = $pdo->prepare("UPDATE site_config SET value = ? WHERE key_name = 'site_favicon'");
                    $stmt->execute(['uploads/' . $filename]);
                } else {
                    $error = "Error al subir el favicon.";
                }
            }
        }

        // ============================================
        // ELIMINAR LOGO DEL HEADER
        // ============================================
        if (empty($error) && isset($_POST['remove_logo']) && $_POST['remove_logo'] == '1') {
            $stmt = $pdo->prepare("SELECT value FROM site_config WHERE key_name = 'site_logo'");
            $stmt->execute();
            $old_logo = $stmt->fetchColumn();
            if (!empty($old_logo) && is_file($siteRoot . '/' . $old_logo)) {
                @unlink($siteRoot . '/' . $old_logo);
            }
            $stmt = $pdo->prepare("UPDATE site_config SET value = '' WHERE key_name = 'site_logo'");
            $stmt->execute([]);
        }

        // ============================================
        // ELIMINAR LOGO DEL FOOTER
        // ============================================
        if (empty($error) && isset($_POST['remove_footer_logo']) && $_POST['remove_footer_logo'] == '1') {
            $stmt = $pdo->prepare("SELECT value FROM site_config WHERE key_name = 'footer_logo'");
            $stmt->execute();
            $old_logo = $stmt->fetchColumn();
            if (!empty($old_logo) && is_file($siteRoot . '/' . $old_logo)) {
                @unlink($siteRoot . '/' . $old_logo);
            }
            $stmt = $pdo->prepare("UPDATE site_config SET value = '' WHERE key_name = 'footer_logo'");
            $stmt->execute([]);
        }

        // ============================================
        // ELIMINAR FAVICON
        // ============================================
        if (empty($error) && isset($_POST['remove_favicon']) && $_POST['remove_favicon'] == '1') {
            $stmt = $pdo->prepare("SELECT value FROM site_config WHERE key_name = 'site_favicon'");
            $stmt->execute();
            $old_favicon = $stmt->fetchColumn();
            if (!empty($old_favicon) && is_file($siteRoot . '/' . $old_favicon)) {
                @unlink($siteRoot . '/' . $old_favicon);
            }
            $stmt = $pdo->prepare("UPDATE site_config SET value = '' WHERE key_name = 'site_favicon'");
            $stmt->execute([]);
        }
    } catch (PDOException $e) {
        error_log("Error al guardar configuración: " . $e->getMessage());
        $error = "Error al guardar configuración. Por favor, intente nuevamente.";
    }

    if (!empty($error)) {
        header('Location: ' . $base_url . '&error=' . urlencode($error));
        exit;
    }

    header('Location: ' . $base_url . '&msg=' . urlencode('Configuración actualizada exitosamente.'));
    exit;
}

// ============================================
// REDIRECCIÓN POR DEFECTO
// ============================================
header('Location: ../../index.php?tab=config');
exit;