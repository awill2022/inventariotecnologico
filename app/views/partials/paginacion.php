<?php
/**
 * Paginación de un listado. Conserva la búsqueda y los filtros de la URL.
 * @var int    $pagina   página actual
 * @var int    $paginas  total de páginas
 * @var string $ruta     ruta del listado, p. ej. '/categorias'
 * @var array  $filtros  parámetros a conservar, p. ej. ['q' => 'lab', 'estado' => 'activos']
 */
if ($paginas <= 1) {
    return;
}
$enlace = fn (int $n) => View::url($ruta) . '?' . http_build_query(array_filter($filtros, fn ($v) => $v !== '') + ['pagina' => $n]);
?>
<nav class="paginacion" aria-label="Páginas">
    <?php if ($pagina > 1): ?>
        <a class="btn btn-outline" href="<?= View::e($enlace($pagina - 1)) ?>" rel="prev">Anterior</a>
    <?php else: ?>
        <span class="btn btn-outline" aria-disabled="true">Anterior</span>
    <?php endif; ?>

    <p class="paginacion-info">Página <strong><?= $pagina ?></strong> de <?= $paginas ?></p>

    <?php if ($pagina < $paginas): ?>
        <a class="btn btn-outline" href="<?= View::e($enlace($pagina + 1)) ?>" rel="next">Siguiente</a>
    <?php else: ?>
        <span class="btn btn-outline" aria-disabled="true">Siguiente</span>
    <?php endif; ?>
</nav>
