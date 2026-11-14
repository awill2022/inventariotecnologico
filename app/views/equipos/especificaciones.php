<?php
/**
 * Editor de especificaciones de un equipo: una fila por atributo–valor.
 * @var array $e            equipo
 * @var array $filas        [['id' => '', 'atributo' => '', 'valor' => ''], ...]
 * @var array $errores      "índice.campo" => mensaje
 * @var array $sugerencias  atributos usados en otros equipos
 * @var string $csrf
 */
$id = (int) $e['id'];
?>
<a class="volver" href="<?= View::url("/equipos/$id") ?>">
    <svg class="icon" aria-hidden="true"><use href="#i-arrow-left"/></svg>
    Volver a la ficha
</a>

<div class="page-head">
    <div>
        <h1 class="section-title">Especificaciones de <?= View::e($e['codigo']) ?></h1>
        <p class="section-note">
            <?= View::e($e['nombre']) ?>. Una fila por dato, por ejemplo «Memoria RAM: 16 GB».
            Para quitar una, usa «Quitar» o deja vacíos sus dos campos.
        </p>
    </div>
</div>

<?php require APP_PATH . '/views/partials/flash.php'; ?>

<?php if ($errores): ?>
    <div class="notice notice--error" role="alert">
        <svg class="icon" aria-hidden="true"><use href="#i-alert"/></svg>
        <p>Revisa <?= count($errores) === 1 ? 'el campo marcado' : 'los campos marcados' ?> antes de guardar.</p>
    </div>
<?php endif; ?>

<form class="panel form form-equipo" method="POST" action="<?= View::url("/equipos/$id/especificaciones") ?>" novalidate>
    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">

    <div class="specs" data-specs>
        <?php foreach ($filas as $i => $f): ?>
            <?php
            $errA = $errores["$i.atributo"] ?? null;
            $errV = $errores["$i.valor"] ?? null;
            ?>
            <div class="spec-fila" data-spec-fila>
                <input type="hidden" name="filas[<?= $i ?>][id]" value="<?= View::e($f['id']) ?>">
                <div class="field">
                    <label for="spec-<?= $i ?>-atributo">Atributo</label>
                    <input type="text" id="spec-<?= $i ?>-atributo" name="filas[<?= $i ?>][atributo]"
                           value="<?= View::e($f['atributo']) ?>" maxlength="<?= Especificacion::MAX_ATRIBUTO ?>"
                           list="atributos-conocidos" autocomplete="off" aria-describedby="spec-<?= $i ?>-atributo-error"
                           <?= $errA ? 'aria-invalid="true"' : '' ?>>
                    <p class="field-error" id="spec-<?= $i ?>-atributo-error" <?= $errA ? '' : 'hidden' ?>><?= View::e($errA) ?></p>
                </div>
                <div class="field">
                    <label for="spec-<?= $i ?>-valor">Valor</label>
                    <input type="text" id="spec-<?= $i ?>-valor" name="filas[<?= $i ?>][valor]"
                           value="<?= View::e($f['valor']) ?>" maxlength="<?= Especificacion::MAX_VALOR ?>"
                           autocomplete="off" aria-describedby="spec-<?= $i ?>-valor-error"
                           <?= $errV ? 'aria-invalid="true"' : '' ?>>
                    <p class="field-error" id="spec-<?= $i ?>-valor-error" <?= $errV ? '' : 'hidden' ?>><?= View::e($errV) ?></p>
                </div>
                <button class="btn-link btn-link--muted spec-quitar" type="button" data-quitar hidden>
                    Quitar<span class="sr-only"> esta fila</span>
                </button>
            </div>
        <?php endforeach; ?>
    </div>

    <datalist id="atributos-conocidos">
        <?php foreach ($sugerencias as $s): ?>
            <option value="<?= View::e($s) ?>"></option>
        <?php endforeach; ?>
    </datalist>

    <button class="btn btn-outline" type="button" data-agregar hidden>
        <svg class="icon" aria-hidden="true"><use href="#i-plus"/></svg>
        Agregar fila
    </button>

    <div class="form-acciones">
        <button class="btn btn-primary" type="submit">Guardar especificaciones</button>
        <a class="btn btn-text" href="<?= View::url("/equipos/$id") ?>">Cancelar</a>
    </div>
</form>
