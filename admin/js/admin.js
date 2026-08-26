// ============================================
// ADMIN.JS - Funcionalidad del panel de administración
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ admin.js cargado correctamente');

    // ============================================
    // SIDEBAR TOGGLE (Móvil)
    // ============================================
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggleBtn = document.getElementById('sidebarToggle');
    const closeBtn = document.getElementById('sidebarClose');

    function openSidebar() {
        sidebar.classList.add('active');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', openSidebar);
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closeSidebar);
    }

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    // Cerrar con Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar && sidebar.classList.contains('active')) {
            closeSidebar();
        }
    });

    // ============================================
    // SUBMENU TOGGLE - CORREGIDO (con delegación de eventos)
    // ============================================
    console.log('🔍 Configurando submenús...');

    // Usar delegación de eventos en el sidebar-nav
    const sidebarNav = document.querySelector('.sidebar-nav');
    
    if (sidebarNav) {
        sidebarNav.addEventListener('click', function(e) {
            // Buscar si el clic fue en un submenu-toggle o en un elemento hijo
            const toggle = e.target.closest('.submenu-toggle');
            
           if (toggle) {
    e.preventDefault();
    e.stopPropagation();
    
    console.log('🔄 Submenú clickeado');
    
    const parent = toggle.closest('.has-submenu');
    if (!parent) {
        console.log('❌ No se encontró el contenedor .has-submenu');
        return;
    }
    
    // 🔧 NUEVO: Si el sidebar está contraído, expandirlo primero
    if (sidebar && sidebar.classList.contains('collapsed')) {
        console.log('📖 Sidebar contraído, expandiendo...');
        toggleSidebarCollapse();
    }
    
    // Cerrar otros submenús abiertos
    document.querySelectorAll('.has-submenu.open').forEach(function(item) {
        if (item !== parent) {
            item.classList.remove('open');
            console.log('🔒 Cerrando otro submenú');
        }
    });
    
    // Toggle del submenú actual
    parent.classList.toggle('open');
    console.log('📂 Estado del submenú:', parent.classList.contains('open') ? 'abierto' : 'cerrado');
}
        });
    } else {
        console.log('❌ No se encontró .sidebar-nav');
    }

    // Abrir submenú si la página actual está dentro de él
    const currentLink = document.querySelector('.sidebar-link.active');
    if (currentLink) {
        const parent = currentLink.closest('.has-submenu');
        if (parent) {
            parent.classList.add('open');
            console.log('📂 Submenú abierto automáticamente');
        }
    }

    // ============================================
    // SIDEBAR COLLAPSE (PC) - con persistencia en cookie
    // ============================================
    const collapseToggle = document.getElementById('sidebarCollapseToggle');
    const topbarCollapseToggle = document.getElementById('topbarCollapseToggle');
    const mainContent = document.getElementById('adminMain');

    function adjustMainWidth() {
        if (!mainContent) return;
        
        const isMobile = window.innerWidth <= 1024;
        
        if (isMobile) {
            mainContent.style.maxWidth = '100%';
            mainContent.style.marginLeft = '0';
            return;
        }
        
        const isCollapsed = sidebar.classList.contains('collapsed');
        if (isCollapsed) {
            mainContent.style.maxWidth = 'calc(100% - 72px)';
            mainContent.style.marginLeft = '72px';
        } else {
            mainContent.style.maxWidth = 'calc(100% - 260px)';
            mainContent.style.marginLeft = '260px';
        }
    }

    function toggleSidebarCollapse() {
        const isCollapsed = sidebar.classList.toggle('collapsed');
        if (mainContent) {
            mainContent.classList.toggle('sidebar-collapsed', isCollapsed);
        }
        
        adjustMainWidth();
        
        document.cookie = 'admin_sidebar_collapsed=' + (isCollapsed ? 'true' : 'false') + '; path=/; max-age=2592000';
    }

    if (collapseToggle) {
        collapseToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleSidebarCollapse();
        });
    }

    if (topbarCollapseToggle) {
        topbarCollapseToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleSidebarCollapse();
        });
    }

    // Cerrar sidebar al hacer clic en un enlace (móvil)
    document.querySelectorAll('.sidebar-link:not(.submenu-toggle)').forEach(function(link) {
        link.addEventListener('click', function(e) {
            if (window.innerWidth <= 1024) {
                setTimeout(closeSidebar, 150);
            }
        });
    });

    // ============================================
    // CONFIRMACIONES PARA ACCIONES DESTRUCTIVAS
    // ============================================
    document.querySelectorAll('[data-confirm]').forEach(function(el) {
        el.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm') || '¿Estás seguro de realizar esta acción?';
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });

    // ============================================
    // AUTO-CERRAR MENSAJES DE ÉXITO/ERROR
    // ============================================
    document.querySelectorAll('.msg-success, .msg-error, .alert, .admin-alert').forEach(function(el) {
        setTimeout(function() {
            el.style.opacity = '0';
            el.style.transition = 'opacity 0.5s ease';
            setTimeout(function() {
                el.style.display = 'none';
            }, 500);
        }, 5000);
    });

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
    // AJUSTAR SIDEBAR EN CAMBIO DE TAMAÑO
    // ============================================
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            if (window.innerWidth <= 1024 && sidebar) {
                if (sidebar.classList.contains('collapsed') && !sidebar.classList.contains('active')) {
                    sidebar.classList.remove('collapsed');
                    if (mainContent) {
                        mainContent.classList.remove('sidebar-collapsed');
                    }
                }
            }
            adjustMainWidth();
        }, 200);
    });

    // ============================================
    // AJUSTE INICIAL
    // ============================================
    adjustMainWidth();

    window.addEventListener('load', function() {
        setTimeout(adjustMainWidth, 100);
    });

    if (window.MutationObserver) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.attributeName === 'class') {
                    adjustMainWidth();
                }
            });
        });
        observer.observe(sidebar, {
            attributes: true,
            attributeFilter: ['class']
        });
    }
});

// ============================================
// FUNCIÓN PARA TOGGLE DE PRECIOS (Niño / Tercera Edad)
// ============================================
window.togglePriceInput = function(checkbox, inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;

    input.disabled = !checkbox.checked;
    input.classList.toggle('price-input-disabled', !checkbox.checked);
};