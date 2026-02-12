-- Script para crear múltiples pedidos de prueba
-- Para pruebas exhaustivas del sistema de delivery

-- Pedido 2: Con propina alta
INSERT INTO pedidos (
    id_usuario, id_negocio, id_repartidor, id_estado, id_direccion,
    tipo_pedido, total_productos, costo_envio, cargo_servicio,
    impuestos, propina, monto_total, instrucciones_especiales,
    tiempo_entrega_estimado, fecha_creacion, ganancia,
    metodo_pago, payment_status
) VALUES (
    4, 6, NULL, 4, 1, 'delivery',
    450.00, 35.00, 20.00, 22.50, 50.00, 577.50,
    'Departamento 302. Dejar en recepción si no hay nadie.',
    DATE_ADD(NOW(), INTERVAL 25 MINUTE), NOW(), 35.00,
    'mercadopago', 'approved'
);

-- Pedido 3: Pedido urgente
INSERT INTO pedidos (
    id_usuario, id_negocio, id_repartidor, id_estado, id_direccion,
    tipo_pedido, total_productos, costo_envio, cargo_servicio,
    impuestos, propina, monto_total, instrucciones_especiales,
    tiempo_entrega_estimado, fecha_creacion, ganancia,
    metodo_pago, payment_status
) VALUES (
    4, 7, NULL, 4, 1, 'delivery',
    180.00, 40.00, 12.00, 9.00, 15.00, 256.00,
    '¡URGENTE! Cliente esperando. Llamar al llegar.',
    DATE_ADD(NOW(), INTERVAL 15 MINUTE), NOW(), 40.00,
    'efectivo', 'pending'
);

-- Pedido 4: Con instrucciones detalladas
INSERT INTO pedidos (
    id_usuario, id_negocio, id_repartidor, id_estado, id_direccion,
    tipo_pedido, total_productos, costo_envio, cargo_servicio,
    impuestos, propina, monto_total, instrucciones_especiales,
    tiempo_entrega_estimado, fecha_creacion, ganancia,
    metodo_pago, payment_status
) VALUES (
    4, 5, NULL, 4, 1, 'delivery',
    320.00, 35.00, 18.00, 16.00, 25.00, 414.00,
    'Casa con jardín grande. Portón negro. Si está cerrado, tocar el timbre blanco del lado derecho. Perro amigable.',
    DATE_ADD(NOW(), INTERVAL 35 MINUTE), NOW(), 35.00,
    'mercadopago', 'approved'
);

-- Pedido 5: Sin propina
INSERT INTO pedidos (
    id_usuario, id_negocio, id_repartidor, id_estado, id_direccion,
    tipo_pedido, total_productos, costo_envio, cargo_servicio,
    impuestos, propina, monto_total, instrucciones_especiales,
    tiempo_entrega_estimado, fecha_creacion, ganancia,
    metodo_pago, payment_status
) VALUES (
    4, 6, NULL, 4, 1, 'delivery',
    150.00, 35.00, 10.00, 7.50, 0.00, 202.50,
    'Edificio blanco, tercer piso.',
    DATE_ADD(NOW(), INTERVAL 40 MINUTE), NOW(), 35.00,
    'mercadopago', 'approved'
);

-- Mostrar todos los pedidos creados
SELECT 
    p.id_pedido,
    p.id_estado,
    u.nombre as cliente,
    n.nombre as negocio,
    p.monto_total,
    p.propina,
    p.ganancia,
    p.metodo_pago,
    LEFT(p.instrucciones_especiales, 50) as instrucciones,
    p.fecha_creacion
FROM pedidos p
LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
LEFT JOIN negocios n ON p.id_negocio = n.id_negocio
WHERE p.id_estado = 4 AND p.id_repartidor IS NULL
ORDER BY p.fecha_creacion DESC
LIMIT 10;
