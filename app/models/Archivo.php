<?php
/**
 * Tabla `archivos`: fotos y documentos de un equipo, enlazados opcionalmente
 * a un mantenimiento (fotos del reporte de falla), una asignación (acta firmada)
 * o un movimiento (respaldo del traslado).
 *
 * El archivo físico vive en storage/archivos (ver Subida) y solo se entrega por /archivos/{id}.
 */
final class Archivo extends Model
{
    /** Tipos (columna `tipo`) => texto. */
    public const TIPOS = [
        'falla'             => 'Foto de la falla',
        'acta_entrega'      => 'Acta de entrega firmada',
        'acta_devolucion'   => 'Acta de devolución firmada',
        'respaldo_traslado' => 'Respaldo del traslado',
        'otro'              => 'Otro documento',
    ];

    private const SELECCION = "a.id, a.equipo_id, a.mantenimiento_id, a.asignacion_id, a.movimiento_id,
                    a.clase, a.tipo, a.nombre_original, a.ruta_archivo, a.mime_type, a.tamano_bytes,
                    a.descripcion, a.subido_por, a.creado_en,
                    COALESCE(CONCAT(p.nombres, ' ', p.apellidos), u.usuario) AS subido";

    private const DESDE = 'FROM archivos a
          LEFT JOIN usuarios u ON u.id = a.subido_por
          LEFT JOIN personas p ON p.id = u.persona_id';

    /**
     * @param array $d equipo_id, mantenimiento_id?, asignacion_id?, movimiento_id?, clase, tipo,
     *                 nombre_original, ruta_archivo, mime_type, tamano_bytes, descripcion?, subido_por
     */
    public function crear(array $d): int
    {
        $this->execute(
            'INSERT INTO archivos (equipo_id, mantenimiento_id, asignacion_id, movimiento_id, clase, tipo,
                                   nombre_original, ruta_archivo, mime_type, tamano_bytes, descripcion, subido_por)
             VALUES (:equipo, :mant, :asig, :mov, :clase, :tipo, :nombre, :ruta, :mime, :tamano, :descripcion, :usuario)',
            [
                ':equipo'      => $d['equipo_id'],
                ':mant'        => $d['mantenimiento_id'] ?? null,
                ':asig'        => $d['asignacion_id'] ?? null,
                ':mov'         => $d['movimiento_id'] ?? null,
                ':clase'       => $d['clase'],
                ':tipo'        => $d['tipo'],
                ':nombre'      => $d['nombre_original'],
                ':ruta'        => $d['ruta_archivo'],
                ':mime'        => $d['mime_type'],
                ':tamano'      => $d['tamano_bytes'],
                ':descripcion' => $d['descripcion'] ?? null,
                ':usuario'     => $d['subido_por'],
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    public function porId(int $id): ?array
    {
        return $this->fetchOne('SELECT ' . self::SELECCION . ' ' . self::DESDE . ' WHERE a.id = :id', [':id' => $id]);
    }

    /** Archivos de varios mantenimientos agrupados: [mantenimiento_id => filas]. */
    public function deMantenimientos(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) {
            return [];
        }
        $in    = implode(',', array_fill(0, count($ids), '?'));
        $filas = $this->fetchAll(
            'SELECT ' . self::SELECCION . ' ' . self::DESDE . " WHERE a.mantenimiento_id IN ($in) ORDER BY a.id",
            $ids
        );

        $grupos = [];
        foreach ($filas as $f) {
            $grupos[(int) $f['mantenimiento_id']][] = $f;
        }
        return $grupos;
    }

    /** @param string $columna asignacion_id | movimiento_id */
    public function de(string $columna, int $id): array
    {
        if (!in_array($columna, ['asignacion_id', 'movimiento_id'], true)) {
            throw new InvalidArgumentException("Columna no válida: $columna");
        }
        return $this->fetchAll(
            'SELECT ' . self::SELECCION . ' ' . self::DESDE . " WHERE a.$columna = :id ORDER BY a.creado_en DESC, a.id DESC",
            [':id' => $id]
        );
    }

    public function eliminar(int $id): void
    {
        $this->execute('DELETE FROM archivos WHERE id = :id', [':id' => $id]);
    }
}
