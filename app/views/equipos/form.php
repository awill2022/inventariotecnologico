<?php
/**
 * Alta o edición de un equipo, con los campos agrupados por sección.
 * @var ?int   $id       null al crear
 * @var ?array $equipo   equipo que se edita
 * @var array  $grupos   [título => campos (formato de Validador)]
 * @var array  $valores
 * @var array  $errores
 * @var string $csrf
 */
$volver = $id ? "/equipos/$id" : '/equipos';
?>
<a class="volver" href="<?= View::url($volver) ?>">
    <svg class="icon" aria-hidden="true"><use href="#i-arrow-left"/></svg>
    <?= $id ? 'Volver a la ficha' : 'Volver a equipos' ?>
</a>

<div class="page-head">
    <div>
        <h1 class="section-title"><?= $id ? 'Editar ' . View::e($equipo['codigo']) : 'Nuevo equipo' ?></h1>
        <?php if (!$id): ?>
            <p class="section-note">El equipo queda registrado en bodega hasta que se entregue a una persona.</p>
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

<form class="panel form form-equipo" method="POST" action="<?= View::url($id ? "/equipos/$id" : '/equipos') ?>"
      novalidate data-form-validar>
    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">

    <?php foreach ($grupos as $titulo => $campos): ?>
        <fieldset class="form-grupo">
            <legend><?= View::e($titulo) ?></legend>
            <div class="form-grupo-campos">
                <?php require APP_PATH . '/views/partials/campos.php'; ?>
            </div>
        </fieldset>
    <?php endforeach; ?>

    <div class="form-acciones">
        <button class="btn btn-primary" type="submit"><?= $id ? 'Guardar cambios' : 'Registrar equipo' ?></button>
        <a class="btn btn-text" href="<?= View::url($volver) ?>">Cancelar</a>
    </div>
</form>
