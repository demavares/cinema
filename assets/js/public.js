// ============================================ 
// PUBLIC.JS - JS global de la vista publica 
// 1) Limpieza de sessionStorage por expiracion/logout (CSP-safe) 
// 2) Menu hamburguesa movil 
// 3) Handlers CSP-safe: imprimir comprobante + fallback de imagenes 
// 4) Rutas a Google Maps (footer) 
// ============================================ 

(function() {
    // ================== 1) LIMPIEZA SESSIONSTORAGE ==================
    function cleanSessionStorage() {
        const prefixes = [
            'food_timeout_', 'food_seats_', 'food_valid_', 'food_order_', 'food_created_',
            'purchase_token_', 'purchase_expires_at_', 'purchase_token_used_', 'purchase_created_at_',
            'ticket_quantities_', 'total_seats_', 'subtotal_', 'tax_amount_', 'total_amount_', 'tax_rate_',
            'payment_method_', 'selected_seats_', 'selected_seats_count_', 'ticket_selection_'
        ];
        const keysToRemove = [];
        for (let i = 0; i < sessionStorage.length; i++) {
            const key = sessionStorage.key(i);
            if (key) {
                const shouldRemove = prefixes.some(prefix => key.includes(prefix));
                if (shouldRemove) keysToRemove.push(key);
            }
        }
        keysToRemove.forEach(key => sessionStorage.removeItem(key));
        return keysToRemove.length;
    }
    const urlParams = new URLSearchParams(window.location.search);
    const triggeredByUrl = ['session_expired','timeout','logout'].some(function(p){ return urlParams.has(p); });
    const viaReferrer = document.referrer.includes('session_expired');
    if (triggeredByUrl || viaReferrer) {
        console.log('🗑️ Limpiando sessionStorage por sesión expirada/logout');
        const cleaned = cleanSessionStorage();
        console.log('✅ SessionStorage limpiado:', cleaned, 'claves');
        try { localStorage.clear(); } catch (e) {}
        if (window.location.pathname.includes('price_selection.php') && urlParams.has('session_expired')) {
            const showtimeId = urlParams.get('showtime_id');
            if (showtimeId) {
                const cleanUrl = window.location.pathname + '?showtime_id=' + showtimeId;
                window.history.replaceState({}, document.title, cleanUrl);
            }
        }
    }
})();

(function() {
    // ================== 2) MENU HAMBURGUESA ==================
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    const menuOverlay = document.getElementById('menuOverlay');
    const closeMenuBtn = document.getElementById('closeMenuBtn');
    if (!hamburgerBtn || !mobileMenu || !menuOverlay || !closeMenuBtn) return;

    function openMenu() {
        mobileMenu.classList.add('active');
        menuOverlay.classList.add('active');
        hamburgerBtn.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeMenu() {
        mobileMenu.classList.remove('active');
        menuOverlay.classList.remove('active');
        hamburgerBtn.classList.remove('active');
        document.body.style.overflow = '';
    }
    function toggleMenu() {
        if (mobileMenu.classList.contains('active')) closeMenu(); else openMenu();
    }
    hamburgerBtn.addEventListener('click', toggleMenu);
    closeMenuBtn.addEventListener('click', closeMenu);
    menuOverlay.addEventListener('click', closeMenu);
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && mobileMenu.classList.contains('active')) closeMenu();
    });
    mobileMenu.querySelectorAll('a').forEach(function(link) {
        link.addEventListener('click', function() {
            if (!this.href.includes('logout.php')) setTimeout(closeMenu, 150);
        });
    });
    mobileMenu.addEventListener('touchmove', function(e) {
        if (mobileMenu.classList.contains('active')) e.stopPropagation();
    });
})();

(function() {
    // ================== 3) HANDLERS CSP-SAFE ==================
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('[data-print-btn]');
        if (btn) window.print();
    });
    document.addEventListener('error', function(e) {
        const img = e.target;
        if (!img || img.tagName !== 'IMG') return;
        if (img.hasAttribute('data-error-hide')) {
            img.style.display = 'none';
        } else if (img.hasAttribute('data-error-fallback')) {
            img.style.display = 'none';
            const fallback = img.nextElementSibling;
            if (fallback) fallback.style.display = 'flex';
        }
    }, true);
})();

// ================== 4) RUTA A GOOGLE MAPS (CSP-safe) ==================
function openDirections(destinationAddress) {
    const encodedDestination = encodeURIComponent(destinationAddress);
    const fallbackUrl = 'https://www.google.com/maps/dir/?api=1&destination=' + encodedDestination;
    if (navigator.geolocation && window.isSecureContext) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                const mapsUrl = 'https://www.google.com/maps/dir/?api=1&origin=' + lat + ',' + lng +
                    '&destination=' + encodedDestination + '&travelmode=driving';
                window.open(mapsUrl, '_blank', 'noopener');
            },
            function(error) { window.open(fallbackUrl, '_blank', 'noopener'); },
            { enableHighAccuracy: true, timeout: 5000 }
        );
    } else {
        window.open(fallbackUrl, '_blank', 'noopener');
    }
}
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('a.address-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            openDirections(this.getAttribute('data-address'));
        });
    });
});
