-- =====================================================================
-- seed_demo.sql — Datos de DEMOSTRACIÓN para la página del docente
-- Base: inventario_tecnologico (MySQL/MariaDB, puerto 3307)
--
-- * Activa a la persona del usuario 'daniel' (rol consulta).
-- * Crea sede, áreas, ubicaciones, categorías, personal técnico y
--   5 equipos asignados a Daniel + 1 equipo ya retirado de su cargo.
-- * Se puede ejecutar varias veces: los equipos demo (códigos EQ-...)
--   se actualizan y sus asignaciones, movimientos, mantenimientos y
--   especificaciones se vuelven a crear (los reportes hechos desde la
--   página sobre estos equipos se borran al re-ejecutar).
-- =====================================================================
USE inventario_tecnologico;
SET NAMES utf8mb4;

START TRANSACTION;

-- ---------- Docente de prueba ----------
UPDATE personas p
  JOIN usuarios u ON u.persona_id = p.id
   SET p.activo = 1
 WHERE u.usuario = 'daniel';

SET @daniel := (SELECT persona_id FROM usuarios WHERE usuario = 'daniel');
SET @admin  := (SELECT id FROM usuarios WHERE rol = 'administrador' ORDER BY id LIMIT 1);

-- Cédulas de prueba válidas (dígito verificador correcto) para las personas
-- que se crearon con identificaciones de relleno ('000001', '2', ...).
UPDATE personas SET identificacion = '0602345670'
 WHERE id = @daniel AND identificacion IN ('2', '000002');
UPDATE personas p
  JOIN usuarios u ON u.persona_id = p.id
   SET p.identificacion = '0601234560'
 WHERE u.id = @admin AND p.identificacion = '000001';

-- ---------- Sede, áreas y ubicaciones ----------
INSERT IGNORE INTO sedes (nombre, direccion) VALUES
 ('Matriz Riobamba', 'Av. Daniel León Borja y Uruguay, Riobamba');
SET @sede := (SELECT id FROM sedes WHERE nombre = 'Matriz Riobamba');

INSERT INTO areas (nombre, tipo)
SELECT * FROM (
  SELECT 'Unidad de Tecnologías de la Información' AS nombre, 'unidad' AS tipo UNION ALL
  SELECT 'Unidad de Audiovisuales', 'unidad' UNION ALL
  SELECT 'Carrera de Enfermería', 'carrera' UNION ALL
  SELECT 'Carrera de Desarrollo de Software', 'carrera'
) n
WHERE NOT EXISTS (SELECT 1 FROM areas a WHERE a.nombre = n.nombre);

SET @a_ti  := (SELECT MIN(id) FROM areas WHERE nombre = 'Unidad de Tecnologías de la Información');
SET @a_av  := (SELECT MIN(id) FROM areas WHERE nombre = 'Unidad de Audiovisuales');
SET @a_enf := (SELECT MIN(id) FROM areas WHERE nombre = 'Carrera de Enfermería');
SET @a_sw  := (SELECT MIN(id) FROM areas WHERE nombre = 'Carrera de Desarrollo de Software');

INSERT INTO ubicaciones (sede_id, area_id, nombre, tipo, edificio, piso)
SELECT @sede, n.area, n.nombre, n.tipo, n.edificio, n.piso FROM (
  SELECT @a_sw  AS area, 'Laboratorio de Cómputo 2' AS nombre, 'laboratorio' AS tipo, 'Bloque B' AS edificio, '2' AS piso UNION ALL
  SELECT @a_sw,  'Laboratorio de Cómputo 1',            'laboratorio', 'Bloque B', '2' UNION ALL
  SELECT NULL,   'Sala de Docentes',                    'sala',        'Bloque A', '1' UNION ALL
  SELECT @a_enf, 'Coordinación de Enfermería',          'oficina',     'Bloque C', NULL UNION ALL
  SELECT @a_sw,  'Aula 204',                            'aula',        'Bloque B', '2' UNION ALL
  SELECT @a_enf, 'Laboratorio de Simulación Clínica',   'laboratorio', 'Bloque C', NULL UNION ALL
  SELECT @a_ti,  'Bodega Central',                      'bodega',      'Bloque A', 'Planta baja'
) n
WHERE NOT EXISTS (SELECT 1 FROM ubicaciones u WHERE u.sede_id = @sede AND u.nombre = n.nombre);

SET @u_lab2 := (SELECT MIN(id) FROM ubicaciones WHERE nombre = 'Laboratorio de Cómputo 2');
SET @u_lab1 := (SELECT MIN(id) FROM ubicaciones WHERE nombre = 'Laboratorio de Cómputo 1');
SET @u_doc  := (SELECT MIN(id) FROM ubicaciones WHERE nombre = 'Sala de Docentes');
SET @u_enf  := (SELECT MIN(id) FROM ubicaciones WHERE nombre = 'Coordinación de Enfermería');
SET @u_204  := (SELECT MIN(id) FROM ubicaciones WHERE nombre = 'Aula 204');
SET @u_sim  := (SELECT MIN(id) FROM ubicaciones WHERE nombre = 'Laboratorio de Simulación Clínica');
SET @u_bod  := (SELECT MIN(id) FROM ubicaciones WHERE nombre = 'Bodega Central');

-- ---------- Categorías ----------
INSERT IGNORE INTO categorias (nombre, descripcion) VALUES
 ('Computadores',       'Equipos de escritorio'),
 ('Portátiles',         'Laptops y tablets'),
 ('Impresoras',         'Impresoras y multifunción'),
 ('Audiovisuales',      'Proyectores, parlantes y pantallas'),
 ('Simulación clínica', 'Simuladores para prácticas de salud'),
 ('Monitores',          'Pantallas de escritorio');

-- ---------- Personal de apoyo ----------
-- Las versiones anteriores del seed usaban 'DEMO-TEC-01' y 'DEMO-INV-01'; se pasan a cédulas válidas.
UPDATE personas SET identificacion = '0603456781' WHERE identificacion = 'DEMO-TEC-01';
UPDATE personas SET identificacion = '0604567891' WHERE identificacion = 'DEMO-INV-01';
INSERT IGNORE INTO personas (identificacion, nombres, apellidos, cargo, area_id) VALUES
 ('0603456781', 'Diego', 'Paredes', 'Técnico de soporte', @a_ti),
 ('0604567891', 'Paúl',  'Guamán',  'Jefe de la Unidad de Inventario', @a_ti);
SET @diego := (SELECT id FROM personas WHERE identificacion = '0603456781');
SET @paul  := (SELECT id FROM personas WHERE identificacion = '0604567891');

-- Cuentas de prueba para los roles técnico e inventario (clave: 1234).
-- La clave en texto plano se convierte en hash en el primer inicio de sesión.
INSERT IGNORE INTO usuarios (persona_id, usuario, clave_hash, rol) VALUES
 (@diego, 'diego', '1234', 'tecnico'),
 (@paul,  'paul',  '1234', 'inventario');

-- ---------- Equipos ----------
INSERT INTO equipos (codigo, codigo_patrimonial, qr_token, categoria_id, area_propietaria_id, ubicacion_actual_id,
                     nombre, marca, modelo, numero_serie, fecha_adquisicion, proveedor, origen,
                     fecha_fin_garantia, estado_operativo, estado_inventario, creado_por)
VALUES
 ('EQ-LAB-0142', '2023-INF-0142', '7f3a91c2e8d04b6aa1c95e0f2d7b8c13', (SELECT id FROM categorias WHERE nombre = 'Computadores'),
  @a_sw, @u_lab2, 'Computador de escritorio', 'Dell', 'OptiPlex 7010', 'CZC3247LQX', '2023-03-15', 'Compudel S.A.', 'compra',
  '2026-03-15', 'operativo', 'activo', @admin),
 ('EQ-NB-0217', '2024-INF-0217', 'b21d0e6f93a74c58be0a17d4c3f2965e', (SELECT id FROM categorias WHERE nombre = 'Portátiles'),
  @a_ti, @u_doc, 'Laptop', 'Lenovo', 'ThinkPad E14 Gen 5', 'PF3K9TQ2', '2024-03-04', 'Lenovo Ecuador', 'compra',
  '2027-03-04', 'con_fallas', 'activo', @admin),
 ('EQ-IMP-0058', '2022-OFI-0058', '4c8e2a0b7d1f46e39a5c06b81e2d7f40', (SELECT id FROM categorias WHERE nombre = 'Impresoras'),
  @a_enf, @u_enf, 'Impresora láser', 'HP', 'LaserJet Pro M404dn', 'VNB3R41207', '2022-08-10', 'Tecnomega', 'compra',
  '2024-08-10', 'en_mantenimiento', 'activo', @admin),
 ('EQ-PRY-0031', '2021-AUD-0031', 'e95a3d71c0b24f8e86d2a4f07c1b3e59', (SELECT id FROM categorias WHERE nombre = 'Audiovisuales'),
  @a_av, @u_204, 'Proyector', 'Epson', 'PowerLite E20', 'X5JK2201843', '2021-05-20', NULL, 'donacion',
  NULL, 'operativo', 'activo', @admin),
 ('EQ-SIM-0009', '2025-ENF-0009', '0a6f5e2c9b8d47137e4a1c3d5f60b28a', (SELECT id FROM categorias WHERE nombre = 'Simulación clínica'),
  @a_enf, @u_sim, 'Simulador de signos vitales', 'MedSim', 'VS-220', 'MSA-VS-220931', '2025-01-20', 'MedSim Andina', 'compra',
  '2026-11-30', 'operativo', 'activo', @admin),
 ('EQ-MON-0144', '2023-INF-0144', 'c6a1f8e03d2b4975a0e8c3f1b6d2a047', (SELECT id FROM categorias WHERE nombre = 'Monitores'),
  @a_sw, @u_lab1, 'Monitor 24 pulgadas', 'Samsung', 'S24R350', '0Z7JH4LT600144', '2023-04-01', 'Compudel S.A.', 'compra',
  '2025-04-01', 'operativo', 'activo', @admin)
ON DUPLICATE KEY UPDATE
  categoria_id = VALUES(categoria_id), area_propietaria_id = VALUES(area_propietaria_id),
  ubicacion_actual_id = VALUES(ubicacion_actual_id), nombre = VALUES(nombre), marca = VALUES(marca),
  modelo = VALUES(modelo), fecha_fin_garantia = VALUES(fecha_fin_garantia),
  estado_operativo = VALUES(estado_operativo), estado_inventario = VALUES(estado_inventario);

SET @e_lab := (SELECT id FROM equipos WHERE codigo = 'EQ-LAB-0142');
SET @e_nb  := (SELECT id FROM equipos WHERE codigo = 'EQ-NB-0217');
SET @e_imp := (SELECT id FROM equipos WHERE codigo = 'EQ-IMP-0058');
SET @e_pry := (SELECT id FROM equipos WHERE codigo = 'EQ-PRY-0031');
SET @e_sim := (SELECT id FROM equipos WHERE codigo = 'EQ-SIM-0009');
SET @e_mon := (SELECT id FROM equipos WHERE codigo = 'EQ-MON-0144');

-- ---------- Limpiar el historial demo antes de recrearlo ----------
DELETE FROM especificaciones WHERE equipo_id IN (@e_lab, @e_nb, @e_imp, @e_pry, @e_sim, @e_mon);
DELETE FROM movimientos      WHERE equipo_id IN (@e_lab, @e_nb, @e_imp, @e_pry, @e_sim, @e_mon);
DELETE FROM mantenimientos   WHERE equipo_id IN (@e_lab, @e_nb, @e_imp, @e_pry, @e_sim, @e_mon);
DELETE FROM asignaciones     WHERE equipo_id IN (@e_lab, @e_nb, @e_imp, @e_pry, @e_sim, @e_mon);

-- ---------- Especificaciones ----------
INSERT INTO especificaciones (equipo_id, atributo, valor) VALUES
 (@e_lab, 'Procesador', 'Intel Core i5-13500'),
 (@e_lab, 'Memoria RAM', '16 GB DDR4'),
 (@e_lab, 'Almacenamiento', 'SSD 512 GB'),
 (@e_lab, 'Sistema operativo', 'Windows 11 Pro'),
 (@e_lab, 'Dirección IP', '10.20.2.142'),
 (@e_nb,  'Procesador', 'AMD Ryzen 5 7530U'),
 (@e_nb,  'Memoria RAM', '16 GB'),
 (@e_nb,  'Almacenamiento', 'SSD 512 GB'),
 (@e_imp, 'Conexión', 'Red · 10.20.5.58'),
 (@e_pry, 'Luminosidad', '3 400 lúmenes'),
 (@e_pry, 'Horas de lámpara', '1 820 h'),
 (@e_sim, 'Componentes', 'Brazalete adulto, sensor SpO₂, adaptador');

-- ---------- Asignaciones a Daniel ----------
INSERT INTO asignaciones (equipo_id, persona_id, ubicacion_entrega_id, fecha_entrega, fecha_devolucion,
                          estado_entrega, estado_devolucion, observaciones, registrado_por) VALUES
 (@e_lab, @daniel, @u_bod,  '2026-11-03 09:00:00', NULL, 'Sin novedades; con teclado y mouse', NULL, NULL, @admin),
 (@e_nb,  @daniel, @u_doc,  '2024-03-04 10:00:00', NULL, 'Nueva, con cargador', NULL, NULL, @admin),
 (@e_imp, @daniel, @u_enf,  '2022-08-10 08:30:00', NULL, 'Nueva, con tóner inicial', NULL, NULL, @admin),
 (@e_pry, @daniel, @u_204,  '2026-11-02 07:50:00', NULL, 'Sin novedades; con cable HDMI y control', NULL, NULL, @admin),
 (@e_sim, @daniel, @u_sim,  '2025-02-03 09:00:00', NULL, 'Nuevo, con maletín', NULL, NULL, @admin),
 -- Ya no está a su cargo: no debe aparecer en la página
 (@e_mon, @daniel, @u_lab2, '2023-04-03 09:00:00', '2026-10-15 11:00:00', 'Nuevo', 'Sin novedades; con cable HDMI',
  'Renovación del puesto de trabajo', @admin);

-- ---------- Movimientos ----------
INSERT INTO movimientos (equipo_id, ubicacion_origen_id, ubicacion_destino_id, fecha, motivo, autorizado_por, registrado_por) VALUES
 (@e_lab, @u_bod,  @u_lab2, '2026-11-03 09:25:00', 'Instalación en laboratorio', @paul, @admin),
 (@e_mon, @u_lab2, @u_lab1, '2026-10-15 11:30:00', 'Reasignación', @paul, @admin);

-- ---------- Mantenimientos ----------
INSERT INTO mantenimientos (equipo_id, tecnico_id, tipo, fecha_programada, fecha_inicio, fecha_fin,
                            problema_reportado, diagnostico, trabajo_realizado, proximo_mantenimiento,
                            estado, registrado_por, creado_en) VALUES
 -- Preventivo programado del computador del laboratorio
 (@e_lab, @diego, 'preventivo', '2026-11-12', NULL, NULL, NULL, NULL, NULL, NULL,
  'programado', @admin, '2026-11-03 10:00:00'),
 -- Falla reportada de la laptop, pendiente de diagnóstico
 (@e_nb, NULL, 'diagnostico', NULL, NULL, NULL, 'La batería no carga aunque el cargador está conectado.', NULL, NULL, NULL,
  'programado', @admin, '2026-11-06 08:15:00'),
 -- Correctivo en curso de la impresora
 (@e_imp, @diego, 'correctivo', '2026-11-05', '2026-11-05 14:10:00', NULL,
  'Atasco recurrente de papel', 'Rodillo de arrastre desgastado', NULL, NULL,
  'en_proceso', @admin, '2026-11-05 13:50:00'),
 -- Preventivo finalizado del simulador
 (@e_sim, @diego, 'preventivo', '2026-10-28', '2026-10-28 09:00:00', '2026-10-28 11:00:00',
  NULL, NULL, 'Calibración del sensor de presión', '2027-04-28',
  'finalizado', @admin, '2026-10-20 08:00:00');

COMMIT;
