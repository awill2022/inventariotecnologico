<?php
/**
 * Hoja de etiquetas patrimoniales para imprimir (A4, 2 columnas de 90 × 50 mm).
 * El QR apunta a la página pública del equipo: /q/{qr_token}.
 * @var array $equipos  Equipo::paraEtiquetas()
 */
$total = count($equipos);
?>
<div class="hoja-intro">
    <h1 class="section-title"><?= $total === 1 ? 'Etiqueta de ' . View::e($equipos[0]['codigo']) : View::e("$total etiquetas") ?></h1>
    <p class="section-note">
        Se imprimen a tamaño real (90 × 50 mm) en hoja A4. En el cuadro de impresión elige «Tamaño real» o escala 100 %.
    </p>
</div>

<div class="etiquetas">
    <?php foreach ($equipos as $eq): ?>
        <?php $modelo = trim(($eq['marca'] ?? '') . ' ' . ($eq['modelo'] ?? '')); ?>
        <article class="etiqueta" aria-label="Etiqueta de <?= View::e($eq['codigo']) ?>">
            <div class="etiqueta-banda"><span>ISTSGA</span><span>Propiedad del instituto</span></div>
            <div class="etiqueta-cuerpo">
                <div class="etiqueta-datos">
                    <p class="etiqueta-codigo"><?= View::e($eq['codigo']) ?></p>
                    <?php if ($eq['codigo_patrimonial']): ?>
                        <p class="etiqueta-patr">Patrimonial <?= View::e($eq['codigo_patrimonial']) ?></p>
                    <?php endif; ?>
                    <p class="etiqueta-nombre"><?= View::e($eq['nombre']) ?></p>
                    <?php if ($modelo): ?><p class="etiqueta-linea"><?= View::e($modelo) ?></p><?php endif; ?>
                    <?php if ($eq['numero_serie']): ?><p class="etiqueta-linea">S/N <?= View::e($eq['numero_serie']) ?></p><?php endif; ?>
                </div>
                <div class="etiqueta-qr" data-qr="<?= View::e(View::absoluteUrl('/q/' . $eq['qr_token'])) ?>"
                     role="img" aria-label="Código QR de <?= View::e($eq['codigo']) ?>"></div>
            </div>
        </article>
    <?php endforeach; ?>
</div>
