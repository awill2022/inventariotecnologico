<?php
/**
 * Tabla `mantenimientos`.
 */
final class Mantenimiento extends Model
{
    public function deEquipos(array $equipoIds): array
    {
        if (!$equipoIds) {
            return [];
        }
        $in   = implode(',', array_fill(0, count($equipoIds), '?'));
        $rows = $this->fetchAll(
            "SELECT m.id, m.equipo_id, m.tecnico_id, m.tipo, m.estado, m.fecha_programada,
                    m.fecha_inicio, m.fecha_fin, m.problema_reportado, m.diagnostico,
                    m.trabajo_realizado, m.resultado, m.recomendaciones,
                    m.costo_mano_obra, m.proximo_mantenimiento, m.creado_en,
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

    public function reportarFalla(int $equipoId, string $problema, ?int $usuarioId): int
    {
        $this->execute(
            "INSERT INTO mantenimientos (equipo_id, tipo, estado, problema_reportado, registrado_por)
             VALUES (:equipo, 'diagnostico', 'programado', :problema, :usuario)",
            [':equipo' => $equipoId, ':problema' => $problema, ':usuario' => $usuarioId]
        );
        return (int) $this->db->lastInsertId();
    }

    public function abiertoDeEquipo(int $equipoId): ?array
    {
        return $this->fetchOne(
            "SELECT m.*,
                    TRIM(CONCAT(COALESCE(t.nombres, ''), ' ', COALESCE(t.apellidos, ''))) AS tecnico
               FROM mantenimientos m
          LEFT JOIN personas t ON t.id = m.tecnico_id
              WHERE m.equipo_id = :equipo
                AND m.estado IN ('programado', 'en_proceso')
           ORDER BY m.estado = 'en_proceso' DESC, m.id DESC
              LIMIT 1",
            [':equipo' => $equipoId]
        );
    }

    public function bandeja(string $estado = 'abiertos', string $q = '', int $limite = 30): array
    {
        $where  = ["m.estado <> 'cancelado'"];
        $params = [];

        if ($estado === 'abiertos') {
            $where[] = "m.estado IN ('programado', 'en_proceso')";
        } elseif (in_array($estado, ['programado', 'en_proceso', 'finalizado'], true)) {
            $where[]           = 'm.estado = :estado';
            $params[':estado'] = $estado;
        }

        if ($q !== '') {
            $like    = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = "(e.codigo LIKE :q1 OR e.nombre LIKE :q2 OR m.problema_reportado LIKE :q3
                         OR CONCAT(p.nombres, ' ', p.apellidos) LIKE :q4)";
            $params += [':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like];
        }

        $sqlWhere = 'WHERE ' . implode(' AND ', $where);

        return $this->fetchAll(
            "SELECT m.id, m.equipo_id, m.tipo, m.estado, m.fecha_programada,
                    m.fecha_inicio, m.fecha_fin, m.problema_reportado, m.diagnostico,
                    m.trabajo_realizado, m.creado_en,
                    e.codigo, e.codigo_patrimonial, e.nombre AS equipo_nombre,
                    e.marca, e.modelo, e.estado_operativo,
                    u.nombre AS ubicacion, u.edificio, u.piso,
                    CONCAT(p.nombres, ' ', p.apellidos) AS custodio,
                    TRIM(CONCAT(COALESCE(t.nombres, ''), ' ', COALESCE(t.apellidos, ''))) AS tecnico,
                    (SELECT COUNT(*) FROM archivos ar WHERE ar.mantenimiento_id = m.id) AS fotos
               FROM mantenimientos m
               JOIN equipos e      ON e.id = m.equipo_id
          LEFT JOIN ubicaciones u  ON u.id = e.ubicacion_actual_id
          LEFT JOIN asignaciones a ON a.equipo_id = e.id AND a.fecha_devolucion IS NULL
          LEFT JOIN personas p     ON p.id = a.persona_id
          LEFT JOIN personas t     ON t.id = m.tecnico_id
              $sqlWhere
           ORDER BY FIELD(m.estado, 'programado', 'en_proceso', 'finalizado'),
                    COALESCE(m.fecha_inicio, m.creado_en) DESC
              LIMIT $limite",
            $params
        );
    }

    public function resumenConteos(): array
    {
        $fila = $this->fetchOne(
            "SELECT SUM(estado = 'programado') AS pendientes,
                    SUM(estado = 'en_proceso') AS en_proceso,
                    SUM(estado = 'finalizado') AS finalizados
               FROM mantenimientos"
        );
        return [
            'pendientes'  => (int) ($fila['pendientes'] ?? 0),
            'en_proceso'  => (int) ($fila['en_proceso'] ?? 0),
            'finalizados' => (int) ($fila['finalizados'] ?? 0),
        ];
    }

    public function iniciar(int $id, ?int $tecnicoPersonaId): void
    {
        $this->execute(
            "UPDATE mantenimientos
                SET estado = 'en_proceso',
                    tecnico_id = COALESCE(:tecnico, tecnico_id),
                    fecha_inicio = COALESCE(fecha_inicio, NOW())
              WHERE id = :id",
            [':tecnico' => $tecnicoPersonaId, ':id' => $id]
        );
    }

    public function finalizar(int $id, array $datos, ?int $tecnicoPersonaId): void
    {
        $this->execute(
            "UPDATE mantenimientos
                SET tipo                  = :tipo,
                    tecnico_id            = COALESCE(:tecnico, tecnico_id),
                    fecha_inicio          = COALESCE(fecha_inicio, NOW()),
                    fecha_fin             = NOW(),
                    diagnostico           = :diagnostico,
                    trabajo_realizado     = :trabajo_realizado,
                    resultado             = :resultado,
                    recomendaciones       = :recomendaciones,
                    costo_mano_obra       = :costo,
                    proximo_mantenimiento = :proximo_mantenimiento,
                    estado                = 'finalizado'
              WHERE id = :id",
            [
                ':tipo'                  => $datos['tipo'],
                ':tecnico'               => $tecnicoPersonaId,
                ':diagnostico'           => $datos['diagnostico'],
                ':trabajo_realizado'     => $datos['trabajo_realizado'],
                ':resultado'             => $datos['resultado'],
                ':recomendaciones'       => $datos['recomendaciones'],
                ':costo'                 => $datos['costo_mano_obra'] ?? 0,
                ':proximo_mantenimiento' => $datos['proximo_mantenimiento'],
                ':id'                    => $id,
            ]
        );
    }

    public function registrarDirecto(int $equipoId, array $datos, ?int $tecnicoPersonaId, int $usuarioId, string $estadoMant): int
    {
        $finalizado = $estadoMant === 'finalizado';
        $this->execute(
            "INSERT INTO mantenimientos
                (equipo_id, tecnico_id, tipo, fecha_inicio, fecha_fin,
                 problema_reportado, diagnostico, trabajo_realizado, resultado,
                 recomendaciones, costo_mano_obra, proximo_mantenimiento, estado, registrado_por)
             VALUES
                (:equipo, :tecnico, :tipo, NOW(), " . ($finalizado ? 'NOW()' : 'NULL') . ",
                 :problema, :diagnostico, :trabajo, :resultado,
                 :recomendaciones, :costo, :proximo, :estado, :usuario)",
            [
                ':equipo'          => $equipoId,
                ':tecnico'         => $tecnicoPersonaId,
                ':tipo'            => $datos['tipo'],
                ':problema'        => $datos['problema_reportado'],
                ':diagnostico'     => $datos['diagnostico'],
                ':trabajo'         => $datos['trabajo_realizado'],
                ':resultado'       => $datos['resultado'],
                ':recomendaciones' => $datos['recomendaciones'],
                ':costo'           => $datos['costo_mano_obra'] ?? 0,
                ':proximo'         => $datos['proximo_mantenimiento'],
                ':estado'          => $estadoMant,
                ':usuario'         => $usuarioId,
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    /** Programa un mantenimiento preventivo futuro para un equipo. */
    public function programarPreventivo(int $equipoId, ?int $tecnicoPersonaId, string $fechaProgramada, ?string $motivo, int $usuarioId): int
    {
        $this->execute(
            "INSERT INTO mantenimientos
                (equipo_id, tecnico_id, tipo, fecha_programada, problema_reportado, estado, registrado_por)
             VALUES
                (:equipo, :tecnico, 'preventivo', :fecha, :motivo, 'programado', :usuario)",
            [
                ':equipo'  => $equipoId,
                ':tecnico' => $tecnicoPersonaId,
                ':fecha'   => $fechaProgramada,
                ':motivo'  => $motivo ?: 'Mantenimiento preventivo programado.',
                ':usuario' => $usuarioId,
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    /** Cancela/anula una solicitud abierta guardando el motivo en resultado. */
    public function cancelar(int $id, string $motivo): void
    {
        $this->execute(
            "UPDATE mantenimientos
                SET estado    = 'cancelado',
                    fecha_fin = NOW(),
                    resultado = :motivo
              WHERE id = :id",
            [':motivo' => 'Cancelado/Anulado: ' . $motivo, ':id' => $id]
        );
    }

    /** Lista de técnicos activos [persona_id => nombre] para asignar preventivos. */
    public function tecnicosActivos(): array
    {
        $filas = $this->fetchAll(
            "SELECT p.id, CONCAT(p.nombres, ' ', p.apellidos) AS nombre
               FROM personas p
          LEFT JOIN usuarios u ON u.persona_id = p.id
              WHERE p.activo = 1
                AND (u.rol IN ('tecnico', 'administrador') OR p.cargo LIKE '%técnico%' OR p.cargo LIKE '%tecnico%')
           ORDER BY p.nombres, p.apellidos"
        );
        return array_column($filas, 'nombre', 'id');
    }
}