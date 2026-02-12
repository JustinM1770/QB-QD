// order-tracking.js - Cliente WebSocket para seguimiento de pedidos en tiempo real
console.log('Cargando cliente OrderTrackingClient...');

class OrderTrackingClient {
  constructor(userId, orderId) {
    console.log('Inicializando OrderTrackingClient para usuario:', userId, 'pedido:', orderId);
    this.userId = userId;
    this.orderId = orderId;
    this.socket = null;
    this.isConnected = false;
    this.reconnectAttempts = 0;
    this.maxReconnectAttempts = 10;
    this.reconnectTimeout = null;
    
    this.callbacks = {
      onConnect: () => {},
      onDisconnect: () => {},
      onOrderStatusUpdate: () => {},
      onCourierLocationUpdate: () => {},
      onError: () => {}
    };

    // Referencias al mapa y marcadores (si se están usando)
    this.map = null;
    this.restaurantMarker = null;
    this.deliveryMarker = null;
    this.courierMarker = null;
    this.routePolyline = null;
  }

  connect() {
    // URL CORREGIDA: usar la ruta que está configurada en Nginx
    const WEBSOCKET_SERVER_URL = 'wss://quickbite.com.mx/ws/';
    console.log('Iniciando conexión WebSocket a:', WEBSOCKET_SERVER_URL);
    
    try {
      this.socket = new WebSocket(WEBSOCKET_SERVER_URL);
      console.log('WebSocket creado, esperando conexión...');

      this.socket.onopen = () => {
        console.log('✅ Conexión WebSocket establecida con éxito');
        this.isConnected = true;
        this.reconnectAttempts = 0;

        // Registrar al usuario
        this.sendMessage('register', {
          userId: this.userId,
          userType: 'customer'
        });

        // Suscribirse a actualizaciones del pedido
        this.sendMessage('subscribe_to_order', {
          orderId: this.orderId,
          userId: this.userId
        });

        this.callbacks.onConnect();
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
        this.callbacks.onError(error);
      };

      this.socket.onclose = (event) => {
        console.log('Conexión WebSocket cerrada. Código:', event.code, 'Razón:', event.reason);
        this.isConnected = false;
        this.callbacks.onDisconnect();
        
        // Intentar reconectar si no fue un cierre limpio y no se ha alcanzado el máximo de intentos
        if (!event.wasClean && this.reconnectAttempts < this.maxReconnectAttempts) {
          const delay = Math.min(1000 * Math.pow(1.5, this.reconnectAttempts), 30000);
          console.log(`Intentando reconectar en ${delay}ms (intento ${this.reconnectAttempts + 1} de ${this.maxReconnectAttempts})...`);
          
          this.reconnectTimeout = setTimeout(() => {
            this.reconnectAttempts++;
            this.connect();
          }, delay);
        } else if (this.reconnectAttempts >= this.maxReconnectAttempts) {
          console.error('❌ Máximo número de intentos de reconexión alcanzado. Deteniendo intentos.');
        }
      };
    } catch (error) {
      console.error('Error al inicializar WebSocket:', error);
      this.callbacks.onError(error);
    }
  }

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

  handleMessage(message) {
    console.log('Procesando mensaje:', message);
    const { event, data } = message;
    
    switch (event) {
      case 'registered':
        console.log('Usuario registrado correctamente:', data);
        break;
        
      case 'order_initial_status':
      case 'initial_status':
        console.log('Estado inicial del pedido recibido:', data);
        this.updateOrderStatus(data);
        if (data.courierLocation) {
          this.updateCourierLocation(data.courierLocation);
        }
        break;
        
      case 'order_status_update':
      case 'status_update':
        console.log('Actualización del estado del pedido:', data);
        this.updateOrderStatus(data);
        this.callbacks.onOrderStatusUpdate(data);
        break;
        
      case 'courier_location_update':
      case 'location_update':
        console.log('Actualización de ubicación del repartidor:', data);
        this.updateCourierLocation(data);
        this.callbacks.onCourierLocationUpdate(data);
        break;
        
      default:
        console.warn('Evento desconocido:', event, data);
        
        // Intentar detectar si es un mensaje con formato diferente pero con información útil
        if (data && data.status !== undefined) {
          console.log('Se detectó información de estado en formato alternativo, procesando...');
          this.updateOrderStatus(data);
          this.callbacks.onOrderStatusUpdate(data);
        }
        
        if (data && data.courierLocation) {
          console.log('Se detectó información de ubicación en formato alternativo, procesando...');
          this.updateCourierLocation(data.courierLocation);
          this.callbacks.onCourierLocationUpdate(data.courierLocation);
        }
    }
  }

  updateOrderStatus(data) {
    console.log('Actualizando UI con nuevo estado:', data);
    
    // Si no hay ID de pedido o no coincide con el pedido actual, ignorar
    if (!data.orderId || data.orderId != this.orderId) {
      console.warn('ID de pedido no coincide:', data.orderId, this.orderId);
      return;
    }
    
    // Actualizar el estado en la interfaz de usuario
    if (data.status !== undefined) {
      // Estados: 1=creado, 2=confirmado, 3=preparando, 4=listo, 5=en camino, 6=entregado
      const estado = parseInt(data.status);
      
      // 1. Actualizar el título de estado
      const statusTitle = document.querySelector('.status-title');
      if (statusTitle) {
        const estadosTexto = {
          1: 'Pedido creado',
          2: 'El negocio está confirmando tu pedido',
          3: 'El negocio está preparando tu pedido',
          4: 'Tu pedido está listo para ser recogido',
          5: 'El repartidor va hacia tu dirección',
          6: 'Tu pedido ha sido entregado'
        };
        statusTitle.textContent = data.statusText || estadosTexto[estado] || 'Estado desconocido';
      }

      // 2. Actualizar el tiempo estimado
      const timeElement = document.querySelector('.tiempo-entrega');
      if (timeElement) {
        const tiemposEntrega = {
          1: "30-40 minutos",
          2: "25-35 minutos",
          3: "20-30 minutos",
          4: "15-20 minutos",
          5: "5-10 minutos",
          6: "Entregado"
        };
        timeElement.innerHTML = `<i class="fas fa-clock me-1"></i> Tiempo estimado: ${data.estimatedTime || tiemposEntrega[estado] || 'Tiempo no disponible'}`;
      }

      // 3. Actualizar la línea de tiempo
      this.updateTimeline(estado);
      
      // 4. Si el pedido acaba de pasar a "entregado", mostrar mensaje
      if (estado === 6) {
        setTimeout(() => {
          if (!document.hidden) {
            console.log('🎉 Pedido entregado exitosamente');
          }
        }, 1000);
      }
      
      // 5. Si el pedido está en camino (estado 5), asegurarse de mostrar el repartidor en el mapa
      if (estado === 5 && window.map && !window.deliverymanMarker && data.courierLocation) {
        this.updateCourierLocation(data.courierLocation);
      }
    }
  }

  updateCourierLocation(location) {
    console.log('Actualizando ubicación del repartidor:', location);
    
    // Verificar si tenemos mapa y coordenadas válidas
    if (!window.map || !location || !location.lat || !location.lng) {
      console.warn('No se puede actualizar ubicación: mapa o coordenadas no disponibles');
      return;
    }

    const { lat, lng } = location;

    // Crear o actualizar el marcador del repartidor
    if (!window.deliverymanMarker) {
      console.log('Creando nuevo marcador de repartidor');
      const deliverymanIcon = L.divIcon({
        html: '<i class="fas fa-motorcycle fa-2x" style="color: #FF6B00;"></i>',
        className: 'map-icon',
        iconSize: [40, 40],
        iconAnchor: [20, 20]
      });

      window.deliverymanMarker = L.marker([lat, lng], { icon: deliverymanIcon })
        .addTo(window.map)
        .bindPopup('Tu repartidor');
    } else {
      console.log('Actualizando posición del marcador existente');
      window.deliverymanMarker.setLatLng([lat, lng]);
    }

    // Actualizar la polilínea de la ruta si tenemos todos los puntos necesarios
    this.updateRoutePolyline();
    
    // Ajustar los límites del mapa para mostrar todos los marcadores
    this.updateMapBounds();
  }

  updateRoutePolyline() {
    if (!window.map || !window.restaurantMarker || !window.deliveryMarker) {
      console.warn('No se puede actualizar ruta: faltan marcadores base');
      return;
    }
    
    const restaurantPos = window.restaurantMarker.getLatLng();
    const deliveryPos = window.deliveryMarker.getLatLng();
    
    // Si tenemos repartidor, trazar ruta completa
    if (window.deliverymanMarker) {
      const courierPos = window.deliverymanMarker.getLatLng();
      
      // Eliminar polilínea anterior si existe
      if (window.polyline) {
        window.polyline.remove();
      }
      
      // Crear nueva polilínea con los tres puntos
      window.polyline = L.polyline([
        [restaurantPos.lat, restaurantPos.lng],
        [courierPos.lat, courierPos.lng],
        [deliveryPos.lat, deliveryPos.lng]
      ], { color: 'blue' }).addTo(window.map);
      
      console.log('Polilínea de ruta actualizada con punto de repartidor');
    }
  }

  updateMapBounds() {
    if (!window.map) return;

    const bounds = L.latLngBounds([]);

    if (window.restaurantMarker) bounds.extend(window.restaurantMarker.getLatLng());
    if (window.deliveryMarker) bounds.extend(window.deliveryMarker.getLatLng());
    if (window.deliverymanMarker) bounds.extend(window.deliverymanMarker.getLatLng());

    if (!bounds.isEmpty()) {
      window.map.fitBounds(bounds.pad(0.2));
      console.log('Límites del mapa actualizados para mostrar todos los marcadores');
    }
  }

  updateTimeline(status) {
    // Actualizar la línea de tiempo visual según el estado actual
    for (let i = 1; i <= 6; i++) {
      const step = document.querySelector(`.status-step:nth-child(${i})`);
      if (!step) continue;
      
      const stepIcon = step.querySelector('.step-icon');
      const stepText = step.querySelector('.step-text');

      if (stepIcon && stepText) {
        // Eliminar todas las clases actuales
        stepIcon.classList.remove('active', 'completed');
        stepText.classList.remove('active');

        // Aplicar clases según el estado actual
        if (i < status) {
          stepIcon.classList.add('completed');
        } else if (i === status) {
          stepIcon.classList.add('active');
          stepText.classList.add('active');
        }
      }
    }
    
    // Actualizar animación del icono de repartidor para el estado 5
    const motoIcons = document.querySelectorAll('.fa-motorcycle');
    motoIcons.forEach(icon => {
      if (status === 5) {
        icon.classList.add('delivery-icon');
      } else {
        icon.classList.remove('delivery-icon');
      }
    });
    
    console.log('Línea de tiempo actualizada para estado:', status);
  }

  // Configurar callbacks para eventos
  on(event, callback) {
    if (this.callbacks.hasOwnProperty('on' + event)) {
      this.callbacks['on' + event] = callback;
    } else if (this.callbacks.hasOwnProperty(event)) {
      this.callbacks[event] = callback;
    } else {
      console.warn(`El evento "${event}" no está soportado`);
    }
    return this;
  }

  // Desconectar el WebSocket
  disconnect() {
    console.log('Desconectando cliente WebSocket');
    
    if (this.reconnectTimeout) {
      clearTimeout(this.reconnectTimeout);
      this.reconnectTimeout = null;
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
  window.OrderTrackingClient = OrderTrackingClient;
  console.log('OrderTrackingClient disponible globalmente');
}

async function sendStatusNotification(newStatus) {
    try {
        const response = await fetch('/api/send-push-notification.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                user_id: config.userId,
                order_id: config.orderId,
                new_status: newStatus,
                business_name: '<?php echo addslashes($negocio->nombre ?? "el restaurante"); ?>'
            })
        });
        
        const result = await response.json();
        console.log('Notificación enviada:', result);
    } catch (error) {
        console.error('Error enviando notificación:', error);
    }
}

// Pedir permiso para notificaciones después de 5 segundos
setTimeout(() => {
    if (Notification.permission === 'default' && window.pushManager) {
        const notificationBanner = document.createElement('div');
        notificationBanner.style.cssText = `
            position: fixed; top: 20px; right: 20px; 
            background: linear-gradient(135deg, #0165FF, #4285F4);
            color: white; padding: 15px 20px; border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            z-index: 1000; max-width: 300px;
            animation: slideIn 0.3s ease;
        `;
        
        notificationBanner.innerHTML = `
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-bell" style="font-size: 20px;"></i>
                <div>
                    <div style="font-weight: 600; margin-bottom: 5px;">
                        Recibe notificaciones
                    </div>
                    <div style="font-size: 12px; opacity: 0.9;">
                        Te avisaremos cuando cambie el estado de tu pedido
                    </div>
                </div>
            </div>
            <div style="margin-top: 10px; display: flex; gap: 10px;">
                <button onclick="enableNotifications()" style="
                    background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3);
                    color: white; padding: 5px 15px; border-radius: 5px; cursor: pointer;
                    font-size: 12px; font-weight: 500;
                ">Activar</button>
                <button onclick="this.parentElement.parentElement.remove()" style="
                    background: transparent; border: 1px solid rgba(255,255,255,0.3);
                    color: white; padding: 5px 15px; border-radius: 5px; cursor: pointer;
                    font-size: 12px;
                ">Después</button>
            </div>
        `;
        
        document.body.appendChild(notificationBanner);
        
        // Remover automáticamente después de 15 segundos
        setTimeout(() => {
            if (notificationBanner.parentElement) {
                notificationBanner.remove();
            }
        }, 15000);
    }
}, 5000);

// Función para activar notificaciones
window.enableNotifications = async function() {
    if (window.pushManager) {
        const success = await window.pushManager.requestPermission();
        if (success) {
            showNotification('Notificaciones activadas correctamente', 'success');
            document.querySelector('[onclick="enableNotifications()"]')?.parentElement?.parentElement?.remove();
        } else {
            showNotification('No se pudieron activar las notificaciones', 'warning');
        }
    }
};

// Agregar CSS para la animación
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
`;
document.head.appendChild(style);