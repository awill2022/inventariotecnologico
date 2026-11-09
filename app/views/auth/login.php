<?php
/**
 * Vista de login (estructura base; el diseño final llega en el siguiente paso).
 * @var ?string $error
 * @var string  $csrf
 */
?>
<main class="auth">
    <form class="auth-card panel" method="POST" action="<?= View::url('/login') ?>" novalidate>
        <h1>Iniciar sesión</h1>

        <?php if ($error): ?>
            <p class="alert alert--error" role="alert"><?= View::e($error) ?></p>
        <?php endif; ?>

        <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">

        <label for="usuario">Usuario</label>
        <input id="usuario" name="usuario" type="text" autocomplete="username" required>

        <label for="clave">Contraseña</label>
        <input id="clave" name="clave" type="password" autocomplete="current-password" required>

        <button class="btn btn--primary" type="submit">Entrar</button>
    </form>
</main>
