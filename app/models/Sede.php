<?php
/**
 * Tabla `sedes`: campus o locales del instituto.
 */
final class Sede extends Catalogo
{
    protected string $tabla     = 'sedes';
    protected array  $columnas  = ['nombre', 'direccion'];
    protected array  $buscables = ['t.nombre', 't.direccion'];

    protected function seleccion(): string
    {
        return 't.*, (SELECT COUNT(*) FROM ubicaciones u
                       WHERE u.sede_id = t.id AND u.activo = 1) AS ubicaciones';
    }

    public function dependencias(int $id): array
    {
        $n = $this->contar('SELECT COUNT(*) AS n FROM ubicaciones WHERE sede_id = :id AND activo = 1', $id);
        return $n ? [Formato::plural($n, 'ubicación activa', 'ubicaciones activas')] : [];
    }
}
