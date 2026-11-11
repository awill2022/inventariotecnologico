<?php
/**
 * Tabla de rutas: método, ruta, [Controlador, acción], roles permitidos.
 * Roles vacíos = ruta pública. ['*'] = cualquier usuario autenticado.
 * Los parámetros van entre llaves ({id} solo acepta números) y llegan a la acción como argumentos.
 *
 * @var Router $router
 */

// Autenticación
$router->get('/',        [AuthController::class, 'home']);
$router->get('/login',   [AuthController::class, 'showLogin']);
$router->post('/login',  [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout'], ['*']);

// Docente (rol: consulta)
$router->get('/docente',           [DocenteController::class, 'inicio'],   ['consulta']);
$router->post('/docente/reportar', [DocenteController::class, 'reportar'], ['consulta']);

// Panel (administrador, inventario, técnico)
$panel = ['administrador', 'inventario', 'tecnico'];
$admin = ['administrador'];

$router->get('/panel', [PanelController::class, 'inicio'], $panel);

// Catálogos: todos los roles del panel los consultan; solo el administrador los modifica
$catalogos = [
    '/categorias'  => CategoriaController::class,
    '/sedes'       => SedeController::class,
    '/areas'       => AreaController::class,
    '/ubicaciones' => UbicacionController::class,
];
foreach ($catalogos as $ruta => $controlador) {
    $router->get($ruta,                  [$controlador, 'index'],      $panel);
    $router->get("$ruta/nueva",          [$controlador, 'nueva'],      $admin);
    $router->post($ruta,                 [$controlador, 'guardar'],    $admin);
    $router->get("$ruta/{id}/editar",    [$controlador, 'editar'],     $admin);
    $router->post("$ruta/{id}",          [$controlador, 'actualizar'], $admin);
    $router->post("$ruta/{id}/estado",   [$controlador, 'estado'],     $admin);
}

// Mi perfil: todos los roles (con clave temporal, es lo único disponible)
$router->get('/perfil',        [PerfilController::class, 'inicio'],      ['*']);
$router->post('/perfil/clave', [PerfilController::class, 'cambiarClave'], ['*']);

// Personas: el panel las consulta; administrador e inventario las gestionan
$gestionPersonas = ['administrador', 'inventario'];
$router->get('/personas',                [PersonaController::class, 'index'],      $panel);
$router->get('/personas/nueva',          [PersonaController::class, 'nueva'],      $gestionPersonas);
$router->post('/personas',               [PersonaController::class, 'guardar'],    $gestionPersonas);
$router->get('/personas/{id}/editar',    [PersonaController::class, 'editar'],     $gestionPersonas);
$router->post('/personas/{id}',          [PersonaController::class, 'actualizar'], $gestionPersonas);
$router->post('/personas/{id}/estado',   [PersonaController::class, 'estado'],     $gestionPersonas);

// Usuarios: solo el administrador
$router->get('/usuarios',                [UsuarioController::class, 'index'],       $admin);
$router->get('/usuarios/nueva',          [UsuarioController::class, 'nueva'],       $admin);
$router->post('/usuarios',               [UsuarioController::class, 'guardar'],     $admin);
$router->get('/usuarios/{id}/editar',    [UsuarioController::class, 'editar'],      $admin);
$router->post('/usuarios/{id}',          [UsuarioController::class, 'actualizar'],  $admin);
$router->post('/usuarios/{id}/estado',   [UsuarioController::class, 'estado'],      $admin);
$router->get('/usuarios/{id}/clave',     [UsuarioController::class, 'clave'],       $admin);
$router->post('/usuarios/{id}/clave',    [UsuarioController::class, 'restablecer'], $admin);
