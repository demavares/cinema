<?php
// ============================================
// MÓDULO: SALAS — LISTADO
// ============================================
try {
    $rooms = $pdo->query("SELECT * FROM rooms ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    error_log("Error al consultar salas: " . $e->getMessage());
    echo '<div class="admin-alert admin-alert-error"><i class="fas fa-exclamation-circle"></i> Error al cargar las salas. Por favor, intente nuevamente.</div>';
    return;
}

$total_rooms = count($rooms);
?>

<div class="admin-content-header">
    <h1 class="admin-content-title">Salas</h1>
    <p class="admin-content-subtitle">Gestiona las salas del cine (<strong><?= $total_rooms ?></strong> salas)</p>
</div>

<!-- Lista de Salas -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">Todas las Salas</h3>
        <a href="index.php?tab=rooms&action=builder" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg transition-all duration-200 shadow-sm hover:shadow-md text-sm no-underline">
            <i class="fas fa-plus-circle"></i> Crear Nueva Sala
        </a>
    </div>
    <div class="admin-card-body">
        <div class="mb-4 p-4 rounded-lg bg-blue-50 border border-blue-300 text-blue-800 text-sm font-medium">
            <p><i class="fas fa-lightbulb mr-1"></i> Las salas se crean y editan desde el <strong>Constructor Visual</strong>.</p>
        </div>
        <!-- Contenedor de tabla con overflow-x para scroll horizontal -->
        <div class="overflow-x-auto">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Capacidad</th>
                        <th>Distribución</th>
                        <th>Configuración</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rooms)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-8 text-gray-500">
                                <i class="fas fa-door-open text-2xl mb-2 block text-gray-300"></i>
                                <p>No hay salas registradas. Crea una con el botón «Crear Nueva Sala».</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rooms as $r):
                            $layout = $r['seat_layout'] ? json_decode($r['seat_layout'], true) : null;
                            $blockedSeats = $layout['blockedSeats'] ?? [];
                            $hasBlocked = count($blockedSeats) > 0;
                        ?>
                            <tr>
                                <td class="font-medium text-gray-900"><?= htmlspecialchars($r['name']) ?></td>
                                <td class="text-gray-500"><?= htmlspecialchars($r['capacity']) ?></td>
                                <td class="text-gray-500">
                                    <?php if ($layout): ?>
                                        <span class="text-sm"><?= count($layout['rows'] ?? []) ?> filas × <?= $layout['seatsPerRow'] ?? 0 ?> asientos</span>
                                        <br><span class="text-xs text-gray-400">Total: <?= $layout['totalSeats'] ?? 0 ?></span>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($hasBlocked): ?>
                                        <span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-600 border border-amber-500/30">
                                            <i class="fas fa-ban"></i> <?= count($blockedSeats) ?> bloqueados
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-xs">Sin pasillos</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($r['is_active']): ?>
                                        <span class="status-badge status-active"><i class="fas fa-circle"></i> Activa</span>
                                    <?php else: ?>
                                        <span class="status-badge status-inactive"><i class="fas fa-circle"></i> Inactiva</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="table-actions">
                                        <a href="index.php?tab=rooms&action=builder&room_id=<?= htmlspecialchars($r['id']) ?>" class="action-btn action-edit" title="Diseñar sala">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        <a href="modules/room/room_actions.php?action=toggle_room&id=<?= $r['id'] ?>&csrf_token=<?= htmlspecialchars($csrf_token) ?>"
                                           class="action-btn <?= $r['is_active'] ? 'action-toggle' : 'action-edit' ?>" data-confirm="¿Cambiar estado de esta sala?" title="<?= $r['is_active'] ? 'Ocultar' : 'Mostrar' ?>">
                                            <i class="fas <?= $r['is_active'] ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                                        </a>
                                        <a href="modules/room/room_actions.php?action=delete_room&id=<?= $r['id'] ?>&csrf_token=<?= htmlspecialchars($csrf_token) ?>"
                                           class="action-btn action-delete" data-confirm="¿Eliminar esta sala permanentemente?" title="Eliminar sala">
                                            <i class="fas fa-trash"></i>
                                        </a>
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