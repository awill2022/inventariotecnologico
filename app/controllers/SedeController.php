<?php
/**
 * Catálogo de sedes del instituto.
 */
final class SedeController extends CatalogoController
{
    protected function modelo(): Catalogo
    {
        return new Sede();
    }

    protected function def(): array
    {
        return [
            'ruta'        => '/sedes',
            'entidad'     => 'sedes',
            'titulo'      => 'Sedes',
            'descripcion' => 'Campus y locales donde el instituto tiene equipos.',
            'singular'    => 'sede',
            'nuevo'       => 'Nueva sede',
            'vacio'       => 'Todavía no hay sedes registradas.',
            'columnas'    => [
                ['clave' => 'nombre',      'titulo' => 'Nombre'],
                ['clave' => 'direccion',   'titulo' => 'Dirección'],
                ['clave' => 'ubicaciones', 'titulo' => 'Ubicaciones', 'numero' => true],
            ],
        ];
    }

    protected function campos(?int $id): array
    {
        return [
            ['nombre' => 'nombre', 'etiqueta' => 'Nombre', 'tipo' => 'text', 'requerido' => true, 'min' => 3, 'max' => 150,
             'ayuda' => 'Por ejemplo: Matriz Riobamba.'],
            ['nombre' => 'direccion', 'etiqueta' => 'Dirección', 'tipo' => 'text', 'max' => 255],
        ];
    }

    protected function validarExtra(array $datos, ?int $id): array
    {
        if ($datos['nombre'] !== null && $this->modelo()->existe(['nombre' => $datos['nombre']], $id)) {
            return ['nombre' => 'Ya existe una sede con ese nombre.'];
        }
        return [];
    }
}
