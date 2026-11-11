<?php
/**
 * Catálogo de categorías de equipo.
 */
final class CategoriaController extends CatalogoController
{
    protected function modelo(): Catalogo
    {
        return new Categoria();
    }

    protected function def(): array
    {
        return [
            'ruta'        => '/categorias',
            'entidad'     => 'categorias',
            'titulo'      => 'Categorías',
            'descripcion' => 'Clases de equipo con las que se ordena el inventario.',
            'singular'    => 'categoría',
            'nuevo'       => 'Nueva categoría',
            'vacio'       => 'Todavía no hay categorías registradas.',
            'columnas'    => [
                ['clave' => 'nombre',      'titulo' => 'Nombre'],
                ['clave' => 'descripcion', 'titulo' => 'Descripción'],
                ['clave' => 'equipos',     'titulo' => 'Equipos', 'numero' => true],
            ],
        ];
    }

    protected function campos(?int $id): array
    {
        return [
            ['nombre' => 'nombre', 'etiqueta' => 'Nombre', 'tipo' => 'text', 'requerido' => true, 'min' => 3, 'max' => 100,
             'ayuda' => 'Por ejemplo: Portátiles, Proyectores, Impresoras.'],
            ['nombre' => 'descripcion', 'etiqueta' => 'Descripción', 'tipo' => 'textarea', 'max' => 255],
        ];
    }

    protected function validarExtra(array $datos, ?int $id): array
    {
        if ($datos['nombre'] !== null && $this->modelo()->existe(['nombre' => $datos['nombre']], $id)) {
            return ['nombre' => 'Ya existe una categoría con ese nombre.'];
        }
        return [];
    }
}
