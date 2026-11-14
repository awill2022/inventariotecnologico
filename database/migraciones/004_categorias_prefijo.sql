-- =====================================================================
-- 004_categorias_prefijo.sql
-- Prefijo corto de cada categoría (PC, NB, IMP...). Con él se propone
-- el código interno de los equipos nuevos: EQ-{PREFIJO}-{número}.
-- Lo escribe el administrador en el catálogo de categorías; es único.
--
-- Se aplica sobre db_mysql.sql. Se puede ejecutar varias veces.
-- =====================================================================
USE inventario_tecnologico;

ALTER TABLE categorias
  ADD COLUMN IF NOT EXISTS prefijo VARCHAR(6) NULL AFTER nombre;

-- Índice único aparte: ADD COLUMN IF NOT EXISTS ... UNIQUE crearía otro índice en cada ejecución
ALTER TABLE categorias
  ADD UNIQUE INDEX IF NOT EXISTS uq_categoria_prefijo (prefijo);
