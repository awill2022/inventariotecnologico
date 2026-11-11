<?php
/**
 * Formulario de alta o edición de un catálogo, armado desde la definición de campos.
 * @var array  $def      textos del catálogo
 * @var ?int   $id       null al crear
 * @var array  $campos   definición de campos (formato de Validador; además: inputmode,
 *                        autocomplete, ayuda, valor para los campos fijos)
 * @var array  $valores  valores a mostrar
 * @var array  $errores  errores por campo
 * @var string $csrf
 * Opcionales, para formularios que no son el alta/edición normal (p. ej. restablecer clave):
 * @var ?string $accion      ruta del POST
 * @var ?string $encabezado  título de la página
 * @var ?string $intro       párrafo bajo el título
 * @var ?string $boton       texto del botón de guardar
 */
$ruta       = $def['ruta'];
$accion   ??= $id ? "$ruta/$id" : $ruta;
$encabezado ??= $id ? 'Editar ' . $def['singular'] : $def['nuevo'];
$boton    ??= $id ? 'Guardar cambios' : 'Guardar';
$intro    ??= null;
?>
<a class="volver" href="<?= View::url($ruta) ?>">
    <svg class="icon" aria-hidden="true"><use href="#i-arrow-left"/></svg>
    Volver a <?= View::e(mb_strtolower($def['titulo'])) ?>
</a>

<div class="page-head">
    <div>
        <h1 class="section-title"><?= View::e($encabezado) ?></h1>
        <?php if ($intro): ?>
            <p class="section-note"><?= View::e($intro) ?></p>
        <?php endif; ?>
        <p class="section-note">Los campos marcados con <span class="req" aria-hidden="true">*</span> son obligatorios.</p>
    </div>
</div>

<?php require APP_PATH . '/views/partials/flash.php'; ?>

<?php if ($errores): ?>
    <div class="notice notice--error" role="alert">
        <svg class="icon" aria-hidden="true"><use href="#i-alert"/></svg>
        <p>Revisa <?= count($errores) === 1 ? 'el campo marcado' : 'los campos marcados' ?> antes de guardar.</p>
    </div>
<?php endif; ?>

<form class="panel form" method="POST" action="<?= View::url($accion) ?>" novalidate data-form-validar>
    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">

    <?php require APP_PATH . '/views/partials/campos.php'; ?>

    <div class="form-acciones">
        <button class="btn btn-primary" type="submit"><?= View::e($boton) ?></button>
        <a class="btn btn-text" href="<?= View::url($ruta) ?>">Cancelar</a>
    </div>
</form>
