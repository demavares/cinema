
// ============================================
// TOGGLE PASSWORD VISIBILITY (delegación, CSP-safe)
// ============================================
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (!input || !icon) return;
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
        btn.setAttribute('aria-label', 'Ocultar contraseña');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
        btn.setAttribute('aria-label', 'Mostrar contraseña');
    }
}
document.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-password-toggle]');
    if (!btn) return;
    const inputId = btn.getAttribute('data-password-toggle');
    if (!inputId) return;
    togglePasswordVisibility(inputId, btn);
});
// ============================================
// ✅ SOLO NÚMEROS EN CÉDULA Y TELÉFONO
// Reemplaza el oninput inline que violaba la CSP
// ============================================
document.querySelectorAll('[data-only-numbers]').forEach(function(input) {
    input.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
    input.addEventListener('paste', function(e) {
        e.preventDefault();
        const pastedText = (e.clipboardData || window.clipboardData).getData('text');
        this.value = pastedText.replace(/[^0-9]/g, '');
    });
    input.addEventListener('keydown', function(e) {
        const allowedKeys = [
            'Backspace', 'Delete', 'Tab', 'Escape', 'Enter',
            'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown',
            'Home', 'End'
        ];
        if (allowedKeys.includes(e.key)) return;
        if (e.ctrlKey || e.metaKey) return;
        if (!/^[0-9]$/.test(e.key)) {
            e.preventDefault();
        }
    });
});
// ============================================
// VALIDACIÓN EN TIEMPO REAL DE CONTRASEÑAS
// ============================================
document.addEventListener('DOMContentLoaded', function () {
    const password = document.getElementById('regPassword');
    const confirmPassword = document.getElementById('regConfirmPassword');
    const submitBtn = document.getElementById('submitBtn');
    const form = document.getElementById('registerForm');
    function validatePasswords() {
        if (password.value.length > 0 && confirmPassword.value.length > 0) {
            if (password.value === confirmPassword.value) {
                confirmPassword.classList.remove('input-error');
                confirmPassword.classList.add('input-success');
            } else {
                confirmPassword.classList.remove('input-success');
                confirmPassword.classList.add('input-error');
            }
        } else {
            confirmPassword.classList.remove('input-error', 'input-success');
        }
    }
    password.addEventListener('input', validatePasswords);
    confirmPassword.addEventListener('input', validatePasswords);
    // ✅ Prevenir envío inválido
    form.addEventListener('submit', function (e) {
        if (password.value.length < 8) {
            e.preventDefault();
            alert('La contraseña debe tener al menos 8 caracteres.');
            password.classList.add('input-error');
            return false;
        }
        if (password.value !== confirmPassword.value) {
            e.preventDefault();
            alert('Las contraseñas no coinciden. Por favor, verifica.');
            confirmPassword.classList.add('input-error');
            return false;
        }
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registrando...';
    });
    // ✅ Autofocus en el primer campo
    const nameInput = document.getElementById('name');
    if (!nameInput.value) {
        nameInput.focus();
    }
});
// ============================================
// REMOVER ERROR DESPUÉS DE 5 SEGUNDOS
// ============================================
const errorBox = document.getElementById('errorBox');
if (errorBox) {
    setTimeout(function () {
        errorBox.style.opacity = '0';
        errorBox.style.transition = 'opacity 0.5s ease';
        setTimeout(function () {
            errorBox.style.display = 'none';
        }, 500);
    }, 5000);
}

