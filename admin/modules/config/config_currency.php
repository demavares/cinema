<?php
// ============================================
// CONFIGURACIÓN — SECCIÓN: MONEDA Y FORMATO E IMPUESTOS
// ============================================
?>
<form action="modules/config/config_actions.php" method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="hidden" name="action" value="save_config">
    <input type="hidden" name="section" value="currency">

    <!-- ============================================ -->
    <!-- Card: Moneda y Formato                        -->
    <!-- ============================================ -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title">💰 Moneda y Formato</h3>
        </div>
        <div class="admin-card-body">
            <div class="movie-form-help">
                <p>ℹ️ Define cómo se muestran los precios y cantidades de dinero en todo el sitio web.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Símbolo de Moneda -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Símbolo de Moneda</label>
                    <input type="text" name="currency_symbol" maxlength="5"
                           value="<?= htmlspecialchars($siteConfig['currency_symbol'] ?? '$') ?>"
                           placeholder="$"
                           class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <!-- Posición del Símbolo -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Posición del Símbolo</label>
                    <select name="currency_position" class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="left" <?= ($siteConfig['currency_position'] ?? 'left') === 'left' ? 'selected' : '' ?>>Izquierda (ej: $100)</option>
                        <option value="right" <?= ($siteConfig['currency_position'] ?? 'left') === 'right' ? 'selected' : '' ?>>Derecha (ej: 100 $)</option>
                    </select>
                </div>

                <!-- Separador de Miles -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Separador de Miles</label>
                    <input type="text" name="thousands_separator" maxlength="1"
                           value="<?= htmlspecialchars($siteConfig['thousands_separator'] ?? '.') ?>"
                           placeholder="."
                           class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <!-- Separador Decimal -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Separador Decimal</label>
                    <input type="text" name="decimal_separator" maxlength="1"
                           value="<?= htmlspecialchars($siteConfig['decimal_separator'] ?? ',') ?>"
                           placeholder=","
                           class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <!-- Número de Decimales -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Número de Decimales</label>
                    <select name="decimal_places" class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="0" <?= ($siteConfig['decimal_places'] ?? '2') == '0' ? 'selected' : '' ?>>0</option>
                        <option value="1" <?= ($siteConfig['decimal_places'] ?? '2') == '1' ? 'selected' : '' ?>>1</option>
                        <option value="2" <?= ($siteConfig['decimal_places'] ?? '2') == '2' ? 'selected' : '' ?>>2</option>
                        <option value="3" <?= ($siteConfig['decimal_places'] ?? '2') == '3' ? 'selected' : '' ?>>3</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- Card: Impuestos                              -->
    <!-- ============================================ -->
    <div class="admin-card mt-6">
        <div class="admin-card-header">
            <h3 class="admin-card-title">🧾 Impuestos</h3>
        </div>
        <div class="admin-card-body">
            <div class="movie-form-help">
                <p>ℹ️ Define el porcentaje de impuesto (IVA) que se aplicará a los precios del cine.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Porcentaje de IVA -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Porcentaje de IVA (%)</label>
                    <input type="number" name="tax_rate" step="0.01" min="0" max="100"
                           value="<?= htmlspecialchars($taxRate) ?>"
                           placeholder="16"
                           class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <p class="text-xs text-gray-500 mt-1">Ejemplo: 16 para 16%</p>
                </div>

                <button type="submit" class="md:col-span-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-lg transition-colors shadow-md">
                    Guardar
                </button>
            </div>
        </div>
    </div>
</form>