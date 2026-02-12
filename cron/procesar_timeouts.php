#!/usr/bin/env php
<?php
/**
 * Cron Job: Procesar Timeouts y Reasignaciones
 * 
 * Ejecutar cada 5 minutos agregando a crontab (crontab -e):
 * CRON_PATTERN 5min php /var/www/html/cron/procesar_timeouts.php >> /var/log/quickbite/timeouts.log 2>&1
 * 
 * O con systemd timer para mejor control
 */

// Evitar ejecución desde web
if (php_sapi_name() !== 'cli') {
    die('Solo ejecución por CLI');
}

// Configurar timezone
date_default_timezone_set('America/Mexico_City');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/GestionPedidos.php';

$inicio = microtime(true);
$fecha = date('Y-m-d H:i:s');

echo "[$fecha] Iniciando procesamiento de timeouts...\n";

try {
    $gestion = new GestionPedidos();
    
    // Procesar timeouts
    $resultado = $gestion->procesarTimeouts();
    
    echo "[$fecha] Resultados:\n";
    echo "  - Pedidos procesados: {$resultado['procesados']}\n";
    echo "  - Reasignados exitosamente: {$resultado['reasignados']}\n";
    echo "  - Sin repartidor disponible: {$resultado['sin_repartidor']}\n";
    
    if (!empty($resultado['errores'])) {
        echo "  - Errores:\n";
        foreach ($resultado['errores'] as $error) {
            echo "    * $error\n";
        }
    }
    
    // Limpiar sugerencias batch expiradas
    $database = new Database();
    $pdo = $database->getConnection();
    
    $stmt = $pdo->prepare("
        UPDATE sugerencias_batch 
        SET estado = 'expirada' 
        WHERE estado = 'pendiente' AND fecha_expiracion < NOW()
    ");
    $stmt->execute();
    $expiradas = $stmt->rowCount();
    
    if ($expiradas > 0) {
        echo "  - Sugerencias batch expiradas: {$expiradas}\n";
    }
    
    // Limpiar pedidos_disponibles_batch antiguos
    $stmt = $pdo->prepare("
        DELETE FROM pedidos_disponibles_batch 
        WHERE fecha_agregado < DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ");
    $stmt->execute();
    
    // Actualizar cache de pedidos disponibles para batch
    actualizarCacheBatch($pdo);
    
    $duracion = round(microtime(true) - $inicio, 3);
    echo "[$fecha] Completado en {$duracion}s\n\n";
    
} catch (Exception $e) {
    echo "[$fecha] ERROR: " . $e->getMessage() . "\n";
    error_log("Cron timeout error: " . $e->getMessage());
    exit(1);
}

/**
 * Actualizar cache de pedidos disponibles para sugerencias batch
 */
function actualizarCacheBatch(PDO $pdo): void {
    // Limpiar cache actual
    $pdo->exec("DELETE FROM pedidos_disponibles_batch");
    
    // Insertar pedidos listos para recoger
    $stmt = $pdo->prepare("
        INSERT INTO pedidos_disponibles_batch 
        (id_pedido, id_negocio, latitud_negocio, longitud_negocio, 
         latitud_entrega, longitud_entrega, distancia_negocio_entrega, fecha_listo, prioridad)
        SELECT 
            p.id_pedido,
            p.id_negocio,
            n.latitud,
            n.longitud,
            d.latitud,
            d.longitud,
            (6371 * acos(cos(radians(n.latitud)) * cos(radians(d.latitud)) * cos(radians(d.longitud) - radians(n.longitud)) + sin(radians(n.latitud)) * sin(radians(d.latitud)))) as distancia,
            COALESCE(p.fecha_asignacion_repartidor, p.fecha_creacion),
            COALESCE(p.prioridad, 0)
        FROM pedidos p
        JOIN negocios n ON p.id_negocio = n.id_negocio
        JOIN direcciones_usuario d ON p.id_direccion = d.id_direccion
        WHERE p.id_estado IN (4, 8, 10)
          AND p.id_repartidor IS NULL
          AND d.latitud IS NOT NULL
        ON DUPLICATE KEY UPDATE prioridad = VALUES(prioridad)
    ");
    $stmt->execute();
}
