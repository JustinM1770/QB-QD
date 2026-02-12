-- Script para crear pedido de prueba completo
-- Fecha: 2025-11-02

-- Insertar pedido con estado 4 (Listo para entrega)
INSERT INTO pedidos (
    id_usuario,
    id_negocio,
    id_repartidor,
    id_estado,
    id_direccion,
    tipo_pedido,
    total_productos,
    costo_envio,
    cargo_servicio,
    impuestos,
    propina,
    monto_total,
    instrucciones_especiales,
    tiempo_entrega_estimado,
    fecha_creacion,
    ganancia,
    metodo_pago,
    payment_status
) VALUES (
    4,                                              -- id_usuario (cliente)
    5,                                              -- id_negocio (OREZ)
    NULL,                                           -- id_repartidor (sin asignar)
    4,                                              -- id_estado (Listo para entrega)
    1,                                              -- id_direccion
    'delivery',                                     -- tipo_pedido
    250.00,                                         -- total_productos
    35.00,                                          -- costo_envio
    15.00,                                          -- cargo_servicio
    12.50,                                          -- impuestos
    20.00,                                          -- propina
    332.50,                                         -- monto_total
    'Tocar timbre 3 veces. Casa color azul.',     -- instrucciones_especiales
    DATE_ADD(NOW(), INTERVAL 30 MINUTE),           -- tiempo_entrega_estimado
    NOW(),                                          -- fecha_creacion
    35.00,                                          -- ganancia (para el repartidor)
    'mercadopago',                                  -- metodo_pago
    'approved'                                      -- payment_status
);

-- Obtener el ID del pedido recién creado
SET @pedido_id = LAST_INSERT_ID();

-- Mostrar el pedido creado
SELECT 
    p.id_pedido,
    p.id_estado,
    u.nombre as cliente,
    n.nombre as negocio,
    p.monto_total,
    p.ganancia,
    p.instrucciones_especiales,
    p.fecha_creacion
FROM pedidos p
LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
LEFT JOIN negocios n ON p.id_negocio = n.id_negocio
WHERE p.id_pedido = @pedido_id;
