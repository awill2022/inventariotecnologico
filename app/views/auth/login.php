<?php
/**
 * Inicio de sesión: banda institucional a la izquierda, formulario a la derecha.
 * @var ?string $error      mensaje general (credenciales, bloqueo, conexión)
 * @var bool    $bloqueado  true mientras dure el bloqueo por intentos fallidos
 * @var array   $errores    [campo => mensaje] de validación
 * @var string  $usuario    último usuario escrito
 * @var string  $csrf
 */
$scripts = ['login.js'];
$errUsuario = $errores['usuario'] ?? null;
$errClave   = $errores['clave'] ?? null;
?>
<div class="login">
    <section class="login-brand" aria-label="Instituto Superior Tecnológico San Gabriel">
        <div class="login-brand-inner">
            <img class="login-crest" src="<?= View::e(App::config('app.escudo')) ?>" alt="Escudo del Instituto Superior Tecnológico San Gabriel" width="96" height="96">
            <p class="login-kicker">Instituto Superior Tecnológico San Gabriel</p>
            <p class="login-system">Inventario Tecnológico</p>
            <p class="login-lead">Consulta los equipos a tu cargo, su estado y reporta una falla en segundos.</p>
        </div>
        <p class="login-place">Riobamba, Ecuador</p>
    </section>

    <main class="login-main" id="contenido">
        <form class="login-form" id="form-login" method="POST" action="<?= View::url('/login') ?>" novalidate>
            <h1 class="section-title">Iniciar sesión</h1>
            <p class="login-help">Ingresa con el usuario que te entregó la Unidad de Inventario.</p>

            <?php if ($error): ?>
                <div class="notice notice--error" role="alert" id="login-error">
                    <svg class="icon" aria-hidden="true"><use href="#i-alert"/></svg>
                    <p><?= View::e($error) ?></p>
                </div>
            <?php endif; ?>

            <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">

            <div class="field">
                <label for="usuario">Usuario</label>
                <div class="input-icon">
                    <svg class="icon" aria-hidden="true"><use href="#i-user"/></svg>
                    <input id="usuario" name="usuario" type="text" value="<?= View::e($usuario) ?>"
                           autocomplete="username" autocapitalize="none" spellcheck="false"
                           required minlength="3" maxlength="80" pattern="[A-Za-z0-9._@\-]+"
                           aria-describedby="e-usuario"
                           <?= $errUsuario ? 'aria-invalid="true"' : '' ?>
                           <?= !$usuario || $errUsuario ? 'autofocus' : '' ?>
                           <?= $bloqueado ? 'disabled' : '' ?>>
                </div>
                <p class="field-error" id="e-usuario" <?= $errUsuario ? '' : 'hidden' ?>><?= View::e($errUsuario) ?></p>
            </div>

            <div class="field">
                <label for="clave">Contraseña</label>
                <div class="input-icon input-icon--action">
                    <svg class="icon" aria-hidden="true"><use href="#i-lock"/></svg>
                    <input id="clave" name="clave" type="password"
                           autocomplete="current-password" required maxlength="255"
                           aria-describedby="e-clave"
                           <?= $errClave ? 'aria-invalid="true"' : '' ?>
                           <?= $usuario && !$errUsuario ? 'autofocus' : '' ?>
                           <?= $bloqueado ? 'disabled' : '' ?>>
                    <button class="toggle-pass" type="button" id="toggle-clave"
                            aria-controls="clave" aria-pressed="false" aria-label="Mostrar contraseña"
                            <?= $bloqueado ? 'disabled' : '' ?>>
                        <svg class="icon" aria-hidden="true"><use href="#i-eye"/></svg>
                    </button>
                </div>
                <p class="field-error" id="e-clave" <?= $errClave ? '' : 'hidden' ?>><?= View::e($errClave) ?></p>
            </div>

            <button class="btn btn-primary btn-block" type="submit" id="btn-ingresar" <?= $bloqueado ? 'disabled' : '' ?>>
                Ingresar
            </button>

            <p class="login-foot">¿Olvidaste tu contraseña? Comunícate con Soporte TI · extensión 214.</p>
        </form>
    </main>
</div>
