# 📦 Shared Package

Paquete compartido entre las 3 apps QuickBite (Cliente, Negocio, Repartidor).

## Contenido

### Modelos de Datos

- **Usuario**: Modelo para usuarios (clientes, negocios, repartidores)
- **Negocio**: Modelo para restaurantes/negocios
- **Producto**: Modelo para productos del menú
- **Pedido**: Modelo para pedidos y detalles

## Uso

En cada app, importa el paquete:

```dart
import 'package:shared/shared.dart';

// Usar modelos
Usuario usuario = Usuario.fromJson(jsonData);
Negocio negocio = Negocio.fromJson(jsonData);
```

## Agregar Nuevo Modelo

1. Crear archivo en `lib/models/`
2. Exportar en `lib/shared.dart`
3. Ejecutar `flutter pub get` en cada app
