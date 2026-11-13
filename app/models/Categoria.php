<?php
/**
 * Tabla `categorias`: clases de equipo (computadores, impresoras, ...).
 */
final class Categoria extends Catalogo
{
    protected string $tabla     = 'categorias';
    protected array  $columnas  = ['nombre', 'prefijo', 'descripcion'];
    protected array  $buscables = ['t.nombre', 't.prefijo', 't.descripcion'];

    protected function seleccion(): string
    {
        return "t.*, (SELECT COUNT(*) FROM equipos e
                       WHERE e.categoria_id = t.id AND e.estado_inventario <> 'de_baja') AS equipos";
    }

    /** Prefijo de cada categoría [id => prefijo], para proponer el código de un equipo. */
    public function prefijos(): array
    {
        return array_column($this->fetchAll('SELECT id, prefijo FROM categorias WHERE prefijo IS NOT NULL'), 'prefijo', 'id');
    }

    public function dependencias(int $id): array
    {
        $n = $this->contar(
            "SELECT COUNT(*) AS n FROM equipos WHERE categoria_id = :id AND estado_inventario <> 'de_baja'",
            $id
        );
        return $n ? [Formato::plural($n, 'equipo vigente', 'equipos vigentes')] : [];
    }
}
