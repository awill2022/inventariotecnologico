<?php
/**
 * Modelo base de los catálogos (sedes, áreas, ubicaciones, categorías):
 * listado con búsqueda y paginación, alta, edición y activar/desactivar.
 * Los catálogos no se borran: se desactivan con activo = 0.
 */
abstract class Catalogo extends Model
{
    /** Nombre de la tabla. */
    protected string $tabla = '';

    /** Columnas que se pueden escribir desde el formulario. */
    protected array $columnas = [];

    /** Expresiones SQL en las que busca el listado. */
    protected array $buscables = ['t.nombre'];

    /** Orden del listado. */
    protected string $orden = 't.nombre';

    /** Columnas del listado (la tabla principal lleva el alias t). */
    protected function seleccion(): string
    {
        return 't.*';
    }

    /** FROM y JOIN del listado. */
    protected function desde(): string
    {
        return "FROM {$this->tabla} t";
    }

    /**
     * Página de resultados.
     * @param string $estado 'todos' | 'activos' | 'inactivos'
     * @return array{filas: array, total: int, pagina: int, paginas: int}
     */
    public function listar(string $q = '', string $estado = 'todos', int $pagina = 1, int $porPagina = 10): array
    {
        $where  = [];
        $params = [];

        if ($q !== '') {
            $likes = [];
            foreach ($this->buscables as $i => $columna) {
                $likes[]          = "$columna LIKE :q$i";
                $params[":q$i"]   = '%' . addcslashes($q, '%_\\') . '%';
            }
            $where[] = '(' . implode(' OR ', $likes) . ')';
        }
        if ($estado === 'activos') {
            $where[] = 't.activo = 1';
        } elseif ($estado === 'inactivos') {
            $where[] = 't.activo = 0';
        }

        $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $total    = (int) $this->fetchOne("SELECT COUNT(*) AS n {$this->desde()}$sqlWhere", $params)['n'];
        $paginas  = max(1, (int) ceil($total / $porPagina));
        $pagina   = min(max(1, $pagina), $paginas);
        $offset   = ($pagina - 1) * $porPagina;

        $filas = $this->fetchAll(
            "SELECT {$this->seleccion()} {$this->desde()}$sqlWhere
              ORDER BY {$this->orden} LIMIT $porPagina OFFSET $offset",
            $params
        );

        return ['filas' => $filas, 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas];
    }

    public function porId(int $id): ?array
    {
        return $this->fetchOne("SELECT * FROM {$this->tabla} WHERE id = :id", [':id' => $id]);
    }

    /** Inserta el registro y devuelve su id. */
    public function crear(array $datos): int
    {
        $datos    = array_intersect_key($datos, array_flip($this->columnas));
        $columnas = array_keys($datos);
        $marcas   = array_map(fn ($c) => ":$c", $columnas);

        $this->execute(
            "INSERT INTO {$this->tabla} (" . implode(', ', $columnas) . ') VALUES (' . implode(', ', $marcas) . ')',
            array_combine($marcas, array_values($datos))
        );
        return (int) $this->db->lastInsertId();
    }

    public function actualizar(int $id, array $datos): void
    {
        $datos  = array_intersect_key($datos, array_flip($this->columnas));
        $sets   = [];
        $params = [':id' => $id];
        foreach ($datos as $columna => $valor) {
            $sets[]             = "$columna = :$columna";
            $params[":$columna"] = $valor;
        }
        $this->execute("UPDATE {$this->tabla} SET " . implode(', ', $sets) . ' WHERE id = :id', $params);
    }

    public function cambiarEstado(int $id, bool $activo): void
    {
        $this->execute(
            "UPDATE {$this->tabla} SET activo = :activo WHERE id = :id",
            [':activo' => (int) $activo, ':id' => $id]
        );
    }

    /**
     * true si ya hay otro registro con esos valores (para reglas de "no repetir").
     * @param array $valores [columna => valor]; admite null
     */
    public function existe(array $valores, ?int $exceptoId = null): bool
    {
        $where  = [];
        $params = [];
        foreach ($valores as $columna => $valor) {
            $where[]             = "$columna <=> :$columna";
            $params[":$columna"] = $valor;
        }
        if ($exceptoId !== null) {
            $where[]        = 'id <> :excepto';
            $params[':excepto'] = $exceptoId;
        }
        return $this->fetchOne(
            "SELECT 1 FROM {$this->tabla} WHERE " . implode(' AND ', $where) . ' LIMIT 1',
            $params
        ) !== null;
    }

    /**
     * Opciones [id => nombre] de los registros activos, para un <select>.
     * $incluirId agrega también ese registro aunque esté inactivo (valor ya guardado).
     */
    public function opciones(?int $incluirId = null): array
    {
        $filas = $this->fetchAll(
            "SELECT id, nombre FROM {$this->tabla} WHERE activo = 1 OR id <=> :incluir ORDER BY nombre",
            [':incluir' => $incluirId]
        );
        return array_column($filas, 'nombre', 'id');
    }

    public function contarActivos(): int
    {
        return (int) $this->fetchOne("SELECT COUNT(*) AS n FROM {$this->tabla} WHERE activo = 1")['n'];
    }

    /**
     * Registros vigentes que dependen de este y que impiden desactivarlo.
     * Devuelve frases listas para mostrar, p. ej. ['3 ubicaciones activas'].
     */
    public function dependencias(int $id): array
    {
        return [];
    }

    /** Cuenta filas de una consulta "SELECT COUNT(*) AS n ..." con el id como parámetro. */
    protected function contar(string $sql, int $id): int
    {
        return (int) $this->fetchOne($sql, [':id' => $id])['n'];
    }
}
