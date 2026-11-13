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
            'buscar'      => 'Buscar por nombre o prefijo',
            'vacio'       => 'Todavía no hay categorías registradas.',
            'columnas'    => [
                ['clave' => 'nombre',      'titulo' => 'Nombre'],
                ['clave' => 'prefijo',     'titulo' => 'Prefijo'],
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
            ['nombre' => 'prefijo', 'etiqueta' => 'Prefijo del código', 'tipo' => 'text', 'requerido' => true,
             'min' => 2, 'max' => 6, 'formato' => 'prefijo', 'autocomplete' => 'off',
             'ayuda' => 'De 2 a 6 letras o números. Con él se propone el código de los equipos: EQ-NB-0001.'],
            ['nombre' => 'descripcion', 'etiqueta' => 'Descripción', 'tipo' => 'textarea', 'max' => 255],
        ];
    }

    protected function validarExtra(array $datos, ?int $id): array
    {
        $errores = [];
        if ($datos['nombre'] !== null && $this->modelo()->existe(['nombre' => $datos['nombre']], $id)) {
            $errores['nombre'] = 'Ya existe una categoría con ese nombre.';
        }
        if ($datos['prefijo'] !== null && $this->modelo()->existe(['prefijo' => $datos['prefijo']], $id)) {
            $errores['prefijo'] = 'Otra categoría ya usa ese prefijo.';
        }
        return $errores;
    }
}
