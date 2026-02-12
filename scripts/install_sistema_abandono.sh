#!/bin/bash

# ============================================
# Script de instalación: Sistema de Abandono Automático
# ============================================

echo "==================================================="
echo "QuickBite - Sistema de Abandono Automático"
echo "==================================================="
echo ""

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Verificar permisos
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}ERROR: Este script debe ejecutarse como root${NC}"
    echo "Ejecuta: sudo bash $0"
    exit 1
fi

echo -e "${GREEN}✓${NC} Verificación de permisos OK"

# 1. Aplicar migraciones SQL
echo ""
echo "1. Aplicando migraciones SQL..."
echo "   Por favor, ingresa la contraseña de MySQL root:"

mysql -u root -p quickbite < /var/www/html/migrations/create_reembolsos_table.sql

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} Migraciones aplicadas correctamente"
else
    echo -e "${RED}✗${NC} Error al aplicar migraciones"
    echo "   Verifica que la base de datos 'quickbite' existe"
    exit 1
fi

# 2. Crear directorio de logs si no existe
echo ""
echo "2. Configurando directorios..."
mkdir -p /var/www/html/logs
chown www-data:www-data /var/www/html/logs
chmod 775 /var/www/html/logs
echo -e "${GREEN}✓${NC} Directorio de logs configurado"

# 3. Dar permisos al script cron
echo ""
echo "3. Configurando permisos..."
chmod +x /var/www/html/cron/abandonar_pedidos_atrasados.php
chown www-data:www-data /var/www/html/cron/abandonar_pedidos_atrasados.php
echo -e "${GREEN}✓${NC} Permisos configurados"

# 4. Instalar crontab
echo ""
echo "4. Configurando crontab..."

# Verificar si ya existe la entrada
if crontab -u www-data -l 2>/dev/null | grep -q "abandonar_pedidos_atrasados.php"; then
    echo -e "${YELLOW}⚠${NC}  La entrada cron ya existe"
else
    # Agregar nueva entrada
    (crontab -u www-data -l 2>/dev/null; echo "*/5 * * * * /usr/bin/php /var/www/html/cron/abandonar_pedidos_atrasados.php >> /var/www/html/logs/cron_abandono.log 2>&1") | crontab -u www-data -
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓${NC} Crontab configurado (se ejecuta cada 5 minutos)"
    else
        echo -e "${RED}✗${NC} Error al configurar crontab"
        exit 1
    fi
fi

# 5. Verificar dependencias PHP
echo ""
echo "5. Verificando dependencias PHP..."

# Verificar si MercadoPago SDK está instalado
if [ -f "/var/www/html/vendor/autoload.php" ]; then
    echo -e "${GREEN}✓${NC} Composer autoload encontrado"
else
    echo -e "${YELLOW}⚠${NC}  Composer no encontrado o vendor no existe"
    echo "   Instalando dependencias..."
    cd /var/www/html
    
    if [ -f "composer.json" ]; then
        composer install --no-dev --optimize-autoloader
        echo -e "${GREEN}✓${NC} Dependencias instaladas"
    else
        echo -e "${RED}✗${NC} composer.json no encontrado"
        echo "   Asegúrate de tener MercadoPago SDK instalado para reembolsos automáticos"
    fi
fi

# 6. Probar ejecución del script
echo ""
echo "6. Probando script de abandono..."
echo "   (Esto puede tardar unos segundos...)"

sudo -u www-data /usr/bin/php /var/www/html/cron/abandonar_pedidos_atrasados.php > /tmp/test_abandono.log 2>&1

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} Script ejecutado correctamente"
    echo ""
    echo "   Resultado de la prueba:"
    tail -n 10 /tmp/test_abandono.log | sed 's/^/   /'
else
    echo -e "${RED}✗${NC} Error al ejecutar el script"
    echo "   Ver detalles en: /tmp/test_abandono.log"
    cat /tmp/test_abandono.log
    exit 1
fi

# 7. Crear archivo de configuración
echo ""
echo "7. Creando archivo de configuración..."

cat > /var/www/html/config/abandono_config.php << 'EOF'
<?php
/**
 * Configuración del Sistema de Abandono Automático
 */

// Tiempos límite (en minutos)
define('TIMEOUT_ENTREGA', 60);      // Tiempo máximo para entregar después de recoger
define('TIMEOUT_RECOGIDA', 30);     // Tiempo máximo para recoger pedido listo
define('TIMEOUT_EN_CAMINO', 45);    // Tiempo máximo en estado "en camino"

// Reembolsos
define('REEMBOLSO_AUTOMATICO', true);  // Habilitar reembolsos automáticos
define('NOTIFICAR_USUARIO', true);      // Enviar notificaciones al usuario

// Penalizaciones
define('PENALIZAR_REPARTIDOR', true);   // Aplicar penalización al repartidor
define('DECREMENTO_CALIFICACION', 0.5); // Puntos a descontar en calificación

// Límites de abandono
define('MAX_ABANDONOS_PERMITIDOS', 3);  // Abandonos antes de suspender repartidor
define('PERIODO_EVALUACION_DIAS', 7);   // Días para evaluar tasa de abandono
EOF

chmod 644 /var/www/html/config/abandono_config.php
chown www-data:www-data /var/www/html/config/abandono_config.php
echo -e "${GREEN}✓${NC} Archivo de configuración creado"

# 8. Resumen final
echo ""
echo "==================================================="
echo -e "${GREEN}✓ INSTALACIÓN COMPLETADA${NC}"
echo "==================================================="
echo ""
echo "Configuración aplicada:"
echo "  • Tabla de reembolsos creada"
echo "  • Cron configurado (cada 5 minutos)"
echo "  • Logs en: /var/www/html/logs/cron_abandono.log"
echo "  • Panel admin: /admin/reembolsos.php"
echo ""
echo "Tiempos configurados:"
echo "  • Entrega: 60 minutos"
echo "  • Recogida: 30 minutos"
echo "  • En camino: 45 minutos"
echo ""
echo "Próximos pasos:"
echo "  1. Verificar variables de entorno (MercadoPago)"
echo "  2. Monitorear logs: tail -f /var/www/html/logs/cron_abandono.log"
echo "  3. Revisar reembolsos en: https://tudominio.com/admin/reembolsos.php"
echo ""
echo "Para ejecutar manualmente:"
echo "  sudo -u www-data /usr/bin/php /var/www/html/cron/abandonar_pedidos_atrasados.php"
echo ""
echo "==================================================="
