<?php
/**
 * Configuración general de la aplicación.
 * Ajusta estos valores según tu entorno local (XAMPP).
 */
return [
    'app' => [
        'name'     => 'Inventario Tecnológico · ISTSGA',
        'timezone' => 'America/Guayaquil',
        'debug'    => true, // false en producción: oculta detalles de errores
    ],

    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3307,
        'name'    => 'inventario_tecnologico',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    'session' => [
        'name'     => 'ISTSGA_SESSID',
        'lifetime' => 0, // 0 = hasta cerrar el navegador
    ],

    // Página de inicio según el rol (enum usuarios.rol)
    'home_por_rol' => [
        'consulta' => '/docente',
        // 'administrador' => '/admin',       // otros sprints
        // 'inventario'    => '/inventario',
        // 'tecnico'       => '/tecnico',
    ],
];
