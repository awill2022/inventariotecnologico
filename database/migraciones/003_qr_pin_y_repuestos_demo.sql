-- =====================================================================
-- 003_qr_pin_y_repuestos_demo.sql
-- * Agrega pin_reporte (4 dígitos) opcional a `personas` para reportes QR
--   sin necesidad de iniciar sesión completa (por defecto usa los últimos
--   4 dígitos de la cédula).
-- * Inserta repuestos y componentes iniciales de prueba.
-- =====================================================================
USE inventario_tecnologico;
SET NAMES utf8mb4;

ALTER TABLE personas
  ADD COLUMN IF NOT EXISTS pin_reporte CHAR(4) NULL
  COMMENT 'PIN de 4 dígitos para reportar fallas por QR (NULL = últimos 4 de cédula)'
  AFTER telefono;

-- Repuestos de catálogo para pruebas de mantenimiento
INSERT IGNORE INTO repuestos (codigo, nombre, descripcion, unidad, activo) VALUES
 ('REP-SSD-512', 'Disco SSD 512 GB SATA/NVMe', 'Unidad de estado sólido para reemplazo', 'unidad', 1),
 ('REP-RAM-8G',  'Memoria RAM 8 GB DDR4',      'Módulo DDR4 3200 MHz',                   'unidad', 1),
 ('REP-ROD-HP',  'Kit de rodillos HP LaserJet','Rodillo de arrastre y separación',       'kit',    1),
 ('REP-BAT-LEN', 'Batería interna ThinkPad E14','Batería Li-Ion 45Wh original',          'unidad', 1),
 ('REP-PASTA',   'Pasta térmica de alto rendimiento', 'Aplicación en mantenimiento preventivo', 'aplicacion', 1);

-- Componentes instalados actuales en los equipos de demostración
SET @e_lab := (SELECT id FROM equipos WHERE codigo = 'EQ-LAB-0142' LIMIT 1);
SET @e_nb  := (SELECT id FROM equipos WHERE codigo = 'EQ-NB-0217' LIMIT 1);
SET @e_imp := (SELECT id FROM equipos WHERE codigo = 'EQ-IMP-0058' LIMIT 1);

INSERT INTO componentes (equipo_id, tipo, marca, modelo, numero_serie, capacidad, fecha_instalacion, observaciones)
SELECT * FROM (
  SELECT @e_lab AS equipo_id, 'Almacenamiento' AS tipo, 'Kingston' AS marca, 'NV2 NVMe' AS modelo, 'KNG-512-091' AS numero_serie, '512 GB' AS capacidad, '2023-03-15' AS fecha_instalacion, 'Instalado de fábrica' AS observaciones UNION ALL
  SELECT @e_lab, 'Memoria RAM', 'Crucial', 'DDR4-3200', 'CRU-16G-882', '16 GB', '2023-03-15', 'Módulo principal' UNION ALL
  SELECT @e_nb,  'Batería',     'Lenovo',  'L20M3PF0',  'BAT-LEN-441', '45 Wh', '2024-03-04', 'Reporta desgaste de carga' UNION ALL
  SELECT @e_imp, 'Rodillo de arrastre', 'HP', 'RM2-5452', 'HP-ROD-112', 'Estándar', '2022-08-10', 'Bandeja 2'
) c
WHERE c.equipo_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM componentes ex
     WHERE ex.equipo_id = c.equipo_id AND ex.numero_serie = c.numero_serie
  );