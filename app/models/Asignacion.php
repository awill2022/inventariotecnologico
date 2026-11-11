<?php
/**
 * Tabla `asignaciones`: entrega de equipos a personas y su retiro (fecha_devolucion).
 */
final class Asignacion extends Model
{
    /** Devuelve el equipo si está asignado hoy a la persona (asignación abierta). */
    public function equipoAbiertoDe(int $equipoId, int $personaId): ?array
    {
        return $this->fetchOne(
            "SELECT e.id, e.codigo, e.nombre, e.estado_operativo
               FROM asignaciones a
               JOIN equipos e ON e.id = a.equipo_id
              WHERE a.equipo_id = :equipo
                AND a.persona_id = :persona
                AND a.fecha_devolucion IS NULL
                AND e.estado_inventario <> 'de_baja'
              LIMIT 1",
            [':equipo' => $equipoId, ':persona' => $personaId]
        );
    }
}
