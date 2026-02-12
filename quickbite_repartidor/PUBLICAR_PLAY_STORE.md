# 📱 Guía para Publicar en Google Play Store

Esta guía cubre cómo publicar la app QuickBite Repartidor en Google Play Store.

## 📋 Requisitos Previos

1. **Cuenta de Google Play Console** ($25 USD pago único)
   - Regístrate en: https://play.google.com/console/signup

2. **Flutter configurado** en tu máquina
   - Verifica con: `flutter doctor`

3. **Información de la app lista:**
   - Nombre de la app
   - Descripción corta (80 caracteres)
   - Descripción completa (4000 caracteres)
   - Íconos y screenshots
   - Política de privacidad (URL obligatoria)

---

## 🎨 PASO 1: Configurar Información de la App

### 1.1 Actualizar `pubspec.yaml`

```yaml
name: quickbite_repartidor
description: App para repartidores de QuickBite
publish_to: 'none'
version: 1.0.0+1  # version+buildNumber
```

### 1.2 Configurar `android/app/build.gradle`

```gradle
android {
    namespace "com.quickbite.repartidor"  # Cambiar este
    compileSdkVersion 34

    defaultConfig {
        applicationId "com.quickbite.repartidor"  # IMPORTANTE: ID único
        minSdkVersion 21
        targetSdkVersion 34
        versionCode 1        # Incrementar en cada release
        versionName "1.0.0"  # Versión visible al usuario
    }
}
```

### 1.3 Actualizar `AndroidManifest.xml`

```xml
<manifest xmlns:android="http://schemas.android.com/apk/res/android">
    <application
        android:label="QuickBite Repartidor"
        android:icon="@mipmap/ic_launcher">
        <!-- ... resto del archivo -->
    </application>
</manifest>
```

---

## 🔐 PASO 2: Generar Keystore (Firma de la App)

⚠️ **MUY IMPORTANTE:** Guarda el keystore en un lugar seguro. Si lo pierdes, no podrás actualizar la app.

### 2.1 Generar keystore

```bash
cd quickbite_repartidor/android/app

# En Windows
keytool -genkey -v -keystore C:\Users\TU_USUARIO\quickbite-repartidor.jks -keyalg RSA -keysize 2048 -validity 10000 -alias quickbite-repartidor

# En Mac/Linux
keytool -genkey -v -keystore ~/quickbite-repartidor.jks -keyalg RSA -keysize 2048 -validity 10000 -alias quickbite-repartidor
```

**Información a proporcionar:**
- Password: (guárdalo, lo necesitarás)
- Nombre y apellido: Tu nombre o empresa
- Unidad organizativa: QuickBite
- Organización: QuickBite
- Ciudad/Localidad: Tu ciudad
- Estado/Provincia: Tu estado
- Código de país: MX (o el tuyo)

### 2.2 Crear archivo `key.properties`

Crea `android/key.properties` (NO lo subas a git):

```properties
storePassword=TU_PASSWORD_AQUI
keyPassword=TU_PASSWORD_AQUI
keyAlias=quickbite-repartidor
storeFile=C:/Users/TU_USUARIO/quickbite-repartidor.jks
```

### 2.3 Agregar al `.gitignore`

```bash
echo "android/key.properties" >> .gitignore
echo "*.jks" >> .gitignore
```

### 2.4 Configurar signing en `android/app/build.gradle`

Agrega ANTES de `android {`:

```gradle
def keystoreProperties = new Properties()
def keystorePropertiesFile = rootProject.file('key.properties')
if (keystorePropertiesFile.exists()) {
    keystoreProperties.load(new FileInputStream(keystorePropertiesFile))
}

android {
    // ... existing config ...

    signingConfigs {
        release {
            keyAlias keystoreProperties['keyAlias']
            keyPassword keystoreProperties['keyPassword']
            storeFile keystoreProperties['storeFile'] ? file(keystoreProperties['storeFile']) : null
            storePassword keystoreProperties['storePassword']
        }
    }

    buildTypes {
        release {
            signingConfig signingConfigs.release
            minifyEnabled true
            shrinkResources true
            proguardFiles getDefaultProguardFile('proguard-android-optimize.txt'), 'proguard-rules.pro'
        }
    }
}
```

---

## 🏗️ PASO 3: Preparar la App para Producción

### 3.1 Actualizar API Config

En `lib/config/api_config.dart`, cambia a tu servidor de producción:

```dart
static const String baseUrl = 'https://tudominio.com/api'; // Producción
```

### 3.2 Generar Íconos de la App

#### Opción A: Usando herramienta online
1. Ve a https://www.appicon.co/
2. Sube tu logo (1024x1024 PNG)
3. Descarga el pack de Android
4. Reemplaza archivos en `android/app/src/main/res/mipmap-*`

#### Opción B: Usando flutter_launcher_icons

```bash
# Agregar a pubspec.yaml
dev_dependencies:
  flutter_launcher_icons: ^0.13.1

flutter_launcher_icons:
  android: true
  image_path: "assets/icon/app_icon.png"  # Tu ícono 1024x1024

# Generar
flutter pub get
flutter pub run flutter_launcher_icons
```

---

## 📦 PASO 4: Generar App Bundle para Play Store

### 4.1 Limpiar proyecto

```bash
cd quickbite_repartidor
flutter clean
flutter pub get
```

### 4.2 Generar App Bundle (.aab)

```bash
flutter build appbundle --release
```

El archivo se genera en:
```
build/app/outputs/bundle/release/app-release.aab
```

### 4.3 Verificar el build

```bash
# Ver tamaño
ls -lh build/app/outputs/bundle/release/app-release.aab

# Verificar signing (opcional)
bundletool build-apks --bundle=build/app/outputs/bundle/release/app-release.aab --output=test.apks
```

---

## 🌐 PASO 5: Google Play Console

### 5.1 Crear App en Play Console

1. **Ir a:** https://play.google.com/console
2. Click en **"Crear aplicación"**
3. Rellenar:
   - **Nombre:** QuickBite Repartidor
   - **Idioma predeterminado:** Español (España)
   - **Tipo de app:** Aplicación
   - **Gratis o de pago:** Gratis
   - Aceptar declaraciones

### 5.2 Configurar Ficha de Play Store

#### Detalles de la aplicación
- **Nombre de la app:** QuickBite Repartidor
- **Descripción breve (80 caracteres):**
  ```
  App para repartidores de QuickBite. Acepta y entrega pedidos fácilmente.
  ```

- **Descripción completa (máx 4000):**
  ```
  QuickBite Repartidor es la aplicación oficial para los repartidores de la plataforma QuickBite.

  🚀 CARACTERÍSTICAS:
  • Recibe notificaciones de nuevos pedidos en tiempo real
  • Acepta múltiples pedidos simultáneamente
  • Navegación GPS integrada al negocio y cliente
  • Actualiza el estado del pedido en tiempo real
  • Historial completo de entregas
  • Estadísticas de ganancias

  📍 CÓMO FUNCIONA:
  1. Activa tu disponibilidad
  2. Recibe pedidos disponibles
  3. Acepta el pedido
  4. Recoge en el negocio
  5. Entrega al cliente
  6. Marca como entregado

  💰 GANA DINERO:
  Trabaja con flexibilidad y gana dinero entregando pedidos de restaurantes y negocios locales.

  📞 SOPORTE:
  ¿Necesitas ayuda? Contacta a soporte@quickbite.com
  ```

#### Assets gráficos requeridos:

**Ícono de la app:**
- 512x512 PNG (32 bits con alpha)

**Gráfico destacado:**
- 1024x500 JPG o PNG

**Capturas de pantalla del teléfono (mínimo 2, máximo 8):**
- 16:9 o 9:16
- Mínimo: 320px
- Máximo: 3840px

**Ejemplo de dimensiones válidas:**
- 1080x1920 (recomendado)
- 1080x2340
- 1440x2960

### 5.3 Categorización

- **Categoría:** Negocios
- **Etiquetas:** repartidor, delivery, entregas, logística

### 5.4 Información de contacto

- **Correo electrónico:** tu@email.com
- **Teléfono:** +52 123 456 7890 (opcional pero recomendado)
- **Sitio web:** https://tudominio.com

### 5.5 Política de privacidad

⚠️ **OBLIGATORIO:** Necesitas una URL pública con tu política de privacidad.

**Ejemplo mínimo de política:**
```
Política de Privacidad de QuickBite Repartidor

1. INFORMACIÓN QUE RECOPILAMOS
- Ubicación GPS en tiempo real (para asignar pedidos cercanos)
- Información de perfil (nombre, teléfono, foto)
- Historial de entregas

2. CÓMO USAMOS LA INFORMACIÓN
- Asignar pedidos cercanos
- Calcular rutas de entrega
- Procesar pagos

3. PERMISOS
- Ubicación: Necesario para recibir y entregar pedidos
- Cámara: Para subir foto de perfil (opcional)
- Llamadas: Para contactar a clientes

4. CONTACTO
soporte@quickbite.com
```

Súbelo a: `https://tudominio.com/privacidad-repartidor.html`

---

## 📤 PASO 6: Subir App Bundle

### 6.1 Crear Release

1. En Play Console, ve a **"Producción"** (sidebar izquierdo)
2. Click en **"Crear nueva versión"**
3. **Subir app bundle:**
   - Click en "Subir"
   - Selecciona `app-release.aab`
   - Espera a que termine

### 6.2 Notas de la versión

```
Versión inicial de QuickBite Repartidor

• Recepción de pedidos en tiempo real
• Multipedido (varios pedidos simultáneos)
• Navegación GPS integrada
• Historial de entregas
• Estadísticas de ganancias
```

### 6.3 Revisión de contenido

Responde las preguntas sobre:
- ✅ Anuncios: Si/No
- ✅ Clasificación de contenido: Completa cuestionario
- ✅ Público objetivo: Mayores de 18 años (repartidores)
- ✅ Permisos sensibles:
  - Ubicación: SÍ (para asignar pedidos)
  - Cámara: NO (opcional para foto perfil)

---

## ✅ PASO 7: Enviar para Revisión

### 7.1 Verificación final

Asegúrate de tener completado:
- ✅ Descripción de la app
- ✅ Screenshots (mínimo 2)
- ✅ Ícono 512x512
- ✅ Gráfico destacado 1024x500
- ✅ Política de privacidad
- ✅ App bundle subido
- ✅ Clasificación de contenido
- ✅ Precios y distribución configurados

### 7.2 Enviar

1. Click en **"Enviar para revisión"**
2. Espera aprobación (puede tardar de horas a días)
3. Recibirás email cuando esté aprobada o rechazada

---

## 🔄 PASO 8: Actualizaciones Futuras

### 8.1 Incrementar versión

En `android/app/build.gradle`:

```gradle
defaultConfig {
    versionCode 2        // +1 en cada actualización
    versionName "1.1.0"  // Versión semántica
}
```

### 8.2 Generar nuevo bundle

```bash
flutter build appbundle --release
```

### 8.3 Subir actualización

1. Play Console → Producción → Nueva versión
2. Subir nuevo `app-release.aab`
3. Agregar notas de la versión
4. Enviar para revisión

---

## 📊 Configuración de Producción

### Config API en producción

```dart
// lib/config/api_config.dart
class ApiConfig {
  static const String baseUrl = 'https://api.quickbite.com/api';

  static const Duration timeout = Duration(seconds: 30);
  static const Duration pollingInterval = Duration(seconds: 15);
}
```

### Configurar Google Maps API Key

1. Ve a: https://console.cloud.google.com/
2. Crea proyecto "QuickBite Repartidor"
3. Habilita "Maps SDK for Android"
4. Crea credenciales → API Key
5. Restringe por app (SHA-1 fingerprint)
6. Actualiza `AndroidManifest.xml`:

```xml
<meta-data
    android:name="com.google.android.geo.API_KEY"
    android:value="TU_API_KEY_DE_PRODUCCION"/>
```

---

## 🐛 Troubleshooting

### Error: "App not signed"
- Verifica que `key.properties` existe
- Verifica rutas en `build.gradle`

### Error: "Version code already exists"
- Incrementa `versionCode` en `build.gradle`

### Error: "Minimum SDK version"
- Cambia `minSdkVersion` a 21 o superior

### App rechazada por políticas
- Lee el email de rechazo
- Corrige lo solicitado
- Vuelve a enviar

---

## 📝 Checklist Final

Antes de publicar:

- [ ] API URL apunta a producción
- [ ] Google Maps API Key configurado
- [ ] Keystore guardado en lugar seguro
- [ ] Versión incrementada
- [ ] Íconos actualizados
- [ ] Screenshots preparados (mínimo 2)
- [ ] Política de privacidad publicada
- [ ] Descripción completa
- [ ] App bundle generado sin errores
- [ ] Probado en dispositivo real

---

## 🎉 ¡Listo!

Tu app estará disponible en Play Store en 1-3 días después de la aprobación.

**URL de tu app será:**
```
https://play.google.com/store/apps/details?id=com.quickbite.repartidor
```

---

## 📞 Recursos Adicionales

- **Play Console:** https://play.google.com/console
- **Documentación Flutter:** https://docs.flutter.dev/deployment/android
- **Políticas de Play Store:** https://play.google.com/about/developer-content-policy/
- **Status de revisión:** En Play Console → Dashboard

---

## 💡 Tips Profesionales

1. **Testing:** Usa "Internal Testing" o "Closed Testing" antes de producción
2. **Beta testers:** Invita usuarios para probar antes del lanzamiento público
3. **Staged rollout:** Empieza con 10% de usuarios, luego incrementa
4. **Monitoreo:** Revisa crashes en Play Console → Calidad
5. **Responde reviews:** Mantén buena calificación respondiendo usuarios
