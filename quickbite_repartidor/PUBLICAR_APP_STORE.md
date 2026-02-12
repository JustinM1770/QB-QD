# 🍎 Guía para Publicar en Apple App Store

Esta guía cubre cómo publicar la app QuickBite Repartidor en Apple App Store.

## 📋 Requisitos Previos

1. **Mac con Xcode** (obligatorio para compilar iOS)
2. **Apple Developer Account** ($99 USD/año)
   - Regístrate en: https://developer.apple.com/programs/
3. **Flutter configurado**
   - Verifica con: `flutter doctor`

---

## 🎨 PASO 1: Configurar App en Xcode

### 1.1 Abrir proyecto iOS

```bash
cd quickbite_repartidor
open ios/Runner.xcworkspace  # Abre en Xcode
```

### 1.2 Configurar Bundle Identifier

En Xcode:
1. Selecciona "Runner" en el navegador
2. En "General" → "Identity":
   - **Bundle Identifier:** `com.quickbite.repartidor`
   - **Version:** `1.0.0`
   - **Build:** `1`

### 1.3 Configurar Signing & Capabilities

1. **Signing:**
   - Team: Selecciona tu cuenta de Apple Developer
   - ✅ Automatically manage signing

2. **Capabilities necesarias:**
   - Location (Always and When In Use)
   - Background Modes → Location updates

---

## 🔐 PASO 2: Configurar en App Store Connect

### 2.1 Crear App en App Store Connect

1. Ve a: https://appstoreconnect.apple.com/
2. Click en **"My Apps"** → **"+"** → **"New App"**
3. Rellenar:
   - **Platform:** iOS
   - **Name:** QuickBite Repartidor
   - **Primary Language:** Spanish (Spain)
   - **Bundle ID:** com.quickbite.repartidor
   - **SKU:** quickbite-repartidor-001
   - **User Access:** Full Access

### 2.2 Información de la App

**App Information:**
- **Name:** QuickBite Repartidor
- **Subtitle (30 chars):** App para repartidores
- **Category:** Business
- **Secondary Category:** Navigation (opcional)

**Privacy Policy URL:**
```
https://tudominio.com/privacidad-repartidor.html
```

---

## 📸 PASO 3: Preparar Assets

### 3.1 App Icon (obligatorio)

Necesitas íconos en varios tamaños. Usa https://appicon.co/:

1. Sube logo 1024x1024 PNG
2. Descarga pack de iOS
3. Reemplaza en `ios/Runner/Assets.xcassets/AppIcon.appiconset/`

### 3.2 Screenshots (obligatorios)

**iPhone (6.5" Display) - iPhone 14 Pro Max:**
- Resolución: 1290 × 2796 pixels
- Mínimo: 1 screenshot

**iPhone (5.5" Display) - iPhone 8 Plus:**
- Resolución: 1242 × 2208 pixels
- Mínimo: 1 screenshot

**Recomendación:** Usa simulador para capturar:

```bash
# Iniciar simulador
open -a Simulator

# Capturar screenshots con Cmd+S
# Guardar en formato PNG
```

---

## 📦 PASO 4: Generar Build para App Store

### 4.1 Limpiar proyecto

```bash
cd quickbite_repartidor
flutter clean
flutter pub get
cd ios
pod install
cd ..
```

### 4.2 Generar build

```bash
flutter build ipa --release
```

El archivo se genera en:
```
build/ios/ipa/quickbite_repartidor.ipa
```

### 4.3 Subir a App Store Connect

#### Opción A: Con Xcode
1. Abre Xcode
2. Window → Organizer
3. Selecciona el archivo
4. Click "Distribute App"
5. Selecciona "App Store Connect"
6. Upload

#### Opción B: Con Transporter
1. Descarga "Transporter" desde Mac App Store
2. Abre Transporter
3. Arrastra el archivo `.ipa`
4. Click "Deliver"

---

## ✍️ PASO 5: Completar Información en App Store

### 5.1 Descripción de la App

```
QuickBite Repartidor es la aplicación oficial para los repartidores de la plataforma QuickBite.

🚀 CARACTERÍSTICAS PRINCIPALES:
• Recibe pedidos en tiempo real
• Acepta múltiples pedidos simultáneamente
• Navegación GPS integrada
• Actualización de estado en tiempo real
• Historial completo de entregas
• Estadísticas de ganancias

📍 CÓMO FUNCIONA:
1. Activa tu disponibilidad
2. Recibe notificaciones de pedidos
3. Acepta pedidos cercanos
4. Recoge en el negocio
5. Entrega al cliente
6. Marca como completado

💰 FLEXIBILIDAD:
Trabaja cuando quieras y gana dinero entregando pedidos de restaurantes y negocios locales.
```

### 5.2 Keywords (100 caracteres máximo)

```
repartidor,delivery,entregas,comida,pedidos,trabajo,ganancias
```

### 5.3 Support URL

```
https://tudominio.com/soporte-repartidor
```

### 5.4 Marketing URL (opcional)

```
https://tudominio.com/repartidor
```

---

## 🔒 PASO 6: Privacidad y Permisos

### 6.1 Declaración de Privacidad

En App Store Connect → App Privacy:

**Location - Precise Location:**
- ✅ Used for App Functionality
- Descripción: "Usamos tu ubicación para asignarte pedidos cercanos y calcular rutas de entrega"

**Contact Info - Phone Number:**
- ✅ Used for App Functionality
- Descripción: "Para contactar con clientes sobre el pedido"

**User ID:**
- ✅ Used for App Functionality
- Descripción: "Para gestionar tu cuenta de repartidor"

### 6.2 Age Rating

Completa el cuestionario:
- **Frequent/Intense Realistic Violence:** No
- **Made For Kids:** No
- **Age Rating:** 17+ (trabajo requiere mayoría de edad)

---

## 📝 PASO 7: Configurar Permisos en iOS

### 7.1 Actualizar `Info.plist`

Edita `ios/Runner/Info.plist`:

```xml
<dict>
    <!-- Permisos de Ubicación -->
    <key>NSLocationWhenInUseUsageDescription</key>
    <string>Necesitamos tu ubicación para asignarte pedidos cercanos</string>

    <key>NSLocationAlwaysAndWhenInUseUsageDescription</key>
    <string>Necesitamos tu ubicación incluso en segundo plano para recibir pedidos mientras la app está cerrada</string>

    <key>NSLocationAlwaysUsageDescription</key>
    <string>Necesitamos tu ubicación en segundo plano para actualizar tu posición durante las entregas</string>

    <!-- Permiso de Cámara (opcional) -->
    <key>NSCameraUsageDescription</key>
    <string>Para tomar foto de perfil o de la entrega</string>

    <!-- Background Modes -->
    <key>UIBackgroundModes</key>
    <array>
        <string>location</string>
        <string>fetch</string>
    </array>
</dict>
```

---

## 🧪 PASO 8: TestFlight (Recomendado)

Antes de publicar, prueba con TestFlight:

### 8.1 Configurar TestFlight

1. En App Store Connect → TestFlight
2. Selecciona el build subido
3. Agrega testers:
   - Internal: Hasta 100 (tu equipo)
   - External: Hasta 10,000 (beta testers)

### 8.2 Invitar Beta Testers

1. Click "Add Testers"
2. Ingresa emails
3. Envía invitación
4. Testers instalan "TestFlight" app
5. Reciben y prueban tu app

---

## 📤 PASO 9: Enviar para Revisión

### 9.1 Completar toda la información

Checklist:
- ✅ Screenshots subidos
- ✅ Descripción completa
- ✅ Keywords
- ✅ Support URL
- ✅ Privacy Policy URL
- ✅ Age Rating
- ✅ App Privacy completado
- ✅ Build seleccionado

### 9.2 Pricing and Availability

- **Price:** Free
- **Availability:** All countries (o selecciona países específicos)

### 9.3 App Review Information

Proporciona:
- **Contact Information:**
  - First Name: Tu nombre
  - Last Name: Apellido
  - Phone: +52 123 456 7890
  - Email: soporte@quickbite.com

- **Demo Account (si requiere login):**
  - Username: demo_repartidor@quickbite.com
  - Password: DemoPass123!
  - Notas: "Cuenta de prueba para revisar funcionalidad"

### 9.4 Enviar

1. Click **"Submit for Review"**
2. Espera aprobación (generalmente 1-3 días)
3. Recibirás updates por email

---

## 🔄 PASO 10: Actualizaciones

### 10.1 Incrementar versión

En `pubspec.yaml`:
```yaml
version: 1.1.0+2  # version+buildNumber
```

En Xcode:
- Version: 1.1.0
- Build: 2

### 10.2 Generar nuevo build

```bash
flutter build ipa --release
```

### 10.3 Subir actualización

1. Sube el nuevo `.ipa` con Transporter/Xcode
2. En App Store Connect → selecciona nuevo build
3. Agrega "What's New" (notas de versión)
4. Submit for Review

---

## 🐛 Troubleshooting

### Error: "No valid signing identity"
- Verifica que tienes Developer Account activa
- Descarga certificados en Xcode → Preferences → Accounts

### Error: "Missing compliance"
- Responde el cuestionario de cifrado en App Store Connect
- Generalmente: "No" para apps sin cifrado custom

### Build rejected
- Lee cuidadosamente el email de rechazo
- Corrige el problema
- Responde al revisor si es necesario
- Vuelve a enviar

---

## 📊 Monitoreo Post-Lanzamiento

### Analytics en App Store Connect

1. **App Analytics:**
   - Descargas
   - Sesiones
   - Crashes
   - Retention

2. **Customer Reviews:**
   - Responde reviews
   - Mantén buena calificación

3. **Crashes:**
   - Monitorea crashes en Xcode Organizer
   - Usa Firebase Crashlytics para mejor tracking

---

## 💡 Tips para Aprobación

1. **Demo account funcional:** Si requiere login, proporciona credenciales válidas
2. **Contenido completo:** No envíes app con "Coming Soon" o features incompletas
3. **Permisos justificados:** Explica claramente por qué necesitas cada permiso
4. **Sin bugs críticos:** Prueba exhaustivamente antes de enviar
5. **Políticas de Apple:** Lee https://developer.apple.com/app-store/review/guidelines/

---

## 🎉 ¡Listo!

Tu app estará en App Store después de la aprobación (generalmente 1-3 días).

**URL de tu app será:**
```
https://apps.apple.com/app/idXXXXXXXXXX
```

---

## 📞 Recursos

- **App Store Connect:** https://appstoreconnect.apple.com/
- **Developer Portal:** https://developer.apple.com/
- **Guidelines:** https://developer.apple.com/app-store/review/guidelines/
- **TestFlight:** https://developer.apple.com/testflight/
- **Flutter iOS Deploy:** https://docs.flutter.dev/deployment/ios
