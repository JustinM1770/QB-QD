/**
 * QuickBite - Sistema de Tema Global (Modo Oscuro)
 * 
 * Este script maneja la persistencia del tema oscuro/claro en todas las páginas.
 * Usa localStorage para recordar la preferencia del usuario.
 * 
 * Opciones de tema:
 * - 'light': Modo claro forzado
 * - 'dark': Modo oscuro forzado  
 * - 'auto': Sigue la preferencia del sistema (default)
 */

(function() {
    'use strict';

    const THEME_KEY = 'quickbite-theme';
    
    /**
     * Obtener el tema guardado en localStorage
     * @returns {string} 'light', 'dark', o 'auto'
     */
    function getSavedTheme() {
        return localStorage.getItem(THEME_KEY) || 'auto';
    }

    /**
     * Guardar el tema en localStorage
     * @param {string} theme - 'light', 'dark', o 'auto'
     */
    function saveTheme(theme) {
        localStorage.setItem(THEME_KEY, theme);
    }

    /**
     * Detectar si el sistema prefiere modo oscuro
     * @returns {boolean}
     */
    function systemPrefersDark() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    /**
     * Determinar si debe aplicarse modo oscuro
     * @returns {boolean}
     */
    function shouldBeDark() {
        const saved = getSavedTheme();
        if (saved === 'dark') return true;
        if (saved === 'light') return false;
        // 'auto' - seguir preferencia del sistema
        return systemPrefersDark();
    }

    /**
     * Aplicar el tema al documento
     * Añade/remueve clases y atributos necesarios para compatibilidad
     */
    function applyTheme() {
        const isDark = shouldBeDark();
        const html = document.documentElement;
        const body = document.body;

        if (isDark) {
            // Aplicar modo oscuro
            html.setAttribute('data-theme', 'dark');
            html.classList.add('dark-mode');
            body?.classList.add('dark-mode');
            
            // Actualizar meta theme-color para la barra del navegador
            updateThemeColor('#000000');
        } else {
            // Aplicar modo claro
            html.setAttribute('data-theme', 'light');
            html.classList.remove('dark-mode');
            body?.classList.remove('dark-mode');
            
            updateThemeColor('#ffffff');
        }

        // Disparar evento personalizado para que otros scripts puedan reaccionar
        window.dispatchEvent(new CustomEvent('themechange', { 
            detail: { theme: isDark ? 'dark' : 'light' } 
        }));
    }

    /**
     * Actualizar el meta tag theme-color
     * @param {string} color - Color hexadecimal
     */
    function updateThemeColor(color) {
        let metaTheme = document.querySelector('meta[name="theme-color"]:not([media])');
        if (!metaTheme) {
            metaTheme = document.createElement('meta');
            metaTheme.name = 'theme-color';
            document.head.appendChild(metaTheme);
        }
        metaTheme.content = color;
    }

    /**
     * Alternar entre modo claro y oscuro
     * @returns {string} El nuevo tema aplicado
     */
    function toggleTheme() {
        const current = getSavedTheme();
        let newTheme;
        
        if (current === 'auto') {
            // Si está en auto, cambiar al opuesto del sistema
            newTheme = systemPrefersDark() ? 'light' : 'dark';
        } else if (current === 'dark') {
            newTheme = 'light';
        } else {
            newTheme = 'dark';
        }
        
        saveTheme(newTheme);
        applyTheme();
        return newTheme;
    }

    /**
     * Establecer un tema específico
     * @param {string} theme - 'light', 'dark', o 'auto'
     */
    function setTheme(theme) {
        if (['light', 'dark', 'auto'].includes(theme)) {
            saveTheme(theme);
            applyTheme();
        }
    }

    /**
     * Obtener el tema actual efectivo
     * @returns {string} 'light' o 'dark'
     */
    function getCurrentTheme() {
        return shouldBeDark() ? 'dark' : 'light';
    }

    /**
     * Inicializar el manejador de tema
     */
    function init() {
        // Aplicar tema inmediatamente
        applyTheme();

        // Escuchar cambios en la preferencia del sistema
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                // Solo reaccionar si el usuario tiene 'auto' configurado
                if (getSavedTheme() === 'auto') {
                    applyTheme();
                }
            });
        }

        // Cuando el DOM esté listo, re-aplicar para asegurar que body existe
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', applyTheme);
        }
    }

    // =============================================
    // APLICACIÓN INMEDIATA (antes de que cargue el DOM)
    // Esto previene el "flash" blanco
    // =============================================
    
    // Aplicar al <html> inmediatamente
    (function() {
        const isDark = shouldBeDark();
        const html = document.documentElement;
        
        if (isDark) {
            html.setAttribute('data-theme', 'dark');
            html.classList.add('dark-mode');
        } else {
            html.setAttribute('data-theme', 'light');
            html.classList.remove('dark-mode');
        }
    })();

    // Inicializar cuando el script cargue
    init();

    // =============================================
    // API PÚBLICA
    // =============================================
    window.QuickBiteTheme = {
        toggle: toggleTheme,
        set: setTheme,
        get: getCurrentTheme,
        getSaved: getSavedTheme,
        isDark: shouldBeDark
    };

})();
