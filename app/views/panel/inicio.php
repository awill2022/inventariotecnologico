<?php
/**
 * Inicio del panel (administrador e inventario): saludo, dashboard (panel/dashboard.php)
 * y accesos al inventario, soporte y catálogos.
 * El técnico tiene su propio inicio: panel/tecnico.php.
 * @var array  $usuario
 * @var string $saludo
 * @var string $hoyIso
 * @var string $hoyTexto
 * @var bool   $puedeEditar
 * @var array  $inventario  texto, ruta, icono, total, nota, unidad [singular, plural]
 * @var array  $soporte     mantenimientos y repuestos (mismo formato)
 * @var array  $catalogos   texto, ruta, icono, total, nota
 */
$accesos = function (array $items): void {
    ?>
    <ul class="accesos">
        <?php foreach ($items as $c): ?>
            <?php [$uno, $varios] = $c['unidad'] ?? ['activa', 'activas']; ?>
            <li>
                <a class="acceso" href="<?= View::url($c['ruta']) ?>">
                    <svg class="icon" aria-hidden="true"><use href="#i-<?= View::e($c['icono']) ?>"/></svg>
                    <span class="acceso-texto">
                        <span class="acceso-nombre"><?= View::e($c['texto']) ?></span>
                        <span class="acceso-nota"><?= View::e($c['nota']) ?></span>
                    </span>
                    <span class="acceso-total">
                        <strong><?= (int) $c['total'] ?></strong>
                        <?= (int) $c['total'] === 1 ? $uno : $varios ?>
                    </span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
};
?>
<div class="page-head">
    <div>
        <h1 class="section-title"><?= View::e($saludo) ?>, <?= View::e($usuario['primer_nombre']) ?></h1>
        <p class="section-note"><time datetime="<?= View::e($hoyIso) ?>"><?= View::e($hoyTexto) ?></time></p>
    </div>
</div>

<?php require APP_PATH . '/views/partials/flash.php'; ?>

<?php require APP_PATH . '/views/panel/dashboard.php'; ?>

<section class="inicio-accesos" aria-labelledby="titulo-inventario">
    <h2 class="sub-title" id="titulo-inventario">Inventario</h2>
    <?php $accesos($inventario); ?>
</section>

<section aria-labelledby="titulo-soporte">
    <h2 class="sub-title" id="titulo-soporte">Soporte técnico</h2>
    <?php $accesos($soporte); ?>
</section>

<section aria-labelledby="titulo-catalogos">
    <h2 class="sub-title" id="titulo-catalogos">Catálogos</h2>
    <p class="section-note">
        <?= $puedeEditar
            ? 'Son la base del inventario: regístralos antes de cargar personas y equipos.'
            : 'Puedes consultarlos; el administrador es quien los mantiene.' ?>
    </p>

    <?php $accesos($catalogos); ?>
</section>
