<?php
/**
 * Catálogo de ubicaciones físicas dentro de cada sede.
 */
final class UbicacionController extends CatalogoController
{
    protected function modelo(): Catalogo
    {
        return new Ubicacion();
    }

    protected function def(): array
    {
        return [
            'ruta'        => '/ubicaciones',
            'entidad'     => 'ubicaciones',
            'titulo'      => 'Ubicaciones',
            'descripcion' => 'Aulas, laboratorios, oficinas y bodegas donde se encuentran los equipos.',
            'singular'    => 'ubicación',
            'nuevo'       => 'Nueva ubicación',
            'vacio'       => 'Todavía no hay ubicaciones registradas.',
            'columnas'    => [
                ['clave' => 'nombre', 'titulo' => 'Nombre'],
                ['clave' => 'tipo',   'titulo' => 'Tipo', 'mapa' => Ubicacion::TIPOS],
                ['clave' => 'sede',   'titulo' => 'Sede'],
                ['clave' => 'area',   'titulo' => 'Área'],
                ['clave' => 'lugar',  'titulo' => 'Edificio y piso'],
            ],
        ];
    }

    protected function campos(?int $id): array
    {
        $actual = $id ? (new Ubicacion())->porId($id) : null;

        return [
            ['nombre' => 'nombre', 'etiqueta' => 'Nombre', 'tipo' => 'text', 'requerido' => true, 'min' => 3, 'max' => 150,
             'ayuda' => 'Por ejemplo: Laboratorio de Cómputo 2.'],
            ['nombre' => 'tipo', 'etiqueta' => 'Tipo', 'tipo' => 'select', 'requerido' => true, 'opciones' => Ubicacion::TIPOS],
            ['nombre' => 'sede_id', 'etiqueta' => 'Sede', 'tipo' => 'select', 'requerido' => true, 'entero' => true,
             'opciones' => (new Sede())->opciones($actual ? (int) $actual['sede_id'] : null)],
            ['nombre' => 'area_id', 'etiqueta' => 'Área', 'tipo' => 'select', 'entero' => true,
             'opciones' => (new Area())->opciones($actual ? (int) $actual['area_id'] ?: null : null),
             'vacia' => 'Sin área (uso común)'],
            ['nombre' => 'edificio', 'etiqueta' => 'Edificio o bloque', 'tipo' => 'text', 'max' => 100],
            ['nombre' => 'piso', 'etiqueta' => 'Piso', 'tipo' => 'text', 'max' => 30,
             'ayuda' => 'Por ejemplo: 2 o Planta baja.'],
            ['nombre' => 'descripcion', 'etiqueta' => 'Descripción', 'tipo' => 'textarea', 'max' => 255],
        ];
    }

    protected function validarExtra(array $datos, ?int $id): array
    {
        if ($datos['nombre'] !== null && $datos['sede_id'] !== null && $this->modelo()->existe(
            ['nombre' => $datos['nombre'], 'sede_id' => $datos['sede_id']],
            $id
        )) {
            return ['nombre' => 'Ya existe una ubicación con ese nombre en la sede elegida.'];
        }
        return [];
    }
}
