<?php
/**
 * business_helpers.php - Funciones helper para lógica de negocios
 * 
 * Este archivo contiene funciones estandarizadas para:
 * - Validación de horarios de negocios
 * - Cálculos de disponibilidad
 * - Utilidades comunes de negocio
 * 
 * @package QuickBite
 * @since 2.0.0
 */

/**
 * Verifica si un negocio está abierto actualmente
 * 
 * Esta función consulta la tabla negocio_horarios para determinar si un negocio
 * está abierto en el momento actual, considerando el día de la semana y la hora.
 * 
 * @param PDO $db Conexión a la base de datos
 * @param int $id_negocio ID del negocio a verificar
 * @return bool true si está abierto, false si está cerrado
 */
function isBusinessOpen($db, $id_negocio) {
    try {
        // Obtener día de la semana actual (0=Domingo, 1=Lunes, ..., 6=Sábado)
        $dia_actual = (int)date('w');
        
        // Obtener hora actual en formato 24h (H:i:s)
        $hora_actual = date('H:i:s');
        
        // Buscar horario para el día actual
        $query = "SELECT hora_apertura, hora_cierre, activo 
                  FROM negocio_horarios 
                  WHERE id_negocio = :id_negocio 
                    AND dia_semana = :dia_semana 
                  LIMIT 1";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id_negocio', $id_negocio, PDO::PARAM_INT);
        $stmt->bindParam(':dia_semana', $dia_actual, PDO::PARAM_INT);
        $stmt->execute();
        
        $horario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Si no hay horario registrado para este día, considerar cerrado
        if (!$horario) {
            return false;
        }
        
        // Si el horario no está activo para este día, considerar cerrado
        if (!$horario['activo']) {
            return false;
        }
        
        // Comparar la hora actual con el rango de apertura-cierre
        $hora_apertura = $horario['hora_apertura'];
        $hora_cierre = $horario['hora_cierre'];
        
        // Manejar caso especial: cierre después de medianoche (ej. 23:00-02:00)
        if ($hora_cierre < $hora_apertura) {
            // El negocio cierra después de medianoche
            return ($hora_actual >= $hora_apertura || $hora_actual <= $hora_cierre);
        }
        
        // Caso normal: verificar si estamos dentro del rango
        return ($hora_actual >= $hora_apertura && $hora_actual <= $hora_cierre);
        
    } catch (Exception $e) {
        // Log del error pero retornar false para no romper la página
        error_log("Error en isBusinessOpen para negocio $id_negocio: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtiene el horario del día actual de un negocio
 * 
 * @param PDO $db Conexión a la base de datos
 * @param int $id_negocio ID del negocio
 * @return array|null Array con hora_apertura y hora_cierre, o null si no hay horario
 */
function getBusinessTodaySchedule($db, $id_negocio) {
    try {
        $dia_actual = (int)date('w');
        
        $query = "SELECT hora_apertura, hora_cierre, activo 
                  FROM negocio_horarios 
                  WHERE id_negocio = :id_negocio 
                    AND dia_semana = :dia_semana 
                    AND activo = 1
                  LIMIT 1";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id_negocio', $id_negocio, PDO::PARAM_INT);
        $stmt->bindParam(':dia_semana', $dia_actual, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        
    } catch (Exception $e) {
        error_log("Error en getBusinessTodaySchedule: " . $e->getMessage());
        return null;
    }
}

/**
 * Verifica si un negocio tiene horarios configurados para cualquier día
 * 
 * @param PDO $db Conexión a la base de datos
 * @param int $id_negocio ID del negocio
 * @return bool true si tiene horarios configurados, false si no
 */
function businessHasSchedule($db, $id_negocio) {
    try {
        $query = "SELECT COUNT(*) as count FROM negocio_horarios WHERE id_negocio = :id_negocio AND activo = 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id_negocio', $id_negocio, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result['count'] > 0);
    } catch (Exception $e) {
        error_log("Error en businessHasSchedule: " . $e->getMessage());
        return true; // Asumir que tiene horarios si falla
    }
}

/**
 * Formatea mensajes de error de la API de pagos para el usuario
 * 
 * Convierte códigos de error técnicos en mensajes amigables para el usuario.
 * 
 * @param string $error_code Código de error de la API
 * @param string $error_message Mensaje de error original
 * @return string Mensaje amigable para mostrar al usuario
 */
function formatPaymentError($error_code, $error_message = '') {
    $friendly_messages = [
        'diff_param_bins' => 'No se pudo procesar tu tarjeta. Por favor verifica los datos ingresados o intenta con otra tarjeta.',
        'cc_rejected_insufficient_amount' => 'Fondos insuficientes en tu tarjeta. Por favor intenta con otra tarjeta.',
        'cc_rejected_bad_filled_card_number' => 'El número de tarjeta ingresado es incorrecto. Por favor verifícalo.',
        'cc_rejected_bad_filled_date' => 'La fecha de expiración ingresada es incorrecta.',
        'cc_rejected_bad_filled_security_code' => 'El código de seguridad (CVV) es incorrecto.',
        'cc_rejected_high_risk' => 'Tu pago fue rechazado por medidas de seguridad. Por favor intenta con otra tarjeta o método de pago.',
        'cc_rejected_call_for_authorize' => 'Debes llamar a tu banco para autorizar este pago.',
        'cc_rejected_card_disabled' => 'Tu tarjeta está deshabilitada. Contacta a tu banco.',
        'cc_rejected_duplicated_payment' => 'Ya se realizó un pago similar hace poco. Espera unos minutos.',
        'cc_rejected_max_attempts' => 'Demasiados intentos de pago. Espera unos minutos e intenta de nuevo.',
        'cc_rejected_other_reason' => 'Tu pago fue rechazado. Por favor intenta con otra tarjeta.',
        'invalid_token' => 'Hubo un problema con los datos de tu tarjeta. Por favor recarga la página e intenta de nuevo.',
        'invalid_bin' => 'El tipo de tarjeta no es compatible. Por favor usa otra tarjeta.',
        'timeout' => 'El proceso tardó demasiado. Por favor intenta de nuevo.',
        'network_error' => 'Problema de conexión. Verifica tu internet e intenta de nuevo.'
    ];
    
    // Buscar mensaje específico por código
    if (isset($friendly_messages[$error_code])) {
        return $friendly_messages[$error_code];
    }
    
    // Buscar coincidencias parciales
    foreach ($friendly_messages as $key => $message) {
        if (stripos($error_code, $key) !== false || stripos($error_message, $key) !== false) {
            return $message;
        }
    }
    
    // Mensaje genérico si no se encontró coincidencia
    return 'No se pudo procesar tu pago. Por favor verifica los datos de tu tarjeta o intenta con otro método de pago.';
}

/**
 * Obtiene las direcciones guardadas de un usuario
 * 
 * @param PDO $db Conexión a la base de datos
 * @param int $id_usuario ID del usuario
 * @return array Array de direcciones
 */
function getUserAddresses($db, $id_usuario) {
    try {
        // Usar direcciones_usuario (tabla principal con campos completos)
        $stmt = $db->prepare("SELECT id_direccion, nombre_direccion as titulo, 
                              CONCAT(calle, ' #', numero, ', ', colonia, ', ', ciudad) as direccion,
                              calle, numero, colonia, ciudad, estado, codigo_postal,
                              latitud, longitud, es_predeterminada
                              FROM direcciones_usuario WHERE id_usuario = ?
                              ORDER BY es_predeterminada DESC, id_direccion ASC");
        $stmt->execute([$id_usuario]);
        $direcciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($direcciones)) {
            return $direcciones;
        }
        
        // Fallback: intentar con ubicaciones_usuarios si existe
        try {
            $stmt = $db->prepare("SELECT id, id_usuario, nombre_ubicacion as titulo, 
                                  direccion, latitud, longitud, es_principal as es_predeterminada,
                                  NULL as calle, NULL as numero, NULL as colonia, NULL as ciudad
                                  FROM ubicaciones_usuarios WHERE id_usuario = ?
                                  ORDER BY es_principal DESC, id ASC");
            $stmt->execute([$id_usuario]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Tabla no existe, retornar vacío
            return [];
        }
        
    } catch (Exception $e) {
        error_log("Error obteniendo direcciones del usuario $id_usuario: " . $e->getMessage());
        return [];
    }
}
