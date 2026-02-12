/**
 * QuickBite PWA JavaScript
 * Funcionalidades adicionales para la Progressive Web App
 */

class QuickBitePWA {
    constructor() {
        this.init();
    }

    init() {
        this.setupNetworkStatusMonitoring();
        this.setupPushNotifications();
        this.setupOfflineHandler();
        this.setupInstallPrompt();
    }

    // ============================================
    // MONITOREO DE CONEXIÓN
    // ============================================
    setupNetworkStatusMonitoring() {
        const updateNetworkStatus = () => {
            const isOnline = navigator.onLine;
            const statusElement = this.getOrCreateNetworkStatus();
            
            if (isOnline) {
                statusElement.style.background = 'rgba(16, 185, 129, 0.9)';
                statusElement.innerHTML = '📡 Conectado';
                statusElement.style.display = 'none';
            } else {
                statusElement.style.background = 'rgba(239, 68, 68, 0.9)';
                statusElement.innerHTML = '📡 Sin conexión';
                statusElement.style.display = 'block';
            }
        };

        window.addEventListener('online', updateNetworkStatus);
        window.addEventListener('offline', updateNetworkStatus);
        updateNetworkStatus();
    }

    getOrCreateNetworkStatus() {
        let statusElement = document.getElementById('network-status');
        if (!statusElement) {
            statusElement = document.createElement('div');
            statusElement.id = 'network-status';
            statusElement.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: rgba(239, 68, 68, 0.9);
                color: white;
                padding: 8px 16px;
                border-radius: 20px;
                font-size: 14px;
                font-weight: 500;
                z-index: 10001;
                display: none;
                font-family: Inter, sans-serif;
            `;
            document.body.appendChild(statusElement);
        }
        return statusElement;
    }

    // ============================================
    // NOTIFICACIONES PUSH
    // ============================================
    async setupPushNotifications() {
        if (!('Notification' in window) || !('serviceWorker' in navigator)) {
            console.log('Las notificaciones push no están soportadas');
            return;
        }

        // Verificar si ya tenemos permisos
        if (Notification.permission === 'granted') {
            console.log('✅ Permisos de notificación ya concedidos');
            this.subscribeUserToPush();
        } else if (Notification.permission !== 'denied') {
            // Mostrar prompt para solicitar permisos después de interacción del usuario
            this.showNotificationPermissionPrompt();
        }
    }

    showNotificationPermissionPrompt() {
        // Solo mostrar si el usuario está logueado
        const isLoggedIn = document.body.dataset.userLoggedIn === 'true';
        
        if (!isLoggedIn || localStorage.getItem('notificationPromptShown')) {
            return;
        }

        const promptElement = document.createElement('div');
        promptElement.innerHTML = `
            <div style="position: fixed; bottom: 20px; left: 20px; right: 20px; background: white; padding: 20px; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.15); z-index: 10000; font-family: Inter, sans-serif; border: 1px solid #e5e5e5;">
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                    <div style="background: #0165FF; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                        🔔
                    </div>
                    <div>
                        <h6 style="margin: 0; color: #171717; font-weight: 600;">Mantente al día</h6>
                        <p style="margin: 0; color: #737373; font-size: 14px;">Recibe notificaciones sobre tus pedidos</p>
                    </div>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button onclick="quickbitePWA.requestNotificationPermission()" style="flex: 1; background: #0165FF; color: white; border: none; padding: 10px; border-radius: 6px; font-weight: 500; cursor: pointer;">
                        Permitir
                    </button>
                    <button onclick="this.parentElement.parentElement.parentElement.remove(); localStorage.setItem('notificationPromptShown', 'true');" style="flex: 1; background: #f5f5f5; color: #525252; border: none; padding: 10px; border-radius: 6px; font-weight: 500; cursor: pointer;">
                        Ahora no
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(promptElement);
        
        // Auto-ocultar después de 15 segundos
        setTimeout(() => {
            if (promptElement.parentElement) {
                promptElement.remove();
                localStorage.setItem('notificationPromptShown', 'true');
            }
        }, 15000);
    }

    async requestNotificationPermission() {
        try {
            const permission = await Notification.requestPermission();
            
            if (permission === 'granted') {
                console.log('✅ Permisos de notificación concedidos');
                this.subscribeUserToPush();
                this.showNotificationSuccess();
            } else {
                console.log('❌ Permisos de notificación denegados');
            }
            
            // Ocultar el prompt
            const prompt = document.querySelector('[style*="position: fixed"][style*="bottom: 20px"]');
            if (prompt) prompt.remove();
            localStorage.setItem('notificationPromptShown', 'true');
            
        } catch (error) {
            console.error('Error solicitando permisos de notificación:', error);
        }
    }

    showNotificationSuccess() {
        const successElement = document.createElement('div');
        successElement.innerHTML = `
            <div style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); background: #10b981; color: white; padding: 12px 20px; border-radius: 8px; z-index: 10002; font-family: Inter, sans-serif; font-weight: 500;">
                ✅ ¡Notificaciones activadas! Te avisaremos sobre tus pedidos.
            </div>
        `;
        document.body.appendChild(successElement);
        
        setTimeout(() => {
            successElement.remove();
        }, 4000);
    }

    async subscribeUserToPush() {
        try {
            const registration = await navigator.serviceWorker.ready;
            
            // Verificar si ya existe una suscripción
            const existingSubscription = await registration.pushManager.getSubscription();
            if (existingSubscription) {
                console.log('✅ Usuario ya suscrito a notificaciones push');
                return;
            }

            // Crear nueva suscripción
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array('BEl62iUYgUivxIkv69yViEuiBIa6wvIKfFWqWHjWj9Dv0Q5yD5Z9v5EiXeDvnAO5D-WY5z5t5jNAm5sCfnvl2q4') // Tu clave VAPID pública
            });

            console.log('✅ Usuario suscrito a notificaciones push');
            
            // Enviar suscripción al servidor
            this.sendSubscriptionToServer(subscription);
            
        } catch (error) {
            console.error('Error suscribiendo a notificaciones push:', error);
        }
    }

    sendSubscriptionToServer(subscription) {
        // Aquí enviarías la suscripción a tu servidor para guardarla
        fetch('/api/push-subscription.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(subscription)
        }).catch(error => {
            console.error('Error enviando suscripción al servidor:', error);
        });
    }

    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    // ============================================
    // MANEJO OFFLINE
    // ============================================
    setupOfflineHandler() {
        // Interceptar formularios para manejarlos offline
        document.addEventListener('submit', (e) => {
            if (!navigator.onLine) {
                e.preventDefault();
                this.handleOfflineForm(e.target);
            }
        });
    }

    handleOfflineForm(form) {
        // Guardar datos del formulario para sincronizar después
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        // Guardar en localStorage para sincronizar cuando vuelva la conexión
        const offlineData = JSON.parse(localStorage.getItem('offlineFormData') || '[]');
        offlineData.push({
            url: form.action,
            method: form.method,
            data: data,
            timestamp: Date.now()
        });
        localStorage.setItem('offlineFormData', JSON.stringify(offlineData));
        
        this.showOfflineMessage();
    }

    showOfflineMessage() {
        const messageElement = document.createElement('div');
        messageElement.innerHTML = `
            <div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #f59e0b; color: white; padding: 20px; border-radius: 12px; z-index: 10003; font-family: Inter, sans-serif; text-align: center; max-width: 300px;">
                <div style="font-size: 2rem; margin-bottom: 10px;">📱</div>
                <h6 style="margin: 0 0 8px 0; font-weight: 600;">Sin conexión</h6>
                <p style="margin: 0; font-size: 14px; opacity: 0.9;">Tus datos se guardarán y se enviarán cuando tengas conexión.</p>
                <button onclick="this.parentElement.parentElement.remove()" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white; padding: 8px 16px; margin-top: 12px; border-radius: 6px; cursor: pointer;">
                    Entendido
                </button>
            </div>
        `;
        document.body.appendChild(messageElement);
        
        setTimeout(() => {
            if (messageElement.parentElement) {
                messageElement.remove();
            }
        }, 5000);
    }

    // ============================================
    // INSTALACIÓN PWA
    // ============================================
    setupInstallPrompt() {
        // El manejo del prompt de instalación ya está en el HTML
        // Aquí podríamos agregar funcionalidad adicional si es necesario
    }

    // ============================================
    // MÉTODOS PÚBLICOS
    // ============================================
    
    // Enviar notificación de prueba
    sendTestNotification() {
        if ('serviceWorker' in navigator && 'Notification' in window && Notification.permission === 'granted') {
            navigator.serviceWorker.ready.then(registration => {
                registration.showNotification('¡Prueba de QuickBite!', {
                    body: 'Las notificaciones funcionan correctamente 🎉',
                    icon: '/assets/icons/icon-192x192.png',
                    badge: '/assets/icons/icon-72x72.png',
                    tag: 'test-notification'
                });
            });
        }
    }

    // Limpiar datos offline
    clearOfflineData() {
        localStorage.removeItem('offlineFormData');
        console.log('🗑️ Datos offline limpiados');
    }

    // Obtener estado de la PWA
    getPWAStatus() {
        return {
            isServiceWorkerSupported: 'serviceWorker' in navigator,
            isNotificationSupported: 'Notification' in window,
            notificationPermission: Notification.permission,
            isOnline: navigator.onLine,
            isPWAInstalled: window.matchMedia('(display-mode: standalone)').matches
        };
    }
}

// Inicializar PWA cuando el DOM esté listo
let quickbitePWA;
document.addEventListener('DOMContentLoaded', function() {
    quickbitePWA = new QuickBitePWA();
});

// Exponer funciones globalmente si es necesario
window.quickbitePWA = quickbitePWA;