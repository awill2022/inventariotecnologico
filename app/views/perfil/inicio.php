<?php
/**
 * Mi perfil: datos de la cuenta y cambio de clave. Se ve en el panel y en la página del docente.
 * @var array   $perfil   cuenta + persona + área (Usuario::perfil)
 * @var string  $rol      nombre visible del rol
 * @var bool    $forzado  true si la clave es temporal y debe cambiarse ya
 * @var bool    $suelto   true en el layout del docente (sin contenedor propio)
 * @var ?string $volver   ruta de regreso (solo docente)
 * @var array   $campos   campos del cambio de clave
 * @var array   $errores
 * @var string  $csrf
 */
$valores = [];
$nombre  = trim(($perfil['nombres'] ?? '') . ' ' . ($perfil['apellidos'] ?? '')) ?: $perfil['usuario'];
$datos   = [
    'Nombre'        => $nombre,
    'Cédula'        => $perfil['identificacion'],
    'Cargo'         => $perfil['cargo'],
    'Área'          => $perfil['area'],
    'Correo'        => $perfil['correo'],
    'Teléfono'      => $perfil['telefono'],
    'Usuario'       => $perfil['usuario'],
    'Rol'           => $rol,
    'Último acceso' => Formato::relativa($perfil['ultimo_acceso']),
];
?>
<div class="perfil<?= $suelto ? ' wrap perfil--suelto' : '' ?>">
    <?php if ($volver): ?>
        <a class="volver" href="<?= View::url($volver) ?>">
            <svg class="icon" aria-hidden="true"><use href="#i-arrow-left"/></svg>
            Volver a mi inicio
        </a>
    <?php endif; ?>

    <div class="page-head">
        <div>
            <h1 class="section-title"><?= $forzado ? 'Crea tu clave personal' : 'Mi perfil' ?></h1>
            <p class="section-note">
                <?= $forzado
                    ? 'Entraste con una clave temporal. Por seguridad, cámbiala por una que solo tú conozcas antes de continuar.'
                    : 'Tus datos en el sistema y el cambio de tu clave.' ?>
            </p>
        </div>
    </div>

    <?php require APP_PATH . '/views/partials/flash.php'; ?>

    <?php if ($errores): ?>
        <div class="notice notice--error" role="alert">
            <svg class="icon" aria-hidden="true"><use href="#i-alert"/></svg>
            <p>Revisa <?= count($errores) === 1 ? 'el campo marcado' : 'los campos marcados' ?> para cambiar tu clave.</p>
        </div>
    <?php endif; ?>

    <div class="perfil-grid<?= $forzado ? ' perfil-grid--solo' : '' ?>">
        <?php if (!$forzado): ?>
            <section class="panel perfil-datos" aria-labelledby="titulo-datos">
                <div class="perfil-quien">
                    <span class="avatar avatar--grande" aria-hidden="true"><?= View::e(Auth::user()['iniciales']) ?></span>
                    <div>
                        <h2 class="sub-title" id="titulo-datos"><?= View::e($nombre) ?></h2>
                        <p class="perfil-rol"><?= View::e($rol) ?></p>
                    </div>
                </div>
                <dl class="datos">
                    <?php foreach ($datos as $etiqueta => $valor): ?>
                        <?php if ($etiqueta === 'Nombre' || $etiqueta === 'Rol') continue; ?>
                        <div>
                            <dt><?= View::e($etiqueta) ?></dt>
                            <dd>
                                <?php if ($valor === null || $valor === ''): ?>
                                    <span class="sin-dato">Sin registrar</span>
                                <?php else: ?>
                                    <?= View::e((string) $valor) ?>
                                <?php endif; ?>
                            </dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
                <p class="field-help">Si algún dato está mal, pide a la Unidad de Inventario que corrija tu ficha.</p>
            </section>
        <?php endif; ?>

        <section class="panel" aria-labelledby="titulo-clave">
            <h2 class="sub-title" id="titulo-clave">
                <svg class="icon" aria-hidden="true"><use href="#i-lock"/></svg>
                <?= $forzado ? 'Tu nueva clave' : 'Cambiar clave' ?>
            </h2>
            <form class="form perfil-form" method="POST" action="<?= View::url('/perfil/clave') ?>" novalidate data-form-validar>
                <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                <?php // Para los gestores de contraseñas: a qué cuenta pertenece la clave ?>
                <input type="text" name="usuario" value="<?= View::e($perfil['usuario']) ?>" autocomplete="username" hidden>

                <?php require APP_PATH . '/views/partials/campos.php'; ?>

                <div class="form-acciones">
                    <button class="btn btn-primary" type="submit"><?= $forzado ? 'Guardar y continuar' : 'Cambiar clave' ?></button>
                    <?php if ($forzado): ?>
                        <button class="btn btn-text" type="submit" form="form-salir">Salir sin cambiarla</button>
                    <?php endif; ?>
                </div>
            </form>
            <?php if ($forzado): ?>
                <form id="form-salir" method="POST" action="<?= View::url('/logout') ?>" hidden>
                    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                </form>
            <?php endif; ?>
        </section>
    </div>
</div>
