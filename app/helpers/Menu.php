<?php
/**
 * Menú lateral del panel (administrador, inventario y técnico).
 * Cada módulo nuevo se agrega aquí con los roles que pueden verlo.
 */
final class Menu
{
    private const PANEL = ['administrador', 'inventario', 'tecnico'];

    /** grupo (null = sin título), texto, ruta, icono (id del sprite sin "i-"), roles. */
    private const ITEMS = [
        ['grupo' => null,        'texto' => 'Inicio',      'ruta' => '/panel',       'icono' => 'home',     'roles' => self::PANEL],
        ['grupo' => 'Catálogos', 'texto' => 'Categorías',  'ruta' => '/categorias',  'icono' => 'tag',      'roles' => self::PANEL],
        ['grupo' => 'Catálogos', 'texto' => 'Sedes',       'ruta' => '/sedes',       'icono' => 'building', 'roles' => self::PANEL],
        ['grupo' => 'Catálogos', 'texto' => 'Áreas',       'ruta' => '/areas',       'icono' => 'network',  'roles' => self::PANEL],
        ['grupo' => 'Catálogos', 'texto' => 'Ubicaciones', 'ruta' => '/ubicaciones', 'icono' => 'pin',      'roles' => self::PANEL],
        ['grupo' => 'Personas',  'texto' => 'Personas',    'ruta' => '/personas',    'icono' => 'users',    'roles' => self::PANEL],
        ['grupo' => 'Personas',  'texto' => 'Usuarios',    'ruta' => '/usuarios',    'icono' => 'shield',   'roles' => ['administrador']],
        ['grupo' => 'Mi cuenta', 'texto' => 'Mi perfil',   'ruta' => '/perfil',      'icono' => 'user',     'roles' => self::PANEL],
    ];

    /**
     * Enlaces que ve un rol, agrupados: [['titulo' => ?string, 'items' => [...]], ...].
     * Cada item lleva 'actual' => true si corresponde a la página abierta.
     */
    public static function para(?string $rol, string $rutaActual): array
    {
        $grupos = [];
        foreach (self::ITEMS as $item) {
            if (!in_array($rol, $item['roles'], true)) {
                continue;
            }
            $item['actual'] = $rutaActual === $item['ruta'] || str_starts_with($rutaActual, $item['ruta'] . '/');
            $clave = $item['grupo'] ?? '';
            $grupos[$clave] ??= ['titulo' => $item['grupo'], 'items' => []];
            $grupos[$clave]['items'][] = $item;
        }
        return array_values($grupos);
    }
}
