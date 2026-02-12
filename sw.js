// QuickBite Service Worker
// Versión del cache - cambiar este valor cuando actualices la app
const CACHE_NAME = 'quickbite-v1.0.0';
const OFFLINE_URL = '/offline.html';

// Recursos que queremos cachear inmediatamente al instalar
const STATIC_CACHE_FILES = [
  '/',
  '/index.php',
  '/restaurants.php',
  '/carrito.php',
  '/pedidos.php',
  '/login.php',
  '/register.php',
  '/offline.html',
  // CSS
  '/assets/css/soft-ui.css',
  '/assets/css/transitions.css',
  // JavaScript
  '/assets/js/pwa.js',
  '/assets/js/transitions.js',
  '/assets/js/hero-slider.js',
  '/assets/js/navbar.js',
  // Bootstrap y librerías externas (solo las esenciales)
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'
];

// Recursos que se cachearán dinámicamente
const DYNAMIC_CACHE_FILES = [
  '/negocio.php',
  '/categoria.php',
  '/perfil.php',
  '/checkout.php'
];

// URLs que nunca se deben cachear
const NEVER_CACHE = [
  '/api/',
  '/admin/',
  '/webhook/',
  '/logout.php',
  '/process_payment.php',
  'chrome-extension://'
];

// ============================================
// INSTALACIÓN DEL SERVICE WORKER
// ============================================
self.addEventListener('install', event => {
  console.log('🚀 QuickBite Service Worker: Instalando...');
  
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('📦 Cacheando recursos estáticos...');
        // Cachear recursos uno por uno para evitar fallos por un solo archivo
        const cachePromises = STATIC_CACHE_FILES.map(url => {
          return cache.add(url).catch(error => {
            console.warn('⚠️ No se pudo cachear:', url, error);
            // No fallar por un recurso individual
            return Promise.resolve();
          });
        });
        return Promise.all(cachePromises);
      })
      .then(() => {
        console.log('✅ Service Worker instalado correctamente');
        // Forzar activación inmediata
        return self.skipWaiting();
      })
      .catch(error => {
        console.error('❌ Error instalando Service Worker:', error);
      })
  );
});

// ============================================
// ACTIVACIÓN DEL SERVICE WORKER
// ============================================
self.addEventListener('activate', event => {
  console.log('🔄 QuickBite Service Worker: Activando...');
  
  event.waitUntil(
    caches.keys()
      .then(cacheNames => {
        // Eliminar caches viejos
        return Promise.all(
          cacheNames.map(cacheName => {
            if (cacheName !== CACHE_NAME) {
              console.log('🗑️ Eliminando cache viejo:', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      })
      .then(() => {
        console.log('✅ Service Worker activado');
        // Tomar control inmediato de todas las páginas
        return self.clients.claim();
      })
  );
});

// ============================================
// INTERCEPTAR REQUESTS (ESTRATEGIA DE CACHE)
// ============================================
self.addEventListener('fetch', event => {
  // Ignorar requests que no debemos cachear
  if (shouldNotCache(event.request.url)) {
    return;
  }

  // Solo manejar requests GET
  if (event.request.method !== 'GET') {
    return;
  }

  // Solo interceptar requests del mismo origen
  if (event.request.url.startsWith(self.location.origin)) {
    event.respondWith(
      handleRequest(event.request).catch(error => {
        console.error('Error en handleRequest:', error);
        // Si hay error, hacer fetch normal sin cache
        return fetch(event.request);
      })
    );
  }
});

// ============================================
// FUNCIONES AUXILIARES
// ============================================

function shouldNotCache(url) {
  return NEVER_CACHE.some(pattern => url.includes(pattern));
}

async function handleRequest(request) {
  const url = new URL(request.url);
  
  try {
    // 1. Estrategia Cache First para recursos estáticos
    if (isStaticResource(url)) {
      return await cacheFirst(request);
    }
    
    // 2. Estrategia Network First para páginas dinámicas
    if (isDynamicPage(url)) {
      return await networkFirst(request);
    }
    
    // 3. Estrategia Network First por defecto
    return await networkFirst(request);
    
  } catch (error) {
    console.error('Error manejando request:', error);
    
    // Si es una página HTML y estamos offline, mostrar página offline
    if (request.destination === 'document') {
      return caches.match(OFFLINE_URL);
    }
    
    // Para otros recursos, intentar desde cache
    return caches.match(request);
  }
}

function isStaticResource(url) {
  return url.pathname.match(/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2)$/);
}

function isDynamicPage(url) {
  return url.pathname.endsWith('.php') || url.pathname === '/';
}

// Cache First: Buscar en cache primero, si no existe ir a red
async function cacheFirst(request) {
  const cached = await caches.match(request);
  if (cached) {
    return cached;
  }
  
  const response = await fetch(request);
  const cache = await caches.open(CACHE_NAME);
  cache.put(request, response.clone());
  return response;
}

// Network First: Intentar red primero, si falla usar cache
async function networkFirst(request) {
  try {
    const response = await fetch(request);
    
    // Solo cachear respuestas exitosas
    if (response.status === 200) {
      const cache = await caches.open(CACHE_NAME);
      cache.put(request, response.clone());
    }
    
    return response;
  } catch (error) {
    // Si la red falla, buscar en cache
    const cached = await caches.match(request);
    if (cached) {
      return cached;
    }
    throw error;
  }
}

// ============================================
// NOTIFICACIONES PUSH
// ============================================
self.addEventListener('push', event => {
  console.log('📬 Notificación push recibida:', event);
  
  if (!event.data) {
    return;
  }
  
  const data = event.data.json();
  const options = {
    body: data.body || 'Tienes una nueva actualización de QuickBite',
    icon: '/assets/icons/icon-192x192.png',
    badge: '/assets/icons/icon-72x72.png',
    image: data.image,
    data: {
      url: data.url || '/',
      action: data.action || 'open'
    },
    actions: [
      {
        action: 'open',
        title: 'Abrir',
        icon: '/assets/icons/icon-72x72.png'
      },
      {
        action: 'close',
        title: 'Cerrar'
      }
    ],
    tag: data.tag || 'quickbite-notification',
    renotify: true,
    vibrate: [200, 100, 200],
    requireInteraction: data.requireInteraction || false
  };
  
  event.waitUntil(
    self.registration.showNotification(data.title || 'QuickBite', options)
  );
});

// Manejar clicks en notificaciones
self.addEventListener('notificationclick', event => {
  console.log('🔔 Click en notificación:', event);
  
  event.notification.close();
  
  if (event.action === 'close') {
    return;
  }
  
  // Obtener URL desde los datos de la notificación
  const urlToOpen = event.notification.data?.url || '/';
  
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then(clientList => {
        // Buscar si ya hay una ventana abierta con la URL
        for (const client of clientList) {
          if (client.url === urlToOpen && 'focus' in client) {
            return client.focus();
          }
        }
        
        // Si no hay ventana abierta, abrir una nueva
        if (clients.openWindow) {
          return clients.openWindow(urlToOpen);
        }
      })
  );
});

// ============================================
// BACKGROUND SYNC (para pedidos offline)
// ============================================
self.addEventListener('sync', event => {
  console.log('🔄 Background sync:', event.tag);
  
  if (event.tag === 'pedido-offline') {
    event.waitUntil(
      syncOfflineOrders()
    );
  }
});

async function syncOfflineOrders() {
  try {
    // Aquí implementarías la lógica para sincronizar pedidos guardados offline
    console.log('🔄 Sincronizando pedidos offline...');
    
    // Por ahora solo un log, pero aquí irían las llamadas a tu API
    // para enviar los pedidos que se guardaron mientras estaba offline
    
  } catch (error) {
    console.error('❌ Error sincronizando pedidos offline:', error);
  }
}

// ============================================
// MENSAJES DESDE LA APLICACIÓN
// ============================================
self.addEventListener('message', event => {
  console.log('📨 Mensaje recibido en Service Worker:', event.data);
  
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
  
  if (event.data && event.data.type === 'GET_VERSION') {
    event.ports[0].postMessage({ version: CACHE_NAME });
  }
});

console.log('🎉 QuickBite Service Worker cargado correctamente!');