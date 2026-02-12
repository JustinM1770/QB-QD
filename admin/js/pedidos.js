// Función para aceptar un pedido
async function aceptarPedido(idPedido) {
    try {
        const response = await fetch('aceptar_pedido.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id_pedido: idPedido
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Mostrar mensaje de éxito
            Swal.fire({
                icon: 'success',
                title: '¡Pedido aceptado!',
                text: 'El pedido ha sido asignado a ti.',
                showConfirmButton: false,
                timer: 2000
            });
            
            // Actualizar UI y mostrar indicaciones
            mostrarPedidoAceptado(data.pedido);
        } else {
            throw new Error(data.message || 'Error al aceptar el pedido');
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message || 'Error al procesar el pedido',
        });
    }
}

// Cargar pedidos activos al iniciar la página
document.addEventListener('DOMContentLoaded', () => {
    cargarPedidosActivos();
    // Actualizar cada 30 segundos
    setInterval(cargarPedidosActivos, 30000);
});

// Función para cargar pedidos activos
async function cargarPedidosActivos() {
    try {
        const response = await fetch('obtener_pedidos_activos.php');
        const data = await response.json();
        
        const contenedorActivos = document.getElementById('pedidosActivos');
        const mensajeNoPedidos = document.getElementById('mensajeNoPedidos');
        const contadorPedidos = document.getElementById('contadorPedidosActivos');
        const btnActualizarPedidos = document.getElementById('btnActualizarPedidos');
        
        // Actualizar estado en línea
        document.getElementById('estadoEnLinea').checked = true;
        
        if (data.success && data.pedidos && Array.isArray(data.pedidos) && data.pedidos.length > 0) {
            // Ocultar mensaje de no pedidos
            if (mensajeNoPedidos) {
                mensajeNoPedidos.style.display = 'none';
            }
            
            // Mostrar contenedor de pedidos activos
            if (contenedorActivos) {
                contenedorActivos.style.display = 'block';
                contenedorActivos.innerHTML = ''; // Limpiar contenedor
            }
            
            // Actualizar contador
            if (contadorPedidos) {
                contadorPedidos.textContent = data.pedidos.length.toString();
            }
            
            // Habilitar botón de actualizar si existe
            if (btnActualizarPedidos) {
                btnActualizarPedidos.disabled = false;
            }
            
            // Crear tarjetas de pedidos
            data.pedidos.forEach(pedido => {
                const card = crearTarjetaPedidoActivo(pedido);
                contenedorActivos.appendChild(card);
            });
        } else {
            // Mostrar mensaje de no pedidos
            if (mensajeNoPedidos) {
                mensajeNoPedidos.style.display = 'block';
            }
            contenedorActivos.style.display = 'none';
            if (contadorPedidos) {
                contadorPedidos.textContent = '0';
            }
        }
    } catch (error) {
        console.error('Error al cargar pedidos activos:', error);
        mostrarNotificacion('Error al cargar pedidos activos', 'error');
    }
}

// Función para crear tarjeta de pedido activo
function crearTarjetaPedidoActivo(pedido) {
    const card = document.createElement('div');
    card.className = 'card mb-3 pedido-activo';
    
    // Determinar el estado del pedido
    const estados = {
        1: 'Asignado',
        2: 'En camino al negocio',
        3: 'Recogido',
        4: 'En camino al cliente'
    };
    
    // Determinar el color según el estado
    const colores = {
        1: 'info',
        2: 'primary',
        3: 'warning',
        4: 'success'
    };
    
    card.innerHTML = `
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
                <h5 class="card-title">Pedido #${pedido.id_pedido}</h5>
                <span class="badge bg-${colores[pedido.id_estado]}">${estados[pedido.id_estado]}</span>
            </div>
            <div class="pedido-info">
                <p class="mb-2">
                    <i class="fas fa-store text-primary"></i> 
                    <strong>Negocio:</strong> ${pedido.nombre_negocio}
                </p>
                <p class="mb-2">
                    <i class="fas fa-map-marker-alt text-danger"></i> 
                    <strong>Dirección negocio:</strong><br>
                    ${pedido.direccion_negocio}
                </p>
                <p class="mb-2">
                    <i class="fas fa-user text-info"></i> 
                    <strong>Cliente:</strong> ${pedido.nombre_cliente} ${pedido.apellido_cliente}
                </p>
                <p class="mb-2">
                    <i class="fas fa-map-pin text-success"></i> 
                    <strong>Dirección entrega:</strong><br>
                    ${pedido.direccion_cliente}
                </p>
                <p class="mb-2">
                    <i class="fas fa-money-bill-wave text-success"></i> 
                    <strong>Total:</strong> $${parseFloat(pedido.monto_pedido).toFixed(2)}
                </p>
            </div>
            <div class="pedido-actions mt-3 d-flex gap-2">
                <button class="btn btn-primary" onclick='mostrarPedidoAceptado(${JSON.stringify(pedido).replace(/'/g, "\\'")})'>
                    <i class="fas fa-route me-2"></i>Ver Indicaciones
                </button>
            </div>
        </div>
    `;
    return card;
}

// Función para mostrar los detalles del pedido e indicaciones
function mostrarPedidoAceptado(pedido) {
    // Ocultar otras secciones
    document.getElementById('pedidosDisponibles').style.display = 'none';
    document.getElementById('seccionPedidosActivos').style.display = 'none';
    
    // Mostrar sección de pedido activo
    const pedidoActivo = document.getElementById('pedidoActivo');
    pedidoActivo.style.display = 'block';
    
    // Actualizar información del pedido
    document.getElementById('nombreNegocio').textContent = pedido.nombre_negocio;
    document.getElementById('direccionNegocio').textContent = pedido.direccion_negocio;
    document.getElementById('telefonoNegocio').textContent = pedido.telefono_negocio;
    document.getElementById('nombreCliente').textContent = pedido.nombre_cliente + ' ' + pedido.apellido_cliente;
    document.getElementById('direccionCliente').textContent = pedido.direccion_cliente;
    document.getElementById('telefonoCliente').textContent = pedido.telefono_cliente;
    
    // Actualizar estado visual
    actualizarUIEstadoPedido(pedido.id_estado);
    
    // Crear datos para el mapa
    const datosMapa = {
        negocio: {
            nombre: pedido.nombre_negocio,
            direccion: pedido.direccion_negocio,
            lat: parseFloat(pedido.lat_negocio),
            lng: parseFloat(pedido.lng_negocio)
        },
        cliente: {
            nombre: pedido.nombre_cliente + ' ' + pedido.apellido_cliente,
            direccion: pedido.direccion_cliente,
            lat: parseFloat(pedido.lat_cliente),
            lng: parseFloat(pedido.lng_cliente)
        }
    };
    
    // Inicializar mapa con ubicaciones
    inicializarMapa(datosMapa);
    
    // Mostrar indicaciones según el estado
    if (pedido.id_estado <= 2) {
        // Ruta hacia el negocio
        actualizarRuta(
            [datosMapa.negocio.lng, datosMapa.negocio.lat],
            null // No mostrar destino final aún
        );
    } else {
        // Ruta hacia el cliente
        actualizarRuta(
            [datosMapa.cliente.lng, datosMapa.cliente.lat],
            null
        );
    }
    
        // Inicializar estado como "asignado"
    actualizarUIEstadoPedido(1);
    
    // Actualizar contadores y estado
    actualizarContadores();
    
    // Ya tenemos datosMapa definido arriba, solo inicializamos el mapa
    inicializarMapa(datosMapa);
}

// Función para actualizar estado del pedido
async function actualizarEstadoPedido(idPedido, nuevoEstado) {
    try {
        const response = await fetch('actualizar_estado_pedido.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id_pedido: idPedido,
                estado: nuevoEstado
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Actualizar UI según el nuevo estado
            actualizarUIEstadoPedido(nuevoEstado);
            
            // Si el pedido se completó
            if (nuevoEstado === 5) { // Estado 5 = entregado
                // Mostrar mensaje de éxito
                Swal.fire({
                    icon: 'success',
                    title: '¡Pedido completado!',
                    text: 'La entrega se ha registrado correctamente.',
                    timer: 2000,
                    showConfirmButton: false
                });

                // Actualizar estadísticas
                actualizarEstadisticas();
                
                // Volver al listado después de 2 segundos
                setTimeout(() => {
                    limpiarMapa();
                    document.getElementById('pedidoActivo').style.display = 'none';
                    document.getElementById('seccionPedidosActivos').style.display = 'block';
                    cargarPedidosActivos(); // Recargar pedidos activos
                }, 2000);
            }
        } else {
            throw new Error(data.message || 'Error al actualizar el estado');
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message || 'Error al actualizar el estado',
        });
    }
}

// Función para actualizar contadores
async function actualizarContadores() {
    try {
        // Actualizar pedidos activos
        const responsePedidos = await fetch('obtener_pedidos_activos.php');
        const dataPedidos = await responsePedidos.json();
        const contadorActivos = document.getElementById('contadorPedidosActivos');
        if (contadorActivos) {
            contadorActivos.textContent = dataPedidos.pedidos ? dataPedidos.pedidos.length.toString() : '0';
        }

        // Actualizar pedidos disponibles
        const responseDisponibles = await fetch('obtener_pedidos_disponibles.php');
        const dataDisponibles = await responseDisponibles.json();
        const contadorDisponibles = document.getElementById('contadorPedidosDisponibles');
        if (contadorDisponibles) {
            contadorDisponibles.textContent = dataDisponibles.pedidos ? dataDisponibles.pedidos.length.toString() : '0';
        }
    } catch (error) {
        console.error('Error al actualizar contadores:', error);
    }
}

// Función para actualizar estadísticas
async function actualizarEstadisticas() {
    try {
        const response = await fetch('obtener_estadisticas.php');
        const data = await response.json();
        
        if (data.success) {
            // Actualizar entregas totales
            document.getElementById('entregasTotales').textContent = data.estadisticas.total_entregas;
            // Actualizar otros elementos estadísticos si existen
            if (data.estadisticas.ganancia_total) {
                document.getElementById('gananciasTotal').textContent = `$${data.estadisticas.ganancia_total}`;
            }
            if (data.estadisticas.calificacion_promedio) {
                document.getElementById('calificacionPromedio').textContent = data.estadisticas.calificacion_promedio;
            }
        }
    } catch (error) {
        console.error('Error al actualizar estadísticas:', error);
    }
}

// Función para actualizar la UI según el estado del pedido
function actualizarUIEstadoPedido(estado) {
    // Mapeo de estados numéricos a texto
    const estadosTexto = {
        1: 'asignado',           // Pedido asignado al repartidor
        2: 'en_camino_negocio',  // Repartidor en camino al negocio
        3: 'recogido',           // Pedido recogido del negocio
        4: 'en_camino_cliente',  // En camino al cliente
        5: 'entregado'           // Pedido entregado
    };

    const estadoTexto = estadosTexto[estado] || estado;
    const estados = ['asignado', 'en_camino_negocio', 'recogido', 'en_camino_cliente', 'entregado'];
    const indiceActual = estados.indexOf(estadoTexto);
    
    // Limpiar estados anteriores
    estados.forEach(e => {
        const elemento = document.getElementById(`estado_${e}`);
        if (elemento) {
            elemento.classList.remove('completado', 'activo');
        }
    });

    // Actualizar estados actuales
    estados.forEach((e, index) => {
        const elemento = document.getElementById(`estado_${e}`);
        if (elemento) {
            if (index < indiceActual) {
                elemento.classList.add('completado');
            } else if (index === indiceActual) {
                elemento.classList.add('activo');
            }
        }
    });
    
    // Actualizar botones según el estado actual
    actualizarBotonesEstado(estadoTexto);
}
