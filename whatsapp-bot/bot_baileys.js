require('dotenv').config({ path: '/var/www/html/.env' });
const { default: makeWASocket, useMultiFileAuthState, DisconnectReason } = require("@whiskeysockets/baileys");
const qrcode = require('qrcode-terminal');
const express = require('express');
const mysql = require('mysql2/promise');
const pino = require("pino");

const app = express();
// Aumentamos el límite por si acaso y aseguramos el parseo de JSON
app.use(express.json({ limit: '1mb' }));

// ========== CONFIGURACIÓN DE BD ==========
const dbConfig = {
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'quickbite',
    password: process.env.DB_PASS,
    database: process.env.DB_NAME || 'app_delivery'
};

let dbPool;
let sock;

async function initDB() {
    try {
        dbPool = mysql.createPool(dbConfig);
        console.log("✅ Pool de BD conectado para QuickBite");
    } catch (e) {
        console.error("❌ Error de BD:", e.message);
    }
}

// ========== LÓGICA DE WHATSAPP ==========
async function connectToWhatsApp() {
    const { state, saveCreds } = await useMultiFileAuthState('auth_info_baileys');

    sock = makeWASocket({
        auth: state,
        logger: pino({ level: "silent" }),
        browser: ["QuickBite Admin", "Ubuntu", "1.0.0"]
    });

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', (update) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
            console.log('\n✨ ESCANEA ESTE QR CON TU CELULAR:');
            qrcode.generate(qr, { small: true });
        }

        if (connection === 'close') {
            const shouldReconnect = lastDisconnect.error?.output?.statusCode !== DisconnectReason.loggedOut;
            if (shouldReconnect) {
                console.log("🔄 Conexión perdida. Reconectando...");
                connectToWhatsApp();
            }
        } else if (connection === 'open') {
            console.log('\n🚀 QuickBite WhatsApp VINCULADO Y LISTO');
        }
    });

    // ESCUCHAR MENSAJES ENTRANTES (botones + texto)
    sock.ev.on('messages.upsert', async ({ messages }) => {
        const m = messages[0];
        if (!m.message) return;
        if (m.key.fromMe) return; // Ignorar mensajes propios

        // --- Respuestas de botones ---
        const selectionId = m.message.buttonsResponseMessage?.selectedButtonId ||
                            m.message.templateButtonReplyMessage?.selectedId;

        if (selectionId) {
            const [accion, pedidoId] = selectionId.split('_');
            let nuevoEstado = (accion === 'acc') ? 2 : 7;

            console.log(`📩 Click recibido: ${accion} para Pedido #${pedidoId}`);

            try {
                const [result] = await dbPool.execute(
                    'UPDATE pedidos SET id_estado = ?, fecha_actualizacion = NOW() WHERE id_pedido = ?',
                    [nuevoEstado, pedidoId]
                );

                let msgText = nuevoEstado === 2 ? `✅ Pedido #${pedidoId} aceptado.` : `❌ Pedido #${pedidoId} rechazado.`;
                await sock.sendMessage(m.key.remoteJid, { text: msgText });

                console.log(`[BD] Pedido ${pedidoId} actualizado a estado ${nuevoEstado}`);
            } catch (e) {
                console.error("❌ Error en BD:", e.message);
            }
            return;
        }

        // --- Mensajes de texto: detectar keywords para gestión de pedidos ---
        const messageBody = (m.message.conversation || m.message.extendedTextMessage?.text || '').toLowerCase().trim();
        if (!messageBody || messageBody.length > 500) return;

        // Buscar número de pedido en el mensaje o en contexto (mensaje citado)
        let pedidoId = null;
        const currentMatch = messageBody.match(/pedido #(\d+)/i) || messageBody.match(/#(\d+)/);
        if (currentMatch) {
            pedidoId = currentMatch[1];
        } else {
            // Buscar en el mensaje citado (reply)
            const quotedText = m.message.extendedTextMessage?.contextInfo?.quotedMessage?.conversation || '';
            const quotedMatch = quotedText.match(/Pedido #(\d+)/i);
            if (quotedMatch) {
                pedidoId = quotedMatch[1];
            }
        }

        if (!pedidoId || !/^\d+$/.test(pedidoId)) return;

        console.log(`📨 Mensaje de texto recibido para Pedido #${pedidoId}: "${messageBody.substring(0, 50)}"`);

        // --- Handler "recibido": confirmación de pago SPEI/transferencia ---
        if (messageBody.includes('recibido')) {
            try {
                const [rows] = await dbPool.execute(
                    'SELECT id_estado, metodo_pago FROM pedidos WHERE id_pedido = ?',
                    [parseInt(pedidoId, 10)]
                );

                if (rows.length > 0 && rows[0].id_estado === 7) {
                    console.log(`💰 Confirmación de pago detectada para Pedido #${pedidoId}`);

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

                    const apiReq = http.request(options, (apiRes) => {
                        let body = '';
                        apiRes.on('data', (chunk) => body += chunk);
                        apiRes.on('end', () => {
                            console.log(`✅ Respuesta confirmar_pago_spei: ${body}`);
                        });
                    });
                    apiReq.on('error', (e) => {
                        console.error(`❌ Error llamando confirmar_pago_spei: ${e.message}`);
                    });
                    apiReq.write(postData);
                    apiReq.end();

                    // Responder al negocio
                    await sock.sendMessage(m.key.remoteJid, {
                        text: `✅ *Pago del Pedido #${pedidoId} CONFIRMADO*\n\n` +
                              `Has confirmado la recepción de la transferencia.\n` +
                              `El cliente será notificado automáticamente.\n\n` +
                              `━━━━━━━━━━━━━━━━━━━━━━━━\n` +
                              `*RESPONDE CON:*\n\n` +
                              `👨‍🍳 Preparando\n` +
                              `❌ Cancelar\n` +
                              `━━━━━━━━━━━━━━━━━━━━━━━━`
                    });
                    return;
                }
            } catch (e) {
                console.error(`❌ Error verificando estado SPEI: ${e.message}`);
            }
        }

        // --- Otros keywords: aceptar, rechazar, preparando, listo, en camino, cancelar ---
        let nuevoEstado = null;
        let respuesta = null;

        if (messageBody.includes('aceptar')) {
            nuevoEstado = 2;
            respuesta = `✅ *Pedido #${pedidoId} CONFIRMADO*\n\n👨‍🍳 *RESPONDE CON:*\n\nPreparando\nCancelar`;
        } else if (messageBody.includes('rechazar') || messageBody.includes('cancelar')) {
            nuevoEstado = 7;
            respuesta = `❌ *Pedido #${pedidoId} CANCELADO*\n\nEl cliente será notificado.`;
        } else if (messageBody.includes('preparando')) {
            nuevoEstado = 3;
            respuesta = `👨‍🍳 *Pedido #${pedidoId} EN PREPARACIÓN*\n\n*Cuando esté listo, RESPONDE CON:*\n\n✅ Listo`;
        } else if (messageBody.includes('listo')) {
            nuevoEstado = 4;
            respuesta = `✅ *Pedido #${pedidoId} LISTO*\n\n*Cuando el repartidor recoja, RESPONDE CON:*\n\n🛵 En Camino`;
        } else if (messageBody.includes('en camino') || messageBody.includes('camino')) {
            nuevoEstado = 5;
            respuesta = `🛵 *Pedido #${pedidoId} EN CAMINO*\n\nEl repartidor marcará como entregado.`;
        }

        if (nuevoEstado && respuesta) {
            try {
                const [result] = await dbPool.execute(
                    'UPDATE pedidos SET id_estado = ?, fecha_actualizacion = NOW() WHERE id_pedido = ?',
                    [nuevoEstado, parseInt(pedidoId, 10)]
                );

                if (result.affectedRows > 0) {
                    await sock.sendMessage(m.key.remoteJid, { text: respuesta });
                    console.log(`[BD] Pedido ${pedidoId} actualizado a estado ${nuevoEstado}`);
                }
            } catch (e) {
                console.error(`❌ Error actualizando pedido: ${e.message}`);
            }
        }
    });
}

// ========== ENDPOINTS API ==========

// Status
app.get('/status', (req, res) => {
    res.json({
        ready: !!sock,
        info: sock ? 'WhatsApp conectado' : 'WhatsApp no conectado',
        uptime: process.uptime()
    });
});

// Health check
app.get('/health', async (req, res) => {
    let dbStatus = 'disconnected';
    try {
        await dbPool.execute('SELECT 1');
        dbStatus = 'connected';
    } catch (e) {
        dbStatus = 'error: ' + e.message;
    }
    res.json({
        status: sock && dbStatus === 'connected' ? 'healthy' : 'degraded',
        services: { whatsapp: sock ? 'connected' : 'disconnected', database: dbStatus },
        uptime: process.uptime()
    });
});

// Enviar mensaje simple
app.post('/send', async (req, res) => {
    try {
        const { phone, message } = req.body;

        if (!sock) {
            return res.status(503).json({ success: false, error: 'WhatsApp no está conectado' });
        }
        if (!phone || !message) {
            return res.status(400).json({ success: false, error: 'Faltan parámetros: phone, message' });
        }

        const cleanPhone = phone.replace(/[^0-9]/g, '');
        const jid = `${cleanPhone}@s.whatsapp.net`;

        await sock.sendMessage(jid, { text: message });
        console.log(`📩 Mensaje enviado a ${cleanPhone}`);
        res.json({ success: true, message_id: Date.now().toString(), sent_to: jid });

    } catch (e) {
        console.error("❌ Error en /send:", e.message);
        res.status(500).json({ success: false, error: e.message });
    }
});

// Enviar notificación de pedido con botones
app.post('/send-order', async (req, res) => {
    try {
        const { phone, order_id, total, customer_name } = req.body;

        // VALIDACIÓN CRÍTICA: Evita que el bot truene si falta el teléfono
        if (!phone || typeof phone !== 'string') {
            console.error("⚠️ Intento de envío sin número de teléfono válido.");
            return res.status(400).json({ success: false, error: "El campo 'phone' es obligatorio y debe ser texto." });
        }

        if (!sock) {
            return res.status(503).json({ success: false, error: "WhatsApp no está vinculado todavía." });
        }

        const cleanPhone = phone.replace(/[^0-9]/g, '');
        const jid = `${cleanPhone}@s.whatsapp.net`;

        const buttons = [
            { buttonId: `acc_${order_id}`, buttonText: { displayText: 'Aceptar ✅' }, type: 1 },
            { buttonId: `rej_${order_id}`, buttonText: { displayText: 'Rechazar ❌' }, type: 1 }
        ];

        const buttonMessage = {
            text: `🍔 *NUEVO PEDIDO EN QUICKBITE*\n\n📦 *ID:* #${order_id}\n👤 *Cliente:* ${customer_name}\n💰 *Total:* $${total}\n\n¿Deseas aceptar este pedido?`,
            footer: 'Admin QuickBite v1.0',
            buttons: buttons,
            headerType: 1
        };

        await sock.sendMessage(jid, buttonMessage);
        console.log(`📩 Botones enviados al ${cleanPhone}`);
        res.json({ success: true, message: "Botones enviados correctamente." });

    } catch (e) {
        console.error("❌ Error en /send-order:", e.message);
        res.status(500).json({ success: false, error: e.message });
    }
});

// ========== INICIAR SERVIDORES ==========
initDB().then(() => connectToWhatsApp());

const PORT = 3031; 
app.listen(PORT, () => {
    console.log(`📡 Servidor Express de QuickBite corriendo en puerto ${PORT}`);
});