/**
 * QuickBite Theme Manager
 * Gestiona el modo claro/oscuro de la aplicación
 * Detecta preferencias del sistema y permite cambio manual
 */

(function() {
    'use strict';

    const ThemeManager = {
        // Clave para localStorage
        STORAGE_KEY: 'quickbite-theme',
        
        // Temas disponibles
        THEMES: {
            LIGHT: 'light',
            DARK: 'dark',
            AUTO: 'auto'
        },

        /**
         * Inicializar el gestor de temas
         */
        init() {
            // Aplicar tema guardado o detectar del sistema
            this.applyTheme(this.getSavedTheme());
            
            // Escuchar cambios en preferencias del sistema
            this.watchSystemPreference();
            
            // Inicializar toggles si existen
            this.initToggles();
            
            console.log('🎨 ThemeManager inicializado');
        },

        /**
         * Obtener tema guardado o 'auto' por defecto
         */
        getSavedTheme() {
            return localStorage.getItem(this.STORAGE_KEY) || this.THEMES.AUTO;
        },

        /**
         * Guardar preferencia de tema
         */
        saveTheme(theme) {
            localStorage.setItem(this.STORAGE_KEY, theme);
        },

        /**
         * Detectar preferencia del sistema
         */
        getSystemPreference() {
            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                return this.THEMES.DARK;
            }
            return this.THEMES.LIGHT;
        },

        /**
         * Aplicar tema al documento
         */
        applyTheme(theme) {
            const html = document.documentElement;
            const body = document.body;
            
            // Si es auto, usar preferencia del sistema
            const effectiveTheme = theme === this.THEMES.AUTO 
                ? this.getSystemPreference() 
                : theme;
            
            // Aplicar atributo data-theme
            html.setAttribute('data-theme', effectiveTheme);
            
            // También agregar/quitar clase para compatibilidad
            if (effectiveTheme === this.THEMES.DARK) {
                body.classList.add('dark-mode');
                body.classList.remove('light-mode');
            } else {
                body.classList.add('light-mode');
                body.classList.remove('dark-mode');
            }
            
            // Actualizar meta theme-color para la barra del navegador
            this.updateMetaThemeColor(effectiveTheme);
            
            // Actualizar estado de toggles
            this.updateToggles(effectiveTheme);
            
            // Disparar evento personalizado
            window.dispatchEvent(new CustomEvent('themechange', { 
                detail: { theme: effectiveTheme, setting: theme } 
            }));
            
            console.log(`🎨 Tema aplicado: ${effectiveTheme} (configuración: ${theme})`);
        },

        /**
         * Actualizar meta tag de color del tema
         */
        updateMetaThemeColor(theme) {
            let metaThemeColor = document.querySelector('meta[name="theme-color"]');
            
            if (!metaThemeColor) {
                metaThemeColor = document.createElement('meta');
                metaThemeColor.name = 'theme-color';
                document.head.appendChild(metaThemeColor);
            }
            
            metaThemeColor.content = theme === this.THEMES.DARK ? '#0f172a' : '#ffffff';
        },

        /**
         * Cambiar tema
         */
        setTheme(theme) {
            this.saveTheme(theme);
            this.applyTheme(theme);
        },

        /**
         * Alternar entre claro y oscuro
         */
        toggle() {
            const currentTheme = this.getSavedTheme();
            const effectiveTheme = currentTheme === this.THEMES.AUTO 
                ? this.getSystemPreference() 
                : currentTheme;
            
            const newTheme = effectiveTheme === this.THEMES.DARK 
                ? this.THEMES.LIGHT 
                : this.THEMES.DARK;
            
            this.setTheme(newTheme);
            return newTheme;
        },

        /**
         * Obtener tema actual efectivo
         */
        getCurrentTheme() {
            const savedTheme = this.getSavedTheme();
            return savedTheme === this.THEMES.AUTO 
                ? this.getSystemPreference() 
                : savedTheme;
        },

        /**
         * Verificar si está en modo oscuro
         */
        isDarkMode() {
            return this.getCurrentTheme() === this.THEMES.DARK;
        },

        /**
         * Escuchar cambios en preferencias del sistema
         */
        watchSystemPreference() {
            if (window.matchMedia) {
                const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
                
                const handler = (e) => {
                    // Solo aplicar si está en modo auto
                    if (this.getSavedTheme() === this.THEMES.AUTO) {
                        this.applyTheme(this.THEMES.AUTO);
                        console.log('🎨 Sistema cambió a modo:', e.matches ? 'oscuro' : 'claro');
                    }
                };
                
                // Usar addEventListener para navegadores modernos
                if (mediaQuery.addEventListener) {
                    mediaQuery.addEventListener('change', handler);
                } else {
                    // Fallback para navegadores antiguos
                    mediaQuery.addListener(handler);
                }
            }
        },

        /**
         * Inicializar toggles de tema en la página
         */
        initToggles() {
            // Buscar todos los toggles de tema
            document.querySelectorAll('[data-theme-toggle]').forEach(toggle => {
                toggle.addEventListener('click', () => {
                    this.toggle();
                });
            });
            
            // Buscar selectores de tema
            document.querySelectorAll('[data-theme-select]').forEach(select => {
                select.addEventListener('change', (e) => {
                    this.setTheme(e.target.value);
                });
            });
        },

        /**
         * Actualizar estado visual de toggles
         */
        updateToggles(theme) {
            const isDark = theme === this.THEMES.DARK;
            
            // Actualizar switches
            document.querySelectorAll('.theme-toggle-switch').forEach(toggle => {
                toggle.classList.toggle('active', isDark);
            });
            
            // Actualizar iconos
            document.querySelectorAll('.theme-icon-sun').forEach(icon => {
                icon.style.display = isDark ? 'none' : 'inline';
            });
            
            document.querySelectorAll('.theme-icon-moon').forEach(icon => {
                icon.style.display = isDark ? 'inline' : 'none';
            });
            
            // Actualizar selectores
            document.querySelectorAll('[data-theme-select]').forEach(select => {
                select.value = this.getSavedTheme();
            });
        },

        /**
         * Crear HTML para un toggle de tema
         */
        createToggleHTML(options = {}) {
            const {
                showLabel = true,
                size = 'normal' // 'small', 'normal', 'large'
            } = options;
            
            const sizeClass = size !== 'normal' ? `theme-toggle--${size}` : '';
            
            return `
                <div class="theme-toggle ${sizeClass}" data-theme-toggle>
                    ${showLabel ? '<span class="theme-toggle-label">Modo oscuro</span>' : ''}
                    <span class="theme-icon-sun">☀️</span>
                    <div class="theme-toggle-switch ${this.isDarkMode() ? 'active' : ''}"></div>
                    <span class="theme-icon-moon">🌙</span>
                </div>
            `;
        }
    };

    // Inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => ThemeManager.init());
    } else {
        ThemeManager.init();
    }

    // Exponer globalmente
    window.ThemeManager = ThemeManager;

})();
