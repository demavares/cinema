    let quantities = {
        adult: 0,
        child: 0,
        senior: 0
    };
    const prices = {
        adult: priceAdult,
        child: priceChild,
        senior: priceSenior
    };
    const enabledTypes = {
        adult: true,
        child: enableChild && priceChild > 0,
        senior: enableSenior && priceSenior > 0
    };

    function formatCurrency(amount) {
        const symbol = currencyConfig.symbol,
            position = currencyConfig.position;
        const thousands = currencyConfig.thousands,
            decimal = currencyConfig.decimal;
        const decimals = currencyConfig.decimals;
        let formatted = Number(amount).toFixed(decimals).replace('.', decimal).replace(/\B(?=(\d{3})+(?!\d))/g, thousands);
        return position === 'right' ? formatted + ' ' + symbol : symbol + formatted;
    }

    function updateUI() {
        const total = quantities.adult + quantities.child + quantities.senior;
        const subtotal = (quantities.adult * prices.adult) + (quantities.child * prices.child) + (quantities.senior * prices.senior);
        const tax = subtotal * (taxRate / 100);
        const totalAmount = subtotal + tax;
        const qtyAdult = document.getElementById('qty_adult');
        const qtyChild = document.getElementById('qty_child');
        const qtySenior = document.getElementById('qty_senior');
        if (qtyAdult) qtyAdult.textContent = quantities.adult;
        if (qtyChild) qtyChild.textContent = quantities.child;
        if (qtySenior) qtySenior.textContent = quantities.senior;
        const totalSeatsCount = document.getElementById('totalSeatsCount');
        const btnSeatsCount = document.getElementById('btnSeatsCount');
        const totalSeatsInput = document.getElementById('totalSeatsInput');
        if (totalSeatsCount) totalSeatsCount.textContent = total;
        if (btnSeatsCount) btnSeatsCount.textContent = total;
        if (totalSeatsInput) totalSeatsInput.value = total;
        const subtotalHidden = document.getElementById('subtotalHidden');
        const taxHidden = document.getElementById('taxHidden');
        const totalHidden = document.getElementById('totalHidden');
        if (subtotalHidden) subtotalHidden.value = subtotal.toFixed(2);
        if (taxHidden) taxHidden.value = tax.toFixed(2);
        if (totalHidden) totalHidden.value = totalAmount.toFixed(2);
        const summaryItems = document.getElementById('summaryItems');
        if (summaryItems) {
            let html = '',
                hasItems = false;
            if (quantities.adult > 0) {
                hasItems = true;
                html += `<div class="summary-plain-row"><span>Adulto x${quantities.adult}</span><span>${formatCurrency(quantities.adult * prices.adult)}</span></div>`;
            }
            if (quantities.child > 0) {
                hasItems = true;
                html += `<div class="summary-plain-row"><span>Niño x${quantities.child}</span><span>${formatCurrency(quantities.child * prices.child)}</span></div>`;
            }
            if (quantities.senior > 0) {
                hasItems = true;
                html += `<div class="summary-plain-row"><span>Tercera Edad x${quantities.senior}</span><span>${formatCurrency(quantities.senior * prices.senior)}</span></div>`;
            }
            summaryItems.innerHTML = hasItems ? html : `<div class="text-sm text-gray-500 text-center py-2">No has seleccionado boletos</div>`;
        }
        const subtotalAmount = document.getElementById('subtotalAmount');
        const taxAmount = document.getElementById('taxAmount');
        const totalAmountEl = document.getElementById('totalAmount');
        if (subtotalAmount) subtotalAmount.textContent = formatCurrency(subtotal);
        if (taxAmount) taxAmount.textContent = formatCurrency(tax);
        if (totalAmountEl) totalAmountEl.textContent = formatCurrency(totalAmount);
        const btnContinue = document.getElementById('btnContinue');
        if (btnContinue) {
            if (total > 0 && total <= maxAvailableSeats) {
                btnContinue.disabled = false;
                btnContinue.innerHTML = `Elegir ${total} Asiento${total !== 1 ? 's' : ''}`;
            } else if (total > maxAvailableSeats) {
                btnContinue.disabled = true;
                btnContinue.innerHTML = `⚠️ Solo ${maxAvailableSeats} asientos disponibles`;
            } else {
                btnContinue.disabled = true;
                btnContinue.innerHTML = 'Elegir 0 Asientos';
            }
        }
        const ticketsInput = document.getElementById('ticketsInput');
        if (ticketsInput) ticketsInput.value = JSON.stringify(quantities);
    }

    function updateQuantity(type, change) {
        if (!enabledTypes[type]) return;
        const newValue = quantities[type] + change;
        if (newValue < 0) return;
        quantities[type] = newValue;
        updateUI();
    }
    document.addEventListener('DOMContentLoaded', function() {
        try {
            sessionStorage.removeItem('selected_seats_' + showtimeId);
            sessionStorage.removeItem('selected_seats_count_' + showtimeId);
        } catch (e) {}
        document.querySelectorAll('.qty-increase').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                updateQuantity(this.dataset.type, 1);
            });
        });
        document.querySelectorAll('.qty-decrease').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                updateQuantity(this.dataset.type, -1);
            });
        });
        document.querySelectorAll('.price-card:not(.disabled)').forEach(function(card) {
            card.addEventListener('click', function(e) {
                if (e.target.closest('.quantity-controls button')) return;
                const type = this.dataset.type;
                if (type) updateQuantity(type, 1);
            });
        });
        document.getElementById('seatsForm').addEventListener('submit', function(e) {
            const total = quantities.adult + quantities.child + quantities.senior;
            if (total === 0) {
                e.preventDefault();
                alert('Por favor, selecciona al menos un boleto.');
                return false;
            }
            if (total > maxAvailableSeats) {
                e.preventDefault();
                alert('Solo hay ' + maxAvailableSeats + ' asientos disponibles.');
                return false;
            }
            return true;
        });
        updateUI();
    });

