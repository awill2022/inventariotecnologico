<?php
/**
 * Bandeja de mantenimientos: resumen, buscador, pestañas por estado, filtros, tabla y paginación.
 * @var array  $filtros         pestana, q, tipo, tecnico ('' | 'sin' | 'mios' | id), equipo (id o null)
 * @var ?array $equipo          equipo filtrado (cuando se llega desde su ficha)
 * @var array  $tipos           [tipo => etiqueta]
 * @var array  $tecnicos        [persona_id => nombre]
 * @var array  $resultado       filas, total, pagina, paginas
 * @var array  $resumen         por_atender, reportes, vencidos, en_proceso, mios
 * @var bool   $esTecnico
 * @var bool   $puedeGestionar  administrador y técnico
 */
$ruta  = '/mantenimientos';
$filas = $resultado['filas'];
$hoy   = date('Y-m-d');

// Parámetros de la URL que se conservan (la pestaña por defecto no se escribe)
$activos = array_filter([
    'pestana' => $filtros['pestana'] === 'por_atender' ? '' : $filtros['pestana'],
    'q'       => $filtros['q'],
    'tipo'    => $filtros['tipo'],
    'tecnico' => $filtros['tecnico'],
    'equipo'  => $filtros['equipo'] ? (string) $filtros['equipo'] : '',
], fn ($v) => $v !== '');
$avanzados = array_intersect_key($activos, array_flip(['tipo', 'tecnico']));
$enlace = function (array $cambios) use ($ruta, $activos): string {
    $params = array_filter(array_merge($activos, $cambios), fn ($v) => $v !== '' && $v !== null);
    unset($params['pagina']);
    return View::url($ruta) . ($params ? '?' . http_build_query($params) : '');
};
$nuevo = '/mantenimientos/nuevo' . ($equipo ? '?equipo=' . (int) $equipo['id'] : '');

/** Indicadores de la parte superior: [texto, total, enlace, clase, actual]. */
$indicadores = [
    ['Reportes de falla sin atender', $resumen['reportes'], $enlace(['pestana' => '', 'tipo' => 'diagnostico', 'tecnico' => '']), 'warn'],
    ['Atrasados', $resumen['vencidos'], $enlace(['pestana' => '', 'tipo' => '', 'tecnico' => '']), 'bad'],
    ['En proceso', $resumen['en_proceso'], $enlace(['pestana' => 'en_proceso', 'tipo' => '', 'tecnico' => '']), 'maint'],
];
if ($esTecnico) {
    $indicadores[] = ['Mis trabajos abiertos', $resumen['mios'], $enlace(['pestana' => 'todos', 'tipo' => '', 'tecnico' => 'mios']), 'ok'];
}
?>
<?php if ($equipo): ?>
    <a class="volver" href="<?= View::url("/equipos/{$equipo['id']}") ?>">
        <svg class="icon" aria-hidden="true"><use href="#i-arrow-left"/></svg>
        Volver a la ficha de <?= View::e($equipo['codigo']) ?>
    </a>
<?php endif; ?>

<div class="page-head">
    <div>
        <h1 class="section-title"><?= $equipo ? 'Mantenimientos de ' . View::e($equipo['codigo']) : 'Mantenimientos' ?></h1>
        <p class="section-note">
            <?= $equipo ? View::e($equipo['nombre']) . ': todos sus trabajos técnicos.'
                        : 'Reportes de falla de los docentes, trabajos programados y su historial.' ?>
        </p>
    </div>
    <?php if ($puedeGestionar && (!$equipo || $equipo['estado_inventario'] !== 'de_baja')): ?>
        <a class="btn btn-primary" href="<?= View::url($nuevo) ?>">
            <svg class="icon" aria-hidden="true"><use href="#i-plus"/></svg>
            Programar mantenimiento
        </a>
    <?php endif; ?>
</div>

<?php require APP_PATH . '/views/partials/flash.php'; ?>

<?php if (!$equipo): ?>
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
<?php endif; ?>

<form class="filtros-equipos" method="GET" action="<?= View::url($ruta) ?>" role="search">
    <?php if ($filtros['pestana'] !== 'por_atender'): ?>
        <input type="hidden" name="pestana" value="<?= View::e($filtros['pestana']) ?>">
    <?php endif; ?>
    <?php if ($equipo): ?>
        <input type="hidden" name="equipo" value="<?= (int) $equipo['id'] ?>">
    <?php else: ?>
        <div class="buscar">
            <label class="sr-only" for="q">Buscar mantenimientos</label>
            <div class="buscar-campo">
                <svg class="icon" aria-hidden="true"><use href="#i-search"/></svg>
                <input type="text" id="q" name="q" value="<?= View::e($filtros['q']) ?>" maxlength="100"
                       placeholder="Código o nombre del equipo, o el problema" autocomplete="off">
            </div>
            <button class="btn btn-outline" type="submit">Buscar</button>
        </div>
    <?php endif; ?>

    <details class="mas-filtros" <?= $avanzados ? 'open' : '' ?>>
        <summary>
            <svg class="icon" aria-hidden="true"><use href="#i-filter"/></svg>
            Filtros<?= $avanzados ? ' (' . count($avanzados) . ')' : '' ?>
        </summary>
        <div class="mas-filtros-campos">
            <div class="field">
                <label for="f-tipo">Tipo</label>
                <select id="f-tipo" name="tipo">
                    <option value="">Todos</option>
                    <?php foreach ($tipos as $valor => $texto): ?>
                        <option value="<?= View::e($valor) ?>" <?= $filtros['tipo'] === $valor ? 'selected' : '' ?>><?= View::e($texto) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="f-tecnico">Técnico</label>
                <select id="f-tecnico" name="tecnico">
                    <option value="">Todos</option>
                    <?php if ($esTecnico): ?>
                        <option value="mios" <?= $filtros['tecnico'] === 'mios' ? 'selected' : '' ?>>Mis trabajos</option>
                    <?php endif; ?>
                    <option value="sin" <?= $filtros['tecnico'] === 'sin' ? 'selected' : '' ?>>Sin asignar</option>
                    <?php foreach ($tecnicos as $valor => $texto): ?>
                        <option value="<?= (int) $valor ?>" <?= $filtros['tecnico'] === (string) $valor ? 'selected' : '' ?>><?= View::e($texto) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mas-filtros-acciones">
                <button class="btn btn-primary" type="submit">Aplicar filtros</button>
                <?php if ($avanzados || $filtros['q'] !== ''): ?>
                    <a class="btn btn-text" href="<?= View::e($enlace(['q' => '', 'tipo' => '', 'tecnico' => ''])) ?>">Quitar filtros</a>
                <?php endif; ?>
            </div>
        </div>
    </details>
</form>

<ul class="chips" aria-label="Filtrar por estado">
    <?php foreach (Mantenimiento::PESTANAS as $valor => $texto): ?>
        <li>
            <a class="chip" href="<?= View::e($enlace(['pestana' => $valor === 'por_atender' ? '' : $valor])) ?>"
               <?= $filtros['pestana'] === $valor ? 'aria-current="true"' : '' ?>><?= View::e($texto) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if ($equipo): ?>
    <p class="filtro-activo">
        Solo <?= View::e($equipo['codigo']) ?> ·
        <a href="<?= View::e($enlace(['equipo' => ''])) ?>">ver todos los equipos</a>
    </p>
<?php endif; ?>

<?php if (!$filas): ?>
    <div class="empty">
        <svg class="icon" aria-hidden="true"><use href="#i-wrench"/></svg>
        <div>
            <?php if ($filtros['q'] !== '' || $avanzados): ?>
                <p class="empty-title">No hay mantenimientos con ese filtro.</p>
                <p>Prueba con otra palabra o <a href="<?= View::e($enlace(['q' => '', 'tipo' => '', 'tecnico' => ''])) ?>">quita los filtros</a>.</p>
            <?php elseif ($filtros['pestana'] === 'por_atender'): ?>
                <p class="empty-title">No hay trabajos pendientes.</p>
                <p>Los reportes de falla de los docentes y los mantenimientos programados aparecerán aquí.</p>
            <?php elseif ($filtros['pestana'] === 'en_proceso'): ?>
                <p class="empty-title">Ningún equipo está en mantenimiento ahora.</p>
            <?php else: ?>
                <p class="empty-title">Todavía no hay mantenimientos en esta lista.</p>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <p class="conteo" aria-live="polite"><?= Formato::plural($resultado['total'], 'mantenimiento', 'mantenimientos') ?></p>

    <div class="tabla-caja">
        <table class="tabla tabla-mant">
            <caption class="sr-only">Mantenimientos</caption>
            <thead>
                <tr>
                    <th scope="col">Trabajo</th>
                    <th scope="col">Equipo</th>
                    <th scope="col">Qué hay que hacer</th>
                    <th scope="col">Fecha</th>
                    <th scope="col">Técnico</th>
                    <th scope="col">Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filas as $f): ?>
                    <?php
                    $reporte  = Mantenimiento::esReporte($f) && $f['estado'] === 'programado';
                    [$estadoTexto, $estadoClase] = Mantenimiento::ESTADOS[$f['estado']];
                    ?>
                    <tr>
                        <th scope="row" class="principal">
                            <a href="<?= View::url("/mantenimientos/{$f['id']}") ?>"><?= Mantenimiento::numero((int) $f['id']) ?></a>
                            <span class="sub"><?= View::e(MantenimientoController::tipos()[$f['tipo']]) ?></span>
                        </th>
                        <td data-label="Equipo">
                            <a href="<?= View::url("/equipos/{$f['equipo_id']}") ?>"><?= View::e($f['codigo']) ?></a>
                            <span class="sub"><?= View::e($f['equipo']) ?><?= $f['ubicacion'] ? ' · ' . View::e($f['ubicacion']) : '' ?></span>
                        </td>
                        <td data-label="Qué hay que hacer">
                            <?php if ($f['estado'] === 'cancelado' && $f['resultado']): ?>
                                <?= View::e(Formato::resumen($f['problema_reportado'], 70)) ?>
                                <span class="sub">Cancelado: <?= View::e(Formato::resumen($f['resultado'], 60)) ?></span>
                            <?php elseif ($f['problema_reportado']): ?>
                                <?= View::e(Formato::resumen($f['problema_reportado'], 90)) ?>
                            <?php else: ?>
                                <span class="sin-dato" aria-label="Sin dato">—</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Fecha">
                            <?php if ($f['estado'] === 'programado' && $f['fecha_programada']): ?>
                                <?= View::e(Formato::fecha($f['fecha_programada'])) ?>
                                <?php if ($f['fecha_programada'] < $hoy): ?>
                                    <span class="sub sub--bad">Atrasado</span>
                                <?php elseif ($f['fecha_programada'] === $hoy): ?>
                                    <span class="sub sub--warn">Es hoy</span>
                                <?php endif; ?>
                            <?php elseif ($f['estado'] === 'programado'): ?>
                                <?= View::e(Formato::relativa($f['creado_en'])) ?>
                                <span class="sub">Reportado</span>
                            <?php elseif ($f['estado'] === 'en_proceso'): ?>
                                <?= View::e(Formato::relativa($f['fecha_inicio'])) ?>
                                <span class="sub">Iniciado</span>
                            <?php elseif ($f['estado'] === 'finalizado'): ?>
                                <?= View::e(Formato::fechaHora($f['fecha_fin'])) ?>
                                <span class="sub">Finalizado</span>
                            <?php else: ?>
                                <?= View::e(Formato::fecha($f['creado_en'])) ?>
                                <span class="sub">Registrado</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Técnico">
                            <?php if ($f['tecnico']): ?>
                                <?= View::e($f['tecnico']) ?>
                            <?php else: ?>
                                <span class="sin-asignar">Sin asignar</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Estado">
                            <span class="sellos">
                                <?php if ($reporte): ?>
                                    <span class="sello sello--warn">Reporte de falla</span>
                                <?php else: ?>
                                    <span class="sello sello--<?= $estadoClase ?>"><?= $estadoTexto ?></span>
                                <?php endif; ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php
    $pagina  = $resultado['pagina'];
    $paginas = $resultado['paginas'];
    $filtros = $activos;
    require APP_PATH . '/views/partials/paginacion.php';
    ?>
<?php endif; ?>
