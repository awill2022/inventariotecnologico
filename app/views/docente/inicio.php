<?php
/**
 * Página principal del docente (rol: consulta).
 * @var array  $usuario
 * @var string $saludo
 * @var string $hoyIso
 * @var string $hoyTexto
 * @var array  $etiquetas  datos de EtiquetaEquipo::desde()
 * @var int    $atencion   equipos que no están operativos
 * @var array  $historial  eventos de EventoHistorial::desde()
 * @var array  $reporte    ok, error, errores, old del formulario de falla
 * @var string $csrf
 */
$total   = count($etiquetas);
$e       = [View::class, 'e'];
?>

<!-- ============ SALUDO + BÚSQUEDA ============ -->
<section class="welcome" aria-labelledby="saludo">
    <div class="wrap welcome-grid">
        <div class="welcome-copy">
            <h1 id="saludo"><?= $e($saludo) ?>, <?= $e($usuario['primer_nombre']) ?></h1>
            <p class="welcome-sub">
                <time datetime="<?= $e($hoyIso) ?>"><?= $e($hoyTexto) ?></time> ·
                <?php if ($total === 0): ?>
                    Aún no tienes equipos asignados.
                <?php else: ?>
                    Tienes <strong><?= $e(Formato::plural($total, 'equipo', 'equipos')) ?></strong> a tu cargo
                    <?php if ($atencion > 0): ?>
                        y <strong><?= $atencion === 1 ? '1 necesita' : $atencion . ' necesitan' ?> atención</strong>.
                    <?php else: ?>
                        y todos están operativos.
                    <?php endif; ?>
                <?php endif; ?>
            </p>
        </div>

        <form class="lookup" id="form-buscar" role="search" autocomplete="off">
            <label for="buscar" class="lookup-label">Buscar un equipo</label>
            <div class="lookup-row">
                <div class="lookup-field">
                    <svg class="icon" aria-hidden="true"><use href="#i-search"/></svg>
                    <input id="buscar" name="q" type="search" maxlength="80"
                           placeholder="Código, patrimonial o serie" aria-describedby="buscar-ayuda">
                </div>
                <button class="btn btn-gold" type="submit">Buscar</button>
                <button class="btn btn-ghost-light" type="button" id="btn-qr" aria-label="Escanear código QR" aria-haspopup="dialog">
                    <svg class="icon" aria-hidden="true"><use href="#i-qr"/></svg>
                    <span aria-hidden="true">QR</span>
                </button>
            </div>
            <p class="lookup-help" id="buscar-ayuda">
                <?php if ($total): ?>
                    Ejemplo: <?= $e($etiquetas[0]['codigo']) ?>. El QR está en la etiqueta del equipo.
                <?php else: ?>
                    El código y el QR están en la etiqueta del equipo.
                <?php endif; ?>
            </p>
            <p class="lookup-status" id="qr-aviso" role="status" hidden></p>
        </form>
    </div>
</section>

<?php // Avisos de otras páginas (p. ej. "tu clave quedó guardada" al volver de Mi perfil)
ob_start();
require APP_PATH . '/views/partials/flash.php';
$avisos = trim(ob_get_clean());
?>
<?php if ($avisos !== ''): ?>
    <div class="wrap avisos-pagina"><?= $avisos ?></div>
<?php endif; ?>

<!-- ============ MIS EQUIPOS (etiquetas patrimoniales) ============ -->
<section class="section section--tags" id="mis-equipos" aria-labelledby="t-equipos">
    <div class="wrap">
        <div class="section-head section-head--row">
            <div>
                <h2 id="t-equipos" class="section-title">Mis equipos</h2>
                <p class="section-note" id="conteo" role="status">
                    <?= $total ? 'Mostrando ' . $total . ' de ' . $e(Formato::plural($total, 'equipo asignado', 'equipos asignados')) . ' a ti.' : 'No tienes equipos asignados.' ?>
                </p>
            </div>
            <?php if ($total): ?>
                <div class="filters" role="group" aria-label="Filtrar equipos">
                    <button class="chip" type="button" data-filtro="todos" aria-pressed="true">Todos</button>
                    <button class="chip" type="button" data-filtro="atencion" aria-pressed="false">Requieren atención</button>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!$total): ?>
            <div class="empty">
                <svg class="icon" aria-hidden="true"><use href="#i-search"/></svg>
                <div>
                    <p class="empty-title">Todavía no tienes equipos a tu cargo.</p>
                    <p>Cuando la Unidad de Inventario te entregue un equipo, aparecerá aquí con su etiqueta, estado y ubicación.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="tags" id="lista-equipos">
                <?php foreach ($etiquetas as $i => $t): ?>
                    <article class="tag" id="<?= $e($t['dom_id']) ?>" data-codigo="<?= $e($t['codigo']) ?>"
                             data-buscar="<?= $e($t['buscar']) ?>" data-estado="<?= $e($t['estado']) ?>" tabindex="-1">
                        <div class="tag-band"><span>ISTSGA</span><span>Propiedad del instituto</span></div>
                        <div class="tag-main">
                            <div class="tag-id">
                                <p class="tag-code"><?= $e($t['codigo']) ?></p>
                                <?php if ($t['patrimonial']): ?>
                                    <p class="tag-patr">Patrimonial <?= $e($t['patrimonial']) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="tag-qr" data-qr="<?= $e($t['qr_url']) ?>" role="img"
                                 aria-label="Código QR del equipo <?= $e($t['codigo']) ?>"></div>
                            <button class="tag-qr-btn" type="button" data-ver-qr aria-haspopup="dialog"
                                    aria-label="Ver código QR del equipo <?= $e($t['codigo']) ?>">
                                <svg class="icon" aria-hidden="true"><use href="#i-qr"/></svg>
                                <span>Ver código</span>
                            </button>
                        </div>
                        <h3 class="tag-name"><?= $e($t['nombre']) ?></h3>
                        <?php if ($t['modelo']): ?>
                            <p class="tag-model"><?= $e($t['modelo']) ?></p>
                        <?php endif; ?>
                        <?php if ($t['meta']): ?>
                            <ul class="tag-meta">
                                <?php foreach ($t['meta'] as $m): ?>
                                    <li<?= $m['alerta'] ? ' class="tag-meta--alert"' : '' ?>>
                                        <svg class="icon" aria-hidden="true"><use href="#i-<?= $e($m['icono']) ?>"/></svg><?= $e($m['texto']) ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <div class="stamps">
                            <?php foreach ($t['sellos'] as $s): ?>
                                <p class="stamp stamp--<?= $e($s['clase']) ?>"><?= $e($s['texto']) ?></p>
                            <?php endforeach; ?>
                        </div>
                        <div class="tag-actions">
                            <button class="link-btn" type="button" aria-expanded="false" aria-controls="fx-<?= $i ?>">
                                <span>Ver ficha</span><svg class="icon" aria-hidden="true"><use href="#i-chevron"/></svg>
                            </button>
                            <a class="link-btn link-btn--muted" href="#reportar" data-reportar="<?= (int) $t['id'] ?>">Reportar falla</a>
                        </div>
                        <dl class="tag-sheet" id="fx-<?= $i ?>" hidden>
                            <?php foreach ($t['ficha'] as [$dt, $dd]): ?>
                                <div><dt><?= $e($dt) ?></dt><dd><?= $e($dd) ?></dd></div>
                            <?php endforeach; ?>
                        </dl>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="empty" id="sin-resultados" hidden>
                <svg class="icon" aria-hidden="true"><use href="#i-search"/></svg>
                <div>
                    <p class="empty-title" id="sin-resultados-titulo">Ningún equipo a tu cargo coincide con la búsqueda.</p>
                    <p>Revisa el código en la etiqueta o busca por número de serie. Si el equipo no es tuyo, consulta a la Unidad de Inventario.</p>
                    <button class="btn btn-outline" type="button" id="limpiar">Mostrar todos mis equipos</button>
                </div>
            </div>

            <!-- Ventana con el QR ampliado (en celulares la etiqueta muestra el botón "Ver código") -->
            <dialog class="qr-dialog" id="qr-dialog" aria-labelledby="qr-dialog-codigo">
                <div class="qr-dialog-inner">
                    <div class="tag-band"><span>ISTSGA</span><span>Propiedad del instituto</span></div>
                    <div class="qr-dialog-body">
                        <h2 class="qr-dialog-code" id="qr-dialog-codigo"></h2>
                        <p class="tag-patr" id="qr-dialog-patr"></p>
                        <div class="qr-dialog-qr" id="qr-dialog-qr"></div>
                        <p class="qr-dialog-name" id="qr-dialog-nombre"></p>
                        <p class="qr-dialog-help">Es el mismo código impreso en la etiqueta del equipo.</p>
                        <button class="btn btn-primary btn-block" type="button" data-cerrar-qr>Cerrar</button>
                    </div>
                </div>
            </dialog>
        <?php endif; ?>
    </div>
</section>

<!-- Lector de QR (lector-qr.js): abre la cámara, lee la etiqueta y lleva a su página /q/{token} -->
<dialog class="qr-dialog lector" id="lector-qr" aria-labelledby="lector-titulo" data-base-qr="<?= $e(View::url('/q/')) ?>">
    <div class="lector-body">
        <h2 class="lector-titulo" id="lector-titulo">Escanear la etiqueta</h2>
        <div class="lector-video" data-lector-camara hidden>
            <video playsinline muted aria-label="Vista de la cámara"></video>
            <span class="lector-marco" aria-hidden="true"></span>
        </div>
        <p class="lector-estado" id="lector-estado" role="status">Apunta la cámara al código QR de la etiqueta.</p>
        <label class="btn btn-outline btn-block fotos-boton" data-lector-foto hidden>
            <svg class="icon" aria-hidden="true"><use href="#i-camera"/></svg>
            Tomar foto del código
            <input type="file" accept="image/*" capture="environment" data-lector-archivo>
        </label>
        <button class="btn btn-primary btn-block" type="button" data-cerrar-lector>Cancelar</button>
    </div>
</dialog>

<!-- ============ REPORTAR FALLA + HISTORIAL ============ -->
<div class="wrap split">
    <section class="panel" id="reportar" aria-labelledby="t-reportar" tabindex="-1">
        <h2 id="t-reportar" class="section-title">Reportar una falla</h2>
        <p class="section-note">Tu reporte crea una solicitud de diagnóstico para el equipo técnico.</p>

        <?php if ($reporte['ok']): ?>
            <div class="notice notice--ok" id="reporte-ok" role="status">
                <svg class="icon" aria-hidden="true"><use href="#i-check"/></svg>
                <p><strong>Reporte enviado.</strong> <?= $e($reporte['ok']) ?></p>
            </div>
        <?php endif; ?>
        <?php if ($reporte['error']): ?>
            <div class="notice notice--error" role="alert">
                <svg class="icon" aria-hidden="true"><use href="#i-alert"/></svg>
                <p><?= $e($reporte['error']) ?></p>
            </div>
        <?php endif; ?>

        <?php if (!$total): ?>
            <p class="report-empty">Podrás reportar fallas cuando tengas equipos a tu cargo.</p>
        <?php else: ?>
            <?php require APP_PATH . '/views/partials/reporte_falla.php'; ?>
        <?php endif; ?>
    </section>

    <section class="panel" id="historial" aria-labelledby="t-historial">
        <h2 id="t-historial" class="section-title">Historial reciente</h2>
        <p class="section-note">Entregas, movimientos y mantenimientos de tus equipos.</p>

        <?php if (!$historial): ?>
            <p class="report-empty">Aún no hay movimientos registrados para tus equipos.</p>
        <?php else: ?>
            <ol class="timeline">
                <?php foreach ($historial as $h): ?>
                    <li>
                        <span class="tl-icon<?= $h['variante'] ? ' tl-icon--' . $e($h['variante']) : '' ?>" aria-hidden="true">
                            <svg class="icon"><use href="#i-<?= $e($h['icono']) ?>"/></svg>
                        </span>
                        <div>
                            <p class="tl-what"><?= $e($h['titulo']) ?></p>
                            <p class="tl-detail">
                                <a class="tag-ref" href="#eq-<?= $e($h['codigo']) ?>"><?= $e($h['codigo']) ?></a>
                                · <?= $e($h['detalle']) ?>
                            </p>
                            <time class="tl-when" datetime="<?= $e($h['fecha_iso']) ?>"><?= $e($h['fecha']) ?></time>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>
</div>
