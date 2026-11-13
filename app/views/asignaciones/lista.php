<?php
/**
 * Listado de asignaciones: buscador, pestañas (entregados / devueltos / todas), tabla y paginación.
 * @var array $filtros         q, estado
 * @var array $resultado       filas, total, pagina, paginas
 * @var bool  $puedeGestionar  administrador e inventario
 */
$ruta  = '/asignaciones';
$filas = $resultado['filas'];
$activos = array_filter([
    'q'      => $filtros['q'],
    'estado' => $filtros['estado'] === 'abiertas' ? '' : $filtros['estado'],
], fn ($v) => $v !== '');
$volverAqui = rawurlencode($ruta);
?>
<div class="page-head">
    <div>
        <h1 class="section-title">Asignaciones</h1>
        <p class="section-note">Equipos entregados a cada persona, con su fecha de entrega y de devolución.</p>
    </div>
    <?php if ($puedeGestionar): ?>
        <a class="btn btn-primary" href="<?= View::url('/asignaciones/nueva') ?>">
            <svg class="icon" aria-hidden="true"><use href="#i-plus"/></svg>
            Entregar un equipo
        </a>
    <?php endif; ?>
</div>

<?php require APP_PATH . '/views/partials/flash.php'; ?>

<div class="filtros">
    <form class="buscar" method="GET" action="<?= View::url($ruta) ?>" role="search">
        <label class="sr-only" for="q">Buscar asignaciones</label>
        <div class="buscar-campo">
            <svg class="icon" aria-hidden="true"><use href="#i-search"/></svg>
            <input type="text" id="q" name="q" value="<?= View::e($filtros['q']) ?>" maxlength="100"
                   placeholder="Código del equipo, persona o cédula" autocomplete="off">
        </div>
        <?php if ($filtros['estado'] !== 'abiertas'): ?>
            <input type="hidden" name="estado" value="<?= View::e($filtros['estado']) ?>">
        <?php endif; ?>
        <button class="btn btn-outline" type="submit">Buscar</button>
    </form>

    <ul class="chips" aria-label="Filtrar por estado">
        <?php foreach (Asignacion::ESTADOS as $valor => $texto): ?>
            <?php
            $params = array_filter(['q' => $filtros['q'], 'estado' => $valor === 'abiertas' ? '' : $valor], fn ($v) => $v !== '');
            $href   = View::url($ruta) . ($params ? '?' . http_build_query($params) : '');
            ?>
            <li>
                <a class="chip" href="<?= View::e($href) ?>" <?= $filtros['estado'] === $valor ? 'aria-current="true"' : '' ?>><?= View::e($texto) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<?php if (!$filas): ?>
    <div class="empty">
        <svg class="icon" aria-hidden="true"><use href="#i-user-check"/></svg>
        <div>
            <?php if ($filtros['q'] !== ''): ?>
                <p class="empty-title">No encontramos asignaciones con esa búsqueda.</p>
                <p>Prueba con otra palabra o <a href="<?= View::url($ruta) ?>">mira los equipos entregados</a>.</p>
            <?php elseif ($filtros['estado'] === 'abiertas'): ?>
                <p class="empty-title">Ningún equipo está entregado en este momento.</p>
                <?php if ($puedeGestionar): ?><p>Usa «Entregar un equipo» para registrar la primera entrega.</p><?php endif; ?>
            <?php else: ?>
                <p class="empty-title">Todavía no hay devoluciones registradas.</p>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <p class="conteo" aria-live="polite"><?= Formato::plural($resultado['total'], 'asignación', 'asignaciones') ?></p>

    <div class="tabla-caja">
        <table class="tabla tabla-asignaciones">
            <caption class="sr-only">Asignaciones</caption>
            <thead>
                <tr>
                    <th scope="col">Equipo</th>
                    <th scope="col">Persona</th>
                    <th scope="col">Entregado</th>
                    <th scope="col">Devuelto</th>
                    <th scope="col" class="acciones-col">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filas as $f): ?>
                    <?php $abierta = $f['fecha_devolucion'] === null; ?>
                    <tr>
                        <th scope="row" class="principal">
                            <a href="<?= View::url("/equipos/{$f['equipo_id']}") ?>"><?= View::e($f['codigo']) ?></a>
                            <span class="sub"><?= View::e($f['equipo']) ?></span>
                        </th>
                        <td data-label="Persona">
                            <a href="<?= View::url("/personas/{$f['persona_id']}") ?>"><?= View::e($f['persona']) ?></a>
                            <?php if ($f['ubicacion_entrega']): ?><span class="sub"><?= View::e($f['ubicacion_entrega']) ?></span><?php endif; ?>
                        </td>
                        <td data-label="Entregado">
                            <time datetime="<?= View::e(str_replace(' ', 'T', $f['fecha_entrega'])) ?>"><?= View::e(Formato::fechaHora($f['fecha_entrega'])) ?></time>
                        </td>
                        <td data-label="Devuelto">
                            <?php if ($abierta): ?>
                                <span class="sello sello--ok">A su cargo</span>
                            <?php else: ?>
                                <time datetime="<?= View::e(str_replace(' ', 'T', $f['fecha_devolucion'])) ?>"><?= View::e(Formato::fechaHora($f['fecha_devolucion'])) ?></time>
                                <span class="sub"><?= View::e(Formato::resumen($f['estado_devolucion'], 60)) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="acciones">
                            <?php if ($abierta && $puedeGestionar): ?>
                                <a class="btn-link" href="<?= View::url("/asignaciones/{$f['id']}/devolucion?volver=$volverAqui") ?>">
                                    <svg class="icon" aria-hidden="true"><use href="#i-undo"/></svg>
                                    Registrar devolución<span class="sr-only"> de <?= View::e($f['codigo']) ?></span>
                                </a>
                            <?php endif; ?>
                            <a class="btn-link" href="<?= View::url("/asignaciones/{$f['id']}/acta") ?>" target="_blank">
                                <svg class="icon" aria-hidden="true"><use href="#i-printer"/></svg>
                                Acta<span class="sr-only"> de <?= View::e($f['codigo']) ?> (se abre en otra pestaña)</span>
                            </a>
                            <a class="btn-link" href="<?= View::url("/asignaciones/{$f['id']}/adjuntos") ?>">
                                <svg class="icon" aria-hidden="true"><use href="#i-paperclip"/></svg>
                                Adjuntos<?= (int) $f['adjuntos'] ? ' (' . (int) $f['adjuntos'] . ')' : '' ?><span class="sr-only"> de <?= View::e($f['codigo']) ?></span>
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
