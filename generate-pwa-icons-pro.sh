#!/bin/bash

# Script para generar iconos PWA profesionales con Q blanca y B amarilla
# Requiere ImageMagick

echo "🎨 Generando iconos PWA PRO para QuickBite..."

# Verificar si ImageMagick está instalado
if ! command -v convert &> /dev/null; then
    echo "❌ ImageMagick no está instalado."
    exit 1
fi

# Crear directorio de iconos si no existe
mkdir -p assets/icons

# Colores oficiales
BLUE_COLOR="#0165FF"
YELLOW_COLOR="#FFD700"

echo "📝 Generando iconos con Q blanca y B amarilla..."

# Función para generar un icono con Q y B separadas
generate_icon() {
    local size=$1
    local output_file=$2
    local font_size=$((size / 2))
    local q_offset=$((size / 8))
    local b_offset=$((size / 8))
    
    # Crear fondo azul con gradiente sutil
    convert -size "${size}x${size}" \
        gradient:"#0165FF-#0150dd" \
        -gravity center \
        -swirl 10 \
        \( +clone -blur 0x3 \) \
        -compose multiply -composite \
        "$output_file"
    
    # Agregar brillo sutil
    convert "$output_file" \
        \( -size "${size}x${size}" radial-gradient:white-transparent \
           -evaluate multiply 0.15 \) \
        -compose lighten -composite \
        "$output_file"
    
    # Agregar Q en blanco
    convert "$output_file" \
        -fill white \
        -font "Arial-Bold" \
        -pointsize "$font_size" \
        -gravity west \
        -annotate "+${q_offset}+0" "Q" \
        "$output_file"
    
    # Agregar B en amarillo
    convert "$output_file" \
        -fill "$YELLOW_COLOR" \
        -font "Arial-Bold" \
        -pointsize "$font_size" \
        -gravity east \
        -annotate "+${b_offset}+0" "B" \
        "$output_file"
    
    # Agregar sombra suave al texto
    convert "$output_file" \
        \( +clone -background black -shadow 80x2+0+2 \) \
        +swap -background none -layers merge +repage \
        "$output_file"
}

# Tamaños de iconos necesarios para PWA
declare -a sizes=("72" "96" "128" "144" "152" "192" "384" "512")

# Generar iconos en diferentes tamaños
for size in "${sizes[@]}"; do
    output_file="assets/icons/icon-${size}x${size}.png"
    echo "🔨 Generando icono ${size}x${size} PRO..."
    
    # Calcular tamaño de fuente proporcional
    font_size=$((size * 45 / 100))
    
    # Crear fondo azul con gradiente
    convert -size "${size}x${size}" \
        -define gradient:angle=135 \
        gradient:"#0165FF-#0150dd" \
        "$output_file"
    
    # Agregar efecto de brillo radial sutil
    convert "$output_file" \
        \( -size "${size}x${size}" \
           radial-gradient:"rgba(255,255,255,0.2)-rgba(255,255,255,0)" \
           -gravity northwest -extent "${size}x${size}" \) \
        -compose lighten -composite \
        "$output_file"
    
    # Agregar texto "QB" centrado con colores
    # Primero calculamos posiciones para Q y B
    offset=$((font_size / 4))
    
    # Crear texto compuesto con Q blanca y B amarilla
    convert "$output_file" \
        -font "Arial-Bold" \
        -pointsize "$font_size" \
        -fill white \
        -gravity center \
        -annotate "-${offset}+0" "Q" \
        -fill "$YELLOW_COLOR" \
        -annotate "+${offset}+0" "B" \
        "$output_file"
    
    # Agregar sombra suave
    convert "$output_file" \
        \( +clone -background "rgba(0,0,0,0.3)" -shadow 60x3+0+2 \) \
        +swap -background none -layers merge +repage \
        "$output_file"
    
    if [ $? -eq 0 ]; then
        echo "✅ Generado: $output_file"
    else
        echo "❌ Error generando: $output_file"
    fi
done

# Crear favicon.ico
echo "🔨 Generando favicon.ico PRO..."
font_size=18
offset=2

convert -size 32x32 \
    -define gradient:angle=135 \
    gradient:"#0165FF-#0150dd" \
    \( -size 32x32 radial-gradient:"rgba(255,255,255,0.2)-rgba(255,255,255,0)" \) \
    -compose lighten -composite \
    -font "Arial-Bold" \
    -pointsize "$font_size" \
    -fill white \
    -gravity center \
    -annotate "-${offset}+0" "Q" \
    -fill "$YELLOW_COLOR" \
    -annotate "+${offset}+0" "B" \
    "favicon.ico"

echo "✅ Generado: favicon.ico"

# Crear Apple Touch Icon
echo "🔨 Generando Apple Touch Icon PRO..."
font_size=90
offset=18

convert -size 180x180 \
    -define gradient:angle=135 \
    gradient:"#0165FF-#0150dd" \
    \( -size 180x180 radial-gradient:"rgba(255,255,255,0.2)-rgba(255,255,255,0)" \) \
    -compose lighten -composite \
    -font "Arial-Bold" \
    -pointsize "$font_size" \
    -fill white \
    -gravity center \
    -annotate "-${offset}+0" "Q" \
    -fill "$YELLOW_COLOR" \
    -annotate "+${offset}+0" "B" \
    \( +clone -background "rgba(0,0,0,0.3)" -shadow 60x3+0+2 \) \
    +swap -background none -layers merge +repage \
    "assets/icons/apple-touch-icon.png"

echo "✅ Generado: assets/icons/apple-touch-icon.png"

echo ""
echo "🎉 ¡Iconos PWA PRO generados exitosamente!"
echo ""
echo "📁 Archivos creados:"
ls -lah assets/icons/icon-*.png
echo ""
echo "🔵 Fondo: Gradiente azul $BLUE_COLOR con efecto de luz"
echo "⚪ Q: Blanca"
echo "🟡 B: Amarilla ($YELLOW_COLOR)"
echo "✨ Efectos: Gradiente + brillo radial + sombra suave"
