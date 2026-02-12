<?php http_response_code(500); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error del servidor - QuickBite</title>
    <link rel="icon" type="image/x-icon" href="/assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #0165FF;
            --primary-light: #E3F2FD;
            --accent: #FF9500;
            --dark: #2F2F2F;
            --light: #FAFAFA;
            --medium-gray: #888;
            --danger: #FF4D4D;
            --warning: #FFB84D;
            --shadow-lg: 0 20px 40px rgba(0, 0, 0, 0.1);
            --border-radius: 20px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--light) 0%, #f0f4f8 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark);
            overflow: hidden;
        }

        .container {
            text-align: center;
            max-width: 600px;
            padding: 40px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            position: relative;
            z-index: 10;
        }

        .error-number {
            font-size: 8rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--warning), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 20px;
            animation: glitch 3s infinite;
        }

        .error-title {
            font-family: 'DM Sans', sans-serif;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--dark);
        }

        .error-description {
            font-size: 1.2rem;
            color: var(--medium-gray);
            margin-bottom: 40px;
            line-height: 1.6;
        }

        .error-icon {
            font-size: 4rem;
            color: var(--warning);
            margin-bottom: 30px;
            opacity: 0.7;
            animation: spin 4s linear infinite;
        }

        .buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #0153CC);
            color: white;
            box-shadow: 0 4px 15px rgba(1, 101, 255, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(1, 101, 255, 0.4);
            color: white;
            text-decoration: none;
        }

        .btn-secondary {
            background: white;
            color: var(--dark);
            border: 2px solid var(--warning);
        }

        .btn-secondary:hover {
            background: var(--warning);
            color: white;
            transform: translateY(-3px);
            text-decoration: none;
        }

        /* Elementos flotantes de fondo */
        .floating-elements {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }

        .floating-element {
            position: absolute;
            opacity: 0.1;
            animation: floatAround 25s infinite linear;
            color: var(--warning);
        }

        .floating-element:nth-child(1) {
            top: 10%;
            left: 10%;
            font-size: 3rem;
            animation-delay: 0s;
        }

        .floating-element:nth-child(2) {
            top: 20%;
            right: 15%;
            font-size: 2.5rem;
            animation-delay: -5s;
        }

        .floating-element:nth-child(3) {
            bottom: 20%;
            left: 20%;
            font-size: 2rem;
            animation-delay: -10s;
        }

        .floating-element:nth-child(4) {
            bottom: 30%;
            right: 10%;
            font-size: 3.5rem;
            animation-delay: -15s;
        }

        .floating-element:nth-child(5) {
            top: 50%;
            left: 50%;
            font-size: 2.8rem;
            animation-delay: -20s;
        }

        @keyframes glitch {
            0%, 90%, 100% {
                transform: translateX(0);
            }
            91%, 92% {
                transform: translateX(-2px);
            }
            93%, 94% {
                transform: translateX(2px);
            }
            95%, 96% {
                transform: translateX(-1px);
            }
            97%, 98% {
                transform: translateX(1px);
            }
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }

        @keyframes floatAround {
            0% {
                transform: translate(0, 0) rotate(0deg);
            }
            25% {
                transform: translate(120px, -80px) rotate(90deg);
            }
            50% {
                transform: translate(-80px, -160px) rotate(180deg);
            }
            75% {
                transform: translate(-160px, -40px) rotate(270deg);
            }
            100% {
                transform: translate(0, 0) rotate(360deg);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                margin: 20px;
                padding: 30px 20px;
            }

            .error-number {
                font-size: 6rem;
            }

            .error-title {
                font-size: 2rem;
            }

            .error-description {
                font-size: 1rem;
            }

            .buttons {
                flex-direction: column;
                align-items: center;
            }

            .btn {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }
        }

        /* Efecto de partículas */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: var(--warning);
            border-radius: 50%;
            opacity: 0.4;
            animation: particleFloat 18s infinite linear;
        }

        @keyframes particleFloat {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 0.4;
            }
            90% {
                opacity: 0.4;
            }
            100% {
                transform: translateY(-100vh) rotate(360deg);
                opacity: 0;
            }
        }

        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            background: var(--warning);
            border-radius: 50%;
            margin-right: 8px;
            animation: blink 2s infinite;
        }

        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0.3; }
        }
        :root {
    --container-padding: 40px;
    --error-number-size: 8rem;
    --error-title-size: 2.5rem;
    --error-description-size: 1.2rem;
    --icon-size: 4rem;
    --button-padding: 15px 30px;
    --gap-size: 20px;
    --border-radius: 20px;
    --container-max-width: 600px;
}

/* ========================================
   DESKTOP FIRST APPROACH
   ======================================== */

.container {
    text-align: center;
    max-width: var(--container-max-width);
    padding: var(--container-padding);
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-lg);
    position: relative;
    z-index: 10;
    margin: 20px;
    transition: all 0.3s ease;
}

.error-number {
    font-size: var(--error-number-size);
    font-weight: 700;
    margin-bottom: 20px;
    transition: font-size 0.3s ease;
    line-height: 1;
}

.error-title {
    font-family: 'DM Sans', sans-serif;
    font-size: var(--error-title-size);
    font-weight: 700;
    margin-bottom: 15px;
    color: var(--dark);
    transition: font-size 0.3s ease;
    line-height: 1.2;
}

.error-description {
    font-size: var(--error-description-size);
    color: var(--medium-gray);
    margin-bottom: 40px;
    line-height: 1.6;
    transition: font-size 0.3s ease;
}

.error-icon {
    font-size: var(--icon-size);
    margin-bottom: 30px;
    opacity: 0.7;
    transition: font-size 0.3s ease;
}

.buttons {
    display: flex;
    gap: var(--gap-size);
    justify-content: center;
    flex-wrap: wrap;
    margin-top: 40px;
}

.btn {
    padding: var(--button-padding);
    border: none;
    border-radius: 12px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    font-size: 1rem;
    min-width: 160px;
    justify-content: center;
    white-space: nowrap;
}

/* ========================================
   TABLET LANDSCAPE (1024px - 1200px)
   ======================================== */
@media screen and (max-width: 1200px) {
    :root {
        --container-max-width: 550px;
        --container-padding: 35px;
    }
    
    .container {
        margin: 15px;
    }
}

/* ========================================
   TABLET PORTRAIT (768px - 1024px)
   ======================================== */
@media screen and (max-width: 1024px) {
    :root {
        --container-max-width: 500px;
        --container-padding: 30px;
        --error-number-size: 7rem;
        --error-title-size: 2.2rem;
        --error-description-size: 1.1rem;
        --icon-size: 3.5rem;
    }

    .buttons {
        gap: 15px;
    }

    .btn {
        min-width: 140px;
        font-size: 0.95rem;
    }

    /* Ajuste para elementos flotantes en tablet */
    .floating-element {
        font-size: 2rem !important;
    }

    .floating-element:nth-child(1),
    .floating-element:nth-child(4) {
        font-size: 2.5rem !important;
    }
}

/* ========================================
   MOBILE LARGE (600px - 768px)
   ======================================== */
@media screen and (max-width: 768px) {
    :root {
        --container-padding: 25px;
        --error-number-size: 6rem;
        --error-title-size: 2rem;
        --error-description-size: 1rem;
        --icon-size: 3rem;
        --button-padding: 12px 25px;
        --gap-size: 15px;
        --border-radius: 15px;
    }

    .container {
        margin: 10px;
        max-width: calc(100vw - 20px);
    }

    .error-description {
        margin-bottom: 30px;
    }

    .buttons {
        flex-direction: column;
        align-items: center;
        gap: 12px;
        margin-top: 30px;
    }

    .btn {
        width: 100%;
        max-width: 280px;
        min-width: auto;
        padding: 14px 20px;
        font-size: 0.95rem;
    }

    /* Ocultar algunos elementos flotantes en mobile */
    .floating-element:nth-child(3),
    .floating-element:nth-child(4) {
        display: none;
    }

    .floating-element {
        font-size: 1.5rem !important;
    }

    /* Reducir partículas en mobile */
    .particle:nth-child(n+11) {
        display: none;
    }
}

/* ========================================
   MOBILE MEDIUM (480px - 600px)
   ======================================== */
@media screen and (max-width: 600px) {
    :root {
        --container-padding: 20px;
        --error-number-size: 5rem;
        --error-title-size: 1.8rem;
        --error-description-size: 0.95rem;
        --icon-size: 2.5rem;
    }

    .container {
        margin: 8px;
        border-radius: 12px;
    }

    .error-description {
        margin-bottom: 25px;
        line-height: 1.5;
    }

    .buttons {
        margin-top: 25px;
    }

    .btn {
        max-width: 250px;
        padding: 12px 18px;
        font-size: 0.9rem;
        gap: 8px;
    }

    /* Status indicator más pequeño */
    .status-indicator {
        width: 10px;
        height: 10px;
        margin-right: 6px;
    }
}

/* ========================================
   MOBILE SMALL (320px - 480px)
   ======================================== */
@media screen and (max-width: 480px) {
    :root {
        --container-padding: 15px;
        --error-number-size: 4rem;
        --error-title-size: 1.5rem;
        --error-description-size: 0.9rem;
        --icon-size: 2rem;
    }

    body {
        padding: 5px;
    }

    .container {
        margin: 5px;
        border-radius: 10px;
        min-height: auto;
    }

    .error-number {
        margin-bottom: 15px;
    }

    .error-title {
        margin-bottom: 10px;
        line-height: 1.1;
    }

    .error-description {
        margin-bottom: 20px;
        padding: 0 5px;
    }

    .error-icon {
        margin-bottom: 20px;
    }

    .buttons {
        margin-top: 20px;
        gap: 10px;
    }

    .btn {
        max-width: 220px;
        padding: 10px 15px;
        font-size: 0.85rem;
        border-radius: 8px;
    }

    /* Ocultar más elementos flotantes */
    .floating-element:nth-child(2) {
        display: none;
    }

    /* Reducir más partículas */
    .particle:nth-child(n+6) {
        display: none;
    }
}

/* ========================================
   MOBILE EXTRA SMALL (< 320px)
   ======================================== */
@media screen and (max-width: 320px) {
    :root {
        --container-padding: 12px;
        --error-number-size: 3.5rem;
        --error-title-size: 1.3rem;
        --error-description-size: 0.85rem;
        --icon-size: 1.8rem;
    }

    .container {
        margin: 3px;
        border-radius: 8px;
    }

    .error-description {
        padding: 0 3px;
    }

    .btn {
        max-width: 200px;
        font-size: 0.8rem;
        padding: 8px 12px;
    }

    /* Solo mostrar un elemento flotante */
    .floating-element:not(:first-child) {
        display: none;
    }

    /* Mínimas partículas */
    .particle:nth-child(n+4) {
        display: none;
    }
}

/* ========================================
   LANDSCAPE ORIENTATION ADJUSTMENTS
   ======================================== */
@media screen and (max-height: 600px) and (orientation: landscape) {
    body {
        overflow-y: auto;
        align-items: flex-start;
        padding-top: 20px;
        padding-bottom: 20px;
    }

    .container {
        margin: 10px auto;
        max-height: 90vh;
        overflow-y: auto;
    }

    :root {
        --error-number-size: 4rem;
        --error-title-size: 1.8rem;
        --icon-size: 2.5rem;
        --container-padding: 20px;
    }

    .error-description {
        margin-bottom: 20px;
    }

    .buttons {
        margin-top: 20px;
    }

    /* Ocultar elementos flotantes en landscape móvil */
    .floating-elements {
        display: none;
    }
}

/* ========================================
   HIGH DPI / RETINA DISPLAYS
   ======================================== */
@media screen and (-webkit-min-device-pixel-ratio: 2),
       screen and (min-resolution: 192dpi) {
    .btn {
        border-width: 0.5px;
    }
    
    .particle {
        width: 3px;
        height: 3px;
    }
}

/* ========================================
   REDUCED MOTION PREFERENCES
   ======================================== */
@media (prefers-reduced-motion: reduce) {
    .error-number,
    .error-icon,
    .floating-element,
    .particle {
        animation: none !important;
    }
    
    .container {
        transform: none !important;
    }
    
    .btn {
        transition: background-color 0.2s ease, color 0.2s ease;
    }
    
    .btn:hover {
        transform: none;
    }
}

/* ========================================
   DARK MODE SUPPORT (si quieres implementarlo)
   ======================================== */
@media (prefers-color-scheme: dark) {
    /* Descomenta si quieres soporte para dark mode
    :root {
        --dark: #ffffff;
        --light: #1a1a1a;
        --medium-gray: #cccccc;
    }
    
    body {
        background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
    }
    
    .container {
        background: #2d2d2d;
        color: white;
    }
    */
}

/* ========================================
   PRINT STYLES
   ======================================== */
@media print {
    .floating-elements,
    .particles,
    .btn-secondary {
        display: none !important;
    }
    
    body {
        background: white !important;
        color: black !important;
    }
    
    .container {
        box-shadow: none !important;
        border: 1px solid #ccc;
        page-break-inside: avoid;
    }
    
    .error-number {
        color: black !important;
        -webkit-text-fill-color: black !important;
    }
}
    </style>
</head>
<body>
    <!-- Elementos flotantes de fondo -->
    <div class="floating-elements">
        <i class="fas fa-server floating-element"></i>
        <i class="fas fa-exclamation-triangle floating-element"></i>
        <i class="fas fa-tools floating-element"></i>
        <i class="fas fa-cog floating-element"></i>
        <i class="fas fa-wifi floating-element"></i>
    </div>

    <!-- Partículas flotantes -->
    <div class="particles" id="particles"></div>

    <!-- Contenido principal -->
    <div class="container">
        <div class="error-icon">
            <i class="fas fa-server"></i>
        </div>
        
        <div class="error-number">50x</div>
        
        <h1 class="error-title">¡Error del Servidor!</h1>
        
        <p class="error-description">
            <span class="status-indicator"></span>
            Estamos experimentando problemas técnicos temporales. 
            Nuestro equipo está trabajando para solucionarlo. Por favor, inténtalo de nuevo en unos minutos.
        </p>

        <div class="buttons">
            <a href="/" class="btn btn-primary">
                <i class="fas fa-home"></i>
                Volver al inicio
            </a>
            <a href="javascript:location.reload()" class="btn btn-secondary">
                <i class="fas fa-redo"></i>
                Recargar página
            </a>
        </div>
    </div>

    <script>
        // Crear partículas flotantes
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 25;

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 18 + 's';
                particle.style.animationDuration = (Math.random() * 8 + 12) + 's';
                particlesContainer.appendChild(particle);
            }
        }

        // Inicializar partículas
        createParticles();

        // Efecto de movimiento del mouse
        document.addEventListener('mousemove', (e) => {
            const container = document.querySelector('.container');
            const x = (e.clientX / window.innerWidth) * 3;
            const y = (e.clientY / window.innerHeight) * 3;
            
            container.style.transform = `translate(${x}px, ${y}px)`;
        });

        // Auto-recarga cada 30 segundos
        setTimeout(() => {
            if (confirm('¿Quieres intentar recargar la página automáticamente?')) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>