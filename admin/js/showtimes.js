// ============================================
// SHOWTIMES.JS - Funcionalidad específica para horarios
// ============================================

document.addEventListener('DOMContentLoaded', function() {
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
            enableSubmit(true);
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
            enableSubmit(true);
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

        fetch('../ajax/check_conflict.php', {
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
                enableSubmit(true);
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
                let message = data.message || '✅ No hay conflictos. La sala está disponible en el horario seleccionado.';
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
            enableSubmit(true);
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
    // EVENT LISTENERS
    // ============================================
    movieSelect.addEventListener('change', checkConflicts);
    roomSelect.addEventListener('change', checkConflicts);
    dateInput.addEventListener('change', checkConflicts);
    timeInput.addEventListener('change', checkConflicts);

    // Validación inicial
    if (movieSelect.value && roomSelect.value && dateInput.value && timeInput.value) {
        setTimeout(checkConflicts, 300);
    }

    // ============================================
    // VALIDACIÓN AL ENVIAR
    // ============================================
    document.getElementById('showtimeForm')?.addEventListener('submit', function(e) {
        if (submitBtn && submitBtn.disabled) {
            e.preventDefault();
            alert('❌ No puedes guardar el horario mientras haya un conflicto. Resuelve el conflicto primero.');
            return false;
        }
    });
});