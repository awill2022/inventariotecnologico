<?php
/**
 * Tabla `equipos` (+ `categorias`, `ubicaciones`, `sedes`, `areas`, `especificaciones`).
 */
final class Equipo extends Model
{
    /**
     * Equipos con asignación abierta (sin fecha de devolución) para una persona.
     * Primero los que requieren atención, luego por código.
     */
    public function asignadosA(int $personaId): array
    {
        return $this->fetchAll(
            "SELECT e.id, e.codigo, e.codigo_patrimonial, e.qr_token, e.nombre,
                    e.marca, e.modelo, e.numero_serie, e.origen,
                    e.fecha_adquisicion, e.fecha_fin_garantia,
                    e.estado_operativo, e.estado_inventario,
                    c.nombre  AS categoria,
                    u.nombre  AS ubicacion, u.edificio, u.piso,
                    s.nombre  AS sede,
                    ar.nombre AS area_propietaria,
                    a.fecha_entrega, a.estado_entrega
               FROM asignaciones a
               JOIN equipos e      ON e.id = a.equipo_id
               JOIN categorias c   ON c.id = e.categoria_id
          LEFT JOIN ubicaciones u  ON u.id = e.ubicacion_actual_id
          LEFT JOIN sedes s        ON s.id = u.sede_id
          LEFT JOIN areas ar       ON ar.id = e.area_propietaria_id
              WHERE a.persona_id = :persona
                AND a.fecha_devolucion IS NULL
                AND e.estado_inventario <> 'de_baja'
           ORDER BY e.estado_operativo = 'operativo', e.codigo",
            [':persona' => $personaId]
        );
    }

    /** Especificaciones agrupadas por equipo: [equipo_id => [[atributo, valor], ...]]. */
    public function especificaciones(array $equipoIds): array
    {
        if (!$equipoIds) {
            return [];
        }
        $in   = implode(',', array_fill(0, count($equipoIds), '?'));
        $rows = $this->fetchAll(
            "SELECT equipo_id, atributo, valor FROM especificaciones
              WHERE equipo_id IN ($in) ORDER BY id",
            array_values($equipoIds)
        );

        $porEquipo = [];
        foreach ($rows as $r) {
            $porEquipo[(int) $r['equipo_id']][] = $r;
        }
        return $porEquipo;
    }

    /**
     * Cambia el estado operativo tras un reporte de falla, sin "mejorar" un estado peor:
     * un equipo en mantenimiento sigue en mantenimiento y uno inoperativo no vuelve a "con fallas".
     */
    public function marcarFalla(int $equipoId, string $nuevoEstado): void
    {
        $this->execute(
            "UPDATE equipos
                SET estado_operativo = :nuevo
              WHERE id = :id
                AND estado_operativo <> 'en_mantenimiento'
                AND NOT (estado_operativo = 'inoperativo' AND :nuevo2 = 'con_fallas')",
            [':nuevo' => $nuevoEstado, ':nuevo2' => $nuevoEstado, ':id' => $equipoId]
        );
    }
}
