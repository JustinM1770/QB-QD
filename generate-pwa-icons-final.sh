#!/bin/bash
# Colores oficiales QuickBite
BLUE="#0165FF"
WHITE="#FFFFFF"
YELLOW="#FFD700"

echo "🎨 Generando iconos QuickBite optimizados..."

generate_icon() {
    local size=$1
    local output=$2
    # Ajuste de escala: Fuente más pequeña para dejar espacio (40% del tamaño)
    local fontsize=$((size * 40 / 100))
    # Desplazamiento horizontal para separación entre letras (10%)
    local offset=$((size * 10 / 100))

    # Crear fondo y dibujar letras con desplazamiento
    convert -size ${size}x${size} xc:"$BLUE" \
        -gravity center \
        -font DejaVu-Sans-Bold -pointsize $fontsize \
        -fill "$WHITE" -annotate -${offset}+0 "Q" \
        -fill "$YELLOW" -annotate +${offset}+0 "B" \
        PNG32:$output
    
    echo "✅ $output"
}

# Generar todos los tamaños necesarios para PWA
generate_icon 72 "assets/icons/icon-72x72.png"
generate_icon 96 "assets/icons/icon-96x96.png"
generate_icon 128 "assets/icons/icon-128x128.png"
generate_icon 144 "assets/icons/icon-144x144.png"
generate_icon 152 "assets/icons/icon-152x152.png"
generate_icon 192 "assets/icons/icon-192x192.png"
generate_icon 384 "assets/icons/icon-384x384.png"
generate_icon 512 "assets/icons/icon-512x512.png"

# Favicon (32x32)
convert -size 32x32 xc:"$BLUE" \
    -gravity center \
    -font DejaVu-Sans-Bold -pointsize 13 \
    -fill "$WHITE" -annotate -3+0 "Q" \
    -fill "$YELLOW" -annotate +3+0 "B" \
    favicon.ico
echo "✅ favicon.ico"

# Apple Touch Icon (180x180)
convert -size 180x180 xc:"$BLUE" \
    -gravity center \
    -font DejaVu-Sans-Bold -pointsize 72 \
    -fill "$WHITE" -annotate -18+0 "Q" \
    -fill "$YELLOW" -annotate +18+0 "B" \
    assets/icons/apple-touch-icon.png
echo "✅ apple-touch-icon.png"

echo ""
echo "🎉 ¡Iconos optimizados generados!"
echo "🔵 Fondo: $BLUE (Azul QuickBite)"
echo "⚪ Q: $WHITE (Blanca)"
echo "🟡 B: $YELLOW (Amarilla)"
echo "✨ Fuente al 40% con más espacio alrededor"
