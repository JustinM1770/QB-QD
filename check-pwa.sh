#!/bin/bash

echo "🔍 VERIFICACIÓN PWA - QUICKBITE"
echo "================================="
echo ""

# Función para verificar archivos
check_file() {
    if [ -f "$1" ]; then
        echo "✅ $1 - Existe"
    else
        echo "❌ $1 - NO ENCONTRADO"
    fi
}

# Función para verificar directorios
check_dir() {
    if [ -d "$1" ]; then
        echo "✅ $1/ - Existe"
    else
        echo "❌ $1/ - NO ENCONTRADO"
    fi
}

echo "📋 Verificando archivos PWA principales:"
echo "-----------------------------------------"
check_file "manifest.json"
check_file "sw.js"
check_file "offline.html"
check_file "favicon.ico"

echo ""
echo "📋 Verificando archivos JavaScript y CSS:"
echo "------------------------------------------"
check_file "assets/js/pwa.js"
check_file "assets/css/pwa.css"

echo ""
echo "📋 Verificando iconos PWA:"
echo "---------------------------"
check_dir "assets/icons"
check_file "assets/icons/icon-72x72.png"
check_file "assets/icons/icon-96x96.png"
check_file "assets/icons/icon-128x128.png"
check_file "assets/icons/icon-144x144.png"
check_file "assets/icons/icon-152x152.png"
check_file "assets/icons/icon-192x192.png"
check_file "assets/icons/icon-384x384.png"
check_file "assets/icons/icon-512x512.png"
check_file "assets/icons/apple-touch-icon.png"

echo ""
echo "📋 Verificando API de notificaciones:"
echo "--------------------------------------"
check_file "api/push-subscription.php"
check_file "api/push-service.php"

echo ""
echo "🔧 Verificando configuración del servidor:"
echo "-------------------------------------------"

# Verificar si Apache/Nginx está sirviendo los archivos correctamente
if curl -s -I http://localhost/manifest.json | grep -q "200 OK"; then
    echo "✅ manifest.json - Accesible vía HTTP"
else
    echo "⚠️  manifest.json - No accesible vía HTTP (verificar configuración del servidor)"
fi

if curl -s -I http://localhost/sw.js | grep -q "200 OK"; then
    echo "✅ sw.js - Accesible vía HTTP"
else
    echo "⚠️  sw.js - No accesible vía HTTP (verificar configuración del servidor)"
fi

echo ""
echo "📱 Verificando configuración HTTPS:"
echo "------------------------------------"
if curl -s -I https://localhost 2>/dev/null | grep -q "200"; then
    echo "✅ HTTPS configurado (requerido para PWA en producción)"
else
    echo "⚠️  HTTPS no detectado - Las PWAs requieren HTTPS en producción"
    echo "   💡 Para desarrollo local, puedes usar http://localhost"
fi

echo ""
echo "🗄️  Verificando base de datos:"
echo "-------------------------------"
if php -r "
try {
    require_once 'config/database.php';
    \$db = new Database();
    \$conn = \$db->getConnection();
    
    // Verificar si la tabla existe
    \$stmt = \$conn->query('SHOW TABLES LIKE \"push_subscriptions\"');
    if (\$stmt->rowCount() > 0) {
        echo '✅ Tabla push_subscriptions existe\n';
        
        // Contar suscripciones
        \$stmt = \$conn->query('SELECT COUNT(*) as count FROM push_subscriptions');
        \$count = \$stmt->fetch(PDO::FETCH_ASSOC);
        echo '📊 Suscripciones registradas: ' . \$count['count'] . '\n';
    } else {
        echo '⚠️  Tabla push_subscriptions no existe (se creará automáticamente)\n';
    }
} catch (Exception \$e) {
    echo '❌ Error conectando a la base de datos: ' . \$e->getMessage() . '\n';
}
" 2>/dev/null; then
    : # El comando se ejecutó correctamente
else
    echo "❌ Error verificando la base de datos"
fi

echo ""
echo "🚀 Comandos útiles:"
echo "-------------------"
echo "• Probar notificaciones push:"
echo "  php api/push-service.php"
echo ""
echo "• Verificar Service Worker en el navegador:"
echo "  Abrir DevTools > Application > Service Workers"
echo ""
echo "• Verificar PWA en el navegador:"
echo "  Abrir DevTools > Application > Manifest"
echo ""
echo "• Instalar PWA en Chrome:"
echo "  Buscar el ícono '📱' en la barra de direcciones"

echo ""
echo "🎯 Lista de verificación para producción:"
echo "------------------------------------------"
echo "☐ Configurar HTTPS"
echo "☐ Generar claves VAPID propias"
echo "☐ Configurar dominio en manifest.json"
echo "☐ Optimizar imágenes e iconos"
echo "☐ Configurar caché del servidor"
echo "☐ Probar en dispositivos reales"

echo ""
echo "📚 URLs importantes para testing:"
echo "----------------------------------"
echo "• Manifest:     http://localhost/manifest.json"
echo "• Service Worker: http://localhost/sw.js"
echo "• Offline Page:   http://localhost/offline.html"
echo "• Push API:       http://localhost/api/push-subscription.php"

echo ""
echo "================================="
echo "✅ VERIFICACIÓN COMPLETADA"
echo "================================="