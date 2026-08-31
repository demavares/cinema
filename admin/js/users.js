// ============================================
// USERS.JS - Funcionalidad específica para usuarios
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    // ============================================
    // BUSCADOR - CÉDULA (solo existe en la lista)
    // ============================================
    const searchBtn = document.getElementById('searchBtn');
    const clearBtn = document.getElementById('clearBtn');
    const searchInput = document.getElementById('searchCedula');

    function doSearch() {
        const csrf = document.querySelector('input[name="csrf_token"]')?.value || '';
        let url = 'index.php?tab=users&csrf_token=' + encodeURIComponent(csrf);
        if (searchInput && searchInput.value.trim()) {
            url += '&search_cedula=' + encodeURIComponent(searchInput.value.trim());
        }
        window.location.href = url;
    }

    function clearSearch() {
        const csrf = document.querySelector('input[name="csrf_token"]')?.value || '';
        window.location.href = 'index.php?tab=users&csrf_token=' + encodeURIComponent(csrf);
    }

    if (searchBtn) {
        searchBtn.addEventListener('click', doSearch);
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', clearSearch);
    }

    if (searchInput) {
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                doSearch();
            }
        });
    }

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