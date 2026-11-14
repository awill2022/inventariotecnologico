<?php
/**
 * Ficha de un equipo por secciones: datos, ubicación y responsable (con entrega, devolución
 * y traslado), adquisición, especificaciones, componentes, entregas y movimientos recientes.
 * @var array $e                 Equipo::porId()
 * @var array $especificaciones
 * @var array $componentes       instalados primero, luego retirados
 * @var array $movimientos       los más recientes
 * @var int   $totalMovimientos
 * @var array $asignaciones      entregas del equipo, la más reciente primero
 * @var ?string $noEntregable    motivo por el que no se puede entregar (null = se puede)
 * @var ?array $enProceso        mantenimiento en proceso (id, tipo) o null
 * @var array $mantenimientos    los más recientes (abiertos primero)
 * @var int   $totalMantenimientos
 * @var bool  $puedeGestionar, $puedeRevertir, $puedeEspecificar, $puedeComponentes, $puedeMantener
 * @var bool  $verInventario   false para el técnico: ficha de solo lectura, sin listado, etiquetas, actas ni movimientos
 * @var bool  $verAuditoria    administrador: enlace al historial de cambios en la auditoría
 * @var string $csrf
 */
$id       = (int) $e['id'];
$deBaja   = $e['estado_inventario'] === 'de_baja';
$modelo   = trim(($e['marca'] ?? '') . ' ' . ($e['modelo'] ?? ''));
$instalados = array_values(array_filter($componentes, fn ($c) => $c['fecha_retiro'] === null));
$retirados  = array_values(array_filter($componentes, fn ($c) => $c['fecha_retiro'] !== null));
$hoy      = date('Y-m-d');
$aqui     = rawurlencode("/equipos/$id");

/** Imprime un par rótulo/valor; los vacíos se muestran con una raya. */
$dato = function (string $rotulo, ?string $valor): void {
    ?>
    <div>
        <dt><?= View::e($rotulo) ?></dt>
        <dd><?php if ($valor === null || $valor === ''): ?><span class="sin-dato" aria-label="Sin dato">—</span><?php else: ?><?= View::e($valor) ?><?php endif; ?></dd>
    </div>
    <?php
};

$lugar = implode(' · ', array_filter([
    $e['edificio'],
    $e['piso'] === null || $e['piso'] === '' ? null : (ctype_digit($e['piso']) ? 'Piso ' . $e['piso'] : $e['piso']),
    $e['sede'],
]));
$garantia = null;
if ($e['fecha_fin_garantia']) {
    $garantia = Formato::fecha($e['fecha_fin_garantia']) . ($e['fecha_fin_garantia'] < $hoy ? ' (vencida)' : '');
}
?>
<a class="volver" href="<?= View::url($verInventario ? '/equipos' : '/mantenimientos') ?>">
    <svg class="icon" aria-hidden="true"><use href="#i-arrow-left"/></svg>
    <?= $verInventario ? 'Volver a equipos' : 'Volver a mantenimientos' ?>
</a>

<header class="ficha-head">
    <div class="ficha-id">
        <p class="ficha-codigo"><?= View::e($e['codigo']) ?></p>
        <h1 class="section-title"><?= View::e($e['nombre']) ?></h1>
        <?php if ($modelo): ?><p class="section-note"><?= View::e($modelo) ?></p><?php endif; ?>
        <p class="sellos">
            <?php foreach (Formato::sellosEquipo($e) as $s): ?>
                <span class="sello sello--<?= $s['clase'] ?>"><?= View::e($s['texto']) ?></span>
            <?php endforeach; ?>
        </p>
    </div>

    <div class="ficha-acciones">
        <?php if ($puedeGestionar && !$deBaja): ?>
            <a class="btn btn-primary" href="<?= View::url("/equipos/$id/editar") ?>">
                <svg class="icon" aria-hidden="true"><use href="#i-pencil"/></svg>
                Editar datos
            </a>
        <?php endif; ?>
        <?php if ($verInventario): ?>
            <a class="btn btn-outline" href="<?= View::url('/equipos/etiquetas?ids[]=' . $id) ?>" target="_blank">
                <svg class="icon" aria-hidden="true"><use href="#i-printer"/></svg>
                Imprimir etiqueta<span class="sr-only"> (se abre en otra pestaña)</span>
            </a>
        <?php endif; ?>
        <?php if ($verAuditoria): ?>
            <a class="btn btn-outline" href="<?= View::url("/auditoria?entidad=equipos&registro=$id") ?>">
                <svg class="icon" aria-hidden="true"><use href="#i-history"/></svg>
                Historial de cambios
            </a>
        <?php endif; ?>
        <?php if ($puedeGestionar && !$deBaja): ?>
            <a class="btn btn-text btn-text--danger" href="<?= View::url("/equipos/$id/baja") ?>">
                <svg class="icon" aria-hidden="true"><use href="#i-archive"/></svg>
                Dar de baja
            </a>
        <?php endif; ?>
        <?php if ($puedeRevertir && $deBaja): ?>
            <form method="POST" action="<?= View::url("/equipos/$id/revertir-baja") ?>"
                  data-confirmar="¿Revertir la baja de <?= View::e($e['codigo']) ?>? El equipo vuelve al inventario vigente, en bodega. Úsalo solo si la baja fue un error."
                  data-confirmar-boton="Revertir baja">
                <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                <button class="btn btn-outline" type="submit">
                    <svg class="icon" aria-hidden="true"><use href="#i-undo"/></svg>
                    Revertir baja
                </button>
            </form>
        <?php endif; ?>
    </div>
</header>

<?php require APP_PATH . '/views/partials/flash.php'; ?>

<?php if ($deBaja): ?>
    <div class="notice notice--error" role="note">
        <svg class="icon" aria-hidden="true"><use href="#i-archive"/></svg>
        <p>
            <strong>Dado de baja el <?= View::e(Formato::fecha($e['fecha_baja'])) ?>.</strong>
            <?= View::e($e['motivo_baja']) ?>
            Su ficha queda como registro histórico y ya no se puede modificar.
        </p>
    </div>
<?php endif; ?>

<div class="ficha">
    <section class="panel ficha-seccion" aria-labelledby="t-datos">
        <h2 class="sub-title" id="t-datos">Datos generales</h2>
        <dl class="datos">
            <?php
            $dato('Categoría', $e['categoria']);
            $dato('Código patrimonial', $e['codigo_patrimonial']);
            $dato('Número de serie', $e['numero_serie']);
            $dato('Marca', $e['marca']);
            $dato('Modelo', $e['modelo']);
            $dato('Registrado', trim(Formato::fecha($e['creado_en']) . ($e['creado_por_nombre'] ? ' por ' . $e['creado_por_nombre'] : '')));
            ?>
        </dl>
    </section>

    <section class="panel ficha-seccion" aria-labelledby="t-ubicacion">
        <h2 class="sub-title" id="t-ubicacion">Ubicación y responsable</h2>
        <dl class="datos">
            <?php
            $dato('Ubicación actual', $e['ubicacion']);
            $dato('Lugar', $lugar);
            $dato('Área propietaria', $e['area_propietaria']);
            ?>
            <div>
                <dt>Responsable</dt>
                <dd>
                    <?php if ($e['responsable']): ?>
                        <a href="<?= View::url("/personas/{$e['responsable_id']}") ?>"><?= View::e($e['responsable']) ?></a>
                        <span class="dato-sub">desde el <?= View::e(Formato::fecha($e['fecha_entrega'])) ?></span>
                    <?php else: ?>
                        Sin asignar
                    <?php endif; ?>
                </dd>
            </div>
        </dl>
        <?php if ($puedeGestionar && !$deBaja): ?>
            <div class="seccion-acciones">
                <?php if ($e['asignacion_id']): ?>
                    <a class="btn btn-outline" href="<?= View::url("/asignaciones/{$e['asignacion_id']}/devolucion?volver=$aqui") ?>">
                        <svg class="icon" aria-hidden="true"><use href="#i-undo"/></svg>
                        Registrar devolución
                    </a>
                <?php elseif (!$noEntregable): ?>
                    <a class="btn btn-outline" href="<?= View::url("/asignaciones/nueva?equipo=$id&volver=$aqui") ?>">
                        <svg class="icon" aria-hidden="true"><use href="#i-user-check"/></svg>
                        Entregar a una persona
                    </a>
                <?php endif; ?>
                <a class="btn btn-outline" href="<?= View::url("/movimientos/nuevo?equipo=$id&volver=$aqui") ?>">
                    <svg class="icon" aria-hidden="true"><use href="#i-move"/></svg>
                    Trasladar
                </a>
            </div>
            <?php if (!$e['asignacion_id'] && $noEntregable): ?>
                <p class="section-note"><?= View::e($noEntregable) ?></p>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($enProceso): ?>
            <p class="ficha-nota">
                <svg class="icon" aria-hidden="true"><use href="#i-wrench"/></svg>
                <span>Tiene un mantenimiento en proceso:
                    <a href="<?= View::url("/mantenimientos/{$enProceso['id']}") ?>"><?= Mantenimiento::numero((int) $enProceso['id']) ?></a></span>
            </p>
        <?php endif; ?>
    </section>

    <section class="panel ficha-seccion" aria-labelledby="t-adquisicion">
        <h2 class="sub-title" id="t-adquisicion">Adquisición y garantía</h2>
        <dl class="datos">
            <?php
            $dato('Origen', Formato::ORIGENES[$e['origen']] ?? 'No especificado');
            $dato('Fecha de adquisición', Formato::fecha($e['fecha_adquisicion']));
            $dato('Valor', Formato::dinero($e['valor_adquisicion']));
            $dato('Proveedor', $e['proveedor']);
            $dato('Garantía hasta', $garantia);
            ?>
        </dl>
    </section>

    <section class="panel ficha-seccion" aria-labelledby="t-specs">
        <div class="seccion-head">
            <h2 class="sub-title" id="t-specs">Especificaciones</h2>
            <?php if ($puedeEspecificar && !$deBaja): ?>
                <a class="btn-link" href="<?= View::url("/equipos/$id/especificaciones") ?>">
                    <svg class="icon" aria-hidden="true"><use href="#i-pencil"/></svg>
                    Editar<span class="sr-only"> especificaciones</span>
                </a>
            <?php endif; ?>
        </div>
        <?php if ($especificaciones): ?>
            <dl class="datos">
                <?php foreach ($especificaciones as $s) { $dato($s['atributo'], $s['valor']); } ?>
            </dl>
        <?php else: ?>
            <p class="section-note">Sin especificaciones registradas. Aquí van datos como procesador, memoria o dirección IP.</p>
        <?php endif; ?>
    </section>

    <section class="panel ficha-seccion ficha-ancha" aria-labelledby="t-componentes">
        <div class="seccion-head">
            <h2 class="sub-title" id="t-componentes">Componentes</h2>
            <?php if ($puedeComponentes && !$deBaja): ?>
                <a class="btn-link" href="<?= View::url("/equipos/$id/componentes/nuevo") ?>">
                    <svg class="icon" aria-hidden="true"><use href="#i-plus"/></svg>
                    Instalar componente
                </a>
            <?php endif; ?>
        </div>

        <?php if (!$instalados): ?>
            <p class="section-note">No hay componentes instalados registrados.</p>
        <?php else: ?>
            <ul class="componentes">
                <?php foreach ($instalados as $c): ?>
                    <?php $detalle = implode(' · ', array_filter([trim(($c['marca'] ?? '') . ' ' . ($c['modelo'] ?? '')), $c['numero_serie'] ? 'Serie ' . $c['numero_serie'] : null])); ?>
                    <li class="componente">
                        <div>
                            <p class="componente-tipo"><?= View::e($c['tipo']) ?><?php if ($c['capacidad']): ?> · <?= View::e($c['capacidad']) ?><?php endif; ?></p>
                            <?php if ($detalle): ?><p class="componente-detalle"><?= View::e($detalle) ?></p><?php endif; ?>
                            <p class="componente-detalle">
                                <?= $c['fecha_instalacion'] ? 'Instalado el ' . View::e(Formato::fecha($c['fecha_instalacion'])) : 'Fecha de instalación no registrada' ?>
                                <?php if ($c['observaciones']): ?> · <?= View::e($c['observaciones']) ?><?php endif; ?>
                            </p>
                        </div>
                        <?php if ($puedeComponentes && !$deBaja): ?>
                            <div class="componente-acciones">
                                <a class="btn-link" href="<?= View::url("/equipos/$id/componentes/{$c['id']}/editar") ?>">
                                    Editar<span class="sr-only"> <?= View::e($c['tipo']) ?></span>
                                </a>
                                <a class="btn-link btn-link--muted" href="<?= View::url("/equipos/$id/componentes/{$c['id']}/retirar") ?>">
                                    Retirar<span class="sr-only"> <?= View::e($c['tipo']) ?></span>
                                </a>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($retirados): ?>
            <details class="retirados">
                <summary><?= Formato::plural(count($retirados), 'componente retirado', 'componentes retirados') ?></summary>
                <ul class="componentes">
                    <?php foreach ($retirados as $c): ?>
                        <li class="componente is-retirado">
                            <div>
                                <p class="componente-tipo"><?= View::e($c['tipo']) ?><?php if ($c['capacidad']): ?> · <?= View::e($c['capacidad']) ?><?php endif; ?></p>
                                <p class="componente-detalle">
                                    <?= View::e(trim(($c['marca'] ?? '') . ' ' . ($c['modelo'] ?? ''))) ?>
                                    Retirado el <?= View::e(Formato::fecha($c['fecha_retiro'])) ?>
                                    <?php if ($c['observaciones']): ?> · <?= View::e($c['observaciones']) ?><?php endif; ?>
                                </p>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endif; ?>
    </section>

    <section class="panel ficha-seccion ficha-ancha" aria-labelledby="t-mantenimientos">
        <div class="seccion-head">
            <h2 class="sub-title" id="t-mantenimientos">Mantenimientos</h2>
            <?php if ($puedeMantener && !$deBaja): ?>
                <a class="btn-link" href="<?= View::url("/mantenimientos/nuevo?equipo=$id") ?>">
                    <svg class="icon" aria-hidden="true"><use href="#i-plus"/></svg>
                    Programar
                </a>
            <?php endif; ?>
        </div>
        <?php if (!$mantenimientos): ?>
            <p class="section-note">Este equipo no tiene mantenimientos ni reportes de falla registrados.</p>
        <?php else: ?>
            <ul class="entregas">
                <?php foreach ($mantenimientos as $mt): ?>
                    <?php
                    [$mtTexto, $mtClase] = Mantenimiento::ESTADOS[$mt['estado']];
                    $mtReporte = Mantenimiento::esReporte($mt) && $mt['estado'] === 'programado';
                    $mtFecha = match ($mt['estado']) {
                        'en_proceso' => 'Desde el ' . Formato::fechaHora($mt['fecha_inicio']),
                        'finalizado' => 'Finalizado el ' . Formato::fecha($mt['fecha_fin']),
                        'cancelado'  => 'Cancelado · registrado el ' . Formato::fecha($mt['creado_en']),
                        default      => $mt['fecha_programada'] ? 'Programado para el ' . Formato::fecha($mt['fecha_programada'])
                                                                : 'Reportado ' . mb_strtolower(Formato::relativa($mt['creado_en'])),
                    };
                    ?>
                    <li class="entrega <?= in_array($mt['estado'], ['finalizado', 'cancelado'], true) ? 'is-cerrada' : '' ?>">
                        <div>
                            <p class="entrega-titulo">
                                <a href="<?= View::url("/mantenimientos/{$mt['id']}") ?>"><?= Mantenimiento::numero((int) $mt['id']) ?></a>
                                <span><?= View::e(MantenimientoController::tipos()[$mt['tipo']]) ?></span>
                                <span class="sello sello--<?= $mtReporte ? 'warn' : $mtClase ?>"><?= $mtReporte ? 'Reporte de falla' : $mtTexto ?></span>
                            </p>
                            <p class="entrega-detalle">
                                <?= View::e($mtFecha) ?><?php if ($mt['tecnico']): ?> · <?= View::e($mt['tecnico']) ?><?php endif; ?>
                            </p>
                            <?php if ($mt['problema_reportado']): ?>
                                <p class="entrega-detalle"><?= View::e(Formato::resumen($mt['problema_reportado'], 120)) ?></p>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($totalMantenimientos > count($mantenimientos)): ?>
                <a class="btn-link" href="<?= View::url("/mantenimientos?pestana=todos&equipo=$id") ?>">
                    Ver los <?= (int) $totalMantenimientos ?> mantenimientos
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="panel ficha-seccion ficha-ancha" aria-labelledby="t-entregas">
        <h2 class="sub-title" id="t-entregas">Entregas</h2>
        <?php if (!$asignaciones): ?>
            <p class="section-note">Este equipo nunca se ha entregado a una persona.</p>
        <?php else: ?>
            <ul class="entregas">
                <?php foreach ($asignaciones as $a): ?>
                    <?php $abierta = $a['fecha_devolucion'] === null; ?>
                    <li class="entrega <?= $abierta ? '' : 'is-cerrada' ?>">
                        <div>
                            <p class="entrega-titulo">
                                <a href="<?= View::url("/personas/{$a['persona_id']}") ?>"><?= View::e($a['persona']) ?></a>
                                <?php if ($abierta): ?><span class="sello sello--ok">A su cargo</span><?php endif; ?>
                            </p>
                            <p class="entrega-detalle">
                                <?php if ($abierta): ?>
                                    Desde el <?= View::e(Formato::fechaHora($a['fecha_entrega'])) ?>
                                <?php else: ?>
                                    Del <?= View::e(Formato::fecha($a['fecha_entrega'])) ?> al <?= View::e(Formato::fecha($a['fecha_devolucion'])) ?>
                                <?php endif; ?>
                                <?php if ($a['ubicacion_entrega']): ?> · <?= View::e($a['ubicacion_entrega']) ?><?php endif; ?>
                            </p>
                            <p class="entrega-detalle">
                                Entrega: <?= View::e((string) $a['estado_entrega']) ?>
                                <?php if (!$abierta && $a['estado_devolucion']): ?><br>Devolución: <?= View::e($a['estado_devolucion']) ?><?php endif; ?>
                            </p>
                        </div>
                        <?php if ($verInventario): ?>
                            <div class="entrega-acciones">
                                <a class="btn-link" href="<?= View::url("/asignaciones/{$a['id']}/acta") ?>" target="_blank">
                                    <svg class="icon" aria-hidden="true"><use href="#i-printer"/></svg>
                                    Acta<span class="sr-only"> de <?= View::e($a['persona']) ?> (se abre en otra pestaña)</span>
                                </a>
                                <a class="btn-link" href="<?= View::url("/asignaciones/{$a['id']}/adjuntos") ?>">
                                    <svg class="icon" aria-hidden="true"><use href="#i-paperclip"/></svg>
                                    Adjuntos<?= (int) $a['adjuntos'] ? ' (' . (int) $a['adjuntos'] . ')' : '' ?><span class="sr-only"> de la entrega a <?= View::e($a['persona']) ?></span>
                                </a>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="panel ficha-seccion ficha-ancha" aria-labelledby="t-movimientos">
        <div class="seccion-head">
            <h2 class="sub-title" id="t-movimientos">Movimientos recientes</h2>
            <?php if ($verInventario && $totalMovimientos > count($movimientos)): ?>
                <a class="btn-link" href="<?= View::url("/movimientos?equipo=$id") ?>">
                    Ver los <?= (int) $totalMovimientos ?><span class="sr-only"> movimientos</span>
                </a>
            <?php endif; ?>
        </div>
        <?php if (!$movimientos): ?>
            <p class="section-note">Este equipo no tiene movimientos registrados.</p>
        <?php else: ?>
            <ol class="movimientos">
                <?php foreach ($movimientos as $m): ?>
                    <li>
                        <svg class="icon" aria-hidden="true"><use href="#i-move"/></svg>
                        <div>
                            <p class="mov-titulo">
                                <?= $m['origen'] ? View::e("De {$m['origen']} a {$m['destino']}") : View::e("Ingresó a {$m['destino']}") ?>
                            </p>
                            <p class="mov-detalle">
                                <time datetime="<?= View::e(str_replace(' ', 'T', $m['fecha'])) ?>"><?= View::e(Formato::fechaHora($m['fecha'])) ?></time>
                                <?php if ($m['motivo']): ?> · <?= View::e($m['motivo']) ?><?php endif; ?>
                                <?php if ($m['autorizado']): ?> · Autorizó <?= View::e($m['autorizado']) ?><?php endif; ?>
                            </p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>

    <?php if ($e['observaciones']): ?>
        <section class="panel ficha-seccion ficha-ancha" aria-labelledby="t-obs">
            <h2 class="sub-title" id="t-obs">Observaciones</h2>
            <p class="texto-libre"><?= nl2br(View::e($e['observaciones'])) ?></p>
        </section>
    <?php endif; ?>
</div>
