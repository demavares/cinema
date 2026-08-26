// ============================================
// USERS.JS - Funcionalidad específica para usuarios
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    // ============================================
    // TOGGLE DE VISIBILIDAD DE CONTRASEÑA
    // ============================================
    document.querySelectorAll('[data-password-toggle]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const inputId = this.getAttribute('data-password-toggle');
            const input = document.getElementById(inputId);
            if (!input) return;

            const icon = this.querySelector('i');
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
        });
    });

    // ============================================
    // SOLO NÚMEROS EN CÉDULA Y TELÉFONO
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
    // CONFIRMACIÓN DE ELIMINACIÓN DE USUARIO
    // ============================================
    document.querySelectorAll('[data-delete-user]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            const name = this.getAttribute('data-user-name') || 'este usuario';
            const message = `¿Eliminar "${name}" permanentemente? Esta acción no se puede deshacer.`;
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });
});