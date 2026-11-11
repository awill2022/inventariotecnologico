<?php
/**
 * Listado de un catálogo: buscador, filtro por estado, tabla y paginación.
 * @var array  $def          textos y columnas (CatalogoController::def)
 * @var string $q            texto buscado
 * @var string $estado       'todos' | 'activos' | 'inactivos'
 * @var array  $resultado    filas, total, pagina, paginas
 * @var bool   $puedeEditar  true para el administrador
 * @var string $csrf
 */
$ruta    = $def['ruta'];
$filas   = $resultado['filas'];
$filtros = ['q' => $q, 'estado' => $estado === 'todos' ? '' : $estado];
$query   = http_build_query(array_filter($filtros + ['pagina' => $resultado['pagina'] > 1 ? $resultado['pagina'] : ''], fn ($v) => $v !== ''));
$chips   = ['todos' => 'Todos', 'activos' => 'Activos', 'inactivos' => 'Inactivos'];
$hayFiltro = $q !== '' || $estado !== 'todos';
?>
<div class="page-head">
    <div>
        <h1 class="section-title"><?= View::e($def['titulo']) ?></h1>
        <p class="section-note"><?= View::e($def['descripcion']) ?></p>
    </div>
    <?php if ($puedeEditar): ?>
        <a class="btn btn-primary" href="<?= View::url("$ruta/nueva") ?>">
            <svg class="icon" aria-hidden="true"><use href="#i-plus"/></svg>
            <?= View::e($def['nuevo']) ?>
        </a>
    <?php endif; ?>
</div>

<?php require APP_PATH . '/views/partials/flash.php'; ?>

<div class="filtros">
    <form class="buscar" method="GET" action="<?= View::url($ruta) ?>" role="search">
        <label class="sr-only" for="q">Buscar en <?= View::e(mb_strtolower($def['titulo'])) ?></label>
        <div class="buscar-campo">
            <svg class="icon" aria-hidden="true"><use href="#i-search"/></svg>
            <input type="text" id="q" name="q" value="<?= View::e($q) ?>" maxlength="100"
                   placeholder="<?= View::e($def['buscar'] ?? 'Buscar por nombre') ?>" autocomplete="off">
        </div>
        <?php if ($estado !== 'todos'): ?>
            <input type="hidden" name="estado" value="<?= View::e($estado) ?>">
        <?php endif; ?>
        <button class="btn btn-outline" type="submit">Buscar</button>
    </form>

    <ul class="chips" aria-label="Filtrar por estado">
        <?php foreach ($chips as $valor => $texto): ?>
            <?php $href = View::url($ruta) . '?' . http_build_query(array_filter(['q' => $q, 'estado' => $valor === 'todos' ? '' : $valor], fn ($v) => $v !== '')); ?>
            <li>
                <a class="chip" href="<?= View::e(rtrim($href, '?')) ?>" <?= $estado === $valor ? 'aria-current="true"' : '' ?>>
                    <?= $texto ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<?php if (!$filas): ?>
    <div class="empty">
        <svg class="icon" aria-hidden="true"><use href="#i-search"/></svg>
        <div>
            <?php if ($hayFiltro): ?>
                <p class="empty-title">No encontramos resultados con ese filtro.</p>
                <p>Prueba con otra palabra o <a href="<?= View::url($ruta) ?>">mira la lista completa</a>.</p>
            <?php else: ?>
                <p class="empty-title"><?= View::e($def['vacio']) ?></p>
                <?php if ($puedeEditar): ?>
                    <p>Usa el botón «<?= View::e($def['nuevo']) ?>» para empezar.</p>
                <?php else: ?>
                    <p>El administrador del sistema es quien las registra.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <p class="conteo" aria-live="polite">
        <?= Formato::plural($resultado['total'], 'resultado', 'resultados') ?>
    </p>

    <div class="tabla-caja">
        <table class="tabla">
            <caption class="sr-only"><?= View::e($def['titulo']) ?></caption>
            <thead>
                <tr>
                    <?php foreach ($def['columnas'] as $col): ?>
                        <th scope="col" <?= !empty($col['numero']) ? 'class="num"' : '' ?>><?= View::e($col['titulo']) ?></th>
                    <?php endforeach; ?>
                    <th scope="col">Estado</th>
                    <?php if ($puedeEditar): ?>
                        <th scope="col" class="acciones-col">Acciones</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filas as $fila): ?>
                    <tr <?= $fila['activo'] ? '' : 'class="is-inactiva"' ?>>
                        <?php foreach ($def['columnas'] as $n => $col): ?>
                            <?php
                            $valor = $fila[$col['clave']] ?? null;
                            if (isset($col['mapa'])) {
                                $valor = $col['mapa'][$valor] ?? $valor;
                            } elseif (isset($col['formato'])) {
                                $valor = Formato::{$col['formato']}($valor); // p. ej. 'relativa'
                            }
                            $vacio = $valor === null || $valor === '';
                            $clase = trim(($n === 0 ? 'principal ' : '') . (!empty($col['numero']) ? 'num' : ''));
                            ?>
                            <?php if ($n === 0): ?>
                                <th scope="row" class="<?= $clase ?>"><?= View::e((string) $valor) ?></th>
                            <?php else: ?>
                                <td data-label="<?= View::e($col['titulo']) ?>" <?= $clase ? "class=\"$clase\"" : '' ?>>
                                    <?php if ($vacio): ?>
                                        <span class="sin-dato" aria-label="Sin dato">—</span>
                                    <?php else: ?>
                                        <?= View::e((string) $valor) ?>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <td data-label="Estado">
                            <span class="sello sello--<?= $fila['activo'] ? 'activo' : 'inactivo' ?>">
                                <?= $fila['activo'] ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>

                        <?php if ($puedeEditar): ?>
                            <td class="acciones">
                                <a class="btn-link" href="<?= View::url("$ruta/{$fila['id']}/editar") ?>">
                                    <svg class="icon" aria-hidden="true"><use href="#i-pencil"/></svg>
                                    Editar<span class="sr-only"> <?= View::e($fila['nombre']) ?></span>
                                </a>
                                <?php foreach ($def['acciones'] ?? [] as $extra): ?>
                                    <a class="btn-link" href="<?= View::url(str_replace('{id}', (string) $fila['id'], $extra['ruta'])) ?>">
                                        <svg class="icon" aria-hidden="true"><use href="#i-<?= View::e($extra['icono']) ?>"/></svg>
                                        <?= View::e($extra['texto']) ?><span class="sr-only"> <?= View::e($fila['nombre']) ?></span>
                                    </a>
                                <?php endforeach; ?>
                                <form method="POST" action="<?= View::url("$ruta/{$fila['id']}/estado") ?>"
                                      <?php if ($fila['activo']): ?>
                                          data-confirmar="¿Desactivar «<?= View::e($fila['nombre']) ?>»? <?= View::e($def['aviso_desactivar'] ?? 'Dejará de aparecer en los formularios, pero su historial se conserva.') ?>"
                                          data-confirmar-boton="Desactivar"
                                      <?php endif; ?>>
                                    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                                    <input type="hidden" name="volver" value="<?= View::e($query !== '' ? "?$query" : '') ?>">
                                    <button class="btn-link <?= $fila['activo'] ? 'btn-link--muted' : '' ?>" type="submit">
                                        <?= $fila['activo'] ? 'Desactivar' : 'Activar' ?><span class="sr-only"> <?= View::e($fila['nombre']) ?></span>
                                    </button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php
    $pagina  = $resultado['pagina'];
    $paginas = $resultado['paginas'];
    require APP_PATH . '/views/partials/paginacion.php';
    ?>
<?php endif; ?>
