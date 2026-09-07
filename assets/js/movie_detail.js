
    function selectDate(date) {
        // ✅ Remover clase 'active' de TODAS las tarjetas
        document.querySelectorAll('.date-card').forEach(card => {
            card.classList.remove('active');
        });

        // ✅ Agregar clase 'active' SOLO a la tarjeta seleccionada
        const selectedCard = document.querySelector(`.date-card[data-date="${date}"]`);
        if (selectedCard) {
            selectedCard.classList.add('active');
        }

        const container = document.getElementById('timesContainer');
        if (!container) return;

        if (showtimesData[date]) {
            let html = `<div class="times-container" id="timesList_${date}">`;
            showtimesData[date].forEach(time => {
                const isFull = time.is_full;
                const promotions = time.promotions ? time.promotions.split(',') : [];
                const hasMonday = promotions.includes('lunes_mitad');
                const hasPresale = promotions.includes('preventa');
                const language = time.language || 'español';
                const langLabel = language == 'español' ? 'Español' : 'Subtítulos en Español';
                const movieFormat = time.format || '2D';
                const formatClass = 'format-2d';

                if (!isFull) {
                    html += `
                    <a href="price_selection.php?showtime_id=${time.id}" class="time-block">
                        <span class="hour">${formatTimeVenezuela(time.show_time)}</span>
                        <div class="room-format">
                            <span class="room">${escapeHtml(time.room_name)}</span>
                            <span class="separator">|</span>
                            <span class="format-badge ${formatClass}">${escapeHtml(movieFormat)}</span>
                        </div>
                        <span class="language-text">${escapeHtml(langLabel)}</span>
                        ${time.has_started ? `<span class="started-badge"><i class="fas fa-clock"></i> Ya inició Función</span>` : ''}
                        ${hasMonday ? `<span class="promo-badge lunes"><span class="dot"></span> Lunes ½ Precio</span>` : ''}
                        ${hasPresale ? `<span class="promo-badge preventa"><span class="dot"></span> Preventa</span>` : ''}
                    </a>
                `;
                } else {
                    html += `
                    <div class="time-block sold-out">
                        <span class="hour">${formatTimeVenezuela(time.show_time)}</span>
                        <div class="room-format">
                            <span class="room">${escapeHtml(time.room_name)}</span>
                            <span class="separator">|</span>
                            <span class="format-badge ${formatClass}">${escapeHtml(movieFormat)}</span>
                        </div>
                        <span class="language-text">${escapeHtml(langLabel)}</span>
                        ${time.has_started ? `<span class="started-badge"><i class="fas fa-clock"></i> Ya inició Función</span>` : ''}
                        <span class="sold-out-label">Agotado</span>
                    </div>
                `;
                }
            });
            html += `</div>`;
            container.innerHTML = html;
        } else {
            container.innerHTML = `<div class="no-showtimes"><p>No hay funciones disponibles para esta fecha.</p></div>`;
        }
    }

    function formatTimeVenezuela(timeStr) {
        if (!timeStr) return '';
        const parts = timeStr.split(':');
        let hours = parseInt(parts[0], 10);
        const minutes = parts[1] || '00';
        const ampm = hours >= 12 ? 'PM' : 'AM';
        const hour12 = hours % 12 || 12;
        const formattedHour = hour12.toString().padStart(2, '0');
        return `${formattedHour}:${minutes.padStart(2, '0')} ${ampm}`;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function slideDates(direction) {
        const slider = document.getElementById('datesSlider');
        if (!slider) return;
        const cardWidth = slider.querySelector('.date-card')?.offsetWidth || 100;
        const gap = 12;
        const scrollAmount = cardWidth + gap;
        if (direction === 'next') {
            slider.scrollBy({
                left: scrollAmount,
                behavior: 'smooth'
            });
        } else {
            slider.scrollBy({
                left: -scrollAmount,
                behavior: 'smooth'
            });
        }
    }

    function updateSliderButtons() {
        const slider = document.getElementById('datesSlider');
        const prevBtn = document.getElementById('sliderPrev');
        const nextBtn = document.getElementById('sliderNext');

        if (!slider || !prevBtn || !nextBtn) return;

        if (window.innerWidth < 769) {
            const isAtStart = slider.scrollLeft <= 10;
            const isAtEnd = slider.scrollLeft + slider.clientWidth >= slider.scrollWidth - 10;
            prevBtn.classList.toggle('show', !isAtStart);
            nextBtn.classList.toggle('show', !isAtEnd);
        } else {
            prevBtn.classList.remove('show');
            nextBtn.classList.remove('show');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const slider = document.getElementById('datesSlider');
        if (slider) {
            slider.addEventListener('scroll', updateSliderButtons);
        }
        window.addEventListener('resize', updateSliderButtons);
        setTimeout(updateSliderButtons, 100);
    });

    function openTrailer(trailerKey) {
        const modal = document.getElementById('trailerModal');
        const iframe = document.getElementById('trailerIframe');

        if (trailerKey && modal && iframe) {
            // Construir URL con parámetros de privacidad mejorados
            const embedUrl = 'https://www.youtube.com/embed/' + trailerKey +
                '?autoplay=1' +
                '&rel=0' +
                '&modestbranding=1' +
                '&iv_load_policy=3' +
                '&fs=1' +
                '&controls=1' +
                '&disablekb=1' +
                '&enablejsapi=1' +
                '&origin=' + encodeURIComponent(window.location.origin) +
                '&widget_referrer=' + encodeURIComponent(window.location.href);

            iframe.src = embedUrl;
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';

            // Forzar recarga del iframe para asegurar permisos
            setTimeout(() => {
                iframe.contentWindow?.postMessage('{"event":"command","func":"playVideo","args":""}', '*');
            }, 500);
        }
    }

    function closeTrailer() {
        const modal = document.getElementById('trailerModal');
        const iframe = document.getElementById('trailerIframe');
        if (modal && iframe) {
            modal.classList.remove('active');
            iframe.src = '';
            document.body.style.overflow = '';
        }
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeTrailer();
    });

    const trailerModal = document.getElementById('trailerModal');
    if (trailerModal) {
        trailerModal.addEventListener('click', function(e) {
            if (e.target === this) closeTrailer();
        });
    }

