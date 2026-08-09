<?php
require_once 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
header('Location: login.php');
exit;
}
$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;
$is_edit = $room_id > 0;
// Obtener datos de la sala si existe
$room = null;
if ($is_edit) {
$stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
$stmt->execute([$room_id]);
$room = $stmt->fetch();
if (!$room) {
header('Location: admin.php?tab=rooms');
exit;
}
}
$csrf_token = generateCSRFToken();
// Procesar guardado
$msg = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_room'])) {
if (!verifyCSRFToken($_POST['csrf_token'])) {
$error = "Error de seguridad: Token inválido.";
} else {
$name = trim($_POST['room_name']);
$capacity = intval($_POST['capacity']);
$description = trim($_POST['room_description']);
$seat_layout = $_POST['seat_layout'] ?? '{}';
$aisle_config = $_POST['aisle_config'] ?? '{}';
if (empty($name) || $capacity <= 0) {
$error = "El nombre es obligatorio y la capacidad debe ser mayor a 0.";
} else {
try {
if ($is_edit) {
$stmt = $pdo->prepare("UPDATE rooms SET name=?, capacity=?, description=?, seat_layout=?, aisle_config=? WHERE id=?");
$stmt->execute([$name, $capacity, $description, $seat_layout, $aisle_config, $room_id]);
$msg = "Sala actualizada exitosamente.";
// Recargar datos tras actualización
$stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
$stmt->execute([$room_id]);
$room = $stmt->fetch();
} else {
$stmt = $pdo->prepare("INSERT INTO rooms (name, capacity, description, seat_layout, aisle_config) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$name, $capacity, $description, $seat_layout, $aisle_config]);
$room_id = $pdo->lastInsertId();
$msg = "Sala creada exitosamente.";
$is_edit = true;
// Recargar datos
$stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
$stmt->execute([$room_id]);
$room = $stmt->fetch();
}
} catch (PDOException $e) {
$error = "Error al guardar: " . $e->getMessage();
}
}
}
}
// Decodificar layout existente
$default_layout = [
'rows' => ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'],
'seatsPerRow' => 21,
'seatMap' => [],
'totalSeats' => 210,
'blockedSeats' => [],
'wheelchairSeats' => []
];
$layout = $default_layout;
if ($room && $room['seat_layout']) {
$decoded = json_decode($room['seat_layout'], true);
if ($decoded) {
$layout = array_merge($default_layout, $decoded);
}
}
// Calcular asientos disponibles
$totalSeats = 0;
foreach ($layout['rows'] as $row) {
$seatsInRow = $layout['seatMap'][$row] ?? [];
$totalSeats += count($seatsInRow);
}
$blockedSeats = $layout['blockedSeats'] ?? [];
$wheelchairSeats = $layout['wheelchairSeats'] ?? [];
$availableSeats = $totalSeats - count($blockedSeats);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Constructor Visual de Salas - Cinema Pro</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
.builder-container {
display: grid;
grid-template-columns: 1fr 380px;
gap: 24px;
min-height: 600px;
}
.seat-grid {
display: flex;
flex-direction: column;
gap: 6px;
padding: 16px;
background: #111827;
border-radius: 12px;
border: 1px solid #374151;
min-height: 320px;
overflow-x: auto;
}
.seat-grid .row {
display: flex;
gap: 4px;
align-items: center;
padding: 4px 8px;
border-radius: 6px;
transition: background 0.2s ease;
cursor: pointer;
width: max-content;
min-width: 100%;
}
.seat-grid .row:hover {
background: #1f2937;
}
.seat-grid .row.selected-row {
background: #1e1b4b;
border: 1px solid #6366f1;
}
.seat-grid .row-label {
width: 28px;
font-size: 0.8rem;
color: #9ca3af;
font-weight: 700;
text-align: right;
padding-right: 8px;
user-select: none;
flex-shrink: 0;
}
.seat-item {
width: 26px;
height: 26px;
border-radius: 6px 6px 8px 8px;
background: #4b5563;
cursor: pointer;
transition: all 0.15s ease;
display: flex;
align-items: center;
justify-content: center;
font-size: 0.65rem;
color: #ffffff;
user-select: none;
position: relative;
border: 1px solid rgba(255,255,255,0.1);
flex-shrink: 0;
font-weight: 700;
}
.seat-item:hover {
transform: translateY(-2px);
box-shadow: 0 4px 10px rgba(99, 102, 241, 0.4);
}
.seat-item.selected {
background: #4f46e5;
border-color: #818cf8;
box-shadow: 0 0 12px rgba(99, 102, 241, 0.5);
}
.seat-item.blocked {
background: #1f2937 !important;
border: 1px dashed #4b5563 !important;
cursor: pointer;
opacity: 0.4;
box-shadow: none !important;
transform: none !important;
}
.seat-item.blocked::after {
content: '✕';
font-size: 10px;
color: #9ca3af;
}
.seat-item.blocked .seat-number {
display: none;
}
/* Estilos Asiento Discapacitados */
.seat-item.wheelchair {
background: #0284c7 !important;
border-color: #38bdf8 !important;
box-shadow: 0 0 8px rgba(56, 189, 248, 0.4);
}
.seat-item.wheelchair::after {
content: '♿';
font-size: 11px;
color: #ffffff;
}
.seat-item.wheelchair .seat-number {
display: none;
}
.btn-inline-add {
background: #16a34a;
color: #ffffff;
border: 1px solid #22c55e;
border-radius: 4px;
padding: 2px 6px;
font-size: 0.75rem;
font-weight: 800;
cursor: pointer;
transition: all 0.15s ease;
}
.btn-inline-add:hover {
background: #15803d;
box-shadow: 0 0 8px rgba(34, 197, 94, 0.4);
}
.btn-inline-remove {
background: #dc2626;
color: #ffffff;
border: 1px solid #ef4444;
border-radius: 4px;
padding: 2px 6px;
font-size: 0.75rem;
font-weight: 800;
cursor: pointer;
transition: all 0.15s ease;
}
.btn-inline-remove:hover {
background: #b91c1c;
box-shadow: 0 0 8px rgba(239, 68, 68, 0.4);
}
.screen-display {
background: #ffffff;
border: 1px solid #e5e7eb;
color: #111827;
text-align: center;
padding: 8px;
border-radius: 6px;
margin-top: 16px;
font-weight: 800;
letter-spacing: 3px;
font-size: 0.85rem;
text-transform: uppercase;
box-shadow: none !important;
}
.toolbar {
display: flex;
gap: 8px;
flex-wrap: wrap;
margin-bottom: 16px;
}
.btn-action {
display: inline-flex;
align-items: center;
gap: 6px;
padding: 6px 12px;
border-radius: 8px;
font-size: 0.75rem;
font-weight: 600;
transition: all 0.2s ease;
border: 1px solid transparent;
cursor: pointer;
box-shadow: 0 1px 3px rgba(0,0,0,0.3);
}
.btn-action:hover {
transform: translateY(-1px);
box-shadow: 0 4px 6px rgba(0,0,0,0.4);
}
.btn-add-row { background: #15803d; color: white; border-color: #22c55e; }
.btn-add-row:hover { background: #166534; }
.btn-remove-row { background: #991b1b; color: white; border-color: #ef4444; }
.btn-remove-row:hover { background: #7f1d1d; }
.btn-add-seat { background: #1d4ed8; color: white; border-color: #3b82f6; }
.btn-add-seat:hover { background: #1e40af; }
.btn-remove-seat { background: #b45309; color: white; border-color: #f59e0b; }
.btn-remove-seat:hover { background: #92400e; }
.btn-clear { background: #374151; color: #d1d5db; border-color: #4b5563; }
.btn-clear:hover { background: #4b5563; color: white; }
.btn-delete-all { background: #7f1d1d; color: #fca5a5; border-color: #ef4444; }
.btn-delete-all:hover { background: #991b1b; color: white; }
.stats {
display: grid;
grid-template-columns: repeat(5, 1fr);
gap: 6px;
margin-top: 16px;
padding: 12px;
background: #111827;
border-radius: 8px;
border: 1px solid #374151;
}
.stats .stat-item {
text-align: center;
}
.stats .stat-value {
font-size: 1.1rem;
font-weight: 700;
color: #818cf8;
}
.stats .stat-label {
font-size: 0.6rem;
color: #9ca3af;
text-transform: uppercase;
}
.row-controls {
display: flex;
gap: 4px;
margin-left: 6px;
flex-shrink: 0;
}
.row-controls button {
padding: 2px 6px;
font-size: 0.65rem;
border-radius: 4px;
border: 1px solid transparent;
cursor: pointer;
transition: all 0.15s ease;
}
.row-controls .btn-row-select {
background: #3730a3;
color: white;
border-color: #4f46e5;
}
.row-controls .btn-row-select:hover { background: #4338ca; }
.row-controls .btn-row-select.active { background: #6366f1; }
.row-controls .btn-row-delete {
background: #991b1b;
color: white;
border-color: #dc2626;
}
.row-controls .btn-row-delete:hover { background: #b91c1c; }
/* Contenedor y botones de modo */
.mode-toggle {
display: inline-flex;
gap: 6px;
padding: 6px;
background: #111827;
border-radius: 10px;
border: 1px solid #374151;
}
.mode-toggle .mode-btn {
padding: 8px 16px;
border-radius: 8px;
font-size: 0.85rem;
font-weight: 600;
border: none;
cursor: pointer;
transition: all 0.2s ease;
}
.mode-toggle .mode-btn.active {
background: #4f46e5;
color: white;
box-shadow: 0 1px 3px rgba(0,0,0,0.3);
}
.mode-toggle .mode-btn.active-wheelchair {
background: #0284c7;
color: white;
box-shadow: 0 1px 3px rgba(0,0,0,0.3);
}
.mode-toggle .mode-btn:not(.active):not(.active-wheelchair) {
background: transparent;
color: #9ca3af;
}
.mode-toggle .mode-btn:not(.active):not(.active-wheelchair):hover {
color: #ffffff;
}
@media (max-width: 1024px) {
.builder-container {
grid-template-columns: 1fr;
}
}
@media (max-width: 640px) {
.seat-item {
width: 22px;
height: 22px;
font-size: 0.55rem;
}
.seat-grid .row-label {
width: 20px;
font-size: 0.7rem;
}
.toolbar {
gap: 6px;
}
.btn-action {
padding: 5px 8px;
font-size: 0.7rem;
}
.mode-toggle .mode-btn {
padding: 6px 10px;
font-size: 0.75rem;
}
.stats {
grid-template-columns: repeat(3, 1fr);
}
}
</style>
</head>
<body class="bg-gray-900 text-white min-h-screen p-3 sm:p-6">
<div class="max-w-7xl mx-auto">
<!-- Header -->
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4 bg-gray-800 p-4 rounded-xl border border-gray-700 shadow-md">
<div>
<h1 class="text-lg sm:text-2xl font-bold text-indigo-400 flex items-center gap-2">🛠️ Constructor Visual de Salas</h1>
<p class="text-xs sm:text-sm text-gray-400 mt-1">Diseña la distribución de asientos, bloquea filas o asigna accesibilidad de forma sencilla.</p>
</div>
<div class="flex gap-2 w-full sm:w-auto">
<a href="admin.php?tab=rooms" class="flex-1 sm:flex-none text-center bg-gray-700 hover:bg-gray-600 px-4 py-2 rounded-lg text-xs sm:text-sm font-semibold transition-colors border border-gray-600">← Volver</a>
<a href="logout.php" class="flex-1 sm:flex-none text-center bg-red-600/20 hover:bg-red-600/40 text-red-400 px-4 py-2 rounded-lg text-xs sm:text-sm font-semibold transition-colors border border-red-500/30">Cerrar</a>
</div>
</div>
<!-- Mensajes -->
<?php if($msg): ?>
<div class="bg-green-600/20 text-green-400 p-3 rounded-lg mb-4 font-medium border border-green-500/30 text-sm flex items-center gap-2">
<span>✅</span> <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>
<?php if($error): ?>
<div class="bg-red-600/20 text-red-400 p-3 rounded-lg mb-4 font-medium border border-red-500/30 text-sm flex items-center gap-2">
<span>⚠️</span> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>
<div class="builder-container">
<!-- Constructor Visual -->
<div class="bg-gray-800 p-4 sm:p-6 rounded-xl shadow-xl border border-gray-700 flex flex-col justify-between">
<div>
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-2">
<h2 class="text-base sm:text-lg font-bold text-indigo-300">
<?= $is_edit ? '✏️ Editando: ' . htmlspecialchars($room['name']) : '📐 Configuración Visual' ?>
</h2>
<span class="text-xs text-gray-400 bg-gray-900 px-3 py-1 rounded-full border border-gray-700" id="seatCountDisplay">
Asientos: <?= $totalSeats ?> | Disponibles: <?= $availableSeats ?>
</span>
</div>
<!-- Toolbar -->
<div class="toolbar">
<button class="btn-action btn-add-row" onclick="addRow()">➕ Añadir Fila</button>
<button class="btn-action btn-remove-row" onclick="removeRow()">➖ Quitar Fila</button>
<button class="btn-action btn-add-seat" onclick="addSeatToRow()">➕ Añadir Asiento</button>
<button class="btn-action btn-remove-seat" onclick="removeSeatFromRow()">➖ Quitar Asiento</button>
<button class="btn-action btn-clear" onclick="resetToDefault()">🔄 Reiniciar</button>
<button class="btn-action btn-delete-all" onclick="clearSeats()">🗑️ Borrar Todo</button>
</div>
<!-- Modo de selección -->
<div class="flex items-center gap-3 mb-4 flex-wrap">
<div class="mode-toggle">
<button class="mode-btn active" id="modeSelect" onclick="setMode('select')">Seleccionar</button>
<button class="mode-btn" id="modeBlock" onclick="setMode('block')">🚫 Bloquear (Pasillo)</button>
<button class="mode-btn" id="modeWheelchair" onclick="setMode('wheelchair')">♿ Discapacidad</button>
</div>
<span id="modeHelp" class="text-xs text-gray-400">Clic en un asiento para seleccionarlo</span>
</div>
<!-- Filas seleccionadas -->
<div class="mb-3 flex items-center gap-2 text-xs">
<span class="text-gray-400">Filas activas:</span>
<span id="selectedRowDisplay" class="font-bold text-indigo-400">Ninguna</span>
<button onclick="clearSelectedRows()" class="text-red-400 hover:text-red-300 ml-2">✕ Limpiar selección</button>
</div>
<!-- Grid de Asientos -->
<div id="seatGrid" class="seat-grid">
<!-- Generado dinámicamente por JS -->
</div>
<!-- Pantalla -->
<div class="screen-display">🎬 PANTALLA</div>
</div>
<!-- Stats Reordenadas: Total, Filas, Bloqueados, Discapacidad, Disponibles -->
<div class="stats">
<div class="stat-item">
<div class="stat-value" id="statTotalSeats">0</div>
<div class="stat-label">Total</div>
</div>
<div class="stat-item">
<div class="stat-value" id="statRows">0</div>
<div class="stat-label">Filas</div>
</div>
<div class="stat-item">
<div class="stat-value" id="statBlocked">0</div>
<div class="stat-label">Bloqueados</div>
</div>
<div class="stat-item">
<div class="stat-value text-sky-400" id="statWheelchair">0</div>
<div class="stat-label">Discapacidad</div>
</div>
<div class="stat-item">
<div class="stat-value text-green-400" id="statAvailable">0</div>
<div class="stat-label">Disponibles</div>
</div>
</div>
</div>
<!-- Panel de Configuración -->
<div class="bg-gray-800 p-4 sm:p-6 rounded-xl shadow-xl border border-gray-700 flex flex-col justify-between">
<form action="" method="POST" id="roomForm" class="space-y-4">
<h2 class="text-base sm:text-lg font-bold text-indigo-300 mb-2">⚙️ Propiedades de la Sala</h2>
<input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
<input type="hidden" name="save_room" value="1">
<input type="hidden" name="seat_layout" id="seatLayoutInput">
<input type="hidden" name="aisle_config" id="aisleConfigInput">
<div>
<label class="block text-xs font-semibold text-gray-300 mb-1">Nombre de la Sala *</label>
<input type="text" name="room_name" id="roomNameInput" required
value="<?= $is_edit ? htmlspecialchars($room['name']) : '' ?>"
placeholder="Ej: Sala 1 - VIP"
class="w-full bg-gray-900 px-3 py-2 rounded-lg text-white border border-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm">
</div>
<div>
<div class="flex justify-between items-center mb-1">
<label class="block text-xs font-semibold text-gray-300">Capacidad Total *</label>
<span class="text-[10px] text-indigo-400 font-medium">🔒 Calculado automáticamente</span>
</div>
<input type="number" name="capacity" id="capacityInput" readonly required min="1"
value="<?= $is_edit ? $room['capacity'] : '0' ?>"
class="w-full bg-gray-900/60 px-3 py-2 rounded-lg text-indigo-300 font-bold border border-gray-700 focus:outline-none text-sm cursor-not-allowed">
<p class="text-[11px] text-gray-400 mt-1">Representa únicamente los asientos disponibles activos.</p>
</div>
<div>
<label class="block text-xs font-semibold text-gray-300 mb-1">Descripción</label>
<textarea name="room_description" rows="2"
placeholder="Detalles sobre tecnología, pantalla o sonido..."
class="w-full bg-gray-900 px-3 py-2 rounded-lg text-white border border-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm resize-none"><?= $is_edit ? htmlspecialchars($room['description']) : '' ?></textarea>
</div>
<hr class="border-gray-700 my-4">
<!-- Leyenda con Discapacitados -->
<div class="text-xs">
<p class="font-semibold text-gray-300 mb-2">📋 Simbología:</p>
<div class="grid grid-cols-2 gap-2 text-[11px]">
<div class="flex items-center gap-2"><span class="w-3.5 h-3.5 bg-gray-600 rounded"></span> Disponible</div>
<div class="flex items-center gap-2"><span class="w-3.5 h-3.5 bg-indigo-600 rounded"></span> Seleccionado</div>
<div class="flex items-center gap-2"><span class="w-3.5 h-3.5 bg-sky-600 rounded"></span> Discapacidad ♿</div>
<div class="flex items-center gap-2"><span class="w-3.5 h-3.5 bg-gray-800 border border-gray-600 rounded"></span> Bloqueado</div>
</div>
</div>
<hr class="border-gray-700 my-4">
<!-- Botones de Acción -->
<button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-3 rounded-lg transition-all shadow-lg text-sm flex items-center justify-center gap-2">
💾 <?= $is_edit ? 'Actualizar Sala' : 'Guardar y Crear Sala' ?>
</button>
<div class="grid grid-cols-2 gap-2 mt-2">
<a href="admin.php?tab=rooms" class="w-full bg-red-600/20 hover:bg-red-600/40 text-red-400 font-semibold py-2.5 rounded-lg transition-all text-sm flex items-center justify-center gap-1 border border-red-500/30">
❌ Cancelar
</a>
<a href="admin.php?tab=rooms" class="w-full bg-gray-700 hover:bg-gray-600 text-gray-300 font-semibold py-2.5 rounded-lg transition-all text-sm flex items-center justify-center gap-1 border border-gray-600">
⬅️ Volver
</a>
</div>
</form>
</div>
</div>
</div>
<script>
const alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
// ESTADO DE LA SALA
let state = {
rows: [],
seatMap: {},
blockedSeats: [],
wheelchairSeats: [],
totalSeats: 0,
availableSeats: 0
};
let selectedRows = [];
let currentMode = 'select';
let selectedSeats = [];
// INICIALIZACIÓN
document.addEventListener('DOMContentLoaded', function() {
<?php if ($is_edit && $room && $room['seat_layout']):
$layout_data = json_decode($room['seat_layout'], true);
$total = 0;
foreach ($layout_data['rows'] ?? [] as $row) {
$total += count($layout_data['seatMap'][$row] ?? []);
}
$blocked = count($layout_data['blockedSeats'] ?? []);
?>
state.rows = <?= json_encode($layout_data['rows'] ?? ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J']) ?>;
state.seatMap = <?= json_encode($layout_data['seatMap'] ?? []) ?>;
state.blockedSeats = <?= json_encode($layout_data['blockedSeats'] ?? []) ?>;
state.wheelchairSeats = <?= json_encode($layout_data['wheelchairSeats'] ?? []) ?>;
state.totalSeats = <?= $total ?>;
state.availableSeats = <?= $total - $blocked ?>;
<?php else: ?>
applyDefaultState();
<?php endif; ?>
reindexRows();
renderGrid();
updateStats();
updateForm();
});
function applyDefaultState() {
const defaultRows = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
const seatsPerRow = 21;
state.rows = defaultRows;
state.seatMap = {};
defaultRows.forEach(row => {
state.seatMap[row] = Array.from({length: seatsPerRow}, (_, i) => i + 1);
});
state.totalSeats = defaultRows.length * seatsPerRow;
state.blockedSeats = [];
state.wheelchairSeats = [];
state.availableSeats = state.totalSeats;
selectedSeats = [];
selectedRows = [];
}
// REINDEXACIÓN DE FILAS (A, B, C...)
function reindexRows() {
if (state.rows.length === 0) return;
let newRows = [];
let newSeatMap = {};
let newBlockedSeats = [];
let newWheelchairSeats = [];
let newSelectedSeats = [];
state.rows.forEach((oldRow, index) => {
if (index < alphabet.length) {
let newRow = alphabet[index];
newRows.push(newRow);
newSeatMap[newRow] = state.seatMap[oldRow] || [];
(state.seatMap[oldRow] || []).forEach(seatNum => {
let oldSeatId = oldRow + seatNum;
let newSeatId = newRow + seatNum;
if (state.blockedSeats.includes(oldSeatId)) {
newBlockedSeats.push(newSeatId);
}
if (state.wheelchairSeats.includes(oldSeatId)) {
newWheelchairSeats.push(newSeatId);
}
if (selectedSeats.includes(oldSeatId)) {
newSelectedSeats.push(newSeatId);
}
});
}
});
state.rows = newRows;
state.seatMap = newSeatMap;
state.blockedSeats = newBlockedSeats;
state.wheelchairSeats = newWheelchairSeats;
selectedSeats = newSelectedSeats;
selectedRows = [];
}
// MODO DE SELECCIÓN
function setMode(mode) {
currentMode = mode;
document.getElementById('modeSelect').className = 'mode-btn' + (mode === 'select' ? ' active' : '');
document.getElementById('modeBlock').className = 'mode-btn' + (mode === 'block' ? ' active' : '');
document.getElementById('modeWheelchair').className = 'mode-btn' + (mode === 'wheelchair' ? ' active-wheelchair' : '');
const help = document.getElementById('modeHelp');
if (mode === 'select') {
help.textContent = 'Clic en un asiento para seleccionarlo';
help.className = 'text-xs text-gray-400';
} else if (mode === 'block') {
help.textContent = 'Clic en un asiento para bloquearlo o convertirlo en pasillo';
help.className = 'text-xs text-indigo-400 font-medium';
} else if (mode === 'wheelchair') {
help.textContent = 'Clic en un asiento para definirlo como accesible (Discapacidad)';
help.className = 'text-xs text-sky-400 font-medium';
}
}
// RENDERIZADO DEL GRID
function renderGrid() {
const grid = document.getElementById('seatGrid');
if (!grid) return;
let html = '';
const reversedRows = [...state.rows].reverse();
reversedRows.forEach((row) => {
const isSelected = selectedRows.includes(row);
const seats = state.seatMap[row] || [];
html += `<div class="row ${isSelected ? 'selected-row' : ''}" data-row="${row}" onclick="toggleSelectRow('${row}')">`;
html += `<span class="row-label">${row}</span>`;
html += `<div class="row-controls">`;
html += `<button class="btn-row-select ${isSelected ? 'active' : ''}" onclick="event.stopPropagation(); toggleSelectRow('${row}')" title="Seleccionar fila">📌</button>`;
if (state.rows.length > 1) {
html += `<button class="btn-row-delete" onclick="event.stopPropagation(); deleteSingleRow('${row}')" title="Eliminar fila">✕</button>`;
}
html += `</div>`;
seats.forEach((seatNum) => {
const seatId = row + seatNum;
const isSelectedSeat = selectedSeats.includes(seatId);
const isBlocked = state.blockedSeats.includes(seatId);
const isWheelchair = state.wheelchairSeats.includes(seatId);
let classes = 'seat-item';
if (isBlocked) classes += ' blocked';
else if (isWheelchair) classes += ' wheelchair';
if (isSelectedSeat) classes += ' selected';
let titleText = `Asiento ${seatId}`;
if (isBlocked) titleText = 'Bloqueado (clic para desbloquear)';
else if (isWheelchair) titleText = `Asiento preferencial ♿ ${seatId}`;
html += `<div class="${classes}"
data-seat="${seatId}"
onclick="event.stopPropagation(); handleSeatClick('${seatId}')"
title="${titleText}">
<span class="seat-number">${seatNum}</span>
</div>`;
});
html += `<div class="flex items-center ml-2 gap-1">`;
html += `<button onclick="event.stopPropagation(); addSeatToSpecificRow('${row}')"
class="btn-inline-add"
title="Añadir asiento a esta fila">+</button>`;
html += `<button onclick="event.stopPropagation(); removeSeatFromSpecificRow('${row}')"
class="btn-inline-remove"
title="Eliminar último asiento de esta fila">-</button>`;
html += `</div>`;
html += `</div>`;
});
grid.innerHTML = html;
updateSelectedRowDisplay();
}
// CLIC EN ASIENTO
function handleSeatClick(seatId) {
if (currentMode === 'block') {
toggleBlockSeat(seatId);
} else if (currentMode === 'wheelchair') {
toggleWheelchairSeat(seatId);
} else {
toggleSeat(seatId);
}
}
function toggleBlockSeat(seatId) {
// Remover de discapacitados si estaba
const wcIndex = state.wheelchairSeats.indexOf(seatId);
if (wcIndex > -1) state.wheelchairSeats.splice(wcIndex, 1);
const index = state.blockedSeats.indexOf(seatId);
if (index > -1) {
state.blockedSeats.splice(index, 1);
} else {
const selIndex = selectedSeats.indexOf(seatId);
if (selIndex > -1) selectedSeats.splice(selIndex, 1);
state.blockedSeats.push(seatId);
}
state.availableSeats = state.totalSeats - state.blockedSeats.length;
renderGrid();
updateStats();
updateForm();
}
function toggleWheelchairSeat(seatId) {
// No permite deshabilitar un asiento que esté totalmente bloqueado
if (state.blockedSeats.includes(seatId)) return;
const index = state.wheelchairSeats.indexOf(seatId);
if (index > -1) {
state.wheelchairSeats.splice(index, 1);
} else {
state.wheelchairSeats.push(seatId);
}
renderGrid();
updateStats();
updateForm();
}
function toggleSeat(seatId) {
if (state.blockedSeats.includes(seatId)) return;
const index = selectedSeats.indexOf(seatId);
if (index > -1) {
selectedSeats.splice(index, 1);
} else {
selectedSeats.push(seatId);
}
renderGrid();
updateStats();
updateForm();
}
// SELECCIÓN MÚLTIPLE DE FILAS
function toggleSelectRow(row) {
const index = selectedRows.indexOf(row);
if (index > -1) {
selectedRows.splice(index, 1);
} else {
selectedRows.push(row);
}
renderGrid();
updateStats();
updateSelectedRowDisplay();
}
function clearSelectedRows() {
selectedRows = [];
renderGrid();
updateStats();
updateSelectedRowDisplay();
}
function updateSelectedRowDisplay() {
const display = document.getElementById('selectedRowDisplay');
if (selectedRows.length > 0) {
display.textContent = selectedRows.sort().join(', ');
display.className = 'font-bold text-indigo-400';
} else {
display.textContent = 'Ninguna';
display.className = 'text-gray-500';
}
}
// ACCIONES DE BARRAS DE HERRAMIENTAS
function addSingleRow() {
const nextLetter = getNextRowLetter();
if (!nextLetter) return false;
state.rows.push(nextLetter);
const seatsPerRow = state.rows.length > 1 ? (state.seatMap[state.rows[0]]?.length || 10) : 10;
state.seatMap[nextLetter] = Array.from({length: seatsPerRow}, (_, i) => i + 1);
state.totalSeats += seatsPerRow;
state.availableSeats = state.totalSeats - state.blockedSeats.length;
return nextLetter;
}
function addRow() {
const countToAdd = selectedRows.length > 0 ? selectedRows.length : 1;
let addedRows = [];
for (let i = 0; i < countToAdd; i++) {
const newRow = addSingleRow();
if (newRow) {
addedRows.push(newRow);
} else {
alert('Límite de filas alcanzado (A-Z)');
break;
}
}
if (addedRows.length > 0) {
reindexRows();
renderGrid();
updateStats();
updateForm();
}
}
function removeSingleRowData(row) {
const seatsToRemove = state.seatMap[row] || [];
seatsToRemove.forEach(seatNum => {
const seatId = row + seatNum;
const blockIndex = state.blockedSeats.indexOf(seatId);
if (blockIndex > -1) state.blockedSeats.splice(blockIndex, 1);
const wcIndex = state.wheelchairSeats.indexOf(seatId);
if (wcIndex > -1) state.wheelchairSeats.splice(wcIndex, 1);
});
state.rows = state.rows.filter(r => r !== row);
delete state.seatMap[row];
state.totalSeats -= seatsToRemove.length;
state.availableSeats = state.totalSeats - state.blockedSeats.length;
}
function deleteSingleRow(row) {
if (state.rows.length <= 1) {
alert('La sala debe contener al menos una fila.');
return;
}
if (!confirm(`¿Eliminar la fila ${row}?`)) return;
removeSingleRowData(row);
reindexRows();
renderGrid();
updateStats();
updateForm();
}
function removeRow() {
if (selectedRows.length > 0) {
const count = selectedRows.length;
if (state.rows.length - count < 1) {
alert('No se pueden eliminar todas las filas. Debe quedar al menos una.');
return;
}
const rowsText = selectedRows.sort().join(', ');
if (!confirm(`¿Deseas eliminar las ${count} filas seleccionadas (${rowsText})?`)) return;
const rowsToDelete = [...selectedRows];
rowsToDelete.forEach(r => removeSingleRowData(r));
reindexRows();
renderGrid();
updateStats();
updateForm();
} else if (state.rows.length > 1) {
const lastRow = state.rows[state.rows.length - 1];
deleteSingleRow(lastRow);
} else {
alert('La sala debe contener al menos una fila.');
}
}
function addSeatToSpecificRow(targetRow) {
const seats = state.seatMap[targetRow] || [];
const newNum = seats.length > 0 ? Math.max(...seats) + 1 : 1;
seats.push(newNum);
state.seatMap[targetRow] = seats.sort((a, b) => a - b);
state.totalSeats++;
state.availableSeats = state.totalSeats - state.blockedSeats.length;
renderGrid();
updateStats();
updateForm();
}
function addSeatToRow() {
if (selectedRows.length > 0) {
selectedRows.forEach(row => addSeatToSpecificRow(row));
} else {
alert('Por favor, selecciona al menos una fila.');
}
}
function removeSeatFromSpecificRow(targetRow) {
const seats = state.seatMap[targetRow] || [];
if (seats.length <= 1) {
alert(`La fila ${targetRow} debe mantener al menos un asiento.`);
return;
}
const lastSeatNum = seats[seats.length - 1];
const lastSeatId = targetRow + lastSeatNum;
const blockIndex = state.blockedSeats.indexOf(lastSeatId);
if (blockIndex > -1) state.blockedSeats.splice(blockIndex, 1);
const wcIndex = state.wheelchairSeats.indexOf(lastSeatId);
if (wcIndex > -1) state.wheelchairSeats.splice(wcIndex, 1);
seats.pop();
state.seatMap[targetRow] = seats;
state.totalSeats--;
state.availableSeats = state.totalSeats - state.blockedSeats.length;
renderGrid();
updateStats();
updateForm();
}
function removeSeatFromRow() {
if (selectedRows.length > 0) {
selectedRows.forEach(row => removeSeatFromSpecificRow(row));
} else {
alert('Por favor, selecciona al menos una fila.');
}
}
function resetToDefault() {
if (!confirm('¿Deseas restablecer el mapa a la configuración por defecto (10 filas de 21 asientos)?')) return;
applyDefaultState();
reindexRows();
renderGrid();
updateStats();
updateForm();
}
function clearSeats() {
if (!confirm('¿Deseas borrar absolutamente todas las filas y asientos del mapa?')) return;
state.rows = [];
state.seatMap = {};
state.blockedSeats = [];
state.wheelchairSeats = [];
state.totalSeats = 0;
state.availableSeats = 0;
selectedSeats = [];
selectedRows = [];
renderGrid();
updateStats();
updateForm();
}
// ACTUALIZACIÓN DE FORMULARIO Y ESTADÍSTICAS
function updateStats() {
document.getElementById('statTotalSeats').textContent = state.totalSeats;
document.getElementById('statRows').textContent = state.rows.length;
document.getElementById('statBlocked').textContent = state.blockedSeats.length;
document.getElementById('statWheelchair').textContent = state.wheelchairSeats.length;
document.getElementById('statAvailable').textContent = state.availableSeats;
document.getElementById('seatCountDisplay').textContent = `Asientos: ${state.totalSeats} | Disponibles: ${state.availableSeats}`;
const capacityInput = document.getElementById('capacityInput');
if (capacityInput) {
capacityInput.value = state.availableSeats;
}
}
function updateForm() {
const layoutData = {
rows: state.rows,
seatsPerRow: state.rows.length > 0 ? (state.seatMap[state.rows[0]]?.length || 0) : 0,
seatMap: state.seatMap,
totalSeats: state.totalSeats,
blockedSeats: state.blockedSeats,
wheelchairSeats: state.wheelchairSeats
};
document.getElementById('seatLayoutInput').value = JSON.stringify(layoutData);
document.getElementById('aisleConfigInput').value = JSON.stringify({
blockedSeats: state.blockedSeats,
wheelchairSeats: state.wheelchairSeats
});
}
function getNextRowLetter() {
for (let i = 0; i < alphabet.length; i++) {
if (!state.rows.includes(alphabet[i])) {
return alphabet[i];
}
}
return null;
}
document.getElementById('roomForm')?.addEventListener('submit', function() {
updateForm();
});
</script>
</body>
</html>