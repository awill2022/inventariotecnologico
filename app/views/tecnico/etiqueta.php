<?php
/**
 * Etiqueta patrimonial QR imprimible (formato placa física ISTSGA).
 * @var array $equipo
 */
$e = [View::class, 'e'];
$qrUrl = View::absoluteUrl('/q/' . $equipo['qr_token']);
?>
<div class="no-print">
    <a class="volver" href="<?= View::url('/tecnico/equipos/' . (int) $equipo['id']) ?>">
        <svg class="icon" aria-hidden="true"><use href="#i-arrow-left"/></svg>
        Volver a la ficha técnica de <?= $e($equipo['codigo']) ?>
    </a>

    <div class="page-head">
        <div>
            <h1 class="section-title">Etiqueta patrimonial QR · <?= $e($equipo['codigo']) ?></h1>
            <p class="section-note">Lista para imprimir y adherir al equipo. Al escanear el QR lleva a <code><?= $e($qrUrl) ?></code>.</p>
        </div>
        <button class="btn btn-primary" type="button" onclick="window.print()">
            Imprimir etiqueta QR
        </button>
    </div>
</div>

<div class="etiqueta-imprimible-wrap">
    <article class="tag etiqueta-imprimible">
        <div class="tag-band"><span>ISTSGA · SAN GABRIEL</span><span>Propiedad del instituto</span></div>
        <div class="tag-main">
            <div class="tag-id">
                <p class="tag-code"><?= $e($equipo['codigo']) ?></p>
                <?php if ($equipo['codigo_patrimonial']): ?>
                    <p class="tag-patr">Patrimonial <?= $e($equipo['codigo_patrimonial']) ?></p>
                <?php endif; ?>
                <p class="tag-name" style="padding-inline: 0; margin-top: var(--s-2); font-size: 1.05rem;">
                    <?= $e($equipo['nombre']) ?>
                </p>
                <?php if ($equipo['numero_serie']): ?>
                    <p class="tag-patr">S/N: <?= $e($equipo['numero_serie']) ?></p>
                <?php endif; ?>
            </div>
            <div class="tag-qr" data-qr="<?= $e($qrUrl) ?>" style="width: 96px; height: 96px;"></div>
        </div>
    </article>
</div>