// ============================================
// TIMEOUT MANAGER - Gestión unificada del timeout
// ============================================

const TimeoutManager = {
    // Configuración
    config: {
        showtimeId: null,
        seats: null,
        initialTimeout: 600,
        syncInterval: 10000,
        tickInterval: 1000,
        redirectOnExpire: true,
        redirectUrl: 'index.php?timeout=1'
    },
    
    // Estado
    state: {
        timeoutSeconds: 600,
        timeoutExpired: false,
        isRunning: false,
        timerId: null,
        syncId: null,
        countdownEl: null,
        warningEl: null,
        statusEl: null,
        iconEl: null,
        sessionValidated: false
    },
    
    // ============================================
    // INICIALIZAR TIMEOUT
    // ============================================
    init(config) {
        this.config = { ...this.config, ...config };
        
        // Buscar elementos DOM
        this.state.countdownEl = document.getElementById('countdownTimer');
        this.state.warningEl = document.getElementById('timeoutWarning');
        this.state.statusEl = document.getElementById('timeoutStatus');
        this.state.iconEl = document.getElementById('timeoutIcon');
        
        if (!this.state.countdownEl) {
            console.warn('TimeoutManager: Elementos DOM no encontrados');
            return this;
        }
        
        // Intentar restaurar desde sessionStorage ANTES de verificar con el servidor
        const savedTime = this.restoreFromSessionStorage();
        if (savedTime !== null && savedTime > 0) {
            this.state.timeoutSeconds = savedTime;
            this.updateDisplay();
        }
        
        // Verificar con el servidor (fuente de verdad)
        this.verifySession();
        
        return this;
    },
    
    // ============================================
    // RESTAURAR DESDE SESSIONSTORAGE (para evitar parpadeo)
    // ============================================
    restoreFromSessionStorage() {
        const showtimeId = this.config.showtimeId;
        if (!showtimeId) return null;
        
        try {
            const savedTime = sessionStorage.getItem('food_timeout_' + showtimeId);
            if (savedTime !== null) {
                const time = parseInt(savedTime);
                if (time > 0 && time <= 600) {
                    console.log('TimeoutManager: Restaurado desde sessionStorage:', time);
                    return time;
                }
            }
        } catch (e) {
            console.warn('TimeoutManager: Error restaurando sessionStorage', e);
        }
        return null;
    },
    
    // ============================================
    // VERIFICAR SESIÓN EN EL SERVIDOR (ÚNICA FUENTE DE VERDAD)
    // ============================================
    verifySession() {
        const showtimeId = this.config.showtimeId;
        if (!showtimeId) {
            console.warn('TimeoutManager: showtime_id no configurado');
            this.redirectTo('index.php');
            return;
        }
        
        console.log('TimeoutManager: Verificando sesión en el servidor...');
        
        fetch('check_session.php?showtime_id=' + showtimeId + '&t=' + Date.now())
            .then(response => response.json())
            .then(data => {
                console.log('TimeoutManager: Respuesta del servidor:', data);
                
                if (!data.valid) {
                    console.log('TimeoutManager: Sesión inválida, redirigiendo...');
                    this.handleInvalidSession(data.reason);
                    return;
                }
                
                // Verificar que los asientos coincidan (si se proporcionaron)
                if (this.config.seats && data.seats && data.seats !== this.config.seats) {
                    console.warn('TimeoutManager: Asientos no coinciden');
                    this.redirectTo('index.php');
                    return;
                }
                
                // SIEMPRE usar el tiempo del servidor, NO el initialTimeout
                if (data.timeLeft !== undefined && data.timeLeft > 0) {
                    this.state.timeoutSeconds = data.timeLeft;
                    this.state.sessionValidated = true;
                    
                    // Guardar en sessionStorage para persistencia
                    sessionStorage.setItem('food_timeout_' + showtimeId, data.timeLeft.toString());
                    sessionStorage.setItem('food_seats_' + showtimeId, data.seats || this.config.seats || '');
                    
                    this.updateDisplay();
                    this.start();
                    console.log('TimeoutManager: Sesión válida, tiempo restante del servidor:', data.timeLeft);
                } else {
                    this.handleExpiredSession();
                }
            })
            .catch(error => {
                console.error('TimeoutManager: Error verificando sesión', error);
                // Si hay error de red, usar el tiempo local si existe
                const savedTime = this.restoreFromSessionStorage();
                if (savedTime !== null && savedTime > 0) {
                    this.state.timeoutSeconds = savedTime;
                    this.updateDisplay();
                    this.start();
                } else {
                    this.redirectTo('index.php');
                }
            });
    },
    
    // ============================================
    // INICIAR CONTADORES
    // ============================================
    start() {
        if (this.state.isRunning) return this;
        
        this.state.isRunning = true;
        
        // Iniciar tick cada segundo
        this.state.timerId = setInterval(() => {
            if (!this.state.timeoutExpired) {
                this.state.timeoutSeconds--;
                this.updateDisplay();
                
                // Guardar en sessionStorage cada segundo
                const showtimeId = this.config.showtimeId;
                if (showtimeId) {
                    sessionStorage.setItem('food_timeout_' + showtimeId, this.state.timeoutSeconds.toString());
                }
                
                if (this.state.timeoutSeconds <= 0) {
                    this.handleExpiredSession();
                }
            }
        }, this.config.tickInterval);
        
        // Iniciar sincronización con el servidor
        this.state.syncId = setInterval(() => {
            this.syncWithServer();
        }, this.config.syncInterval);
        
        return this;
    },
    
    // ============================================
    // SINCRONIZAR CON EL SERVIDOR
    // ============================================
    syncWithServer() {
        if (this.state.timeoutExpired) return;
        
        const showtimeId = this.config.showtimeId;
        fetch('check_session.php?showtime_id=' + showtimeId + '&t=' + Date.now())
            .then(response => response.json())
            .then(data => {
                if (!data.valid) {
                    this.handleInvalidSession(data.reason);
                    return;
                }
                if (data.timeLeft !== undefined && data.timeLeft > 0) {
                    // Usar el tiempo del servidor, NO sobrescribir con initialTimeout
                    this.state.timeoutSeconds = data.timeLeft;
                    sessionStorage.setItem('food_timeout_' + showtimeId, data.timeLeft.toString());
                    this.updateDisplay();
                } else {
                    this.handleExpiredSession();
                }
            })
            .catch(() => {});
    },
    
    // ============================================
    // MANEJAR SESIÓN INVÁLIDA
    // ============================================
    handleInvalidSession(reason) {
        this.stop();
        const showtimeId = this.config.showtimeId;
        if (showtimeId) {
            sessionStorage.removeItem('food_timeout_' + showtimeId);
            sessionStorage.removeItem('food_seats_' + showtimeId);
        }
        if (reason === 'timeout_expired') {
            this.redirectTo('index.php?timeout=1');
        } else {
            this.redirectTo('index.php');
        }
    },
    
    // ============================================
    // MANEJAR SESIÓN EXPIRADA
    // ============================================
    handleExpiredSession() {
        if (this.state.timeoutExpired) return;
        
        this.state.timeoutExpired = true;
        this.stop();
        
        const showtimeId = this.config.showtimeId;
        if (showtimeId) {
            sessionStorage.removeItem('food_timeout_' + showtimeId);
            sessionStorage.removeItem('food_seats_' + showtimeId);
        }
        
        fetch('clear_seats_session.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'showtime_id=' + this.config.showtimeId
        }).finally(() => {
            if (this.config.redirectOnExpire) {
                this.redirectTo(this.config.redirectUrl);
            }
        });
    },
    
    // ============================================
    // DETENER CONTADORES
    // ============================================
    stop() {
        if (this.state.timerId) {
            clearInterval(this.state.timerId);
            this.state.timerId = null;
        }
        if (this.state.syncId) {
            clearInterval(this.state.syncId);
            this.state.syncId = null;
        }
        this.state.isRunning = false;
        return this;
    },
    
    // ============================================
    // ACTUALIZAR PANTALLA
    // ============================================
    updateDisplay() {
        const seconds = this.state.timeoutSeconds;
        const mins = String(Math.floor(seconds / 60)).padStart(2, '0');
        const secs = String(seconds % 60).padStart(2, '0');
        
        if (this.state.countdownEl) {
            this.state.countdownEl.textContent = `${mins}:${secs}`;
        }
        
        this.updateColors();
    },
    
    // ============================================
    // ACTUALIZAR COLORES DEL WARNING
    // ============================================
    updateColors() {
        const seconds = this.state.timeoutSeconds;
        const warning = this.state.warningEl;
        const status = this.state.statusEl;
        const icon = this.state.iconEl;
        
        if (!warning) return;
        
        warning.classList.remove('normal', 'warning', 'danger');
        
        if (seconds > 60) {
            warning.classList.add('normal');
            if (status) status.textContent = 'Los asientos se liberarán automáticamente';
            if (icon) icon.className = 'fas fa-clock';
        } else if (seconds > 30) {
            warning.classList.add('warning');
            if (status) status.textContent = '⚠️ ¡Tu tiempo está por agotarse!';
            if (icon) icon.className = 'fas fa-exclamation-triangle';
        } else if (seconds > 0) {
            warning.classList.add('danger');
            if (status) status.textContent = '🚨 ¡ÚLTIMOS SEGUNDOS!';
            if (icon) icon.className = 'fas fa-exclamation-circle';
        } else {
            warning.classList.add('danger');
            if (status) status.textContent = '⏰ TIEMPO AGOTADO';
            if (icon) icon.className = 'fas fa-hourglass-end';
        }
    },
    
    // ============================================
    // REDIRIGIR
    // ============================================
    redirectTo(url) {
        window.location.href = url;
    },
    
    // ============================================
    // OBTENER TIEMPO RESTANTE
    // ============================================
    getTimeLeft() {
        return this.state.timeoutSeconds;
    },
    
    // ============================================
    // VERIFICAR SI ESTÁ EXPIRADO
    // ============================================
    isExpired() {
        return this.state.timeoutExpired;
    },
    
    // ============================================
    // DESTRUIR
    // ============================================
    destroy() {
        this.stop();
        this.state.isRunning = false;
        this.state.timeoutExpired = true;
        return this;
    }
};

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.TimeoutManager = TimeoutManager;
}