<?php
/**
 * QuickBite Theme Detector
 * Incluir en el <head> de cada página para evitar parpadeo de tema
 * 
 * Uso: <?php include 'includes/theme-detector.php'; ?>
 */
?>
<!-- Script de detección temprana de tema (evita parpadeo) -->
<script>
    (function() {
        var saved = localStorage.getItem('quickbite-theme');
        var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        var theme = saved === 'dark' || (saved !== 'light' && prefersDark) ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', theme);
        if (theme === 'dark') {
            document.documentElement.style.backgroundColor = '#0f172a';
            document.documentElement.style.colorScheme = 'dark';
        }
    })();
</script>
