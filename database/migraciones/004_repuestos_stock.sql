USE inventario_tecnologico;

ALTER TABLE repuestos
  ADD COLUMN IF NOT EXISTS stock DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER unidad,
  ADD COLUMN IF NOT EXISTS stock_minimo DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER stock;

UPDATE repuestos SET stock = 12, stock_minimo = 3 WHERE stock = 0;