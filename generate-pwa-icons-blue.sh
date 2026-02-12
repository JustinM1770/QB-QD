#!/bin/bash

# Script para generar iconos PWA con fondo AZUL #0165FF
# Requiere ImageMagick (sudo apt install imagemagick)

echo "🎨 Generando iconos PWA para QuickBite con fondo AZUL..."

# Verificar si ImageMagick está instalado
if ! command -v convert &> /dev/null; then
    echo "❌ ImageMagick no está instalado. Instálalo con:"
    echo "sudo apt update && sudo apt install imagemagick"
    exit 1
fi

# Crear directorio de iconos si no existe
mkdir -p assets/icons

# Color azul del logo QuickBite
BLUE_COLOR="#0165FF"
YELLOW_COLOR="#FFD700"

echo "📝 Generando iconos con fondo azul $BLUE_COLOR..."

# Tamaños de iconos necesarios para PWA
declare -a sizes=("72" "96" "128" "144" "152" "192" "384" "512")

# Generar iconos en diferentes tamaños con fondo azul y texto "QB"
for size in "${sizes[@]}"; do
    output_file="assets/icons/icon-${size}x${size}.png"
    echo "🔨 Generando icono ${size}x${size} con fondo azul..."
    
    # Calcular tamaño de fuente proporcional
    font_size=$((size / 2))
    
    # Crear icono con fondo azul y texto "QB" en blanco
    convert -size "${size}x${size}" \
        xc:"$BLUE_COLOR" \
        -fill white \
        -font "DejaVu-Sans-Bold" \
        -pointsize "$font_size" \
        -gravity center \
        -annotate +0+0 "QB" \
        "$output_file"
    
    if [ $? -eq 0 ]; then
        echo "✅ Generado: $output_file"
    else
        echo "❌ Error generando: $output_file"
    fi
done

# Crear favicon.ico con fondo azul
echo "🔨 Generando favicon.ico con fondo azul..."
convert -size 32x32 \
    xc:"$BLUE_COLOR" \
    -fill white \
    -font "DejaVu-Sans-Bold" \
    -pointsize 16 \
    -gravity center \
    -annotate +0+0 "QB" \
    "favicon.ico"

if [ $? -eq 0 ]; then
    echo "✅ Generado: favicon.ico"
else
    echo "❌ Error generando favicon.ico"
fi

# Crear Apple Touch Icon con fondo azul
echo "🔨 Generando Apple Touch Icon con fondo azul..."
convert -size 180x180 \
    xc:"$BLUE_COLOR" \
    -fill white \
    -font "DejaVu-Sans-Bold" \
    -pointsize 90 \
    -gravity center \
    -annotate +0+0 "QB" \
    "assets/icons/apple-touch-icon.png"

if [ $? -eq 0 ]; then
    echo "✅ Generado: assets/icons/apple-touch-icon.png"
else
    echo "❌ Error generando Apple Touch Icon"
fi

echo ""
echo "🎉 ¡Iconos PWA con fondo AZUL generados exitosamente!"
echo ""
echo "📁 Archivos creados:"
ls -lah assets/icons/icon-*.png
echo ""
echo "🔵 Color de fondo: $BLUE_COLOR (Azul QuickBite)"
echo "⚪ Texto: Blanco con 'QB'"
echo ""
echo "💡 Los iconos ahora tienen el fondo azul del logo QuickBite"
