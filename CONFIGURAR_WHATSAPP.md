# 🟢 Configurar WhatsApp Business API

## Opción 1: WhatsApp Business API (Recomendado) ✅

### Paso 1: Crear App en Meta
1. Ve a https://developers.facebook.com/apps
2. Click en "Crear app"
3. Selecciona "Negocios" como tipo
4. Dale un nombre: "QuickBite Notifications"

### Paso 2: Agregar WhatsApp
1. En el panel de tu app, busca "WhatsApp" 
2. Click en "Configurar"
3. Ve a la sección "API Setup"

### Paso 3: Obtener Credenciales
Copia estos valores y pégalos en `/var/www/html/config/whatsapp_config.php`:

```php
// En la sección API Setup encontrarás:
define('WHATSAPP_PHONE_NUMBER_ID', 'AQUI_TU_PHONE_ID'); // Se ve como: 123456789012345
define('WHATSAPP_ACCESS_TOKEN', 'AQUI_TU_TOKEN'); // Empieza con: EAAG...
define('WHATSAPP_APP_SECRET', 'AQUI_TU_SECRET'); // En Configuración > Básico
```

### Paso 4: Número de Prueba
Meta te da un número de prueba gratuito. Puedes:
- Agregar hasta 5 números para pruebas
- Enviar mensajes ilimitados durante desarrollo
- Usar templates predefinidos

### Paso 5: Probar
1. Abre: http://quickbite.com.mx/test_whatsapp.html
2. Agrega tu número en Meta (Configuración > Números de prueba)
3. Envía un mensaje de prueba

---

## Opción 2: Usar el Bot Actual (WhatsApp Web)

El bot que tienes corriendo (PID 158408) usa WhatsApp Web. Para usarlo:

### Crear API Bridge

```bash
# Crear endpoint que comunique con el bot
cat > /var/www/html/whatsapp-server/api.js << 'EOF'
const express = require('express');
const app = express();
app.use(express.json());

let whatsappClient = null;

// Exportar función para recibir el cliente
global.setWhatsAppClient = (client) => {
    whatsappClient = client;
};

// Endpoint para enviar mensajes
app.post('/send', async (req, res) => {
    try {
        const { phone, message } = req.body;
        
        if (!whatsappClient) {
            return res.status(503).json({ error: 'WhatsApp no conectado' });
        }
        
        const chatId = phone.includes('@c.us') ? phone : `${phone}@c.us`;
        await whatsappClient.sendMessage(chatId, message);
        
        res.json({ success: true, message_id: Date.now() });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.listen(3030, () => {
    console.log('API WhatsApp corriendo en puerto 3030');
});
EOF

# Reiniciar el bot con la API
pm2 restart whatsapp-bot
```

Luego edita `/var/www/html/api/WhatsAppService.php` para usar:
```php
$url = 'http://localhost:3030/send';
```

---

## ⚡ Recomendación

**Usa la Opción 1 (Meta Business API)** porque:
- ✅ Es completamente gratis
- ✅ Más estable y confiable
- ✅ Soporta templates y botones
- ✅ No necesita sesión activa
- ✅ Mejor para producción

**Toma solo 10 minutos configurarlo.**

---

## 🔧 Estado Actual

```bash
# Ver si el bot de WhatsApp Web está corriendo
ps aux | grep whatsapp

# Ver logs
tail -f /var/www/html/logs/whatsapp.log
```

## 📞 Números de Prueba

Para agregar números de prueba en Meta:
1. Panel de WhatsApp > API Setup
2. "To" field > Manage phone number list
3. Agregar tu número con código de país: +52 449 287 3740
