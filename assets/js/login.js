
// ============================================
// TOGGLE PASSWORD VISIBILITY
// ============================================
function togglePasswordVisibility(inputId, button) {
    const input = document.getElementById(inputId);
    const icon = button.querySelector('i');
    if (!input || !icon) return;
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
        button.setAttribute('aria-label', 'Ocultar contraseña');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
        button.setAttribute('aria-label', 'Mostrar contraseña');
    }
}
// ✅ Reemplaza el onclick inline por delegación de eventos (CSP-safe)
document.addEventListener('click', function(event) {
    const button = event.target.closest('[data-password-toggle]');
    if (!button) return;
    const inputId = button.getAttribute('data-password-toggle');
    if (!inputId) return;
    togglePasswordVisibility(inputId, button);
});
// ============================================
// PREVENIR REENVÍO DEL FORMULARIO AL RECARGAR
// ============================================
if (window.history && window.history.replaceState) {
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
}
// ============================================
// VALIDACIÓN EN TIEMPO REAL
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('loginPassword');
    const submitBtn = document.getElementById('submitBtn');
    const loginForm = document.getElementById('loginForm');
    emailInput.addEventListener('blur', function() {
        const email = this.value.trim();
        if (email && !email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
            this.style.borderColor = '#ef4444';
        } else {
            this.style.borderColor = '#cbd5e1';
        }
    });
    loginForm.addEventListener('submit', function(e) {
        if (submitBtn.disabled) {
            e.preventDefault();
            return false;
        }
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verificando...';
        submitBtn.disabled = true;
    });
    if (!emailInput.value) {
        emailInput.focus();
    } else {
        passwordInput.focus();
    }
});
// ============================================
// REMOVER ANIMACIÓN DE ERROR DESPUÉS DE 2 SEGUNDOS
// ============================================
const errorBox = document.getElementById('errorBox');
if (errorBox) {
    setTimeout(function() {
        errorBox.classList.remove('error-shake');
    }, 2000);
}

