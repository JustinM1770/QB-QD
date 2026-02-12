#!/bin/bash

###############################################################################
# Script para generar builds de producción de QuickBite Repartidor
# Uso: ./scripts/build_release.sh [android|ios|both]
###############################################################################

set -e  # Exit on error

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Funciones de utilidad
print_header() {
    echo -e "\n${BLUE}========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}========================================${NC}\n"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

# Verificar que estamos en el directorio correcto
if [ ! -f "pubspec.yaml" ]; then
    print_error "Error: Debes ejecutar este script desde el directorio raíz del proyecto Flutter"
    exit 1
fi

# Leer plataforma del argumento
PLATFORM=${1:-both}

# Validar argumento
if [[ "$PLATFORM" != "android" && "$PLATFORM" != "ios" && "$PLATFORM" != "both" ]]; then
    print_error "Uso: $0 [android|ios|both]"
    exit 1
fi

print_header "🚀 QuickBite Repartidor - Build de Producción"

# Leer versión actual
VERSION=$(grep "^version:" pubspec.yaml | sed 's/version: //')
print_warning "Versión actual: $VERSION"

# Preguntar si quiere incrementar versión
read -p "¿Quieres incrementar la versión? (y/n): " INCREMENT_VERSION

if [[ "$INCREMENT_VERSION" == "y" ]]; then
    read -p "Nueva versión (ej. 1.1.0+2): " NEW_VERSION

    # Actualizar pubspec.yaml
    if [[ "$OSTYPE" == "darwin"* ]]; then
        # macOS
        sed -i '' "s/^version: .*/version: $NEW_VERSION/" pubspec.yaml
    else
        # Linux
        sed -i "s/^version: .*/version: $NEW_VERSION/" pubspec.yaml
    fi

    print_success "Versión actualizada a: $NEW_VERSION"
    VERSION=$NEW_VERSION
fi

# Paso 1: Limpiar proyecto
print_header "🧹 Limpiando proyecto"
flutter clean
print_success "Limpieza completada"

# Paso 2: Obtener dependencias
print_header "📦 Obteniendo dependencias"
flutter pub get
print_success "Dependencias obtenidas"

# Paso 3: Verificar configuración
print_header "🔍 Verificando configuración"

# Verificar API URL
API_URL=$(grep "baseUrl = " lib/config/api_config.dart | grep -o "'.*'" | tr -d "'")
print_warning "API URL configurada: $API_URL"

if [[ "$API_URL" == *"localhost"* ]] || [[ "$API_URL" == *"10.0.2.2"* ]]; then
    print_error "⚠️  ADVERTENCIA: La URL de la API apunta a localhost/emulador"
    read -p "¿Estás seguro que quieres continuar? (y/n): " CONTINUE
    if [[ "$CONTINUE" != "y" ]]; then
        print_error "Build cancelado"
        exit 1
    fi
fi

# Paso 4: Build Android
if [[ "$PLATFORM" == "android" || "$PLATFORM" == "both" ]]; then
    print_header "🤖 Generando build de Android"

    # Verificar que existe key.properties
    if [ ! -f "android/key.properties" ]; then
        print_error "Error: No se encuentra android/key.properties"
        print_warning "Debes crear este archivo con las credenciales del keystore"
        print_warning "Ver: PUBLICAR_PLAY_STORE.md para instrucciones"
        exit 1
    fi

    # Generar App Bundle
    print_warning "Generando App Bundle (.aab)..."
    flutter build appbundle --release

    AAB_PATH="build/app/outputs/bundle/release/app-release.aab"

    if [ -f "$AAB_PATH" ]; then
        AAB_SIZE=$(ls -lh "$AAB_PATH" | awk '{print $5}')
        print_success "App Bundle generado exitosamente"
        print_success "Ubicación: $AAB_PATH"
        print_success "Tamaño: $AAB_SIZE"
    else
        print_error "Error al generar App Bundle"
        exit 1
    fi

    # Opcionalmente generar APK
    read -p "¿Generar también APK para testing? (y/n): " GEN_APK
    if [[ "$GEN_APK" == "y" ]]; then
        print_warning "Generando APK..."
        flutter build apk --release

        APK_PATH="build/app/outputs/flutter-apk/app-release.apk"
        if [ -f "$APK_PATH" ]; then
            APK_SIZE=$(ls -lh "$APK_PATH" | awk '{print $5}')
            print_success "APK generado exitosamente"
            print_success "Ubicación: $APK_PATH"
            print_success "Tamaño: $APK_SIZE"
        fi
    fi
fi

# Paso 5: Build iOS
if [[ "$PLATFORM" == "ios" || "$PLATFORM" == "both" ]]; then
    print_header "🍎 Generando build de iOS"

    # Verificar que estamos en macOS
    if [[ "$OSTYPE" != "darwin"* ]]; then
        print_error "Error: Los builds de iOS solo se pueden generar en macOS"
        exit 1
    fi

    # Actualizar pods
    print_warning "Actualizando CocoaPods..."
    cd ios
    pod install
    cd ..

    # Generar IPA
    print_warning "Generando archivo IPA..."
    flutter build ipa --release

    IPA_PATH="build/ios/ipa/quickbite_repartidor.ipa"

    if [ -f "$IPA_PATH" ]; then
        IPA_SIZE=$(ls -lh "$IPA_PATH" | awk '{print $5}')
        print_success "IPA generado exitosamente"
        print_success "Ubicación: $IPA_PATH"
        print_success "Tamaño: $IPA_SIZE"
    else
        print_error "Error al generar IPA"
        exit 1
    fi
fi

# Paso 6: Resumen
print_header "✅ Build Completado"

echo "📱 Plataforma: $PLATFORM"
echo "🏷️  Versión: $VERSION"
echo ""
echo "📂 Archivos generados:"

if [[ "$PLATFORM" == "android" || "$PLATFORM" == "both" ]]; then
    echo "   Android:"
    if [ -f "$AAB_PATH" ]; then
        echo "   - $AAB_PATH ($AAB_SIZE)"
    fi
    if [ -f "$APK_PATH" ]; then
        echo "   - $APK_PATH ($APK_SIZE)"
    fi
fi

if [[ "$PLATFORM" == "ios" || "$PLATFORM" == "both" ]]; then
    echo "   iOS:"
    if [ -f "$IPA_PATH" ]; then
        echo "   - $IPA_PATH ($IPA_SIZE)"
    fi
fi

echo ""
print_success "¡Todo listo para publicar!"
echo ""
print_warning "Próximos pasos:"

if [[ "$PLATFORM" == "android" || "$PLATFORM" == "both" ]]; then
    echo "  Android:"
    echo "  1. Ve a: https://play.google.com/console"
    echo "  2. Selecciona tu app"
    echo "  3. Producción → Nueva versión"
    echo "  4. Sube: $AAB_PATH"
    echo ""
fi

if [[ "$PLATFORM" == "ios" || "$PLATFORM" == "both" ]]; then
    echo "  iOS:"
    echo "  1. Abre Transporter app"
    echo "  2. Arrastra: $IPA_PATH"
    echo "  3. Click 'Deliver'"
    echo "  4. Ve a: https://appstoreconnect.apple.com"
    echo ""
fi

print_success "Para más detalles consulta:"
echo "  - PUBLICAR_PLAY_STORE.md (Android)"
echo "  - PUBLICAR_APP_STORE.md (iOS)"
