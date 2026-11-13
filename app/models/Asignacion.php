<?php
/**
 * Tabla `asignaciones`: entrega de equipos a personas y su devolución (fecha_devolucion).
 * Una asignación está "abierta" mientras no tiene fecha de devolución; la base admite
 * solo una abierta por equipo (índice único uq_asignacion_abierta).
 */
final class Asignacion extends Model
{
    /** Estado del equipo al devolverlo: clave => [texto, estado operativo que deja (null = no cambia)]. */
    public const DEVOLUCION = [
        'bueno'       => ['Buen estado', null],
        'con_fallas'  => ['Con fallas', 'con_fallas'],
        'no_funciona' => ['No funciona', 'inoperativo'],
    ];

    /** Filtro del listado. */
    public const ESTADOS = ['abiertas' => 'Entregados', 'devueltas' => 'Devueltos', 'todas' => 'Todas'];

    /** Columnas comunes de las consultas con equipo y persona. */
    private const SELECCION = "a.id, a.equipo_id, a.persona_id, a.fecha_entrega, a.fecha_devolucion,
                    a.estado_entrega, a.estado_devolucion, a.observaciones,
                    e.codigo, e.codigo_patrimonial, e.nombre AS equipo, e.marca, e.modelo, e.numero_serie,
                    e.estado_operativo, e.estado_inventario,
                    CONCAT(p.nombres, ' ', p.apellidos) AS persona, p.identificacion, p.cargo,
                    ue.nombre AS ubicacion_entrega,
                    (SELECT COUNT(*) FROM archivos adj WHERE adj.asignacion_id = a.id) AS adjuntos";

    private const DESDE = 'FROM asignaciones a
               JOIN equipos e       ON e.id = a.equipo_id
               JOIN personas p      ON p.id = a.persona_id
          LEFT JOIN ubicaciones ue  ON ue.id = a.ubicacion_entrega_id';

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

    /** Asignación con su equipo, persona, ubicación de entrega y quién la registró (para el acta). */
    public function porId(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT ' . self::SELECCION . ",
                    a.ubicacion_entrega_id, c.nombre AS categoria, p.correo, ar.nombre AS area,
                    s.nombre AS sede_entrega,
                    CONCAT(pr.nombres, ' ', pr.apellidos) AS registrado
             " . self::DESDE . '
               JOIN categorias c    ON c.id = e.categoria_id
          LEFT JOIN areas ar        ON ar.id = p.area_id
          LEFT JOIN sedes s         ON s.id = ue.sede_id
          LEFT JOIN usuarios ur     ON ur.id = a.registrado_por
          LEFT JOIN personas pr     ON pr.id = ur.persona_id
              WHERE a.id = :id',
            [':id' => $id]
        );
    }

    /** Inserta la entrega y devuelve su id. */
    public function crear(int $equipoId, int $personaId, int $ubicacionId, string $fecha,
                          string $estadoEntrega, ?string $observaciones, int $usuarioId): int
    {
        $this->execute(
            'INSERT INTO asignaciones (equipo_id, persona_id, ubicacion_entrega_id, fecha_entrega,
                                       estado_entrega, observaciones, registrado_por)
             VALUES (:equipo, :persona, :ubicacion, :fecha, :estado, :obs, :usuario)',
            [
                ':equipo'    => $equipoId,
                ':persona'   => $personaId,
                ':ubicacion' => $ubicacionId,
                ':fecha'     => $fecha,
                ':estado'    => $estadoEntrega,
                ':obs'       => $observaciones,
                ':usuario'   => $usuarioId,
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    /** Registra la devolución. Solo afecta a una asignación todavía abierta: devuelve false si ya estaba cerrada. */
    public function cerrar(int $id, string $fecha, string $estadoDevolucion): bool
    {
        return $this->execute(
            'UPDATE asignaciones SET fecha_devolucion = :fecha, estado_devolucion = :estado
              WHERE id = :id AND fecha_devolucion IS NULL',
            [':fecha' => $fecha, ':estado' => $estadoDevolucion, ':id' => $id]
        ) === 1;
    }

    /**
     * Página del listado con filtros.
     * @param array $f q (equipo, persona o cédula), estado (clave de ESTADOS)
     * @return array{filas: array, total: int, pagina: int, paginas: int}
     */
    public function listar(array $f, int $pagina = 1, int $porPagina = 15): array
    {
        $where  = [];
        $params = [];

        if ($f['q'] !== '') {
            $likes = [];
            $buscables = ['e.codigo', 'e.codigo_patrimonial', 'e.nombre', 'p.identificacion',
                          "CONCAT(p.nombres, ' ', p.apellidos)", "CONCAT(p.apellidos, ' ', p.nombres)"];
            foreach ($buscables as $i => $columna) {
                $likes[]        = "$columna LIKE :q$i";
                $params[":q$i"] = '%' . addcslashes($f['q'], '%_\\') . '%';
            }
            $where[] = '(' . implode(' OR ', $likes) . ')';
        }
        if ($f['estado'] === 'abiertas') {
            $where[] = 'a.fecha_devolucion IS NULL';
        } elseif ($f['estado'] === 'devueltas') {
            $where[] = 'a.fecha_devolucion IS NOT NULL';
        }

        $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $total    = (int) $this->fetchOne('SELECT COUNT(*) AS n ' . self::DESDE . $sqlWhere, $params)['n'];
        $paginas  = max(1, (int) ceil($total / $porPagina));
        $pagina   = min(max(1, $pagina), $paginas);
        $offset   = ($pagina - 1) * $porPagina;

        $filas = $this->fetchAll(
            'SELECT ' . self::SELECCION . ' ' . self::DESDE . $sqlWhere . "
           ORDER BY COALESCE(a.fecha_devolucion, a.fecha_entrega) DESC, a.id DESC
              LIMIT $porPagina OFFSET $offset",
            $params
        );

        return ['filas' => $filas, 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas];
    }

    /** Entregas de un equipo, de la más reciente a la más antigua. */
    public function deEquipo(int $equipoId): array
    {
        return $this->fetchAll(
            'SELECT ' . self::SELECCION . ' ' . self::DESDE . '
              WHERE a.equipo_id = :id
           ORDER BY a.fecha_entrega DESC, a.id DESC',
            [':id' => $equipoId]
        );
    }

    /** Entregas de una persona: primero las abiertas, luego las devueltas por fecha. */
    public function dePersona(int $personaId): array
    {
        return $this->fetchAll(
            'SELECT ' . self::SELECCION . ', u.nombre AS ubicacion_actual
             ' . self::DESDE . '
          LEFT JOIN ubicaciones u ON u.id = e.ubicacion_actual_id
              WHERE a.persona_id = :id
           ORDER BY a.fecha_devolucion IS NOT NULL, COALESCE(a.fecha_devolucion, a.fecha_entrega) DESC, a.id DESC',
            [':id' => $personaId]
        );
    }

    /** Texto que se guarda en estado_devolucion: "Con fallas: la pantalla parpadea". */
    public static function textoDevolucion(string $clave, ?string $nota): string
    {
        $texto = self::DEVOLUCION[$clave][0];
        return $nota ? "$texto: $nota" : $texto;
    }
}
