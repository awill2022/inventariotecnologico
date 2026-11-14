<?php
/**
 * Tabla `componentes`: historial de partes internas del equipo (RAM, discos, baterías, etc.).
 */
final class Componente extends Model
{
    /** Todos los componentes del equipo (primero los instalados vigentes, luego los retirados). */
    public function deEquipo(int $equipoId): array
    {
        return $this->fetchAll(
            'SELECT c.*,
                    mi.fecha_fin AS fecha_mant_instalacion,
                    mr.fecha_fin AS fecha_mant_retiro
               FROM componentes c
          LEFT JOIN mantenimientos mi ON mi.id = c.mantenimiento_instalacion_id
          LEFT JOIN mantenimientos mr ON mr.id = c.mantenimiento_retiro_id
              WHERE c.equipo_id = :equipo
           ORDER BY c.fecha_retiro IS NOT NULL, c.id DESC',
            [':equipo' => $equipoId]
        );
    }

    /** Solo los componentes actualmente instalados (sin fecha de retiro). */
    public function activosDeEquipo(int $equipoId): array
    {
        return $this->fetchAll(
            'SELECT * FROM componentes WHERE equipo_id = :equipo AND fecha_retiro IS NULL ORDER BY id',
            [':equipo' => $equipoId]
        );
    }

    /** Marca como retirados los componentes indicados dentro de un mantenimiento. */
    public function retirar(int $equipoId, array $ids, int $mantenimientoId): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids), fn ($v) => $v > 0));
        if (!$ids) {
            return 0;
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        return $this->execute(
            "UPDATE componentes
                SET fecha_retiro = CURDATE(),
                    mantenimiento_retiro_id = ?
              WHERE equipo_id = ?
                AND fecha_retiro IS NULL
                AND id IN ($in)",
            [$mantenimientoId, $equipoId, ...$ids]
        );
    }

    /** Instala un componente nuevo asociándolo al mantenimiento actual. */
    public function instalar(int $equipoId, int $mantenimientoId, array $datos): int
    {
        $this->execute(
            'INSERT INTO componentes
                (equipo_id, tipo, marca, modelo, numero_serie, capacidad,
                 fecha_instalacion, mantenimiento_instalacion_id, observaciones)
             VALUES
                (:equipo, :tipo, :marca, :modelo, :serie, :capacidad,
                 CURDATE(), :mant, :obs)',
            [
                ':equipo'    => $equipoId,
                ':tipo'      => $datos['tipo'],
                ':marca'     => $datos['marca'] ?: null,
                ':modelo'    => $datos['modelo'] ?: null,
                ':serie'     => $datos['numero_serie'] ?: null,
                ':capacidad' => $datos['capacidad'] ?: null,
                ':mant'      => $mantenimientoId,
                ':obs'       => $datos['observaciones'] ?: null,
            ]
        );
        return (int) $this->db->lastInsertId();
    }
}