#!/bin/bash

# Script de Verificación Rápida - QuickBite
# Verifica que todas las correcciones estén implementadas

echo "🔍 Verificando Correcciones de QuickBite..."
echo "=========================================="
echo ""

# Colores
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Función para verificar un archivo
check_file() {
    if [ -f "$1" ]; then
        echo -e "${GREEN}✅${NC} $1 existe"
        return 0
    else
        echo -e "${RED}❌${NC} $1 NO encontrado"
        return 1
    fi
}

# Función para verificar contenido
check_content() {
    if grep -q "$2" "$1"; then
        echo -e "${GREEN}✅${NC} $3"
        return 0
    else
        echo -e "${RED}❌${NC} $3 NO encontrado"
        return 1
    fi
}

echo "1️⃣  Verificando archivos principales..."
echo "----------------------------------------"
check_file "/var/www/html/index.php"
check_file "/var/www/html/checkout.php"
check_file "/var/www/html/admin/gemini_menu_parser.php"
check_file "/var/www/html/includes/business_helpers.php"
echo ""

echo "2️⃣  Verificando archivos de prueba..."
echo "----------------------------------------"
check_file "/var/www/html/_testing_files/test_google_maps_api.php"
check_file "/var/www/html/_testing_files/test_gemini_ai.php"
check_file "/var/www/html/_testing_files/test_gemini_backend.php"
check_file "/var/www/html/SOLUCION_PROBLEMAS_CRITICOS.md"
echo ""

echo "3️⃣  Verificando correcciones específicas..."
echo "----------------------------------------"

# Verificar corrección de ubicación (index.php)
if [ -f "/var/www/html/index.php" ]; then
    check_content "/var/www/html/index.php" "direccionCompleta: 'Ubicación aproximada'" "Fallback de ubicación en index.php"
    check_content "/var/www/html/index.php" "Error al obtener ubicación" "Mensaje de error de ubicación"
fi

# Verificar corrección de efectivo (checkout.php)
if [ -f "/var/www/html/checkout.php" ]; then
    check_content "/var/www/html/checkout.php" 'id="payment-methods"' "Payment methods container"
    if ! grep -q 'id="payment-methods" style="display: none;"' "/var/www/html/checkout.php"; then
        echo -e "${GREEN}✅${NC} Payment methods NO ocultos por defecto (correcto)"
    else
        echo -e "${RED}❌${NC} Payment methods siguen ocultos (revisar línea 2920)"
    fi
    check_content "/var/www/html/checkout.php" "selectedPaymentMethod = paymentType" "Asignación de payment method"
fi

# Verificar corrección de Gemini (gemini_menu_parser.php)
if [ -f "/var/www/html/admin/gemini_menu_parser.php" ]; then
    check_content "/var/www/html/admin/gemini_menu_parser.php" "getenv('AI_API_KEY')" "Variable de entorno AI_API_KEY"
fi

echo ""
echo "4️⃣  Verificando sintaxis PHP..."
echo "----------------------------------------"

# Verificar sintaxis de archivos PHP críticos
for file in "/var/www/html/index.php" "/var/www/html/checkout.php" "/var/www/html/admin/gemini_menu_parser.php"; do
    if [ -f "$file" ]; then
        if php -l "$file" > /dev/null 2>&1; then
            echo -e "${GREEN}✅${NC} Sintaxis correcta: $(basename $file)"
        else
            echo -e "${RED}❌${NC} Error de sintaxis en: $(basename $file)"
            php -l "$file"
        fi
    fi
done

echo ""
echo "5️⃣  Verificando permisos..."
echo "----------------------------------------"

# Verificar permisos de archivos
for file in "/var/www/html/index.php" "/var/www/html/checkout.php"; do
    if [ -f "$file" ]; then
        perms=$(stat -c "%a" "$file")
        if [ "$perms" = "644" ] || [ "$perms" = "664" ] || [ "$perms" = "755" ]; then
            echo -e "${GREEN}✅${NC} Permisos correctos ($perms): $(basename $file)"
        else
            echo -e "${YELLOW}⚠️${NC}  Permisos inusuales ($perms): $(basename $file)"
        fi
    fi
done

echo ""
echo "6️⃣  URLs de Prueba..."
echo "----------------------------------------"
DOMAIN=$(basename $(pwd))
echo -e "${YELLOW}🌐${NC} Abre estos URLs en tu navegador:"
echo ""
echo "   📍 Test Google Maps API:"
echo "   http://tu-dominio.com/_testing_files/test_google_maps_api.php"
echo ""
echo "   🤖 Test Gemini AI:"
echo "   http://tu-dominio.com/_testing_files/test_gemini_ai.php"
echo ""
echo "   📄 Documentación de soluciones:"
echo "   http://tu-dominio.com/SOLUCION_PROBLEMAS_CRITICOS.md"
echo ""

echo "=========================================="
echo "✅ Verificación completada"
echo ""
echo "📝 Próximos pasos:"
echo "   1. Abrir los URLs de prueba en navegador"
echo "   2. Verificar que Google Maps API funcione"
echo "   3. Verificar que Gemini AI funcione"
echo "   4. Probar checkout con efectivo"
echo "   5. Revisar SOLUCION_PROBLEMAS_CRITICOS.md para detalles"
echo ""
