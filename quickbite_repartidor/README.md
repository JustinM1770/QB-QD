# 🛵 QuickBite Repartidor - App Flutter

App móvil para repartidores de QuickBite con Google Maps, GPS y tracking en tiempo real.

---

## ✨ Funcionalidades

- ✅ **Login y autenticación** de repartidores
- ✅ **Dashboard** con estadísticas del día
- ✅ **Estado Online/Offline** para recibir pedidos
- ✅ **Ver pedidos disponibles** en tiempo real
- ✅ **Aceptar pedidos** con un tap
- ✅ **Google Maps integrado** con ruta al cliente
- ✅ **Navegación GPS** con Google Maps externa
- ✅ **Llamar al cliente** directo desde la app
- ✅ **Actualizar estado** del pedido (En camino → Entregado)
- ✅ **Historial** de entregas completadas

---

## 📱 Pantallas

1. **LoginScreen** - Login de repartidores
2. **DashboardScreen** - Dashboard con botón online/offline
3. **PedidosDisponiblesScreen** - Lista de pedidos para aceptar
4. **PedidoActivoScreen** - Mapa con ruta y botones de acción

---

## 🚀 Instalación y Configuración

### 1. Requisitos Previos

- Flutter instalado ([Guía de instalación](../INSTALACION_FLUTTER.md))
- Android Studio o VS Code
- Cuenta de Google Cloud Platform (para Google Maps API)

### 2. Clonar y Configurar

```bash
# Clonar repositorio
cd quickbite

# Si ya existe la carpeta, eliminarla
rm -rf quickbite_repartidor

# Crear proyecto Flutter
flutter create quickbite_repartidor --org com.quickbite

# Copiar archivos generados
# (Los archivos ya están en quickbite_repartidor/)

# Instalar dependencias
cd quickbite_repartidor
flutter pub get
```

### 3. Configurar Google Maps API Key

#### **Obtener API Key:**

1. Ve a [Google Cloud Console](https://console.cloud.google.com/)
2. Crea un proyecto o selecciona uno existente
3. Habilita estas APIs:
   - Maps SDK for Android
   - Maps SDK for iOS (si vas a usar iOS)
   - Directions API (para rutas)
4. Ve a "Credenciales" → "Crear credenciales" → "Clave de API"
5. Copia tu API Key

#### **Configurar para Android:**

Edita `android/app/src/main/AndroidManifest.xml`:

```xml
<meta-data
    android:name="com.google.android.geo.API_KEY"
    android:value="TU_API_KEY_AQUI"/>
```

#### **Configurar para iOS (opcional):**

Edita `ios/Runner/AppDelegate.swift`:

```swift
import GoogleMaps

GMSServices.provideAPIKey("TU_API_KEY_AQUI")
```

### 4. Configurar URL de API

Edita `lib/config/api_config.dart`:

```dart
static const String baseUrl = 'http://10.0.2.2:8000/api'; // Emulador Android
// static const String baseUrl = 'http://localhost:8000/api'; // iOS
// static const String baseUrl = 'http://192.168.1.X:8000/api'; // Dispositivo real
```

---

## 🏃 Correr la App

### Levantar el Backend

```bash
# En la raíz del proyecto
cd /ruta/a/quickbite
php -S localhost:8000
```

### Correr la App

```bash
cd quickbite_repartidor

# Ver dispositivos disponibles
flutter devices

# Correr en emulador/dispositivo
flutter run

# O especificar dispositivo
flutter run -d <device_id>
```

---

## 🗺️ Configuración de Ubicación

### Permisos en Android

Los permisos ya están configurados en `AndroidManifest.xml`:

```xml
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_BACKGROUND_LOCATION" />
<uses-permission android:name="android.permission.CALL_PHONE" />
```

### Probar en Emulador

En Android Emulator, puedes simular ubicación:

1. Click en los 3 puntos (Extended Controls)
2. Location
3. Ingresar coordenadas manualmente o usar ruta predefinida

---

## 📂 Estructura del Proyecto

```
quickbite_repartidor/
├── lib/
│   ├── config/
│   │   ├── api_config.dart        # URLs de API
│   │   └── theme.dart             # Colores y tema
│   ├── models/
│   │   └── repartidor.dart        # Modelo de repartidor
│   ├── providers/
│   │   ├── auth_provider.dart     # State de autenticación
│   │   └── pedido_provider.dart   # State de pedidos
│   ├── services/
│   │   ├── auth_service.dart      # Llamadas API de auth
│   │   └── pedido_service.dart    # Llamadas API de pedidos
│   ├── screens/
│   │   ├── auth/
│   │   │   └── login_screen.dart  # Pantalla de login
│   │   ├── dashboard/
│   │   │   └── dashboard_screen.dart # Dashboard principal
│   │   └── pedidos/
│   │       ├── pedidos_disponibles_screen.dart
│   │       └── pedido_activo_screen.dart # Con mapa
│   └── main.dart                   # Punto de entrada
├── android/
│   └── app/src/main/AndroidManifest.xml # Permisos y API key
├── pubspec.yaml                    # Dependencias
└── README.md                       # Este archivo
```

---

## 🔧 Dependencias Principales

```yaml
dependencies:
  google_maps_flutter: ^2.5.0  # Google Maps
  geolocator: ^11.0.0           # GPS
  location: ^5.0.3              # Ubicación
  permission_handler: ^11.2.0   # Permisos
  url_launcher: ^6.2.4          # Llamadas y navegación
  provider: ^6.1.1              # State management
  http: ^1.1.0                  # API calls
  shared: ^1.0.0                # Modelos compartidos
```

---

## 🧪 Testing

### Credenciales de Prueba

```
Email: repartidor@test.com
Password: 123456
```

(Estas credenciales deben existir en tu base de datos)

### Flujo de Prueba

1. **Login** con credenciales de repartidor
2. **Activar** estado "En Línea"
3. **Ver pedidos disponibles** en el dashboard
4. **Aceptar un pedido**
5. **Ver mapa** con ubicación del restaurante y cliente
6. **Iniciar entrega** (cambia estado a "En camino")
7. **Abrir Google Maps** para navegación
8. **Llamar al cliente** si es necesario
9. **Marcar como entregado**

---

## 🐛 Solución de Problemas

### Error: "Google Maps API key not found"

- Verifica que agregaste la API key en `AndroidManifest.xml`
- Asegúrate de habilitar Maps SDK for Android en Google Cloud

### Error: "Location permissions denied"

- En dispositivo real: Ve a Ajustes → Apps → QuickBite Repartidor → Permisos
- En emulador: Los permisos se otorgan automáticamente

### Error: "Can't reach API"

- Verifica que el backend PHP esté corriendo (`php -S localhost:8000`)
- Verifica la URL en `api_config.dart`:
  - Emulador Android: `http://10.0.2.2:8000/api`
  - iOS Simulator: `http://localhost:8000/api`
  - Dispositivo real: `http://TU_IP_LOCAL:8000/api`

### Mapa no carga

- Verifica que tengas conexión a internet
- Verifica la API key de Google Maps
- Revisa los logs: `flutter logs`

---

## 📱 Compilar para Producción

### Android APK

```bash
flutter build apk --release
```

El APK estará en: `build/app/outputs/flutter-apk/app-release.apk`

### Android App Bundle (para Play Store)

```bash
flutter build appbundle --release
```

---

## 🎯 Próximos Pasos

Para mejorar la app, considera:

- [ ] Tracking de ubicación en tiempo real
- [ ] Notificaciones push cuando llegue un pedido
- [ ] Chat con el cliente
- [ ] Historial con ganancias detalladas
- [ ] Modo oscuro
- [ ] Soporte multiidioma

---

## 📞 Soporte

Si tienes problemas:

1. Lee la [documentación completa](../README_FLUTTER.md)
2. Verifica los logs: `flutter logs`
3. Revisa el estado de la API: `http://localhost:8000/api/health.php`

---

¡Listo para entregar pedidos! 🚀🛵
