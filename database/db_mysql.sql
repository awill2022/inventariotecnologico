CREATE DATABASE IF NOT EXISTS inventario_tecnologico CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE inventario_tecnologico;

CREATE TABLE sedes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(150) NOT NULL, direccion VARCHAR(255), activo BOOLEAN NOT NULL DEFAULT TRUE,
 UNIQUE KEY uq_sede_nombre(nombre)
) ENGINE=InnoDB;

CREATE TABLE areas (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(150) NOT NULL, tipo ENUM('departamento','carrera','unidad','otro') NOT NULL DEFAULT 'departamento',
 area_padre_id BIGINT UNSIGNED NULL, activo BOOLEAN NOT NULL DEFAULT TRUE,
 CONSTRAINT fk_area_padre FOREIGN KEY(area_padre_id) REFERENCES areas(id)
) ENGINE=InnoDB;

CREATE TABLE ubicaciones (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 sede_id BIGINT UNSIGNED NOT NULL, area_id BIGINT UNSIGNED NULL,
 nombre VARCHAR(150) NOT NULL, tipo ENUM('oficina','laboratorio','aula','bodega','sala','otro') NOT NULL DEFAULT 'otro',
 edificio VARCHAR(100), piso VARCHAR(30), descripcion VARCHAR(255), activo BOOLEAN NOT NULL DEFAULT TRUE,
 CONSTRAINT fk_ubic_sede FOREIGN KEY(sede_id) REFERENCES sedes(id),
 CONSTRAINT fk_ubic_area FOREIGN KEY(area_id) REFERENCES areas(id),
 INDEX idx_ubic_sede(sede_id)
) ENGINE=InnoDB;

CREATE TABLE personas (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 identificacion VARCHAR(30) UNIQUE, nombres VARCHAR(150) NOT NULL, apellidos VARCHAR(150) NOT NULL,
 cargo VARCHAR(120), area_id BIGINT UNSIGNED NULL, correo VARCHAR(190), telefono VARCHAR(30), activo BOOLEAN NOT NULL DEFAULT TRUE,
 CONSTRAINT fk_persona_area FOREIGN KEY(area_id) REFERENCES areas(id)
) ENGINE=InnoDB;

CREATE TABLE usuarios (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, persona_id BIGINT UNSIGNED NULL,
 usuario VARCHAR(80) NOT NULL UNIQUE, clave_hash VARCHAR(255) NOT NULL,
 rol ENUM('administrador','inventario','tecnico','consulta') NOT NULL DEFAULT 'consulta',
 activo BOOLEAN NOT NULL DEFAULT TRUE, ultimo_acceso DATETIME NULL,
 CONSTRAINT fk_usuario_persona FOREIGN KEY(persona_id) REFERENCES personas(id)
) ENGINE=InnoDB;

CREATE TABLE categorias (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(100) NOT NULL UNIQUE, descripcion VARCHAR(255), activo BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB;

CREATE TABLE equipos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 codigo VARCHAR(60) NOT NULL UNIQUE, codigo_patrimonial VARCHAR(80) UNIQUE,
 qr_token CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE COMMENT 'Token aleatorio permanente para la URL del QR',
 categoria_id BIGINT UNSIGNED NOT NULL, area_propietaria_id BIGINT UNSIGNED NULL, ubicacion_actual_id BIGINT UNSIGNED NULL,
 nombre VARCHAR(150) NOT NULL, marca VARCHAR(100), modelo VARCHAR(120), numero_serie VARCHAR(120) UNIQUE,
 fecha_adquisicion DATE NULL, valor_adquisicion DECIMAL(12,2) NULL, proveedor VARCHAR(190) NULL,
 origen ENUM('compra','donacion','traspaso','otro','no_especificado') NOT NULL DEFAULT 'no_especificado',
 fecha_fin_garantia DATE NULL,
 estado_operativo ENUM('operativo','con_fallas','en_mantenimiento','inoperativo') NOT NULL DEFAULT 'operativo',
 estado_inventario ENUM('activo','en_bodega','prestado','de_baja') NOT NULL DEFAULT 'activo',
 observaciones TEXT, creado_por BIGINT UNSIGNED NULL,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_equipo_categoria FOREIGN KEY(categoria_id) REFERENCES categorias(id),
 CONSTRAINT fk_equipo_area FOREIGN KEY(area_propietaria_id) REFERENCES areas(id),
 CONSTRAINT fk_equipo_ubic FOREIGN KEY(ubicacion_actual_id) REFERENCES ubicaciones(id),
 CONSTRAINT fk_equipo_creado FOREIGN KEY(creado_por) REFERENCES usuarios(id),
 INDEX idx_equipo_estado(estado_inventario,estado_operativo)
) ENGINE=InnoDB;

-- Caracteristicas flexibles por clase de equipo, ej. procesador, RAM, IP, sistema operativo.
CREATE TABLE especificaciones (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 equipo_id BIGINT UNSIGNED NOT NULL, atributo VARCHAR(100) NOT NULL, valor VARCHAR(500) NOT NULL,
 CONSTRAINT fk_esp_equipo FOREIGN KEY(equipo_id) REFERENCES equipos(id),
 UNIQUE KEY uq_esp_atributo(equipo_id,atributo)
) ENGINE=InnoDB;

-- Registro del historial de componentes; puede haber varios del mismo tipo.
CREATE TABLE componentes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, equipo_id BIGINT UNSIGNED NOT NULL,
 tipo VARCHAR(100) NOT NULL, marca VARCHAR(100), modelo VARCHAR(120), numero_serie VARCHAR(120),
 capacidad VARCHAR(100), fecha_instalacion DATE NULL, fecha_retiro DATE NULL,
 mantenimiento_instalacion_id BIGINT UNSIGNED NULL, mantenimiento_retiro_id BIGINT UNSIGNED NULL,
 observaciones TEXT,
 CONSTRAINT fk_com_equipo FOREIGN KEY(equipo_id) REFERENCES equipos(id),
 INDEX idx_com_equipo_tipo(equipo_id,tipo)
) ENGINE=InnoDB;

-- Una sola asignacion activa por equipo, admitiendo historicos ilimitados.
CREATE TABLE asignaciones (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 equipo_id BIGINT UNSIGNED NOT NULL, persona_id BIGINT UNSIGNED NOT NULL,
 ubicacion_entrega_id BIGINT UNSIGNED NULL,
 fecha_entrega DATETIME NOT NULL, fecha_devolucion DATETIME NULL,
 estado_entrega TEXT, estado_devolucion TEXT, observaciones TEXT,
 registrado_por BIGINT UNSIGNED NULL,
 equipo_activo_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN fecha_devolucion IS NULL THEN equipo_id ELSE NULL END) STORED,
 CONSTRAINT fk_asig_equipo FOREIGN KEY(equipo_id) REFERENCES equipos(id),
 CONSTRAINT fk_asig_persona FOREIGN KEY(persona_id) REFERENCES personas(id),
 CONSTRAINT fk_asig_ubic FOREIGN KEY(ubicacion_entrega_id) REFERENCES ubicaciones(id),
 CONSTRAINT fk_asig_usuario FOREIGN KEY(registrado_por) REFERENCES usuarios(id),
 UNIQUE KEY uq_asignacion_abierta(equipo_activo_id),
 INDEX idx_asig_equipo_fecha(equipo_id,fecha_entrega),
 CONSTRAINT ck_asig_fechas CHECK (fecha_devolucion IS NULL OR fecha_devolucion >= fecha_entrega)
) ENGINE=InnoDB;

CREATE TABLE movimientos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 equipo_id BIGINT UNSIGNED NOT NULL, ubicacion_origen_id BIGINT UNSIGNED NULL,
 ubicacion_destino_id BIGINT UNSIGNED NOT NULL, fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 motivo VARCHAR(255), observaciones TEXT, autorizado_por BIGINT UNSIGNED NULL,
 registrado_por BIGINT UNSIGNED NULL,
 CONSTRAINT fk_mov_equipo FOREIGN KEY(equipo_id) REFERENCES equipos(id),
 CONSTRAINT fk_mov_origen FOREIGN KEY(ubicacion_origen_id) REFERENCES ubicaciones(id),
 CONSTRAINT fk_mov_destino FOREIGN KEY(ubicacion_destino_id) REFERENCES ubicaciones(id),
 CONSTRAINT fk_mov_autoriza FOREIGN KEY(autorizado_por) REFERENCES personas(id),
 CONSTRAINT fk_mov_usuario FOREIGN KEY(registrado_por) REFERENCES usuarios(id),
 INDEX idx_mov_equipo_fecha(equipo_id,fecha)
) ENGINE=InnoDB;

CREATE TABLE mantenimientos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 equipo_id BIGINT UNSIGNED NOT NULL,
 tecnico_id BIGINT UNSIGNED NULL,
 tipo ENUM('preventivo','correctivo','diagnostico','actualizacion') NOT NULL,
 fecha_programada DATE NULL, fecha_inicio DATETIME NULL, fecha_fin DATETIME NULL,
 problema_reportado TEXT, diagnostico TEXT, trabajo_realizado TEXT,
 resultado TEXT, recomendaciones TEXT,
 costo_mano_obra DECIMAL(12,2) NOT NULL DEFAULT 0,
 proximo_mantenimiento DATE NULL,
 estado ENUM('programado','en_proceso','finalizado','cancelado') NOT NULL DEFAULT 'programado',
 registrado_por BIGINT UNSIGNED NULL, creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_mant_equipo FOREIGN KEY(equipo_id) REFERENCES equipos(id),
 CONSTRAINT fk_mant_tecnico FOREIGN KEY(tecnico_id) REFERENCES personas(id),
 CONSTRAINT fk_mant_usuario FOREIGN KEY(registrado_por) REFERENCES usuarios(id),
 INDEX idx_mant_equipo_fecha(equipo_id,fecha_inicio),
 INDEX idx_mant_programacion(estado,fecha_programada),
 CONSTRAINT ck_mant_fechas CHECK (fecha_fin IS NULL OR fecha_inicio IS NULL OR fecha_fin >= fecha_inicio),
 CONSTRAINT ck_mant_costo CHECK (costo_mano_obra >= 0)
) ENGINE=InnoDB;

ALTER TABLE componentes
 ADD CONSTRAINT fk_com_mant_inst FOREIGN KEY(mantenimiento_instalacion_id) REFERENCES mantenimientos(id),
 ADD CONSTRAINT fk_com_mant_ret FOREIGN KEY(mantenimiento_retiro_id) REFERENCES mantenimientos(id);

CREATE TABLE repuestos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 codigo VARCHAR(80) UNIQUE, nombre VARCHAR(150) NOT NULL, descripcion TEXT,
 unidad VARCHAR(30) NOT NULL DEFAULT 'unidad', activo BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB;

CREATE TABLE mantenimiento_repuestos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 mantenimiento_id BIGINT UNSIGNED NOT NULL, repuesto_id BIGINT UNSIGNED NOT NULL,
 cantidad DECIMAL(10,2) NOT NULL DEFAULT 1, costo_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
 detalle VARCHAR(255),
 CONSTRAINT fk_mr_mantenimiento FOREIGN KEY(mantenimiento_id) REFERENCES mantenimientos(id),
 CONSTRAINT fk_mr_repuesto FOREIGN KEY(repuesto_id) REFERENCES repuestos(id),
 CONSTRAINT ck_mr_cant CHECK(cantidad > 0),
 CONSTRAINT ck_mr_costo CHECK(costo_unitario >= 0)
) ENGINE=InnoDB;

CREATE TABLE archivos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 equipo_id BIGINT UNSIGNED NOT NULL, mantenimiento_id BIGINT UNSIGNED NULL, asignacion_id BIGINT UNSIGNED NULL,
 movimiento_id BIGINT UNSIGNED NULL,
 clase ENUM('fotografia','documento') NOT NULL,
 tipo VARCHAR(50) NOT NULL DEFAULT 'general',
 nombre_original VARCHAR(255) NOT NULL, ruta_archivo VARCHAR(500) NOT NULL,
 mime_type VARCHAR(100) NOT NULL, tamano_bytes BIGINT UNSIGNED NOT NULL,
 descripcion VARCHAR(255), subido_por BIGINT UNSIGNED NULL,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_arch_equipo FOREIGN KEY(equipo_id) REFERENCES equipos(id),
 CONSTRAINT fk_arch_mant FOREIGN KEY(mantenimiento_id) REFERENCES mantenimientos(id),
 CONSTRAINT fk_arch_asig FOREIGN KEY(asignacion_id) REFERENCES asignaciones(id),
 CONSTRAINT fk_arch_mov FOREIGN KEY(movimiento_id) REFERENCES movimientos(id),
 CONSTRAINT fk_arch_usuario FOREIGN KEY(subido_por) REFERENCES usuarios(id),
 INDEX idx_arch_equipo(equipo_id,clase)
) ENGINE=InnoDB;

CREATE TABLE auditoria (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 usuario_id BIGINT UNSIGNED NULL, entidad VARCHAR(70) NOT NULL, entidad_id BIGINT UNSIGNED NOT NULL,
 accion ENUM('crear','editar','cerrar','anular','acceder') NOT NULL,
 detalle JSON NULL, fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_aud_usuario FOREIGN KEY(usuario_id) REFERENCES usuarios(id),
 INDEX idx_aud_entidad(entidad,entidad_id,fecha)
) ENGINE=InnoDB;
-- Ingreso de persona --
insert into personas (identificacion, nombres, apellidos, cargo, area_id, correo, telefono, activo	)
VALUES ('000002', 'Daniel', 'Caiza', 'docente', NULL, 'morocho.24caiza@gmail.com', '0999428684', TRUE);
-- Ingreso de usuario (la clave en texto plano se convierte en hash en el primer inicio de sesión) --
insert into usuarios (persona_id, usuario, clave_hash, rol, activo)
VALUES (LAST_INSERT_ID(), 'daniel', '1234', 'consulta', TRUE);