<?php
/**
 * Historial general de movimientos: buscador, filtros por ubicación y fechas, tabla y paginación.
 * @var array  $filtros         q, equipo (id), ubicacion (id), desde, hasta
 * @var ?array $equipo          equipo filtrado (cuando se llega desde su ficha)
 * @var array  $resultado       filas, total, pagina, paginas
 * @var array  $ubicaciones     [id => "Ubicación · Sede"]
 * @var bool   $puedeGestionar  administrador e inventario
 */
$ruta  = '/movimientos';
$filas = $resultado['filas'];
$activos = array_filter($filtros, fn ($v) => $v !== '' && $v !== null);
$avanzados = array_intersect_key($activos, array_flip(['ubicacion', 'desde', 'hasta']));
$sinEquipo = $activos;
unset($sinEquipo['equipo']);
$nuevo = '/movimientos/nuevo' . ($equipo ? '?equipo=' . (int) $equipo['id'] . '&volver=' . rawurlencode('/equipos/' . (int) $equipo['id']) : '');
?>
<?php if ($equipo): ?>
    <a class="volver" href="<?= View::url("/equipos/{$equipo['id']}") ?>">
        <svg class="icon" aria-hidden="true"><use href="#i-arrow-left"/></svg>
        Volver a la ficha de <?= View::e($equipo['codigo']) ?>
    </a>
<?php endif; ?>

<div class="page-head">
    <div>
        <h1 class="section-title"><?= $equipo ? 'Movimientos de ' . View::e($equipo['codigo']) : 'Movimientos' ?></h1>
        <p class="section-note">
            <?= $equipo ? View::e($equipo['nombre']) . ': todos sus cambios de ubicación.' : 'Cada cambio de ubicación de un equipo: ingresos, entregas, devoluciones y traslados.' ?>
        </p>
    </div>
    <?php if ($puedeGestionar && (!$equipo || $equipo['estado_inventario'] !== 'de_baja')): ?>
        <a class="btn btn-primary" href="<?= View::url($nuevo) ?>">
            <svg class="icon" aria-hidden="true"><use href="#i-move"/></svg>
            Registrar traslado
        </a>
    <?php endif; ?>
</div>

<?php require APP_PATH . '/views/partials/flash.php'; ?>

<form class="filtros-equipos" method="GET" action="<?= View::url($ruta) ?>" role="search">
    <?php if ($equipo): ?>
        <input type="hidden" name="equipo" value="<?= (int) $equipo['id'] ?>">
    <?php else: ?>
        <div class="buscar">
            <label class="sr-only" for="q">Buscar movimientos por equipo</label>
            <div class="buscar-campo">
                <svg class="icon" aria-hidden="true"><use href="#i-search"/></svg>
                <input type="text" id="q" name="q" value="<?= View::e($filtros['q']) ?>" maxlength="100"
                       placeholder="Código, patrimonial o nombre del equipo" autocomplete="off">
            </div>
            <button class="btn btn-outline" type="submit">Buscar</button>
        </div>
    <?php endif; ?>

    <details class="mas-filtros" <?= $avanzados ? 'open' : '' ?>>
        <summary>
            <svg class="icon" aria-hidden="true"><use href="#i-filter"/></svg>
            Filtros<?= $avanzados ? ' (' . count($avanzados) . ')' : '' ?>
        </summary>
        <div class="mas-filtros-campos mas-filtros-campos--3">
            <div class="field">
                <label for="f-ubicacion">Ubicación (origen o destino)</label>
                <select id="f-ubicacion" name="ubicacion">
                    <option value="">Todas</option>
                    <?php foreach ($ubicaciones as $valor => $texto): ?>
                        <option value="<?= (int) $valor ?>" <?= (int) $valor === $filtros['ubicacion'] ? 'selected' : '' ?>><?= View::e($texto) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="f-desde">Desde</label>
                <input type="date" id="f-desde" name="desde" value="<?= View::e((string) $filtros['desde']) ?>" max="<?= date('Y-m-d') ?>">
            </div>
            <div class="field">
                <label for="f-hasta">Hasta</label>
                <input type="date" id="f-hasta" name="hasta" value="<?= View::e((string) $filtros['hasta']) ?>" max="<?= date('Y-m-d') ?>">
            </div>
            <div class="mas-filtros-acciones">
                <button class="btn btn-primary" type="submit">Aplicar filtros</button>
                <?php if ($avanzados || $filtros['q'] !== ''): ?>
                    <a class="btn btn-text" href="<?= View::url($ruta . ($equipo ? '?equipo=' . (int) $equipo['id'] : '')) ?>">Quitar filtros</a>
                <?php endif; ?>
            </div>
        </div>
    </details>
</form>

<?php if ($equipo): ?>
    <p class="filtro-activo">
        Solo <?= View::e($equipo['codigo']) ?> ·
        <a href="<?= View::e(View::url($ruta) . ($sinEquipo ? '?' . http_build_query($sinEquipo) : '')) ?>">ver todos los equipos</a>
    </p>
<?php endif; ?>

<?php if (!$filas): ?>
    <div class="empty">
        <svg class="icon" aria-hidden="true"><use href="#i-move"/></svg>
        <div>
            <?php if ($activos): ?>
                <p class="empty-title">No hay movimientos con ese filtro.</p>
                <p>Prueba con otras fechas o <a href="<?= View::url($ruta) ?>">mira todo el historial</a>.</p>
            <?php else: ?>
                <p class="empty-title">Todavía no hay movimientos registrados.</p>
                <p>Se registran solos al dar de alta, entregar o devolver un equipo.</p>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <p class="conteo" aria-live="polite"><?= Formato::plural($resultado['total'], 'movimiento', 'movimientos') ?></p>

    <div class="tabla-caja">
        <table class="tabla tabla-movimientos">
            <caption class="sr-only">Movimientos</caption>
            <thead>
                <tr>
                    <th scope="col">Fecha</th>
                    <th scope="col">Equipo</th>
                    <th scope="col">Traslado</th>
                    <th scope="col">Motivo</th>
                    <th scope="col">Registró</th>
                    <th scope="col" class="acciones-col">Adjuntos</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filas as $m): ?>
                    <tr>
                        <th scope="row" class="principal">
                            <time datetime="<?= View::e(str_replace(' ', 'T', $m['fecha'])) ?>"><?= View::e(Formato::fechaHora($m['fecha'])) ?></time>
                        </th>
                        <td data-label="Equipo">
                            <a href="<?= View::url("/equipos/{$m['equipo_id']}") ?>"><?= View::e($m['codigo']) ?></a>
                            <span class="sub"><?= View::e($m['equipo']) ?></span>
                        </td>
                        <td data-label="Traslado">
                            <?php if ($m['origen']): ?>
                                <span class="ruta"><?= View::e($m['origen']) ?> <span aria-hidden="true">→</span><span class="sr-only">a</span> <?= View::e($m['destino']) ?></span>
                            <?php else: ?>
                                <span class="ruta">Ingresó a <?= View::e($m['destino']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Motivo">
                            <?= View::e((string) $m['motivo']) ?>
                            <?php if ($m['autorizado']): ?><span class="sub">Autorizó <?= View::e($m['autorizado']) ?></span><?php endif; ?>
                            <?php if ($m['observaciones']): ?><span class="sub"><?= View::e(Formato::resumen($m['observaciones'], 80)) ?></span><?php endif; ?>
                        </td>
                        <td data-label="Registró">
                            <?php if ($m['registrado']): ?>
                                <?= View::e($m['registrado']) ?>
                            <?php else: ?>
                                <span class="sin-dato" aria-label="Sin dato">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="acciones">
                            <a class="btn-link" href="<?= View::url("/movimientos/{$m['id']}/adjuntos") ?>">
                                <svg class="icon" aria-hidden="true"><use href="#i-paperclip"/></svg>
                                <?= (int) $m['adjuntos'] ? Formato::plural((int) $m['adjuntos'], 'archivo', 'archivos') : 'Adjuntar' ?><span class="sr-only"> al movimiento de <?= View::e($m['codigo']) ?></span>
                            </a>
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
