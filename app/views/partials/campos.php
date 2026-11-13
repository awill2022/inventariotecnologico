<?php
/**
 * Campos de un formulario armados desde su definición (formato de Validador).
 * Admite text, textarea, select, password, fecha, fechahora, numero y fijo (solo se muestra).
 * @var array $campos   definición de campos (además: inputmode, autocomplete, ayuda, valor de los fijos,
 *                      data => [nombre => valor] para atributos data-* que usa el JavaScript,
 *                      sugerencias => [texto, ...] para un <datalist> en los campos de texto)
 * @var array $valores  valores a mostrar (las claves nunca se devuelven)
 * @var array $errores  errores por campo
 */
?>
<?php foreach ($campos as $campo): ?>
    <?php if ($campo['tipo'] === 'fijo'): ?>
        <div class="field">
            <span class="field-label"><?= View::e($campo['etiqueta']) ?></span>
            <p class="field-fijo"><?= View::e($campo['valor'] ?? '') ?></p>
            <?php if (!empty($campo['ayuda'])): ?>
                <p class="field-help"><?= View::e($campo['ayuda']) ?></p>
            <?php endif; ?>
        </div>
        <?php continue; ?>
    <?php endif; ?>
    <?php
    $nombre  = $campo['nombre'];
    $valor   = (string) ($valores[$nombre] ?? '');
    $error   = $errores[$nombre] ?? null;
    $idCampo = "campo-$nombre";
    $describe = trim((!empty($campo['ayuda']) ? "$idCampo-ayuda " : '') . "$idCampo-error");
    $attrs   = 'id="' . $idCampo . '" name="' . View::e($nombre) . '" aria-describedby="' . $describe . '"'
             . (!empty($campo['requerido']) ? ' required' : '')
             . ($error ? ' aria-invalid="true"' : '')
             . (isset($campo['inputmode']) ? ' inputmode="' . View::e($campo['inputmode']) . '"' : '');
    foreach ($campo['data'] ?? [] as $dato => $datoValor) {
        $attrs .= ' data-' . View::e($dato) . '="' . View::e((string) $datoValor) . '"';
    }
    ?>
    <div class="field">
        <label for="<?= $idCampo ?>">
            <?= View::e($campo['etiqueta']) ?>
            <?php if (!empty($campo['requerido'])): ?>
                <span class="req" aria-hidden="true">*</span>
            <?php else: ?>
                <span class="opcional">(opcional)</span>
            <?php endif; ?>
        </label>

        <?php if ($campo['tipo'] === 'select'): ?>
            <select <?= $attrs ?>>
                <option value=""><?= View::e($campo['vacia'] ?? 'Elige una opción') ?></option>
                <?php foreach ($campo['opciones'] as $opValor => $opTexto): ?>
                    <option value="<?= View::e((string) $opValor) ?>" <?= (string) $opValor === $valor ? 'selected' : '' ?>>
                        <?= View::e($opTexto) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php elseif ($campo['tipo'] === 'textarea'): ?>
            <textarea <?= $attrs ?> rows="3"
                <?= isset($campo['max']) ? 'maxlength="' . (int) $campo['max'] . '"' : '' ?>><?= View::e($valor) ?></textarea>
        <?php elseif ($campo['tipo'] === 'fecha'): ?>
            <input type="date" <?= $attrs ?> value="<?= View::e($valor) ?>"
                <?= isset($campo['min_valor']) ? 'min="' . View::e($campo['min_valor']) . '"' : '' ?>
                <?= isset($campo['max_valor']) ? 'max="' . View::e($campo['max_valor']) . '"' : '' ?>>
        <?php elseif ($campo['tipo'] === 'fechahora'): ?>
            <?php $local = fn (?string $v) => $v ? str_replace(' ', 'T', substr($v, 0, 16)) : ''; // formato de datetime-local ?>
            <input type="datetime-local" <?= $attrs ?> value="<?= View::e($local($valor)) ?>"
                <?= isset($campo['min_valor']) ? 'min="' . View::e($local($campo['min_valor'])) . '"' : '' ?>
                <?= isset($campo['max_valor']) ? 'max="' . View::e($local($campo['max_valor'])) . '"' : '' ?>>
        <?php elseif ($campo['tipo'] === 'numero'): ?>
            <input type="text" <?= $attrs ?> value="<?= View::e($valor) ?>" inputmode="decimal" autocomplete="off" maxlength="14">
        <?php elseif ($campo['tipo'] === 'password'): ?>
            <?php // La clave nunca se devuelve al formulario: se vuelve a escribir ?>
            <input type="password" <?= $attrs ?> value="" autocomplete="<?= View::e($campo['autocomplete'] ?? 'new-password') ?>"
                <?= isset($campo['igual_a']) ? 'data-igual-a="campo-' . View::e($campo['igual_a']) . '"' : '' ?>
                <?= !empty($campo['politica']) ? 'minlength="' . Clave::MIN . '" data-politica' : '' ?>
                maxlength="<?= (int) ($campo['max'] ?? Clave::MAX) ?>">
        <?php else: ?>
            <input type="text" <?= $attrs ?> value="<?= View::e($valor) ?>" autocomplete="<?= View::e($campo['autocomplete'] ?? 'off') ?>"
                <?= isset($campo['min']) ? 'minlength="' . (int) $campo['min'] . '"' : '' ?>
                <?= isset($campo['max']) ? 'maxlength="' . (int) $campo['max'] . '"' : '' ?>
                <?= !empty($campo['sugerencias']) ? 'list="' . $idCampo . '-lista"' : '' ?>>
            <?php if (!empty($campo['sugerencias'])): ?>
                <datalist id="<?= $idCampo ?>-lista">
                    <?php foreach ($campo['sugerencias'] as $sugerencia): ?>
                        <option value="<?= View::e($sugerencia) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!empty($campo['ayuda'])): ?>
            <p class="field-help" id="<?= $idCampo ?>-ayuda"><?= View::e($campo['ayuda']) ?></p>
        <?php endif; ?>
        <p class="field-error" id="<?= $idCampo ?>-error" <?= $error ? '' : 'hidden' ?>><?= View::e($error) ?></p>
    </div>
<?php endforeach; ?>
