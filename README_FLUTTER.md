# 🚀 QuickBite - Desarrollo con Flutter

**3 Apps con una sola tecnología** para Android e iOS

---

## ✨ Ventajas de Flutter

✅ **Cross-platform**: Android e iOS con el mismo código
✅ **Hot Reload**: Ve cambios instantáneamente
✅ **Performance**: Compilado a código nativo
✅ **UI Moderna**: Material Design 3 y Cupertino
✅ **Fácil de aprender**: Dart es simple y potente

---

## 📱 Las 3 Apps del Proyecto

| App | Carpeta | Usuario | Funcionalidades |
|-----|---------|---------|-----------------|
| **QuickBite Cliente** | `quickbite_cliente/` | Clientes | Ver negocios, hacer pedidos, seguimiento |
| **QuickBite Negocio** | `quickbite_negocio/` | Negocios | Gestionar pedidos, productos, estadísticas |
| **QuickBite Repartidor** | `quickbite_repartidor/` | Repartidores | Aceptar entregas, GPS, navegación |

---

## 🎯 Inicio Rápido (5 pasos)

### 1️⃣ Instalar Flutter

Sigue la guía: **[INSTALACION_FLUTTER.md](INSTALACION_FLUTTER.md)**

Verifica que funciona:
```bash
flutter doctor
```

### 2️⃣ Clonar el Proyecto

```bash
git clone <URL_DEL_REPO> quickbite
cd quickbite
```

### 3️⃣ Configurar Base de Datos

```bash
# Crear BD
mysql -u root -p -e "CREATE DATABASE app_delivery;"

# Importar datos
mysql -u root -p app_delivery < quickbite_database.sql

# Configurar .env
cp .env.example .env
# Editar .env con tus credenciales
```

### 4️⃣ Crear los 3 Proyectos Flutter

```bash
# App Clientes
flutter create quickbite_cliente --org com.quickbite

# App Negocios
flutter create quickbite_negocio --org com.quickbite

# App Repartidores
flutter create quickbite_repartidor --org com.quickbite

# Paquete compartido
flutter create --template=package shared
```

### 5️⃣ Copiar Código Base

```bash
# Copiar modelos compartidos (ya están creados)
# Los modelos ya están en shared/lib/models/

# Agregar dependencias
cd quickbite_cliente
flutter pub get

cd ../quickbite_negocio
flutter pub get

cd ../quickbite_repartidor
flutter pub get
```

---

## 📂 Estructura del Proyecto

```
quickbite/
├── api/                          # Backend PHP existente ✅
├── config/                       # Configuración
├── shared/                       # 📦 Código compartido
│   ├── lib/
│   │   ├── models/              # Usuario, Negocio, Producto, Pedido
│   │   ├── services/            # (crear después)
│   │   └── utils/               # (crear después)
│   └── pubspec.yaml
├── quickbite_cliente/           # 📱 App Clientes
│   ├── lib/
│   │   ├── main.dart
│   │   ├── screens/             # Pantallas
│   │   ├── providers/           # State management
│   │   ├── services/            # API calls
│   │   └── widgets/             # Componentes UI
│   └── pubspec.yaml
├── quickbite_negocio/           # 🏪 App Negocios
│   └── lib/
├── quickbite_repartidor/        # 🛵 App Repartidores
│   └── lib/
└── README_FLUTTER.md            # 📖 Este archivo
```

---

## 👥 División del Trabajo

### **Amigo 1: quickbite_cliente** (App para Clientes)

**Pantallas principales:**
- Login / Registro
- Home: Lista de negocios
- Negocio: Ver menú de productos
- Carrito: Gestionar pedido
- Checkout: Confirmar y pagar
- Mis Pedidos: Ver pedidos activos e historial
- Perfil: Datos del usuario

**Prioridad:**
1. Auth (login/registro)
2. Listar negocios
3. Ver productos y agregar al carrito
4. Checkout y crear pedido

---

### **Amigo 2: quickbite_negocio** (App para Negocios)

**Pantallas principales:**
- Login
- Dashboard: Estadísticas y pedidos pendientes
- Pedidos: Lista de pedidos (pendiente, preparando, listo)
- Detalle Pedido: Ver y actualizar estado
- Productos: Lista de productos del negocio
- Agregar/Editar Producto
- Perfil: Info del negocio

**Prioridad:**
1. Auth (login)
2. Dashboard con pedidos
3. Aceptar/rechazar pedidos
4. Actualizar estado de pedidos

---

### **Amigo 3: quickbite_repartidor** (App para Repartidores)

**Pantallas principales:**
- Login
- Dashboard: Estado (online/offline), pedidos disponibles
- Pedidos Disponibles: Lista para aceptar
- Pedido Activo: Mapa con ruta
- Detalle: Info del pedido, negocio, cliente
- Historial: Entregas completadas
- Perfil: Datos del repartidor

**Prioridad:**
1. Auth (login)
2. Ver pedidos disponibles
3. Aceptar pedido
4. Mostrar mapa con ruta (Google Maps)
5. Actualizar estado de entrega

---

## 🔧 Configuración API

En cada app, crear `lib/config/api_config.dart`:

```dart
class ApiConfig {
  // CAMBIAR según tu entorno
  static const String baseUrl = 'http://10.0.2.2/api'; // Emulador Android
  // static const String baseUrl = 'http://localhost/api'; // iOS Simulator
  // static const String baseUrl = 'http://192.168.1.X/api'; // Dispositivo real

  // Endpoints
  static const String login = '$baseUrl/auth/login.php';
  static const String register = '$baseUrl/auth/register.php';
  static const String negocios = '$baseUrl/negocios/listar.php';
  static const String productos = '$baseUrl/productos/listar.php';
  static const String pedidos = '$baseUrl/pedidos/crear.php';
}
```

---

## 🎨 Tema y Colores

Usar colores consistentes en las 3 apps:

```dart
// lib/config/theme.dart
class AppColors {
  static const Color primary = Color(0xFFFF6B35);      // Naranja QuickBite
  static const Color secondary = Color(0xFF2D3142);    // Gris oscuro
  static const Color accent = Color(0xFF4ECDC4);       // Verde azulado
  static const Color background = Color(0xFFF7F7F7);   // Gris claro
  static const Color success = Color(0xFF4CAF50);      // Verde
  static const Color warning = Color(0xFFFF9800);      // Naranja
  static const Color error = Color(0xFFE53935);        // Rojo
}
```

---

## 🔄 Workflow con Git

```bash
# 1. Crear rama para tu app
git checkout -b feature/cliente-login      # Amigo 1
git checkout -b feature/negocio-dashboard   # Amigo 2
git checkout -b feature/repartidor-mapa     # Amigo 3

# 2. Trabajar en tu código
# ... hacer cambios ...

# 3. Guardar cambios
git add .
git commit -m "feat: implementar login de clientes"

# 4. Subir cambios
git push origin feature/cliente-login

# 5. Actualizar tu rama con cambios de otros
git checkout main
git pull
git checkout feature/cliente-login
git merge main
```

---

## 📱 Comandos Útiles

```bash
# Ver dispositivos conectados
flutter devices

# Correr app en emulador
flutter run

# Correr en dispositivo específico
flutter run -d chrome
flutter run -d emulator-5554

# Hot reload (mientras corre)
# Presiona 'r' en la terminal

# Limpiar y reconstruir
flutter clean
flutter pub get
flutter run

# Analizar código
flutter analyze

# Compilar para producción
flutter build apk          # Android
flutter build ios          # iOS (requiere Mac)
flutter build web          # Web
```

---

## 🧪 Testing

Probar la API antes de desarrollar:

```bash
# Levantar backend
cd quickbite
php -S localhost:8000

# Probar endpoint
curl http://localhost:8000/api/health.php
curl http://localhost:8000/api/negocios/listar.php
```

---

## 📚 Recursos de Aprendizaje

### Flutter
- [Flutter Docs](https://docs.flutter.dev/)
- [Dart Language](https://dart.dev/guides)
- [Flutter Widget Catalog](https://docs.flutter.dev/ui/widgets)
- [Flutter Cookbook](https://docs.flutter.dev/cookbook)

### YouTube Channels
- [Flutter Official](https://www.youtube.com/@flutterdev)
- [The Net Ninja - Flutter](https://www.youtube.com/playlist?list=PL4cUxeGkcC9jLYyp2Aoh6hcWuxFDX6PBJ)
- [Rivaan Ranawat](https://www.youtube.com/@RivaanRanawat)

### Paquetes Útiles
- [pub.dev](https://pub.dev/) - Repositorio de paquetes
- [Provider](https://pub.dev/packages/provider) - State management
- [Dio](https://pub.dev/packages/dio) - HTTP client avanzado
- [Google Maps Flutter](https://pub.dev/packages/google_maps_flutter)

---

## ❓ Preguntas Frecuentes

**P: ¿Flutter es más fácil que Kotlin?**
R: Sí, Dart es más simple y Flutter tiene mejor documentación.

**P: ¿Puedo probar en iOS sin Mac?**
R: No para iOS nativo, pero sí puedes probar en navegador con `flutter run -d chrome`.

**P: ¿Las 3 apps comparten código?**
R: Sí, los modelos y servicios comunes están en `shared/`.

**P: ¿Cómo depuro errores?**
R: Usa `print()`, DevTools de Flutter, o extensión de VS Code/Android Studio.

**P: ¿Cómo subo a Google Play / App Store?**
R: Al final del desarrollo, usamos `flutter build` y seguimos guías de publicación.

---

## 🎯 Plan de Desarrollo (4 Semanas)

### **Semana 1: Fundamentos**
- Todos: Instalar Flutter, crear proyectos
- Login/Registro en las 3 apps
- Conexión exitosa con API

### **Semana 2: Funcionalidades Core**
- Cliente: Ver negocios y productos
- Negocio: Ver y gestionar pedidos
- Repartidor: Ver pedidos disponibles

### **Semana 3: Completar Flujo**
- Cliente: Carrito y checkout
- Negocio: Gestión de productos
- Repartidor: Mapas y navegación

### **Semana 4: Pulir y Testing**
- Corregir bugs
- Mejorar UI/UX
- Testing en dispositivos reales
- Preparar para producción

---

## 🚀 Siguiente Paso

1. **Instala Flutter**: [INSTALACION_FLUTTER.md](INSTALACION_FLUTTER.md)
2. **Crea los proyectos**: [FLUTTER_SETUP.md](FLUTTER_SETUP.md)
3. **Empieza a desarrollar**: Cada quien en su app

---

¡Éxito con el proyecto! 🎉
