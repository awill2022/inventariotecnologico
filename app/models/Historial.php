<?php
/**
 * Historial reciente de los equipos a cargo de una persona:
 * entregas, movimientos y mantenimientos desde que se le asignó cada equipo.
 */
final class Historial extends Model
{
    public function deEquiposDe(int $personaId, int $limite = 10): array
    {
        $sql = "
            SELECT * FROM (
                SELECT 'asignacion' AS clase, NULL AS subtipo, a.fecha_entrega AS fecha,
                       e.codigo, e.nombre AS equipo, a.estado_entrega AS detalle,
                       NULL AS origen, NULL AS destino, NULL AS persona
                  FROM asignaciones a
                  JOIN equipos e ON e.id = a.equipo_id
                 WHERE a.persona_id = :p1 AND a.fecha_devolucion IS NULL

                UNION ALL

                SELECT 'movimiento', NULL, m.fecha,
                       e.codigo, e.nombre, m.motivo,
                       uo.nombre, ud.nombre,
                       TRIM(CONCAT(COALESCE(pa.nombres, ''), ' ', COALESCE(pa.apellidos, '')))
                  FROM movimientos m
                  JOIN equipos e       ON e.id = m.equipo_id
                  JOIN asignaciones a  ON a.equipo_id = m.equipo_id
                                      AND a.persona_id = :p2 AND a.fecha_devolucion IS NULL
             LEFT JOIN ubicaciones uo  ON uo.id = m.ubicacion_origen_id
                  JOIN ubicaciones ud  ON ud.id = m.ubicacion_destino_id
             LEFT JOIN personas pa     ON pa.id = m.autorizado_por
                 WHERE m.fecha >= a.fecha_entrega

                UNION ALL

                SELECT 'mantenimiento', CONCAT(mt.tipo, ':', mt.estado),
                       CASE mt.estado
                            WHEN 'finalizado' THEN COALESCE(mt.fecha_fin, mt.fecha_inicio, mt.creado_en)
                            WHEN 'en_proceso' THEN COALESCE(mt.fecha_inicio, mt.creado_en)
                            ELSE mt.creado_en END,
                       e.codigo, e.nombre,
                       COALESCE(mt.trabajo_realizado, mt.diagnostico, mt.problema_reportado),
                       NULL, NULL, NULL
                  FROM mantenimientos mt
                  JOIN equipos e      ON e.id = mt.equipo_id
                  JOIN asignaciones a ON a.equipo_id = mt.equipo_id
                                     AND a.persona_id = :p3 AND a.fecha_devolucion IS NULL
                 WHERE mt.estado <> 'cancelado'
                   AND mt.creado_en >= a.fecha_entrega
            ) h
            ORDER BY h.fecha DESC
            LIMIT $limite";

        return $this->fetchAll($sql, [':p1' => $personaId, ':p2' => $personaId, ':p3' => $personaId]);
    }
}
