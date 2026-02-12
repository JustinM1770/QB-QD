# 📱 QuickBite PWA - Progressive Web App

¡Felicidades! Tu proyecto QuickBite ahora es una **Progressive Web App (PWA)** completa. Los usuarios pueden instalarla como una aplicación nativa y disfrutar de funcionalidades offline, notificaciones push y una experiencia móvil optimizada.

## ✨ Características Implementadas

### 🔧 Funcionalidades PWA
- ✅ **Instalable**: Los usuarios pueden instalar la app desde el navegador
- ✅ **Offline**: Funciona sin conexión a internet
- ✅ **Notificaciones Push**: Recibe notificaciones sobre pedidos y promociones
- ✅ **Responsive**: Optimizada para todos los dispositivos
- ✅ **Fast Loading**: Carga rápida gracias al cache inteligente
- ✅ **Native Feel**: Se ve y se siente como una app nativa

### 📁 Archivos Añadidos

#### Archivos Principales
```
📄 manifest.json          # Configuración de la PWA
📄 sw.js                  # Service Worker (cache y offline)
📄 offline.html           # Página mostrada sin conexión
📄 favicon.ico            # Favicon optimizado
```

#### Recursos PWA
```
📁 assets/js/
   📄 pwa.js              # Funcionalidades PWA en JavaScript

📁 assets/css/
   📄 pwa.css             # Estilos optimizados para PWA

📁 assets/icons/          # Iconos en todos los tamaños necesarios
   📄 icon-72x72.png
   📄 icon-96x96.png
   📄 icon-128x128.png
   📄 icon-144x144.png
   📄 icon-152x152.png
   📄 icon-192x192.png
   📄 icon-384x384.png
   📄 icon-512x512.png
   📄 apple-touch-icon.png
```

#### API de Notificaciones
```
📁 api/
   📄 push-subscription.php    # Registrar suscripciones push
   📄 push-service.php         # Enviar notificaciones
```

#### Scripts de Utilidad
```
📄 generate-pwa-icons.sh      # Generar iconos automáticamente
📄 check-pwa.sh               # Verificar configuración PWA
```

## 🚀 Cómo Usar la PWA

### Para Usuarios (Instalación)

1. **En Chrome/Edge (Móvil y Desktop)**:
   - Visita tu sitio web
   - Busca el ícono "📱 Instalar" en la barra de direcciones
   - O toca el botón "Instalar App" que aparece en la pantalla

2. **En Safari (iOS)**:
   - Abre el sitio en Safari
   - Toca el botón "Compartir" (📤)
   - Selecciona "Agregar a pantalla de inicio"

3. **En Firefox**:
   - Visita el sitio
   - Toca el menú (⋮) → "Instalar"

### Para Desarrolladores

#### Verificar la PWA
```bash
# Ejecutar verificación completa
./check-pwa.sh
```

#### Enviar Notificación de Prueba
```bash
# Probar el sistema de notificaciones
php api/push-service.php
```

#### Regenerar Iconos
```bash
# Si cambias el logo, regenera los iconos
./generate-pwa-icons.sh
```

## 🔧 Configuración Técnica

### Service Worker (sw.js)
- **Cache Strategy**: Network First para páginas dinámicas, Cache First para recursos estáticos
- **Offline Support**: Guarda páginas visitadas para acceso offline
- **Background Sync**: Sincroniza datos cuando vuelve la conexión
- **Push Notifications**: Maneja notificaciones push del servidor

### Manifest (manifest.json)
- **Nombre**: QuickBite - Delivery Rápido y Delicioso
- **Tema**: Azul (#0165FF) siguiendo tu marca
- **Display**: Standalone (pantalla completa)
- **Shortcuts**: Accesos rápidos a Restaurantes, Carrito y Pedidos

### Notificaciones Push
- **API Endpoint**: `/api/push-subscription.php`
- **Base de datos**: Tabla `push_subscriptions`
- **Tipos**: Confirmación de pedido, estado del pedido, promociones

## 📱 Experiencia del Usuario

### Instalación
1. Al visitar el sitio, aparece un prompt para instalar la app
2. Una vez instalada, se abre como aplicación independiente
3. Aparece en el menú de aplicaciones del dispositivo

### Uso Offline
1. Las páginas visitadas se guardan automáticamente
2. Sin conexión, se muestra una página offline personalizada
3. Los formularios se guardan y se envían al reconectar

### Notificaciones
1. Prompt para permitir notificaciones al iniciar sesión
2. Notificaciones automáticas sobre estado de pedidos
3. Notificaciones promocionales (configurables)

## 🛠️ Desarrollo y Personalización

### Modificar Colores y Tema
Edita `manifest.json`:
```json
{
  "theme_color": "#0165FF",
  "background_color": "#FFFFFF"
}
```

### Agregar Nuevas Páginas al Cache
Edita `sw.js` en la sección `STATIC_CACHE_FILES`:
```javascript
const STATIC_CACHE_FILES = [
  '/',
  '/nueva-pagina.php',
  // ... otras páginas
];
```

### Personalizar Notificaciones
Usa la clase `PushNotificationService` en `api/push-service.php`:
```php
$pushService = new PushNotificationService();

// Notificar a un usuario específico
$pushService->sendToUser($userId, $title, $message, $data);

// Notificar a todos los usuarios
$pushService->sendToAll($title, $message, $data);

// Notificación de pedido
$pushService->sendOrderNotification($userId, $orderId, 'confirmado');
```

## 🚨 Producción

### Lista de Verificación

#### Antes de ir a producción:
- [ ] **Configurar HTTPS** (requerido para PWA)
- [ ] **Generar claves VAPID propias** para notificaciones push
- [ ] **Actualizar dominio** en manifest.json
- [ ] **Optimizar imágenes** para mejor rendimiento
- [ ] **Configurar cache headers** en el servidor
- [ ] **Probar en dispositivos reales**

#### Generar Claves VAPID
```bash
# Instalar web-push globally
npm install -g web-push

# Generar claves
web-push generate-vapid-keys

# Actualizar las claves en api/push-service.php
```

#### Configurar HTTPS
Las PWAs requieren HTTPS en producción. Opciones:
- **Let's Encrypt** (gratuito)
- **Cloudflare** (gratuito con proxy)
- **Certificado SSL** de tu hosting

### Monitoreo

#### Métricas Importantes
- **Instalaciones de PWA**: Google Analytics puede trackear esto
- **Uso Offline**: Monitorear en DevTools > Application
- **Notificaciones**: Tasa de entrega y clics
- **Performance**: Core Web Vitals

#### DevTools para Debug
- **Chrome DevTools** > Application > Service Workers
- **Chrome DevTools** > Application > Manifest
- **Chrome DevTools** > Application > Storage (Cache)

## 📊 Estadísticas y Analytics

### Tracking de PWA
Agrega este código a Google Analytics:
```javascript
// Detectar si es PWA instalada
if (window.matchMedia('(display-mode: standalone)').matches) {
  gtag('event', 'pwa_opened', {
    'event_category': 'PWA',
    'event_label': 'App opened in standalone mode'
  });
}

// Tracking de instalación
window.addEventListener('beforeinstallprompt', (e) => {
  gtag('event', 'pwa_install_prompt', {
    'event_category': 'PWA',
    'event_label': 'Install prompt shown'
  });
});
```

## 🆘 Resolución de Problemas

### Problemas Comunes

#### Service Worker no se registra
- Verificar que `sw.js` esté en la raíz del dominio
- Verificar permisos del archivo
- Comprobar errores en DevTools > Console

#### PWA no se puede instalar
- Verificar que `manifest.json` esté vinculado correctamente
- Verificar que todos los iconos existan
- Asegurar que el sitio esté servido por HTTPS (en producción)

#### Notificaciones no funcionan
- Verificar permisos de notificación en el navegador
- Comprobar que las claves VAPID sean correctas
- Verificar configuración de la tabla `push_subscriptions`

### Logs y Debug
```bash
# Ver logs del Service Worker
# Chrome DevTools > Application > Service Workers > Console

# Ver cache del Service Worker
# Chrome DevTools > Application > Storage > Cache Storage

# Verificar base de datos
mysql -u usuario -p nombre_db -e "SELECT COUNT(*) FROM push_subscriptions;"
```

## 📞 Soporte

Si necesitas ayuda con la PWA:

1. **Verificación**: Ejecuta `./check-pwa.sh` primero
2. **Documentación**: Consulta este README
3. **DevTools**: Usa Chrome DevTools para debug
4. **Logs**: Revisa los logs del servidor y navegador

---

## 🎉 ¡Felicidades!

Tu proyecto QuickBite ahora es una PWA completa y moderna. Los usuarios pueden:
- 📱 Instalarla como app nativa
- 🔄 Usarla sin conexión
- 🔔 Recibir notificaciones push
- ⚡ Disfrutar de carga ultra-rápida
- 📱 Tener una experiencia móvil perfecta

**¡La app del futuro está aquí! 🚀**