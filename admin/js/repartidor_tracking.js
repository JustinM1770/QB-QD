// Configuración inicial del mapa
let map;
let marker;
let watchId;
let routePolyline;

// Inicializar el mapa
function initMap() {
    // Obtener ID del repartidor del atributo data
    const repartidorId = document.getElementById('mapa-ruta').dataset.repartidorId;
    
    // Coordenadas iniciales (pueden ser las del repartidor o una ubicación por defecto)
    const initialLocation = { lat: 19.4326, lng: -99.1332 }; // Ciudad de México
    
    // Crear el mapa
    map = new google.maps.Map(document.getElementById("mapa-ruta"), {
        zoom: 15,
        center: initialLocation,
        mapTypeId: "roadmap",
    });

    // Crear marcador para la ubicación del repartidor
    marker = new google.maps.Marker({
        position: initialLocation,
        map: map,
        title: "Tu ubicación",
        icon: {
            url: "https://maps.google.com/mapfiles/ms/icons/blue-dot.png"
        }
    });

    // Configurar el seguimiento de ubicación en tiempo real
    setupGeolocation(repartidorId);
}

// Configurar geolocalización
function setupGeolocation(repartidorId) {
    if (navigator.geolocation) {
        // Obtener ubicación actual
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const pos = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                };
                
                // Actualizar marcador
                marker.setPosition(pos);
                map.setCenter(pos);
                
                // Enviar ubicación al servidor
                updateServerLocation(pos, repartidorId);
            },
            () => {
                handleLocationError(true);
            }
        );

        // Seguimiento continuo
        watchId = navigator.geolocation.watchPosition(
            (position) => {
                const pos = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                };
                
                // Actualizar marcador
                marker.setPosition(pos);
                
                // Enviar ubicación al servidor
                updateServerLocation(pos, repartidorId);
            },
            (error) => {
                console.error("Error en geolocalización:", error);
            },
            {
                enableHighAccuracy: true,
                timeout: 5000,
                maximumAge: 0
            }
        );
    } else {
        handleLocationError(false);
    }
}

// Manejar errores de geolocalización
function handleLocationError(browserHasGeolocation) {
    alert(browserHasGeolocation ?
        "Error: El servicio de geolocalización falló." :
        "Error: Tu navegador no soporta geolocalización.");
}

// Actualizar ubicación en el servidor
function updateServerLocation(position, repartidorId) {
    fetch('update_location.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            lat: position.lat,
            lng: position.lng,
            repartidor_id: repartidorId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.ruta) {
            drawRoute(data.ruta);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// Dibujar ruta en el mapa
function drawRoute(routePoints) {
    // Limpiar ruta anterior si existe
    if (routePolyline) {
        routePolyline.setMap(null);
    }

    // Convertir puntos a formato LatLng de Google Maps
    const path = routePoints.map(point => ({
        lat: parseFloat(point.lat),
        lng: parseFloat(point.lng)
    }));

    // Dibujar nueva ruta
    routePolyline = new google.maps.Polyline({
        path: path,
        geodesic: true,
        strokeColor: "#0165FF",
        strokeOpacity: 1.0,
        strokeWeight: 4,
        map: map
    });

    // Ajustar el mapa para mostrar toda la ruta
    const bounds = new google.maps.LatLngBounds();
    path.forEach(point => bounds.extend(point));
    map.fitBounds(bounds);
}

// Evento para el botón de actualizar ubicación
document.getElementById('btn-actualizar-ubicacion').addEventListener('click', () => {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const pos = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                };
                
                marker.setPosition(pos);
                map.setCenter(pos);
                updateServerLocation(pos, document.getElementById('mapa-ruta').dataset.repartidorId);
                
                alert('Ubicación actualizada correctamente');
            },
            () => {
                alert('No se pudo obtener la ubicación actual');
            }
        );
    }
});

// Inicializar el mapa cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', initMap);
