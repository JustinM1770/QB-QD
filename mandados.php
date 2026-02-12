<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Próximamente - QuickBite</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ====================================
           SOFT UI MINIMALISTA
           ==================================== */
        
        :root {
            --bg-primary: #fff8f3;
            --bg-secondary: #ffffff;
            --blue-primary: #0165FF;
            --blue-light: rgba(1, 101, 255, 0.1);
            --blue-soft: rgba(1, 101, 255, 0.05);
            --text-primary: #2a2a2a;
            --text-secondary: #6b7280;
            --text-light: #9ca3af;
            --shadow-soft: 0 4px 20px rgba(1, 101, 255, 0.08);
            --shadow-medium: 0 8px 40px rgba(1, 101, 255, 0.12);
            --border-radius: 16px;
            --border-radius-large: 24px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* BLOQUEO COMPLETO - NO SE PUEDE QUITAR */
        #countdown-overlay {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            background: rgba(255, 248, 243, 0.85) !important;
            backdrop-filter: blur(20px) !important;
            -webkit-backdrop-filter: blur(20px) !important;
            z-index: 999999999 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            user-select: none !important;
            -webkit-user-select: none !important;
            -moz-user-select: none !important;
            -ms-user-select: none !important;
            pointer-events: all !important;
            overflow: hidden !important;
            font-family: 'Inter', sans-serif !important;
        }

        /* CONTENEDOR PRINCIPAL */
        .countdown-container {
            text-align: center;
            padding: 48px 40px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border-radius: var(--border-radius-large);
            box-shadow: 
                0 20px 60px rgba(1, 101, 255, 0.15),
                0 0 0 1px rgba(255, 255, 255, 0.2);
            max-width: 520px;
            width: 90%;
            position: relative;
            border: 1px solid rgba(1, 101, 255, 0.1);
        }

        /* LOGO/ICONO */
        .countdown-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--blue-primary), #4f9aff);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 32px;
            box-shadow: var(--shadow-soft);
            transition: transform 0.3s ease;
        }

        .countdown-logo i {
            font-size: 32px;
            color: white;
        }

        /* TÍTULO PRINCIPAL */
        .countdown-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 12px;
            letter-spacing: -0.02em;
        }

        /* SUBTÍTULO */
        .countdown-subtitle {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 40px;
            font-weight: 400;
            line-height: 1.5;
        }

        /* CONTENEDOR DEL CONTADOR */
        .countdown-timer {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 32px;
        }

        /* ELEMENTOS INDIVIDUALES DEL CONTADOR */
        .countdown-item {
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
            padding: 24px 12px;
            position: relative;
            box-shadow: var(--shadow-soft);
            border: 1px solid rgba(1, 101, 255, 0.06);
            transition: all 0.3s ease;
        }

        .countdown-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .countdown-number {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--blue-primary);
            display: block;
            margin-bottom: 8px;
            font-family: 'Inter', monospace;
            line-height: 1;
        }

        .countdown-label {
            font-size: 0.8rem;
            color: var(--text-light);
            text-transform: uppercase;
            font-weight: 500;
            letter-spacing: 0.5px;
        }

        /* MENSAJE DE ESTADO */
        .countdown-message {
            background: var(--blue-soft);
            color: var(--blue-primary);
            font-size: 0.95rem;
            font-weight: 500;
            margin-bottom: 24px;
            padding: 12px 20px;
            border-radius: 50px;
            border: 1px solid var(--blue-light);
            display: inline-block;
        }

        /* BARRA DE PROGRESO */
        .progress-container {
            width: 100%;
            height: 6px;
            background: var(--blue-soft);
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 24px;
            position: relative;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--blue-primary), #4f9aff);
            border-radius: 6px;
            width: 0%;
            transition: width 1s ease;
        }

        /* INFO ADICIONAL */
        .countdown-info {
            color: var(--text-light);
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .countdown-info strong {
            color: var(--text-secondary);
            font-weight: 600;
        }

        /* ELEMENTOS FLOTANTES MINIMALISTAS */
        .floating-elements {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
        }

        .floating-circle {
            position: absolute;
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--blue-light), transparent);
            border-radius: 50%;
            opacity: 0.4;
            animation: float 20s ease-in-out infinite;
        }

        .floating-circle:nth-child(1) {
            top: 10%;
            left: 15%;
            animation-delay: 0s;
            width: 60px;
            height: 60px;
        }

        .floating-circle:nth-child(2) {
            top: 20%;
            right: 20%;
            animation-delay: -5s;
            width: 100px;
            height: 100px;
        }

        .floating-circle:nth-child(3) {
            bottom: 15%;
            left: 10%;
            animation-delay: -10s;
            width: 120px;
            height: 120px;
        }

        .floating-circle:nth-child(4) {
            bottom: 25%;
            right: 15%;
            animation-delay: -15s;
            width: 80px;
            height: 80px;
        }

        /* ANIMACIONES SUAVES */
        @keyframes float {
            0%, 100% {
                transform: translateY(0) translateX(0);
            }
            25% {
                transform: translateY(-20px) translateX(10px);
            }
            50% {
                transform: translateY(-10px) translateX(-10px);
            }
            75% {
                transform: translateY(-30px) translateX(5px);
            }
        }

        /* RESPONSIVE LIMPIO */
        @media (max-width: 768px) {
            .countdown-container {
                padding: 36px 24px;
                width: 95%;
                border-radius: 20px;
            }

            .countdown-logo {
                width: 64px;
                height: 64px;
                margin-bottom: 24px;
            }

            .countdown-logo i {
                font-size: 24px;
            }

            .countdown-title {
                font-size: 2rem;
                margin-bottom: 8px;
            }

            .countdown-subtitle {
                font-size: 1rem;
                margin-bottom: 32px;
            }

            .countdown-timer {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
                margin-bottom: 24px;
            }

            .countdown-item {
                padding: 20px 8px;
            }

            .countdown-number {
                font-size: 1.8rem;
            }

            .countdown-label {
                font-size: 0.75rem;
            }

            /* Ocultar elementos flotantes en móvil */
            .floating-circle:nth-child(3),
            .floating-circle:nth-child(4) {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .countdown-container {
                padding: 28px 20px;
                width: 98%;
                border-radius: 16px;
            }

            .countdown-title {
                font-size: 1.75rem;
            }

            .countdown-subtitle {
                font-size: 0.95rem;
            }

            .countdown-timer {
                gap: 8px;
            }

            .countdown-item {
                padding: 16px 6px;
                border-radius: 12px;
            }

            .countdown-number {
                font-size: 1.5rem;
            }

            .countdown-label {
                font-size: 0.7rem;
            }

            .countdown-message {
                font-size: 0.85rem;
                padding: 10px 16px;
            }
        }

        /* ANTI-BYPASS: Permitir que el contenido de fondo se vea */
        html, body {
            height: 100vh !important;
            width: 100% !important;
            /* Removemos overflow: hidden para permitir que se vea el contenido de fondo */
        }

        /* Bloquear herramientas de desarrollador */
        body {
            -webkit-user-select: none !important;
            -moz-user-select: none !important;
            -ms-user-select: none !important;
            user-select: none !important;
            -webkit-touch-callout: none !important;
            -webkit-tap-highlight-color: transparent !important;
        }

        /* Micro animación en hover del logo */
        .countdown-logo:hover {
            transform: scale(1.05);
        }

        /* Transición suave para números que cambian */
        .countdown-number {
            transition: transform 0.2s ease;
        }

        .countdown-number.updating {
            transform: scale(1.1);
        }
    </style>
</head>
<body>
<?php include_once 'includes/valentine.php'; ?>
    <!-- OVERLAY PRINCIPAL - NO SE PUEDE REMOVER -->
    <div id="countdown-overlay">
        <!-- Elementos flotantes minimalistas -->
        <div class="floating-elements">
            <div class="floating-circle"></div>
            <div class="floating-circle"></div>
            <div class="floating-circle"></div>
            <div class="floating-circle"></div>
        </div>

        <!-- Contenedor principal -->
        <div class="countdown-container">
            <!-- Logo/Icono -->
            <div class="countdown-logo">
                <i class="fas fa-rocket"></i>
            </div>

            <!-- Título principal -->
            <h1 class="countdown-title">Próximamente</h1>

            <!-- Subtítulo -->
            <p class="countdown-subtitle">
                Estamos preparando algo increíble para ti.<br>
                La espera valdrá la pena.
            </p>

            <!-- Contador 
            <div class="countdown-timer">
                <div class="countdown-item">
                    <span class="countdown-number" id="days">00</span>
                    <span class="countdown-label">Días</span>
                </div>
                <div class="countdown-item">
                    <span class="countdown-number" id="hours">00</span>
                    <span class="countdown-label">Horas</span>
                </div>
                <div class="countdown-item">
                    <span class="countdown-number" id="minutes">00</span>
                    <span class="countdown-label">Minutos</span>
                </div>
                <div class="countdown-item">
                    <span class="countdown-number" id="seconds">00</span>
                    <span class="countdown-label">Segundos</span>
                </div>
            </div>
-->
            <!-- Mensaje de estado -->
            <div class="countdown-message" id="status-message">
                Preparando el lanzamiento
            </div>

            <!-- Barra de progreso -->
            <div class="progress-container">
                <div class="progress-bar" id="progress-bar"></div>
            </div>

            <!-- Información adicional -->
            <div class="countdown-info">
                <p><strong>QuickBite</strong> está llegando pronto</p>
                <p>Síguenos en nuestras redes sociales para estar al día</p>
            </div>
        </div>
    </div>

    <script>
        // ====================================
        // SISTEMA ANTI-BYPASS Y CONTADOR
        // ====================================

        (function() {
            'use strict';

            // CONFIGURACIÓN DEL CONTADOR
            const LAUNCH_DATE = new Date('2025-08-15T00:00:00').getTime(); // Cambia esta fecha
            const TOTAL_DURATION = LAUNCH_DATE - Date.now();

            // Variables del DOM
            const daysEl = document.getElementById('days');
            const hoursEl = document.getElementById('hours');
            const minutesEl = document.getElementById('minutes');
            const secondsEl = document.getElementById('seconds');
            const progressBar = document.getElementById('progress-bar');
            const statusMessage = document.getElementById('status-message');
            const overlay = document.getElementById('countdown-overlay');

            // ANTI-BYPASS: Protecciones múltiples
            function setupProtections() {
                // Bloquear teclas de desarrollo
                document.addEventListener('keydown', function(e) {
                    if (e.keyCode === 123 || 
                        (e.ctrlKey && e.shiftKey && e.keyCode === 73) ||
                        (e.ctrlKey && e.shiftKey && e.keyCode === 74) ||
                        (e.ctrlKey && e.keyCode === 85) ||
                        (e.ctrlKey && e.keyCode === 83)) {
                        e.preventDefault();
                        return false;
                    }
                });

                // Bloquear clic derecho
                document.addEventListener('contextmenu', function(e) {
                    e.preventDefault();
                    return false;
                });

                // Bloquear selección
                document.addEventListener('selectstart', function(e) {
                    e.preventDefault();
                    return false;
                });

                // Monitor de integridad
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'childList') {
                            if (!document.getElementById('countdown-overlay')) {
                                location.reload();
                            }
                        }
                        if (mutation.type === 'attributes') {
                            if (mutation.target.id === 'countdown-overlay') {
                                if (mutation.target.style.display === 'none') {
                                    mutation.target.style.display = 'flex !important';
                                }
                            }
                        }
                    });
                });

                observer.observe(document.body, {
                    childList: true,
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['style', 'class']
                });
            }

            // Efecto de actualización suave
            function animateNumberChange(element) {
                element.classList.add('updating');
                setTimeout(() => {
                    element.classList.remove('updating');
                }, 200);
            }

            // FUNCIÓN PRINCIPAL DEL CONTADOR
            function updateCountdown() {
                const now = new Date().getTime();
                const distance = LAUNCH_DATE - now;

                if (distance < 0) {
                    // El contador ha terminado
                    overlay.style.display = 'none';
                    document.body.style.overflow = 'auto';
                    document.body.style.position = 'static';
                    return;
                }

                // Calcular tiempo restante
                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                // Actualizar elementos con animación
                const newDays = String(days).padStart(2, '0');
                const newHours = String(hours).padStart(2, '0');
                const newMinutes = String(minutes).padStart(2, '0');
                const newSeconds = String(seconds).padStart(2, '0');

                if (daysEl.textContent !== newDays) {
                    animateNumberChange(daysEl);
                    daysEl.textContent = newDays;
                }
                if (hoursEl.textContent !== newHours) {
                    animateNumberChange(hoursEl);
                    hoursEl.textContent = newHours;
                }
                if (minutesEl.textContent !== newMinutes) {
                    animateNumberChange(minutesEl);
                    minutesEl.textContent = newMinutes;
                }
                if (secondsEl.textContent !== newSeconds) {
                    animateNumberChange(secondsEl);
                    secondsEl.textContent = newSeconds;
                }

                // Actualizar barra de progreso
                const elapsed = TOTAL_DURATION - distance;
                const progress = Math.max(0, (elapsed / TOTAL_DURATION) * 100);
                progressBar.style.width = progress + '%';

                // Actualizar mensaje según el tiempo restante
                if (days > 7) {
                    statusMessage.textContent = 'Preparando el lanzamiento';
                } else if (days > 1) {
                    statusMessage.textContent = '¡Ya casi estamos listos!';
                } else if (hours > 1) {
                    statusMessage.textContent = '¡Últimas horas!';
                } else {
                    statusMessage.textContent = '¡Los últimos minutos!';
                }
            }

            // INICIALIZACIÓN
            function init() {
                setupProtections();
                updateCountdown();
                
                // Actualizar cada segundo
                setInterval(updateCountdown, 1000);

                // Verificar integridad del overlay cada 100ms
                setInterval(function() {
                    if (!document.getElementById('countdown-overlay') || 
                        overlay.style.display === 'none') {
                        location.reload();
                    }
                }, 100);
            }

            // Iniciar cuando el DOM esté listo
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }

        })();
    </script>
</body>
</html>