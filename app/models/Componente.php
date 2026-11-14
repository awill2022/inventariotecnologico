<?php
/**
 * Tabla `componentes`: piezas instaladas en un equipo (RAM, disco, batería...).
 * No se borran: al retirarse quedan con fecha_retiro y forman el historial del equipo.
 * Si la pieza se instala o se retira durante un mantenimiento en proceso, queda enlazada a él
 * (mantenimiento_instalacion_id / mantenimiento_retiro_id).
 */
final class Componente extends Model
{
    private const COLUMNAS = ['tipo', 'marca', 'modelo', 'numero_serie', 'capacidad', 'fecha_instalacion', 'observaciones'];

    /** Componentes del equipo: primero los instalados, luego los retirados (recientes primero). */
    public function deEquipo(int $equipoId): array
    {
        return $this->fetchAll(
            'SELECT * FROM componentes WHERE equipo_id = :id
              ORDER BY fecha_retiro IS NOT NULL, fecha_retiro DESC, tipo, fecha_instalacion DESC, id',
            [':id' => $equipoId]
        );
    }

    /** Componente solo si pertenece a ese equipo (evita editar piezas de otro equipo cambiando la URL). */
    public function deEquipoPorId(int $equipoId, int $id): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM componentes WHERE id = :id AND equipo_id = :equipo',
            [':id' => $id, ':equipo' => $equipoId]
        );
    }

    /** Tipos ya usados, para sugerirlos al escribir. */
    public function tiposConocidos(): array
    {
        return array_column(
            $this->fetchAll('SELECT DISTINCT tipo FROM componentes ORDER BY tipo LIMIT 100'),
            'tipo'
        );
    }

    /** Componentes instalados o retirados durante un mantenimiento. */
    public function deMantenimiento(int $mantenimientoId): array
    {
        return $this->fetchAll(
            'SELECT * FROM componentes
              WHERE mantenimiento_instalacion_id = :m1 OR mantenimiento_retiro_id = :m2
           ORDER BY id',
            [':m1' => $mantenimientoId, ':m2' => $mantenimientoId]
        );
    }

    public function crear(int $equipoId, array $datos, ?int $mantenimientoId = null): int
    {
        $datos    = ['equipo_id' => $equipoId] + array_intersect_key($datos, array_flip(self::COLUMNAS))
                  + ['mantenimiento_instalacion_id' => $mantenimientoId];
        $columnas = array_keys($datos);
        $marcas   = array_map(fn ($c) => ":$c", $columnas);
        $this->execute(
            'INSERT INTO componentes (' . implode(', ', $columnas) . ') VALUES (' . implode(', ', $marcas) . ')',
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
        $this->execute('UPDATE componentes SET ' . implode(', ', $sets) . ' WHERE id = :id', $params);
    }

    public function retirar(int $id, string $fecha, ?string $observaciones, ?int $mantenimientoId = null): void
    {
        $this->execute(
            'UPDATE componentes SET fecha_retiro = :fecha, observaciones = :obs, mantenimiento_retiro_id = :mant WHERE id = :id',
            [':fecha' => $fecha, ':obs' => $observaciones, ':mant' => $mantenimientoId, ':id' => $id]
        );
    }
}
