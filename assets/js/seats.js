let selectedSeats = [...userPendingSeats];
const maxSeats = totalSeatsNeeded;
let skipUnloadRelease = false;
function formatCurrency(amount) {
if (typeof amount !== 'number' || isNaN(amount)) amount = 0;
const formatted = amount.toFixed(currencyConfig.decimals)
.replace('.', currencyConfig.decimal)
.replace(/\B(?=(\d{3})+(?!\d))/g, currencyConfig.thousands);
return currencyConfig.position === 'left' ? currencyConfig.symbol + formatted : formatted + ' ' + currencyConfig.symbol;
}
function showNotification(message, type = 'info', duration = 3000) {
const container = document.getElementById('notificationContainer');
if (!container) return;
container.innerHTML = '';
const notif = document.createElement('div');
notif.className = 'notification ' + type;
const icons = { info: 'fa-info-circle', success: 'fa-check-circle', warning: 'fa-exclamation-triangle', error: 'fa-times-circle' };
notif.innerHTML = `<span class="notif-icon"><i class="fas ${icons[type] || icons.info}"></i></span><span>${message}</span>`;
container.appendChild(notif);
setTimeout(() => {
notif.classList.add('fade-out');
setTimeout(() => { if (notif.parentNode) notif.remove(); }, 300);
}, duration);
}
function saveSeatsToStorage() {
try {
sessionStorage.setItem('selected_seats_' + showtimeId, JSON.stringify(selectedSeats));
sessionStorage.setItem('selected_seats_count_' + showtimeId, selectedSeats.length);
sessionStorage.setItem('purchase_token_' + showtimeId, purchaseToken);
} catch (e) {}
}
function loadSeatsFromStorage() {
try {
const saved = sessionStorage.getItem('selected_seats_' + showtimeId);
if (saved) {
const parsed = JSON.parse(saved);
if (Array.isArray(parsed) && parsed.length > 0) {
const validSeats = parsed.filter(seat => !occupiedSeats.includes(seat) && !blockedSeats.includes(seat));
if (validSeats.length > 0) {
selectedSeats = validSeats;
return true;
}
}
}
} catch (e) {}
return false;
}
function liberarAsientos(callback) {
const formData = new FormData();
formData.append('showtime_id', showtimeId);
fetch('liberar_asientos.php', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
.then(response => response.json())
.then(data => { if (callback) callback(data.success); })
.catch(() => { if (callback) callback(false); });
}
// ============================================
// ✅ LIBERAR ASIENTOS AL CERRAR NAVEGADOR / PESTAÑA
// ============================================
let unloadReleaseSent = false;
function releaseSeatsOnUnload() {
if (skipUnloadRelease || unloadReleaseSent) return;
unloadReleaseSent = true;
// Limpiar solo la selección de ESTA función para que no se restaure al volver
try {
sessionStorage.removeItem('selected_seats_' + showtimeId);
sessionStorage.removeItem('selected_seats_count_' + showtimeId);
sessionStorage.removeItem('purchase_token_' + showtimeId);
} catch (e) {}
const formData = new FormData();
formData.append('showtime_id', showtimeId);
if (navigator.sendBeacon) {
navigator.sendBeacon('liberar_asientos.php', formData);
} else {
fetch('liberar_asientos.php', { method: 'POST', body: formData, keepalive: true });
}
}
window.addEventListener('pagehide', releaseSeatsOnUnload);
window.addEventListener('beforeunload', releaseSeatsOnUnload);
// ============================================
// ✅ F5 / RECARGA: liberar y redirigir con un solo F5
// ============================================
window.addEventListener('pageshow', function(event) {
const navEntry = (performance.getEntriesByType && performance.getEntriesByType('navigation')[0]) || null;
const isReload = (navEntry && navEntry.type === 'reload') ||
(window.performance && window.performance.navigation && window.performance.navigation.type === 1);
const isBackForward = event.persisted ||
(navEntry && navEntry.type === 'back_forward') ||
(window.performance && window.performance.navigation && window.performance.navigation.type === 2);
if (isBackForward) {
// ✅ Regreso por bfcache / botón atrás: liberar y mostrar los asientos libres en el acto
liberarAsientos(function() {});
selectedSeats = [];
try {
sessionStorage.removeItem('selected_seats_' + showtimeId);
sessionStorage.removeItem('selected_seats_count_' + showtimeId);
sessionStorage.removeItem('purchase_token_' + showtimeId);
} catch (e) {}
document.querySelectorAll('.seat-selected').forEach(function(seat) {
const seatId = seat.getAttribute('data-seat');
seat.classList.remove('seat-selected');
seat.classList.add(accessibleSeats.includes(seatId) ? 'seat-accessible' : 'seat-available');
});
updateSummary();
return;
}
if (isReload) {
skipUnloadRelease = true;
liberarAsientos(function() {
window.location.replace('index.php?expired=1');
});
}
});
function updateSummary() {
const count = selectedSeats.length;
const selectedSeatsList = document.getElementById('selected-seats-list');
const ticketCountEl = document.getElementById('ticket-count');
const seatsInput = document.getElementById('seats-input');
const btnContinue = document.getElementById('btn-continue');
const btnSeatsCount = document.getElementById('btnSeatsCount');
if (selectedSeatsList) selectedSeatsList.innerText = count > 0 ? selectedSeats.join(', ') : '-';
if (ticketCountEl) ticketCountEl.innerText = count + ' de ' + maxSeats;
if (btnSeatsCount) btnSeatsCount.textContent = count;
if (seatsInput) seatsInput.value = selectedSeats.join(',');
const subtotalEl = document.getElementById('subtotalAmount');
const taxEl = document.getElementById('taxAmount');
const totalEl = document.getElementById('totalAmount');
if (subtotalEl) subtotalEl.textContent = formatCurrency(subtotal);
if (taxEl) taxEl.textContent = formatCurrency(taxAmount);
if (totalEl) totalEl.textContent = formatCurrency(totalAmount);
if (btnContinue) {
if (count === maxSeats) {
btnContinue.disabled = false;
btnContinue.innerHTML = '<i class="fas fa-utensils mr-2"></i> Continuar a Comida';
btnContinue.classList.remove('opacity-50', 'cursor-not-allowed');
} else {
btnContinue.disabled = true;
btnContinue.classList.add('opacity-50', 'cursor-not-allowed');
const remaining = maxSeats - count;
btnContinue.innerHTML = `<i class="fas fa-chair mr-2"></i> Selecciona ${remaining} asiento${remaining !== 1 ? 's' : ''}`;
}
}
saveSeatsToStorage();
}
document.getElementById('btnBackToPrices').addEventListener('click', function() {
if (!confirm('¿Estás seguro? Se liberarán los asientos seleccionados.')) return;
liberarAsientos(function(success) {
if (success) {
skipUnloadRelease = true;
window.location.href = 'price_selection.php?showtime_id=' + showtimeId;
} else {
alert('Error al liberar asientos. Intenta nuevamente.');
}
});
});
document.getElementById('foodForm').addEventListener('submit', function(e) {
e.preventDefault();
const count = selectedSeats.length;
if (count === 0) {
showNotification('⚠️ Por favor, selecciona al menos un asiento.', 'warning');
return false;
}
if (count !== maxSeats) {
showNotification(`⚠️ Debes seleccionar ${maxSeats} asientos. Has seleccionado ${count}.`, 'warning');
return false;
}
const form = this;
const btnContinue = document.getElementById('btn-continue');
const tokenInput = document.getElementById('purchaseTokenInput');
const seatsInput = document.getElementById('seats-input');
if (seatsInput) {
seatsInput.value = selectedSeats.join(',');
}
btnContinue.disabled = true;
btnContinue.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Obteniendo token...';
fetch('get_purchase_token.php?showtime_id=' + showtimeId + '&t=' + Date.now())
.then(response => response.json())
.then(data => {
if (data.success && data.token) {
if (tokenInput) {
tokenInput.value = data.token;
}
purchaseToken = data.token;
btnContinue.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Reservando asientos...';
const formData = new FormData(form);
fetch('create_food_session.php', {
method: 'POST',
body: formData,
headers: { 'X-Requested-With': 'XMLHttpRequest' }
})
.then(response => response.json())
.then(sessionData => {
if (sessionData.success && sessionData.redirect) {
skipUnloadRelease = true;
window.location.href = sessionData.redirect;
} else {
showNotification('⚠️ ' + (sessionData.error || 'Error al procesar la compra'), 'error');
btnContinue.disabled = false;
btnContinue.innerHTML = '<i class="fas fa-chair mr-2"></i> Selecciona ' + count + ' asiento(s)';
}
})
.catch(error => {
console.error('Error:', error);
showNotification('⚠️ Error de conexión al reservar.', 'error');
btnContinue.disabled = false;
btnContinue.innerHTML = '<i class="fas fa-chair mr-2"></i> Selecciona ' + count + ' asiento(s)';
});
} else {
btnContinue.disabled = false;
btnContinue.innerHTML = '<i class="fas fa-chair mr-2"></i> Selecciona ' + count + ' asiento(s)';
showNotification('⚠️ Error al obtener token. Intenta nuevamente.', 'error');
}
})
.catch(error => {
console.error('Error obteniendo token:', error);
btnContinue.disabled = false;
btnContinue.innerHTML = '<i class="fas fa-chair mr-2"></i> Selecciona ' + count + ' asiento(s)';
showNotification('⚠️ Error de conexión. Intenta nuevamente.', 'error');
});
});
document.addEventListener('DOMContentLoaded', function() {
const seats = document.querySelectorAll('.seat:not(.seat-blocked)');
if (fromFood) loadSeatsFromStorage();
seats.forEach(seat => {
const seatId = seat.getAttribute('data-seat');
if (selectedSeats.includes(seatId)) {
seat.classList.add('seat-selected');
seat.classList.remove('seat-available', 'seat-accessible');
}
});
updateSummary();
seats.forEach(seat => {
seat.addEventListener('click', function() {
const seatId = this.getAttribute('data-seat');
if (blockedSeats.includes(seatId)) {
showNotification('🚫 Este es un pasillo, no se puede seleccionar', 'warning');
return;
}
if (occupiedSeats.includes(seatId) && !userPendingSeats.includes(seatId)) {
showNotification('❌ Este asiento ya ha sido reservado.', 'error');
return;
}
const index = selectedSeats.indexOf(seatId);
const isAccessible = accessibleSeats.includes(seatId);
if (index > -1) {
selectedSeats.splice(index, 1);
this.classList.remove('seat-selected');
this.classList.add(isAccessible ? 'seat-accessible' : 'seat-available');
} else {
if (selectedSeats.length >= maxSeats) {
showNotification(`⚠️ Ya tienes ${maxSeats} asientos seleccionados.`, 'warning', 4000);
return;
}
selectedSeats.push(seatId);
this.classList.remove('seat-available', 'seat-accessible');
this.classList.add('seat-selected');
}
updateSummary();
});
});
setInterval(function() {
fetch('check_seats.php?showtime_id=' + showtimeId)
.then(response => response.json())
.then(data => {
if (data.occupied) {
data.occupied.forEach(seatId => {
const seatEl = document.querySelector('[data-seat="' + seatId + '"]');
if (seatEl && !seatEl.classList.contains('seat-occupied')) {
const isAccessibleSeat = accessibleSeats.includes(seatId);
seatEl.classList.remove('seat-selected', 'seat-available');
seatEl.classList.add('seat-occupied');
if (isAccessibleSeat) seatEl.classList.add('seat-accessible');
seatEl.disabled = true;
const index = selectedSeats.indexOf(seatId);
if (index > -1) {
selectedSeats.splice(index, 1);
showNotification('⚠️ El asiento ' + seatId + ' acaba de ser reservado.', 'warning');
}
}
});
updateSummary();
}
})
.catch(err => console.log('Error checking seats:', err));
}, 15000);
});

