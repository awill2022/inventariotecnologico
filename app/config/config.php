<?php
/**
 * Configuración general de la aplicación.
 * Ajusta estos valores según tu entorno local (XAMPP).
 */
return [
    'app' => [
        'name'     => 'Inventario Tecnológico · ISTSGA',
        'timezone' => 'America/Guayaquil',
        'debug'    => true, // true solo para desarrollar en local: muestra el detalle de los errores
        'escudo'   => 'https://academico.sangabrielriobamba.edu.ec/assets/brand/SGlogo.svg',
        // Encabezado de las actas de entrega
        'institucion' => 'Instituto Superior Tecnológico San Gabriel',
        'ciudad'      => 'Riobamba',
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

    // Archivos subidos (fotos de fallas, actas firmadas). La carpeta queda fuera de public/
    // y además tiene un .htaccess que niega el acceso directo: solo se descargan por /archivos/{id}.
    'archivos' => [
        'carpeta'      => dirname(__DIR__, 2) . '/storage/archivos',
        'max_foto'     => 5 * 1024 * 1024,   // 5 MB por foto
        'max_documento'=> 10 * 1024 * 1024,  // 10 MB por PDF
        'fotos_reporte'=> 3,                 // fotos por reporte de falla
    ],

    // Página de inicio según el rol (enum usuarios.rol)
    'home_por_rol' => [
        'consulta'      => '/docente',
        // Sus módulos llegan en otros sprints: por ahora ven una página "en construcción".
        'administrador' => '/panel',
        'inventario'    => '/panel',
        'tecnico'       => '/panel',
    ],
];
