<?php
/**
 * Listado de equipos: buscador, filtros, tabla con selección para imprimir etiquetas y paginación.
 * @var array $filtros         q, categoria, ubicacion, area, operativo, inventario
 * @var array $resultado       filas, total, pagina, paginas
 * @var array $categorias      [id => nombre]
 * @var array $ubicaciones     [id => "Ubicación · Sede"]
 * @var array $areas           [id => nombre]
 * @var bool  $puedeGestionar  administrador e inventario
 */
$ruta  = '/equipos';
$filas = $resultado['filas'];

// Filtros que se conservan al cambiar de página o de pestaña de inventario
$activos = array_filter([
    'q'          => $filtros['q'],
    'categoria'  => $filtros['categoria'],
    'ubicacion'  => $filtros['ubicacion'],
    'area'       => $filtros['area'],
    'operativo'  => $filtros['operativo'],
    'inventario' => $filtros['inventario'] === 'vigentes' ? '' : $filtros['inventario'],
], fn ($v) => $v !== '' && $v !== null);
$hayFiltro = $activos !== [];
$avanzados = array_intersect_key($activos, array_flip(['categoria', 'ubicacion', 'area', 'operativo']));

$selects = [
    'categoria' => ['Categoría', $categorias, 'Todas'],
    'operativo' => ['Estado operativo', Equipo::OPERATIVOS, 'Todos'],
    'ubicacion' => ['Ubicación', $ubicaciones, 'Todas'],
    'area'      => ['Área propietaria', $areas, 'Todas'],
];
?>
<div class="page-head">
    <div>
        <h1 class="section-title">Equipos</h1>
        <p class="section-note">Inventario de bienes tecnológicos del instituto, con su ubicación, estado y responsable.</p>
    </div>
    <?php if ($puedeGestionar): ?>
        <a class="btn btn-primary" href="<?= View::url('/equipos/nuevo') ?>">
            <svg class="icon" aria-hidden="true"><use href="#i-plus"/></svg>
            Nuevo equipo
        </a>
    <?php endif; ?>
</div>

<?php require APP_PATH . '/views/partials/flash.php'; ?>

<form class="filtros-equipos" method="GET" action="<?= View::url($ruta) ?>" role="search">
    <div class="buscar">
        <label class="sr-only" for="q">Buscar equipos</label>
        <div class="buscar-campo">
            <svg class="icon" aria-hidden="true"><use href="#i-search"/></svg>
            <input type="text" id="q" name="q" value="<?= View::e($filtros['q']) ?>" maxlength="100"
                   placeholder="Código, patrimonial, serie, nombre o marca" autocomplete="off">
        </div>
        <button class="btn btn-outline" type="submit">Buscar</button>
    </div>

    <details class="mas-filtros" <?= $avanzados ? 'open' : '' ?>>
        <summary>
            <svg class="icon" aria-hidden="true"><use href="#i-filter"/></svg>
            Filtros<?= $avanzados ? ' (' . count($avanzados) . ')' : '' ?>
        </summary>
        <div class="mas-filtros-campos">
            <?php foreach ($selects as $nombre => [$etiqueta, $opciones, $todas]): ?>
                <div class="field">
                    <label for="f-<?= $nombre ?>"><?= View::e($etiqueta) ?></label>
                    <select id="f-<?= $nombre ?>" name="<?= $nombre ?>">
                        <option value=""><?= View::e($todas) ?></option>
                        <?php foreach ($opciones as $valor => $texto): ?>
                            <option value="<?= View::e((string) $valor) ?>" <?= (string) $valor === (string) $filtros[$nombre] ? 'selected' : '' ?>>
                                <?= View::e($texto) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endforeach; ?>
            <div class="mas-filtros-acciones">
                <button class="btn btn-primary" type="submit">Aplicar filtros</button>
                <?php if ($hayFiltro): ?>
                    <a class="btn btn-text" href="<?= View::url($ruta) ?>">Quitar filtros</a>
                <?php endif; ?>
            </div>
        </div>
    </details>
    <?php if ($filtros['inventario'] !== 'vigentes'): ?>
        <input type="hidden" name="inventario" value="<?= View::e($filtros['inventario']) ?>">
    <?php endif; ?>
</form>

<ul class="chips" aria-label="Filtrar por inventario">
    <?php foreach (Equipo::INVENTARIO as $valor => $texto): ?>
        <?php
        // Cada pestaña conserva los demás filtros y reemplaza el de inventario ('vigentes' es el predeterminado)
        $params = $activos;
        unset($params['inventario']);
        if ($valor !== 'vigentes') {
            $params['inventario'] = $valor;
        }
        $href = View::url($ruta) . ($params ? '?' . http_build_query($params) : '');
        ?>
        <li>
            <a class="chip" href="<?= View::e($href) ?>" <?= $filtros['inventario'] === $valor ? 'aria-current="true"' : '' ?>>
                <?= View::e($texto) ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if (!$filas): ?>
    <div class="empty">
        <svg class="icon" aria-hidden="true"><use href="#i-search"/></svg>
        <div>
            <?php if ($hayFiltro): ?>
                <p class="empty-title">No encontramos equipos con ese filtro.</p>
                <p>Prueba con otra palabra o <a href="<?= View::url($ruta) ?>">mira todos los equipos vigentes</a>.</p>
            <?php else: ?>
                <p class="empty-title">Todavía no hay equipos registrados.</p>
                <?php if ($puedeGestionar): ?>
                    <p>Usa el botón «Nuevo equipo» para registrar el primero.</p>
                <?php else: ?>
                    <p>La Unidad de Inventario es quien los registra.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <form id="form-etiquetas" method="GET" action="<?= View::url('/equipos/etiquetas') ?>" target="_blank"></form>

    <div class="lista-barra">
        <p class="conteo" aria-live="polite"><?= Formato::plural($resultado['total'], 'equipo', 'equipos') ?></p>
        <div class="seleccion" data-seleccion>
            <label class="check" data-solo-js hidden>
                <input type="checkbox" data-seleccionar-todos>
                <span>Seleccionar esta página</span>
            </label>
            <button class="btn btn-outline" type="submit" form="form-etiquetas" data-imprimir-seleccion>
                <svg class="icon" aria-hidden="true"><use href="#i-printer"/></svg>
                Imprimir etiquetas <span data-contador></span>
            </button>
        </div>
    </div>

    <div class="tabla-caja">
        <table class="tabla tabla-equipos">
            <caption class="sr-only">Equipos</caption>
            <thead>
                <tr>
                    <th scope="col" class="col-check"><span class="sr-only">Seleccionar</span></th>
                    <th scope="col">Código</th>
                    <th scope="col">Equipo</th>
                    <th scope="col">Categoría</th>
                    <th scope="col">Ubicación</th>
                    <th scope="col">Responsable</th>
                    <th scope="col">Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filas as $f): ?>
                    <?php $modelo = trim(($f['marca'] ?? '') . ' ' . ($f['modelo'] ?? '')); ?>
                    <tr <?= $f['estado_inventario'] === 'de_baja' ? 'class="is-inactiva"' : '' ?>>
                        <td class="col-check">
                            <label class="check">
                                <input type="checkbox" name="ids[]" value="<?= (int) $f['id'] ?>" form="form-etiquetas" data-equipo>
                                <span class="sr-only">Seleccionar <?= View::e($f['codigo']) ?> para imprimir su etiqueta</span>
                            </label>
                        </td>
                        <th scope="row" class="principal">
                            <a href="<?= View::url("/equipos/{$f['id']}") ?>"><?= View::e($f['codigo']) ?></a>
                            <?php if ($f['codigo_patrimonial']): ?>
                                <span class="sub"><?= View::e($f['codigo_patrimonial']) ?></span>
                            <?php endif; ?>
                        </th>
                        <td data-label="Equipo">
                            <?= View::e($f['nombre']) ?>
                            <?php if ($modelo): ?><span class="sub"><?= View::e($modelo) ?></span><?php endif; ?>
                        </td>
                        <td data-label="Categoría"><?= View::e($f['categoria']) ?></td>
                        <td data-label="Ubicación">
                            <?php if ($f['ubicacion']): ?>
                                <?= View::e($f['ubicacion']) ?><span class="sub"><?= View::e($f['sede']) ?></span>
                            <?php else: ?>
                                <span class="sin-dato" aria-label="Sin dato">—</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Responsable">
                            <?php if ($f['responsable']): ?>
                                <?= View::e($f['responsable']) ?>
                            <?php else: ?>
                                <span class="sin-dato">Sin asignar</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Estado">
                            <span class="sellos">
                                <?php foreach (Formato::sellosEquipo($f) as $s): ?>
                                    <span class="sello sello--<?= $s['clase'] ?>"><?= View::e($s['texto']) ?></span>
                                <?php endforeach; ?>
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
