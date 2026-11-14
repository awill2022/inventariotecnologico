-- =====================================================================
-- 001_equipos_baja.sql
-- Registra la baja de un equipo: cuándo y por qué dejó el inventario.
-- El estado sigue en equipos.estado_inventario = 'de_baja'; si el
-- administrador revierte la baja, estas columnas vuelven a NULL.
--
-- Se aplica sobre db_mysql.sql. Se puede ejecutar varias veces.
-- =====================================================================
USE inventario_tecnologico;

ALTER TABLE equipos
  ADD COLUMN IF NOT EXISTS fecha_baja  DATE         NULL AFTER estado_inventario,
  ADD COLUMN IF NOT EXISTS motivo_baja VARCHAR(255) NULL AFTER fecha_baja;
