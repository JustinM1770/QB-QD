// business-orders.js - Cliente WebSocket para negocios
const WEBSOCKET_SERVER_URL = 'wss://quickbite.com.mx/ws/';// Cambiar a wss:// si usas SSL

class BusinessClient {
  constructor(businessId) {
    this.businessId = businessId;
    this.socket = null;
    this.isConnected = false;
    this.activeOrders = new Map(); // Mapa de pedidos activos: orderId -> orderData

    this.callbacks = {
      onConnect: () => {},
      onDisconnect: () => {},
      onNewOrder: () => {},
      onOrderStatusUpdate: () => {},
      onCourierAssigned: () => {},
      onError: () => {}
    };
  }

  // Inicializar la conexión WebSocket
  connect() {
    this.socket = new WebSocket(WEBSOCKET_SERVER_URL);

    this.socket.onopen = () => {
      console.log('Conexión WebSocket establecida');
      this.isConnected = true;

      // Registrar al negocio
      this.sendMessage('register', {
        userId: this.businessId,
        userType: 'business'
      });

      this.callbacks.onConnect();
    };

    this.socket.onmessage = (event) => {
      try {
        const message = JSON.parse(event.data);
        this.handleMessage(message);
      } catch (error) {
        console.error('Error al parsear mensaje:', error);
      }
    };

    this.socket.onerror = (error) => {
      console.error('Error en WebSocket:', error);
      this.callbacks.onError(error);
    };

    this.socket.onclose = () => {
      console.log('Conexión WebSocket cerrada');
      this.isConnected = false;
      this.callbacks.onDisconnect();

      // Intentar reconectar después de un tiempo
      setTimeout(() => {
        if (!this.isConnected) {
          this.connect();
        }
      }, 5000);
    };
  }

  // Enviar un mensaje al servidor WebSocket
  sendMessage(event, data) {
    if (this.isConnected && this.socket.readyState === WebSocket.OPEN) {
      const message = JSON.stringify({ event, data });
      this.socket.send(message);
    } else {
      console.warn('Intentando enviar mensaje sin conexión activa:', event);
    }
  }

  // Manejar los mensajes recibidos
  handleMessage(message) {
    const { event, data } = message;

    switch (event) {
      case 'registered':
        console.log('Negocio registrado correctamente:', data);
        break;

      case 'new_order':
        console.log('Nuevo pedido recibido:', data);
        if (data.orderId) {
          this.activeOrders.set(data.orderId, data);
          this.callbacks.onNewOrder(data);
          
          // Reproducir sonido de notificación si está disponible
          this.playNotificationSound();
        }
        break;

      case 'order_status_update':
        console.log('Actualización del estado del pedido:', data);
        if (data.orderId && this.activeOrders.has(data.orderId)) {
          const orderData = this.activeOrders.get(data.orderId);
          this.activeOrders.set(data.orderId, { ...orderData, ...data });
        }
        this.callbacks.onOrderStatusUpdate(data);
        break;

      case 'courier_assigned':
        console.log('Repartidor asignado al pedido:', data);
        if (data.orderId && this.activeOrders.has(data.orderId)) {
          const orderData = this.activeOrders.get(data.orderId);
          this.activeOrders.set(data.orderId, { 
            ...orderData, 
            courierId: data.courierId,
            courierName: data.courierName 
          });
        }
        this.callbacks.onCourierAssigned(data);
        break;

      case 'order_history':
        console.log('Historial de pedidos recibido:', data);
        if (Array.isArray(data.orders)) {
          data.orders.forEach(order => {
            if (order.status < 6) { // Si no está completado
              this.activeOrders.set(order.orderId, order);
            }
          });
        }
        break;

      default:
        console.warn('Evento desconocido:', event, data);
    }
  }

  // Configurar callbacks para eventos
  on(event, callback) {
    if (this.callbacks.hasOwnProperty(event)) {
      this.callbacks[event] = callback;
    } else {
      console.warn(`El evento "${event}" no está soportado`);
    }
    return this;
  }

  // Aceptar un pedido
  acceptOrder(orderId, estimatedTime) {
    this.sendMessage('update_order_status', {
      orderId,
      businessId: this.businessId,
      status: 2, // Pedido aceptado/confirmado
      estimatedTime
    });
  }

  // Rechazar un pedido
  rejectOrder(orderId, reason) {
    this.sendMessage('reject_order', {
      orderId,
      businessId: this.businessId,
      reason
    });
    
    // Eliminar de los pedidos activos
    if (this.activeOrders.has(orderId)) {
      this.activeOrders.delete(orderId);
    }
  }

  // Marcar pedido como en preparación
  startPreparingOrder(orderId) {
    this.sendMessage('update_order_status', {
      orderId,
      businessId: this.businessId,
      status: 3 // En preparación
    });
  }

  // Marcar pedido como listo para entrega
  markOrderReady(orderId) {
    this.sendMessage('update_order_status', {
      orderId,
      businessId: this.businessId,
      status: 4 // Listo para entrega
    });
  }

  // Solicitar todos los pedidos activos
  requestActiveOrders() {
    this.sendMessage('get_business_orders', {
      businessId: this.businessId
    });
  }

  // Obtener un pedido específico
  getOrder(orderId) {
    return this.activeOrders.get(orderId) || null;
  }

  // Obtener todos los pedidos activos
  getAllActiveOrders() {
    return Array.from(this.activeOrders.values());
  }

  // Reproducir sonido de notificación
  playNotificationSound() {
    try {
      const audio = new Audio('/assets/notification.mp3');
      audio.play().catch(e => console.warn('No se pudo reproducir el sonido de notificación:', e));
    } catch (error) {
      console.warn('Error al reproducir sonido de notificación:', error);
    }
  }

  // Cerrar la conexión
  disconnect() {
    if (this.socket) {
      this.socket.close();
      this.isConnected = false;
    }
  }
}

// Exportar cliente para uso en navegador
if (typeof window !== 'undefined') {
  window.BusinessClient = BusinessClient;
}
