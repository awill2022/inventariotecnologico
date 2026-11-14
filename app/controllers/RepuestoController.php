<?php
/**
 * Catálogo de repuestos e insumos técnicos con control de stock.
 * Ver: todos los roles del panel. Escribir: administrador, técnico e inventario.
 */
final class RepuestoController extends CatalogoController
{
    protected function modelo(): Catalogo
    {
        return new Repuesto();
    }

    protected function rolesEditar(): array
    {
        return ['administrador', 'tecnico', 'inventario'];
    }

    protected function def(): array
    {
        return [
            'ruta'        => '/repuestos',
            'entidad'     => 'repuestos',
            'titulo'      => 'Repuestos e insumos',
            'descripcion' => 'Piezas, componentes y consumibles para el mantenimiento de equipos.',
            'singular'    => 'repuesto',
            'articulo'    => 'el',
            'nuevo'       => 'Nuevo repuesto',
            'buscar'      => 'Buscar por código o nombre',
            'vacio'       => 'Todavía no hay repuestos registrados.',
            'columnas'    => [
                ['clave' => 'nombre',       'titulo' => 'Nombre'],
                ['clave' => 'codigo',       'titulo' => 'Código'],
                ['clave' => 'unidad',       'titulo' => 'Unidad', 'mapa' => Repuesto::UNIDADES],
                ['clave' => 'stock',        'titulo' => 'Stock actual', 'numero' => true],
                ['clave' => 'stock_minimo', 'titulo' => 'Stock mín.',   'numero' => true],
            ],
        ];
    }

    protected function campos(?int $id): array
    {
        return [
            ['nombre' => 'codigo', 'etiqueta' => 'Código', 'tipo' => 'text', 'requerido' => true,
             'min' => 3, 'max' => 80, 'ayuda' => 'Por ejemplo: REP-SSD-512.'],
            ['nombre' => 'nombre', 'etiqueta' => 'Nombre del repuesto', 'tipo' => 'text', 'requerido' => true,
             'min' => 3, 'max' => 150],
            ['nombre' => 'unidad', 'etiqueta' => 'Unidad de medida', 'tipo' => 'select', 'requerido' => true,
             'opciones' => Repuesto::UNIDADES],
            ['nombre' => 'stock', 'etiqueta' => 'Stock disponible', 'tipo' => 'text', 'requerido' => true,
             'inputmode' => 'decimal', 'ayuda' => 'Cantidad actual en bodega técnica (ej. 10).'],
            ['nombre' => 'stock_minimo', 'etiqueta' => 'Stock mínimo de alerta', 'tipo' => 'text', 'requerido' => true,
             'inputmode' => 'decimal', 'ayuda' => 'Cantidad mínima antes de requerir reposición (ej. 2).'],
            ['nombre' => 'descripcion', 'etiqueta' => 'Descripción', 'tipo' => 'textarea', 'max' => 255],
        ];
    }

    protected function validarExtra(array $datos, ?int $id): array
    {
        $errores = [];
        if ($datos['codigo'] !== null && $this->modelo()->existe(['codigo' => $datos['codigo']], $id)) {
            $errores['codigo'] = 'Ya existe otro repuesto con ese código.';
        }
        foreach (['stock', 'stock_minimo'] as $campo) {
            if ($datos[$campo] !== null && (!is_numeric($datos[$campo]) || (float) $datos[$campo] < 0)) {
                $errores[$campo] = 'Escribe un número mayor o igual a 0.';
            }
        }
        return $errores;
    }
}