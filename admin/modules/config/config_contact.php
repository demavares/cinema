<?php
// ============================================
// CONFIGURACIÓN — SECCIÓN: CONTACTO Y REDES SOCIALES
// ============================================
?>
<form action="modules/config/config_actions.php" method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="hidden" name="action" value="save_config">
    <input type="hidden" name="section" value="contact">

    <!-- ============================================ -->
    <!-- Card: Información de Contacto                 -->
    <!-- ============================================ -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title">📞 Información de Contacto</h3>
        </div>
        <div class="admin-card-body">
            <div class="movie-form-help">
                <p>ℹ️ Datos de contacto del cine que se muestran en el sitio público.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Dirección -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
                    <input type="text" name="address"
                           value="<?= htmlspecialchars($siteConfig['address'] ?? '') ?>"
                           placeholder="Dirección del cine"
                           class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <!-- Teléfono -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                    <input type="text" name="phone" maxlength="15"
                           value="<?= htmlspecialchars($siteConfig['phone'] ?? '') ?>"
                           placeholder="Teléfono de contacto"
                           class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <!-- Email -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email"
                           value="<?= htmlspecialchars($siteConfig['email'] ?? '') ?>"
                           placeholder="contacto@cine.com"
                           class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- Card: Redes Sociales                          -->
    <!-- ============================================ -->
    <div class="admin-card mt-6">
        <div class="admin-card-header">
            <h3 class="admin-card-title">📱 Redes Sociales</h3>
        </div>
        <div class="admin-card-body">
            <div class="movie-form-help">
                <p>ℹ️ Enlaces a tus redes sociales. Se muestran como iconos en el sitio público. Deben ser URL válidas.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Instagram -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><i class="fab fa-instagram text-pink-500 mr-1"></i>Instagram</label>
                    <input type="url" name="instagram"
                           value="<?= htmlspecialchars($siteConfig['instagram'] ?? '') ?>"
                           placeholder="https://instagram.com/tuusuario"
                           class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <!-- Facebook -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><i class="fab fa-facebook text-blue-500 mr-1"></i>Facebook</label>
                    <input type="url" name="facebook"
                           value="<?= htmlspecialchars($siteConfig['facebook'] ?? '') ?>"
                           placeholder="https://facebook.com/tupagina"
                           class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <!-- X (Twitter) -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><i class="fa-brands fa-x-twitter text-gray-900 mr-1"></i>X (Twitter)</label>
                    <input type="url" name="twitter"
                           value="<?= htmlspecialchars($siteConfig['twitter'] ?? '') ?>"
                           placeholder="https://x.com/tuusuario"
                           class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <!-- Telegram -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><i class="fab fa-telegram text-sky-500 mr-1"></i>Telegram</label>
                    <input type="url" name="telegram"
                           value="<?= htmlspecialchars($siteConfig['telegram'] ?? '') ?>"
                           placeholder="https://t.me/tucanal"
                           class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <!-- WhatsApp -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><i class="fab fa-whatsapp text-green-500 mr-1"></i>WhatsApp</label>
                    <input type="text" name="whatsapp" maxlength="15" inputmode="tel"
                           value="<?= htmlspecialchars($siteConfig['whatsapp'] ?? '') ?>"
                           placeholder="+58123456789"
                           class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <p class="text-xs text-gray-500 mt-1">WhatsApp (solo número de teléfono. Ej: +58123456789).</p>
                </div>

                <button type="submit" class="md:col-span-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-lg transition-colors shadow-md">
                    Guardar
                </button>
            </div>
        </div>
    </div>
</form>