// dashboard-websocket-integration.js
// Integración del CourierClient con el dashboard existente

console.log('🚀 Cargando integración WebSocket para dashboard...');

// Variables globales
let courierClient = null;
let currentOrderForTracking = null;

// ===========================
// INICIALIZACIÓN
// ===========================

function initializeWebSocketIntegration() {
    const userId = <?php echo $_SESSION['id_usuario'] ?? 'null'; ?>;
    
    if (!userId) {
        console.error('❌ No se pudo obtener ID de usuario');
        return;
    }
    
    console.log('🔌 Inicializando CourierClient para usuario:', userId);
    
    // Crear instancia del cliente
    courierClient = new CourierClient(userId);
    
    // Configurar eventos
    setupWebSocketEvents();
    
    // Conectar
    courierClient.connect();
    
    // Hacer disponible globalmente
    window.courierClient = courierClient;
    
    console.log('✅ WebSocket integration inicializada');
}

// ===========================
// CONFIGURACIÓN DE EVENTOS
// ===========================

function setupWebSocketEvents() {
    // Evento: Conexión establecida
    courierClient.on('onConnect', () => {
        console.log('✅ Conectado al sistema en tiempo real');
        updateConnectionStatus('connected');
        
        // Mostrar notificación de conexión
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Conectado',
                text: 'Sistema en tiempo real activado',
                timer: 2000,
                showConfirmButton: false,
                position: 'top-end',
                toast: true
            });
        }
    });
    
    // Evento: Desconexión
    courierClient.on('onDisconnect', () => {
        console.log('❌ Desconectado del sistema');
        updateConnectionStatus('disconnected');
        
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'Desconectado',
                text: 'Intentando reconectar...',
                timer: 3000,
                showConfirmButton: false,
                position: 'top-end',
                toast: true
            });
        }
    });
    
    // Evento: Nuevos pedidos disponibles
    courierClient.on('onNewAvailableOrders', (data) => {
        console.log('📦 Pedidos disponibles recibidos:', data);
        handleNewAvailableOrders(data.orders || []);
    });
    
    // Evento: Pedido asignado
    courierClient.on('onOrderAssigned', (data) => {
        console.log('✅ Pedido asignado:', data);
        handleOrderAssigned(data);
    });
    
    // Evento: Actualización de estado
    courierClient.on('onOrderStatusUpdate', (data) => {
        console.log('📊 Estado actualizado:', data);
        handleOrderStatusUpdate(data);
    });
    
    // Evento: Error
    courierClient.on('onError', (error) => {
        console.error('❌ Error WebSocket:', error);
        updateConnectionStatus('error');
        
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: error.message || 'Error desconocido',
                timer: 4000,
                showConfirmButton: false,
                position: 'top-end',
                toast: true
            });
        }
    });
}

// ===========================
// MANEJADORES DE EVENTOS
// ===========================

function handleNewAvailableOrders(orders) {
    console.log('🔄 Actualizando lista de pedidos disponibles:', orders);
    
    const container = document.getElementById('pedidosDisponibles');
    if (!container) {
        console.warn('⚠️ Container de pedidos disponibles no encontrado');
        return;
    }
    
    if (!orders || orders.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <h4>No hay pedidos disponibles</h4>
                <p>En este momento no hay pedidos disponibles en tu zona.</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = orders.map(order => `
        <div class="list-group-item" data-order-id="${order.id_pedido}">
            <div class="d-flex justify-content-between">
                <div class="flex-grow-1">
                    <h6>Pedido #${order.id_pedido}</h6>
                    <small class="text-muted d-block">Restaurante: ${order.restaurante}</small>
                    <small class="text-muted d-block">Cliente: ${order.cliente}</small>
                    <small class="text-muted d-block">Dirección: ${order.direccion_entrega}</small>
                    <small class="text-muted d-block">Distancia: ${((order.distancia || 1500) / 1000).toFixed(1)} km</small>
                    <small class="text-success">
                        <i class="fas fa-dollar-sign me-1"></i>
                        Ganancia estimada: $35.00
                    </small>
                </div>
                <button class="btn btn-sm btn-success btn-aceptar align-self-start" 
                        onclick="aceptarPedidoWebSocket(${order.id_pedido})">
                    Aceptar
                </button>
            </div>
        </div>
    `).join('');
    
    // Actualizar contador de pedidos disponibles
    updateAvailableOrdersCount(orders.length);
}

function handleOrderAssigned(data) {
    console.log('✅ Pedido asignado exitosamente:', data);
    
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: '¡Pedido aceptado!',
            text: 'El pedido ha sido asignado correctamente',
            timer: 3000,
            showConfirmButton: false
        }).then(() => {
            // Recargar página para mostrar pedido en activos
            location.reload();
        });
    }
}

function handleOrderStatusUpdate(data) {
    console.log('📊 Procesando actualización de estado:', data);
    
    // Si es del pedido actual, actualizar interfaz
    if (currentOrder && currentOrder.id_pedido == data.orderId) {
        console.log('🔄 Actualizando estado del pedido actual');
        
        // Actualizar estado visual si existe
        const estadoElement = document.querySelector('.estado-pedido');
        if (estadoElement) {
            estadoElement.textContent = getEstadoTexto(data.status);
            estadoElement.className = 'estado-pedido ' + getClaseEstado(data.status);
        }
    }
}

// ===========================
// FUNCIONES DE INTERFAZ
// ===========================

function updateConnectionStatus(status) {
    const statusElement = document.querySelector('.connection-status');
    if (!statusElement) return;
    
    switch (status) {
        case 'connected':
            statusElement.innerHTML = '<small class="text-success"><i class="fas fa-circle"></i> En línea (Tiempo real)</small>';
            break;
        case 'disconnected':
            statusElement.innerHTML = '<small class="text-warning"><i class="fas fa-circle"></i> Reconectando...</small>';
            break;
        case 'error':
            statusElement.innerHTML = '<small class="text-danger"><i class="fas fa-exclamation-triangle"></i> Error de conexión</small>';
            break;
        default:
            statusElement.innerHTML = '<small class="text-secondary"><i class="fas fa-question-circle"></i> Desconocido</small>';
    }
}

function updateAvailableOrdersCount(count) {
    // Actualizar badge en la interfaz si existe
    const badge = document.querySelector('.pedidos-disponibles-count');
    if (badge) {
        badge.textContent = count;
    }
}

// ===========================
// FUNCIONES PARA PEDIDOS
// ===========================

// Función para aceptar pedido usando WebSocket
function aceptarPedidoWebSocket(orderId) {
    console.log('🔄 Aceptando pedido vía WebSocket:', orderId);
    
    const orderElement = document.querySelector(`[data-order-id="${orderId}"]`);
    const button = orderElement?.querySelector('.btn-aceptar');
    
    if (button) {
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Aceptando...';
        button.disabled = true;
    }
    
    if (courierClient && courierClient.isConnected) {
        // Usar WebSocket
        const success = courierClient.acceptOrder(orderId);
        if (!success) {
            console.warn('⚠️ No se pudo enviar por WebSocket, usando respaldo POST');
            aceptarPedidoPost(orderId, button);
        }
    } else {
        // Usar respaldo POST
        console.log('📡 Sin conexión WebSocket, usando método POST');
        aceptarPedidoPost(orderId, button);
    }
}

// Función de respaldo POST para aceptar pedido
function aceptarPedidoPost(orderId, button) {
    fetch('aceptar_pedido.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'id_pedido=' + orderId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: '¡Pedido aceptado!',
                    text: 'El pedido ha sido asignado correctamente',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            }
        } else {
            if (button) {
                button.innerHTML = 'Aceptar';
                button.disabled = false;
            }
            alert('Error al aceptar el pedido: ' + (data.message || 'Error desconocido'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (button) {
            button.innerHTML = 'Aceptar';
            button.disabled = false;
        }
        alert('Error al procesar la solicitud');
    });
}

// ===========================
// FUNCIONES PARA ESTADOS
// ===========================

// Función para marcar como recogido usando WebSocket
function marcarComoRecogidoWebSocket() {
    if (!currentOrder) {
        console.error('No hay pedido activo');
        return;
    }
    
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: '¿Confirmar recogida?',
            text: '¿Confirmas que has recogido el pedido del restaurante?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, he recogido el pedido',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Mostrar loading
                const container = document.getElementById('navigationActions');
                if (container) {
                    container.innerHTML = `
                        <button class="btn btn-secondary btn-lg" disabled>
                            <i class="fas fa-spinner fa-spin me-2"></i>
                            Marcando como recogido...
                        </button>
                    `;
                }
                
                // Intentar usar WebSocket primero
                if (courierClient && courierClient.isConnected) {
                    courierClient.setActiveOrder(currentOrder.id_pedido);
                    const success = courierClient.updateOrderStatus(5); // Estado: En camino
                    
                    if (success) {
                        console.log('✅ Estado enviado vía WebSocket');
                        handleSuccessfulPickup();
                    } else {
                        console.warn('⚠️ Fallo WebSocket, usando respaldo POST');
                        marcarComoRecogidoPost();
                    }
                } else {
                    console.log('📡 Sin conexión WebSocket, usando método POST');
                    marcarComoRecogidoPost();
                }
            }
        });
    }
}

// Función para marcar como entregado usando WebSocket
function marcarComoEntregadoWebSocket() {
    if (!currentOrder) {
        console.error('No hay pedido activo');
        return;
    }
    
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: '¿Confirmar entrega?',
            text: '¿Confirmas que has entregado el pedido al cliente?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, he entregado el pedido',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Mostrar loading
                const container = document.getElementById('navigationActions');
                if (container) {
                    container.innerHTML = `
                        <button class="btn btn-secondary btn-lg" disabled>
                            <i class="fas fa-spinner fa-spin me-2"></i>
                            Marcando como entregado...
                        </button>
                    `;
                }
                
                // Intentar usar WebSocket primero
                if (courierClient && courierClient.isConnected) {
                    const success = courierClient.updateOrderStatus(6); // Estado: Entregado
                    
                    if (success) {
                        console.log('✅ Estado enviado vía WebSocket');
                        handleSuccessfulDelivery();
                    } else {
                        console.warn('⚠️ Fallo WebSocket, usando respaldo POST');
                        marcarComoEntregadoPost();
                    }
                } else {
                    console.log('📡 Sin conexión WebSocket, usando método POST');
                    marcarComoEntregadoPost();
                }
            }
        });
    }
}

// Funciones de respaldo POST
function marcarComoRecogidoPost() {
    cambiarEstadoPedidoPost('recogido');
}

function marcarComoEntregadoPost() {
    cambiarEstadoPedidoPost('entregado');
}

// Función POST genérica para cambiar estado
function cambiarEstadoPedidoPost(nuevoEstado) {
    if (!currentOrder) {
        console.error('No hay pedido activo para cambiar estado');
        return;
    }
    
    console.log(`Cambiando estado de pedido ${currentOrder.id_pedido} a: ${nuevoEstado} (método POST)`);
    
    const formData = new FormData();
    formData.append('id_pedido', currentOrder.id_pedido);
    formData.append('estado', nuevoEstado);
    
    fetch('actualizar_estado_pedido.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Error HTTP ${response.status}: ${response.statusText}`);
        }
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('Respuesta no JSON:', text);
                throw new Error('La respuesta del servidor no es JSON válido');
            });
        }
        
        return response.json();
    })
    .then(data => {
        console.log('Respuesta del servidor (POST):', data);
        
        if (data.success) {
            if (nuevoEstado === 'recogido') {
                handleSuccessfulPickup();
            } else if (nuevoEstado === 'entregado') {
                handleSuccessfulDelivery();
            }
        } else {
            // Restaurar botones
            restoreNavigationButtons();
            
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al actualizar estado',
                    text: data.message || 'Error desconocido',
                    confirmButtonText: 'Entendido'
                });
            }
        }
    })
    .catch(error => {
        console.error('Error en petición POST:', error);
        restoreNavigationButtons();
        
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'No se pudo conectar con el servidor. Verifica tu conexión.',
                confirmButtonText: 'Reintentar'
            });
        }
    });
}

// ===========================
// MANEJADORES DE ÉXITO
// ===========================

function handleSuccessfulPickup() {
    console.log('✅ Pedido marcado como recogido exitosamente');
    
    // Cambiar a navegación hacia cliente
    setTimeout(() => {
        currentStep = 'cliente';
        
        if (typeof actualizarInfoDestino === 'function') {
            actualizarInfoDestino();
        }
        
        if (typeof actualizarBotonesAccion === 'function') {
            actualizarBotonesAccion();
        }
        
        const navTitle = document.getElementById('navTitle');
        if (navTitle) {
            navTitle.textContent = 'Ir al Cliente';
        }
        
        // Reinicializar mapa si existe
        if (typeof inicializarMapa === 'function') {
            inicializarMapa();
        }
        if (typeof obtenerUbicacionRepartidor === 'function') {
            obtenerUbicacionRepartidor();
        }
        
        // Iniciar seguimiento automático de ubicación
        if (courierClient && courierClient.isConnected) {
            courierClient.startLocationTracking();
        }
        
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Pedido recogido',
                text: '¡Dirígete al cliente!',
                timer: 2000,
                showConfirmButton: false,
                position: 'top-end',
                toast: true
            });
        }
    }, 1000);
}

function handleSuccessfulDelivery() {
    console.log('✅ Pedido marcado como entregado exitosamente');
    
    // Detener seguimiento de ubicación
    if (courierClient) {
        courierClient.stopLocationTracking();
    }
    
    setTimeout(() => {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: '¡Pedido entregado!',
                text: 'Has completado la entrega exitosamente. ¡Excelente trabajo!',
                timer: 4000,
                showConfirmButton: false
            }).then(() => {
                if (typeof cerrarNavegacion === 'function') {
                    cerrarNavegacion();
                }
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            });
        }
    }, 1000);
}

// ===========================
// FUNCIONES AUXILIARES
// ===========================

function restoreNavigationButtons() {
    const container = document.getElementById('navigationActions');
    if (container && typeof actualizarBotonesAccion === 'function') {
        actualizarBotonesAccion();
    }
}

function getEstadoTexto(estado) {
    const estados = {
        1: 'Asignado',
        2: 'Confirmado', 
        3: 'Preparando',
        4: 'Listo para entrega',
        5: 'En camino',
        6: 'Entregado'
    };
    return estados[estado] || 'Estado desconocido';
}

function getClaseEstado(estado) {
    const clases = {
        1: 'estado-asignado',
        2: 'estado-confirmado', 
        3: 'estado-preparando',
        4: 'estado-listo',
        5: 'estado-en_camino',
        6: 'estado-entregado'
    };
    return clases[estado] || 'estado-asignado';
}

// ===========================
// FUNCIÓN HTTP DE RESPALDO
// ===========================

// Función para obtener pedidos disponibles vía HTTP (respaldo)
function obtenerPedidosDisponiblesHTTP() {
    console.log('📡 Obteniendo pedidos disponibles vía HTTP');
    
    return fetch('obtener_pedidos_disponibles.php', {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Cache-Control': 'no-cache'
        },
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Pedidos recibidos vía HTTP:', data);
        return data;
    })
    .catch(error => {
        console.error('Error obteniendo pedidos vía HTTP:', error);
        throw error;
    });
}

// ===========================
// REEMPLAZAR FUNCIONES ORIGINALES
// ===========================

// Sobrescribir funciones originales del dashboard
function setupFunctionOverrides() {
    // Reemplazar función de aceptar pedido
    if (typeof window.aceptarPedido !== 'undefined') {
        window.aceptarPedidoOriginal = window.aceptarPedido;
    }
    window.aceptarPedido = aceptarPedidoWebSocket;
    
    // Reemplazar funciones de cambio de estado
    if (typeof window.marcarComoRecogido !== 'undefined') {
        window.marcarComoRecogidoOriginal = window.marcarComoRecogido;
    }
    window.marcarComoRecogido = marcarComoRecogidoWebSocket;
    
    if (typeof window.marcarComoEntregado !== 'undefined') {
        window.marcarComoEntregadoOriginal = window.marcarComoEntregado;
    }
    window.marcarComoEntregado = marcarComoEntregadoWebSocket;
    
    // Función de respaldo HTTP disponible globalmente
    window.obtenerPedidosDisponiblesHTTP = obtenerPedidosDisponiblesHTTP;
    
    console.log('✅ Funciones WebSocket configuradas como principales');
}

// ===========================
// INICIALIZACIÓN AUTOMÁTICA
// ===========================

document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Inicializando integración WebSocket...');
    
    // Verificar que CourierClient esté disponible
    if (typeof CourierClient === 'undefined') {
        console.error('❌ CourierClient no está disponible. Asegúrate de incluir courier-tracking.js');
        return;
    }
    
    // Solicitar permisos de notificación
    if (Notification.permission === 'default') {
        Notification.requestPermission();
    }
    
    // Inicializar WebSocket después de un breve delay
    setTimeout(() => {
        initializeWebSocketIntegration();
        setupFunctionOverrides();
    }, 1000);
    
    // Configurar pedido activo si existe
    <?php if (count($pedidos_activos) > 0): ?>
    <?php foreach ($pedidos_activos as $pedido): ?>
    setTimeout(() => {
        currentOrderForTracking = <?php echo json_encode($pedido); ?>;
        if (courierClient && courierClient.isConnected) {
            courierClient.setActiveOrder(<?php echo $pedido['id_pedido']; ?>);
            
            // Si está en camino, iniciar seguimiento automático
            <?php if ($pedido['id_estado'] == 5): ?>
            console.log('🔄 Pedido en camino detectado, iniciando seguimiento automático');
            courierClient.startLocationTracking();
            <?php endif; ?>
        }
    }, 3000);
    <?php break; // Solo un pedido activo a la vez ?>
    <?php endforeach; ?>
    <?php endif; ?>
    
    // Manejar visibilidad de la página
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            console.log('📱 Página oculta');
        } else {
            console.log('📱 Página visible, verificando conexión WebSocket');
            if (courierClient && !courierClient.isConnected) {
                courierClient.connect();
            }
        }
    });
    
    // Limpiar al cerrar la página
    window.addEventListener('beforeunload', function() {
        if (courierClient) {
            courierClient.disconnect();
        }
    });
});

// ===========================
// FUNCIONES DE DEBUG
// ===========================

function debugWebSocketStatus() {
    console.log('=== DEBUG WEBSOCKET STATUS ===');
    console.log('CourierClient existe:', typeof CourierClient !== 'undefined');
    console.log('courierClient instanciado:', !!courierClient);
    console.log('WebSocket conectado:', courierClient?.isConnected);
    console.log('Pedido activo:', currentOrderForTracking);
    console.log('Seguimiento activo:', courierClient?.isTracking);
    console.log('============================');
}

// Hacer funciones disponibles globalmente para debug
window.debugWebSocketStatus = debugWebSocketStatus;
window.courierClientInstance = () => courierClient;