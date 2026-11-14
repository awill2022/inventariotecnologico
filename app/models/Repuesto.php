<?php
/**
 * Tablas `repuestos` y `mantenimiento_repuestos`.
 * Funciona como catálogo del panel y descuenta existencias al usar piezas en mantenimientos.
 */
final class Repuesto extends Catalogo
{
    public const UNIDADES = [
        'unidad'     => 'Unidad',
        'kit'        => 'Kit',
        'aplicacion' => 'Aplicación',
        'metro'      => 'Metro',
        'par'        => 'Par',
    ];

    protected string $tabla     = 'repuestos';
    protected array  $columnas  = ['codigo', 'nombre', 'descripcion', 'unidad', 'stock', 'stock_minimo'];
    protected array  $buscables = ['t.codigo', 't.nombre', 't.descripcion'];
    protected string $orden     = 't.nombre';

    protected function seleccion(): string
    {
        return "t.*, IF(t.stock <= t.stock_minimo, 'Bajo stock', 'OK') AS alerta_stock";
    }

    /** Lista de repuestos activos para el selector del formulario técnico. */
    public function activos(): array
    {
        return $this->fetchAll('SELECT * FROM repuestos WHERE activo = 1 ORDER BY nombre');
    }

    /** Repuestos utilizados en los mantenimientos de un equipo, agrupados por mantenimiento_id. */
    public function deMantenimientos(array $mantenimientoIds): array
    {
        if (!$mantenimientoIds) {
            return [];
        }
        $in   = implode(',', array_fill(0, count($mantenimientoIds), '?'));
        $rows = $this->fetchAll(
            "SELECT mr.*, r.codigo, r.nombre, r.unidad
               FROM mantenimiento_repuestos mr
               JOIN repuestos r ON r.id = mr.repuesto_id
              WHERE mr.mantenimiento_id IN ($in)
           ORDER BY mr.id",
            array_values($mantenimientoIds)
        );

        $porMant = [];
        foreach ($rows as $r) {
            $porMant[(int) $r['mantenimiento_id']][] = $r;
        }
        return $porMant;
    }

    /** Busca o crea un repuesto por nombre si el técnico escribió uno nuevo en el mantenimiento. */
    public function obtenerOCrearPorNombre(string $nombre): int
    {
        $existente = $this->fetchOne(
            'SELECT id FROM repuestos WHERE LOWER(nombre) = LOWER(:nombre) LIMIT 1',
            [':nombre' => $nombre]
        );
        if ($existente) {
            return (int) $existente['id'];
        }
        $codigo = 'REP-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $this->execute(
            'INSERT INTO repuestos (codigo, nombre, unidad, stock, stock_minimo, activo)
             VALUES (:codigo, :nombre, :unidad, 0, 1, 1)',
            [':codigo' => $codigo, ':nombre' => $nombre, ':unidad' => 'unidad']
        );
        return (int) $this->db->lastInsertId();
    }

    /** Registra el repuesto usado en el mantenimiento y descuenta del stock disponible. */
    public function registrarUso(int $mantenimientoId, int $repuestoId, float $cantidad, float $costoUnitario, ?string $detalle): void
    {
        $cant = max(0.01, $cantidad);
        $this->execute(
            'INSERT INTO mantenimiento_repuestos (mantenimiento_id, repuesto_id, cantidad, costo_unitario, detalle)
             VALUES (:mant, :rep, :cant, :costo, :detalle)',
            [
                ':mant'    => $mantenimientoId,
                ':rep'     => $repuestoId,
                ':cant'    => $cant,
                ':costo'   => max(0, $costoUnitario),
                ':detalle' => $detalle ?: null,
            ]
        );

        $this->execute(
            'UPDATE repuestos SET stock = GREATEST(0, stock - :cant) WHERE id = :id',
            [':cant' => $cant, ':id' => $repuestoId]
        );
    }

    public function dependencias(int $id): array
    {
        $n = $this->contar('SELECT COUNT(*) AS n FROM mantenimiento_repuestos WHERE repuesto_id = :id', $id);
        return $n ? [Formato::plural($n, 'mantenimiento registrado', 'mantenimientos registrados')] : [];
    }
}