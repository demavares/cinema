<?php
// footer.php - Pie de página en estilo oscuro reutilizable
// Obtener configuración del sitio
global $pdo;
$config = getSiteConfig($pdo);
?>
    <footer class="site-footer">
        <!-- Sección de Estudios de Cine / Aliados - MARQUESINA INFINITA -->
        <div class="footer-studios">
            <div class="footer-container">
               
                <div class="marquee-wrapper">
                    <div class="marquee-track">
                        <!-- Primera vuelta de logos -->
                        <div class="marquee-content">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/6/64/Warner_Bros_logo.svg" alt="Warner Bros" class="studio-logo" title="Warner Bros" loading="lazy">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/b/b6/Universal_Pictures_logo.svg" alt="Universal" class="studio-logo" title="Universal" loading="lazy">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/9/90/Paramount_Pictures_Corporation_logo.svg" alt="Paramount" class="studio-logo" title="Paramount" loading="lazy">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/b/b4/Columbia_Pictures.svg" alt="Columbia Pictures" class="studio-logo" title="Columbia Pictures" loading="lazy">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/2/2d/Walt_Disney_Pictures_text_logo.svg" alt="Disney" class="studio-logo" title="Disney" loading="lazy">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/c/ca/Sony_logo.svg" alt="Sony Pictures" class="studio-logo" title="Sony Pictures" loading="lazy">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/4/40/Pixar_logo.svg" alt="Pixar" class="studio-logo" title="Pixar" loading="lazy">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/c/ce/Star_wars2.svg" alt="Star Wars" class="studio-logo" title="Star Wars" loading="lazy">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/b/b7/A24_logo.svg" alt="A24" class="studio-logo" title="A24" loading="lazy">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/d/da/Marvel_Studios_2025.svg" alt="Marvel Studios" class="studio-logo" title="Marvel Studios" loading="lazy">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/3/36/DC_Studios_logo.svg?utm_source=commons.wikimedia.org&utm_campaign=index&utm_content=original" alt="DC" class="studio-logo" title="DC" loading="lazy">
                        </div>
                        <!-- Segunda vuelta de logos (duplicado para efecto infinito) -->
                        <div class="marquee-content">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/6/64/Warner_Bros_logo.svg" alt="Warner Bros" class="studio-logo" title="Warner Bros" loading="lazy">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/b/b6/Universal_Pictures_logo.svg" alt="Universal" class="studio-logo" title="Universal" loading="lazy">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/9/90/Paramount_Pictures_Corporation_logo.svg" alt="Paramount" class="studio-logo" title="Paramount" loading="lazy">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/b/b4/Columbia_Pictures.svg" alt="Columbia Pictures" class="studio-logo" title="Columbia Pictures" loading="lazy">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/2/2d/Walt_Disney_Pictures_text_logo.svg" alt="Disney" class="studio-logo" title="Disney" loading="lazy">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/c/ca/Sony_logo.svg" alt="Sony Pictures" class="studio-logo" title="Sony Pictures" loading="lazy">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/4/40/Pixar_logo.svg" alt="Pixar" class="studio-logo" title="Pixar" loading="lazy">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/c/ce/Star_wars2.svg" alt="Star Wars" class="studio-logo" title="Star Wars" loading="lazy">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/b/b7/A24_logo.svg" alt="A24" class="studio-logo" title="A24" loading="lazy">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/d/da/Marvel_Studios_2025.svg" alt="Marvel Studios" class="studio-logo" title="Marvel Studios" loading="lazy">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/3/36/DC_Studios_logo.svg?utm_source=commons.wikimedia.org&utm_campaign=index&utm_content=original" alt="DC" class="studio-logo" title="DC" loading="lazy">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contenido Principal del Footer -->
        <div class="footer-main">
            <div class="footer-container footer-grid">
                
                <!-- Columna 1: Branding e Información -->
                <div class="footer-col brand-col">
                    <a href="index.php" class="footer-brand" title="<?= htmlspecialchars($config['site_name'] ?? 'Cinema Pro') ?>">
                        <?php 
                        // Usar footer_logo en lugar de site_logo
                        $logo_path = $config['footer_logo'] ?? '';
                        $logo_exists = !empty($logo_path) && file_exists($logo_path);
                        ?>
                        <?php if($logo_exists): ?>
                            <img src="<?= htmlspecialchars($logo_path) . '?v=' . filemtime($logo_path) ?>" 
                                 alt="<?= htmlspecialchars($config['site_name'] ?? 'Cinema Pro') ?>" 
                                 title="<?= htmlspecialchars($config['site_name'] ?? 'Cinema Pro') ?>"
                                 class="footer-logo-img">
                        <?php elseif(!empty($logo_path) && filter_var($logo_path, FILTER_VALIDATE_URL)): ?>
                            <img src="<?= htmlspecialchars($logo_path) ?>" 
                                 alt="<?= htmlspecialchars($config['site_name'] ?? 'Cinema Pro') ?>" 
                                 title="<?= htmlspecialchars($config['site_name'] ?? 'Cinema Pro') ?>"
                                 class="footer-logo-img">
                        <?php else: ?>
                            <i class="fas fa-film"></i>
                            <span><?= htmlspecialchars($config['site_name'] ?? 'Cinema Pro') ?></span>
                        <?php endif; ?>
                    </a>
                    <p class="brand-desc">
                        La mejor experiencia cinematográfica con tecnología de vanguardia, salas confortables y la mejor selección de películas.
                    </p>
                </div>

                <!-- Columna 2: Contacto & Dirección - Solo mostrar si hay datos -->
                <?php 
                $has_contact = !empty($config['address']) || !empty($config['email']) || !empty($config['phone']) || !empty($config['whatsapp']);
                if($has_contact): 
                ?>
                <div class="footer-col">
                    <h3 class="footer-heading">Contacto</h3>
                    <ul class="contact-list">
                        <?php if(!empty($config['address'])): ?>
                        <li>
                            <i class="fas fa-map-marker-alt"></i>
                            <a href="javascript:void(0)" onclick="openDirections('<?= htmlspecialchars($config['address']) ?>')" title="Clic para calcular la ruta desde tu ubicación actual">
                                <?= htmlspecialchars($config['address']) ?>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if(!empty($config['email'])): ?>
                        <li>
                            <i class="fas fa-envelope"></i>
                            <a href="mailto:<?= htmlspecialchars($config['email']) ?>"><?= htmlspecialchars($config['email']) ?></a>
                        </li>
                        <?php endif; ?>
                        <?php if(!empty($config['phone']) && !empty($config['whatsapp'])): ?>
                        <li>
                            <i class="fab fa-whatsapp"></i>
                            <a href="<?= htmlspecialchars($config['whatsapp']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($config['phone']) ?></a>
                        </li>
                        <?php elseif(!empty($config['phone'])): ?>
                        <li>
                            <i class="fas fa-phone"></i>
                            <span><?= htmlspecialchars($config['phone']) ?></span>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <!-- Columna 3: Redes Sociales - Solo mostrar si hay al menos una red configurada -->
                <?php 
                $has_social = !empty($config['instagram']) || !empty($config['facebook']) || !empty($config['twitter']) || !empty($config['telegram']) || !empty($config['whatsapp']);
                if($has_social): 
                ?>
                <div class="footer-col">
                    <h3 class="footer-heading">Síguenos</h3>
                    <p class="social-intro">Entérate de los próximos estrenos y promociones exclusivas.</p>
                    <div class="social-links">
                        <?php if(!empty($config['instagram'])): ?>
                            <a href="<?= htmlspecialchars($config['instagram']) ?>" target="_blank" rel="noopener" aria-label="Instagram" class="social-btn instagram">
                                <i class="fab fa-instagram"></i>
                            </a>
                        <?php endif; ?>
                        <?php if(!empty($config['facebook'])): ?>
                            <a href="<?= htmlspecialchars($config['facebook']) ?>" target="_blank" rel="noopener" aria-label="Facebook" class="social-btn facebook">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                        <?php endif; ?>
                        <?php if(!empty($config['twitter'])): ?>
                            <a href="<?= htmlspecialchars($config['twitter']) ?>" target="_blank" rel="noopener" aria-label="X" class="social-btn x-twitter">
                                <i class="fa-brands fa-x-twitter"></i>
                            </a>
                        <?php endif; ?>
                        <?php if(!empty($config['telegram'])): ?>
                            <a href="<?= htmlspecialchars($config['telegram']) ?>" target="_blank" rel="noopener" aria-label="Telegram" class="social-btn telegram">
                                <i class="fab fa-telegram-plane"></i>
                            </a>
                        <?php endif; ?>
                        <?php if(!empty($config['whatsapp'])): ?>
                            <a href="<?= htmlspecialchars($config['whatsapp']) ?>" target="_blank" rel="noopener" aria-label="WhatsApp" class="social-btn whatsapp">
                                <i class="fab fa-whatsapp"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
		<!-- Copyright / Sub-footer -->
		<div class="footer-bottom">
			<div class="footer-container footer-bottom-content">
				<?php
				// ✅ Obtener copyright desde configuración de BD
				$footerCopyright = $config['footer_copyright'] ?? '© {year} Cinema. Todos los derechos reservados.';
				// Reemplazar placeholder {year} con el año actual
				$footerCopyright = str_replace('{year}', date('Y'), $footerCopyright);
				?>
				<p><?= $footerCopyright ?></p>
				<div class="legal-links">
					<a href="#">Términos y Condiciones</a>
					<span class="dot">•</span>
					<a href="#">Políticas de Privacidad</a>
				</div>
			</div>
		</div>
    </footer>

    <!-- Estilos CSS del Footer (Tema Oscuro) -->
    <style>
        .site-footer {
            background-color: #0a0a0f;
            color: #9ca3af;
            font-family: inherit;
            border-top: 1px solid #1e1e2e;
            margin-top: auto;
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* ============================================
           MARQUESINA INFINITA
           ============================================ */
        .footer-studios {
            background-color: #07070a;
            padding: 24px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            overflow: hidden;
        }

        .studios-title {
            text-align: center;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #6b7280;
            margin-bottom: 16px;
            font-weight: 600;
        }

        .marquee-wrapper {
            width: 100%;
            overflow: hidden;
            position: relative;
        }

        .marquee-track {
            display: flex;
            gap: 0;
            animation: marquee-scroll 30s linear infinite;
            width: max-content;
        }

        .marquee-content {
            display: flex;
            align-items: center;
            gap: 50px;
            padding: 0 25px;
            flex-shrink: 0;
        }

        @keyframes marquee-scroll {
            0% {
                transform: translateX(0);
            }
            100% {
                transform: translateX(-50%);
            }
        }

        .marquee-track:hover {
            animation-play-state: paused;
        }

        .studio-logo {
            height: 32px;
            width: auto;
            max-width: 120px;
            object-fit: contain;
            opacity: 0.5;
            filter: brightness(0) invert(0.7);
            transition: all 0.4s ease;
            flex-shrink: 0;
        }

        .studio-logo:hover {
            opacity: 1;
            filter: brightness(0) invert(1);
            transform: scale(1.1);
        }

        /* Fallback para logos que no cargan - texto alternativo */
        .studio-logo::before {
            content: "🎬";
            display: none;
        }

        /* ============================================
           FIN MARQUESINA
           ============================================ */

        .footer-main {
            padding: 50px 0 40px 0;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 1.5fr 1.2fr 1fr;
            gap: 40px;
        }

        .footer-brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: #ffffff;
            font-size: 1.4rem;
            font-weight: 800;
            text-decoration: none;
            margin-bottom: 14px;
        }

        .footer-brand i {
            color: #6366f1;
        }

        .footer-brand img {
            max-height: 81px;
            max-width: 277px;
            width: auto;
            height: auto;
            object-fit: contain;
        }

        /* Estilo específico para el logo del footer con tamaño 277px x 81px */
        .footer-logo-img {
            height: 81px;
            width: 277px;
            max-width: 100%;
            object-fit: contain;
        }

        .brand-desc {
            font-size: 0.9rem;
            line-height: 1.6;
            color: #9ca3af;
        }

        .footer-heading {
            color: #ffffff;
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 18px;
            letter-spacing: 0.02em;
        }

        .contact-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .contact-list li {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .contact-list i {
            color: #6366f1;
            font-size: 1rem;
            margin-top: 3px;
            flex-shrink: 0;
        }

        .contact-list a {
            color: #d1d5db;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .contact-list a:hover {
            color: #818cf8;
        }

        .social-intro {
            font-size: 0.85rem;
            margin-bottom: 16px;
            color: #9ca3af;
        }

        .social-links {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .social-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #14141e;
            border: 1px solid #2a2a3e;
            color: #d1d5db;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .social-btn:hover {
            transform: translateY(-3px);
            color: #ffffff;
        }

        .social-btn.instagram:hover { background: #e1306c; border-color: #e1306c; }
        .social-btn.facebook:hover { background: #1877f2; border-color: #1877f2; }
        .social-btn.x-twitter:hover { background: #000000; border-color: #333333; }
        .social-btn.telegram:hover { background: #0088cc; border-color: #0088cc; }
        .social-btn.whatsapp:hover { background: #25d366; border-color: #25d366; }

        .footer-bottom {
            background-color: #07070a;
            padding: 18px 0;
            border-top: 1px solid #1e1e2e;
            font-size: 0.8rem;
        }

        .footer-bottom-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .legal-links {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .legal-links a {
            color: #6b7280;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .legal-links a:hover {
            color: #9ca3af;
        }

        .legal-links .dot {
            color: #374151;
        }

        /* ============================================
           RESPONSIVE
           ============================================ */
        @media (max-width: 900px) {
            .footer-grid { grid-template-columns: 1fr 1fr; gap: 30px; }
            .brand-col { grid-column: span 2; }
        }

        @media (max-width: 768px) {
            .marquee-content {
                gap: 30px;
                padding: 0 15px;
            }
            .studio-logo {
                height: 28px;
                max-width: 90px;
            }
            .marquee-track {
                animation-duration: 25s;
            }
        }

        @media (max-width: 600px) {
            .footer-grid { grid-template-columns: 1fr; gap: 28px; }
            .brand-col { grid-column: span 1; }
            .footer-bottom-content { flex-direction: column; text-align: center; }
            
            .contact-list {
                gap: 18px;
            }
            .contact-list li {
                line-height: 1.6;
                padding-bottom: 2px;
            }
            
            .footer-logo-img {
                height: 60px;
                width: 200px;
            }
            
            .footer-brand img {
                max-height: 60px;
                max-width: 200px;
            }

            .marquee-content {
                gap: 20px;
                padding: 0 10px;
            }
            .studio-logo {
                height: 24px;
                max-width: 75px;
            }
            .marquee-track {
                animation-duration: 20s;
            }
        }

        @media (max-width: 480px) {
            .marquee-content {
                gap: 15px;
                padding: 0 8px;
            }
            .studio-logo {
                height: 20px;
                max-width: 60px;
            }
            .marquee-track {
                animation-duration: 16s;
            }
        }
    </style>

    <script nonce="<?= htmlspecialchars($cspNonce ?? '') ?>">
    function openDirections(destinationAddress) {
        const encodedDestination = encodeURIComponent(destinationAddress);
        
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    const mapsUrl = `https://www.google.com/maps/dir/?api=1&origin=${lat},${lng}&destination=${encodedDestination}&travelmode=driving`;
                    window.open(mapsUrl, '_blank');
                },
                function(error) {
                    const fallbackUrl = `https://www.google.com/maps/dir/?api=1&destination=${encodedDestination}`;
                    window.open(fallbackUrl, '_blank');
                },
                { enableHighAccuracy: true, timeout: 5000 }
            );
        } else {
            const fallbackUrl = `https://www.google.com/maps/dir/?api=1&destination=${encodedDestination}`;
            window.open(fallbackUrl, '_blank');
        }
    }
    </script>