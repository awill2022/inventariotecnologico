<?php
/**
 * Formulario «Reportar una falla» del docente: equipo, descripción, si se puede seguir usando y fotos.
 * Se usa en /docente (elige entre sus equipos) y en /q/{token}/reportar (equipo fijo).
 * @var array   $reporte     errores, old, maxFotos, maxBytes (DocenteController::datosFormulario)
 * @var string  $csrf
 * @var ?array  $etiquetas   equipos a elegir (id, codigo, nombre) — en /docente
 * @var ?array  $equipoFijo  id, codigo, nombre — en la página del QR
 * @var ?string $qrToken     token del QR para volver a su página
 * Sin JavaScript: el campo de fotos es un <input type="file" multiple> normal y el servidor valida todo.
 * Con JavaScript (reporte.js): botones «Tomar foto» y «Elegir de la galería», vista previa y fotos reducidas.
 */
$errores    = $reporte['errores'];
$old        = $reporte['old'] + ['equipo' => '', 'problema' => '', 'uso' => 'con_fallas'];
$equipoFijo ??= null;
$etiquetas  ??= [];
$qrToken    ??= null;
$maxFotos   = (int) $reporte['maxFotos'];
$e          = [View::class, 'e'];
?>
<form class="report" id="form-reporte" method="POST" action="<?= View::url('/docente/reportar') ?>"
      enctype="multipart/form-data" novalidate>
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
    <?php if ($qrToken): ?>
        <input type="hidden" name="qr" value="<?= $e($qrToken) ?>">
    <?php endif; ?>

    <?php if ($equipoFijo): ?>
        <input type="hidden" id="r-equipo" name="equipo" value="<?= (int) $equipoFijo['id'] ?>">
        <?php if (isset($errores['equipo'])): ?>
            <p class="field-error" id="e-equipo"><?= $e($errores['equipo']) ?></p>
        <?php endif; ?>
    <?php else: ?>
        <div class="field">
            <label for="r-equipo">Equipo</label>
            <select id="r-equipo" name="equipo" required aria-describedby="e-equipo"
                    <?= isset($errores['equipo']) ? 'aria-invalid="true"' : '' ?>>
                <option value="">Selecciona un equipo…</option>
                <?php foreach ($etiquetas as $t): ?>
                    <option value="<?= (int) $t['id'] ?>" <?= (string) $old['equipo'] === (string) $t['id'] ? 'selected' : '' ?>>
                        <?= $e($t['codigo'] . ' · ' . $t['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="field-error" id="e-equipo" <?= isset($errores['equipo']) ? '' : 'hidden' ?>>
                <?= $e($errores['equipo'] ?? 'Elige el equipo que presenta la falla.') ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="field">
        <label for="r-problema">¿Qué está pasando?</label>
        <textarea id="r-problema" name="problema" rows="4" required minlength="15" maxlength="2000"
                  aria-describedby="h-problema e-problema"
                  <?= isset($errores['problema']) ? 'aria-invalid="true"' : '' ?>
                  placeholder="Ej.: la pantalla se apaga sola después de 10 minutos de uso."><?= $e($old['problema']) ?></textarea>
        <p class="field-help" id="h-problema">Describe qué ocurre, desde cuándo y qué estabas haciendo.</p>
        <p class="field-error" id="e-problema" <?= isset($errores['problema']) ? '' : 'hidden' ?>>
            <?= $e($errores['problema'] ?? 'Cuéntanos qué ocurre (al menos 15 caracteres).') ?>
        </p>
    </div>

    <fieldset class="field">
        <legend>¿Puedes seguir usándolo?</legend>
        <div class="radios">
            <label class="radio"><input type="radio" name="uso" value="con_fallas" <?= $old['uso'] !== 'inoperativo' ? 'checked' : '' ?>> Sí, con limitaciones</label>
            <label class="radio"><input type="radio" name="uso" value="inoperativo" <?= $old['uso'] === 'inoperativo' ? 'checked' : '' ?>> No, no funciona</label>
        </div>
        <?php if (isset($errores['uso'])): ?>
            <p class="field-error"><?= $e($errores['uso']) ?></p>
        <?php endif; ?>
    </fieldset>

    <fieldset class="field fotos" data-fotos data-max="<?= $maxFotos ?>" data-max-bytes="<?= (int) $reporte['maxBytes'] ?>">
        <legend>Fotos de la falla <span class="opcional">(opcional)</span></legend>
        <p class="field-help" id="h-fotos">
            Hasta <?= $e(Formato::plural($maxFotos, 'foto', 'fotos')) ?> en JPG, PNG o WebP. Ayudan al técnico a ver
            el problema: la pantalla, el mensaje de error o la pieza dañada.
        </p>

        <input class="fotos-nativo" type="file" id="r-fotos" name="fotos[]" multiple
               accept="image/jpeg,image/png,image/webp" aria-describedby="h-fotos e-fotos"
               <?= isset($errores['fotos']) ? 'aria-invalid="true"' : '' ?>>

        <div class="fotos-botones" data-fotos-botones hidden>
            <label class="btn btn-outline fotos-boton">
                <svg class="icon" aria-hidden="true"><use href="#i-camera"/></svg>
                Tomar foto
                <input type="file" accept="image/*" capture="environment" data-fotos-camara aria-describedby="h-fotos e-fotos">
            </label>
            <label class="btn btn-outline fotos-boton">
                <svg class="icon" aria-hidden="true"><use href="#i-image"/></svg>
                Elegir de la galería
                <input type="file" accept="image/*" multiple data-fotos-galeria aria-describedby="h-fotos e-fotos">
            </label>
        </div>

        <ul class="fotos-lista" data-fotos-lista aria-label="Fotos adjuntas"></ul>
        <p class="fotos-conteo" data-fotos-conteo aria-live="polite" hidden></p>
        <p class="field-error" id="e-fotos" <?= isset($errores['fotos']) ? '' : 'hidden' ?>><?= $e($errores['fotos'] ?? '') ?></p>
    </fieldset>

    <div class="report-actions">
        <button class="btn btn-primary" type="submit" id="btn-reportar">Enviar reporte</button>
        <button class="btn btn-text" type="reset">Limpiar</button>
    </div>
</form>
