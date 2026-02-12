-- Migration to add fecha_entrega column to pedidos table

ALTER TABLE pedidos
ADD COLUMN fecha_entrega DATETIME NULL AFTER fecha_creacion;
