-- =====================================================================
-- 003_repuestos_stock.sql
-- Existencias de cada repuesto: cuánto hay en bodega, desde qué cantidad
-- se avisa que hay que reponer y el costo de referencia por unidad.
--
-- - stock: baja al agregar el repuesto a un mantenimiento en proceso y
--   vuelve a subir si se quita antes de finalizar. Nunca queda negativo
--   (lo controla la aplicación con UPDATE ... WHERE stock >= cantidad).
-- - stock_minimo: el listado marca "Bajo el mínimo" cuando stock <= mínimo.
-- - costo_referencial: se propone como costo unitario al usarlo.
--
-- Se aplica sobre db_mysql.sql. Se puede ejecutar varias veces.
-- =====================================================================
USE inventario_tecnologico;

ALTER TABLE repuestos
  ADD COLUMN IF NOT EXISTS stock             DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER unidad,
  ADD COLUMN IF NOT EXISTS stock_minimo      DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER stock,
  ADD COLUMN IF NOT EXISTS costo_referencial DECIMAL(12,2) NULL AFTER stock_minimo;
