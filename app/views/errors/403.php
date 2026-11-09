<?php /** @var string $titulo  @var string $mensaje */ ?>
<main class="auth">
    <div class="auth-card panel">
        <h1><?= View::e($titulo) ?></h1>
        <p><?= View::e($mensaje ?? '') ?></p>
        <a class="btn btn--primary" href="<?= View::url('/') ?>">Volver al inicio</a>
    </div>
</main>
