#!/bin/bash

# Script para generar iconos PWA desde el logo existente
# Requiere ImageMagick (sudo apt install imagemagick)

echo "🎨 Generando iconos PWA para QuickBite..."

# Verificar si ImageMagick está instalado
if ! command -v convert &> /dev/null; then
    echo "❌ ImageMagick no está instalado. Instálalo con:"
    echo "sudo apt update && sudo apt install imagemagick"
    exit 1
fi

# Crear directorio de iconos si no existe
mkdir -p assets/icons

# Logo fuente
SOURCE_LOGO="assets/img/logo.png"

# Verificar si el logo existe
if [ ! -f "$SOURCE_LOGO" ]; then
    echo "❌ No se encontró el logo en $SOURCE_LOGO"
    echo "Por favor, asegúrate de que el logo existe en esa ubicación."
    exit 1
fi

echo "📝 Usando logo fuente: $SOURCE_LOGO"

# Tamaños de iconos necesarios para PWA
declare -a sizes=("72" "96" "128" "144" "152" "192" "384" "512")

# Generar iconos en diferentes tamaños
for size in "${sizes[@]}"; do
    output_file="assets/icons/icon-${size}x${size}.png"
    echo "🔨 Generando icono ${size}x${size}..."
    
    convert "$SOURCE_LOGO" \
        -resize "${size}x${size}" \
        -background transparent \
        -gravity center \
        -extent "${size}x${size}" \
        "$output_file"
    
    if [ $? -eq 0 ]; then
        echo "✅ Generado: $output_file"
    else
        echo "❌ Error generando: $output_file"
    fi
done

# Crear favicon.ico
echo "🔨 Generando favicon.ico..."
convert "$SOURCE_LOGO" \
    -resize 32x32 \
    -background transparent \
    -gravity center \
    -extent 32x32 \
    "favicon.ico"

if [ $? -eq 0 ]; then
    echo "✅ Generado: favicon.ico"
else
    echo "❌ Error generando favicon.ico"
fi

# Crear Apple Touch Icon
echo "🔨 Generando Apple Touch Icon..."
convert "$SOURCE_LOGO" \
    -resize 180x180 \
    -background transparent \
    -gravity center \
    -extent 180x180 \
    "assets/icons/apple-touch-icon.png"

if [ $? -eq 0 ]; then
    echo "✅ Generado: assets/icons/apple-touch-icon.png"
else
    echo "❌ Error generando Apple Touch Icon"
fi

echo ""
echo "🎉 ¡Iconos PWA generados exitosamente!"
echo ""
echo "📁 Archivos creados:"
ls -la assets/icons/
echo ""
echo "💡 Consejo: Si quieres iconos más profesionales, considera:"
echo "   - Usar una herramienta como Figma o Photoshop"
echo "   - Crear iconos específicamente diseñados para cada tamaño"
echo "   - Agregar efectos de sombra o gradientes"