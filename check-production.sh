#!/bin/bash
echo "🔍 Verificando configuración de producción QuickBite..."
echo ""

checks_passed=0
checks_failed=0

# 1. .htaccess
if [ -f /var/www/html/.htaccess ]; then
    echo "✅ .htaccess existe"
    ((checks_passed++))
else
    echo "❌ .htaccess NO existe"
    ((checks_failed++))
fi

# 2. .env
if [ -f /var/www/html/.env ]; then
    echo "✅ .env existe"
    ((checks_passed++))
    perms=$(stat -c %a /var/www/html/.env)
    if [ "$perms" == "600" ]; then
        echo "✅ .env tiene permisos correctos (600)"
        ((checks_passed++))
    else
        echo "⚠️  .env permisos: $perms (recomendado: 600)"
        ((checks_failed++))
    fi
else
    echo "❌ .env NO existe"
    ((checks_failed++))
fi

# 3. error_handler.php
if [ -f /var/www/html/config/error_handler.php ]; then
    echo "✅ error_handler.php existe"
    ((checks_passed++))
else
    echo "❌ error_handler.php NO existe"
    ((checks_failed++))
fi

# 4. Logs directory
if [ -d /var/www/html/logs ]; then
    echo "✅ Directorio logs/ existe"
    ((checks_passed++))
    if [ -w /var/www/html/logs ]; then
        echo "✅ Directorio logs/ es escribible"
        ((checks_passed++))
    else
        echo "⚠️  logs/ no es escribible"
        ((checks_failed++))
    fi
else
    echo "⚠️  Directorio logs/ NO existe (creando...)"
    mkdir -p /var/www/html/logs
    chmod 755 /var/www/html/logs
fi

# 5. robots.txt
if [ -f /var/www/html/robots.txt ]; then
    echo "✅ robots.txt existe"
    ((checks_passed++))
else
    echo "⚠️  robots.txt NO existe"
    ((checks_failed++))
fi

# 6. sitemap.xml
if [ -f /var/www/html/sitemap.xml ]; then
    echo "✅ sitemap.xml existe"
    ((checks_passed++))
else
    echo "⚠️  sitemap.xml NO existe"
    ((checks_failed++))
fi

# 7. Iconos PWA
icon_count=$(ls /var/www/html/assets/icons/icon-*.png 2>/dev/null | wc -l)
if [ $icon_count -ge 8 ]; then
    echo "✅ Iconos PWA completos ($icon_count iconos)"
    ((checks_passed++))
else
    echo "⚠️  Iconos PWA: $icon_count de 8"
    ((checks_failed++))
fi

# 8. WhatsApp bot
if command -v pm2 &> /dev/null; then
    if pm2 list 2>/dev/null | grep -q "whatsapp-bot.*online"; then
        echo "✅ WhatsApp bot corriendo (PM2)"
        ((checks_passed++))
    else
        echo "⚠️  WhatsApp bot NO está corriendo"
        ((checks_failed++))
    fi
else
    echo "⚠️  PM2 no instalado"
    ((checks_failed++))
fi

# 9. Archivos de testing movidos
if [ -d /var/www/html/_testing_files ]; then
    test_count=$(ls /var/www/html/_testing_files/*.{php,html} 2>/dev/null | wc -l)
    if [ $test_count -gt 0 ]; then
        echo "✅ Archivos de prueba organizados ($test_count archivos)"
        ((checks_passed++))
    fi
else
    echo "⚠️  Carpeta _testing_files no existe"
fi

# 10. Seguridad
if grep -q "display_errors Off" /var/www/html/.htaccess 2>/dev/null; then
    echo "✅ display_errors desactivado en .htaccess"
    ((checks_passed++))
else
    echo "⚠️  display_errors no configurado en .htaccess"
    ((checks_failed++))
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📊 RESUMEN:"
echo "   ✅ Verificaciones exitosas: $checks_passed"
echo "   ❌ Verificaciones fallidas: $checks_failed"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [ $checks_failed -eq 0 ]; then
    echo "🎉 ¡Todo listo para producción!"
    exit 0
elif [ $checks_failed -le 3 ]; then
    echo "⚠️  Casi listo, algunos ajustes menores pendientes"
    exit 0
else
    echo "❌ Requiere atención antes de producción"
    exit 1
fi
