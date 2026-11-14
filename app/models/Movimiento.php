<?php
/**
 * Tabla `movimientos`: cada cambio de ubicación de un equipo.
 * Es la única vía para cambiar equipos.ubicacion_actual_id; se llama dentro de una transacción.
 * Los registran el alta (ingreso), las entregas, las devoluciones y los traslados.
 */
final class Movimiento extends Model
{
    /**
     * Registra el movimiento y deja el equipo en el destino. Devuelve el id del movimiento.
     * $origenId es null en el ingreso al inventario (el equipo aún no tenía ubicación).
     * $fecha es 'AAAA-MM-DD HH:MM:SS'; null = ahora.
     */
    public function registrar(int $equipoId, ?int $origenId, int $destinoId, string $motivo,
                              int $usuarioId, ?int $autorizadoPor = null, ?string $observaciones = null,
                              ?string $fecha = null): int
    {
        $this->execute(
            'INSERT INTO movimientos (equipo_id, ubicacion_origen_id, ubicacion_destino_id, fecha, motivo,
                                      observaciones, autorizado_por, registrado_por)
             VALUES (:equipo, :origen, :destino, COALESCE(:fecha, NOW()), :motivo, :obs, :autoriza, :usuario)',
            [
                ':equipo'   => $equipoId,
                ':origen'   => $origenId,
                ':destino'  => $destinoId,
                ':fecha'    => $fecha,
                ':motivo'   => $motivo,
                ':obs'      => $observaciones,
                ':autoriza' => $autorizadoPor,
                ':usuario'  => $usuarioId,
            ]
        );
        $id = (int) $this->db->lastInsertId();
        (new Equipo())->moverA($equipoId, $destinoId);
        return $id;
    }

    /** Movimientos de un equipo, del más reciente al más antiguo. */
    public function deEquipo(int $equipoId, int $limite = 10): array
    {
        return $this->fetchAll(
            "SELECT m.id, m.fecha, m.motivo, m.observaciones,
                    uo.nombre AS origen, ud.nombre AS destino,
                    CONCAT(pa.nombres, ' ', pa.apellidos) AS autorizado,
                    CONCAT(pr.nombres, ' ', pr.apellidos) AS registrado
               FROM movimientos m
          LEFT JOIN ubicaciones uo ON uo.id = m.ubicacion_origen_id
               JOIN ubicaciones ud ON ud.id = m.ubicacion_destino_id
          LEFT JOIN personas pa    ON pa.id = m.autorizado_por
          LEFT JOIN usuarios ur    ON ur.id = m.registrado_por
          LEFT JOIN personas pr    ON pr.id = ur.persona_id
              WHERE m.equipo_id = :id
           ORDER BY m.fecha DESC, m.id DESC
              LIMIT $limite",
            [':id' => $equipoId]
        );
    }

    /** Un movimiento con su equipo, ubicaciones y personas (para la página de adjuntos). */
    public function porId(int $id): ?array
    {
        return $this->fetchOne(
            "SELECT m.id, m.equipo_id, m.fecha, m.motivo, m.observaciones,
                    e.codigo, e.nombre AS equipo, e.estado_inventario,
                    uo.nombre AS origen, ud.nombre AS destino,
                    CONCAT(pa.nombres, ' ', pa.apellidos) AS autorizado,
                    CONCAT(pr.nombres, ' ', pr.apellidos) AS registrado
               FROM movimientos m
               JOIN equipos e      ON e.id = m.equipo_id
          LEFT JOIN ubicaciones uo ON uo.id = m.ubicacion_origen_id
               JOIN ubicaciones ud ON ud.id = m.ubicacion_destino_id
          LEFT JOIN personas pa    ON pa.id = m.autorizado_por
          LEFT JOIN usuarios ur    ON ur.id = m.registrado_por
          LEFT JOIN personas pr    ON pr.id = ur.persona_id
              WHERE m.id = :id",
            [':id' => $id]
        );
    }

    /** Cuántos movimientos tiene un equipo (para el enlace "Ver todos"). */
    public function contarDe(int $equipoId): int
    {
        return (int) $this->fetchOne('SELECT COUNT(*) AS n FROM movimientos WHERE equipo_id = :id', [':id' => $equipoId])['n'];
    }

    /**
     * Página del historial general con filtros.
     * @param array $f q (código o nombre del equipo), equipo (id), ubicacion (id, como origen o destino),
     *                 desde, hasta ('AAAA-MM-DD' o null)
     * @return array{filas: array, total: int, pagina: int, paginas: int}
     */
    public function listar(array $f, int $pagina = 1, int $porPagina = 20): array
    {
        $where  = [];
        $params = [];

        if ($f['q'] !== '') {
            $where[]     = '(e.codigo LIKE :q1 OR e.codigo_patrimonial LIKE :q2 OR e.nombre LIKE :q3)';
            $like        = '%' . addcslashes($f['q'], '%_\\') . '%';
            $params     += [':q1' => $like, ':q2' => $like, ':q3' => $like];
        }
        if ($f['equipo'] !== null) {
            $where[]           = 'm.equipo_id = :equipo';
            $params[':equipo'] = $f['equipo'];
        }
        if ($f['ubicacion'] !== null) {
            $where[]         = '(m.ubicacion_origen_id = :u1 OR m.ubicacion_destino_id = :u2)';
            $params[':u1']   = $f['ubicacion'];
            $params[':u2']   = $f['ubicacion'];
        }
        if ($f['desde'] !== null) {
            $where[]          = 'm.fecha >= :desde';
            $params[':desde'] = $f['desde'] . ' 00:00:00';
        }
        if ($f['hasta'] !== null) {
            $where[]          = 'm.fecha <= :hasta';
            $params[':hasta'] = $f['hasta'] . ' 23:59:59';
        }

        $desde    = 'FROM movimientos m JOIN equipos e ON e.id = m.equipo_id';
        $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $total   = (int) $this->fetchOne("SELECT COUNT(*) AS n $desde$sqlWhere", $params)['n'];
        $paginas = max(1, (int) ceil($total / $porPagina));
        $pagina  = min(max(1, $pagina), $paginas);
        $offset  = ($pagina - 1) * $porPagina;

        $filas = $this->fetchAll(
            "SELECT m.id, m.fecha, m.motivo, m.observaciones,
                    e.id AS equipo_id, e.codigo, e.nombre AS equipo,
                    uo.nombre AS origen, ud.nombre AS destino,
                    (SELECT COUNT(*) FROM archivos adj WHERE adj.movimiento_id = m.id) AS adjuntos,
                    CONCAT(pa.nombres, ' ', pa.apellidos) AS autorizado,
                    CONCAT(pr.nombres, ' ', pr.apellidos) AS registrado
             $desde
          LEFT JOIN ubicaciones uo ON uo.id = m.ubicacion_origen_id
               JOIN ubicaciones ud ON ud.id = m.ubicacion_destino_id
          LEFT JOIN personas pa    ON pa.id = m.autorizado_por
          LEFT JOIN usuarios ur    ON ur.id = m.registrado_por
          LEFT JOIN personas pr    ON pr.id = ur.persona_id
             $sqlWhere
           ORDER BY m.fecha DESC, m.id DESC
              LIMIT $porPagina OFFSET $offset",
            $params
        );

        return ['filas' => $filas, 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas];
    }
}
