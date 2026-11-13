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

// Catálogos: administrador e inventario los consultan (el técnico solo da soporte); solo el administrador los modifica
$inventario = ['administrador', 'inventario'];
$catalogos = [
    '/categorias'  => CategoriaController::class,
    '/sedes'       => SedeController::class,
    '/areas'       => AreaController::class,
    '/ubicaciones' => UbicacionController::class,
];
foreach ($catalogos as $ruta => $controlador) {
    $router->get($ruta,                  [$controlador, 'index'],      $inventario);
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
$router->get('/personas/{id}',           [PersonaController::class, 'ver'],        $panel);
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

// Equipos: administrador e inventario los listan y gestionan; el técnico solo abre la ficha (lectura) desde sus trabajos;
// solo el administrador revierte una baja
$router->get('/equipos',                         [EquipoController::class, 'index'],        EquipoController::LISTAR);
$router->get('/equipos/nuevo',                   [EquipoController::class, 'nuevo'],        EquipoController::GESTIONAR);
$router->post('/equipos',                        [EquipoController::class, 'guardar'],      EquipoController::GESTIONAR);
$router->get('/equipos/etiquetas',               [EquipoController::class, 'etiquetas'],    EquipoController::LISTAR);
$router->get('/equipos/{id}',                    [EquipoController::class, 'ver'],          EquipoController::VER);
$router->get('/equipos/{id}/editar',             [EquipoController::class, 'editar'],       EquipoController::GESTIONAR);
$router->post('/equipos/{id}',                   [EquipoController::class, 'actualizar'],   EquipoController::GESTIONAR);
$router->get('/equipos/{id}/baja',               [EquipoController::class, 'baja'],         EquipoController::GESTIONAR);
$router->post('/equipos/{id}/baja',              [EquipoController::class, 'darDeBaja'],    EquipoController::GESTIONAR);
$router->post('/equipos/{id}/revertir-baja',     [EquipoController::class, 'revertirBaja'], EquipoController::REVERTIR);

// Especificaciones: los tres roles del panel
$router->get('/equipos/{id}/especificaciones',   [EspecificacionController::class, 'editar'],  EquipoController::ESPECIFICAR);
$router->post('/equipos/{id}/especificaciones',  [EspecificacionController::class, 'guardar'], EquipoController::ESPECIFICAR);

// Componentes: administrador y técnico
$router->get('/equipos/{id}/componentes/nuevo',                    [ComponenteController::class, 'nuevo'],      EquipoController::COMPONENTES);
$router->post('/equipos/{id}/componentes',                         [ComponenteController::class, 'guardar'],    EquipoController::COMPONENTES);
$router->get('/equipos/{id}/componentes/{componente_id}/editar',   [ComponenteController::class, 'editar'],     EquipoController::COMPONENTES);
$router->post('/equipos/{id}/componentes/{componente_id}',         [ComponenteController::class, 'actualizar'], EquipoController::COMPONENTES);
$router->get('/equipos/{id}/componentes/{componente_id}/retirar',  [ComponenteController::class, 'retiro'],     EquipoController::COMPONENTES);
$router->post('/equipos/{id}/componentes/{componente_id}/retirar', [ComponenteController::class, 'retirar'],    EquipoController::COMPONENTES);

// Asignaciones (entregas y devoluciones): administrador e inventario las consultan y las registran
$router->get('/asignaciones',                     [AsignacionController::class, 'index'],      AsignacionController::VER);
$router->get('/asignaciones/nueva',               [AsignacionController::class, 'nueva'],      AsignacionController::GESTIONAR);
$router->post('/asignaciones',                    [AsignacionController::class, 'guardar'],    AsignacionController::GESTIONAR);
$router->get('/asignaciones/{id}/devolucion',     [AsignacionController::class, 'devolucion'], AsignacionController::GESTIONAR);
$router->post('/asignaciones/{id}/devolucion',    [AsignacionController::class, 'devolver'],   AsignacionController::GESTIONAR);
$router->get('/asignaciones/{id}/acta',           [AsignacionController::class, 'acta'],       AsignacionController::VER);

// Movimientos (traslados): administrador e inventario los consultan y los registran
$router->get('/movimientos',        [MovimientoController::class, 'index'],   MovimientoController::VER);
$router->get('/movimientos/nuevo',  [MovimientoController::class, 'nuevo'],   MovimientoController::GESTIONAR);
$router->post('/movimientos',       [MovimientoController::class, 'guardar'], MovimientoController::GESTIONAR);

// Mantenimientos: el panel los consulta; administrador y técnico los gestionan; solo el administrador asigna técnico
$router->get('/mantenimientos',                                [MantenimientoController::class, 'index'],          MantenimientoController::VER);
$router->get('/mantenimientos/nuevo',                          [MantenimientoController::class, 'nuevo'],          MantenimientoController::GESTIONAR);
$router->post('/mantenimientos',                               [MantenimientoController::class, 'guardar'],        MantenimientoController::GESTIONAR);
$router->get('/mantenimientos/{id}',                           [MantenimientoController::class, 'ver'],            MantenimientoController::VER);
$router->get('/mantenimientos/{id}/editar',                    [MantenimientoController::class, 'editar'],         MantenimientoController::GESTIONAR);
$router->post('/mantenimientos/{id}',                          [MantenimientoController::class, 'actualizar'],     MantenimientoController::GESTIONAR);
$router->post('/mantenimientos/{id}/iniciar',                  [MantenimientoController::class, 'iniciar'],        MantenimientoController::GESTIONAR);
$router->post('/mantenimientos/{id}/asignar',                  [MantenimientoController::class, 'asignar'],        MantenimientoController::ASIGNAR);
$router->get('/mantenimientos/{id}/finalizar',                 [MantenimientoController::class, 'finalizacion'],   MantenimientoController::GESTIONAR);
$router->post('/mantenimientos/{id}/finalizar',                [MantenimientoController::class, 'finalizar'],      MantenimientoController::GESTIONAR);
$router->get('/mantenimientos/{id}/cancelar',                  [MantenimientoController::class, 'cancelacion'],    MantenimientoController::GESTIONAR);
$router->post('/mantenimientos/{id}/cancelar',                 [MantenimientoController::class, 'cancelar'],       MantenimientoController::GESTIONAR);
$router->post('/mantenimientos/{id}/repuestos',                [MantenimientoController::class, 'agregarRepuesto'], MantenimientoController::GESTIONAR);
$router->post('/mantenimientos/{id}/repuestos/{linea_id}/quitar', [MantenimientoController::class, 'quitarRepuesto'], MantenimientoController::GESTIONAR);

// Repuestos: el panel los consulta; administrador y técnico los gestionan y cargan stock
$router->get('/repuestos',                [RepuestoController::class, 'index'],        RepuestoController::VER);
$router->get('/repuestos/nueva',          [RepuestoController::class, 'nueva'],        RepuestoController::GESTIONAR);
$router->post('/repuestos',               [RepuestoController::class, 'guardar'],      RepuestoController::GESTIONAR);
$router->get('/repuestos/{id}/editar',    [RepuestoController::class, 'editar'],       RepuestoController::GESTIONAR);
$router->post('/repuestos/{id}',          [RepuestoController::class, 'actualizar'],   RepuestoController::GESTIONAR);
$router->post('/repuestos/{id}/estado',   [RepuestoController::class, 'estado'],       RepuestoController::GESTIONAR);
$router->get('/repuestos/{id}/stock',     [RepuestoController::class, 'stock'],        RepuestoController::GESTIONAR);
$router->post('/repuestos/{id}/stock',    [RepuestoController::class, 'agregarStock'], RepuestoController::GESTIONAR);

// Página pública del QR de la etiqueta: cualquiera ve la ficha técnica del equipo;
// para reportar una falla pide iniciar sesión (lo controla QrController) y solo deja al docente a cargo
$router->get('/q/{token}',          [QrController::class, 'ver']);
$router->get('/q/{token}/reportar', [QrController::class, 'reportar']);

// Archivos: la descarga revisa el permiso según a qué está enlazado el archivo (ArchivoController)
$router->get('/archivos/{id}',            [ArchivoController::class, 'descargar'], ['*']);
$router->post('/archivos/{id}/eliminar',  [ArchivoController::class, 'eliminar'],  ['*']);

// Adjuntos de entregas (acta firmada) y de traslados: administrador e inventario
$router->get('/asignaciones/{id}/adjuntos',  [ArchivoController::class, 'asignacion'],      AsignacionController::VER);
$router->post('/asignaciones/{id}/adjuntos', [ArchivoController::class, 'subirAsignacion'], AsignacionController::GESTIONAR);
$router->get('/movimientos/{id}/adjuntos',   [ArchivoController::class, 'movimiento'],      MovimientoController::VER);
$router->post('/movimientos/{id}/adjuntos',  [ArchivoController::class, 'subirMovimiento'], MovimientoController::GESTIONAR);

// Auditoría: solo el administrador (Sprint 7)
$router->get('/auditoria', [AuditoriaController::class, 'index'], AuditoriaController::VER);
