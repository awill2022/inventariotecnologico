<?php
/**
 * Acta de entrega-recepción de un equipo (A4) para imprimir y firmar.
 * Si la asignación ya se cerró, incluye la devolución con sus propias firmas.
 * @var array  $a                 Asignacion::porId()
 * @var string $numero            "ACT-000042"
 * @var array  $especificaciones  [atributo, valor]
 */
$devuelto = $a['fecha_devolucion'] !== null;
$modelo   = trim(($a['marca'] ?? '') . ' ' . ($a['modelo'] ?? ''));
$lugar    = trim(($a['ubicacion_entrega'] ?? '') . ($a['sede_entrega'] ? ' · ' . $a['sede_entrega'] : ''));
$fechaLarga = fn (string $f) => lcfirst(Formato::fechaLarga(new DateTimeImmutable($f)));

$fila = function (string $rotulo, ?string $valor): void {
    ?>
    <div>
        <dt><?= View::e($rotulo) ?></dt>
        <dd><?= $valor === null || $valor === '' ? '—' : View::e($valor) ?></dd>
    </div>
    <?php
};
?>
<div class="hoja-intro">
    <h1 class="section-title">Acta <?= View::e($numero) ?></h1>
    <p class="section-note">Imprímela en A4, fírmenla las dos partes y archívala en la Unidad de Inventario.</p>
</div>

<article class="acta" aria-label="Acta <?= View::e($numero) ?>">
    <header class="acta-banda">
        <img src="<?= View::e(App::config('app.escudo')) ?>" alt="" width="44" height="44">
        <div>
            <p class="acta-inst"><?= View::e(App::config('app.institucion', 'Instituto')) ?></p>
            <p class="acta-unidad">Unidad de Inventario Tecnológico</p>
        </div>
        <p class="acta-numero">N.º <?= View::e($numero) ?></p>
    </header>

    <h2 class="acta-titulo">Acta de entrega-recepción de equipo</h2>
    <p class="acta-lugar"><?= View::e(App::config('app.ciudad', '')) ?>, <?= View::e($fechaLarga($a['fecha_entrega'])) ?>, a las <?= View::e(substr($a['fecha_entrega'], 11, 5)) ?></p>

    <p class="acta-texto">
        La Unidad de Inventario Tecnológico entrega a <strong><?= View::e($a['persona']) ?></strong><?php if ($a['identificacion']): ?>,
        con cédula <strong><?= View::e($a['identificacion']) ?></strong><?php endif; ?><?php if ($a['cargo']): ?>,
        <?= View::e(mb_strtolower($a['cargo'])) ?><?php endif; ?><?php if ($a['area']): ?> del área de <?= View::e($a['area']) ?><?php endif; ?>,
        el equipo que se describe a continuación<?php if ($lugar): ?>, para su uso en <?= View::e($lugar) ?><?php endif; ?>.
    </p>

    <section class="acta-seccion">
        <h3>Equipo</h3>
        <dl class="acta-datos">
            <?php
            $fila('Código interno', $a['codigo']);
            $fila('Código patrimonial', $a['codigo_patrimonial']);
            $fila('Equipo', $a['equipo']);
            $fila('Categoría', $a['categoria']);
            $fila('Marca y modelo', $modelo);
            $fila('Número de serie', $a['numero_serie']);
            ?>
        </dl>
        <?php if ($especificaciones): ?>
            <p class="acta-specs">
                <?php foreach ($especificaciones as $i => $s): ?><?= $i ? ' · ' : '' ?><span><?= View::e($s['atributo']) ?>: <?= View::e($s['valor']) ?></span><?php endforeach; ?>
            </p>
        <?php endif; ?>
    </section>

    <section class="acta-seccion">
        <h3>Estado en que se entrega</h3>
        <p class="acta-libre"><?= View::e((string) $a['estado_entrega']) ?></p>
        <?php if ($a['observaciones']): ?>
            <p class="acta-libre"><strong>Observaciones:</strong> <?= View::e($a['observaciones']) ?></p>
        <?php endif; ?>
    </section>

    <p class="acta-compromiso">
        Quien recibe se compromete a usar el equipo solo en actividades institucionales, a cuidarlo, a no trasladarlo
        sin autorización de la Unidad de Inventario y a reportar cualquier falla en cuanto ocurra.
    </p>

    <div class="firmas">
        <div class="firma">
            <p class="firma-rol">Entrega</p>
            <p class="firma-nombre"><?= View::e($a['registrado'] ?: ' ') ?></p>
            <p class="firma-cargo">Unidad de Inventario Tecnológico</p>
        </div>
        <div class="firma">
            <p class="firma-rol">Recibe</p>
            <p class="firma-nombre"><?= View::e($a['persona']) ?></p>
            <p class="firma-cargo"><?= $a['identificacion'] ? 'C.I. ' . View::e($a['identificacion']) : ' ' ?></p>
        </div>
    </div>

    <?php if ($devuelto): ?>
        <section class="acta-seccion acta-devolucion">
            <h3>Devolución</h3>
            <p class="acta-texto">
                El <?= View::e($fechaLarga($a['fecha_devolucion'])) ?>, a las <?= View::e(substr($a['fecha_devolucion'], 11, 5)) ?>,
                <strong><?= View::e($a['persona']) ?></strong> devuelve el equipo <?= View::e($a['codigo']) ?> a la Unidad de Inventario.
            </p>
            <p class="acta-libre"><strong>Estado al devolverlo:</strong> <?= View::e((string) $a['estado_devolucion']) ?></p>

            <div class="firmas">
                <div class="firma">
                    <p class="firma-rol">Devuelve</p>
                    <p class="firma-nombre"><?= View::e($a['persona']) ?></p>
                    <p class="firma-cargo"><?= $a['identificacion'] ? 'C.I. ' . View::e($a['identificacion']) : ' ' ?></p>
                </div>
                <div class="firma">
                    <p class="firma-rol">Recibe</p>
                    <p class="firma-nombre"> </p>
                    <p class="firma-cargo">Unidad de Inventario Tecnológico</p>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <footer class="acta-pie">
        <span>Generada por el Sistema de Inventario Tecnológico el <?= View::e(Formato::fechaHora(date('Y-m-d H:i:s'))) ?></span>
        <span><?= View::e($numero) ?></span>
    </footer>
</article>
