<?php
global $pdo;
$config = getSiteConfig($pdo);
// PREFIJO DE RUTA RELATIVA (footer usado desde la raíz o desde user/)
$publicPathPrefix = '';
$publicCssDir = str_replace('\\', '/', dirname(__FILE__));
$publicScriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME'] ?? __FILE__));
if ($publicScriptDir !== $publicCssDir && strpos($publicScriptDir, $publicCssDir . '/') === 0) {
    $publicPathPrefix = str_repeat('../', substr_count(substr($publicScriptDir, strlen($publicCssDir) + 1), '/') + 1);
}
// Enlace de WhatsApp: el número se guarda solo (sin https://wa.me/)
$whatsappHref = !empty($config['whatsapp']) ? 'https://wa.me/' . preg_replace('/[^0-9]/', '', $config['whatsapp']) : '';
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
<?php if ($logo_exists): ?>
<img src="<?= htmlspecialchars($logo_path) . '?v=' . filemtime($logo_path) ?>"
alt="<?= htmlspecialchars($config['site_name'] ?? 'Cinema Pro') ?>"
title="<?= htmlspecialchars($config['site_name'] ?? 'Cinema Pro') ?>"
class="footer-logo-img">
<?php elseif (!empty($logo_path) && filter_var($logo_path, FILTER_VALIDATE_URL)): ?>
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
if ($has_contact):
?>
<div class="footer-col">
<h3 class="footer-heading">Contacto</h3>
<ul class="contact-list">
<?php if (!empty($config['address'])): ?>
<li>
<i class="fas fa-map-marker-alt"></i>
<!-- ✅ CORREGIDO: enlace real + data-address + listener (CSP-safe, sin onclick inline) -->
<a href="https://www.google.com/maps/dir/?api=1&destination=<?= urlencode($config['address']) ?>"
target="_blank" rel="noopener"
class="address-link"
data-address="<?= htmlspecialchars($config['address'], ENT_QUOTES, 'UTF-8') ?>"
title="Clic para calcular la ruta desde tu ubicación actual">
<?= htmlspecialchars($config['address']) ?>
</a>
</li>
<?php endif; ?>
<?php if (!empty($config['email'])): ?>
<li>
<i class="fas fa-envelope"></i>
<a href="mailto:<?= htmlspecialchars($config['email']) ?>"><?= htmlspecialchars($config['email']) ?></a>
</li>
<?php endif; ?>
<?php if (!empty($config['phone']) && !empty($whatsappHref)): ?>
<li>
<i class="fab fa-whatsapp"></i>
<a href="<?= htmlspecialchars($whatsappHref) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($config['phone']) ?></a>
</li>
<?php elseif (!empty($config['phone'])): ?>
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
if ($has_social):
?>
<div class="footer-col">
<h3 class="footer-heading">Síguenos</h3>
<p class="social-intro">Entérate de los próximos estrenos y promociones exclusivas.</p>
<div class="social-links">
<?php if (!empty($config['instagram'])): ?>
<a href="<?= htmlspecialchars($config['instagram']) ?>" target="_blank" rel="noopener" aria-label="Instagram" class="social-btn instagram">
<i class="fab fa-instagram"></i>
</a>
<?php endif; ?>
<?php if (!empty($config['facebook'])): ?>
<a href="<?= htmlspecialchars($config['facebook']) ?>" target="_blank" rel="noopener" aria-label="Facebook" class="social-btn facebook">
<i class="fab fa-facebook-f"></i>
</a>
<?php endif; ?>
<?php if (!empty($config['twitter'])): ?>
<a href="<?= htmlspecialchars($config['twitter']) ?>" target="_blank" rel="noopener" aria-label="X" class="social-btn x-twitter">
<i class="fa-brands fa-x-twitter"></i>
</a>
<?php endif; ?>
<?php if (!empty($config['telegram'])): ?>
<a href="<?= htmlspecialchars($config['telegram']) ?>" target="_blank" rel="noopener" aria-label="Telegram" class="social-btn telegram">
<i class="fab fa-telegram-plane"></i>
</a>
<?php endif; ?>
<?php if (!empty($whatsappHref)): ?>
<a href="<?= htmlspecialchars($whatsappHref) ?>" target="_blank" rel="noopener" aria-label="WhatsApp" class="social-btn whatsapp">
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
// Obtener copyright desde configuración de BD
$footerCopyright = $config['footer_copyright'] ?? '© {year} Cinema. Todos los derechos reservados.';
// Reemplazar placeholder {year} con el año actual
$footerCopyright = str_replace('{year}', date('Y'), $footerCopyright);
// RIF de la empresa (configuración separada)
$companyRif = trim($config['company_rif'] ?? '');
$footerLine = $footerCopyright;
if ($companyRif !== '') {
    $footerLine .= ' RIF: ' . $companyRif;
}
?>
<p><?= htmlspecialchars($footerLine) ?></p>
<div class="legal-links">
<a href="#">Términos y Condiciones</a>
<span class="dot">•</span>
<a href="#">Políticas de Privacidad</a>
</div>
</div>
<!-- ✅ Fila de crédito del desarrollador (centrada, mismo fondo, con enlace) -->
<div class="footer-container footer-dev">
<p>Desarrollado por: <a href="#">Ing Darwin Mavares</a></p>
</div>
</div>
</footer>
<!-- Estilos CSS del Footer (Tema Oscuro) -->
<link rel="stylesheet" href="<?= $publicPathPrefix ?>assets/css/footer.css">
