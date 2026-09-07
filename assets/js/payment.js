let selectedPayment = null;
// ✅ Bandera para evitar liberar asientos al pagar
let skipUnloadRelease = false;
// ============================================
// ✅ NUEVO: GRACIA AL SALIR (cerrar pestaña / navegador)
// NO liberamos de inmediato: marcamos la compra para expirar en 20 s.
// - Si recargan (F5) o vuelven ("Volver a Comida"), el PHP restaura la reserva.
// - Si cerraron pestaña/navegador, la compra expira y la limpieza libera.
// ============================================
let unloadReleaseSent = false;
function releaseSeatsOnUnload() {
    if (skipUnloadRelease || unloadReleaseSent) return;
    unloadReleaseSent = true;
    const formData = new FormData();
    formData.append('showtime_id', showtimeId);
    formData.append('action', 'grace');
    if (navigator.sendBeacon) {
        navigator.sendBeacon('liberar_asientos.php', formData);
    } else {
        fetch('liberar_asientos.php', { method: 'POST', body: formData, keepalive: true });
    }
}
window.addEventListener('pagehide', releaseSeatsOnUnload);
window.addEventListener('beforeunload', releaseSeatsOnUnload);
document.addEventListener('DOMContentLoaded', function() {
    if (window.TimeoutManager) {
        TimeoutManager.init({
            showtimeId: showtimeId,
            seats: seats,
            initialTimeout: 600,
            syncInterval: 10000,
            redirectOnExpire: true,
            redirectUrl: 'index.php?timeout=1'
        });
    }
    // ============================================
    // CORREGIDO: Event listeners para métodos de pago
    // Reemplaza los onclick inline que eran bloqueados por CSP
    // ============================================
    document.querySelectorAll('.payment-method[data-payment-method]').forEach(function(methodCard) {
        methodCard.addEventListener('click', function() {
            const method = this.getAttribute('data-payment-method');
            if (method) {
                selectPayment(method);
            }
        });
    });
    // Listener para el submit del formulario de pago
    const paymentForm = document.getElementById('paymentForm');
    if (paymentForm) {
        paymentForm.addEventListener('submit', function(e) {
            if (!selectedPayment) {
                e.preventDefault();
                alert('Por favor, selecciona un método de pago.');
                return false;
            }
            // Activar bandera para evitar liberar asientos al pagar
            skipUnloadRelease = true;
            return true;
        });
    }
});
function selectPayment(method) {
    selectedPayment = method;
    const paymentMethodInput = document.getElementById('paymentMethodInput');
    if (paymentMethodInput) {
        paymentMethodInput.value = method;
    }
    // Remover selección de todos los métodos
    document.querySelectorAll('.payment-method').forEach(function(el) {
        el.classList.remove('selected');
    });
    // Seleccionar el método elegido
    const selectedMethod = document.getElementById('method-' + method);
    if (selectedMethod) {
        selectedMethod.classList.add('selected');
    }
    // Ocultar todos los detalles de pago
    document.querySelectorAll('.payment-details').forEach(function(el) {
        el.classList.remove('active');
    });
    // Mostrar los detalles del método seleccionado
    const selectedDetails = document.getElementById('details-' + method);
    if (selectedDetails) {
        selectedDetails.classList.add('active');
    }
    // Habilitar botón de pago
    const btnPay = document.getElementById('btnPay');
    if (btnPay) {
        btnPay.disabled = false;
    }
}

