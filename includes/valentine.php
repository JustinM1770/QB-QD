<?php
// ═══════════════════════════════════════════════════════════════
// TEMÁTICA DE SAN VALENTÍN - Auto-activación del 1 al 15 de febrero
// ═══════════════════════════════════════════════════════════════
$mes_actual = (int)date('m');
$dia_actual = (int)date('d');
$es_san_valentin = ($mes_actual === 2 && $dia_actual >= 1 && $dia_actual <= 15);

if (!$es_san_valentin) return;
?>

<!-- San Valentín Theme -->
<style>
/* ═══ CORAZONES FLOTANTES ═══ */
.valentine-container {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    pointer-events: none;
    z-index: 9990;
    overflow: hidden;
}

.valentine-heart {
    position: absolute;
    bottom: -50px;
    opacity: 0;
    font-size: 20px;
    animation: valentine-float linear forwards;
    filter: drop-shadow(0 0 3px rgba(255, 23, 68, 0.3));
}

@keyframes valentine-float {
    0% {
        opacity: 0;
        transform: translateY(0) translateX(0) rotate(0deg) scale(0.5);
    }
    10% {
        opacity: 0.7;
    }
    50% {
        opacity: 0.5;
        transform: translateY(-45vh) translateX(30px) rotate(15deg) scale(0.8);
    }
    100% {
        opacity: 0;
        transform: translateY(-100vh) translateX(-20px) rotate(-10deg) scale(0.6);
    }
}

/* ═══ BANNER SAN VALENTÍN ═══ */
.valentine-banner {
    background: linear-gradient(135deg, #E91E63, #FF1744, #E91E63);
    color: white;
    text-align: center;
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 500;
    letter-spacing: 0.5px;
    position: relative;
    z-index: 1050;
    font-family: 'Inter', sans-serif;
    box-shadow: 0 2px 8px rgba(233, 30, 99, 0.3);
}

.valentine-banner span {
    animation: valentine-pulse 2s ease-in-out infinite;
    display: inline-block;
}

@keyframes valentine-pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.2); }
}

.valentine-banner .valentine-close {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.8);
    font-size: 18px;
    cursor: pointer;
    pointer-events: auto;
    padding: 4px 8px;
    line-height: 1;
}

.valentine-banner .valentine-close:hover {
    color: white;
}

/* ═══ ACENTOS DE COLOR ═══ */
body.valentine-active {
    --valentine-primary: #E91E63;
    --valentine-light: #FCE4EC;
    --valentine-dark: #AD1457;
}

body.valentine-active .btn-primary,
body.valentine-active .btn-success {
    background: linear-gradient(135deg, #E91E63, #FF1744) !important;
    border-color: #E91E63 !important;
}

body.valentine-active .btn-primary:hover,
body.valentine-active .btn-success:hover {
    background: linear-gradient(135deg, #AD1457, #E91E63) !important;
    border-color: #AD1457 !important;
}

body.valentine-active .btn-outline-primary {
    color: #E91E63 !important;
    border-color: #E91E63 !important;
}

body.valentine-active .btn-outline-primary:hover {
    background: #E91E63 !important;
    color: white !important;
}

body.valentine-active .text-primary {
    color: #E91E63 !important;
}

body.valentine-active .bg-primary {
    background: linear-gradient(135deg, #E91E63, #FF1744) !important;
}

/* ═══ SOPORTE DARK MODE ═══ */
[data-theme="dark"] .valentine-banner,
html.dark-mode .valentine-banner {
    background: linear-gradient(135deg, #880E4F, #AD1457, #880E4F);
    box-shadow: 0 2px 8px rgba(136, 14, 79, 0.5);
}

[data-theme="dark"] .valentine-heart,
html.dark-mode .valentine-heart {
    filter: drop-shadow(0 0 6px rgba(255, 107, 157, 0.5));
}

/* ═══ RESPONSIVE ═══ */
@media (max-width: 768px) {
    .valentine-banner {
        font-size: 12px;
        padding: 6px 12px;
    }
    .valentine-heart {
        font-size: 16px;
    }
}

/* Reducir animaciones si el usuario lo prefiere */
@media (prefers-reduced-motion: reduce) {
    .valentine-heart {
        animation: none !important;
        display: none;
    }
    .valentine-banner span {
        animation: none !important;
    }
}
</style>

<!-- Banner de San Valentín -->
<div class="valentine-banner" id="valentineBanner" style="display: none;">
    <span>&#10084;&#65039;</span> &nbsp;Celebra San Valent&iacute;n con QuickBite &nbsp;<span>&#10084;&#65039;</span>
    <button class="valentine-close" onclick="document.getElementById('valentineBanner').style.display='none'; sessionStorage.setItem('valentine-banner-closed','1');" aria-label="Cerrar">&times;</button>
</div>

<!-- Contenedor de corazones -->
<div class="valentine-container" id="valentineContainer"></div>

<script>
(function() {
    // Banner: mostrar solo si no fue cerrado en esta sesión
    if (!sessionStorage.getItem('valentine-banner-closed')) {
        document.getElementById('valentineBanner').style.display = 'block';
    }

    // Activar clase en body
    document.body.classList.add('valentine-active');

    // Generar corazones flotantes
    var container = document.getElementById('valentineContainer');
    var hearts = ['\u2764\uFE0F', '\uD83E\uDE77', '\uD83D\uDC95', '\uD83D\uDC96', '\uD83D\uDC97', '\uD83D\uDC93', '\u2763\uFE0F', '\uD83C\uDF39'];
    var maxHearts = 12;
    var activeHearts = 0;

    function createHeart() {
        if (activeHearts >= maxHearts) return;

        var heart = document.createElement('div');
        heart.className = 'valentine-heart';
        heart.textContent = hearts[Math.floor(Math.random() * hearts.length)];
        heart.style.left = (Math.random() * 95) + '%';
        heart.style.fontSize = (14 + Math.random() * 18) + 'px';
        heart.style.animationDuration = (6 + Math.random() * 8) + 's';
        heart.style.animationDelay = (Math.random() * 2) + 's';

        container.appendChild(heart);
        activeHearts++;

        heart.addEventListener('animationend', function() {
            heart.remove();
            activeHearts--;
        });
    }

    // Crear corazones iniciales escalonados
    for (var i = 0; i < 6; i++) {
        setTimeout(createHeart, i * 800);
    }

    // Crear corazones periódicamente
    setInterval(function() {
        if (activeHearts < maxHearts) createHeart();
    }, 3000);
})();
</script>
