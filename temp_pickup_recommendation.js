$(document).ready(function() {
    // NUEVO: Recomendación PickUp si el usuario está cerca del negocio
    function recommendPickUpIfNear() {
        if (!navigator.geolocation) {
            console.log('Geolocalización no soportada');
            return;
        }
        
        navigator.geolocation.getCurrentPosition(function(position) {
            const userLat = position.coords.latitude;
            const userLng = position.coords.longitude;
            
            // Latitud y longitud del negocio desde PHP
            const negocioLat = <?php echo $negocio ? $negocio->latitud : 'null'; ?>;
            const negocioLng = <?php echo $negocio ? $negocio->longitud : 'null'; ?>;
            
            if (negocioLat === null || negocioLng === null) {
                console.log('Coordenadas del negocio no disponibles');
                return;
            }
            
            // Calcular distancia en metros usando la fórmula Haversine
            function getDistanceFromLatLonInMeters(lat1, lon1, lat2, lon2) {
                const R = 6371000; // Radio de la Tierra en metros
                const dLat = (lat2 - lat1) * Math.PI / 180;
                const dLon = (lon2 - lon1) * Math.PI / 180;
                const a = 
                    Math.sin(dLat/2) * Math.sin(dLat/2) +
                    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
                    Math.sin(dLon/2) * Math.sin(dLon/2);
                const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
                const d = R * c;
                return d;
            }
            
            const distance = getDistanceFromLatLonInMeters(userLat, userLng, negocioLat, negocioLng);
            console.log('Distancia al negocio:', distance, 'metros');
            
            const threshold = 3000; // 3 km
            
            if (distance <= threshold) {
                // Mostrar recomendación
                const pickupRadio = document.getElementById('tipo_pickup');
                const deliveryRadio = document.getElementById('tipo_delivery');
                const recommendationDiv = document.createElement('div');
                recommendationDiv.id = 'pickupRecommendation';
                recommendationDiv.style.backgroundColor = '#d1e7dd';
                recommendationDiv.style.color = '#0f5132';
                recommendationDiv.style.padding = '10px';
                recommendationDiv.style.marginTop = '10px';
                recommendationDiv.style.borderRadius = '8px';
                recommendationDiv.style.fontWeight = '600';
                recommendationDiv.textContent = '¡Estás cerca del negocio! Te recomendamos seleccionar PickUp para recoger tu pedido.';
                
                const tipoPedidoSection = pickupRadio.parentElement.parentElement;
                tipoPedidoSection.insertBefore(recommendationDiv, deliveryRadio.parentElement);
                
                // Opcional: seleccionar automáticamente PickUp
                // pickupRadio.checked = true;
            }
        }, function(error) {
            console.warn('Error obteniendo ubicación:', error);
        });
    }
    
    // Ejecutar la recomendación al cargar la página
    recommendPickUpIfNear();
});
