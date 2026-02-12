#!/bin/bash

echo "🎨 Generando iconos QuickBite (Q blanca + B amarilla)..."

# Colores
BLUE="#0165FF"
WHITE="#FFFFFF"
YELLOW="#FFD700"

# Función para generar icono
generate_icon() {
    local size=$1
    local output=$2
    local fontsize=$((size * 50 / 100))
    local q_x=$((size * 30 / 100))
    local b_x=$((size * 60 / 100))
    local y=$((size / 2))
    
    # Crear imagen con fondo azul
    convert -size ${size}x${size} xc:"$BLUE" \
        -gravity center \
        -font DejaVu-Sans-Bold -pointsize $fontsize \
        -fill "$WHITE" -annotate +0+0 "Q" \
        PNG32:$output
    
    # Agregar B amarilla encima
    convert $output \
        -gravity center \
        -font DejaVu-Sans-Bold -pointsize $fontsize \
        -fill "$YELLOW" -annotate +$((fontsize/3))+0 "B" \
        PNG32:$output
    
    echo "✅ $output"
}

# Generar todos los tamaños
generate_icon 72 "assets/icons/icon-72x72.png"
generate_icon 96 "assets/icons/icon-96x96.png"
generate_icon 128 "assets/icons/icon-128x128.png"
generate_icon 144 "assets/icons/icon-144x144.png"
generate_icon 152 "assets/icons/icon-152x152.png"
generate_icon 192 "assets/icons/icon-192x192.png"
generate_icon 384 "assets/icons/icon-384x384.png"
generate_icon 512 "assets/icons/icon-512x512.png"

# Favicon
convert -size 32x32 xc:"$BLUE" \
    -gravity center \
    -font DejaVu-Sans-Bold -pointsize 18 \
    -fill "$WHITE" -annotate +0+0 "Q" \
    -fill "$YELLOW" -annotate +6+0 "B" \
    favicon.ico
echo "✅ favicon.ico"

# Apple touch
convert -size 180x180 xc:"$BLUE" \
    -gravity center \
    -font DejaVu-Sans-Bold -pointsize 90 \
    -fill "$WHITE" -annotate +0+0 "Q" \
    -fill "$YELLOW" -annotate +30+0 "B" \
    assets/icons/apple-touch-icon.png
echo "✅ apple-touch-icon.png"

echo ""
echo "🎉 ¡Listo!"
echo "🔵 Fondo azul: $BLUE"
echo "⚪ Q blanca: $WHITE"
echo "🟡 B amarilla: $YELLOW"
