<?php /** @var string $titulo  @var string $mensaje */ ?>
<main class="status-page" id="contenido">
    <div class="status-card panel">
        <img src="<?= View::e(App::config('app.escudo')) ?>" alt="" width="64" height="64">
        <h1 class="section-title"><?= View::e($titulo) ?></h1>
        <p><?= View::e($mensaje ?? '') ?></p>
        <a class="btn btn-primary" href="<?= View::url('/') ?>">Volver al inicio</a>
    </div>
</main>
