-- =====================================================================
-- seed_demo.sql — Datos de DEMOSTRACIÓN para la página del docente
-- Base: inventario_tecnologico (MySQL/MariaDB, puerto 3307)
--
-- * Activa a la persona del usuario 'daniel' (rol consulta).
-- * Crea sede, áreas, ubicaciones, categorías, personal técnico y
--   5 equipos asignados a Daniel + 1 equipo ya retirado de su cargo.
-- * Sprint 3: prefijos de categoría, 2 equipos en bodega, 1 dado de baja
--   y componentes instalados. Requiere las migraciones 001, 002, 003 y 004.
-- * Sprint 5: catálogo de repuestos con stock (uno bajo el mínimo y uno
--   agotado) y repuestos usados en dos mantenimientos.
-- * Sprint 7: segunda sede (Sede Norte) con 5 equipos, categoría Redes,
--   agenda de mantenimientos con fechas relativas a hoy (uno atrasado y
--   varios próximos) y filas de auditoría de ejemplo para el visor.
-- * Se puede ejecutar varias veces: los equipos demo (códigos EQ-...)
--   se actualizan y sus asignaciones, movimientos, mantenimientos,
--   especificaciones, componentes y adjuntos se vuelven a crear (los reportes
--   y fotos hechos desde la página sobre estos equipos se borran al re-ejecutar).
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

-- Sprint 7: segunda sede, para que el dashboard muestre la distribución por sede
INSERT IGNORE INTO sedes (nombre, direccion) VALUES
 ('Sede Norte', 'Av. Pedro Vicente Maldonado, Riobamba');
SET @sede2 := (SELECT id FROM sedes WHERE nombre = 'Sede Norte');

INSERT INTO areas (nombre, tipo)
SELECT 'Carrera de Redes y Telecomunicaciones', 'carrera'
 WHERE NOT EXISTS (SELECT 1 FROM areas WHERE nombre = 'Carrera de Redes y Telecomunicaciones');
SET @a_red := (SELECT MIN(id) FROM areas WHERE nombre = 'Carrera de Redes y Telecomunicaciones');

INSERT INTO ubicaciones (sede_id, area_id, nombre, tipo, edificio, piso)
SELECT @sede2, n.area, n.nombre, n.tipo, n.edificio, n.piso FROM (
  SELECT @a_red AS area, 'Laboratorio de Redes' AS nombre, 'laboratorio' AS tipo, 'Bloque Norte' AS edificio, '1' AS piso UNION ALL
  SELECT @a_red, 'Aula 105',     'aula',   'Bloque Norte', '1' UNION ALL
  SELECT @a_ti,  'Bodega Norte', 'bodega', 'Bloque Norte', 'Planta baja'
) n
WHERE NOT EXISTS (SELECT 1 FROM ubicaciones u WHERE u.sede_id = @sede2 AND u.nombre = n.nombre);

SET @u_red  := (SELECT MIN(id) FROM ubicaciones WHERE sede_id = @sede2 AND nombre = 'Laboratorio de Redes');
SET @u_105  := (SELECT MIN(id) FROM ubicaciones WHERE sede_id = @sede2 AND nombre = 'Aula 105');
SET @u_bodn := (SELECT MIN(id) FROM ubicaciones WHERE sede_id = @sede2 AND nombre = 'Bodega Norte');

-- ---------- Categorías ----------
INSERT IGNORE INTO categorias (nombre, descripcion) VALUES
 ('Computadores',       'Equipos de escritorio'),
 ('Portátiles',         'Laptops y tablets'),
 ('Impresoras',         'Impresoras y multifunción'),
 ('Audiovisuales',      'Proyectores, parlantes y pantallas'),
 ('Simulación clínica', 'Simuladores para prácticas de salud'),
 ('Monitores',          'Pantallas de escritorio'),
 ('Redes',              'Switches, routers y puntos de acceso');

-- Prefijo con el que se propone el código de los equipos nuevos (migración 004)
UPDATE categorias SET prefijo = CASE nombre
    WHEN 'Computadores'       THEN 'PC'
    WHEN 'Portátiles'         THEN 'NB'
    WHEN 'Impresoras'         THEN 'IMP'
    WHEN 'Audiovisuales'      THEN 'AV'
    WHEN 'Simulación clínica' THEN 'SIM'
    WHEN 'Monitores'          THEN 'MON'
    WHEN 'Redes'              THEN 'RED'
  END
 WHERE prefijo IS NULL
   AND nombre IN ('Computadores', 'Portátiles', 'Impresoras', 'Audiovisuales', 'Simulación clínica', 'Monitores', 'Redes');

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
  '2025-04-01', 'operativo', 'activo', @admin),
 -- En bodega, sin asignar (Sprint 3)
 ('EQ-NB-0230', '2026-INF-0230', '3d9b6e21f0a84c7e95b1d2c4a6f8e013', (SELECT id FROM categorias WHERE nombre = 'Portátiles'),
  @a_ti, @u_bod, 'Laptop', 'HP', 'ProBook 450 G10', '5CD4281KJ9', '2026-09-22', 'Tecnomega', 'compra',
  '2029-09-22', 'operativo', 'en_bodega', @admin),
 ('EQ-AV-0040', NULL, '8a2c4e6f1b3d4f5a9c7e0b2d4f6a8c1e', (SELECT id FROM categorias WHERE nombre = 'Audiovisuales'),
  @a_av, @u_bod, 'Parlante portátil', 'JBL', 'EON One Compact', 'JBL-EON1C-5521', '2025-06-12', NULL, 'donacion',
  NULL, 'con_fallas', 'en_bodega', @admin),
 -- Dado de baja (migración 001)
 ('EQ-IMP-0012', '2016-OFI-0012', 'f1e2d3c4b5a6478990a1b2c3d4e5f607', (SELECT id FROM categorias WHERE nombre = 'Impresoras'),
  @a_enf, @u_bod, 'Impresora de inyección', 'Epson', 'L355', 'S2AK100512', '2016-02-15', NULL, 'compra',
  NULL, 'inoperativo', 'de_baja', @admin),
 -- Sprint 7: equipos de la Sede Norte (red, laboratorio y bodega)
 ('EQ-RED-0007', '2025-RED-0007', '95af5b380ba517dc3601a61a35475763', (SELECT id FROM categorias WHERE nombre = 'Redes'),
  @a_red, @u_red, 'Switch de 24 puertos', 'Cisco', 'CBS250-24T-4G', 'FOC2547Y0QK', '2025-08-18', 'Comware', 'compra',
  '2028-08-18', 'operativo', 'activo', @admin),
 ('EQ-RED-0011', '2025-RED-0011', '90453332487d7d4d9b0e078dacce3557', (SELECT id FROM categorias WHERE nombre = 'Redes'),
  @a_red, @u_105, 'Punto de acceso inalámbrico', 'Ubiquiti', 'U6 Lite', 'UBNT-U6L-7A21C9', '2025-08-18', 'Comware', 'compra',
  '2026-12-18', 'con_fallas', 'activo', @admin),
 ('EQ-PC-0310', '2025-INF-0310', '7d5396982068bafdad0446b6e93aac50', (SELECT id FROM categorias WHERE nombre = 'Computadores'),
  @a_red, @u_red, 'Computador de escritorio', 'Lenovo', 'ThinkCentre M70s Gen 4', 'MJ0K7A31', '2025-09-01', 'Lenovo Ecuador', 'compra',
  '2028-09-01', 'operativo', 'activo', @admin),
 ('EQ-PC-0311', '2025-INF-0311', 'e902775de4dd3f8e696fac19997dc3d5', (SELECT id FROM categorias WHERE nombre = 'Computadores'),
  @a_red, @u_red, 'Computador de escritorio', 'Lenovo', 'ThinkCentre M70s Gen 4', 'MJ0K7A44', '2025-09-01', 'Lenovo Ecuador', 'compra',
  '2028-09-01', 'inoperativo', 'activo', @admin),
 ('EQ-MON-0150', '2025-INF-0150', 'd0139d8db0ed4f60aeec11b774e8fa3e', (SELECT id FROM categorias WHERE nombre = 'Monitores'),
  @a_red, @u_bodn, 'Monitor 22 pulgadas', 'LG', '22MK430H', '507NTPC1B150', '2025-09-01', 'Compudel S.A.', 'compra',
  '2027-09-01', 'operativo', 'en_bodega', @admin)
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
SET @e_nb2 := (SELECT id FROM equipos WHERE codigo = 'EQ-NB-0230');
SET @e_av  := (SELECT id FROM equipos WHERE codigo = 'EQ-AV-0040');
SET @e_baj := (SELECT id FROM equipos WHERE codigo = 'EQ-IMP-0012');
SET @e_sw   := (SELECT id FROM equipos WHERE codigo = 'EQ-RED-0007');
SET @e_ap   := (SELECT id FROM equipos WHERE codigo = 'EQ-RED-0011');
SET @e_pc10 := (SELECT id FROM equipos WHERE codigo = 'EQ-PC-0310');
SET @e_pc11 := (SELECT id FROM equipos WHERE codigo = 'EQ-PC-0311');
SET @e_mon2 := (SELECT id FROM equipos WHERE codigo = 'EQ-MON-0150');

UPDATE equipos SET fecha_baja = '2026-06-30', motivo_baja = 'Cabezal dañado; la reparación cuesta más que un equipo nuevo.'
 WHERE id = @e_baj;

-- ---------- Limpiar el historial demo antes de recrearlo ----------
SET @demo := CONCAT_WS(',', @e_lab, @e_nb, @e_imp, @e_pry, @e_sim, @e_mon, @e_nb2, @e_av, @e_baj,
                         @e_sw, @e_ap, @e_pc10, @e_pc11, @e_mon2);
-- Adjuntos (fotos de reportes, actas firmadas) del historial demo: se quitan antes que su registro.
-- El archivo físico queda en storage/archivos; se puede borrar a mano.
DELETE FROM archivos
 WHERE FIND_IN_SET(equipo_id, @demo)
    OR mantenimiento_id IN (SELECT id FROM mantenimientos WHERE FIND_IN_SET(equipo_id, @demo))
    OR asignacion_id    IN (SELECT id FROM asignaciones   WHERE FIND_IN_SET(equipo_id, @demo))
    OR movimiento_id    IN (SELECT id FROM movimientos    WHERE FIND_IN_SET(equipo_id, @demo));
DELETE FROM componentes      WHERE FIND_IN_SET(equipo_id, @demo);
DELETE FROM especificaciones WHERE FIND_IN_SET(equipo_id, @demo);
DELETE FROM movimientos      WHERE FIND_IN_SET(equipo_id, @demo);
-- Repuestos usados en los mantenimientos demo (Sprint 5): se borran antes que sus mantenimientos
DELETE mr FROM mantenimiento_repuestos mr
  JOIN mantenimientos m ON m.id = mr.mantenimiento_id
 WHERE FIND_IN_SET(m.equipo_id, @demo);
DELETE FROM mantenimientos   WHERE FIND_IN_SET(equipo_id, @demo);
DELETE FROM asignaciones     WHERE FIND_IN_SET(equipo_id, @demo);

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
 (@e_sim, 'Componentes', 'Brazalete adulto, sensor SpO₂, adaptador'),
 (@e_nb2, 'Procesador', 'Intel Core i7-1355U'),
 (@e_nb2, 'Memoria RAM', '16 GB DDR4'),
 (@e_nb2, 'Almacenamiento', 'SSD 1 TB');

-- ---------- Componentes ----------
INSERT INTO componentes (equipo_id, tipo, marca, modelo, numero_serie, capacidad, fecha_instalacion, fecha_retiro, observaciones) VALUES
 (@e_lab, 'Memoria RAM',    'Kingston', 'KVR32N22S8/8', 'KG8A1123', '8 GB',   '2023-03-15', NULL, 'De fábrica'),
 (@e_lab, 'Memoria RAM',    'Kingston', 'KVR32N22S8/8', 'KG8A1187', '8 GB',   '2025-02-10', NULL, 'Ampliación a 16 GB'),
 (@e_lab, 'Almacenamiento', 'Samsung',  '980 PRO',      'S5GXNF0T', '512 GB', '2025-02-10', NULL, NULL),
 (@e_lab, 'Almacenamiento', 'Seagate',  'Barracuda',    'ZDH4K1QX', '1 TB',   '2023-03-15', '2025-02-10', 'Se reemplazó por SSD');

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
 (@e_mon, @u_lab2, @u_lab1, '2026-10-15 11:30:00', 'Reasignación', @paul, @admin),
 (@e_nb2, NULL,    @u_bod,  '2026-09-22 10:00:00', 'Ingreso al inventario', NULL, @admin),
 (@e_av,  NULL,    @u_bod,  '2025-06-12 09:00:00', 'Ingreso al inventario', NULL, @admin),
 (@e_baj, NULL,    @u_bod,  '2016-02-15 09:00:00', 'Ingreso al inventario', NULL, @admin),
 -- Sprint 7: Sede Norte
 (@e_sw,   NULL, @u_red,  '2025-08-20 10:00:00', 'Ingreso al inventario', NULL, @admin),
 (@e_ap,   NULL, @u_105,  '2025-08-20 10:15:00', 'Ingreso al inventario', NULL, @admin),
 (@e_pc10, NULL, @u_red,  '2025-09-03 09:00:00', 'Ingreso al inventario', NULL, @admin),
 (@e_pc11, NULL, @u_red,  '2025-09-03 09:05:00', 'Ingreso al inventario', NULL, @admin),
 (@e_mon2, NULL, @u_bodn, '2025-09-03 09:10:00', 'Ingreso al inventario', NULL, @admin);

-- ---------- Mantenimientos ----------
INSERT INTO mantenimientos (equipo_id, tecnico_id, tipo, fecha_programada, fecha_inicio, fecha_fin,
                            problema_reportado, diagnostico, trabajo_realizado, proximo_mantenimiento,
                            estado, registrado_por, creado_en) VALUES
 -- Preventivo programado del computador del laboratorio (para hoy, según el día en que se ejecute)
 (@e_lab, @diego, 'preventivo', CURDATE(), NULL, NULL, NULL, NULL, NULL, NULL,
  'programado', @admin, '2026-11-03 10:00:00'),
 -- Sprint 7: agenda del dashboard con fechas relativas a hoy (un atrasado y tres próximos)
 (@e_pc11, @diego, 'correctivo', CURDATE() - INTERVAL 4 DAY, NULL, NULL,
  'No enciende: posible fuente de poder quemada.', NULL, NULL, NULL,
  'programado', @admin, NOW() - INTERVAL 6 DAY),
 (@e_sw, @diego, 'preventivo', CURDATE() + INTERVAL 6 DAY, NULL, NULL, NULL, NULL, NULL, NULL,
  'programado', @admin, NOW() - INTERVAL 3 DAY),
 (@e_pc10, NULL, 'actualizacion', CURDATE() + INTERVAL 15 DAY, NULL, NULL,
  'Actualizar el sistema operativo y el software del laboratorio.', NULL, NULL, NULL,
  'programado', @admin, NOW() - INTERVAL 2 DAY),
 (@e_mon, @diego, 'preventivo', CURDATE() + INTERVAL 24 DAY, NULL, NULL, NULL, NULL, NULL, NULL,
  'programado', @admin, NOW() - INTERVAL 2 DAY),
 -- Falla reportada del punto de acceso, sin atender
 (@e_ap, NULL, 'diagnostico', NULL, NULL, NULL, 'La red inalámbrica se desconecta cada pocos minutos.', NULL, NULL, NULL,
  'programado', @admin, NOW() - INTERVAL 1 DAY),
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

-- ---------- Repuestos (Sprint 5, migración 003) ----------
-- El stock se vuelve a fijar en cada ejecución; ya descuenta lo usado abajo.
INSERT INTO repuestos (codigo, nombre, descripcion, unidad, stock, stock_minimo, costo_referencial, activo) VALUES
 ('RAM-DDR4-8',   'Memoria RAM DDR4 8 GB',          'Módulo DIMM 3200 MHz para computadores de escritorio', 'unidad', 4, 2, 35.50, 1),
 ('SSD-512',      'Disco SSD 512 GB',               'SATA 2,5 pulgadas',                                    'unidad', 2, 1, 48.00, 1),
 ('PASTA-TERM',   'Pasta térmica',                  'Jeringa de 4 g',                                       'frasco', 5, 2, 6.25, 1),
 ('KIT-LIMP',     'Kit de limpieza electrónica',    'Aire comprimido, alcohol isopropílico y paños',        'kit',    1, 2, 12.00, 1),
 ('RODILLO-HP',   'Rodillo de arrastre de impresora', 'Compatible con HP LaserJet Pro',                    'unidad', 0, 1, 18.75, 1)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), descripcion = VALUES(descripcion), unidad = VALUES(unidad),
  stock = VALUES(stock), stock_minimo = VALUES(stock_minimo), costo_referencial = VALUES(costo_referencial), activo = 1;

SET @m_imp := (SELECT id FROM mantenimientos WHERE equipo_id = @e_imp AND estado = 'en_proceso' ORDER BY id DESC LIMIT 1);
SET @m_sim := (SELECT id FROM mantenimientos WHERE equipo_id = @e_sim AND estado = 'finalizado' ORDER BY id DESC LIMIT 1);
INSERT INTO mantenimiento_repuestos (mantenimiento_id, repuesto_id, cantidad, costo_unitario, detalle) VALUES
 (@m_imp, (SELECT id FROM repuestos WHERE codigo = 'RODILLO-HP'), 1, 18.75, 'Reemplazo del rodillo desgastado'),
 (@m_sim, (SELECT id FROM repuestos WHERE codigo = 'KIT-LIMP'),   1, 12.00, NULL);

-- ---------- Auditoría de ejemplo (Sprint 7) ----------
-- Los datos de este script no pasan por la aplicación, así que no dejan auditoría.
-- Estas filas imitan lo que el sistema registra para que el visor y la actividad reciente
-- tengan contenido. Se marcan con el navegador 'datos_demostracion.sql' y se recrean en cada ejecución.
DELETE FROM auditoria WHERE JSON_VALUE(detalle, '$.user_agent') = 'datos_demostracion.sql';

SET @usr_paul  := (SELECT id FROM usuarios WHERE usuario = 'paul');
SET @usr_diego := (SELECT id FROM usuarios WHERE usuario = 'diego');

-- Alta de la sede
INSERT INTO auditoria (usuario_id, entidad, entidad_id, accion, detalle, fecha)
SELECT @admin, 'sedes', s.id, 'crear',
       JSON_OBJECT('datos', JSON_OBJECT('nombre', s.nombre, 'direccion', s.direccion),
                   'ip', '127.0.0.1', 'user_agent', 'datos_demostracion.sql'),
       NOW() - INTERVAL 7 DAY
  FROM sedes s WHERE s.id = @sede2;

-- Alta de los equipos de la Sede Norte y su movimiento de ingreso
INSERT INTO auditoria (usuario_id, entidad, entidad_id, accion, detalle, fecha)
SELECT @usr_paul, 'equipos', e.id, 'crear',
       JSON_OBJECT('datos', JSON_OBJECT('codigo', e.codigo, 'nombre', e.nombre, 'marca', e.marca, 'modelo', e.modelo,
                                        'numero_serie', e.numero_serie, 'fecha_adquisicion', e.fecha_adquisicion),
                   'ip', '127.0.0.1', 'user_agent', 'datos_demostracion.sql'),
       NOW() - INTERVAL 6 DAY + INTERVAL e.id MINUTE
  FROM equipos e WHERE FIND_IN_SET(e.id, CONCAT_WS(',', @e_sw, @e_ap, @e_pc10, @e_pc11, @e_mon2));

INSERT INTO auditoria (usuario_id, entidad, entidad_id, accion, detalle, fecha)
SELECT @usr_paul, 'movimientos', m.id, 'crear',
       JSON_OBJECT('equipo_id', m.equipo_id, 'origen', NULL, 'destino', m.ubicacion_destino_id, 'motivo', m.motivo,
                   'ip', '127.0.0.1', 'user_agent', 'datos_demostracion.sql'),
       NOW() - INTERVAL 6 DAY + INTERVAL m.equipo_id MINUTE + INTERVAL 1 SECOND
  FROM movimientos m WHERE FIND_IN_SET(m.equipo_id, CONCAT_WS(',', @e_sw, @e_ap, @e_pc10, @e_pc11, @e_mon2));

-- El técnico marcó el computador como inoperativo al revisarlo
INSERT INTO auditoria (usuario_id, entidad, entidad_id, accion, detalle, fecha) VALUES
 (@usr_diego, 'equipos', @e_pc11, 'editar',
  JSON_OBJECT('cambios', JSON_OBJECT('estado_operativo', JSON_ARRAY('operativo', 'inoperativo')),
              'ip', '127.0.0.1', 'user_agent', 'datos_demostracion.sql'),
  NOW() - INTERVAL 6 DAY + INTERVAL 2 HOUR);

-- Mantenimientos programados de la agenda
INSERT INTO auditoria (usuario_id, entidad, entidad_id, accion, detalle, fecha)
SELECT @admin, 'mantenimientos', m.id, 'crear',
       JSON_OBJECT('datos', JSON_OBJECT('tipo', m.tipo, 'fecha_programada', m.fecha_programada,
                                        'tecnico_id', m.tecnico_id, 'problema_reportado', m.problema_reportado),
                   'ip', '127.0.0.1', 'user_agent', 'datos_demostracion.sql'),
       m.creado_en
  FROM mantenimientos m
 WHERE FIND_IN_SET(m.equipo_id, CONCAT_WS(',', @e_sw, @e_ap, @e_pc10, @e_pc11, @e_mon))
   AND m.estado = 'programado';

-- Ajuste de stock de un repuesto
INSERT INTO auditoria (usuario_id, entidad, entidad_id, accion, detalle, fecha)
SELECT @usr_diego, 'repuestos', r.id, 'editar',
       JSON_OBJECT('cambios', JSON_OBJECT('stock', JSON_ARRAY('2.00', '4.00')), 'motivo', 'Compra de reposición',
                   'ip', '127.0.0.1', 'user_agent', 'datos_demostracion.sql'),
       NOW() - INTERVAL 30 HOUR
  FROM repuestos r WHERE r.codigo = 'RAM-DDR4-8';

COMMIT;
