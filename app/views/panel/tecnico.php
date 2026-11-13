<?php
/**
 * Inicio del técnico: lo primero que ve son los reportes de falla por atender
 * (del más antiguo al más reciente, con «Tomar e iniciar») y debajo sus trabajos abiertos.
 * @var array  $usuario
 * @var string $saludo
 * @var string $hoyIso
 * @var string $hoyTexto
 * @var array  $reportes    filas, total (Mantenimiento::reportesPorAtender)
 * @var array  $trabajos    Mantenimiento::abiertosDe
 * @var array  $resumen     por_atender, reportes, vencidos, en_proceso, mios
 * @var bool   $sinPersona  la cuenta no está enlazada a una persona: no puede tomar trabajos
 * @var string $csrf
 */
$hoy       = date('Y-m-d');
$filas     = $reportes['filas'];
$enProceso = count(array_filter($trabajos, fn ($t) => $t['estado'] === 'en_proceso'));
$atrasados = count(array_filter($trabajos, fn ($t) => $t['estado'] === 'programado' && $t['fecha_programada'] && $t['fecha_programada'] < $hoy));

/** Indicadores: [texto, total, enlace, clase]. */
$indicadores = [
    ['Reportes por atender', $reportes['total'], '#titulo-reportes', 'warn'],
    ['Mis trabajos en proceso', $enProceso, '#titulo-trabajos', 'maint'],
    ['Mis trabajos atrasados', $atrasados, '#titulo-trabajos', 'bad'],
];
?>
<div class="page-head">
    <div>
        <h1 class="section-title"><?= View::e($saludo) ?>, <?= View::e($usuario['primer_nombre']) ?></h1>
        <p class="section-note"><time datetime="<?= View::e($hoyIso) ?>"><?= View::e($hoyTexto) ?></time></p>
    </div>
    <a class="btn btn-outline" href="<?= View::url('/mantenimientos') ?>">
        <svg class="icon" aria-hidden="true"><use href="#i-wrench"/></svg>
        Ver todos los mantenimientos
    </a>
</div>

<?php require APP_PATH . '/views/partials/flash.php'; ?>

<?php if ($sinPersona): ?>
    <div class="notice notice--warn" role="note">
        <p>Tu cuenta no está enlazada a una persona activa, así que no puedes tomar trabajos. Pide al administrador que la revise.</p>
    </div>
<?php endif; ?>

<ul class="indicadores" aria-label="Resumen">
    <?php foreach ($indicadores as [$texto, $total, $href, $clase]): ?>
        <li>
            <a class="indicador <?= $total ? "indicador--$clase" : '' ?>" href="<?= View::e($href) ?>">
                <strong><?= (int) $total ?></strong>
                <span><?= View::e($texto) ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<section class="inicio-seccion" aria-labelledby="titulo-reportes">
    <div class="seccion-head">
        <h2 class="sub-title" id="titulo-reportes">Reportes de falla por atender</h2>
        <?php if ($reportes['total'] > count($filas)): ?>
            <a class="btn-link" href="<?= View::url('/mantenimientos?tipo=diagnostico') ?>">
                Ver los <?= (int) $reportes['total'] ?><span class="sr-only"> reportes</span>
            </a>
        <?php endif; ?>
    </div>
    <p class="section-note">Los docentes reportan fallas desde su página. Atiende primero los más antiguos.</p>

    <?php if (!$filas): ?>
        <div class="empty">
            <svg class="icon" aria-hidden="true"><use href="#i-check"/></svg>
            <div>
                <p class="empty-title">No hay reportes de falla pendientes.</p>
                <p>Cuando un docente reporte una falla, aparecerá aquí.</p>
            </div>
        </div>
    <?php else: ?>
        <ol class="tarjetas-reporte">
            <?php foreach ($filas as $r): ?>
                <?php
                $id       = (int) $r['id'];
                $equipoId = (int) $r['equipo_id'];
                $deBaja   = $r['estado_inventario'] === 'de_baja';
                $ocupado  = $r['en_proceso_id'] !== null;
                $esMio    = $r['tecnico_id'] !== null;
                $quien    = $r['reportado'] ? $r['reportado'] . ($r['reportado_rol'] === 'consulta' ? ' (docente)' : '') : null;
                [$opTexto, $opClase] = Formato::ESTADOS[$r['estado_operativo']];
                ?>
                <li class="tarjeta-reporte">
                    <div class="tarjeta-reporte-cabecera">
                        <a class="tarjeta-reporte-numero" href="<?= View::url("/mantenimientos/$id") ?>"><?= Mantenimiento::numero($id) ?></a>
                        <time datetime="<?= View::e($r['creado_en']) ?>"><?= View::e(Formato::relativa($r['creado_en'])) ?></time>
                    </div>

                    <p class="tarjeta-reporte-equipo">
                        <a href="<?= View::url("/equipos/$equipoId") ?>"><?= View::e($r['codigo']) ?></a>
                        · <?= View::e($r['equipo']) ?>
                        <?php if ($r['ubicacion']): ?><span class="sub"><?= View::e($r['ubicacion']) ?></span><?php endif; ?>
                    </p>

                    <p class="tarjeta-reporte-problema"><?= View::e(Formato::resumen($r['problema_reportado'], 240)) ?></p>
                    <?php if ((int) $r['fotos'] > 0): ?>
                        <p class="tarjeta-reporte-fotos">
                            <svg class="icon" aria-hidden="true"><use href="#i-camera"/></svg>
                            <?= Formato::plural((int) $r['fotos'], 'foto adjunta', 'fotos adjuntas') ?>
                        </p>
                    <?php endif; ?>

                    <p class="sellos">
                        <?php if ($deBaja): ?>
                            <span class="sello sello--bad">Equipo de baja</span>
                        <?php else: ?>
                            <span class="sello sello--<?= $opClase ?>"><?= $opTexto ?></span>
                        <?php endif; ?>
                        <?php if ($esMio): ?>
                            <span class="sello sello--ok">Asignado a ti</span>
                        <?php endif; ?>
                    </p>

                    <?php if ($quien): ?>
                        <p class="tarjeta-reporte-quien">Reportado por <?= View::e($quien) ?></p>
                    <?php endif; ?>

                    <?php if ($ocupado): ?>
                        <p class="tarjeta-reporte-aviso">
                            El equipo ya está en mantenimiento con
                            <a href="<?= View::url("/mantenimientos/{$r['en_proceso_id']}") ?>"><?= Mantenimiento::numero((int) $r['en_proceso_id']) ?></a>:
                            este reporte se cierra cuando ese trabajo termine.
                        </p>
                    <?php elseif ($deBaja): ?>
                        <p class="tarjeta-reporte-aviso">El equipo está dado de baja: abre el reporte para cancelarlo.</p>
                    <?php endif; ?>

                    <div class="tarjeta-reporte-acciones">
                        <?php if (!$ocupado && !$deBaja && !$sinPersona): ?>
                            <form method="POST" action="<?= View::url("/mantenimientos/$id/iniciar") ?>">
                                <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                                <button class="btn btn-primary" type="submit">
                                    <svg class="icon" aria-hidden="true"><use href="#i-play"/></svg>
                                    <?= $esMio ? 'Iniciar trabajo' : 'Tomar e iniciar' ?><span class="sr-only"> <?= Mantenimiento::numero($id) ?></span>
                                </button>
                            </form>
                        <?php endif; ?>
                        <a class="btn btn-outline" href="<?= View::url("/mantenimientos/$id") ?>">
                            Ver detalle<span class="sr-only"> de <?= Mantenimiento::numero($id) ?></span>
                        </a>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>

<section class="inicio-seccion" aria-labelledby="titulo-trabajos">
    <div class="seccion-head">
        <h2 class="sub-title" id="titulo-trabajos">Mis trabajos abiertos</h2>
        <?php if ($trabajos): ?>
            <a class="btn-link" href="<?= View::url('/mantenimientos?pestana=todos&tecnico=mios') ?>">Ver en la bandeja</a>
        <?php endif; ?>
    </div>

    <?php if (!$trabajos): ?>
        <div class="empty">
            <svg class="icon" aria-hidden="true"><use href="#i-wrench"/></svg>
            <div>
                <p class="empty-title">No tienes trabajos en curso ni programados.</p>
                <p>Cuando tomes un reporte o te asignen un mantenimiento, lo verás aquí.</p>
            </div>
        </div>
    <?php else: ?>
        <ul class="lista-trabajos">
            <?php foreach ($trabajos as $t): ?>
                <?php
                $id = (int) $t['id'];
                [$estadoTexto, $estadoClase] = Mantenimiento::ESTADOS[$t['estado']];
                $atrasado = $t['estado'] === 'programado' && $t['fecha_programada'] && $t['fecha_programada'] < $hoy;
                ?>
                <li>
                    <a class="trabajo" href="<?= View::url("/mantenimientos/$id") ?>">
                        <span class="trabajo-texto">
                            <span class="trabajo-titulo">
                                <?= Mantenimiento::numero($id) ?> · <?= View::e($t['codigo']) ?>
                                <span class="trabajo-tipo"><?= View::e(Formato::TIPOS_MANTENIMIENTO[$t['tipo']]) ?></span>
                            </span>
                            <span class="trabajo-nota">
                                <?= View::e($t['equipo']) ?><?= $t['ubicacion'] ? ' · ' . View::e($t['ubicacion']) : '' ?>
                            </span>
                        </span>
                        <span class="trabajo-estado">
                            <span class="sello sello--<?= $atrasado ? 'bad' : $estadoClase ?>"><?= $atrasado ? 'Atrasado' : $estadoTexto ?></span>
                            <span class="trabajo-fecha">
                                <?php if ($t['estado'] === 'en_proceso'): ?>
                                    Iniciado <?= View::e(mb_strtolower(Formato::relativa($t['fecha_inicio']))) ?>
                                <?php elseif ($t['fecha_programada']): ?>
                                    <?= $t['fecha_programada'] === $hoy ? 'Es hoy' : View::e(Formato::fecha($t['fecha_programada'])) ?>
                                <?php endif; ?>
                            </span>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
