<?php
/**
 * Dashboard del inicio (administrador e inventario): indicadores, equipos por estado y distribución,
 * agenda de mantenimientos y, solo para el administrador, la actividad reciente.
 * Las barras son HTML y CSS: el número va en el texto y la barra es solo apoyo visual.
 * @var array $resumen       Mantenimiento::resumen() — por_atender, reportes, vencidos, en_proceso
 * @var int   $bajos         repuestos por reponer
 * @var array $conteos       Equipo::conteosDashboard() — operativo, inventario, categoria, sede
 * @var array $agenda        Mantenimiento::agenda() — filas, total
 * @var int   $diasAgenda
 * @var array $actividad     EventoAuditoria::desde()[]
 * @var bool  $verActividad  administrador
 */
$vigentes   = array_sum($conteos['operativo']);
$totalInv   = array_sum($conteos['inventario']);
$porcentaje = fn (int $n, int $total) => $total ? round($n * 100 / $total, 1) : 0;

/** Indicadores: [texto, total, enlace, clase cuando hay algo pendiente] */
$indicadores = [
    ['Equipos vigentes',      $vigentes,                '/equipos',                          ''],
    ['Reportes de falla',     $resumen['reportes'],     '/mantenimientos?tipo=diagnostico',  'warn'],
    ['En mantenimiento',      $resumen['en_proceso'],   '/mantenimientos?pestana=en_proceso', 'maint'],
    ['Mantenimientos atrasados', $resumen['vencidos'],  '#titulo-agenda',                    'bad'],
    ['Repuestos por reponer', $bajos,                   '/repuestos',                        'warn'],
];

/** Grupos de barras por estado: [clave => [texto, clase, enlace]] */
$porFuncionamiento = [];
foreach (Equipo::OPERATIVOS as $clave => $texto) {
    $porFuncionamiento[] = ['texto' => $texto, 'n' => $conteos['operativo'][$clave] ?? 0,
                            'clase' => Formato::ESTADOS[$clave][1], 'ruta' => "/equipos?operativo=$clave"];
}
$porInventario = [];
foreach (['activo' => ['En uso', 'ok'], 'en_bodega' => ['En bodega', 'muted'], 'de_baja' => ['De baja', 'bad']] as $clave => [$texto, $clase]) {
    $porInventario[] = ['texto' => $texto, 'n' => $conteos['inventario'][$clave] ?? 0,
                        'clase' => $clase, 'ruta' => "/equipos?inventario=$clave"];
}

/** Categorías y sedes: hasta 7 barras; si hay más, las 6 primeras y el resto sumado en una línea. */
$recortar = function (array $filas, ?string $rutaBase, string $plural) {
    $mostrar = count($filas) <= 7 ? 7 : 6;
    $barras  = [];
    foreach (array_slice($filas, 0, $mostrar) as $f) {
        $barras[] = ['texto' => $f['nombre'], 'n' => (int) $f['n'], 'clase' => 'navy',
                     'ruta' => $rutaBase && $f['id'] ? $rutaBase . (int) $f['id'] : null];
    }
    $resto = array_slice($filas, $mostrar);
    if ($resto) {
        $barras[] = ['texto' => 'Otras ' . count($resto) . " $plural", 'n' => array_sum(array_map('intval', array_column($resto, 'n'))),
                     'clase' => 'muted', 'ruta' => null];
    }
    return $barras;
};
$porCategoria = $recortar($conteos['categoria'], '/equipos?categoria=', 'categorías');
$porSede      = $recortar($conteos['sede'], null, 'sedes');

$barras = function (array $items, int $total, string $titulo) use ($porcentaje): void {
    ?>
    <ul class="barras" aria-label="<?= View::e($titulo) ?>">
        <?php foreach ($items as $b): ?>
            <?php $p = $porcentaje($b['n'], $total); ?>
            <li>
                <<?= $b['ruta'] ? 'a href="' . View::e(View::url($b['ruta'])) . '"' : 'div' ?> class="barra <?= $b['n'] ? '' : 'barra--vacia' ?>">
                    <span class="barra-nombre"><?= View::e($b['texto']) ?></span>
                    <span class="barra-valor">
                        <strong><?= (int) $b['n'] ?></strong><span class="sr-only"> <?= $b['n'] === 1 ? 'equipo' : 'equipos' ?>,</span>
                        <span class="barra-porcentaje"><?= View::e(str_replace('.', ',', (string) $p)) ?> %</span>
                    </span>
                    <span class="barra-pista" aria-hidden="true">
                        <span class="barra-relleno barra-relleno--<?= View::e($b['clase']) ?>" style="width: <?= (float) $p ?>%"></span>
                    </span>
                </<?= $b['ruta'] ? 'a' : 'div' ?>>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
};
?>
<ul class="indicadores dashboard-indicadores" aria-label="Resumen del inventario">
    <?php foreach ($indicadores as [$texto, $total, $href, $clase]): ?>
        <li>
            <a class="indicador <?= $total && $clase ? "indicador--$clase" : '' ?>"
               href="<?= View::e(str_starts_with($href, '#') ? $href : View::url($href)) ?>">
                <strong><?= (int) $total ?></strong>
                <span><?= View::e($texto) ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="dashboard">
    <section class="tablero" aria-labelledby="titulo-estados">
        <div class="seccion-head">
            <h2 class="sub-title" id="titulo-estados">Estado de los equipos</h2>
            <a class="btn-link" href="<?= View::url('/equipos') ?>">Ver equipos</a>
        </div>

        <?php if (!$totalInv): ?>
            <p class="tablero-vacio">Todavía no hay equipos registrados.</p>
        <?php else: ?>
            <h3 class="tablero-sub">Funcionamiento <span>· <?= Formato::plural($vigentes, 'equipo vigente', 'equipos vigentes') ?></span></h3>
            <?php $barras($porFuncionamiento, $vigentes, 'Equipos vigentes por funcionamiento'); ?>

            <h3 class="tablero-sub">Inventario <span>· <?= Formato::plural($totalInv, 'equipo registrado', 'equipos registrados') ?></span></h3>
            <?php $barras($porInventario, $totalInv, 'Equipos por estado de inventario'); ?>
        <?php endif; ?>
    </section>

    <section class="tablero" aria-labelledby="titulo-distribucion">
        <h2 class="sub-title" id="titulo-distribucion">Dónde están</h2>

        <?php if (!$vigentes): ?>
            <p class="tablero-vacio">Cuando registres equipos verás cómo se reparten por categoría y por sede.</p>
        <?php else: ?>
            <h3 class="tablero-sub">Por categoría <span>· vigentes</span></h3>
            <?php $barras($porCategoria, $vigentes, 'Equipos vigentes por categoría'); ?>

            <h3 class="tablero-sub">Por sede <span>· según su ubicación actual</span></h3>
            <?php $barras($porSede, $vigentes, 'Equipos vigentes por sede'); ?>
        <?php endif; ?>
    </section>

    <section class="tablero" aria-labelledby="titulo-agenda">
        <div class="seccion-head">
            <h2 class="sub-title" id="titulo-agenda">Agenda de mantenimientos</h2>
            <a class="btn-link" href="<?= View::url('/mantenimientos') ?>">Ver bandeja</a>
        </div>
        <p class="section-note">Programados atrasados y los de los próximos <?= (int) $diasAgenda ?> días.</p>

        <?php if (!$agenda['filas']): ?>
            <p class="tablero-vacio">No hay mantenimientos programados para los próximos <?= (int) $diasAgenda ?> días.</p>
        <?php else: ?>
            <ol class="agenda">
                <?php foreach ($agenda['filas'] as $m): ?>
                    <?php $fecha = new DateTimeImmutable($m['fecha_programada']); ?>
                    <li class="agenda-item <?= $m['atrasado'] ? 'agenda-item--atrasado' : '' ?>">
                        <time class="agenda-fecha" datetime="<?= View::e($m['fecha_programada']) ?>">
                            <strong><?= $fecha->format('j') ?></strong>
                            <span><?= View::e(explode(' ', Formato::fecha($m['fecha_programada']))[1]) ?></span>
                        </time>
                        <div class="agenda-texto">
                            <a href="<?= View::url("/mantenimientos/{$m['id']}") ?>">
                                <?= View::e(ucfirst(Formato::TIPOS_MANTENIMIENTO[$m['tipo']])) ?> · <?= View::e($m['codigo']) ?>
                            </a>
                            <span class="sub"><?= View::e($m['equipo']) ?><?= $m['ubicacion'] ? ' · ' . View::e($m['ubicacion']) : '' ?></span>
                            <span class="sub"><?= $m['tecnico'] ? View::e($m['tecnico']) : 'Sin técnico asignado' ?></span>
                        </div>
                        <?php if ($m['atrasado']): ?>
                            <span class="sello sello--bad">Atrasado</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
            <?php if ($agenda['total'] > count($agenda['filas'])): ?>
                <p class="tablero-mas">Y <?= Formato::plural($agenda['total'] - count($agenda['filas']), 'mantenimiento más', 'mantenimientos más') ?> en la bandeja.</p>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <?php if ($verActividad): ?>
        <section class="tablero" aria-labelledby="titulo-actividad">
            <div class="seccion-head">
                <h2 class="sub-title" id="titulo-actividad">Actividad reciente</h2>
                <a class="btn-link" href="<?= View::url('/auditoria') ?>">Ver auditoría</a>
            </div>

            <?php if (!$actividad): ?>
                <p class="tablero-vacio">Todavía no hay acciones registradas.</p>
            <?php else: ?>
                <ol class="actividad">
                    <?php foreach ($actividad as $ev): ?>
                        <li>
                            <p class="evento-titulo">
                                <?= View::e($ev['frase']) ?>
                                <?php if ($ev['ruta']): ?>
                                    <a href="<?= View::url($ev['ruta']) ?>"><?= View::e($ev['nombre']) ?></a>
                                <?php else: ?>
                                    <strong><?= View::e($ev['nombre']) ?></strong>
                                <?php endif; ?>
                            </p>
                            <p class="actividad-quien">
                                <?= View::e($ev['autor']) ?> ·
                                <time datetime="<?= View::e($ev['fecha_iso']) ?>"><?= View::e($ev['fecha']) ?></time>
                            </p>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
