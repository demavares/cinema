// ============================================
// SHOWTIMES.JS - Funcionalidad específica para funciones
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    // ============================================
    // BUSCADOR - EVENT LISTENERS (CSP-safe)
    // Solo existe en la página de listado
    // ============================================
    const searchBtn = document.getElementById('searchBtn');
    const clearBtn = document.getElementById('clearBtn');
    const searchInput = document.getElementById('searchShowtime');

    function doSearch() {
        const csrf = document.querySelector('input[name="csrf_token"]')?.value || '';
        let url = 'index.php?tab=showtimes&csrf_token=' + encodeURIComponent(csrf);
        if (searchInput && searchInput.value.trim()) {
            url += '&search_showtime=' + encodeURIComponent(searchInput.value.trim());
        }
        window.location.href = url;
    }

    function clearSearch() {
        const csrf = document.querySelector('input[name="csrf_token"]')?.value || '';
        window.location.href = 'index.php?tab=showtimes&csrf_token=' + encodeURIComponent(csrf);
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

    const movieSelect = document.getElementById('movieSelect');
    const roomSelect = document.getElementById('roomSelect');
    const dateInput = document.getElementById('dateInput');
    const timeInput = document.getElementById('timeInput');
    const conflictStatus = document.getElementById('conflictStatus');
    const conflictChecker = document.getElementById('conflictChecker');
    const submitBtn = document.getElementById('submitBtn');
    const showtimeIdInput = document.getElementById('showtimeIdInput');

    if (!movieSelect || !roomSelect || !dateInput || !timeInput) return;

    // ============================================
    // TOGGLES DE PRECIO
    // ============================================
    const childCheckbox = document.getElementById('enable_child_price');
    const childInput = document.getElementById('price_child');
    const seniorCheckbox = document.getElementById('enable_senior_price');
    const seniorInput = document.getElementById('price_senior');

    function syncPriceToggle(checkbox, input) {
        if (!checkbox || !input) return;
        input.disabled = !checkbox.checked;
        input.classList.toggle('price-input-disabled', !checkbox.checked);
    }

    syncPriceToggle(childCheckbox, childInput);
    syncPriceToggle(seniorCheckbox, seniorInput);

    if (childCheckbox) {
        childCheckbox.addEventListener('change', function() {
            syncPriceToggle(childCheckbox, childInput);
        });
    }

    if (seniorCheckbox) {
        seniorCheckbox.addEventListener('change', function() {
            syncPriceToggle(seniorCheckbox, seniorInput);
        });
    }

    // ============================================
    // VERIFICACIÓN DE CONFLICTOS
    // El botón de guardar SOLO se habilita cuando
    // se confirma que no hay conflicto.
    // ============================================
    function checkConflicts() {
        const movieId = movieSelect.value;
        const roomId = roomSelect.value;
        const date = dateInput.value;
        const time = timeInput.value;

        if (!movieId || !roomId || !date || !time) {
            if (conflictStatus) {
                conflictStatus.textContent = 'Selecciona película, sala, fecha y hora para verificar automáticamente si hay conflictos.';
            }
            if (conflictChecker) {
                conflictChecker.className = 'mb-4 p-3 rounded-lg border text-sm conflict-checking';
                conflictChecker.style.display = 'block';
            }
            enableSubmit(false);
            return;
        }

        const selectedOption = movieSelect.options[movieSelect.selectedIndex];
        const duration = selectedOption ? parseInt(selectedOption.dataset.duration) || 0 : 0;

        if (duration === 0) {
            if (conflictStatus) {
                conflictStatus.textContent = '⚠️ La película seleccionada no tiene duración definida.';
            }
            if (conflictChecker) {
                conflictChecker.className = 'mb-4 p-3 rounded-lg border text-sm conflict-warning';
            }
            enableSubmit(false);
            return;
        }

        if (conflictStatus) {
            conflictStatus.textContent = '⏳ Verificando conflictos...';
        }
        if (conflictChecker) {
            conflictChecker.className = 'mb-4 p-3 rounded-lg border text-sm conflict-checking';
        }
        enableSubmit(false);

        const formData = new FormData();
        formData.append('action', 'check_conflict');
        formData.append('room_id', roomId);
        formData.append('show_date', date);
        formData.append('show_time', time);
        formData.append('duration', duration);

        const excludeId = showtimeIdInput ? showtimeIdInput.value : '0';
        if (excludeId && parseInt(excludeId) > 0) {
            formData.append('exclude_id', excludeId);
        }

        fetch('ajax/check_conflict.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                if (conflictStatus) {
                    conflictStatus.textContent = '⚠️ Error: ' + data.error;
                }
                if (conflictChecker) {
                    conflictChecker.className = 'mb-4 p-3 rounded-lg border text-sm conflict-warning';
                }
                enableSubmit(false);
                return;
            }

            if (data.conflict) {
                let message = data.message || '❌ Conflicto detectado';
                message = message.replace(/Sala\s+Sala/g, 'Sala');
                message = message.replace(/sala\s+sala/g, 'sala');

                if (conflictStatus) {
                    conflictStatus.textContent = '❌ ' + message;
                }
                if (conflictChecker) {
                    conflictChecker.className = 'mb-4 p-3 rounded-lg border text-sm conflict-warning';
                }
                enableSubmit(false);
            } else {
                let message = data.message || '✅ No hay conflictos. La sala está disponible en la función seleccionada.';
                if (conflictStatus) {
                    conflictStatus.textContent = message;
                }
                if (conflictChecker) {
                    conflictChecker.className = 'mb-4 p-3 rounded-lg border text-sm conflict-safe';
                }
                enableSubmit(true);
            }
        })
        .catch(error => {
            console.error('Error verificando conflictos:', error);
            if (conflictStatus) {
                conflictStatus.textContent = '⚠️ Error al verificar conflictos. Intenta nuevamente.';
            }
            if (conflictChecker) {
                conflictChecker.className = 'mb-4 p-3 rounded-lg border text-sm conflict-warning';
            }
            enableSubmit(false);
        });
    }

    function enableSubmit(enabled) {
        if (!submitBtn) return;
        submitBtn.disabled = !enabled;
        if (enabled) {
            submitBtn.classList.remove('btn-disabled');
            submitBtn.style.opacity = '1';
            submitBtn.style.cursor = 'pointer';
        } else {
            submitBtn.classList.add('btn-disabled');
            submitBtn.style.opacity = '0.5';
            submitBtn.style.cursor = 'not-allowed';
        }
    }

    // ============================================
    // EVENT LISTENERS (verificación en tiempo real)
    // ============================================
    let conflictTimeout;

    function checkConflictsImmediate() {
        clearTimeout(conflictTimeout);
        checkConflicts();
    }

    function checkConflictsDebounced() {
        clearTimeout(conflictTimeout);
        conflictTimeout = setTimeout(checkConflicts, 300);
    }

    movieSelect.addEventListener('change', checkConflictsImmediate);
    movieSelect.addEventListener('input', checkConflictsDebounced);
    roomSelect.addEventListener('change', checkConflictsImmediate);
    roomSelect.addEventListener('input', checkConflictsDebounced);
    dateInput.addEventListener('change', checkConflictsImmediate);
    dateInput.addEventListener('input', checkConflictsDebounced);
    timeInput.addEventListener('change', checkConflictsImmediate);
    timeInput.addEventListener('input', checkConflictsDebounced);

    // Validación inicial: el botón queda deshabilitado
    // hasta confirmar que no hay conflicto
    enableSubmit(false);
    if (movieSelect.value && roomSelect.value && dateInput.value && timeInput.value) {
        setTimeout(checkConflicts, 300);
    }

    // ============================================
    // VALIDACIÓN AL ENVIAR
    // ============================================
    document.getElementById('showtimeForm')?.addEventListener('submit', function(e) {
        if (submitBtn && submitBtn.disabled) {
            e.preventDefault();
            alert('❌ No puedes guardar la función mientras haya un conflicto. Resuelve el conflicto primero.');
            return false;
        }
    });
});