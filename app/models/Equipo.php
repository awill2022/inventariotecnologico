<?php
/**
 * Tabla `equipos` (+ `categorias`, `ubicaciones`, `sedes`, `areas`, `especificaciones`).
 *
 * Reglas:
 * - No se borra: la baja pone estado_inventario = 'de_baja' con fecha y motivo (migración 001).
 * - La ubicación solo cambia con un registro en `movimientos` (ver Movimiento).
 * - estado_operativo lo mueven el reporte de falla y el mantenimiento, no el formulario de edición.
 */
final class Equipo extends Model
{
    public const OPERATIVOS = [
        'operativo'        => 'Operativo',
        'con_fallas'       => 'Con fallas',
        'en_mantenimiento' => 'En mantenimiento',
        'inoperativo'      => 'Inoperativo',
    ];

    /** Estados operativos con los que un equipo se puede entregar (decisión del Sprint 4). */
    public const ENTREGABLES = ['operativo', 'con_fallas'];

    /** Filtro de inventario del listado. 'activo' es un equipo entregado o en uso. */
    public const INVENTARIO = [
        'vigentes'  => 'Vigentes',
        'activo'    => 'En uso',
        'en_bodega' => 'En bodega',
        'de_baja'   => 'De baja',
        'todos'     => 'Todos',
    ];

    /** Columnas que se escriben desde el formulario de alta y edición. */
    private const COLUMNAS = [
        'codigo', 'codigo_patrimonial', 'categoria_id', 'area_propietaria_id', 'nombre', 'marca', 'modelo',
        'numero_serie', 'fecha_adquisicion', 'valor_adquisicion', 'proveedor', 'origen', 'fecha_fin_garantia',
        'observaciones',
    ];

    /** Columnas únicas que el formulario revisa antes de guardar. */
    private const UNICAS = ['codigo', 'codigo_patrimonial', 'numero_serie'];

    /**
     * Página del listado con filtros.
     * @param array $f q, categoria, ubicacion, area (ids o null), operativo, inventario (claves de INVENTARIO)
     * @return array{filas: array, total: int, pagina: int, paginas: int}
     */
    public function listar(array $f, int $pagina = 1, int $porPagina = 15): array
    {
        $where  = [];
        $params = [];

        if ($f['q'] !== '') {
            $likes = [];
            $buscables = ['e.codigo', 'e.codigo_patrimonial', 'e.numero_serie', 'e.nombre',
                          "CONCAT_WS(' ', e.marca, e.modelo)"];
            foreach ($buscables as $i => $columna) {
                $likes[]        = "$columna LIKE :q$i";
                $params[":q$i"] = '%' . addcslashes($f['q'], '%_\\') . '%';
            }
            $where[] = '(' . implode(' OR ', $likes) . ')';
        }
        foreach (['categoria' => 'e.categoria_id', 'ubicacion' => 'e.ubicacion_actual_id', 'area' => 'e.area_propietaria_id'] as $clave => $columna) {
            if ($f[$clave] !== null) {
                $where[]          = "$columna = :$clave";
                $params[":$clave"] = $f[$clave];
            }
        }
        if ($f['operativo'] !== '') {
            $where[]             = 'e.estado_operativo = :operativo';
            $params[':operativo'] = $f['operativo'];
        }
        if ($f['inventario'] === 'vigentes') {
            $where[] = "e.estado_inventario <> 'de_baja'";
        } elseif ($f['inventario'] !== 'todos') {
            $where[]              = 'e.estado_inventario = :inventario';
            $params[':inventario'] = $f['inventario'];
        }

        $desde = "FROM equipos e
                  JOIN categorias c   ON c.id = e.categoria_id
             LEFT JOIN ubicaciones u  ON u.id = e.ubicacion_actual_id
             LEFT JOIN sedes s        ON s.id = u.sede_id
             LEFT JOIN areas ar       ON ar.id = e.area_propietaria_id
             LEFT JOIN asignaciones a ON a.equipo_activo_id = e.id
             LEFT JOIN personas p     ON p.id = a.persona_id";
        $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $total   = (int) $this->fetchOne("SELECT COUNT(*) AS n $desde$sqlWhere", $params)['n'];
        $paginas = max(1, (int) ceil($total / $porPagina));
        $pagina  = min(max(1, $pagina), $paginas);
        $offset  = ($pagina - 1) * $porPagina;

        $filas = $this->fetchAll(
            "SELECT e.id, e.codigo, e.codigo_patrimonial, e.nombre, e.marca, e.modelo, e.numero_serie,
                    e.estado_operativo, e.estado_inventario,
                    c.nombre AS categoria, u.nombre AS ubicacion, s.nombre AS sede, ar.nombre AS area,
                    CONCAT(p.nombres, ' ', p.apellidos) AS responsable
             $desde$sqlWhere
             ORDER BY e.codigo LIMIT $porPagina OFFSET $offset",
            $params
        );

        return ['filas' => $filas, 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas];
    }

    /** Equipo con los nombres de su categoría, ubicación, área, responsable actual y quién lo registró. */
    public function porId(int $id): ?array
    {
        return $this->fetchOne(
            "SELECT e.*,
                    c.nombre AS categoria, c.prefijo,
                    u.nombre AS ubicacion, u.edificio, u.piso, s.nombre AS sede,
                    ar.nombre AS area_propietaria,
                    a.id AS asignacion_id, a.fecha_entrega, a.persona_id AS responsable_id,
                    CONCAT(p.nombres, ' ', p.apellidos) AS responsable,
                    CONCAT(pc.nombres, ' ', pc.apellidos) AS creado_por_nombre
               FROM equipos e
               JOIN categorias c    ON c.id = e.categoria_id
          LEFT JOIN ubicaciones u   ON u.id = e.ubicacion_actual_id
          LEFT JOIN sedes s         ON s.id = u.sede_id
          LEFT JOIN areas ar        ON ar.id = e.area_propietaria_id
          LEFT JOIN asignaciones a  ON a.equipo_activo_id = e.id
          LEFT JOIN personas p      ON p.id = a.persona_id
          LEFT JOIN usuarios uc     ON uc.id = e.creado_por
          LEFT JOIN personas pc     ON pc.id = uc.persona_id
              WHERE e.id = :id",
            [':id' => $id]
        );
    }

    /** Equipo por el token de su QR (página pública /q/{token}); mismos datos que porId(). */
    public function porToken(string $token): ?array
    {
        $fila = $this->fetchOne('SELECT id FROM equipos WHERE qr_token = :token', [':token' => $token]);
        return $fila ? $this->porId((int) $fila['id']) : null;
    }

    /** Equipos para imprimir sus etiquetas, en el orden del código. */
    public function paraEtiquetas(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        return $this->fetchAll(
            "SELECT e.id, e.codigo, e.codigo_patrimonial, e.qr_token, e.nombre, e.marca, e.modelo, e.numero_serie,
                    c.nombre AS categoria
               FROM equipos e
               JOIN categorias c ON c.id = e.categoria_id
              WHERE e.id IN ($in)
           ORDER BY e.codigo",
            $ids
        );
    }

    /**
     * Código propuesto para un equipo nuevo: EQ-{PREFIJO}-{siguiente número con 4 cifras}.
     * Sin prefijo, EQ-{número}. Solo cuenta los códigos que siguen ese formato.
     */
    public function siguienteCodigo(?string $prefijo): string
    {
        $base = $prefijo ? "EQ-$prefijo-" : 'EQ-';
        $fila = $this->fetchOne(
            "SELECT MAX(CAST(SUBSTRING(codigo, :desde) AS UNSIGNED)) AS n
               FROM equipos
              WHERE codigo LIKE :base
                AND SUBSTRING(codigo, :desde2) REGEXP '^[0-9]+$'",
            [':desde' => strlen($base) + 1, ':desde2' => strlen($base) + 1, ':base' => addcslashes($base, '%_\\') . '%']
        );
        return $base . str_pad((string) ((int) ($fila['n'] ?? 0) + 1), 4, '0', STR_PAD_LEFT);
    }

    /** true si otro equipo ya usa ese valor en una columna única (codigo, codigo_patrimonial, numero_serie). */
    public function existe(string $columna, ?string $valor, ?int $exceptoId = null): bool
    {
        if ($valor === null || !in_array($columna, self::UNICAS, true)) {
            return false;
        }
        return $this->fetchOne(
            "SELECT 1 FROM equipos WHERE $columna = :valor AND id <> :excepto LIMIT 1",
            [':valor' => $valor, ':excepto' => $exceptoId ?? 0]
        ) !== null;
    }

    /**
     * Inserta el equipo (queda en bodega) y devuelve su id.
     * La ubicación inicial la pone Movimiento::registrar() en la misma transacción.
     */
    public function crear(array $datos, string $estadoOperativo, int $usuarioId): int
    {
        $datos = array_intersect_key($datos, array_flip(self::COLUMNAS)) + [
            'qr_token'          => bin2hex(random_bytes(16)),
            'estado_operativo'  => $estadoOperativo,
            'estado_inventario' => 'en_bodega',
            'creado_por'        => $usuarioId,
        ];
        $columnas = array_keys($datos);
        $marcas   = array_map(fn ($c) => ":$c", $columnas);

        $this->execute(
            'INSERT INTO equipos (' . implode(', ', $columnas) . ') VALUES (' . implode(', ', $marcas) . ')',
            array_combine($marcas, array_values($datos))
        );
        return (int) $this->db->lastInsertId();
    }

    public function actualizar(int $id, array $datos): void
    {
        $datos  = array_intersect_key($datos, array_flip(self::COLUMNAS));
        $sets   = [];
        $params = [':id' => $id];
        foreach ($datos as $columna => $valor) {
            $sets[]              = "$columna = :$columna";
            $params[":$columna"] = $valor;
        }
        $this->execute('UPDATE equipos SET ' . implode(', ', $sets) . ' WHERE id = :id', $params);
    }

    /** Cambia la ubicación actual. Solo la llama Movimiento::registrar(). */
    public function moverA(int $id, int $ubicacionId): void
    {
        $this->execute('UPDATE equipos SET ubicacion_actual_id = :u WHERE id = :id', [':u' => $ubicacionId, ':id' => $id]);
    }

    /**
     * Relee y bloquea la fila del equipo hasta el fin de la transacción (SELECT ... FOR UPDATE),
     * para que dos entregas o traslados simultáneos no se pisen. Incluye su entrega abierta.
     */
    public function bloquear(int $id): ?array
    {
        return $this->fetchOne(
            "SELECT e.id, e.codigo, e.nombre, e.estado_operativo, e.estado_inventario, e.ubicacion_actual_id,
                    (SELECT a.id FROM asignaciones a WHERE a.equipo_activo_id = e.id) AS asignacion_id
               FROM equipos e
              WHERE e.id = :id
                FOR UPDATE",
            [':id' => $id]
        );
    }

    /** Cambia estado_inventario (en_bodega / activo) tras una entrega, devolución o traslado. */
    public function fijarInventario(int $id, string $estado): void
    {
        $this->execute('UPDATE equipos SET estado_inventario = :estado WHERE id = :id', [':estado' => $estado, ':id' => $id]);
    }

    /** Fija estado_operativo al iniciar, finalizar o cancelar un mantenimiento. */
    public function fijarOperativo(int $id, string $estado): void
    {
        $this->execute('UPDATE equipos SET estado_operativo = :estado WHERE id = :id', [':estado' => $estado, ':id' => $id]);
    }

    /**
     * Fecha del último evento del equipo (movimiento, entrega o devolución).
     * Un evento nuevo no puede ser anterior: así el historial y la ubicación de origen siguen en orden.
     */
    public function ultimaFecha(int $id): ?string
    {
        // GREATEST con un NULL da NULL: cada parte vacía vale una fecha mínima y al final se vuelve NULL
        $fila = $this->fetchOne(
            "SELECT NULLIF(GREATEST(
                        COALESCE((SELECT MAX(fecha) FROM movimientos WHERE equipo_id = :e1), '1000-01-01 00:00:00'),
                        COALESCE((SELECT MAX(fecha_entrega) FROM asignaciones WHERE equipo_id = :e2), '1000-01-01 00:00:00'),
                        COALESCE((SELECT MAX(fecha_devolucion) FROM asignaciones WHERE equipo_id = :e3), '1000-01-01 00:00:00')
                    ), '1000-01-01 00:00:00') AS f",
            [':e1' => $id, ':e2' => $id, ':e3' => $id]
        );
        return $fila['f'] ?? null;
    }

    /**
     * Opciones [id => "Código · Nombre — Ubicación"] para los formularios de entrega y traslado.
     * $soloEntregables: sin entrega abierta y con estado operativo de ENTREGABLES.
     * $incluirId agrega ese equipo aunque no cumpla (para mostrar el valor elegido).
     */
    public function opciones(bool $soloEntregables, ?int $incluirId = null): array
    {
        $condicion = "e.estado_inventario <> 'de_baja'";
        if ($soloEntregables) {
            $condicion .= " AND a.id IS NULL AND e.estado_operativo IN ('" . implode("','", self::ENTREGABLES) . "')";
        }
        $filas = $this->fetchAll(
            "SELECT e.id, CONCAT(e.codigo, ' · ', e.nombre, COALESCE(CONCAT(' — ', u.nombre), '')) AS texto
               FROM equipos e
          LEFT JOIN ubicaciones u  ON u.id = e.ubicacion_actual_id
          LEFT JOIN asignaciones a ON a.equipo_activo_id = e.id
              WHERE ($condicion) OR e.id <=> :incluir
           ORDER BY e.codigo",
            [':incluir' => $incluirId]
        );
        return array_column($filas, 'texto', 'id');
    }

    /** Equipos que no están dados de baja. */
    public function contarVigentes(): int
    {
        return (int) $this->fetchOne("SELECT COUNT(*) AS n FROM equipos WHERE estado_inventario <> 'de_baja'")['n'];
    }

    /**
     * Conteos para el dashboard del inicio.
     * - operativo:  equipos vigentes por estado_operativo [estado => n]
     * - inventario: todos los equipos por estado_inventario [estado => n]
     * - categoria:  equipos vigentes por categoría [[id, nombre, n], ...] (de mayor a menor)
     * - sede:       equipos vigentes por sede de su ubicación actual (id null = sin ubicación)
     */
    public function conteosDashboard(): array
    {
        $vigente = "e.estado_inventario <> 'de_baja'";

        $operativo = $this->fetchAll(
            "SELECT e.estado_operativo AS clave, COUNT(*) AS n FROM equipos e WHERE $vigente GROUP BY e.estado_operativo"
        );
        $inventario = $this->fetchAll(
            'SELECT e.estado_inventario AS clave, COUNT(*) AS n FROM equipos e GROUP BY e.estado_inventario'
        );
        $categoria = $this->fetchAll(
            "SELECT c.id, c.nombre, COUNT(*) AS n
               FROM equipos e JOIN categorias c ON c.id = e.categoria_id
              WHERE $vigente
           GROUP BY c.id, c.nombre
           ORDER BY n DESC, c.nombre"
        );
        $sede = $this->fetchAll(
            "SELECT s.id, COALESCE(s.nombre, 'Sin ubicación') AS nombre, COUNT(*) AS n
               FROM equipos e
          LEFT JOIN ubicaciones u ON u.id = e.ubicacion_actual_id
          LEFT JOIN sedes s       ON s.id = u.sede_id
              WHERE $vigente
           GROUP BY s.id, s.nombre
           ORDER BY s.id IS NULL, n DESC, s.nombre"
        );

        return [
            'operativo'  => array_map('intval', array_column($operativo, 'n', 'clave')),
            'inventario' => array_map('intval', array_column($inventario, 'n', 'clave')),
            'categoria'  => $categoria,
            'sede'       => $sede,
        ];
    }

    /** true si hay un mantenimiento en curso (bloquea la baja). */
    public function tieneMantenimientoEnProceso(int $id): bool
    {
        return $this->fetchOne(
            "SELECT 1 FROM mantenimientos WHERE equipo_id = :id AND estado = 'en_proceso' LIMIT 1",
            [':id' => $id]
        ) !== null;
    }

    public function darDeBaja(int $id, string $fecha, string $motivo): void
    {
        $this->execute(
            "UPDATE equipos SET estado_inventario = 'de_baja', fecha_baja = :fecha, motivo_baja = :motivo WHERE id = :id",
            [':fecha' => $fecha, ':motivo' => $motivo, ':id' => $id]
        );
    }

    /** Deshace una baja: el equipo vuelve a bodega (no tenía asignación abierta al darse de baja). */
    public function revertirBaja(int $id): void
    {
        $this->execute(
            "UPDATE equipos SET estado_inventario = 'en_bodega', fecha_baja = NULL, motivo_baja = NULL WHERE id = :id",
            [':id' => $id]
        );
    }

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
