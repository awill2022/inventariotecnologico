<?php
/**
 * Tabla de rutas: método, ruta, [Controlador, acción], roles permitidos.
 * Roles vacíos = ruta pública. ['*'] = cualquier usuario autenticado.
 *
 * @var Router $router
 */

// Autenticación
$router->get('/',        [AuthController::class, 'home']);
$router->get('/login',   [AuthController::class, 'showLogin']);
$router->post('/login',  [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout'], ['*']);

// Docente (rol: consulta)
$router->get('/docente', [DocenteController::class, 'inicio'], ['consulta']);
