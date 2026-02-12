require('dotenv').config({ path: '/var/www/html/.env' });
const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode-terminal');
const express = require('express');
const mysql = require('mysql2/promise');
const rateLimit = require('express-rate-limit');

const app = express();

// ========== SISTEMA DE LOGS PROFESIONAL ==========
const LOG_LEVELS = { ERROR: 'ERROR', WARN: 'WARN', INFO: 'INFO', DEBUG: 'DEBUG' };

function log(level, message, meta = {}) {
    const timestamp = new Date().toISOString();
    const logEntry = {
        timestamp,
        level,
        message,
        ...meta,
        pid: process.pid
    };
    console.log(JSON.stringify(logEntry));
}

// ========== MANEJADORES GLOBALES DE ERRORES ==========
process.on('uncaughtException', (error) => {
    log(LOG_LEVELS.ERROR, 'Uncaught Exception', { error: error.message, stack: error.stack });
    // Dar tiempo para escribir logs antes de salir
    setTimeout(() => process.exit(1), 1000);
});

process.on('unhandledRejection', (reason, promise) => {
    log(LOG_LEVELS.ERROR, 'Unhandled Rejection', { reason: String(reason) });
});

// Graceful shutdown
process.on('SIGTERM', async () => {
    log(LOG_LEVELS.INFO, 'SIGTERM recibido, cerrando servidor...');
    await gracefulShutdown();
});

process.on('SIGINT', async () => {
    log(LOG_LEVELS.INFO, 'SIGINT recibido, cerrando servidor...');
    await gracefulShutdown();
});

async function gracefulShutdown() {
    try {
        if (dbPool) {
            await dbPool.end();
            log(LOG_LEVELS.INFO, 'Pool de BD cerrado');
        }
        if (client) {
            await client.destroy();
            log(LOG_LEVELS.INFO, 'Cliente WhatsApp destruido');
        }
        process.exit(0);
    } catch (error) {
        log(LOG_LEVELS.ERROR, 'Error en shutdown', { error: error.message });
        process.exit(1);
    }
}

app.use(express.json({ limit: '1mb' }));

// ========== RATE LIMITING (Protección contra abuso) ==========
const apiLimiter = rateLimit({
    windowMs: 1 * 60 * 1000, // 1 minuto
    max: 60, // máximo 60 peticiones por minuto
    message: { success: false, error: 'Demasiadas peticiones, intenta más tarde' },
    standardHeaders: true,
    legacyHeaders: false
});

app.use(apiLimiter);

// Configurar CORS (restringido a dominios permitidos)
const ALLOWED_ORIGINS = [
    'https://quickbite.com.mx',
    'https://www.quickbite.com.mx',
    'http://localhost',
    'http://127.0.0.1'
];

app.use((req, res, next) => {
    const origin = req.headers.origin;
    if (ALLOWED_ORIGINS.includes(origin) || !origin) {
        res.header('Access-Control-Allow-Origin', origin || '*');
    }
    res.header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    res.header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    
    if (req.method === 'OPTIONS') {
        return res.sendStatus(200);
    }
    
    next();
});

// ========== CONFIGURACIÓN DE BASE DE DATOS (Variables de Entorno) ==========
const dbConfig = {
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'quickbite',
    password: process.env.DB_PASS,
    database: process.env.DB_NAME || 'app_delivery',
    // CRÍTICO: Configuración del pool para 2GB RAM
    connectionLimit: 5,           // Máximo 5 conexiones simultáneas
    waitForConnections: true,     // Esperar si no hay conexiones disponibles
    queueLimit: 10,               // Máximo 10 en cola de espera
    acquireTimeout: 10000,        // Timeout para obtener conexión: 10s
    connectTimeout: 10000,        // Timeout de conexión: 10s
    idleTimeout: 60000,           // Cerrar conexiones inactivas después de 60s
    enableKeepAlive: true,        // Mantener conexiones vivas
    keepAliveInitialDelay: 30000  // Ping cada 30s para evitar timeout
};

// Validar que las credenciales existen
if (!process.env.DB_PASS) {
    log(LOG_LEVELS.ERROR, 'DB_PASS no está definida en variables de entorno');
    process.exit(1);
}

// Pool de conexiones
let dbPool;

async function initDB() {
    try {
        dbPool = mysql.createPool(dbConfig);
        // Test de conexión
        const connection = await dbPool.getConnection();
        await connection.ping();
        connection.release();
        log(LOG_LEVELS.INFO, 'Pool de BD creado y verificado', { connectionLimit: dbConfig.connectionLimit });
    } catch (error) {
        log(LOG_LEVELS.ERROR, 'Error al conectar con la BD', { error: error.message });
        process.exit(1);
    }
}

// Función helper para queries con manejo de errores y timeout
async function executeQuery(sql, params = []) {
    let connection;
    try {
        connection = await dbPool.getConnection();
        const [rows] = await connection.execute(sql, params);
        return rows;
    } catch (error) {
        log(LOG_LEVELS.ERROR, 'Error en query', { sql, error: error.message });
        throw error;
    } finally {
        if (connection) connection.release();
    }
}

// Inicializar base de datos
initDB();

// Inicializar cliente de WhatsApp
const client = new Client({
    authStrategy: new LocalAuth({
        dataPath: './.wwebjs_auth'
    }),
    puppeteer: {
        headless: true,
        executablePath: '/usr/bin/chromium-browser',
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-accelerated-2d-canvas',
            '--no-first-run',
            '--no-zygote',
            '--disable-gpu'
        ]
    }
});

let isReady = false;

// Evento: QR Code para escanear
client.on('qr', (qr) => {
    log(LOG_LEVELS.INFO, 'QR Code generado - escanear con WhatsApp');
    qrcode.generate(qr, { small: true });
    console.log('\nO abre WhatsApp > Dispositivos vinculados > Vincular dispositivo');
});

// Evento: Cliente listo
client.on('ready', () => {
    log(LOG_LEVELS.INFO, 'WhatsApp conectado y listo');
    isReady = true;
});

// Evento: Mensaje recibido
client.on('message', async (message) => {
    try {
        // Ignorar mensajes de grupos y broadcasts para reducir carga
        if (message.isGroupMsg || message.isBroadcast) {
            return;
        }
        
        const messageBody = message.body.toLowerCase().trim();
        
        // Ignorar mensajes vacíos o muy largos
        if (!messageBody || messageBody.length > 500) {
            return;
        }
        
        log(LOG_LEVELS.INFO, 'Mensaje recibido', { from: message.from, body: messageBody.substring(0, 50) });
        
        // OPTIMIZACIÓN: Primero buscar en el mensaje actual antes de cargar historial
        let pedidoId = null;
        const currentMatch = message.body.match(/Pedido #(\d+)/i);
        
        if (currentMatch) {
            pedidoId = currentMatch[1];
        } else {
            // Solo cargar historial si es necesario (reducido a 10 mensajes)
            const chat = await message.getChat();
            const messages = await chat.fetchMessages({ limit: 10 });
            
            for (const m of messages) {
                const match = m.body.match(/Pedido #(\d+)/i);
                if (match) {
                    pedidoId = match[1];
                    break;
                }
            }
            // Limpiar referencia para ayudar al GC
            messages.length = 0;
        }
        
        if (!pedidoId) {
            log(LOG_LEVELS.DEBUG, 'No se encontró número de pedido en el contexto');
            return;
        }
        
        // VALIDACIÓN: Asegurar que pedidoId es numérico (prevenir inyección SQL)
        if (!/^\d+$/.test(pedidoId)) {
            log(LOG_LEVELS.WARN, 'ID de pedido inválido', { pedidoId });
            return;
        }
        
        log(LOG_LEVELS.INFO, 'Pedido detectado', { pedidoId });
        
        // Detectar intención del mensaje
        let nuevoEstado = null;
        let respuesta = null;
        
        if (messageBody.includes('recibido')) {
            // Confirmación de pago SPEI - verificar que el pedido está en estado 7
            try {
                const pedidoRows = await executeQuery(
                    'SELECT id_estado, metodo_pago FROM pedidos WHERE id_pedido = ?',
                    [parseInt(pedidoId, 10)]
                );

                if (pedidoRows.length > 0 && pedidoRows[0].id_estado === 7) {
                    log(LOG_LEVELS.INFO, 'Confirmación SPEI detectada', { pedidoId });

                    // Llamar endpoint PHP para confirmar pago y notificar al cliente
                    const http = require('http');
                    const botToken = process.env.WHATSAPP_BOT_SECRET || 'quickbite_bot_internal_2024';
                    const postData = JSON.stringify({ pedido_id: parseInt(pedidoId, 10) });

                    const options = {
                        hostname: 'localhost',
                        port: 80,
                        path: '/api/confirmar_pago_spei.php',
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + botToken,
                            'Content-Length': Buffer.byteLength(postData)
                        }
                    };

                    // Esperar a que confirmar_pago_spei termine antes de responder
                    const speiResult = await new Promise((resolve, reject) => {
                        const apiReq = http.request(options, (apiRes) => {
                            let body = '';
                            apiRes.on('data', (chunk) => body += chunk);
                            apiRes.on('end', () => {
                                log(LOG_LEVELS.INFO, 'Respuesta confirmar_pago_spei', { body });
                                try {
                                    resolve(JSON.parse(body));
                                } catch (e) {
                                    resolve({ success: false, error: 'Respuesta inválida' });
                                }
                            });
                        });
                        apiReq.on('error', (e) => {
                            log(LOG_LEVELS.ERROR, 'Error llamando confirmar_pago_spei', { error: e.message });
                            resolve({ success: false, error: e.message });
                        });
                        apiReq.setTimeout(10000, () => {
                            apiReq.destroy();
                            resolve({ success: false, error: 'Timeout' });
                        });
                        apiReq.write(postData);
                        apiReq.end();
                    });

                    if (speiResult.success) {
                        // Pago confirmado - responder al negocio
                        await message.reply(
                            `✅ *Pago del Pedido #${pedidoId} CONFIRMADO*\n\n` +
                            `Has confirmado la recepción de la transferencia.\n` +
                            `El cliente ha sido notificado.\n\n` +
                            `━━━━━━━━━━━━━━━━━━━━━━━━\n` +
                            `*RESPONDE CON:*\n\n` +
                            `👨‍🍳 Preparando\n` +
                            `❌ Cancelar\n` +
                            `━━━━━━━━━━━━━━━━━━━━━━━━`
                        );
                    } else {
                        log(LOG_LEVELS.ERROR, 'Error confirmando pago SPEI', { pedidoId, error: speiResult.error });
                        await message.reply(
                            `⚠️ *Error al confirmar pago del Pedido #${pedidoId}*\n\n` +
                            `Hubo un problema al procesar la confirmación. Intenta escribir *recibido* nuevamente.`
                        );
                    }
                    return; // Ya procesado, no continuar con otros handlers
                }
            } catch (err) {
                log(LOG_LEVELS.ERROR, 'Error verificando estado SPEI', { error: err.message });
            }
        }

        if (messageBody.includes('aceptar')) {
            nuevoEstado = 2; // confirmado
            respuesta = `✅ *Pedido #${pedidoId} CONFIRMADO*\n\n` +
                       `¿Confirmas que comenzarás a preparar este pedido?\n\n` +
                       `🚗 *Tipo:* Entrega a domicilio\n\n` +
                       `━━━━━━━━━━━━━━━━━━━━━━━━\n` +
                       `*RESPONDE CON:*\n\n` +
                       `👨‍🍳 Preparando\n` +
                       `❌ Cancelar\n` +
                       `━━━━━━━━━━━━━━━━━━━━━━━━`;
                       
        } else if (messageBody.includes('rechazar')) {
            nuevoEstado = 7; // cancelado
            respuesta = `❌ *Pedido #${pedidoId} CANCELADO*\n\n` +
                       `El pedido ha sido cancelado.\n` +
                       `El cliente será notificado.`;
                       
        } else if (messageBody.includes('preparando')) {
            nuevoEstado = 3; // en_preparacion
            respuesta = `👨‍🍳 *Pedido #${pedidoId} EN PREPARACIÓN*\n\n` +
                       `El cliente ha sido notificado.\n\n` +
                       `━━━━━━━━━━━━━━━━━━━━━━━━\n` +
                       `*Cuando esté listo, RESPONDE CON:*\n\n` +
                       `✅ Listo\n` +
                       `━━━━━━━━━━━━━━━━━━━━━━━━`;
                       
        } else if (messageBody.includes('listo')) {
            nuevoEstado = 4; // listo_para_recoger
            respuesta = `✅ *Pedido #${pedidoId} LISTO*\n\n` +
                       `El pedido está listo para ser recogido por el repartidor.\n\n` +
                       `━━━━━━━━━━━━━━━━━━━━━━━━\n` +
                       `*Cuando el repartidor recoja el pedido, RESPONDE CON:*\n\n` +
                       `🛵 En Camino\n` +
                       `━━━━━━━━━━━━━━━━━━━━━━━━`;
                       
        } else if (messageBody.includes('en camino') || messageBody.includes('camino')) {
            nuevoEstado = 5; // en_camino
            respuesta = `🛵 *Pedido #${pedidoId} EN CAMINO*\n\n` +
                       `El repartidor está en camino a la dirección del cliente.\n\n` +
                       `━━━━━━━━━━━━━━━━━━━━━━━━\n` +
                       `El repartidor marcará como entregado desde su aplicación.\n` +
                       `━━━━━━━━━━━━━━━━━━━━━━━━`;
                       
        } else if (messageBody.includes('cancelar')) {
            nuevoEstado = 7; // cancelado
            respuesta = `❌ *Pedido #${pedidoId} CANCELADO*\n\n` +
                       `El pedido ha sido cancelado.\n` +
                       `El cliente será notificado.`;
        }
        
        // Si se detectó una acción, actualizar el pedido
        if (nuevoEstado && respuesta) {
            log(LOG_LEVELS.INFO, 'Actualizando pedido', { pedidoId, nuevoEstado });
            
            // Actualizar estado en BD usando función helper
            try {
                const result = await executeQuery(
                    'UPDATE pedidos SET id_estado = ?, fecha_actualizacion = NOW() WHERE id_pedido = ?',
                    [nuevoEstado, parseInt(pedidoId, 10)]
                );
                
                if (result.affectedRows > 0) {
                    log(LOG_LEVELS.INFO, 'Pedido actualizado', { pedidoId, nuevoEstado });
                    
                    // Enviar respuesta al restaurante
                    await message.reply(respuesta);
                    log(LOG_LEVELS.INFO, 'Respuesta enviada al restaurante');
                } else {
                    log(LOG_LEVELS.WARN, 'Pedido no encontrado para actualizar', { pedidoId });
                }
                
            } catch (error) {
                log(LOG_LEVELS.ERROR, 'Error actualizando BD', { pedidoId, error: error.message });
            }
        }
        
    } catch (error) {
        log(LOG_LEVELS.ERROR, 'Error procesando mensaje', { error: error.message });
    }
});

// Evento: Desconexión con reconexión automática
client.on('disconnected', async (reason) => {
    log(LOG_LEVELS.WARN, 'Cliente WhatsApp desconectado', { reason });
    isReady = false;
    
    // Intentar reconexión después de 5 segundos
    setTimeout(async () => {
        log(LOG_LEVELS.INFO, 'Intentando reconexión de WhatsApp...');
        try {
            await client.initialize();
        } catch (error) {
            log(LOG_LEVELS.ERROR, 'Error en reconexión', { error: error.message });
        }
    }, 5000);
});

// Evento: Error de autenticación
client.on('auth_failure', (message) => {
    log(LOG_LEVELS.ERROR, 'Error de autenticación WhatsApp', { message });
});

// Inicializar WhatsApp
log(LOG_LEVELS.INFO, 'Inicializando cliente WhatsApp...');
client.initialize();

// ========== API REST ==========

// Health check completo
app.get('/health', async (req, res) => {
    try {
        // Verificar BD
        let dbStatus = 'disconnected';
        try {
            await executeQuery('SELECT 1');
            dbStatus = 'connected';
        } catch (e) {
            dbStatus = 'error: ' + e.message;
        }
        
        const health = {
            status: isReady && dbStatus === 'connected' ? 'healthy' : 'degraded',
            timestamp: new Date().toISOString(),
            uptime: process.uptime(),
            memory: {
                used: Math.round(process.memoryUsage().heapUsed / 1024 / 1024) + 'MB',
                total: Math.round(process.memoryUsage().heapTotal / 1024 / 1024) + 'MB'
            },
            services: {
                whatsapp: isReady ? 'connected' : 'disconnected',
                database: dbStatus
            }
        };
        
        res.status(health.status === 'healthy' ? 200 : 503).json(health);
    } catch (error) {
        res.status(500).json({ status: 'error', error: error.message });
    }
});

// Status del bot (simplificado)
app.get('/status', (req, res) => {
    res.json({
        ready: isReady,
        info: isReady ? 'WhatsApp conectado' : 'WhatsApp no conectado',
        uptime: process.uptime()
    });
});

// Enviar mensaje simple
app.post('/send', async (req, res) => {
    try {
        const { phone, message } = req.body;
        
        if (!isReady) {
            return res.status(503).json({ 
                success: false, 
                error: 'WhatsApp no está conectado' 
            });
        }
        
        if (!phone || !message) {
            return res.status(400).json({ 
                success: false, 
                error: 'Faltan parámetros: phone, message' 
            });
        }
        
        // Formatear número (agregar @c.us si no lo tiene)
        const cleanPhone = phone.replace(/[^0-9]/g, '');
        
        // Obtener el ID correcto del número
        const numberId = await client.getNumberId(cleanPhone);
        if (!numberId) {
            return res.status(400).json({
                success: false,
                error: `El número ${cleanPhone} no está registrado en WhatsApp`
            });
        }
        
        const chatId = numberId._serialized;
        
        // Enviar mensaje
        await client.sendMessage(chatId, message);
        
        res.json({ 
            success: true, 
            message_id: Date.now().toString(),
            sent_to: chatId
        });
        
        log(LOG_LEVELS.INFO, 'Mensaje enviado', { chatId });
        
    } catch (error) {
        log(LOG_LEVELS.ERROR, 'Error al enviar mensaje', { error: error.message });
        res.status(500).json({ 
            success: false, 
            error: error.message 
        });
    }
});

// Enviar mensaje con botones interactivos
app.post('/send-buttons', async (req, res) => {
    try {
        const { phone, message, buttons, footer } = req.body;
        
        if (!isReady) {
            return res.status(503).json({ 
                success: false, 
                error: 'WhatsApp no está conectado' 
            });
        }
        
        const chatId = phone.includes('@c.us') ? phone : `${phone.replace(/[^0-9]/g, '')}@c.us`;
        
        // Crear botones interactivos
        const buttonObjects = buttons.map((btnText, idx) => ({
            id: `btn_${idx + 1}`,
            body: btnText
        }));
        
        // Enviar mensaje con botones
        await client.sendMessage(chatId, message, {
            buttons: buttonObjects,
            footer: footer || 'QuickBite',
            headerType: 1
        });
        
        res.json({ 
            success: true, 
            message_id: Date.now().toString() 
        });
        
        log(LOG_LEVELS.INFO, 'Mensaje con botones enviado', { chatId });
        
    } catch (error) {
        log(LOG_LEVELS.ERROR, 'Error al enviar botones', { error: error.message });
        res.status(500).json({ 
            success: false, 
            error: error.message 
        });
    }
});

// Enviar notificación de pedido con botones
app.post('/send-order', async (req, res) => {
    try {
        const { phone, order_id, status, total, customer_name, negocio_nombre, id_estado } = req.body;
        
        if (!isReady) {
            return res.status(503).json({ 
                success: false, 
                error: 'WhatsApp no está conectado' 
            });
        }
        
        const chatId = phone.includes('@c.us') ? phone : `${phone.replace(/[^0-9]/g, '')}@c.us`;
        
        let orderMessage = '';
        
        // TODOS LOS MENSAJES VAN AL RESTAURANTE PARA AUTOMATIZACIÓN
        // 1=pendiente, 2=confirmado, 3=en_preparacion, 4=listo_para_recoger, 5=en_camino, 6=entregado, 7=cancelado
        
        if (id_estado === 1 || status === 'pendiente' || status === 'nuevo_pedido_restaurante') {
            // CASE 1: Pedido nuevo - RESTAURANTE
            orderMessage = `🍕 *NUEVO PEDIDO RECIBIDO* 🍕\n\n` +
                          `📋 *Pedido #${order_id}*\n` +
                          `👤 *Cliente:* ${customer_name}\n` +
                          `💰 *Total:* $${total}\n` +
                          `📦 *Tipo:* Delivery (Envío a domicilio)\n\n` +
                          `━━━━━━━━━━━━━━━━━━━━━━━━\n` +
                          `*RESPONDE CON:*\n\n` +
                          `✅ Aceptar\n` +
                          `❌ Rechazar\n` +
                          `━━━━━━━━━━━━━━━━━━━━━━━━`;
            
            await client.sendMessage(chatId, orderMessage);
            
        } else if (id_estado === 2 || status === 'confirmado') {
            // CASE 2: Después de aceptar - RESTAURANTE
            orderMessage = `✅ *Pedido #${order_id} CONFIRMADO*\n\n` +
                          `¿Confirmas que comenzarás a preparar este pedido?\n\n` +
                          `🚗 *Tipo:* Entrega a domicilio\n\n` +
                          `━━━━━━━━━━━━━━━━━━━━━━━━\n` +
                          `*RESPONDE CON:*\n\n` +
                          `👨‍🍳 Preparando\n` +
                          `❌ Cancelar\n` +
                          `━━━━━━━━━━━━━━━━━━━━━━━━`;
            
            await client.sendMessage(chatId, orderMessage);
            
        } else if (id_estado === 3 || status === 'en_preparacion' || status === 'preparando') {
            // CASE 3: En preparación - RESTAURANTE
            orderMessage = `👨‍🍳 *Pedido #${order_id} EN PREPARACIÓN*\n\n` +
                          `El cliente ha sido notificado.\n\n` +
                          `━━━━━━━━━━━━━━━━━━━━━━━━\n` +
                          `*Cuando esté listo, RESPONDE CON:*\n\n` +
                          `✅ Listo\n` +
                          `━━━━━━━━━━━━━━━━━━━━━━━━`;
            
            await client.sendMessage(chatId, orderMessage);
            
        } else if (id_estado === 4 || status === 'listo_para_recoger' || status === 'listo') {
            // CASE 4: Listo - RESTAURANTE
            orderMessage = `✅ *Pedido #${order_id} LISTO*\n\n` +
                          `El pedido está listo para ser recogido por el repartidor.\n\n` +
                          `━━━━━━━━━━━━━━━━━━━━━━━━\n` +
                          `*Cuando el repartidor recoja el pedido, RESPONDE CON:*\n\n` +
                          `🛵 En Camino\n` +
                          `━━━━━━━━━━━━━━━━━━━━━━━━`;
            
            await client.sendMessage(chatId, orderMessage);
            
        } else if (id_estado === 5 || status === 'en_camino') {
            // CASE 5: En camino - RESTAURANTE (confirmación)
            orderMessage = `🛵 *Pedido #${order_id} EN CAMINO*\n\n` +
                          `El repartidor está en camino a la dirección del cliente.\n\n` +
                          `━━━━━━━━━━━━━━━━━━━━━━━━\n` +
                          `El repartidor marcará como entregado desde su aplicación.\n` +
                          `━━━━━━━━━━━━━━━━━━━━━━━━`;
            
            await client.sendMessage(chatId, orderMessage);
            
        } else if (id_estado === 6 || status === 'entregado') {
            // CASE 6: Entregado - RESTAURANTE (confirmación final)
            orderMessage = `🎉 *Pedido #${order_id} ENTREGADO*\n\n` +
                          `¡Pedido completado exitosamente!\n` +
                          `Gracias por usar QuickBite. 🚀`;
            
            await client.sendMessage(chatId, orderMessage);
            
        } else if (id_estado === 7 || status === 'cancelado') {
            // CASE 7: Cancelado - RESTAURANTE
            orderMessage = `❌ *Pedido #${order_id} CANCELADO*\n\n` +
                          `El pedido ha sido cancelado.\n` +
                          `El cliente será notificado.`;
            
            await client.sendMessage(chatId, orderMessage);
        }
        
        res.json({ 
            success: true, 
            message_id: Date.now().toString(),
            sent_to: chatId
        });
        
        log(LOG_LEVELS.INFO, 'Notificación enviada a restaurante', { chatId, estado: id_estado || status });
        
    } catch (error) {
        log(LOG_LEVELS.ERROR, 'Error al enviar notificación', { error: error.message });
        res.status(500).json({ 
            success: false, 
            error: error.message 
        });
    }
});

// Iniciar servidor API
const PORT = process.env.WHATSAPP_BOT_PORT || 3030;

const server = app.listen(PORT, () => {
    log(LOG_LEVELS.INFO, 'API de WhatsApp iniciada', { 
        port: PORT, 
        environment: process.env.ENVIRONMENT || 'development',
        nodeVersion: process.version
    });
    console.log(`\nEndpoints disponibles:`);
    console.log(`  GET  http://localhost:${PORT}/health`);
    console.log(`  GET  http://localhost:${PORT}/status`);
    console.log(`  POST http://localhost:${PORT}/send`);
    console.log(`  POST http://localhost:${PORT}/send-buttons`);
    console.log(`  POST http://localhost:${PORT}/send-order\n`);
});

// Configurar timeout del servidor
server.timeout = 30000; // 30 segundos
server.keepAliveTimeout = 65000; // 65 segundos (mayor que el timeout de nginx/cloudflare)
