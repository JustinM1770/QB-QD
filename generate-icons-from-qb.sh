#!/bin/bash

echo "🎨 Generando iconos PWA desde QB.jpeg..."

SOURCE="/var/www/html/assets/icons/QB.jpeg"

if [ ! -f "$SOURCE" ]; then
    echo "❌ No se encontró QB.jpeg"
    exit 1
fi

generate_icon() {
    local size=$1
    local output=$2
    
    convert "$SOURCE" \
        -resize "${size}x${size}^" \
        -gravity center \
        -extent "${size}x${size}" \
        PNG32:"$output"
    
    echo "✅ $output (${size}x${size})"
}

# Generar iconos PWA
generate_icon 72 "assets/icons/icon-72x72.png"
generate_icon 96 "assets/icons/icon-96x96.png"
generate_icon 128 "assets/icons/icon-128x128.png"
generate_icon 144 "assets/icons/icon-144x144.png"
generate_icon 152 "assets/icons/icon-152x152.png"
generate_icon 192 "assets/icons/icon-192x192.png"
generate_icon 384 "assets/icons/icon-384x384.png"
generate_icon 512 "assets/icons/icon-512x512.png"

# Favicon
convert "$SOURCE" \
    -resize 32x32^ \
    -gravity center \
    -extent 32x32 \
    favicon.ico
echo "✅ favicon.ico (32x32)"

# Apple Touch Icon
convert "$SOURCE" \
    -resize 180x180^ \
    -gravity center \
    -extent 180x180 \
    assets/icons/apple-touch-icon.png
echo "✅ apple-touch-icon.png (180x180)"

echo ""
echo "🎉 ¡Iconos generados desde QB.jpeg!"
ls -lh assets/icons/icon-*.png
