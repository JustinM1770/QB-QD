-- Migration to add tiempo_entrega column to pedidos table

ALTER TABLE pedidos
ADD COLUMN tiempo_entrega INT NULL AFTER fecha_entrega;
