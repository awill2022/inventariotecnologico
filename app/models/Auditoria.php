<?php
/**
 * Tabla `auditoria`: registro de acciones de los usuarios.
 *
 * - registrar(): lo usan todos los módulos al escribir.
 * - listar() / recientes(): visor de auditoría y actividad reciente del dashboard (solo administrador).
 *   Los inicios de sesión (`acceder`) se omiten salvo que se pidan con el filtro de acción.
 */
final class Auditoria extends Model
{
    public const ACCIONES = ['crear', 'editar', 'cerrar', 'anular', 'acceder'];

    /**
     * Cómo nombrar y enlazar el registro afectado, por entidad: SQL que devuelve id, texto y ruta
     * para una lista de ids (el marcador {ids} se reemplaza por "?, ?, ...").
     */
    private const REFERENCIAS = [
        'equipos'        => "SELECT e.id, e.codigo AS texto, CONCAT('/equipos/', e.id) AS ruta
                               FROM equipos e WHERE e.id IN ({ids})",
        'mantenimientos' => "SELECT m.id, CONCAT('MNT-', LPAD(m.id, 6, '0'), ' · ', e.codigo) AS texto,
                                    CONCAT('/mantenimientos/', m.id) AS ruta
                               FROM mantenimientos m JOIN equipos e ON e.id = m.equipo_id WHERE m.id IN ({ids})",
        'mantenimiento_repuestos' => "SELECT mr.id, CONCAT(r.nombre, ' · MNT-', LPAD(mr.mantenimiento_id, 6, '0')) AS texto,
                                    CONCAT('/mantenimientos/', mr.mantenimiento_id) AS ruta
                               FROM mantenimiento_repuestos mr JOIN repuestos r ON r.id = mr.repuesto_id
                              WHERE mr.id IN ({ids})",
        'asignaciones'   => "SELECT a.id, CONCAT(e.codigo, ' a ', p.nombres, ' ', p.apellidos) AS texto,
                                    CONCAT('/asignaciones/', a.id, '/acta') AS ruta
                               FROM asignaciones a JOIN equipos e ON e.id = a.equipo_id JOIN personas p ON p.id = a.persona_id
                              WHERE a.id IN ({ids})",
        'movimientos'    => "SELECT mv.id, CONCAT(e.codigo, ' a ', u.nombre) AS texto,
                                    CONCAT('/movimientos?equipo=', mv.equipo_id) AS ruta
                               FROM movimientos mv JOIN equipos e ON e.id = mv.equipo_id
                               JOIN ubicaciones u ON u.id = mv.ubicacion_destino_id
                              WHERE mv.id IN ({ids})",
        'componentes'    => "SELECT c.id, CONCAT(c.tipo, ' · ', e.codigo) AS texto, CONCAT('/equipos/', c.equipo_id) AS ruta
                               FROM componentes c JOIN equipos e ON e.id = c.equipo_id WHERE c.id IN ({ids})",
        'archivos'       => "SELECT f.id, f.nombre_original AS texto, NULL AS ruta FROM archivos f WHERE f.id IN ({ids})",
        'personas'       => "SELECT p.id, CONCAT(p.nombres, ' ', p.apellidos) AS texto, CONCAT('/personas/', p.id) AS ruta
                               FROM personas p WHERE p.id IN ({ids})",
        'usuarios'       => "SELECT u.id, u.usuario AS texto, CONCAT('/usuarios/', u.id, '/editar') AS ruta
                               FROM usuarios u WHERE u.id IN ({ids})",
        'repuestos'      => "SELECT r.id, r.nombre AS texto, CONCAT('/repuestos/', r.id, '/editar') AS ruta
                               FROM repuestos r WHERE r.id IN ({ids})",
        'categorias'     => "SELECT x.id, x.nombre AS texto, CONCAT('/categorias/', x.id, '/editar') AS ruta
                               FROM categorias x WHERE x.id IN ({ids})",
        'sedes'          => "SELECT x.id, x.nombre AS texto, CONCAT('/sedes/', x.id, '/editar') AS ruta
                               FROM sedes x WHERE x.id IN ({ids})",
        'areas'          => "SELECT x.id, x.nombre AS texto, CONCAT('/areas/', x.id, '/editar') AS ruta
                               FROM areas x WHERE x.id IN ({ids})",
        'ubicaciones'    => "SELECT x.id, x.nombre AS texto, CONCAT('/ubicaciones/', x.id, '/editar') AS ruta
                               FROM ubicaciones x WHERE x.id IN ({ids})",
    ];

    /** @param string $accion crear | editar | cerrar | anular | acceder */
    public function registrar(?int $usuarioId, string $entidad, int $entidadId, string $accion, array $detalle = []): void
    {
        $detalle += [
            'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ];

        $this->execute(
            'INSERT INTO auditoria (usuario_id, entidad, entidad_id, accion, detalle)
             VALUES (:usuario, :entidad, :entidad_id, :accion, :detalle)',
            [
                ':usuario'    => $usuarioId,
                ':entidad'    => $entidad,
                ':entidad_id' => $entidadId,
                ':accion'     => $accion,
                ':detalle'    => json_encode($detalle, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            ]
        );
    }

    /**
     * Visor de auditoría, del más reciente al más antiguo.
     * @param array $f usuario (id|null), entidad (''|nombre), registro (id|null, solo con entidad),
     *                 accion (''|ACCIONES), desde, hasta (Y-m-d|null)
     * @return array{filas: array, total: int, pagina: int, paginas: int}
     */
    public function listar(array $f, int $pagina = 1, int $porPagina = 25): array
    {
        [$sqlWhere, $params] = $this->condiciones($f);
        $desde = 'FROM auditoria a';

        $total   = (int) $this->fetchOne("SELECT COUNT(*) AS n $desde$sqlWhere", $params)['n'];
        $paginas = max(1, (int) ceil($total / $porPagina));
        $pagina  = min(max(1, $pagina), $paginas);
        $offset  = ($pagina - 1) * $porPagina;

        $filas = $this->conAutor("$sqlWhere ORDER BY a.fecha DESC, a.id DESC LIMIT $porPagina OFFSET $offset", $params);
        return ['filas' => $filas, 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas];
    }

    /** Últimas acciones (sin inicios de sesión) para el dashboard. */
    public function recientes(int $limite = 8): array
    {
        return $this->conAutor(" WHERE a.accion <> 'acceder' ORDER BY a.fecha DESC, a.id DESC LIMIT " . max(1, $limite));
    }

    /** Entidades que aparecen en la auditoría, para el filtro. */
    public function entidades(): array
    {
        return array_column($this->fetchAll('SELECT DISTINCT entidad FROM auditoria ORDER BY entidad'), 'entidad');
    }

    /** Usuarios con acciones registradas: [id => "Nombre Apellido (usuario)"]. */
    public function autores(): array
    {
        $filas = $this->fetchAll(
            "SELECT u.id, CONCAT(COALESCE(CONCAT(p.nombres, ' ', p.apellidos), u.usuario), ' (', u.usuario, ')') AS texto
               FROM usuarios u
          LEFT JOIN personas p ON p.id = u.persona_id
              WHERE EXISTS (SELECT 1 FROM auditoria a WHERE a.usuario_id = u.id)
           ORDER BY texto"
        );
        return array_column($filas, 'texto', 'id');
    }

    /**
     * Nombre legible y enlace del registro afectado de cada fila.
     * @return array<string, array{texto: string, ruta: ?string}> clave "entidad:id"
     */
    public function referencias(array $filas): array
    {
        $ids = [];
        foreach ($filas as $r) {
            if (isset(self::REFERENCIAS[$r['entidad']])) {
                $ids[$r['entidad']][(int) $r['entidad_id']] = true;
            }
        }

        $refs = [];
        foreach ($ids as $entidad => $lista) {
            $lista = array_keys($lista);
            $sql   = str_replace('{ids}', implode(', ', array_fill(0, count($lista), '?')), self::REFERENCIAS[$entidad]);
            foreach ($this->fetchAll($sql, $lista) as $fila) {
                $refs["$entidad:{$fila['id']}"] = ['texto' => (string) $fila['texto'], 'ruta' => $fila['ruta']];
            }
        }
        return $refs;
    }

    // ------------------------------------------------------------------

    /** Filas con el nombre de quien hizo la acción. $resto = WHERE + ORDER + LIMIT. */
    private function conAutor(string $resto, array $params = []): array
    {
        return $this->fetchAll(
            "SELECT a.id, a.usuario_id, a.entidad, a.entidad_id, a.accion, a.detalle, a.fecha,
                    u.usuario, u.rol,
                    CONCAT(p.nombres, ' ', p.apellidos) AS autor
               FROM auditoria a
          LEFT JOIN usuarios u ON u.id = a.usuario_id
          LEFT JOIN personas p ON p.id = u.persona_id
             $resto",
            $params
        );
    }

    /** @return array{0: string, 1: array} " WHERE ..." y sus parámetros */
    private function condiciones(array $f): array
    {
        $where  = [];
        $params = [];

        if ($f['accion'] !== '') {
            $where[]           = 'a.accion = :accion';
            $params[':accion'] = $f['accion'];
        } else {
            $where[] = "a.accion <> 'acceder'";
        }
        if ($f['usuario'] !== null) {
            $where[]            = 'a.usuario_id = :usuario';
            $params[':usuario'] = $f['usuario'];
        }
        if ($f['entidad'] !== '') {
            $where[]            = 'a.entidad = :entidad';
            $params[':entidad'] = $f['entidad'];
            if ($f['registro'] !== null) {
                $where[]             = 'a.entidad_id = :registro';
                $params[':registro'] = $f['registro'];
            }
        }
        if ($f['desde'] !== null) {
            $where[]          = 'a.fecha >= :desde';
            $params[':desde'] = $f['desde'] . ' 00:00:00';
        }
        if ($f['hasta'] !== null) {
            $where[]          = 'a.fecha < :hasta + INTERVAL 1 DAY';
            $params[':hasta'] = $f['hasta'];
        }

        return [' WHERE ' . implode(' AND ', $where), $params];
    }
}
