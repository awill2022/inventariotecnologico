<?php
/**
 * Catálogo de áreas (departamentos, carreras, unidades), con jerarquía.
 */
final class AreaController extends CatalogoController
{
    protected function modelo(): Catalogo
    {
        return new Area();
    }

    protected function def(): array
    {
        return [
            'ruta'        => '/areas',
            'entidad'     => 'areas',
            'titulo'      => 'Áreas',
            'descripcion' => 'Departamentos, carreras y unidades a los que pertenecen personas y equipos.',
            'singular'    => 'área',
            'articulo'    => 'el',
            'nuevo'       => 'Nueva área',
            'vacio'       => 'Todavía no hay áreas registradas.',
            'columnas'    => [
                ['clave' => 'nombre', 'titulo' => 'Nombre'],
                ['clave' => 'tipo',   'titulo' => 'Tipo', 'mapa' => Area::TIPOS],
                ['clave' => 'padre',  'titulo' => 'Depende de'],
            ],
        ];
    }

    protected function campos(?int $id): array
    {
        $modelo  = new Area();
        $actual  = $id ? $modelo->porId($id) : null;
        $padres  = $modelo->opciones($actual ? (int) $actual['area_padre_id'] ?: null : null);

        // Un área no puede depender de sí misma ni de las que cuelgan de ella
        if ($id) {
            foreach ([$id, ...$modelo->descendientes($id)] as $excluida) {
                unset($padres[$excluida]);
            }
        }

        return [
            ['nombre' => 'nombre', 'etiqueta' => 'Nombre', 'tipo' => 'text', 'requerido' => true, 'min' => 3, 'max' => 150],
            ['nombre' => 'tipo', 'etiqueta' => 'Tipo', 'tipo' => 'select', 'requerido' => true, 'opciones' => Area::TIPOS],
            ['nombre' => 'area_padre_id', 'etiqueta' => 'Depende de', 'tipo' => 'select', 'entero' => true,
             'opciones' => $padres, 'vacia' => 'Ninguna (área principal)',
             'ayuda' => 'Elige el área superior si esta forma parte de otra.'],
        ];
    }

    protected function validarExtra(array $datos, ?int $id): array
    {
        if ($datos['nombre'] !== null && $this->modelo()->existe(
            ['nombre' => $datos['nombre'], 'area_padre_id' => $datos['area_padre_id']],
            $id
        )) {
            return ['nombre' => 'Ya existe un área con ese nombre en el mismo nivel.'];
        }
        return [];
    }
}
