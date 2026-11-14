<?php
/**
 * Tabla `repuestos` (+ stock de la migración 003): piezas e insumos que el técnico usa
 * en los mantenimientos. No se borran: se desactivan con activo = 0.
 *
 * El stock solo cambia por tres vías, siempre dentro de una transacción y con auditoría:
 * stock inicial al crear, «Agregar stock» (ingreso) y su uso en un mantenimiento (descontar / reponer).
 */
final class Repuesto extends Catalogo
{
    protected string $tabla     = 'repuestos';
    protected array  $columnas  = ['codigo', 'nombre', 'descripcion', 'unidad', 'stock', 'stock_minimo', 'costo_referencial'];
    protected array  $buscables = ['t.codigo', 't.nombre', 't.descripcion'];
    protected string $orden     = 't.nombre';

    protected function seleccion(): string
    {
        // bajo_minimo: 1 cuando ya toca reponer (solo si se fijó un mínimo o se agotó)
        return 't.*, (t.stock <= t.stock_minimo AND (t.stock_minimo > 0 OR t.stock = 0)) AS bajo_minimo';
    }

    /** Sin stock inicial ni mínimo escritos, empiezan en 0 (las columnas no admiten NULL). */
    public function crear(array $datos): int
    {
        $datos['stock']        ??= '0';
        $datos['stock_minimo'] ??= '0';
        return parent::crear($datos);
    }

    /** El stock no se edita desde el formulario: solo cambia con ingresos y mantenimientos. */
    public function actualizar(int $id, array $datos): void
    {
        unset($datos['stock']);
        if (array_key_exists('stock_minimo', $datos)) {
            $datos['stock_minimo'] ??= '0';
        }
        parent::actualizar($id, $datos);
    }

    /**
     * Repuestos activos para agregarlos a un mantenimiento, con su stock a la vista.
     * @return array [id => "Nombre (código) · hay 4 unidades"]
     */
    public function opcionesConStock(): array
    {
        $filas = $this->fetchAll(
            "SELECT id, nombre, codigo, unidad, stock FROM repuestos WHERE activo = 1 ORDER BY nombre"
        );
        $opciones = [];
        foreach ($filas as $f) {
            $opciones[$f['id']] = $f['nombre'] . ($f['codigo'] ? " ({$f['codigo']})" : '')
                                . ' · hay ' . Formato::conUnidad($f['stock'], $f['unidad']);
        }
        return $opciones;
    }

    /** Costos de referencia [id => costo] de los activos, para proponerlos en el formulario. */
    public function costosReferenciales(): array
    {
        $filas = $this->fetchAll('SELECT id, costo_referencial FROM repuestos WHERE activo = 1 AND costo_referencial IS NOT NULL');
        return array_column($filas, 'costo_referencial', 'id');
    }

    /**
     * Resta del stock solo si alcanza y el repuesto sigue activo. Devuelve false si no alcanzó
     * (la condición en el mismo UPDATE evita que dos técnicos dejen el stock en negativo).
     */
    public function descontar(int $id, string $cantidad): bool
    {
        return $this->execute(
            'UPDATE repuestos SET stock = stock - :c1 WHERE id = :id AND activo = 1 AND stock >= :c2',
            [':c1' => $cantidad, ':c2' => $cantidad, ':id' => $id]
        ) === 1;
    }

    /** Suma al stock: ingreso de bodega o repuesto que se quitó de un mantenimiento. */
    public function reponer(int $id, string $cantidad): void
    {
        $this->execute('UPDATE repuestos SET stock = stock + :c WHERE id = :id', [':c' => $cantidad, ':id' => $id]);
    }

    /** Fila bloqueada hasta el fin de la transacción, para auditar el stock antes y después. */
    public function bloquear(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM repuestos WHERE id = :id FOR UPDATE', [':id' => $id]);
    }

    /** Actualiza el costo de referencia tras una compra. */
    public function fijarCosto(int $id, string $costo): void
    {
        $this->execute('UPDATE repuestos SET costo_referencial = :c WHERE id = :id', [':c' => $costo, ':id' => $id]);
    }

    /** Repuestos activos que ya llegaron a su mínimo (o se agotaron). */
    public function contarBajoMinimo(): int
    {
        return (int) $this->fetchOne(
            'SELECT COUNT(*) AS n FROM repuestos WHERE activo = 1 AND stock <= stock_minimo AND (stock_minimo > 0 OR stock = 0)'
        )['n'];
    }

    public function dependencias(int $id): array
    {
        $n = $this->contar(
            "SELECT COUNT(*) AS n FROM mantenimiento_repuestos mr
               JOIN mantenimientos m ON m.id = mr.mantenimiento_id
              WHERE mr.repuesto_id = :id AND m.estado = 'en_proceso'",
            $id
        );
        return $n ? [Formato::plural($n, 'mantenimiento en proceso que lo usa', 'mantenimientos en proceso que lo usan')] : [];
    }
}
