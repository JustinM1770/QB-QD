# 🚀 Setup de Proyectos Flutter

Esta guía te ayudará a crear los 3 proyectos Flutter para QuickBite.

---

## 📁 Estructura del Proyecto

```
quickbite/
├── api/                          # Backend PHP (ya existe)
├── config/                       # Configuración backend
├── shared/                       # Código compartido entre apps
│   ├── lib/
│   │   ├── models/              # Modelos de datos
│   │   ├── services/            # Servicios API
│   │   └── utils/               # Utilidades
│   └── pubspec.yaml
├── quickbite_cliente/           # App para CLIENTES
├── quickbite_negocio/           # App para NEGOCIOS
├── quickbite_repartidor/        # App para REPARTIDORES
└── README_DESARROLLO_FLUTTER.md # Documentación
```

---

## 🎯 PASO 1: Crear los 3 Proyectos

### Desde la raíz del proyecto:

```bash
cd /ruta/a/quickbite

# App para Clientes
flutter create quickbite_cliente --org com.quickbite --description "App QuickBite para clientes"

# App para Negocios
flutter create quickbite_negocio --org com.quickbite --description "App QuickBite para negocios"

# App para Repartidores
flutter create quickbite_repartidor --org com.quickbite --description "App QuickBite para repartidores"

# Crear paquete compartido
flutter create --template=package shared
```

---

## 🔧 PASO 2: Configurar Dependencias

### Para cada app (quickbite_cliente, quickbite_negocio, quickbite_repartidor):

Edita `pubspec.yaml`:

```yaml
name: quickbite_cliente  # o negocio, o repartidor
description: App QuickBite para clientes
publish_to: 'none'
version: 1.0.0+1

environment:
  sdk: '>=3.0.0 <4.0.0'

dependencies:
  flutter:
    sdk: flutter

  # HTTP & API
  http: ^1.1.0
  dio: ^5.4.0

  # State Management
  provider: ^6.1.1

  # Navigation
  go_router: ^13.0.0

  # Storage
  shared_preferences: ^2.2.2

  # UI
  cupertino_icons: ^1.0.2
  cached_network_image: ^3.3.1
  flutter_svg: ^2.0.9
  shimmer: ^3.0.0

  # Utilities
  intl: ^0.19.0

  # Código compartido (local package)
  shared:
    path: ../shared

dev_dependencies:
  flutter_test:
    sdk: flutter
  flutter_lints: ^3.0.0

flutter:
  uses-material-design: true

  # Agregar assets aquí cuando los tengas
  # assets:
  #   - assets/images/
  #   - assets/icons/
```

### Para quickbite_repartidor (adicional):

Agregar dependencias de mapas:

```yaml
  # Mapas (solo para repartidores)
  google_maps_flutter: ^2.5.0
  geolocator: ^11.0.0
  location: ^5.0.3
  url_launcher: ^6.2.4  # Para llamar al cliente
```

---

## 📦 PASO 3: Crear Paquete Compartido

### Editar `shared/pubspec.yaml`:

```yaml
name: shared
description: Código compartido entre apps QuickBite
version: 1.0.0
publish_to: 'none'

environment:
  sdk: '>=3.0.0 <4.0.0'

dependencies:
  http: ^1.1.0
  dio: ^5.4.0
  intl: ^0.19.0
```

---

## 🏗️ PASO 4: Estructura de Carpetas

### Para cada app Flutter:

```
quickbite_cliente/
├── lib/
│   ├── main.dart                 # Punto de entrada
│   ├── config/
│   │   └── api_config.dart      # Configuración de API
│   ├── models/                   # Modelos específicos de la app
│   ├── providers/                # State management
│   │   ├── auth_provider.dart
│   │   ├── cart_provider.dart
│   │   └── ...
│   ├── screens/                  # Pantallas
│   │   ├── auth/
│   │   │   ├── login_screen.dart
│   │   │   └── register_screen.dart
│   │   ├── home/
│   │   │   └── home_screen.dart
│   │   └── ...
│   ├── widgets/                  # Widgets reutilizables
│   │   ├── custom_button.dart
│   │   ├── product_card.dart
│   │   └── ...
│   ├── services/                 # Servicios de API
│   │   ├── auth_service.dart
│   │   ├── product_service.dart
│   │   └── ...
│   └── utils/                    # Utilidades
│       ├── constants.dart
│       └── helpers.dart
├── pubspec.yaml
└── test/
```

---

## 🔑 PASO 5: Configuración de API

### Crear `lib/config/api_config.dart` en cada app:

```dart
class ApiConfig {
  // Cambiar según tu entorno
  static const String baseUrl = 'http://10.0.2.2/api'; // Emulador Android
  // static const String baseUrl = 'http://localhost/api'; // iOS Simulator
  // static const String baseUrl = 'http://192.168.1.X/api'; // Dispositivo físico
  // static const String baseUrl = 'https://tudominio.com/api'; // Producción

  // Endpoints
  static const String login = '$baseUrl/auth/login.php';
  static const String register = '$baseUrl/auth/register.php';
  static const String negocios = '$baseUrl/negocios/listar.php';
  static const String productos = '$baseUrl/productos/listar.php';
  // ... más endpoints
}
```

---

## 🎨 PASO 6: Configurar Colores y Tema

### Crear `lib/config/theme.dart`:

```dart
import 'package:flutter/material.dart';

class AppTheme {
  static const Color primaryColor = Color(0xFFFF6B35);  // Naranja QuickBite
  static const Color secondaryColor = Color(0xFF2D3142);
  static const Color accentColor = Color(0xFF4ECDC4);
  static const Color backgroundColor = Color(0xFFF7F7F7);

  static ThemeData lightTheme = ThemeData(
    primaryColor: primaryColor,
    colorScheme: ColorScheme.fromSeed(
      seedColor: primaryColor,
      brightness: Brightness.light,
    ),
    useMaterial3: true,
    appBarTheme: const AppBarTheme(
      backgroundColor: primaryColor,
      foregroundColor: Colors.white,
      elevation: 0,
    ),
    elevatedButtonTheme: ElevatedButtonThemeData(
      style: ElevatedButton.styleFrom(
        backgroundColor: primaryColor,
        foregroundColor: Colors.white,
        padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 16),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
        ),
      ),
    ),
  );
}
```

---

## 🚀 PASO 7: Configurar main.dart

### `quickbite_cliente/lib/main.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'config/theme.dart';
import 'providers/auth_provider.dart';
import 'screens/auth/login_screen.dart';

void main() {
  runApp(const QuickBiteClienteApp());
}

class QuickBiteClienteApp extends StatelessWidget {
  const QuickBiteClienteApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthProvider()),
        // Agregar más providers aquí
      ],
      child: MaterialApp(
        title: 'QuickBite - Cliente',
        theme: AppTheme.lightTheme,
        debugShowCheckedModeBanner: false,
        home: const LoginScreen(),
      ),
    );
  }
}
```

---

## 🔐 PASO 8: Ejemplo de Provider (Auth)

### Crear `lib/providers/auth_provider.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../services/auth_service.dart';
import 'package:shared/models/usuario.dart';

class AuthProvider extends ChangeNotifier {
  final AuthService _authService = AuthService();

  Usuario? _usuario;
  bool _isLoading = false;
  String? _error;

  Usuario? get usuario => _usuario;
  bool get isLoading => _isLoading;
  String? get error => _error;
  bool get isAuthenticated => _usuario != null;

  Future<bool> login(String email, String password) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      _usuario = await _authService.login(email, password);
      await _saveToken(_usuario!.token);
      _isLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      _error = e.toString();
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  Future<void> logout() async {
    _usuario = null;
    await _clearToken();
    notifyListeners();
  }

  Future<void> _saveToken(String token) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('auth_token', token);
  }

  Future<void> _clearToken() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('auth_token');
  }
}
```

---

## 🌐 PASO 9: Ejemplo de Servicio API

### Crear `lib/services/auth_service.dart`:

```dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import '../config/api_config.dart';
import 'package:shared/models/usuario.dart';

class AuthService {
  Future<Usuario> login(String email, String password) async {
    final response = await http.post(
      Uri.parse(ApiConfig.login),
      headers: {'Content-Type': 'application/json'},
      body: json.encode({
        'email': email,
        'password': password,
      }),
    );

    if (response.statusCode == 200) {
      final data = json.decode(response.body);
      if (data['success'] == true) {
        return Usuario.fromJson(data['usuario']);
      } else {
        throw Exception(data['message'] ?? 'Error en login');
      }
    } else {
      throw Exception('Error de conexión');
    }
  }

  Future<Usuario> register(String nombre, String email, String password) async {
    final response = await http.post(
      Uri.parse(ApiConfig.register),
      headers: {'Content-Type': 'application/json'},
      body: json.encode({
        'nombre': nombre,
        'email': email,
        'password': password,
        'tipo': 'cliente',
      }),
    );

    if (response.statusCode == 200) {
      final data = json.decode(response.body);
      if (data['success'] == true) {
        return Usuario.fromJson(data['usuario']);
      } else {
        throw Exception(data['message'] ?? 'Error en registro');
      }
    } else {
      throw Exception('Error de conexión');
    }
  }
}
```

---

## 📄 PASO 10: Modelos Compartidos

### Crear `shared/lib/models/usuario.dart`:

```dart
class Usuario {
  final int id;
  final String nombre;
  final String email;
  final String? telefono;
  final String tipo; // cliente, negocio, repartidor
  final String token;

  Usuario({
    required this.id,
    required this.nombre,
    required this.email,
    this.telefono,
    required this.tipo,
    required this.token,
  });

  factory Usuario.fromJson(Map<String, dynamic> json) {
    return Usuario(
      id: json['id'],
      nombre: json['nombre'],
      email: json['email'],
      telefono: json['telefono'],
      tipo: json['tipo'],
      token: json['token'] ?? '',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'nombre': nombre,
      'email': email,
      'telefono': telefono,
      'tipo': tipo,
      'token': token,
    };
  }
}
```

---

## ✅ PASO 11: Verificar que Todo Funciona

### En cada proyecto:

```bash
# 1. Obtener dependencias
cd quickbite_cliente
flutter pub get

# 2. Analizar código
flutter analyze

# 3. Correr app
flutter run

# Debería compilar sin errores
```

---

## 🎯 División del Trabajo

| Desarrollador | App | Carpeta | Enfoque |
|--------------|-----|---------|---------|
| **Amigo 1** | Clientes | `quickbite_cliente/` | Ver negocios, hacer pedidos |
| **Amigo 2** | Negocios | `quickbite_negocio/` | Gestionar pedidos y productos |
| **Amigo 3** | Repartidores | `quickbite_repartidor/` | Entregas con mapas |

---

## 📱 Siguiente Paso

Lee los README específicos de cada app:

- [quickbite_cliente/README.md](quickbite_cliente/README.md)
- [quickbite_negocio/README.md](quickbite_negocio/README.md)
- [quickbite_repartidor/README.md](quickbite_repartidor/README.md)

---

¡Éxito! 🚀
