#!/bin/bash

# Script rápido de verificación del sistema de abandono

echo "=== QuickBite - Verificación Sistema de Abandono ==="
echo ""

# 1. Verificar cron
echo "1. Estado del Cron Job:"
if crontab -u www-data -l 2>/dev/null | grep -q "abandonar_pedidos_atrasados.php"; then
    echo "   ✓ Cron configurado correctamente"
    crontab -u www-data -l | grep "abandonar_pedidos_atrasados.php"
else
    echo "   ✗ Cron NO configurado"
fi

echo ""

# 2. Verificar tabla reembolsos
echo "2. Tabla de Reembolsos:"
resultado=$(mysql -u root -e "USE quickbite; SHOW TABLES LIKE 'reembolsos';" 2>/dev/null | grep -c "reembolsos")
if [ "$resultado" -eq 1 ]; then
    echo "   ✓ Tabla existe"
    total=$(mysql -u root -e "USE quickbite; SELECT COUNT(*) FROM reembolsos;" 2>/dev/null | tail -1)
    echo "   → Total reembolsos registrados: $total"
else
    echo "   ✗ Tabla NO existe"
fi

echo ""

# 3. Verificar logs
echo "3. Logs del Sistema:"
if [ -f "/var/www/html/logs/cron_abandono.log" ]; then
    echo "   ✓ Archivo de logs existe"
    tamaño=$(du -h /var/www/html/logs/cron_abandono.log | cut -f1)
    echo "   → Tamaño: $tamaño"
    echo "   → Últimas 3 líneas:"
    tail -3 /var/www/html/logs/cron_abandono.log | sed 's/^/     /'
else
    echo "   ⚠ Archivo de logs NO existe (se creará en primera ejecución)"
fi

echo ""

# 4. Verificar permisos
echo "4. Permisos de Archivos:"
if [ -x "/var/www/html/cron/abandonar_pedidos_atrasados.php" ]; then
    echo "   ✓ Script tiene permisos de ejecución"
else
    echo "   ✗ Script NO tiene permisos de ejecución"
fi

echo ""

# 5. Estadísticas de pedidos abandonados
echo "5. Estadísticas (últimos 7 días):"
abandonados=$(mysql -u root -e "USE quickbite; SELECT COUNT(*) FROM pedidos WHERE id_estado = 8 AND fecha_actualizacion >= DATE_SUB(NOW(), INTERVAL 7 DAY);" 2>/dev/null | tail -1)
echo "   → Pedidos abandonados: $abandonados"

reembolsos_pendientes=$(mysql -u root -e "USE quickbite; SELECT COUNT(*) FROM reembolsos WHERE estado = 'pendiente';" 2>/dev/null | tail -1)
echo "   → Reembolsos pendientes: $reembolsos_pendientes"

echo ""
echo "=== Fin de Verificación ==="
echo ""
echo "Para más información ver: docs/SISTEMA_ABANDONO.md"
echo "Panel admin: https://tudominio.com/admin/reembolsos.php"
