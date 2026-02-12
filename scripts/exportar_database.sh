#!/bin/bash

# Script para exportar la base de datos QuickBite
# Uso: ./scripts/exportar_database.sh

echo "🗄️  Exportando base de datos QuickBite..."

# Configuración
DB_NAME="app_delivery"
OUTPUT_FILE="quickbite_database_$(date +%Y%m%d_%H%M%S).sql"
SCHEMA_FILE="quickbite_schema_$(date +%Y%m%d_%H%M%S).sql"

# Pedir contraseña de MySQL
read -sp "Ingresa la contraseña de MySQL root: " MYSQL_PASS
echo ""

# Exportar estructura y datos completos
echo "📦 Exportando estructura y datos..."
mysqldump -u root -p"$MYSQL_PASS" "$DB_NAME" > "$OUTPUT_FILE"

if [ $? -eq 0 ]; then
    echo "✅ Base de datos completa exportada: $OUTPUT_FILE"
    echo "   Tamaño: $(du -h "$OUTPUT_FILE" | cut -f1)"
else
    echo "❌ Error al exportar base de datos"
    exit 1
fi

# Exportar solo la estructura (sin datos)
echo "📋 Exportando solo estructura..."
mysqldump -u root -p"$MYSQL_PASS" --no-data "$DB_NAME" > "$SCHEMA_FILE"

if [ $? -eq 0 ]; then
    echo "✅ Estructura exportada: $SCHEMA_FILE"
    echo "   Tamaño: $(du -h "$SCHEMA_FILE" | cut -f1)"
else
    echo "❌ Error al exportar estructura"
    exit 1
fi

# Crear archivo .env.example si no existe
if [ ! -f ".env.example" ]; then
    echo "📝 Creando archivo .env.example..."
    cat > .env.example << 'EOF'
# Base de datos
DB_HOST=localhost
DB_NAME=app_delivery
DB_USER=quickbite
DB_PASS=tu_password_aqui

# Ambiente
ENVIRONMENT=development

# API URL (cambia según tu configuración)
API_URL=http://localhost/api

# MercadoPago (opcional)
MP_ACCESS_TOKEN=
MP_PUBLIC_KEY=

# WhatsApp (opcional)
WHATSAPP_ENABLED=false
EOF
    echo "✅ Archivo .env.example creado"
fi

echo ""
echo "🎉 Exportación completada!"
echo ""
echo "📤 Comparte estos archivos con tu equipo:"
echo "   1. $OUTPUT_FILE (base de datos completa)"
echo "   2. .env.example (configuración)"
echo "   3. README_DESARROLLO.md (guía de desarrollo)"
echo ""
echo "💡 Para importar en otra computadora:"
echo "   mysql -u root -p -e \"CREATE DATABASE app_delivery;\""
echo "   mysql -u root -p app_delivery < $OUTPUT_FILE"
