// ============================================
// FOOD.JS - Funcionalidad específica para comida
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    // ============================================
    // PREVISUALIZACIÓN DE IMAGEN AL SUBIR
    // ============================================
    const foodImageInput = document.getElementById('foodImageInput');
    const foodImagePreview = document.getElementById('foodImagePreview');

    if (foodImageInput && foodImagePreview) {
        foodImageInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    foodImagePreview.src = e.target.result;
                    foodImagePreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                foodImagePreview.style.display = 'none';
            }
        });
    }

    // ============================================
    // CONFIRMACIÓN DE ELIMINACIÓN
    // ============================================
    document.querySelectorAll('[data-delete-food]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            const name = this.getAttribute('data-food-name') || 'este producto';
            const message = `¿Eliminar "${name}" permanentemente?`;
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });

    // ============================================
    // AUTO-FORMATEO DE PRECIO
    // ============================================
    const foodPriceInput = document.getElementById('foodPriceInput');
    if (foodPriceInput) {
        foodPriceInput.addEventListener('blur', function() {
            let value = parseFloat(this.value);
            if (!isNaN(value) && value > 0) {
                this.value = value.toFixed(2);
            }
        });
    }
});