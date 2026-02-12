// Service Worker Simple para QuickBite
const CACHE_NAME = 'quickbite-simple-v1.0.0';

// Lista mínima de recursos para cachear
const CACHE_FILES = [
  '/offline.html'
];

// Instalación
self.addEventListener('install', event => {
  console.log('Service Worker: Instalando versión simple...');
  
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Service Worker: Cacheando offline.html');
        return cache.addAll(CACHE_FILES);
      })
      .then(() => {
        console.log('Service Worker: Instalación completada');
        return self.skipWaiting();
      })
      .catch(error => {
        console.error('Service Worker: Error en instalación:', error);
      })
  );
});

// Activación
self.addEventListener('activate', event => {
  console.log('Service Worker: Activando...');
  
  event.waitUntil(
    caches.keys()
      .then(cacheNames => {
        return Promise.all(
          cacheNames.map(cacheName => {
            if (cacheName !== CACHE_NAME) {
              console.log('Service Worker: Limpiando cache viejo:', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      })
      .then(() => {
        console.log('Service Worker: Activación completada');
        return self.clients.claim();
      })
  );
});

// Fetch - Solo maneja offline
self.addEventListener('fetch', event => {
  // Solo manejar requests GET del mismo origen
  if (event.request.method !== 'GET' || !event.request.url.startsWith(self.location.origin)) {
    return;
  }

  // No interceptar APIs, admin, webhooks
  const url = new URL(event.request.url);
  if (url.pathname.includes('/api/') || 
      url.pathname.includes('/admin/') || 
      url.pathname.includes('/webhook/')) {
    return;
  }

  // Solo mostrar página offline si está offline y es una navegación
  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request).catch(() => {
        return caches.match('/offline.html');
      })
    );
  }
});

console.log('Service Worker Simple cargado');