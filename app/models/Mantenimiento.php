<?php
/**
 * Tabla `mantenimientos`.
 */
final class Mantenimiento extends Model
{
    /**
     * Mantenimientos (no cancelados) de varios equipos, del más reciente al más antiguo,
     * agrupados por equipo: [equipo_id => [fila, ...]].
     */
    public function deEquipos(array $equipoIds): array
    {
        if (!$equipoIds) {
            return [];
        }
        $in   = implode(',', array_fill(0, count($equipoIds), '?'));
        $rows = $this->fetchAll(
            "SELECT m.id, m.equipo_id, m.tipo, m.estado, m.fecha_programada,
                    m.fecha_inicio, m.fecha_fin, m.problema_reportado, m.diagnostico,
                    m.trabajo_realizado, m.proximo_mantenimiento, m.creado_en,
                    TRIM(CONCAT(COALESCE(t.nombres, ''), ' ', COALESCE(t.apellidos, ''))) AS tecnico
               FROM mantenimientos m
          LEFT JOIN personas t ON t.id = m.tecnico_id
              WHERE m.equipo_id IN ($in) AND m.estado <> 'cancelado'
           ORDER BY COALESCE(m.fecha_fin, m.fecha_inicio, m.creado_en) DESC, m.id DESC",
            array_values($equipoIds)
        );

        $porEquipo = [];
        foreach ($rows as $r) {
            $porEquipo[(int) $r['equipo_id']][] = $r;
        }
        return $porEquipo;
    }

    /** Crea la solicitud de diagnóstico de un reporte de falla y devuelve su id. */
    public function reportarFalla(int $equipoId, string $problema, int $usuarioId): int
    {
        $this->execute(
            "INSERT INTO mantenimientos (equipo_id, tipo, estado, problema_reportado, registrado_por)
             VALUES (:equipo, 'diagnostico', 'programado', :problema, :usuario)",
            [':equipo' => $equipoId, ':problema' => $problema, ':usuario' => $usuarioId]
        );
        return (int) $this->db->lastInsertId();
    }
}
