#!/bin/bash

echo "🚀 Configurando QuickBite Repartidor App..."

# Verificar que Flutter esté instalado
if ! command -v flutter &> /dev/null; then
    echo "❌ Flutter no está instalado"
    echo "👉 Instala Flutter siguiendo: INSTALACION_FLUTTER.md"
    exit 1
fi

echo "✅ Flutter encontrado: $(flutter --version | head -1)"

# Guardar archivos actuales
echo "💾 Guardando archivos actuales..."
if [ -d "quickbite_repartidor_backup" ]; then
    rm -rf quickbite_repartidor_backup
fi
mv quickbite_repartidor quickbite_repartidor_backup

# Crear proyecto Flutter
echo "📦 Creando proyecto Flutter..."
flutter create quickbite_repartidor --org com.quickbite

# Restaurar archivos personalizados
echo "📋 Copiando archivos personalizados..."
cp -r quickbite_repartidor_backup/lib/* quickbite_repartidor/lib/
cp quickbite_repartidor_backup/pubspec.yaml quickbite_repartidor/
cp -r quickbite_repartidor_backup/android/app/src/main/* quickbite_repartidor/android/app/src/main/ 2>/dev/null || true
cp quickbite_repartidor_backup/README.md quickbite_repartidor/

# Instalar dependencias
echo "📥 Instalando dependencias..."
cd quickbite_repartidor
flutter pub get

echo ""
echo "✅ ¡Proyecto configurado correctamente!"
echo ""
echo "📱 Próximos pasos:"
echo ""
echo "1. Obtén Google Maps API Key:"
echo "   https://console.cloud.google.com/"
echo ""
echo "2. Configura la API Key en:"
echo "   quickbite_repartidor/android/app/src/main/AndroidManifest.xml"
echo ""
echo "3. Inicia un emulador o conecta tu dispositivo"
echo ""
echo "4. Corre la app:"
echo "   cd quickbite_repartidor"
echo "   flutter run"
echo ""
echo "🎉 ¡Listo para despegar!"
