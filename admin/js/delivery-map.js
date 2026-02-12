let deliveryMap;
let directionsControl;

// Función para inicializar mapa con coordenadas reales
function inicializarMapa(pedido) {
    if (deliveryMap) {
        deliveryMap.remove();
    }
    
    // Usar coordenadas reales del pedido
    const negocioCoords = [parseFloat(pedido.negocio.lng), parseFloat(pedido.negocio.lat)];
    const clienteCoords = [parseFloat(pedido.cliente.lng), parseFloat(pedido.cliente.lat)];
    
    deliveryMap = new mapboxgl.Map({
        container: 'deliveryMap',
        style: 'mapbox://styles/mapbox/streets-v12',
        center: negocioCoords,
        zoom: 12
    });
    
    // Inicializar el control de direcciones
    directionsControl = new MapboxDirections({
        accessToken: mapboxgl.accessToken,
        unit: 'metric',
        profile: 'mapbox/driving',
        alternatives: true,
        language: 'es',
        steps: true,
        controls: {
            inputs: false,
            instructions: true
        }
    });
    
    // Agregar controles al mapa
    deliveryMap.addControl(new mapboxgl.NavigationControl(), 'top-right');
    deliveryMap.addControl(directionsControl, 'top-left');
    
    // Escuchar eventos de la ruta
    directionsControl.on('route', function(e) {
        if (e.route && e.route[0]) {
            const duration = Math.round(e.route[0].duration / 60);
            const distance = (e.route[0].distance / 1000).toFixed(1);
            
            document.getElementById('tiempoNegocio').textContent = `Tiempo estimado: ${duration} minutos`;
            document.getElementById('tiempoCliente').textContent = `Distancia total: ${distance} km`;
        }
    });
    
    deliveryMap.on('load', function() {
        // Marcador del negocio
        new mapboxgl.Marker({ color: '#ffc107' })
            .setLngLat(negocioCoords)
            .setPopup(new mapboxgl.Popup().setText(pedido.negocio.nombre))
            .addTo(deliveryMap);
        
        // Marcador del cliente
        new mapboxgl.Marker({ color: '#28a745' })
            .setLngLat(clienteCoords)
            .setPopup(new mapboxgl.Popup().setText(pedido.cliente.nombre))
            .addTo(deliveryMap);
        
        // Establecer origen y destino para las direcciones
        directionsControl.setOrigin(negocioCoords);
        directionsControl.setDestination(clienteCoords);
        
        // Ajustar vista para mostrar ambos puntos
        const bounds = new mapboxgl.LngLatBounds()
            .extend(negocioCoords)
            .extend(clienteCoords);
        
        deliveryMap.fitBounds(bounds, { padding: 50 });
    });
}

// Función para actualizar la ruta cuando el pedido cambia
function actualizarRuta(origen, destino) {
    if (directionsControl) {
        directionsControl.setOrigin(origen);
        directionsControl.setDestination(destino);
    }
}

// Función para limpiar el mapa
function limpiarMapa() {
    if (deliveryMap) {
        deliveryMap.remove();
        deliveryMap = null;
    }
}
