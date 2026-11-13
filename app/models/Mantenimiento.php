<?php
/**
 * Tabla `mantenimientos` (+ `mantenimiento_repuestos`).
 *
 * Ciclo de vida (Sprint 5):
 *   programado ──iniciar──▶ en_proceso ──finalizar──▶ finalizado
 *        └────────────cancelar────────────┴──────────▶ cancelado
 * - Un reporte de falla del docente es un `diagnostico` programado sin fecha ni técnico.
 * - Solo un mantenimiento en proceso por equipo; mientras dura, el equipo está «En mantenimiento».
 * - Al finalizar, los reportes pendientes del mismo equipo se cierran con él.
 */
final class Mantenimiento extends Model
{
    /** Pestañas de la bandeja. */
    public const PESTANAS = [
        'por_atender' => 'Por atender',
        'en_proceso'  => 'En proceso',
        'finalizados' => 'Finalizados',
        'cancelados'  => 'Cancelados',
        'todos'       => 'Todos',
    ];

    /** Estado de cada pestaña y su orden. */
    private const FILTRO_PESTANA = [
        'por_atender' => ["m.estado = 'programado'",
                          'm.fecha_programada IS NOT NULL, m.fecha_programada, m.creado_en, m.id'],
        'en_proceso'  => ["m.estado = 'en_proceso'", 'm.fecha_inicio, m.id'],
        'finalizados' => ["m.estado = 'finalizado'", 'm.fecha_fin DESC, m.id DESC'],
        'cancelados'  => ["m.estado = 'cancelado'",  'm.creado_en DESC, m.id DESC'],
        'todos'       => ['1 = 1',                   'm.creado_en DESC, m.id DESC'],
    ];

    public const ESTADOS = [
        'programado' => ['Programado', 'muted'],
        'en_proceso' => ['En proceso', 'maint'],
        'finalizado' => ['Finalizado', 'ok'],
        'cancelado'  => ['Cancelado', 'inactivo'],
    ];

    /** "MNT-000030" */
    public static function numero(int $id): string
    {
        return 'MNT-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    /** true si es un reporte de falla todavía sin atender (diagnóstico sin fecha programada). */
    public static function esReporte(array $m): bool
    {
        return $m['tipo'] === 'diagnostico' && $m['fecha_programada'] === null;
    }

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

    /**
     * Página de la bandeja.
     * @param array $f pestana (clave de PESTANAS), q, tipo ('' o clave de tipo), tecnico (persona id,
     *                 0 = sin asignar, null = todos), equipo (id o null)
     * @return array{filas: array, total: int, pagina: int, paginas: int}
     */
    public function listar(array $f, int $pagina = 1, int $porPagina = 15): array
    {
        [$condicion, $orden] = self::FILTRO_PESTANA[$f['pestana']];
        $where  = [$condicion];
        $params = [];

        if ($f['q'] !== '') {
            $where[] = '(e.codigo LIKE :q1 OR e.codigo_patrimonial LIKE :q2 OR e.nombre LIKE :q3 OR m.problema_reportado LIKE :q4)';
            $like    = '%' . addcslashes($f['q'], '%_\\') . '%';
            $params += [':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like];
        }
        if ($f['tipo'] !== '') {
            $where[]         = 'm.tipo = :tipo';
            $params[':tipo'] = $f['tipo'];
        }
        if ($f['tecnico'] === 0) {
            $where[] = 'm.tecnico_id IS NULL';
        } elseif ($f['tecnico'] !== null) {
            $where[]            = 'm.tecnico_id = :tecnico';
            $params[':tecnico'] = $f['tecnico'];
        }
        if ($f['equipo'] !== null) {
            $where[]           = 'm.equipo_id = :equipo';
            $params[':equipo'] = $f['equipo'];
        }

        $desde    = 'FROM mantenimientos m JOIN equipos e ON e.id = m.equipo_id';
        $sqlWhere = ' WHERE ' . implode(' AND ', $where);

        $total   = (int) $this->fetchOne("SELECT COUNT(*) AS n $desde$sqlWhere", $params)['n'];
        $paginas = max(1, (int) ceil($total / $porPagina));
        $pagina  = min(max(1, $pagina), $paginas);
        $offset  = ($pagina - 1) * $porPagina;

        $filas = $this->fetchAll(
            "SELECT m.id, m.equipo_id, m.tipo, m.estado, m.fecha_programada, m.fecha_inicio, m.fecha_fin,
                    m.problema_reportado, m.resultado, m.creado_en,
                    e.codigo, e.nombre AS equipo, e.estado_operativo, e.estado_inventario,
                    u.nombre AS ubicacion,
                    CONCAT(t.nombres, ' ', t.apellidos) AS tecnico
             $desde
          LEFT JOIN ubicaciones u ON u.id = e.ubicacion_actual_id
          LEFT JOIN personas t    ON t.id = m.tecnico_id
             $sqlWhere
           ORDER BY $orden
              LIMIT $porPagina OFFSET $offset",
            $params
        );

        return ['filas' => $filas, 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas];
    }

    /**
     * Conteos para el resumen de la bandeja y el inicio del panel.
     * @return array{por_atender: int, reportes: int, vencidos: int, en_proceso: int, mios: int}
     */
    public function resumen(?int $tecnicoId): array
    {
        $fila = $this->fetchOne(
            "SELECT SUM(estado = 'programado') AS por_atender,
                    SUM(estado = 'programado' AND tipo = 'diagnostico' AND fecha_programada IS NULL) AS reportes,
                    SUM(estado = 'programado' AND fecha_programada < CURDATE()) AS vencidos,
                    SUM(estado = 'en_proceso') AS en_proceso,
                    SUM(estado IN ('programado', 'en_proceso') AND tecnico_id <=> :tecnico) AS mios
               FROM mantenimientos",
            [':tecnico' => $tecnicoId ?? -1]
        );
        return array_map('intval', $fila);
    }

    /**
     * Reportes de falla sin atender que puede tomar un técnico (sin asignar o asignados a él),
     * del más antiguo al más reciente, para el inicio del técnico.
     * Cada fila trae quién reportó y, si el equipo ya tiene otro trabajo en proceso, su id.
     * @return array{filas: array, total: int}
     */
    public function reportesPorAtender(?int $tecnicoId, int $limite = 30): array
    {
        $where  = "m.estado = 'programado' AND m.tipo = 'diagnostico' AND m.fecha_programada IS NULL
                   AND (m.tecnico_id IS NULL OR m.tecnico_id <=> :tecnico)";
        $params = [':tecnico' => $tecnicoId ?? -1];

        $total = (int) $this->fetchOne("SELECT COUNT(*) AS n FROM mantenimientos m WHERE $where", $params)['n'];
        $filas = $this->fetchAll(
            "SELECT m.id, m.equipo_id, m.tecnico_id, m.problema_reportado, m.creado_en,
                    e.codigo, e.nombre AS equipo, e.estado_operativo, e.estado_inventario,
                    u.nombre AS ubicacion,
                    CONCAT(pr.nombres, ' ', pr.apellidos) AS reportado, ur.rol AS reportado_rol,
                    (SELECT o.id FROM mantenimientos o
                      WHERE o.equipo_id = m.equipo_id AND o.estado = 'en_proceso' LIMIT 1) AS en_proceso_id,
                    (SELECT COUNT(*) FROM archivos f WHERE f.mantenimiento_id = m.id) AS fotos
               FROM mantenimientos m
               JOIN equipos e     ON e.id = m.equipo_id
          LEFT JOIN ubicaciones u ON u.id = e.ubicacion_actual_id
          LEFT JOIN usuarios ur   ON ur.id = m.registrado_por
          LEFT JOIN personas pr   ON pr.id = ur.persona_id
              WHERE $where
           ORDER BY m.creado_en, m.id
              LIMIT " . max(1, $limite),
            $params
        );
        return ['filas' => $filas, 'total' => $total];
    }

    /**
     * Trabajos abiertos de un técnico que no son reportes sin atender: primero los que están
     * en proceso, luego los programados por fecha.
     */
    public function abiertosDe(int $tecnicoId, int $limite = 20): array
    {
        return $this->fetchAll(
            "SELECT m.id, m.equipo_id, m.tipo, m.estado, m.fecha_programada, m.fecha_inicio,
                    m.problema_reportado, m.creado_en,
                    e.codigo, e.nombre AS equipo, u.nombre AS ubicacion
               FROM mantenimientos m
               JOIN equipos e     ON e.id = m.equipo_id
          LEFT JOIN ubicaciones u ON u.id = e.ubicacion_actual_id
              WHERE m.tecnico_id = :tecnico
                AND (m.estado = 'en_proceso'
                     OR (m.estado = 'programado' AND NOT (m.tipo = 'diagnostico' AND m.fecha_programada IS NULL)))
           ORDER BY m.estado = 'en_proceso' DESC, m.fecha_programada IS NULL, m.fecha_programada, m.fecha_inicio, m.id
              LIMIT " . max(1, $limite),
            [':tecnico' => $tecnicoId]
        );
    }

    /**
     * Agenda del dashboard: mantenimientos programados con fecha, primero los atrasados
     * y luego los que vencen en los próximos $dias días.
     * @return array{filas: array, total: int}
     */
    public function agenda(int $dias = 30, int $limite = 6): array
    {
        $where  = "m.estado = 'programado' AND m.fecha_programada IS NOT NULL
                   AND m.fecha_programada < CURDATE() + INTERVAL :dias DAY";
        $params = [':dias' => max(1, $dias)];

        $total = (int) $this->fetchOne("SELECT COUNT(*) AS n FROM mantenimientos m WHERE $where", $params)['n'];
        $filas = $this->fetchAll(
            "SELECT m.id, m.equipo_id, m.tipo, m.fecha_programada,
                    m.fecha_programada < CURDATE() AS atrasado,
                    e.codigo, e.nombre AS equipo, u.nombre AS ubicacion,
                    CONCAT(t.nombres, ' ', t.apellidos) AS tecnico
               FROM mantenimientos m
               JOIN equipos e     ON e.id = m.equipo_id
          LEFT JOIN ubicaciones u ON u.id = e.ubicacion_actual_id
          LEFT JOIN personas t    ON t.id = m.tecnico_id
              WHERE $where
           ORDER BY m.fecha_programada, m.id
              LIMIT " . max(1, $limite),
            $params
        );
        return ['filas' => $filas, 'total' => $total];
    }

    /** Mantenimiento con su equipo, técnico y quién lo registró. */
    public function porId(int $id): ?array
    {
        return $this->fetchOne(
            "SELECT m.*,
                    e.codigo, e.nombre AS equipo, e.marca, e.modelo, e.estado_operativo, e.estado_inventario,
                    u.nombre AS ubicacion,
                    CONCAT(t.nombres, ' ', t.apellidos) AS tecnico,
                    CONCAT(pr.nombres, ' ', pr.apellidos) AS registrado, ur.rol AS registrado_rol,
                    CONCAT(pa.nombres, ' ', pa.apellidos) AS responsable
               FROM mantenimientos m
               JOIN equipos e           ON e.id = m.equipo_id
          LEFT JOIN ubicaciones u       ON u.id = e.ubicacion_actual_id
          LEFT JOIN personas t          ON t.id = m.tecnico_id
          LEFT JOIN usuarios ur         ON ur.id = m.registrado_por
          LEFT JOIN personas pr         ON pr.id = ur.persona_id
          LEFT JOIN asignaciones a      ON a.equipo_activo_id = e.id
          LEFT JOIN personas pa         ON pa.id = a.persona_id
              WHERE m.id = :id",
            [':id' => $id]
        );
    }

    /** Relee y bloquea el mantenimiento hasta el fin de la transacción. */
    public function bloquear(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM mantenimientos WHERE id = :id FOR UPDATE', [':id' => $id]);
    }

    /** Mantenimientos de un equipo para su ficha (incluye cancelados), el más reciente primero. */
    public function deEquipo(int $equipoId, int $limite = 5): array
    {
        return $this->fetchAll(
            "SELECT m.id, m.tipo, m.estado, m.fecha_programada, m.fecha_inicio, m.fecha_fin,
                    m.problema_reportado, m.resultado, m.creado_en,
                    CONCAT(t.nombres, ' ', t.apellidos) AS tecnico
               FROM mantenimientos m
          LEFT JOIN personas t ON t.id = m.tecnico_id
              WHERE m.equipo_id = :id
           ORDER BY m.estado = 'en_proceso' DESC, m.estado = 'programado' DESC,
                    COALESCE(m.fecha_fin, m.fecha_inicio, m.creado_en) DESC, m.id DESC
              LIMIT $limite",
            [':id' => $equipoId]
        );
    }

    public function contarDe(int $equipoId): int
    {
        return (int) $this->fetchOne('SELECT COUNT(*) AS n FROM mantenimientos WHERE equipo_id = :id', [':id' => $equipoId])['n'];
    }

    /** Mantenimiento en proceso del equipo (distinto de $exceptoId), o null. */
    public function enProcesoDe(int $equipoId, ?int $exceptoId = null): ?array
    {
        return $this->fetchOne(
            "SELECT id, tipo FROM mantenimientos
              WHERE equipo_id = :equipo AND estado = 'en_proceso' AND id <> :excepto LIMIT 1",
            [':equipo' => $equipoId, ':excepto' => $exceptoId ?? 0]
        );
    }

    /** Reportes de falla pendientes del mismo equipo (sin contar $exceptoId), del más antiguo al más nuevo. */
    public function reportesPendientes(int $equipoId, int $exceptoId): array
    {
        return $this->fetchAll(
            "SELECT m.id, m.problema_reportado, m.creado_en,
                    CONCAT(p.nombres, ' ', p.apellidos) AS registrado
               FROM mantenimientos m
          LEFT JOIN usuarios u ON u.id = m.registrado_por
          LEFT JOIN personas p ON p.id = u.persona_id
              WHERE m.equipo_id = :equipo AND m.id <> :excepto AND m.estado = 'programado'
                AND m.tipo = 'diagnostico' AND m.fecha_programada IS NULL
           ORDER BY m.creado_en, m.id",
            [':equipo' => $equipoId, ':excepto' => $exceptoId]
        );
    }

    /** Programa un mantenimiento y devuelve su id. */
    public function crear(array $datos, int $usuarioId): int
    {
        $this->execute(
            "INSERT INTO mantenimientos (equipo_id, tecnico_id, tipo, fecha_programada, problema_reportado, estado, registrado_por)
             VALUES (:equipo, :tecnico, :tipo, :fecha, :problema, 'programado', :usuario)",
            [
                ':equipo'   => $datos['equipo_id'],
                ':tecnico'  => $datos['tecnico_id'] ?? null,
                ':tipo'     => $datos['tipo'],
                ':fecha'    => $datos['fecha_programada'] ?? null,
                ':problema' => $datos['problema_reportado'] ?? null,
                ':usuario'  => $usuarioId,
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    /** Cambia tipo, fecha, descripción y técnico de un mantenimiento todavía programado. */
    public function reprogramar(int $id, array $datos): bool
    {
        return $this->execute(
            "UPDATE mantenimientos
                SET tipo = :tipo, fecha_programada = :fecha, problema_reportado = :problema, tecnico_id = :tecnico
              WHERE id = :id AND estado = 'programado'",
            [
                ':tipo'     => $datos['tipo'],
                ':fecha'    => $datos['fecha_programada'],
                ':problema' => $datos['problema_reportado'],
                ':tecnico'  => $datos['tecnico_id'],
                ':id'       => $id,
            ]
        ) === 1;
    }

    /** Asigna (o cambia) el técnico de un mantenimiento abierto. */
    public function asignar(int $id, int $tecnicoId): bool
    {
        return $this->execute(
            "UPDATE mantenimientos SET tecnico_id = :t WHERE id = :id AND estado IN ('programado', 'en_proceso')",
            [':t' => $tecnicoId, ':id' => $id]
        ) === 1;
    }

    /** Pasa a en proceso con el técnico indicado. false si ya no estaba programado. */
    public function iniciar(int $id, int $tecnicoId, string $fecha): bool
    {
        return $this->execute(
            "UPDATE mantenimientos SET estado = 'en_proceso', tecnico_id = :t, fecha_inicio = :f
              WHERE id = :id AND estado = 'programado'",
            [':t' => $tecnicoId, ':f' => $fecha, ':id' => $id]
        ) === 1;
    }

    /** Cierra el trabajo con su informe. false si ya no estaba en proceso. */
    public function finalizar(int $id, array $datos): bool
    {
        return $this->execute(
            "UPDATE mantenimientos
                SET estado = 'finalizado', fecha_fin = :fin, diagnostico = :diagnostico,
                    trabajo_realizado = :trabajo, resultado = :resultado, recomendaciones = :recomendaciones,
                    costo_mano_obra = :costo, proximo_mantenimiento = :proximo
              WHERE id = :id AND estado = 'en_proceso'",
            [
                ':fin'             => $datos['fecha_fin'],
                ':diagnostico'     => $datos['diagnostico'],
                ':trabajo'         => $datos['trabajo_realizado'],
                ':resultado'       => $datos['resultado'],
                ':recomendaciones' => $datos['recomendaciones'],
                ':costo'           => $datos['costo_mano_obra'] ?? '0',
                ':proximo'         => $datos['proximo_mantenimiento'],
                ':id'              => $id,
            ]
        ) === 1;
    }

    /** Cierra un reporte pendiente porque se atendió dentro de otro mantenimiento. */
    public function cerrarReporte(int $id, int $tecnicoId, string $fecha, string $resultado): void
    {
        $this->execute(
            "UPDATE mantenimientos
                SET estado = 'finalizado', tecnico_id = :t, fecha_inicio = COALESCE(fecha_inicio, :f1),
                    fecha_fin = :f2, resultado = :resultado
              WHERE id = :id AND estado = 'programado'",
            [':t' => $tecnicoId, ':f1' => $fecha, ':f2' => $fecha, ':resultado' => $resultado, ':id' => $id]
        );
    }

    /** Anula un mantenimiento abierto; el motivo queda en el resultado. */
    public function cancelar(int $id, string $motivo): bool
    {
        return $this->execute(
            "UPDATE mantenimientos SET estado = 'cancelado', resultado = :motivo
              WHERE id = :id AND estado IN ('programado', 'en_proceso')",
            [':motivo' => $motivo, ':id' => $id]
        ) === 1;
    }

    // ---------- Repuestos usados ----------

    /** Repuestos usados en el mantenimiento, en el orden en que se agregaron. */
    public function repuestos(int $id): array
    {
        return $this->fetchAll(
            'SELECT mr.id, mr.repuesto_id, mr.cantidad, mr.costo_unitario, mr.detalle,
                    (mr.cantidad * mr.costo_unitario) AS subtotal,
                    r.nombre, r.codigo, r.unidad
               FROM mantenimiento_repuestos mr
               JOIN repuestos r ON r.id = mr.repuesto_id
              WHERE mr.mantenimiento_id = :id
           ORDER BY mr.id',
            [':id' => $id]
        );
    }

    /** Línea de repuesto solo si pertenece a ese mantenimiento. */
    public function lineaRepuesto(int $mantenimientoId, int $lineaId): ?array
    {
        return $this->fetchOne(
            'SELECT mr.*, r.nombre, r.unidad FROM mantenimiento_repuestos mr
               JOIN repuestos r ON r.id = mr.repuesto_id
              WHERE mr.id = :linea AND mr.mantenimiento_id = :mant',
            [':linea' => $lineaId, ':mant' => $mantenimientoId]
        );
    }

    public function agregarRepuesto(int $mantenimientoId, array $datos): int
    {
        $this->execute(
            'INSERT INTO mantenimiento_repuestos (mantenimiento_id, repuesto_id, cantidad, costo_unitario, detalle)
             VALUES (:mant, :repuesto, :cantidad, :costo, :detalle)',
            [
                ':mant'     => $mantenimientoId,
                ':repuesto' => $datos['repuesto_id'],
                ':cantidad' => $datos['cantidad'],
                ':costo'    => $datos['costo_unitario'] ?? '0',
                ':detalle'  => $datos['detalle'],
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    public function quitarRepuesto(int $lineaId): void
    {
        $this->execute('DELETE FROM mantenimiento_repuestos WHERE id = :id', [':id' => $lineaId]);
    }

    public function contarRepuestos(int $id): int
    {
        return (int) $this->fetchOne('SELECT COUNT(*) AS n FROM mantenimiento_repuestos WHERE mantenimiento_id = :id', [':id' => $id])['n'];
    }
}
