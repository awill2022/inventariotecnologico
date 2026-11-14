<?php
/**
 * Reporte de falla desde la página del QR (/q/{token}/reportar). Solo llega aquí el docente a cargo.
 * @var array  $e        Equipo::porId()
 * @var string $token
 * @var array  $reporte  DocenteController::datosFormulario()
 * @var string $csrf
 */
$modelo = trim(($e['marca'] ?? '') . ' ' . ($e['modelo'] ?? ''));
$equipoFijo = ['id' => (int) $e['id'], 'codigo' => $e['codigo'], 'nombre' => $e['nombre']];
$qrToken = $token;
?>
<div class="wrap qr-pagina">
    <a class="qr-volver" href="<?= View::url("/q/$token") ?>">
        <svg class="icon" aria-hidden="true"><use href="#i-arrow-left"/></svg>
        Volver a la ficha de <?= View::e($e['codigo']) ?>
    </a>

    <section class="panel qr-reporte" id="reportar" aria-labelledby="t-reportar">
        <h1 id="t-reportar" class="section-title">Reportar una falla</h1>

        <div class="qr-equipo-fijo">
            <span class="qr-equipo-codigo"><?= View::e($e['codigo']) ?></span>
            <span class="qr-equipo-nombre"><?= View::e($e['nombre']) ?></span>
            <?php if ($modelo): ?><span class="qr-equipo-modelo"><?= View::e($modelo) ?></span><?php endif; ?>
        </div>

        <p class="section-note">Tu reporte crea una solicitud de diagnóstico para el equipo técnico.</p>

        <?php if ($reporte['error']): ?>
            <div class="notice notice--error" role="alert">
                <svg class="icon" aria-hidden="true"><use href="#i-alert"/></svg>
                <p><?= View::e($reporte['error']) ?></p>
            </div>
        <?php endif; ?>
        <?php if ($reporte['errores']): ?>
            <div class="notice notice--error" role="alert">
                <svg class="icon" aria-hidden="true"><use href="#i-alert"/></svg>
                <p>Revisa <?= count($reporte['errores']) === 1 ? 'el campo marcado' : 'los campos marcados' ?> antes de enviar.</p>
            </div>
        <?php endif; ?>

        <?php require APP_PATH . '/views/partials/reporte_falla.php'; ?>
    </section>
</div>
