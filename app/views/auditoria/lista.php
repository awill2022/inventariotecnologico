<?php
/**
 * Visor de auditoría: chips por acción, filtros por usuario, entidad y fechas, y la lista de eventos
 * con su detalle desplegable («Campo: antes → después» y datos técnicos).
 * @var array  $filtros    usuario, entidad, registro, accion, desde, hasta
 * @var array  $resultado  filas, total, pagina, paginas
 * @var array  $eventos    EventoAuditoria::desde() + usuario_id
 * @var ?array $registro   texto, ruta del registro filtrado
 * @var array  $autores    [id => "Nombre (usuario)"]
 * @var array  $entidades  [entidad => nombre del filtro]
 * @var array  $acciones   [accion => nombre del filtro]
 */
$ruta      = '/auditoria';
$activos   = array_filter($filtros, fn ($v) => $v !== '' && $v !== null);
$avanzados = array_intersect_key($activos, array_flip(['usuario', 'entidad', 'desde', 'hasta']));
$enlace    = fn (array $cambios) => View::url($ruta) . (($q = http_build_query(array_filter(
    array_merge($activos, $cambios), fn ($v) => $v !== '' && $v !== null))) ? "?$q" : '');
$chips     = ['' => 'Todo menos inicios de sesión'] + $acciones;
$hoy       = date('Y-m-d');
?>
<div class="page-head">
    <div>
        <h1 class="section-title">Auditoría</h1>
        <p class="section-note">Quién hizo cada alta, cambio, cierre y baja en el sistema, y cuándo.</p>
    </div>
</div>

<?php require APP_PATH . '/views/partials/flash.php'; ?>

<form class="filtros-equipos" method="GET" action="<?= View::url($ruta) ?>">
    <ul class="chips" aria-label="Filtrar por acción">
        <?php foreach ($chips as $valor => $texto): ?>
            <li>
                <a class="chip" href="<?= View::e($enlace(['accion' => $valor, 'pagina' => null])) ?>"
                   <?= $filtros['accion'] === $valor ? 'aria-current="true"' : '' ?>><?= View::e($texto) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($filtros['accion'] !== ''): ?>
        <input type="hidden" name="accion" value="<?= View::e($filtros['accion']) ?>">
    <?php endif; ?>
    <?php if ($filtros['registro'] !== null): ?>
        <input type="hidden" name="registro" value="<?= (int) $filtros['registro'] ?>">
    <?php endif; ?>

    <details class="mas-filtros" <?= $avanzados ? 'open' : '' ?>>
        <summary>
            <svg class="icon" aria-hidden="true"><use href="#i-filter"/></svg>
            Filtros<?= $avanzados ? ' (' . count($avanzados) . ')' : '' ?>
        </summary>
        <div class="mas-filtros-campos mas-filtros-campos--4">
            <div class="field">
                <label for="f-usuario">Usuario</label>
                <select id="f-usuario" name="usuario">
                    <option value="">Todos</option>
                    <?php foreach ($autores as $valor => $texto): ?>
                        <option value="<?= (int) $valor ?>" <?= (int) $valor === $filtros['usuario'] ? 'selected' : '' ?>><?= View::e($texto) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="f-entidad">Módulo</label>
                <select id="f-entidad" name="entidad">
                    <option value="">Todos</option>
                    <?php foreach ($entidades as $valor => $texto): ?>
                        <option value="<?= View::e($valor) ?>" <?= $valor === $filtros['entidad'] ? 'selected' : '' ?>><?= View::e($texto) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="f-desde">Desde</label>
                <input type="date" id="f-desde" name="desde" value="<?= View::e((string) $filtros['desde']) ?>" max="<?= $hoy ?>">
            </div>
            <div class="field">
                <label for="f-hasta">Hasta</label>
                <input type="date" id="f-hasta" name="hasta" value="<?= View::e((string) $filtros['hasta']) ?>" max="<?= $hoy ?>">
            </div>
            <div class="mas-filtros-acciones">
                <button class="btn btn-primary" type="submit">Aplicar filtros</button>
                <?php if ($activos): ?>
                    <a class="btn btn-text" href="<?= View::url($ruta) ?>">Quitar filtros</a>
                <?php endif; ?>
            </div>
        </div>
    </details>
</form>

<?php if ($registro): ?>
    <p class="filtro-activo">
        Solo
        <?php if ($registro['ruta']): ?>
            <a href="<?= View::url($registro['ruta']) ?>"><?= View::e($registro['texto']) ?></a>
        <?php else: ?>
            <?= View::e($registro['texto']) ?>
        <?php endif; ?>
        · <a href="<?= View::e($enlace(['registro' => null, 'pagina' => null])) ?>">ver todos los registros</a>
    </p>
<?php endif; ?>

<?php if (!$eventos): ?>
    <div class="empty">
        <svg class="icon" aria-hidden="true"><use href="#i-shield"/></svg>
        <div>
            <?php if ($activos): ?>
                <p class="empty-title">No hay acciones con ese filtro.</p>
                <p>Prueba con otras fechas o <a href="<?= View::url($ruta) ?>">mira todo el registro</a>.</p>
            <?php else: ?>
                <p class="empty-title">Todavía no hay acciones registradas.</p>
                <p>Cada alta, cambio o baja que se haga en el sistema aparecerá aquí.</p>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <p class="conteo" aria-live="polite"><?= Formato::plural($resultado['total'], 'acción registrada', 'acciones registradas') ?></p>

    <ol class="eventos-auditoria">
        <?php foreach ($eventos as $ev): ?>
            <li class="evento-auditoria">
                <div class="evento-cabecera">
                    <span class="sello sello--<?= View::e($ev['clase']) ?>"><?= View::e($ev['accion']) ?></span>
                    <time datetime="<?= View::e($ev['fecha_iso']) ?>"><?= View::e($ev['fecha']) ?></time>
                </div>
                <p class="evento-titulo">
                    <?= View::e($ev['frase']) ?>
                    <?php if ($ev['nombre'] !== ''): ?>
                        <?php if ($ev['ruta']): ?>
                            <a href="<?= View::url($ev['ruta']) ?>"><?= View::e($ev['nombre']) ?></a>
                        <?php else: ?>
                            <strong><?= View::e($ev['nombre']) ?></strong>
                        <?php endif; ?>
                    <?php endif; ?>
                </p>
                <p class="evento-autor">
                    <svg class="icon" aria-hidden="true"><use href="#i-user"/></svg>
                    <?php if ($ev['usuario_id'] && $filtros['usuario'] === null): ?>
                        <a href="<?= View::e($enlace(['usuario' => (int) $ev['usuario_id'], 'pagina' => null])) ?>"
                           title="Ver solo las acciones de este usuario"><?= View::e($ev['autor']) ?></a>
                    <?php else: ?>
                        <?= View::e($ev['autor']) ?>
                    <?php endif; ?>
                </p>

                <?php if ($ev['lineas'] || $ev['tecnicos']): ?>
                    <details class="evento-detalle">
                        <summary>Ver detalle<span class="sr-only"> de: <?= View::e($ev['titulo']) ?></span></summary>
                        <?php if ($ev['lineas']): ?>
                            <dl class="evento-lineas">
                                <?php foreach ($ev['lineas'] as [$campo, $valor]): ?>
                                    <div>
                                        <dt><?= View::e($campo) ?></dt>
                                        <dd><?= View::e($valor) ?></dd>
                                    </div>
                                <?php endforeach; ?>
                            </dl>
                        <?php endif; ?>
                        <?php if ($ev['tecnicos']): ?>
                            <dl class="evento-lineas evento-lineas--tecnicas">
                                <?php foreach ($ev['tecnicos'] as [$campo, $valor]): ?>
                                    <div>
                                        <dt><?= View::e($campo) ?></dt>
                                        <dd><?= View::e($valor) ?></dd>
                                    </div>
                                <?php endforeach; ?>
                            </dl>
                        <?php endif; ?>
                    </details>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>

    <?php
    $pagina  = $resultado['pagina'];
    $paginas = $resultado['paginas'];
    $filtros = $activos;
    require APP_PATH . '/views/partials/paginacion.php';
    ?>
<?php endif; ?>
