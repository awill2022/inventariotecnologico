<?php
/**
 * Adjuntos de una entrega o de un traslado: datos del registro, archivos subidos y formulario de subida.
 * @var string  $titulo, $intro
 * @var array   $volver       url, texto
 * @var array   $contexto     [rótulo => [texto, url?]]
 * @var ?array  $enlaceExtra  url, texto (p. ej. imprimir el acta)
 * @var array   $archivos     filas de Archivo
 * @var bool    $puedeSubir   también puede eliminar
 * @var string  $accion       ruta del POST de subida
 * @var array   $tipos        [clave => texto]
 * @var string  $tipoDefecto
 * @var string  $formatos, $maxDoc, $maxFoto
 * @var array   $errores, $old
 * @var string  $csrf
 */
$tipoElegido = $old['tipo'] ?? $tipoDefecto;
$campoError  = fn (string $c) => isset($errores[$c]) ? 'aria-invalid="true"' : '';
?>
<a class="volver" href="<?= View::url($volver['url']) ?>">
    <svg class="icon" aria-hidden="true"><use href="#i-arrow-left"/></svg>
    <?= View::e($volver['texto']) ?>
</a>

<div class="page-head">
    <div>
        <h1 class="section-title"><?= View::e($titulo) ?></h1>
        <p class="section-note"><?= View::e($intro) ?></p>
    </div>
</div>

<?php require APP_PATH . '/views/partials/flash.php'; ?>

<div class="adjuntos-grid">
    <section class="panel adjuntos-seccion" aria-labelledby="t-registro">
        <h2 class="sub-title" id="t-registro">Registro</h2>
        <dl class="datos">
            <?php foreach ($contexto as $rotulo => $dato): ?>
                <div>
                    <dt><?= View::e($rotulo) ?></dt>
                    <dd>
                        <?php if (!empty($dato['url'])): ?>
                            <a href="<?= View::url($dato['url']) ?>"><?= View::e($dato['texto']) ?></a>
                        <?php else: ?>
                            <?= View::e($dato['texto']) ?>
                        <?php endif; ?>
                    </dd>
                </div>
            <?php endforeach; ?>
        </dl>
        <?php if ($enlaceExtra): ?>
            <a class="btn btn-outline" href="<?= View::url($enlaceExtra['url']) ?>" target="_blank">
                <svg class="icon" aria-hidden="true"><use href="#i-printer"/></svg>
                <?= View::e($enlaceExtra['texto']) ?><span class="sr-only"> (se abre en otra pestaña)</span>
            </a>
        <?php endif; ?>
    </section>

    <section class="panel adjuntos-seccion" aria-labelledby="t-archivos">
        <h2 class="sub-title" id="t-archivos">Archivos</h2>

        <?php if (!$archivos): ?>
            <p class="adjuntos-vacio">
                <svg class="icon" aria-hidden="true"><use href="#i-paperclip"/></svg>
                Todavía no hay archivos adjuntos.
            </p>
        <?php else: ?>
            <ul class="adjuntos">
                <?php foreach ($archivos as $a): ?>
                    <?php
                    $url    = View::url("/archivos/{$a['id']}");
                    $esFoto = Subida::esFoto($a['mime_type']);
                    ?>
                    <li class="adjunto">
                        <a class="adjunto-vista" href="<?= $url ?>" target="_blank" tabindex="-1" aria-hidden="true">
                            <?php if ($esFoto): ?>
                                <img src="<?= $url ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <svg class="icon" aria-hidden="true"><use href="#i-file"/></svg>
                                <span>PDF</span>
                            <?php endif; ?>
                        </a>
                        <div class="adjunto-info">
                            <a class="adjunto-nombre" href="<?= $url ?>" target="_blank">
                                <?= View::e($a['nombre_original']) ?><span class="sr-only"> (se abre en otra pestaña)</span>
                            </a>
                            <span class="adjunto-meta">
                                <?= View::e(Archivo::TIPOS[$a['tipo']] ?? $a['tipo']) ?> ·
                                <?= View::e(Formato::bytes((int) $a['tamano_bytes'])) ?> ·
                                <?= View::e(Formato::fechaHora($a['creado_en'])) ?><?= $a['subido'] ? ' · ' . View::e($a['subido']) : '' ?>
                            </span>
                            <?php if ($a['descripcion']): ?>
                                <span class="adjunto-desc"><?= View::e($a['descripcion']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="adjunto-acciones">
                            <a class="btn-link" href="<?= $url ?>?descargar=1">
                                <svg class="icon" aria-hidden="true"><use href="#i-download"/></svg>
                                Descargar<span class="sr-only"> <?= View::e($a['nombre_original']) ?></span>
                            </a>
                            <?php if ($puedeSubir): ?>
                                <form method="POST" action="<?= View::url("/archivos/{$a['id']}/eliminar") ?>"
                                      data-confirmar="¿Eliminar «<?= View::e($a['nombre_original']) ?>»? El archivo se borra y no se puede recuperar."
                                      data-confirmar-boton="Eliminar">
                                    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                                    <button class="btn-link btn-link--muted" type="submit">
                                        <svg class="icon" aria-hidden="true"><use href="#i-trash"/></svg>
                                        Eliminar<span class="sr-only"> <?= View::e($a['nombre_original']) ?></span>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($puedeSubir): ?>
            <form class="form adjuntos-form" method="POST" action="<?= View::url($accion) ?>" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                <h3 class="informe-titulo">Subir un archivo</h3>

                <?php if ($errores): ?>
                    <div class="notice notice--error" role="alert">
                        <svg class="icon" aria-hidden="true"><use href="#i-alert"/></svg>
                        <p>Revisa <?= count($errores) === 1 ? 'el campo marcado' : 'los campos marcados' ?>. Si ya elegiste el archivo, vuelve a elegirlo.</p>
                    </div>
                <?php endif; ?>

                <div class="field">
                    <label for="adj-tipo">¿Qué documento es? <span class="req" aria-hidden="true">*</span></label>
                    <select id="adj-tipo" name="tipo" required aria-describedby="adj-tipo-error" <?= $campoError('tipo') ?>>
                        <?php foreach ($tipos as $valor => $texto): ?>
                            <option value="<?= View::e($valor) ?>" <?= $valor === $tipoElegido ? 'selected' : '' ?>><?= View::e($texto) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="field-error" id="adj-tipo-error" <?= isset($errores['tipo']) ? '' : 'hidden' ?>><?= View::e($errores['tipo'] ?? '') ?></p>
                </div>

                <div class="field">
                    <label for="adj-archivo">Archivo <span class="req" aria-hidden="true">*</span></label>
                    <input type="file" id="adj-archivo" name="archivo" required
                           accept="application/pdf,image/jpeg,image/png,image/webp"
                           aria-describedby="adj-archivo-ayuda adj-archivo-error" <?= $campoError('archivo') ?>>
                    <p class="field-help" id="adj-archivo-ayuda">
                        <?= View::e($formatos) ?>. PDF hasta <?= View::e($maxDoc) ?>; fotos hasta <?= View::e($maxFoto) ?>.
                    </p>
                    <p class="field-error" id="adj-archivo-error" <?= isset($errores['archivo']) ? '' : 'hidden' ?>><?= View::e($errores['archivo'] ?? '') ?></p>
                </div>

                <div class="field">
                    <label for="adj-descripcion">Descripción <span class="opcional">(opcional)</span></label>
                    <input type="text" id="adj-descripcion" name="descripcion" maxlength="255" autocomplete="off"
                           value="<?= View::e($old['descripcion'] ?? '') ?>" aria-describedby="adj-descripcion-error" <?= $campoError('descripcion') ?>
                           placeholder="Ej.: acta firmada por el docente y el custodio">
                    <p class="field-error" id="adj-descripcion-error" <?= isset($errores['descripcion']) ? '' : 'hidden' ?>><?= View::e($errores['descripcion'] ?? '') ?></p>
                </div>

                <div class="form-acciones">
                    <button class="btn btn-primary" type="submit">
                        <svg class="icon" aria-hidden="true"><use href="#i-paperclip"/></svg>
                        Subir archivo
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </section>
</div>
