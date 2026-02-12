<?php
session_start();

if (isset($_SESSION['registro_exitoso']) && $_SESSION['registro_exitoso'] === true) {
    $mensaje_registro = true;
    $mensaje_validacion = isset($_SESSION['mensaje_validacion']) ? $_SESSION['mensaje_validacion'] : "¡Registro exitoso! Bienvenido a QuickBite. Tu registro se encuentra en validacion, no te preocupes, Ya eres parte de QuickBite...!";
    unset($_SESSION['registro_exitoso']); // Limpiar la sesión
    unset($_SESSION['mensaje_validacion']); // Limpiar mensaje de validación
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuickBite - Próximamente</title>
    <link rel="icon" type="image/x-icon" href="assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Bootstrap CSS para la alerta -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #0165FF;
            --dark: #1a1a1a;
            --light-gray: #f8f9fa;
            --medium-gray: #6c757d;
            --border: #e9ecef;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #fff8f3;
            color: var(--dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            line-height: 1.6;
        }

        h1, h2 {
            font-family: 'DM Sans', sans-serif;
            font-weight: 600;
        }

        /* Main container */
        .container {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 2rem;
            max-width: 800px;
            margin: 0 auto;
            text-align: center;
        }

        /* Logo section */
        .logo-section {
            margin-bottom: 4rem;
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.8s ease-out 0.2s forwards;
        }

        .logo {
            width: 80px;
            height: 80px;
            background: var(--primary);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            transition: transform 0.3s ease;
        }

        .logo:hover {
            transform: scale(1.05);
        }

        .logo i {
            font-size: 2rem;
            color: white;
        }

        .brand-name {
            font-size: 3rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
            letter-spacing: -1px;
        }

        .tagline {
            font-size: 1.1rem;
            color: var(--medium-gray);
            font-weight: 400;
        }

        /* Coming soon section */
        .coming-soon {
            margin-bottom: 4rem;
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.8s ease-out 0.4s forwards;
        }

        .coming-soon h2 {
            font-size: 1.5rem;
            color: var(--dark);
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .description {
            font-size: 1rem;
            color: var(--medium-gray);
            max-width: 500px;
            margin: 0 auto 2rem;
            line-height: 1.7;
        }

        /* Buttons */
        .buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.8s ease-out 0.6s forwards;
        }

        .btn {
            padding: 1rem 2rem;
            border: 2px solid var(--border);
            background: white;
            color: var(--dark);
            text-decoration: none;
            border-radius: 12px;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 180px;
            justify-content: center;
        }

        .btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(1, 101, 255, 0.1);
            text-decoration: none;
        }

        .btn i {
            font-size: 1rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background: #0056d3;
            border-color: #0056d3;
            color: white;
        }

        /* Footer */
        .footer {
            padding: 2rem;
            text-align: center;
            border-top: 1px solid var(--border);
            margin-top: auto;
            opacity: 0;
            animation: fadeIn 0.8s ease-out 0.8s forwards;
        }

        .footer p {
            font-size: 0.85rem;
            color: var(--medium-gray);
            margin-bottom: 0.5rem;
        }

        .developed-by {
            font-size: 0.8rem;
            color: var(--medium-gray);
        }

        .developed-by a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .developed-by a:hover {
            text-decoration: underline;
        }

        /* Decorative elements */
        .decoration {
            position: absolute;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(1, 101, 255, 0.05) 0%, rgba(1, 101, 255, 0.02) 100%);
            z-index: -1;
        }

        .decoration-1 {
            top: 10%;
            right: 10%;
            animation: float 6s ease-in-out infinite;
        }

        .decoration-2 {
            bottom: 20%;
            left: 10%;
            width: 150px;
            height: 150px;
            animation: float 8s ease-in-out infinite reverse;
        }

        /* Alerta de registro - SIMPLIFICADA */
        @media (max-width: 768px) {
            #alertaRegistro {
                top: 10px !important;
                right: 10px !important;
                left: 10px !important;
                max-width: none !important;
            }
        }

        /* Animations */
        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            to {
                opacity: 1;
            }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
            }

            .brand-name {
                font-size: 2.5rem;
            }

            .buttons {
                flex-direction: column;
                align-items: center;
                gap: 0.8rem;
            }

            .btn {
                width: 100%;
                max-width: 280px;
            }

            .decoration {
                display: none;
            }

            .alert-registro {
                top: 10px;
                right: 10px;
                left: 10px;
                max-width: none;
                margin: 0;
            }
        }

        @media (max-width: 480px) {
            .brand-name {
                font-size: 2rem;
            }

            .logo {
                width: 70px;
                height: 70px;
            }

            .logo i {
                font-size: 1.8rem;
            }

            .coming-soon h2 {
                font-size: 1.3rem;
            }

            .description {
                font-size: 0.95rem;
            }
        }

        /* Loading state */
        .loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: white;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 1;
            transition: opacity 0.5s ease-out;
        }

        .loading.hidden {
            opacity: 0;
            pointer-events: none;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid var(--border);
            border-top: 3px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Loading -->
    <div class="loading" id="loading">
        <div class="spinner"></div>
    </div>

    <!-- Alerta de registro SIMPLE -->
    <?php if (isset($mensaje_registro) && $mensaje_registro): ?>
    <div id="alertaRegistro" style="position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 400px; background: #d4edda; color: #155724; padding: 15px 20px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 1px solid #c3e6cb; display: block;">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <strong>🎉 ¡Registro Completado!</strong><br>
                <span><?php echo htmlspecialchars($mensaje_validacion); ?></span>
            </div>
            <button onclick="cerrarAlerta()" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #155724; margin-left: 15px;">&times;</button>
        </div>
    </div>
    
    <script>
        // Auto-cerrar después de 6 segundos
        setTimeout(function() {
            cerrarAlerta();
        }, 10000);
        
        function cerrarAlerta() {
            var alerta = document.getElementById('alertaRegistro');
            if (alerta) {
                alerta.style.display = 'none';
            }
        }
    </script>
    <?php endif; ?>

    <!-- Decorative elements -->
    <div class="decoration decoration-1"></div>
    <div class="decoration decoration-2"></div>

    <!-- Main content -->
    <div class="container">
        <div class="logo-section">
            <div class="logo">
                <i class="fas fa-utensils"></i>
            </div>
            <h1 class="brand-name">QuickBite</h1>
            <p class="tagline">Delivery rápido y delicioso</p>
        </div>

        <div class="coming-soon">
            <h2>Próximamente</h2>
            <p class="description">
                Estamos construyendo algo increíble. Una nueva experiencia de delivery 
                que conectará a los mejores restaurantes con repartidores dedicados 
                para brindarte el servicio que mereces.
            </p>
        </div>

        <div class="buttons">
            <a href="registro-usuario-negocio.php" class="btn btn-primary" >
                <i class="fas fa-store"></i>
                Registrar Negocio
            </a>
            <a href="registro-repartidor.php" class="btn" >
                <i class="fas fa-motorcycle"></i>
                Ser Repartidor
            </a>
        </div>
    </div>

    <footer class="footer">
        <p>&copy; 2025 QuickBite. Todos los derechos reservados.</p>
        <p class="developed-by">
            Desarrollado por <a href="alora.html" target="_blank">alora</a>
        </p>
    </footer>

    <!-- jQuery y Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Hide loading after page loads
        window.addEventListener('load', function() {
            setTimeout(() => {
                document.getElementById('loading').classList.add('hidden');
            }, 800);
        });

        // Smooth scroll and interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Add subtle parallax effect to decorations
            document.addEventListener('mousemove', function(e) {
                const decorations = document.querySelectorAll('.decoration');
                const mouseX = e.clientX / window.innerWidth;
                const mouseY = e.clientY / window.innerHeight;

                decorations.forEach((decoration, index) => {
                    const speed = (index + 1) * 0.5;
                    const x = (mouseX - 0.5) * speed * 10;
                    const y = (mouseY - 0.5) * speed * 10;
                    
                    decoration.style.transform = `translate(${x}px, ${y}px)`;
                });
            });

            // Add click effect to buttons
            document.querySelectorAll('.btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    // Create ripple effect
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.cssText = `
                        position: absolute;
                        width: ${size}px;
                        height: ${size}px;
                        left: ${x}px;
                        top: ${y}px;
                        background: rgba(1, 101, 255, 0.2);
                        border-radius: 50%;
                        transform: scale(0);
                        animation: ripple 0.6s ease-out;
                        pointer-events: none;
                    `;
                    
                    this.style.position = 'relative';
                    this.style.overflow = 'hidden';
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });
        });

        // Add ripple animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(2);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>