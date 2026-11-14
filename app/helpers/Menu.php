<?php
/**
 * Menú lateral del panel (administrador, inventario y técnico).
 * Cada módulo nuevo se agrega aquí con los roles que pueden verlo.
 */
final class Menu
{
    private const PANEL = ['administrador', 'inventario', 'tecnico'];
    /** El técnico solo da soporte: no ve catálogos ni inventario. */
    private const INVENTARIO = ['administrador', 'inventario'];

    /** grupo (null = sin título), texto, ruta, icono (id del sprite sin "i-"), roles. */
    private const ITEMS = [
        ['grupo' => null,        'texto' => 'Inicio',      'ruta' => '/panel',       'icono' => 'home',     'roles' => self::PANEL],
        ['grupo' => 'Catálogos', 'texto' => 'Categorías',  'ruta' => '/categorias',  'icono' => 'tag',      'roles' => self::INVENTARIO],
        ['grupo' => 'Catálogos', 'texto' => 'Sedes',       'ruta' => '/sedes',       'icono' => 'building', 'roles' => self::INVENTARIO],
        ['grupo' => 'Catálogos', 'texto' => 'Áreas',       'ruta' => '/areas',       'icono' => 'network',  'roles' => self::INVENTARIO],
        ['grupo' => 'Catálogos', 'texto' => 'Ubicaciones', 'ruta' => '/ubicaciones', 'icono' => 'pin',      'roles' => self::INVENTARIO],
        ['grupo' => 'Inventario', 'texto' => 'Equipos',    'ruta' => '/equipos',     'icono' => 'laptop',   'roles' => self::INVENTARIO],
        ['grupo' => 'Inventario', 'texto' => 'Asignaciones', 'ruta' => '/asignaciones', 'icono' => 'user-check', 'roles' => self::INVENTARIO],
        ['grupo' => 'Inventario', 'texto' => 'Movimientos', 'ruta' => '/movimientos', 'icono' => 'move',    'roles' => self::INVENTARIO],
        ['grupo' => 'Soporte técnico', 'texto' => 'Mantenimientos', 'ruta' => '/mantenimientos', 'icono' => 'wrench',  'roles' => self::PANEL],
        ['grupo' => 'Soporte técnico', 'texto' => 'Repuestos',      'ruta' => '/repuestos',      'icono' => 'package', 'roles' => self::PANEL],
        ['grupo' => 'Personas',  'texto' => 'Personas',    'ruta' => '/personas',    'icono' => 'users',    'roles' => self::PANEL],
        ['grupo' => 'Personas',  'texto' => 'Usuarios',    'ruta' => '/usuarios',    'icono' => 'shield',   'roles' => ['administrador']],
        ['grupo' => 'Personas',  'texto' => 'Auditoría',   'ruta' => '/auditoria',   'icono' => 'history',  'roles' => ['administrador']],
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
