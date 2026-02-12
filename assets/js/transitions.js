/* ===================================== */
/* QUICKBITE TRANSITIONS - transitions.js */
/* Sistema minimalista de transiciones */
/* ===================================== */

(function() {
    'use strict';
    
    // Configuración por defecto
    const config = {
        duration: 400,
        delay: 150,
        autoHide: 2000,
        triggerClass: 'qb-transition',
        overlayId: 'qbTransitionOverlay',
        contentClass: 'qb-page-content'
    };

    // Mensajes personalizados por página
    const pageMessages = {
        'carrito.php': 'Cargando carrito...',
        'checkout.php': 'Procesando pedido...',
        'negocio.php': 'Cargando menú...',
        'perfil.php': 'Cargando perfil...',
        'pedidos.php': 'Consultando pedidos...',
        'default': 'Cargando...'
    };

    // Crear el overlay si no existe
    function createOverlay() {
        if (document.getElementById(config.overlayId)) return;

        const overlay = document.createElement('div');
        overlay.id = config.overlayId;
        overlay.className = 'qb-transition-overlay';
        overlay.innerHTML = `
            <div class="qb-transition-content">
                <div class="qb-logo">
                    <span class="quick">Quick</span><span class="bite">Bite</span>
                </div>
                <div class="qb-spinner"></div>
                <div class="qb-text">Cargando...</div>
            </div>
        `;
        document.body.appendChild(overlay);
    }

    // Obtener mensaje personalizado según la página de destino
    function getCustomMessage(url) {
        if (!url) return pageMessages.default;
        
        const page = url.split('/').pop().split('?')[0];
        return pageMessages[page] || pageMessages.default;
    }

    // Mostrar transición
    function showTransition(customMessage = null) {
        createOverlay();
        
        const overlay = document.getElementById(config.overlayId);
        const content = document.querySelector(`.${config.contentClass}`);
        const textElement = overlay.querySelector('.qb-text');
        
        // Actualizar mensaje si se proporciona
        if (customMessage) {
            textElement.textContent = customMessage;
        }
        
        // Fade out del contenido actual
        if (content) {
            content.classList.add('fade-out');
        }
        
        // Mostrar overlay
        requestAnimationFrame(() => {
            overlay.classList.add('active');
        });
    }

    // Ocultar transición
    function hideTransition() {
        const overlay = document.getElementById(config.overlayId);
        const content = document.querySelector(`.${config.contentClass}`);
        
        if (overlay) {
            overlay.classList.remove('active');
        }
        
        if (content) {
            content.classList.remove('fade-out');
            content.classList.add('fade-in');
        }
    }

    // Navegar con transición
    function navigateWithTransition(url, customMessage = null) {
        const message = customMessage || getCustomMessage(url);
        showTransition(message);
        
        setTimeout(() => {
            window.location.href = url;
        }, config.delay);
    }

    // Inicializar eventos automáticos
    function initAutoEvents() {
        // Enlaces con clase específica
        document.addEventListener('click', function(e) {
            const target = e.target.closest(`.${config.triggerClass}`);
            if (!target) return;
            
            e.preventDefault();
            const url = target.href || target.dataset.href;
            const customMessage = target.dataset.message;
            
            if (url) {
                navigateWithTransition(url, customMessage);
            }
        });

        // Formularios con clase específica
        document.addEventListener('submit', function(e) {
            const form = e.target;
            if (!form.classList.contains(config.triggerClass)) return;
            
            showTransition('Procesando...');
        });
    }

    // Auto-ocultar al cargar la página
    function initPageLoad() {
        window.addEventListener('load', function() {
            setTimeout(hideTransition, 100);
        });
        
        // Fallback para DOMContentLoaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(hideTransition, 100);
            });
        } else {
            setTimeout(hideTransition, 100);
        }
    }

    // API pública
    window.QBTransitions = {
        show: showTransition,
        hide: hideTransition,
        navigate: navigateWithTransition,
        config: config
    };

    // Auto-inicializar
    initAutoEvents();
    initPageLoad();

})();