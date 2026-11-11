-- =====================================================================
-- 002_usuarios_cambio_clave.sql
-- Marca las cuentas que deben cambiar su clave al entrar: el administrador
-- crea la cuenta o restablece la clave con una clave temporal y el usuario
-- queda obligado a cambiarla en "Mi perfil" antes de usar el sistema.
--
-- Se aplica sobre db_mysql.sql. Se puede ejecutar varias veces.
-- =====================================================================
USE inventario_tecnologico;

ALTER TABLE usuarios
  ADD COLUMN IF NOT EXISTS debe_cambiar_clave BOOLEAN NOT NULL DEFAULT FALSE AFTER activo;
