<?php
/**
 * Inicio del panel: saludo, alerta de soporte QR y acceso a los módulos.
 * @var array  $usuario
 * @var string $saludo
 * @var string $hoyIso
 * @var string $hoyTexto
 * @var bool   $puedeEditar
 * @var int    $pendientes
 * @var int    $enProceso
 * @var array  $catalogos   texto, ruta, icono, total, nota
 */
$pendientes ??= 0;
$enProceso  ??= 0;
?>
<div class="page-head">
    <div>
        <h1 class="section-title"><?= View::e($saludo) ?>, <?= View::e($usuario['primer_nombre']) ?></h1>
        <p class="section-note"><time datetime="<?= View::e($hoyIso) ?>"><?= View::e($hoyTexto) ?></time></p>
    </div>
    <a class="btn btn-gold" href="<?= View::url('/tecnico') ?>">
        <svg class="icon" aria-hidden="true"><use href="#i-qr"/></svg>
        Ir a Soporte y Escáner QR
    </a>
</div>

<?php require APP_PATH . '/views/partials/flash.php'; ?>

<?php if ($pendientes > 0): ?>
    <div class="notice notice--error" role="status">
        <svg class="icon" aria-hidden="true"><use href="#i-alert"/></svg>
        <p>
            Hay <strong><?= Formato::plural($pendientes, 'reporte de falla pendiente', 'reportes de falla pendientes') ?></strong>
            <?= $enProceso > 0 ? ' y ' . Formato::plural($enProceso, 'equipo en mantenimiento', 'equipos en mantenimiento') : '' ?>.
            <a href="<?= View::url('/tecnico?estado=programado') ?>">Revisar bandeja técnica</a>.
        </p>
    </div>
<?php endif; ?>

<section aria-labelledby="titulo-catalogos">
    <h2 class="sub-title" id="titulo-catalogos">Módulos y Catálogos</h2>
    <p class="section-note">
        <?= $puedeEditar
            ? 'Gestiona las solicitudes de soporte por QR y los catálogos base del inventario.'
            : 'Consulta las solicitudes de soporte por QR y los catálogos del sistema.' ?>
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
                        <?= $c['ruta'] === '/tecnico'
                            ? ((int) $c['total'] === 1 ? 'abierto' : 'abiertos')
                            : ((int) $c['total'] === 1 ? 'activa' : 'activas') ?>
                    </span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>