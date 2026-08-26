<?php
// Obtener configuración para el footer
$siteConfig = getSiteConfig($pdo);
?>
        </div>
    </main>

    <!-- JavaScript del Admin -->
    <script src="js/admin.js"></script>

    <!-- Scripts específicos por módulo -->
    <?php if ($activeTab === 'showtimes'): ?>
        <script src="js/showtimes.js"></script>
    <?php endif; ?>

    <?php if ($activeTab === 'movies'): ?>
        <script src="js/movies.js"></script>
    <?php endif; ?>

    <?php if ($activeTab === 'users'): ?>
        <script src="js/users.js"></script>
    <?php endif; ?>

    <?php if ($activeTab === 'food'): ?>
        <script src="js/food.js"></script>
    <?php endif; ?>
</body>
</html>