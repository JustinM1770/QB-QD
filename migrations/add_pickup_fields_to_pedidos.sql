-- Migration to add tipo_pedido and pickup_time columns to pedidos table

ALTER TABLE pedidos
ADD COLUMN tipo_pedido VARCHAR(10) NOT NULL DEFAULT 'delivery' AFTER id_metodo_pago,
ADD COLUMN pickup_time TIME NULL AFTER tipo_pedido;
