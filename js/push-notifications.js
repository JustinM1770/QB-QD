class PushNotificationManager {
    constructor() {
        this.swRegistration = null;
        this.isSubscribed = false;
        this.applicationServerKey = 'BOeqLR7r_-fyCTb5S8G3I-AmcRz58mueyf9ncPQ2Pm12dO_7bu1-2YBnU3iLrRS7fhw1N1bin7lNAmQSxpDx6Iw';
    }

    async init() {
        if ('serviceWorker' in navigator && 'PushManager' in window) {
            try {
                this.swRegistration = await navigator.serviceWorker.register('/sw.js');
                console.log('Service Worker registrado correctamente');
                await this.checkSubscription();
            } catch (error) {
                console.error('Error registrando Service Worker:', error);
            }
        } else {
            console.log('Push messaging no es compatible con este navegador');
        }
    }

    async checkSubscription() {
        try {
            const subscription = await this.swRegistration.pushManager.getSubscription();
            this.isSubscribed = subscription !== null;
            
            if (subscription && window.config?.userId) {
                await this.updateSubscriptionOnServer(subscription);
            }
        } catch (error) {
            console.error('Error verificando suscripción:', error);
        }
    }

    async requestPermission() {
        if (Notification.permission === 'granted') {
            await this.subscribeUser();
            return true;
        }
        
        const permission = await Notification.requestPermission();
        if (permission === 'granted') {
            await this.subscribeUser();
            return true;
        }
        
        console.log('Permiso de notificaciones denegado');
        return false;
    }

    async subscribeUser() {
        try {
            const subscription = await this.swRegistration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(this.applicationServerKey)
            });

            console.log('Usuario suscrito a notificaciones push');
            await this.updateSubscriptionOnServer(subscription);
            this.isSubscribed = true;
            
            return subscription;
        } catch (error) {
            console.error('Error suscribiendo usuario:', error);
            return null;
        }
    }

    async updateSubscriptionOnServer(subscription) {
        if (!subscription || !window.config?.userId) {
            return false;
        }

        try {
            const response = await fetch('/api/save-subscription.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    subscription: subscription,
                    user_id: window.config.userId
                })
            });

            const result = await response.json();
            console.log('Suscripción guardada en servidor:', result.success);
            return result.success;
        } catch (error) {
            console.error('Error guardando suscripción:', error);
            return false;
        }
    }

    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }
}

// Inicializar automáticamente cuando se carga el DOM
document.addEventListener('DOMContentLoaded', function() {
    window.pushManager = new PushNotificationManager();
    window.pushManager.init();
});