<?php
/**
 * Tabla `equipos` (+ `categorias`, `ubicaciones`, `sedes`, `areas`, `especificaciones`).
 */
final class Equipo extends Model
{
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

    public function cambiarEstadoOperativo(int $equipoId, string $estado): void
    {
        $this->execute(
            'UPDATE equipos SET estado_operativo = :estado WHERE id = :id',
            [':estado' => $estado, ':id' => $equipoId]
        );
    }

    public function porQrToken(string $token): ?array
    {
        return $this->fichaWhere('e.qr_token = :valor', [':valor' => $token]);
    }

    public function ficha(int $id): ?array
    {
        return $this->fichaWhere('e.id = :valor', [':valor' => $id]);
    }

    public function buscarParaTecnico(string $q, int $limite = 15): array
    {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        return $this->fetchAll(
            "SELECT e.id, e.codigo, e.codigo_patrimonial, e.nombre, e.marca, e.modelo,
                    e.numero_serie, e.estado_operativo, e.estado_inventario,
                    c.nombre AS categoria,
                    u.nombre AS ubicacion, u.edificio, u.piso,
                    CONCAT(p.nombres, ' ', p.apellidos) AS custodio,
                    p.identificacion AS custodio_cedula,
                    ar.nombre AS custodio_area
               FROM equipos e
               JOIN categorias c   ON c.id = e.categoria_id
          LEFT JOIN ubicaciones u  ON u.id = e.ubicacion_actual_id
          LEFT JOIN asignaciones a ON a.equipo_id = e.id AND a.fecha_devolucion IS NULL
          LEFT JOIN personas p     ON p.id = a.persona_id
          LEFT JOIN areas ar       ON ar.id = p.area_id
              WHERE e.codigo LIKE :q1
                 OR e.codigo_patrimonial LIKE :q2
                 OR e.numero_serie LIKE :q3
                 OR e.nombre LIKE :q4
                 OR CONCAT(p.nombres, ' ', p.apellidos) LIKE :q5
                 OR p.identificacion LIKE :q6
           ORDER BY e.estado_operativo = 'operativo', e.codigo
              LIMIT $limite",
            [
                ':q1' => $like, ':q2' => $like, ':q3' => $like,
                ':q4' => $like, ':q5' => $like, ':q6' => $like,
            ]
        );
    }

    /**
     * Verifica a cualquier persona activa del sistema mediante su cédula (o usuario)
     * y su PIN de 4 dígitos (usa `pin_reporte` o los últimos 4 dígitos de su cédula).
     */
    public function verificarPinReporte(string $identificador, string $pin): ?array
    {
        $identificador = trim($identificador);
        $pin           = trim($pin);
        if ($identificador === '' || !preg_match('/^\d{4}$/', $pin)) {
            return null;
        }

        $fila = $this->fetchOne(
            "SELECT p.id AS persona_id, p.identificacion,
                    CONCAT(p.nombres, ' ', p.apellidos) AS nombre,
                    p.cargo,
                    u.id AS usuario_id, u.usuario,
                    COALESCE(NULLIF(p.pin_reporte, ''), RIGHT(p.identificacion, 4)) AS pin_esperado
               FROM personas p
          LEFT JOIN usuarios u ON u.persona_id = p.id AND u.activo = 1
              WHERE p.activo = 1
                AND (p.identificacion = :id1 OR u.usuario = :id2)
              LIMIT 1",
            [':id1' => $identificador, ':id2' => $identificador]
        );

        if (!$fila || !hash_equals((string) $fila['pin_esperado'], $pin)) {
            return null;
        }
        return $fila;
    }

    private function fichaWhere(string $condicion, array $params): ?array
    {
        return $this->fetchOne(
            "SELECT e.*,
                    c.nombre  AS categoria,
                    u.nombre  AS ubicacion, u.edificio, u.piso, u.tipo AS ubicacion_tipo,
                    s.nombre  AS sede,
                    ap.nombre AS area_propietaria,
                    a.id      AS asignacion_id,
                    a.fecha_entrega, a.estado_entrega, a.observaciones AS asignacion_obs,
                    p.id      AS custodio_id,
                    CONCAT(p.nombres, ' ', p.apellidos) AS custodio_nombre,
                    p.identificacion AS custodio_cedula,
                    p.cargo   AS custodio_cargo,
                    p.correo  AS custodio_correo,
                    p.telefono AS custodio_telefono,
                    ac.nombre AS custodio_area
               FROM equipos e
               JOIN categorias c   ON c.id = e.categoria_id
          LEFT JOIN ubicaciones u  ON u.id = e.ubicacion_actual_id
          LEFT JOIN sedes s        ON s.id = u.sede_id
          LEFT JOIN areas ap       ON ap.id = e.area_propietaria_id
          LEFT JOIN asignaciones a ON a.equipo_id = e.id AND a.fecha_devolucion IS NULL
          LEFT JOIN personas p     ON p.id = a.persona_id
          LEFT JOIN areas ac       ON ac.id = p.area_id
              WHERE $condicion
              LIMIT 1",
            $params
        );
    }
}