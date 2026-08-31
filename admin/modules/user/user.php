<?php
// ============================================
// MÓDULO: USUARIOS — LISTADO
// ============================================
$search_cedula = isset($_GET['search_cedula']) ? trim($_GET['search_cedula']) : '';

try {
    if ($search_cedula !== '') {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE cedula_number LIKE ? ORDER BY id DESC");
        $stmt->execute(['%' . $search_cedula . '%']);
    } else {
        $stmt = $pdo->query("SELECT * FROM users ORDER BY id DESC");
    }
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al consultar usuarios: " . $e->getMessage());
    echo '<div class="admin-alert admin-alert-error"><i class="fas fa-exclamation-circle"></i> Error al cargar los usuarios. Por favor, intente nuevamente.</div>';
    return;
}

$user_count = count($users);
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
?>

<div class="admin-content-header">
    <h1 class="admin-content-title">Usuarios</h1>
    <p class="admin-content-subtitle">Gestiona los usuarios registrados en el sistema (<strong><?= $user_count ?></strong> de <strong><?= $total_users ?></strong> totales)</p>
</div>

<!-- Lista de Usuarios con buscador por cédula -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">Usuarios Registrados</h3>
        <a href="index.php?tab=users&action=register" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg transition-all duration-200 shadow-sm hover:shadow-md text-sm no-underline">
            <i class="fas fa-plus-circle"></i> Registrar Usuario
        </a>
    </div>
    <div class="admin-card-body">
        <!-- Buscador -->
        <div class="search-box" data-tab="users">
            <div class="search-group">
                <label><i class="fas fa-id-card mr-1"></i> Buscar por Cédula</label>
                <input type="text" id="searchCedula" placeholder="Ej: 12345678..." value="<?= htmlspecialchars($search_cedula) ?>">
            </div>
            <div class="search-actions">
                <button class="btn-search" id="searchBtn"><i class="fas fa-search"></i> Buscar</button>
                <button class="btn-clear" id="clearBtn"><i class="fas fa-times"></i> Limpiar</button>
            </div>
        </div>

        <!-- Contenedor de tabla con overflow-x para scroll horizontal -->
        <div class="overflow-x-auto">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Correo</th>
                        <th>Cédula</th>
                        <th>Teléfono</th>
                        <th>Nacimiento</th>
                        <th>Rol</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-8 text-gray-500">
                                <?php if ($search_cedula !== ''): ?>
                                    <i class="fas fa-search text-2xl mb-2 block text-gray-300"></i>
                                    <p>No se encontraron usuarios con la cédula «<?= htmlspecialchars($search_cedula) ?>».</p>
                                <?php else: ?>
                                    <i class="fas fa-users text-2xl mb-2 block text-gray-300"></i>
                                    <p>No hay usuarios registrados.</p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $index => $u): ?>
                            <?php
                            $is_self = $u['id'] == $_SESSION['user_id'];
                            $is_blocked = $u['is_blocked'] == 1;
                            $initial = strtoupper(substr($u['name'] ?? 'U', 0, 1));
                            $birth_date = $u['birth_date'] ? formatDateVenezuela($u['birth_date']) : '—';
                            ?>
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full <?= $is_blocked ? 'bg-gray-100 text-gray-400' : 'bg-indigo-100 text-indigo-600' ?> flex items-center justify-center font-bold flex-shrink-0">
                                            <?= htmlspecialchars($initial) ?>
                                        </div>
                                        <div class="flex flex-col min-w-0">
                                            <span class="font-medium text-gray-900">
                                                <?= htmlspecialchars($u['name']) ?>
                                                <?php if ($is_self): ?><span class="badge badge-b ml-1">Tú</span><?php endif; ?>
                                            </span>
                                            <small class="text-gray-400">#<?= $u['id'] ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-gray-700"><?= htmlspecialchars($u['email']) ?></td>
                                <td class="text-gray-700"><?= $u['cedula_type'] ? htmlspecialchars($u['cedula_type'] . '-' . $u['cedula_number']) : '<span class="text-gray-400">—</span>' ?></td>
                                <td class="text-gray-700"><?= $u['phone_number'] ? htmlspecialchars((substr($u['phone_prefix'], 0, 1) === '+' ? $u['phone_prefix'] : '0' . $u['phone_prefix']) . '-' . $u['phone_number']) : '<span class="text-gray-400">—</span>' ?></td>
                                <td class="text-gray-700"><?= $birth_date ?></td>
                                <td><?= $u['role'] === 'admin' ? '<span class="badge badge-a"><i class="fas fa-shield-halved"></i> Admin</span>' : '<span class="badge badge-c"><i class="fas fa-user"></i> Usuario</span>' ?></td>
                                <td class="text-center">
                                    <span class="status-badge <?= $is_blocked ? 'status-inactive' : 'status-active' ?>">
                                        <i class="fas fa-circle"></i> <?= $is_blocked ? 'Bloqueado' : 'Activo' ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="table-actions">
                                        <a href="index.php?tab=users&action=register&edit_user_id=<?= $u['id'] ?>" class="action-btn action-edit" title="Editar usuario">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        <?php if (!$is_self): ?>
                                            <form method="POST" action="modules/user/user_actions.php" class="inline-form" style="display: inline-block;">
                                                <input type="hidden" name="action" value="toggle_block_user">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <input type="hidden" name="current_status" value="<?= $u['is_blocked'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                <input type="hidden" name="return" value="../../index.php?tab=users">
                                                <button type="submit" class="action-btn <?= $is_blocked ? 'action-edit' : 'action-toggle' ?>" title="<?= $is_blocked ? 'Desbloquear usuario' : 'Bloquear usuario' ?>">
                                                    <i class="fas <?= $is_blocked ? 'fa-lock-open' : 'fa-lock' ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="modules/user/user_actions.php" class="inline-form" style="display: inline-block;">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                <input type="hidden" name="return" value="../../index.php?tab=users">
                                                <button type="submit" class="action-btn action-delete" data-delete-user data-user-name="<?= htmlspecialchars($u['name']) ?>" title="Eliminar usuario">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>