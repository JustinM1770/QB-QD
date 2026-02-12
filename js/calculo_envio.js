/**
 * QuickBite - Sistema de Cálculo de Envío
 *
 * MODELO DE NEGOCIO:
 * - Mínimo garantizado para repartidor: $25
 * - Primeros 3km incluidos en tarifa base
 * - $5 por km adicional después de 3km
 * - Miembros QuickBite Club: Envío GRATIS
 */

// Configuración de envío (debe coincidir con config/quickbite_fees.php)
const QUICKBITE_ENVIO = {
    MINIMO: 25.00,           // $25 mínimo garantizado para repartidor
    POR_KM: 5.00,            // $5 por km adicional
    KM_BASE: 3,              // 3 km incluidos en la tarifa base
    DISTANCIA_MAXIMA: 15     // 15 km máximo de entrega
};

// Función para calcular distancia entre coordenadas (Fórmula Haversine)
function calcularDistancia(lat1, lon1, lat2, lon2) {
    const R = 6371; // Radio de la Tierra en km
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a =
        Math.sin(dLat/2) * Math.sin(dLat/2) +
        Math.cos(lat1 * Math.PI / 180) *
        Math.cos(lat2 * Math.PI / 180) *
        Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c; // Distancia en km
}

/**
 * Calcular costo de envío basado en distancia
 * @param {number} distanciaKm - Distancia en kilómetros
 * @param {boolean} esMiembroClub - Si el cliente es miembro QuickBite Club
 * @returns {number} Costo de envío o -1 si está fuera de rango
 */
function calcularCostoEnvio(distanciaKm, esMiembroClub = false) {
    // Miembros Club no pagan envío
    if (esMiembroClub) {
        return 0;
    }

    // Verificar distancia máxima
    if (distanciaKm > QUICKBITE_ENVIO.DISTANCIA_MAXIMA) {
        return -1; // Fuera de rango de entrega
    }

    // Si está dentro de los km base, solo cobra el mínimo
    if (distanciaKm <= QUICKBITE_ENVIO.KM_BASE) {
        return QUICKBITE_ENVIO.MINIMO;
    }

    // Calcular km adicionales después de los primeros 3km
    const kmAdicionales = distanciaKm - QUICKBITE_ENVIO.KM_BASE;
    const costoAdicional = kmAdicionales * QUICKBITE_ENVIO.POR_KM;

    return QUICKBITE_ENVIO.MINIMO + costoAdicional;
}

/**
 * Calcular pago al repartidor (siempre recibe el mínimo garantizado o más)
 * @param {number} distanciaKm - Distancia en kilómetros
 * @param {number} propina - Propina del cliente
 * @returns {number} Pago total al repartidor
 */
function calcularPagoRepartidor(distanciaKm, propina = 0) {
    // El repartidor SIEMPRE recibe mínimo $25, sin importar si cliente pagó envío
    const pagoEnvio = Math.max(QUICKBITE_ENVIO.MINIMO, calcularCostoEnvio(distanciaKm, false));
    return pagoEnvio + propina;
}

/**
 * Calcular cargo de servicio
 * @param {boolean} esMiembroClub - Si el cliente es miembro QuickBite Club
 * @returns {number} Cargo de servicio
 */
function calcularCargoServicio(esMiembroClub = false) {
    return esMiembroClub ? 0 : 5.00; // $5 cargo servicio, gratis para miembros
}

/**
 * Verificar si la dirección está dentro del rango de entrega
 * @param {number} distanciaKm - Distancia en kilómetros
 * @returns {boolean} Si está dentro del rango
 */
function dentroDeRangoEntrega(distanciaKm) {
    return distanciaKm <= QUICKBITE_ENVIO.DISTANCIA_MAXIMA;
}

/**
 * Obtener mensaje de costo de envío formateado
 * @param {number} distanciaKm - Distancia en kilómetros
 * @param {boolean} esMiembroClub - Si el cliente es miembro QuickBite Club
 * @returns {string} Mensaje formateado
 */
function getMensajeEnvio(distanciaKm, esMiembroClub = false) {
    if (!dentroDeRangoEntrega(distanciaKm)) {
        return 'Lo sentimos, esta dirección está fuera de nuestro rango de entrega (máx. 15km)';
    }

    const costo = calcularCostoEnvio(distanciaKm, esMiembroClub);

    if (esMiembroClub) {
        return 'Envío GRATIS (Beneficio QuickBite Club)';
    }

    return `$${costo.toFixed(2)} (${distanciaKm.toFixed(1)} km)`;
}
