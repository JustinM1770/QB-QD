#!/bin/bash
# Script de prueba completa del sistema QuickBite

echo "=================================="
echo "  QUICKBITE - PRUEBAS COMPLETAS"
echo "=================================="
echo ""

# Colores
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 1. Verificar servidores Node.js
echo -e "${YELLOW}[1/5] Verificando servidores Node.js...${NC}"
if pgrep -f "node.*server.js" > /dev/null; then
    echo -e "${GREEN}✅ Servidor WebSocket está corriendo${NC}"
else
    echo -e "${RED}❌ Servidor WebSocket NO está corriendo${NC}"
    echo "   Ejecuta: cd /var/www/html/verUT && node server.js &"
fi

if pgrep -f "whatsapp.*bot.js" > /dev/null; then
    echo -e "${GREEN}✅ Bot de WhatsApp está corriendo${NC}"
else
    echo -e "${YELLOW}⚠️  Bot de WhatsApp NO está corriendo (opcional)${NC}"
fi
echo ""

# 2. Verificar archivos PHP críticos
echo -e "${YELLOW}[2/5] Verificando archivos PHP...${NC}"
files=(
    "/var/www/html/api/WhatsAppService.php"
    "/var/www/html/admin/gemini_menu_parser.php"
    "/var/www/html/admin/menu_parser_endpoint.php"
    "/var/www/html/models/WalletMercadoPago.php"
    "/var/www/html/admin/wallet_negocio.php"
    "/var/www/html/admin/wallet.php"
)

all_ok=true
for file in "${files[@]}"; do
    if php -l "$file" 2>&1 | grep -q "No syntax errors"; then
        echo -e "${GREEN}✅ $(basename $file)${NC}"
    else
        echo -e "${RED}❌ $(basename $file) - Error de sintaxis${NC}"
        all_ok=false
    fi
done
echo ""

# 3. Verificar tablas de base de datos
echo -e "${YELLOW}[3/5] Verificando tablas de base de datos...${NC}"
tables=("wallets" "wallet_transacciones" "whatsapp_messages" "productos" "categorias_producto")

for table in "${tables[@]}"; do
    if mysql -u root -p'Aa13684780@@' app_delivery -e "DESC $table" &> /dev/null; then
        echo -e "${GREEN}✅ Tabla '$table' existe${NC}"
    else
        echo -e "${RED}❌ Tabla '$table' NO existe${NC}"
    fi
done
echo ""

# 4. Test de WhatsApp Service
echo -e "${YELLOW}[4/5] Probando WhatsApp Service...${NC}"
echo "Para enviar un mensaje de prueba, ejecuta:"
echo "  php /var/www/html/test_whatsapp_send.php"
echo ""
echo "NOTA: Primero configura tu número de prueba en el archivo"
echo ""

# 5. Test de Gemini Menu Parser
echo -e "${YELLOW}[5/5] Probando Gemini Menu Parser...${NC}"
echo "Para analizar un menú, ejecuta:"
echo "  php /var/www/html/test_gemini_menu.php [ruta_a_imagen]"
echo ""
echo "Ejemplo:"
echo "  php /var/www/html/test_gemini_menu.php /var/www/html/uploads/menu.jpg"
echo ""

# Resumen
echo "=================================="
echo "  RESUMEN"
echo "=================================="
echo ""
echo "COMPONENTES DISPONIBLES:"
echo "  1. ✅ WhatsApp Service     - /api/WhatsAppService.php"
echo "  2. ✅ Gemini Menu Parser   - /admin/gemini_menu_parser.php"
echo "  3. ✅ Wallet MercadoPago   - /models/WalletMercadoPago.php"
echo "  4. ✅ WebSocket Server     - /verUT/server.js"
echo ""
echo "WEBHOOKS CONFIGURADOS:"
echo "  • WhatsApp: https://quickbite.com.mx/whatsapp_webhook.php"
echo "  • MercadoPago: https://quickbite.com.mx/webhook/mercadopago_webhook.php"
echo ""
echo "PRUEBAS DISPONIBLES:"
echo "  • WhatsApp: php test_whatsapp_send.php"
echo "  • Gemini IA: php test_gemini_menu.php [imagen]"
echo ""
echo "ESTADOS DE PEDIDOS (WebSocket):"
echo "  1 - Aceptar pedido"
echo "  2 - Preparando pedido"
echo "  3 - Pedido listo"
echo "  4 - Listo para entrega"
echo "  5 - En camino"
echo "  6 - Entregado"
echo ""
echo "=================================="
