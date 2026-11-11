<?php
/**
 * Inicio del panel: saludo y acceso a los catálogos.
 * @var array  $usuario
 * @var string $saludo
 * @var string $hoyIso
 * @var string $hoyTexto
 * @var bool   $puedeEditar
 * @var array  $catalogos   texto, ruta, icono, total, nota
 */
?>
<div class="page-head">
    <div>
        <h1 class="section-title"><?= View::e($saludo) ?>, <?= View::e($usuario['primer_nombre']) ?></h1>
        <p class="section-note"><time datetime="<?= View::e($hoyIso) ?>"><?= View::e($hoyTexto) ?></time></p>
    </div>
</div>

<?php require APP_PATH . '/views/partials/flash.php'; ?>

<section aria-labelledby="titulo-catalogos">
    <h2 class="sub-title" id="titulo-catalogos">Catálogos</h2>
    <p class="section-note">
        <?= $puedeEditar
            ? 'Son la base del inventario: regístralos antes de cargar personas y equipos.'
            : 'Puedes consultarlos; el administrador es quien los mantiene.' ?>
    </p>

    <ul class="accesos">
        <?php foreach ($catalogos as $c): ?>
            <li>
                <a class="acceso" href="<?= View::url($c['ruta']) ?>">
                    <svg class="icon" aria-hidden="true"><use href="#i-<?= View::e($c['icono']) ?>"/></svg>
                    <span class="acceso-texto">
                        <span class="acceso-nombre"><?= View::e($c['texto']) ?></span>
                        <span class="acceso-nota"><?= View::e($c['nota']) ?></span>
                    </span>
                    <span class="acceso-total">
                        <strong><?= (int) $c['total'] ?></strong>
                        <?= (int) $c['total'] === 1 ? 'activa' : 'activas' ?>
                    </span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
