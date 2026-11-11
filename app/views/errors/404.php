<main class="status-page" id="contenido">
    <div class="status-card panel">
        <img src="<?= View::e(App::config('app.escudo')) ?>" alt="" width="64" height="64">
        <h1 class="section-title">Página no encontrada</h1>
        <p>La dirección que buscas no existe o cambió de lugar.</p>
        <a class="btn btn-primary" href="<?= View::url('/') ?>">Volver al inicio</a>
    </div>
</main>
