// courier-tracking.js - Cliente WebSocket para repartidores
console.log('Cargando cliente CourierClient...');

// URL del servidor WebSocket (ajustada para tu servidor)
const WEBSOCKET_SERVER_URL = 'wss://quickbite.com.mx/ws/';

class CourierClient {
  constructor(courierId) {
    console.log('Inicializando CourierClient para repartidor ID:', courierId);
    this.courierId = courierId;
    this.orderId = null;
    this.socket = null;
    this.isConnected = false;
    this.isTracking = false;
    this.watchId = null;
    this.currentPosition = null;
    this.updateInterval = null;
    this.reconnectAttempts = 0;
    this.maxReconnectAttempts = 10;
    this.reconnectTimeout = null;
    this.lastOrdersReceived = 0;

    this.callbacks = {
      connect: () => {},
      disconnect: () => {},
      orderAssigned: () => {},
      orderStatusUpdate: () => {},
      locationUpdate: () => {},
      newAvailableOrders: () => {},
      error: () => {},
      message: () => {}
    };
  }

  // Inicializar la conexión WebSocket
  connect() {
    console.log('Iniciando conexión WebSocket a:', WEBSOCKET_SERVER_URL);
    
    try {
      this.socket = new WebSocket(WEBSOCKET_SERVER_URL);
      console.log('WebSocket creado, esperando conexión...');

      this.socket.onopen = () => {
        console.log('✅ Conexión WebSocket establecida con éxito');
        this.isConnected = true;
        this.reconnectAttempts = 0;

        // Registrar al repartidor (ajustado para tu servidor)
        this.sendMessage('register', {
          userId: this.courierId,
          userType: 'courier'
        });

        this.callbacks.connect();
        
        // Programar solicitudes periódicas de pedidos disponibles
        this._startOrdersPolling();
      };

      this.socket.onmessage = (event) => {
        console.log('Mensaje recibido:', event.data);
        try {
          const message = JSON.parse(event.data);
          this.handleMessage(message);
        } catch (error) {
          console.error('Error al parsear mensaje:', error);
        }
      };

      this.socket.onerror = (error) => {
        console.error('❌ Error en WebSocket:', error);
        this.callbacks.error(error);
        this._usarRespaldoHTTP();
      };

      this.socket.onclose = (event) => {
        console.log('Conexión WebSocket cerrada. Código:', event.code, 'Razón:', event.reason);
        this.isConnected = false;
        this.callbacks.disconnect();
        this._usarRespaldoHTTP();
        
        // Intentar reconectar
        if (!event.wasClean && this.reconnectAttempts < this.maxReconnectAttempts) {
          const delay = Math.min(1000 * Math.pow(1.5, this.reconnectAttempts), 30000);
          console.log(`Intentando reconectar en ${delay}ms (intento ${this.reconnectAttempts + 1})...`);
          
          this.reconnectTimeout = setTimeout(() => {
            this.reconnectAttempts++;
            this.connect();
          }, delay);
        }
      };
    } catch (error) {
      console.error('Error al inicializar WebSocket:', error);
      this.callbacks.error(error);
      this._usarRespaldoHTTP();
    }
  }
  
  // Iniciar sondeo periódico de pedidos disponibles
  _startOrdersPolling() {
    if (this._ordersPollingInterval) {
      clearInterval(this._ordersPollingInterval);
    }
    
    setTimeout(() => {
      this.requestAvailableOrders();
    }, 1000);
    
    this._ordersPollingInterval = setInterval(() => {
      console.log('Solicitando actualización periódica de pedidos disponibles');
      this.requestAvailableOrders();
    }, 30000);
  }
  
  // Método para usar respaldo HTTP cuando WebSocket falla
  _usarRespaldoHTTP() {
    console.log('📡 Usando respaldo HTTP para obtener pedidos...');
    const now = Date.now();
    if (now - this.lastOrdersReceived < 10000) {
      console.log('Se omite solicitud HTTP porque ya se recibieron pedidos recientemente');
      return;
    }
    
    // Esta función la implementaremos en el dashboard
    if (typeof window !== 'undefined' && window.obtenerPedidosDisponiblesHTTP) {
      window.obtenerPedidosDisponiblesHTTP()
        .then(data => {
          console.log('Pedidos disponibles recibidos via HTTP:', data);
          this.lastOrdersReceived = Date.now();
          if (data && data.orders) {
            this.callbacks.newAvailableOrders(data);
          } else if (Array.isArray(data)) {
            this.callbacks.newAvailableOrders({ orders: data });
          }
        })
        .catch(error => {
          console.error('Error al solicitar pedidos via HTTP:', error);
        });
    }
  }

  // Enviar un mensaje al servidor WebSocket
  sendMessage(event, data) {
    if (this.isConnected && this.socket && this.socket.readyState === WebSocket.OPEN) {
      const message = JSON.stringify({ event, data });
      console.log('Enviando mensaje:', message);
      this.socket.send(message);
      return true;
    } else {
      console.warn('Intentando enviar mensaje sin conexión activa:', event);
      return false;
    }
  }

  // Manejar los mensajes recibidos (ajustado para tu servidor)
  handleMessage(message) {
    console.log('Procesando mensaje:', message);
    const { event, data } = message;

    switch (event) {
      case 'registered':
        console.log('Repartidor registrado correctamente:', data);
        setTimeout(() => {
          this.requestAvailableOrders();
        }, 500);
        break;

      case 'new_available_orders':
      case 'available_orders':
        console.log('Nuevos pedidos disponibles:', data);
        this.lastOrdersReceived = Date.now();
        
        if (data && data.orders) {
          this.callbacks.newAvailableOrders(data);
        } else if (Array.isArray(data)) {
          this.callbacks.newAvailableOrders({ orders: data });
        }
        this.playNotificationSound();
        break;
        
      case 'new_available_order':
        console.log('Nuevo pedido individual disponible:', data);
        this.lastOrdersReceived = Date.now();
        
        if (data && data.order) {
          this.callbacks.newAvailableOrders({ orders: [data.order] });
        }
        this.playNotificationSound();
        break;

      case 'order_accepted':
        console.log('Pedido aceptado exitosamente:', data);
        this.orderId = data.orderId;
        this.callbacks.orderAssigned(data);
        if (this.orderId && !this.isTracking) {
          this.startLocationTracking();
        }
        break;

      case 'order_status_updated':
        console.log('Actualización del estado del pedido:', data);
        this.callbacks.orderStatusUpdate(data);
        
        if (data.status === 6) {
          this.stopLocationTracking();
        }
        break;

      case 'location_updated':
        console.log('Ubicación actualizada correctamente:', data);
        this.callbacks.locationUpdate({
          success: true,
          position: this.currentPosition
        });
        break;
        
      case 'no_available_orders':
        console.log('No hay pedidos disponibles en este momento');
        this.lastOrdersReceived = Date.now();
        this.callbacks.newAvailableOrders({ orders: [] });
        break;

      case 'error':
        console.error('Error del servidor:', data);
        this.callbacks.error(data);
        break;

      case 'message':
        console.log('Mensaje del servidor:', data);
        if (data.orders) {
          this.lastOrdersReceived = Date.now();
          this.callbacks.newAvailableOrders(data);
        }
        break;

      default:
        console.warn('Evento desconocido:', event, data);
        if (data && (data.orders || Array.isArray(data))) {
          const orders = data.orders || data;
          if (Array.isArray(orders)) {
            console.log('Detectados posibles pedidos en mensaje no estándar');
            this.lastOrdersReceived = Date.now();
            this.callbacks.newAvailableOrders({ orders: orders });
          }
        }
    }
  }

  // Configurar callbacks para eventos
  on(event, callback) {
    const eventMap = {
      'onConnect': 'connect',
      'onDisconnect': 'disconnect',
      'onMessage': 'message',
      'onNewAvailableOrders': 'newAvailableOrders',
      'onOrderStatusUpdate': 'orderStatusUpdate',
      'onLocationUpdate': 'locationUpdate',
      'onOrderAssigned': 'orderAssigned',
      'onError': 'error'
    };

    const mappedEvent = eventMap[event];
    if (mappedEvent && typeof callback === 'function') {
      this.callbacks[mappedEvent] = callback;
      console.log(`Callback configurado para evento: ${event} -> ${mappedEvent}`);
    } else {
      console.warn(`El evento "${event}" no está soportado`);
    }
    return this;
  }

  // Establecer el ID del pedido activo
  setActiveOrder(orderId) {
    this.orderId = orderId;
    console.log('Estableciendo pedido activo:', orderId);
    
    if (this.orderId && this.isConnected) {
      this.sendMessage('subscribe_to_order', {
        orderId: this.orderId
      });
    }
    
    return this;
  }

  // Iniciar seguimiento de ubicación
  startLocationTracking() {
    console.log('Iniciando seguimiento de ubicación');
    if (!this.orderId) {
      console.warn('No hay pedido activo para rastrear.');
      return false;
    }

    if (this.isTracking) {
      console.warn('Ya se está rastreando la ubicación.');
      return false;
    }

    if (!navigator.geolocation) {
      this.callbacks.error({
        message: 'Geolocalización no soportada en este dispositivo'
      });
      return false;
    }

    this.isTracking = true;
    
    const sendPosition = (position) => {
      if (!this.orderId) return;
      
      this.currentPosition = {
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
        accuracy: position.coords.accuracy,
        timestamp: new Date().toISOString()
      };
      
      console.log('Posición actual:', this.currentPosition);
      
      if (this.isConnected) {
        this.sendMessage('update_courier_location', {
          orderId: this.orderId,
          location: this.currentPosition
        });
      }
    };

    const handleError = (error) => {
      let message;
      switch (error.code) {
        case error.PERMISSION_DENIED:
          message = 'Usuario denegó la solicitud de geolocalización.';
          break;
        case error.POSITION_UNAVAILABLE:
          message = 'Información de ubicación no disponible.';
          break;
        case error.TIMEOUT:
          message = 'Tiempo de espera agotado para obtener la ubicación.';
          break;
        default:
          message = 'Error desconocido de geolocalización.';
      }
      
      console.error('Error de geolocalización:', message);
      this.callbacks.error({
        message,
        code: error.code
      });
    };

    const options = {
      enableHighAccuracy: true,
      timeout: 10000,
      maximumAge: 0
    };

    navigator.geolocation.getCurrentPosition(sendPosition, handleError, options);
    this.watchId = navigator.geolocation.watchPosition(sendPosition, handleError, options);
    
    this.updateInterval = setInterval(() => {
      navigator.geolocation.getCurrentPosition(sendPosition, handleError, options);
    }, 15000);
    
    return true;
  }

  // Detener seguimiento de ubicación
  stopLocationTracking() {
    console.log('Deteniendo seguimiento de ubicación');
    if (!this.isTracking) {
      return false;
    }

    this.isTracking = false;
    
    if (this.watchId !== null && navigator.geolocation) {
      navigator.geolocation.clearWatch(this.watchId);
      this.watchId = null;
    }
    
    if (this.updateInterval) {
      clearInterval(this.updateInterval);
      this.updateInterval = null;
    }
    
    return true;
  }

  // Actualizar estado del pedido
  updateOrderStatus(status) {
    console.log('Actualizando estado del pedido a:', status);
    if (!this.orderId) {
      console.warn('No hay pedido activo para actualizar.');
      return false;
    }

    if (this.isConnected) {
      return this.sendMessage('update_order_status', {
        orderId: this.orderId,
        status: status
      });
    } else {
      console.log('Sin conexión WebSocket, usando método POST');
      return false; // El dashboard manejará el POST
    }
  }

  // Aceptar un pedido disponible
  acceptOrder(orderId) {
    console.log('Aceptando pedido:', orderId);
    if (this.isConnected) {
      const result = this.sendMessage('accept_order', {
        orderId: orderId
      });
      
      if (result) {
        this.setActiveOrder(orderId);
      }
      
      return result;
    } else {
      console.log('Sin conexión WebSocket para aceptar pedido');
      return false;
    }
  }

  // Solicitar pedidos disponibles
  requestAvailableOrders() {
    console.log('Solicitando pedidos disponibles');
    if (this.isConnected) {
      const sent = this.sendMessage('get_available_orders', {});
      console.log('Solicitud de pedidos enviada:', sent);
      
      setTimeout(() => {
        const now = Date.now();
        if (now - this.lastOrdersReceived > 5000) {
          console.log('No se recibieron pedidos vía WebSocket, usando respaldo HTTP...');
          this._usarRespaldoHTTP();
        }
      }, 3000);
      
      return sent;
    } else {
      this._usarRespaldoHTTP();
      return true;
    }
  }

  // Reproducir sonido de notificación
  playNotificationSound() {
    console.log('Reproduciendo sonido de notificación');
    try {
      const audio = new Audio('https://cdn.pixabay.com/audio/2024/10/25/audio_9b7a3774d3.mp3');
      audio.volume = 0.7;
      const playPromise = audio.play();
      
      if (playPromise !== undefined) {
        playPromise
          .then(() => {
            console.log('Notificación reproducida correctamente');
          })
          .catch(error => {
            if (error.name !== 'NotAllowedError') {
              console.warn('Error al reproducir sonido:', error);
            }
          });
      }
    } catch (error) {
      console.warn('Error al crear objeto de audio:', error);
    }
  }

  // Forzar actualización de pedidos
  forceOrdersRefresh() {
    console.log('Forzando actualización de pedidos disponibles');
    this.lastOrdersReceived = 0;
    this.requestAvailableOrders();
    setTimeout(() => this._usarRespaldoHTTP(), 500);
    return true;
  }

  // Desconectar el WebSocket
  disconnect() {
    console.log('Desconectando cliente WebSocket');
    this.stopLocationTracking();
    
    if (this.reconnectTimeout) {
      clearTimeout(this.reconnectTimeout);
      this.reconnectTimeout = null;
    }
    
    if (this._ordersPollingInterval) {
      clearInterval(this._ordersPollingInterval);
      this._ordersPollingInterval = null;
    }
    
    if (this.socket) {
      this.socket.close(1000, "Cierre voluntario");
      this.socket = null;
      this.isConnected = false;
    }
  }
}

// Exportar cliente para uso en navegador
if (typeof window !== 'undefined') {
  window.CourierClient = CourierClient;
  console.log('CourierClient disponible globalmente');
}