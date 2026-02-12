const http = require('http');
const WebSocket = require('ws');
const path = require('path');

// Configuración
const PORT = 5500;
const SERVER_URL = 'https://quickbite.com.mx';

// Crear servidor HTTP (Nginx maneja el SSL)
const server = http.createServer();

// Estructuras de datos
const clients = new Map(); // ws -> { userId, userType, orders: [] }
const orders = new Map();  // orderId -> { status, restaurant, courier, customer, subscribers: [] }

// Rutas HTTP
server.on('request', (req, res) => {
  // CORS headers
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
  
  if (req.method === 'OPTIONS') {
    res.writeHead(200);
    res.end();
    return;
  }
  
  if (req.url === '/') {
    res.writeHead(200, { 'Content-Type': 'text/html' });
    res.end(`<html>
      <head><title>Servidor WebSocket</title></head>
      <body>
        <h1>Servidor WebSocket para el sistema de delivery</h1>
        <p>Estado: Funcionando</p>
        <p>Puerto: ${PORT}</p>
        <p>Hora del servidor: ${new Date().toLocaleString()}</p>
      </body>
    </html>`);
  } else if (req.url === '/api/couriers-online' && req.method === 'GET') {
    // Endpoint para obtener repartidores conectados
    let couriersOnline = 0;
    for (const [ws, client] of clients.entries()) {
      if (client.userType === 'courier' && ws.readyState === WebSocket.OPEN) {
        couriersOnline++;
      }
    }
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ 
      success: true, 
      couriers_online: couriersOnline,
      timestamp: new Date().toISOString()
    }));
  } else if (req.url.startsWith('/api/update-status') && req.method === 'POST') {
    let body = '';
    req.on('data', chunk => { body += chunk.toString(); });
    req.on('end', () => {
      try {
        const data = JSON.parse(body);
        if (data.orderId && data.status !== undefined) {
          updateOrderStatus(data.orderId, data.status, data.courierLocation);
          res.writeHead(200, { 'Content-Type': 'application/json' });
          res.end(JSON.stringify({ success: true, message: 'Status updated' }));
        } else {
          res.writeHead(400, { 'Content-Type': 'application/json' });
          res.end(JSON.stringify({ success: false, message: 'Missing fields' }));
        }
      } catch (error) {
        res.writeHead(400, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ success: false, message: error.message }));
      }
    });
  } else {
    res.writeHead(404, { 'Content-Type': 'text/plain' });
    res.end('Not Found');
  }
});

// WebSocket Server
const wss = new WebSocket.Server({ server });

wss.on('connection', (ws) => {
  console.log('Cliente conectado');
  clients.set(ws, { userId: null, userType: null, orders: [] });

  ws.on('message', (message) => {
    try {
      const parsed = JSON.parse(message);
      const { event, data } = parsed;
      console.log('Mensaje recibido:', parsed);

      switch (event) {
        case 'register':
          handleRegister(ws, data);
          break;
        case 'subscribe_to_order':
          handleSubscribe(ws, data);
          break;
        case 'update_order_status':
          handleStatusUpdate(ws, data);
          break;
        case 'update_courier_location':
          handleLocationUpdate(ws, data);
          break;
        case 'get_available_orders':
          handleGetAvailableOrders(ws, data);
          break;
        case 'accept_order':
        case 1: // Aceptar pedido
          handleAcceptOrder(ws, data);
          break;
        case 2: // Preparando pedido
          handleStatusUpdate(ws, { orderId: data.orderId, status: 2 });
          break;
        case 3: // Pedido listo
          handleStatusUpdate(ws, { orderId: data.orderId, status: 3 });
          break;
        case 4: // Listo para entrega
          handleStatusUpdate(ws, { orderId: data.orderId, status: 4 });
          break;
        case 5: // En camino
          handleStatusUpdate(ws, { orderId: data.orderId, status: 5 });
          break;
        case 6: // Entregado
          handleStatusUpdate(ws, { orderId: data.orderId, status: 6 });
          break;
        default:
          console.log('Evento desconocido:', event);
          sendToClient(ws, 'message', { message: `Evento desconocido: ${event}` });
      }
    } catch (error) {
      console.error('Error al procesar mensaje:', error);
      sendToClient(ws, 'error', { message: 'Error al procesar mensaje', error: error.message });
    }
  });

  ws.on('close', () => {
    console.log('Cliente desconectado');
    const client = clients.get(ws);
    if (client && client.orders) {
      client.orders.forEach(orderId => {
        const order = orders.get(orderId);
        if (order && order.subscribers) {
          const index = order.subscribers.indexOf(ws);
          if (index !== -1) order.subscribers.splice(index, 1);
        }
      });
    }
    clients.delete(ws);
  });

  sendToClient(ws, 'message', {
    message: 'Conectado correctamente al WebSocket',
    timestamp: new Date().toISOString()
  });
});

// Funciones de manejo
function sendToClient(ws, event, data) {
  try {
    if (ws.readyState === WebSocket.OPEN) {
      const message = JSON.stringify({ event, data });
      console.log('Enviando mensaje:', message.substring(0, 200) + (message.length > 200 ? '...' : ''));
      ws.send(message);
    }
  } catch (error) {
    console.error('Error al enviar mensaje al cliente:', error);
  }
}


function handleRegister(ws, data) {
  const { userId, userType } = data;
  if (!userId || !userType) {
    sendToClient(ws, 'error', { message: 'Falta userId o userType' });
    return;
  }
  const client = clients.get(ws);
  client.userId = userId;
  client.userType = userType;
  clients.set(ws, client);

  console.log(`Cliente registrado: ${userId} (${userType})`);
  sendToClient(ws, 'registered', { userId, userType });

  if (userType === 'courier') {
    // Enviamos solo los pedidos con estado 4 (listos para entrega)
    const availableOrders = getOrdersWithStatus4();
    console.log('Enviando pedidos disponibles al courier:', availableOrders);
    
    if (availableOrders.length > 0) {
      sendToClient(ws, 'new_available_orders', { orders: availableOrders });
    } else {
      sendToClient(ws, 'no_available_orders', { message: 'No hay pedidos listos para entrega en este momento' });
    }
  }

  if (userType === 'business') {
    sendToClient(ws, 'active_orders', {
      orders: getActiveOrdersForBusiness(userId)
    });
  }
}

function handleSubscribe(ws, data) {
  const { orderId } = data;
  if (!orderId) {
    sendToClient(ws, 'error', { message: 'Falta orderId' });
    return;
  }
  const client = clients.get(ws);
  if (!client) {
    sendToClient(ws, 'error', { message: 'Cliente no registrado' });
    return;
  }
  if (!client.orders.includes(orderId)) {
    client.orders.push(orderId);
  }
  if (!orders.has(orderId)) {
    orders.set(orderId, { subscribers: [] });
  }
  const order = orders.get(orderId);
  if (!order.subscribers.includes(ws)) {
    order.subscribers.push(ws);
  }
  console.log(`Cliente ${client.userId} suscrito a orden ${orderId}`);
}

function handleStatusUpdate(ws, data) {
  const { orderId, status } = data;
  if (!orderId || status === undefined) {
    sendToClient(ws, 'error', { message: 'Faltan datos para actualizar estado' });
    return;
  }
  
  const client = clients.get(ws);
  if (!client) {
    sendToClient(ws, 'error', { message: 'Cliente no registrado' });
    return;
  }
  
  updateOrderStatus(orderId, status, data.courierLocation || null);
  
  // Si un negocio marca un pedido como "listo para entrega" (estado 4), notificar a todos los repartidores
  if (client.userType === 'business' && status === 4) {
    notifyAvailableOrderToCouriers(orderId);
  }
  
  sendToClient(ws, 'status_updated', { orderId, status });
}

function handleLocationUpdate(ws, data) {
  const { orderId, location } = data;
  if (!orderId || !location) {
    sendToClient(ws, 'error', { message: 'Faltan datos para actualizar ubicación' });
    return;
  }
  
  const client = clients.get(ws);
  if (!client || client.userType !== 'courier') {
    sendToClient(ws, 'error', { message: 'Solo repartidores pueden actualizar ubicación' });
    return;
  }
  
  if (!orders.has(orderId)) {
    sendToClient(ws, 'error', { message: 'Orden no encontrada' });
    return;
  }
  
  const order = orders.get(orderId);
  order.courierLocation = location;
  
  // Notificar a los suscriptores del pedido sobre la nueva ubicación
  order.subscribers.forEach(subscriberWs => {
    sendToClient(subscriberWs, 'courier_location_updated', { orderId, location });
  });
  
  sendToClient(ws, 'location_updated', { orderId });
}

function handleGetAvailableOrders(ws, data) {
  const client = clients.get(ws);
  if (!client || client.userType !== 'courier') {
    sendToClient(ws, 'error', { message: 'Solo repartidores pueden solicitar pedidos disponibles' });
    return;
  }
  
  const availableOrders = getOrdersWithStatus4();
  console.log('Respondiendo a solicitud de pedidos disponibles:', availableOrders);
  
  if (availableOrders.length > 0) {
    sendToClient(ws, 'available_orders', { orders: availableOrders });
  } else {
    sendToClient(ws, 'no_available_orders', { message: 'No hay pedidos disponibles en este momento' });
  }
}

function handleAcceptOrder(ws, data) {
  const { orderId } = data;
  if (!orderId) {
    sendToClient(ws, 'error', { message: 'Falta orderId' });
    return;
  }
  
  const client = clients.get(ws);
  if (!client || client.userType !== 'courier') {
    sendToClient(ws, 'error', { message: 'Solo repartidores pueden aceptar pedidos' });
    return;
  }
  
  if (!orders.has(orderId)) {
    sendToClient(ws, 'error', { message: 'Orden no encontrada' });
    return;
  }
  
  const order = orders.get(orderId);
  if (order.status !== 4) {
    sendToClient(ws, 'error', { message: 'Este pedido no está listo para ser recogido' });
    return;
  }
  
  // Verificar si el pedido ya está asignado a otro repartidor
  if (order.courier && order.courier !== client.userId) {
    sendToClient(ws, 'error', { message: 'Este pedido ya ha sido asignado a otro repartidor' });
    return;
  }
  
  // Asignar el pedido al repartidor
  order.courier = client.userId;
  order.status = 5; // En camino
  
  // Suscribir al repartidor a actualizaciones del pedido
  if (!client.orders.includes(orderId)) {
    client.orders.push(orderId);
  }
  
  if (!order.subscribers.includes(ws)) {
    order.subscribers.push(ws);
  }
  
  // Notificar a los suscriptores
  order.subscribers.forEach(subscriberWs => {
    sendToClient(subscriberWs, 'order_status_updated', { 
      orderId, 
      status: 5, // En camino
      courierId: client.userId 
    });
  });
  
  // Notificar a otros repartidores que el pedido ya no está disponible
  notifyOrderNoLongerAvailable(orderId);
  
  sendToClient(ws, 'order_accepted', { orderId });
}

function updateOrderStatus(orderId, status, courierLocation) {
  if (!orders.has(orderId)) {
    orders.set(orderId, { status: status, subscribers: [] });
  }
  const order = orders.get(orderId);
  order.status = status;
  if (courierLocation) {
    order.courierLocation = courierLocation;
  }
  
  // Notificar a los suscriptores
  order.subscribers.forEach(subscriberWs => {
    sendToClient(subscriberWs, 'order_status_updated', { 
      orderId, 
      status, 
      courierLocation: order.courierLocation 
    });
  });
  
  console.log(`Actualizado estado del pedido ${orderId} a ${status}`);
}

function getActiveOrdersForBusiness(businessId) {
  return Array.from(orders.entries())
    .filter(([id, order]) => order.restaurant === businessId)
    .map(([id, order]) => ({
      id,
      status: order.status,
      courier: order.courier,
      customer: order.customer
    }));
}

// Obtener pedidos con estado 4 (listos para entrega)
function getOrdersWithStatus4() {
  const result = [];
  
  orders.forEach((order, orderId) => {
    // Verificar que el pedido tenga estado 4 y que no esté asignado a un repartidor
    if (order.status === 4 && !order.courier) {
      result.push({
        id_pedido: parseInt(orderId),
        restaurante: order.restaurant || 'Restaurante ' + orderId,
        cliente: order.customer || 'Cliente ' + orderId,
        direccion_entrega: order.deliveryAddress || 'Dirección ' + orderId,
        distancia: order.distance || 1800,
        tiempo_restante: order.timeRemaining || 25
      });
    }
  });
  
  return result;
}

function notifyAvailableOrderToCouriers(orderId) {
  const order = orders.get(orderId);
  if (!order) return;
  
  const availableOrder = {
    id_pedido: parseInt(orderId),
    restaurante: order.restaurant || 'Restaurante ' + orderId,
    cliente: order.customer || 'Cliente ' + orderId,
    direccion_entrega: order.deliveryAddress || 'Dirección ' + orderId,
    distancia: order.distance || 1800,
    tiempo_restante: order.timeRemaining || 25
  };
  
  // Notificar a todos los repartidores conectados
  for (const [ws, client] of clients.entries()) {
    if (client.userType === 'courier') {
      sendToClient(ws, 'new_available_order', { order: availableOrder });
    }
  }
}

function notifyOrderNoLongerAvailable(orderId) {
  // Notificar a todos los repartidores (excepto el que aceptó) que el pedido ya no está disponible
  for (const [ws, client] of clients.entries()) {
    if (client.userType === 'courier') {
      sendToClient(ws, 'order_no_longer_available', { orderId });
    }
  }
}

// Escuchar
server.listen(PORT, '0.0.0.0', () => {
  console.log(`Servidor WSS y API corriendo en puerto ${PORT}`);
});