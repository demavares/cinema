// ============================================
// MOVIES.JS - Funcionalidad específica para el módulo de películas
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    // ============================================
    // AUTO-COMPLETAR AÑO DESDE TÍTULO
    // ============================================
    const titleInput = document.getElementById('movieTitleInput');
    const yearInput = document.getElementById('movieYearInput');

    if (titleInput && yearInput) {
        titleInput.addEventListener('blur', function() {
            const title = this.value.trim();
            const yearMatch = title.match(/,\s*(\d{4})$/);

            if (yearMatch && !yearInput.value) {
                yearInput.value = yearMatch[1];
                const cleanTitle = title.replace(/,\s*\d{4}$/, '');
                if (confirm(`¿Deseas usar "${cleanTitle}" como título y "${yearMatch[1]}" como año?`)) {
                    this.value = cleanTitle;
                }
            }
        });
    }

    // ============================================
    // PREVISUALIZACIÓN DE IMÁGENES (URL)
    // ============================================
    const posterUrlInput = document.getElementById('posterUrlInput');
    const posterPreview = document.getElementById('posterPreview');

    if (posterUrlInput && posterPreview) {
        posterUrlInput.addEventListener('input', function() {
            const url = this.value.trim();
            if (url && (url.startsWith('http://') || url.startsWith('https://'))) {
                posterPreview.src = url;
                posterPreview.style.display = 'block';
            } else {
                posterPreview.style.display = 'none';
            }
        });
    }

    const bannerUrlInput = document.getElementById('bannerUrlInput');
    const bannerPreview = document.getElementById('bannerPreview');

    if (bannerUrlInput && bannerPreview) {
        bannerUrlInput.addEventListener('input', function() {
            const url = this.value.trim();
            if (url && (url.startsWith('http://') || url.startsWith('https://'))) {
                bannerPreview.src = url;
                bannerPreview.style.display = 'block';
            } else {
                bannerPreview.style.display = 'none';
            }
        });
    }

    // ============================================
    // BUSCADOR - EVENT LISTENERS (CSP-safe)
    // ============================================
    const searchBtn = document.getElementById('searchBtn');
    const clearBtn = document.getElementById('clearBtn');
    const searchInput = document.getElementById('searchTitle');

    function doSearch() {
        const csrf = document.querySelector('input[name="csrf_token"]')?.value || '';
        let url = 'index.php?tab=movies&csrf_token=' + encodeURIComponent(csrf);
        if (searchInput && searchInput.value.trim()) {
            url += '&search_title=' + encodeURIComponent(searchInput.value.trim());
        }
        window.location.href = url;
    }

    function clearSearch() {
        const csrf = document.querySelector('input[name="csrf_token"]')?.value || '';
        window.location.href = 'index.php?tab=movies&csrf_token=' + encodeURIComponent(csrf);
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
});