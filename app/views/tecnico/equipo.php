<?php
/**
 * Ficha técnica del equipo para el Técnico y Administrador:
 * 1. Placa patrimonial + Especificaciones + Componentes instalados.
 * 2. ¿De quién es? (Custodio actual + Ubicación física + Fotos).
 * 3. Botones de Imprimir Etiqueta QR, Programar Preventivo y Atender Equipo.
 * 4. Historial técnico siempre a la vista.
 */
$e = [View::class, 'e'];
[$estadoTexto, $estadoClase] = Formato::ESTADOS[$equipo['estado_operativo']];
$qrUrl  = View::absoluteUrl('/q/' . $equipo['qr_token']);
$modelo = implode(' · ', array_filter([
    trim(($equipo['marca'] ?? '') . ' ' . ($equipo['modelo'] ?? '')),
    $equipo['numero_serie'] ? 'Serie ' . $equipo['numero_serie'] : '',
]));

$valores = $old + [
    'tipo'                  => $abierto['tipo'] ?? 'correctivo',
    'problema_reportado'    => $abierto['problema_reportado'] ?? '',
    'diagnostico'           => $abierto['diagnostico'] ?? '',
    'trabajo_realizado'     => $abierto['trabajo_realizado'] ?? '',
    'resultado'             => $abierto['resultado'] ?? '',
    'recomendaciones'       => $abierto['recomendaciones'] ?? '',
    'costo_mano_obra'       => $abierto['costo_mano_obra'] ?? '0.00',
    'proximo_mantenimiento' => $abierto['proximo_mantenimiento'] ?? '',
    'estado_operativo'      => 'operativo',
];

$abrirForm = !empty($errores) || !empty($old);
?>
<a class="volver" href="<?= View::url('/tecnico') ?>">
    <svg class="icon" aria-hidden="true"><use href="#i-arrow-left"/></svg>
    Volver a bandeja de soporte y lector QR
</a>

<div class="page-head">
    <div>
        <h1 class="section-title">Ficha técnica · <?= $e($equipo['codigo']) ?></h1>
        <p class="section-note"><?= $e($equipo['nombre']) ?> — custodio, componentes internos e historial técnico.</p>
    </div>
    <div style="display: flex; flex-wrap: wrap; gap: var(--s-2);">
        <a class="btn btn-outline" href="<?= View::url('/tecnico/equipos/' . (int) $equipo['id'] . '/etiqueta') ?>">
            <svg class="icon" aria-hidden="true"><use href="#i-qr"/></svg>
            Imprimir etiqueta QR
        </a>
        <?php if ($puedeIntervenir): ?>
            <button class="btn btn-outline" type="button" id="btn-toggle-preventivo">
                <svg class="icon" aria-hidden="true"><use href="#i-calendar"/></svg>
                Programar preventivo
            </button>
            <button class="btn btn-primary" type="button" id="btn-atender-equipo"
                    aria-expanded="<?= $abrirForm ? 'true' : 'false' ?>" aria-controls="caja-form-mantenimiento">
                <svg class="icon" aria-hidden="true"><use href="#i-wrench"/></svg>
                <span><?= $abrirForm ? 'Ocultar formulario técnico' : 'Atender equipo / Llenar informe' ?></span>
            </button>
        <?php endif; ?>
    </div>
</div>

<?php require APP_PATH . '/views/partials/flash.php'; ?>

<div class="tec-ficha-grid">
    <!-- 1. LA PLACA PATRIMONIAL + ESPECIFICACIONES + COMPONENTES ACTUALES -->
    <article class="tag">
        <div class="tag-band"><span>ISTSGA</span><span>Propiedad del instituto</span></div>
        <div class="tag-main">
            <div class="tag-id">
                <p class="tag-code"><?= $e($equipo['codigo']) ?></p>
                <?php if ($equipo['codigo_patrimonial']): ?>
                    <p class="tag-patr">Patrimonial <?= $e($equipo['codigo_patrimonial']) ?></p>
                <?php endif; ?>
            </div>
            <div class="tag-qr" data-qr="<?= $e($qrUrl) ?>" role="img"
                 aria-label="Código QR del equipo <?= $e($equipo['codigo']) ?>"></div>
        </div>
        <h2 class="tag-name"><?= $e($equipo['nombre']) ?></h2>
        <?php if ($modelo): ?>
            <p class="tag-model"><?= $e($modelo) ?></p>
        <?php endif; ?>

        <div class="stamps">
            <p class="stamp stamp--<?= $e($estadoClase) ?>"><?= $e($estadoTexto) ?></p>
        </div>

        <dl class="tag-sheet">
            <div><dt>Categoría</dt><dd><?= $e($equipo['categoria']) ?></dd></div>
            <?php if ($equipo['area_propietaria']): ?>
                <div><dt>Área propietaria</dt><dd><?= $e($equipo['area_propietaria']) ?></dd></div>
            <?php endif; ?>
            <?php if ($equipo['fecha_fin_garantia']): ?>
                <div><dt>Garantía hasta</dt><dd><?= $e(Formato::fecha($equipo['fecha_fin_garantia'])) ?></dd></div>
            <?php endif; ?>
            <?php foreach ($specs as $s): ?>
                <div><dt><?= $e($s['atributo']) ?></dt><dd><?= $e($s['valor']) ?></dd></div>
            <?php endforeach; ?>
        </dl>

        <div class="tec-comp-box">
            <h3 class="tec-comp-title">Componentes internos registrados</h3>
            <?php if (!$componentes): ?>
                <p class="tec-sub">Aún no se han desglosado componentes internos para este equipo.</p>
            <?php else: ?>
                <ul class="tec-comp-list">
                    <?php foreach ($componentes as $c): ?>
                        <li class="<?= $c['fecha_retiro'] ? 'is-retirado' : '' ?>">
                            <div>
                                <strong><?= $e($c['tipo']) ?>:</strong>
                                <?= $e(implode(' · ', array_filter([$c['marca'], $c['modelo'], $c['capacidad']]))) ?>
                                <?php if ($c['numero_serie']): ?>
                                    <span class="tec-sub num">S/N: <?= $e($c['numero_serie']) ?></span>
                                <?php endif; ?>
                            </div>
                            <span class="sello sello--<?= $c['fecha_retiro'] ? 'inactivo' : 'activo' ?>">
                                <?= $c['fecha_retiro'] ? 'Retirado (' . $e(Formato::fecha($c['fecha_retiro'])) . ')' : 'Instalado' ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </article>

    <!-- 2. ¿DE QUIÉN ES Y DÓNDE ESTÁ? + EVIDENCIAS FOTOGRÁFICAS -->
    <section class="panel tec-custodio" aria-labelledby="t-custodio">
        <h2 class="sub-title" id="t-custodio">
            <svg class="icon" aria-hidden="true"><use href="#i-user-check"/></svg>
            ¿De quién es este equipo? (Custodio actual)
        </h2>

        <?php if ($equipo['custodio_id']): ?>
            <div class="tec-responsable">
                <p class="tec-responsable-nombre"><?= $e($equipo['custodio_nombre']) ?></p>
                <p class="tec-responsable-meta">
                    <?= $e($equipo['custodio_cargo'] ?: 'Personal institucional') ?>
                    <?= $equipo['custodio_area'] ? ' · ' . $e($equipo['custodio_area']) : '' ?>
                </p>
            </div>
            <dl class="datos">
                <div><dt>Cédula</dt><dd class="num"><?= $e($equipo['custodio_cedula'] ?: '—') ?></dd></div>
                <div><dt>Correo</dt><dd><?= $e($equipo['custodio_correo'] ?: '—') ?></dd></div>
                <div><dt>Teléfono</dt><dd class="num"><?= $e($equipo['custodio_telefono'] ?: '—') ?></dd></div>
                <div><dt>Asignado desde</dt><dd class="num"><?= $e(Formato::fechaHora($equipo['fecha_entrega'])) ?></dd></div>
            </dl>
        <?php else: ?>
            <div class="notice notice--ok" style="margin-top: var(--s-4);">
                <svg class="icon" aria-hidden="true"><use href="#i-pin"/></svg>
                <p><strong>Sin custodio individual asignado.</strong> El equipo está asignado a la ubicación o en bodega.</p>
            </div>
        <?php endif; ?>

        <h3 class="sub-title tec-ubic-titulo">
            <svg class="icon" aria-hidden="true"><use href="#i-pin"/></svg>
            Ubicación física
        </h3>
        <dl class="datos">
            <div><dt>Sede</dt><dd><?= $e($equipo['sede'] ?: '—') ?></dd></div>
            <div><dt>Aula / Lab</dt><dd><strong><?= $e($equipo['ubicacion'] ?: 'Sin ubicación') ?></strong></dd></div>
            <div>
                <dt>Edificio / Piso</dt>
                <dd><?= $e(implode(' · ', array_filter([$equipo['edificio'], $equipo['piso'] ? 'Piso ' . $equipo['piso'] : null])) ?: '—') ?></dd>
            </div>
        </dl>

        <?php if ($archivos): ?>
            <h3 class="sub-title tec-ubic-titulo">Fotografías de evidencia (<?= count($archivos) ?>)</h3>
            <div class="tec-galeria">
                <?php foreach ($archivos as $ar): ?>
                    <a class="tec-foto-card" href="<?= View::url('/archivos/' . (int) $ar['id']) ?>" target="_blank" rel="noopener">
                        <img src="<?= View::url('/archivos/' . (int) $ar['id']) ?>" alt="<?= $e($ar['descripcion'] ?: $ar['nombre_original']) ?>" loading="lazy">
                        <span><?= $e(Formato::fechaHora($ar['creado_en'])) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<!-- 3. AVISO DE SOLICITUD ABIERTA + PREVENTIVO + FORMULARIO DESPLEGABLE + HISTORIAL -->
<div class="tec-ficha-grid" id="intervencion" style="margin-top: var(--s-6);">

    <?php if ($abierto): ?>
        <div class="tec-alerta-abierta" style="margin-top: 0;">
            <p><strong>Reporte / Solicitud abierta:</strong> <?= $e($abierto['problema_reportado'] ?: 'Mantenimiento programado.') ?></p>
            <p class="tec-sub">
                Estado: <strong><?= $abierto['estado'] === 'en_proceso' ? 'En proceso' : 'Pendiente de iniciar' ?></strong>
                <?= $abierto['tecnico'] ? ' · Técnico: ' . $e($abierto['tecnico']) : '' ?>
            </p>
            <?php if ($puedeIntervenir): ?>
                <div style="display: flex; flex-wrap: wrap; gap: var(--s-2); margin-top: var(--s-3);">
                    <button class="btn btn-primary" type="button" data-abrir-mantenimiento>
                        <svg class="icon" aria-hidden="true"><use href="#i-wrench"/></svg>
                        Atender equipo y llenar informe
                    </button>
                    <?php if ($abierto['estado'] === 'programado'): ?>
                        <form method="POST" action="<?= View::url('/tecnico/equipos/' . (int) $equipo['id'] . '/iniciar') ?>">
                            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                            <button class="btn btn-outline" type="submit">
                                Marcar equipo «En mantenimiento»
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <details style="margin-top: var(--s-3);">
                    <summary class="btn-link btn-link--muted" style="padding: 0;">¿Solicitud duplicada o falsa alarma? Anular solicitud</summary>
                    <form method="POST" action="<?= View::url('/tecnico/equipos/' . (int) $equipo['id'] . '/cancelar') ?>"
                          style="display: flex; gap: var(--s-2); margin-top: var(--s-2); flex-wrap: wrap;">
                        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                        <input type="text" name="motivo_cancelacion" required minlength="5" maxlength="255"
                               placeholder="Motivo de anulación (ej. reporte duplicado)" style="flex: 1; min-width: 220px;">
                        <button class="btn btn-danger" type="submit">Anular</button>
                    </form>
                </details>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- FORMULARIO DESPLEGABLE PARA PROGRAMAR PREVENTIVO -->
    <?php if ($puedeIntervenir): ?>
        <section class="panel" id="caja-form-preventivo" hidden>
            <h2 class="sub-title">Programar próximo mantenimiento preventivo</h2>
            <form class="form" style="margin-top: var(--s-4); max-width: none;" method="POST"
                  action="<?= View::url('/tecnico/equipos/' . (int) $equipo['id'] . '/programar') ?>">
                <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                <div class="qr-pin-grid">
                    <div class="field">
                        <label for="prev-fecha">Fecha programada <span class="req">*</span></label>
                        <input type="date" id="prev-fecha" name="fecha_programada" required min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="field">
                        <label for="prev-tec">Técnico asignado <span class="opcional">(opcional)</span></label>
                        <select id="prev-tec" name="tecnico_id">
                            <option value="">— Sin asignar aún —</option>
                            <?php foreach (($tecnicos ?? []) as $tId => $tNombre): ?>
                                <option value="<?= (int) $tId ?>"><?= $e($tNombre) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="field">
                    <label for="prev-det">Tarea preventiva a realizar <span class="opcional">(opcional)</span></label>
                    <input type="text" id="prev-det" name="detalle_preventivo" maxlength="255"
                           placeholder="Ej.: Limpieza interna, revisión de fuentes y actualización.">
                </div>
                <div class="form-acciones">
                    <button class="btn btn-primary" type="submit">Guardar programación</button>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <!-- FORMULARIO TÉCNICO (OCULTO HASTA PULSAR "ATENDER EQUIPO") -->
    <?php if ($puedeIntervenir): ?>
        <section class="panel" id="caja-form-mantenimiento" aria-labelledby="t-reporte-tec" <?= $abrirForm ? '' : 'hidden' ?>>
            <h2 class="section-title" id="t-reporte-tec">
                <?= $abierto ? 'Completar mantenimiento y cambio de piezas' : 'Registrar mantenimiento técnico' ?>
            </h2>
            <p class="section-note">Completa el diagnóstico, los componentes o repuestos reemplazados y el estado final del equipo.</p>

            <form class="form" style="margin-top: var(--s-5); max-width: none;" method="POST"
                  action="<?= View::url('/tecnico/equipos/' . (int) $equipo['id'] . '/reporte') ?>"
                  enctype="multipart/form-data" novalidate data-form-validar>
                <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">

                <div class="field">
                    <label for="campo-tipo">Tipo de mantenimiento <span class="req">*</span></label>
                    <select id="campo-tipo" name="tipo" required>
                        <?php foreach ($tiposMant as $k => $v): ?>
                            <option value="<?= $e($k) ?>" <?= $valores['tipo'] === $k ? 'selected' : '' ?>><?= $e(ucfirst($v)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (!$abierto): ?>
                    <div class="field">
                        <label for="campo-problema">Falla o motivo observado <span class="req">*</span></label>
                        <textarea id="campo-problema" name="problema_reportado" rows="2" required minlength="10" maxlength="2000"
                                  placeholder="Motivo por el que se interviene el equipo."><?= $e((string) $valores['problema_reportado']) ?></textarea>
                        <p class="field-error" id="campo-problema-error" <?= isset($errores['problema_reportado']) ? '' : 'hidden' ?>>
                            <?= $e($errores['problema_reportado'] ?? '') ?>
                        </p>
                    </div>
                <?php endif; ?>

                <div class="field">
                    <label for="campo-diagnostico">Diagnóstico técnico <span class="req">*</span></label>
                    <textarea id="campo-diagnostico" name="diagnostico" rows="3" required minlength="10" maxlength="2000"
                              placeholder="Detalla qué revisaste y cuál fue la causa de la falla."><?= $e((string) $valores['diagnostico']) ?></textarea>
                    <p class="field-error" id="campo-diagnostico-error" <?= isset($errores['diagnostico']) ? '' : 'hidden' ?>>
                        <?= $e($errores['diagnostico'] ?? '') ?>
                    </p>
                </div>

                <div class="field">
                    <label for="campo-trabajo">Trabajo realizado <span class="req">*</span></label>
                    <textarea id="campo-trabajo" name="trabajo_realizado" rows="3" required minlength="10" maxlength="2000"
                              placeholder="Reparación, limpieza, configuración o sustitución ejecutada."><?= $e((string) $valores['trabajo_realizado']) ?></textarea>
                    <p class="field-error" id="campo-trabajo-error" <?= isset($errores['trabajo_realizado']) ? '' : 'hidden' ?>>
                        <?= $e($errores['trabajo_realizado'] ?? '') ?>
                    </p>
                </div>

                <!-- BLOQUE DE CAMBIO DE COMPONENTES INTERNOS -->
                <fieldset class="tec-bloque-piezas">
                    <legend>Cambio de componentes internos <span class="opcional">(opcional)</span></legend>

                    <?php if ($componentesActivos): ?>
                        <p class="field-help" style="margin-bottom: var(--s-2);">Marca los componentes dañados o antiguos que <strong>retiraste</strong> en este mantenimiento:</p>
                        <div class="tec-checks">
                            <?php foreach ($componentesActivos as $ca): ?>
                                <label class="tec-check-item">
                                    <input type="checkbox" name="retirar_componentes[]" value="<?= (int) $ca['id'] ?>">
                                    <span>
                                        <strong>Retirar <?= $e($ca['tipo']) ?>:</strong>
                                        <?= $e(implode(' · ', array_filter([$ca['marca'], $ca['modelo'], $ca['capacidad'], $ca['numero_serie'] ? 'S/N ' . $ca['numero_serie'] : null]))) ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <p class="field-help" style="margin-top: var(--s-3);">Si <strong>instalaste un componente nuevo</strong> en el equipo, regístralo aquí:</p>
                    <div id="lista-nuevos-componentes" class="tec-filas-dinamicas">
                        <div class="tec-fila-comp">
                            <input type="text" name="comp_tipo[]" placeholder="Tipo (ej. Disco SSD, RAM, Batería)">
                            <input type="text" name="comp_marca[]" placeholder="Marca">
                            <input type="text" name="comp_modelo[]" placeholder="Modelo">
                            <input type="text" name="comp_capacidad[]" placeholder="Capacidad (ej. 512 GB)">
                            <input type="text" name="comp_serie[]" placeholder="N.º de serie">
                            <input type="text" name="comp_obs[]" placeholder="Observación">
                        </div>
                    </div>
                    <button class="btn btn-outline" type="button" id="btn-agregar-comp" style="margin-top: var(--s-2);">
                        <svg class="icon" aria-hidden="true"><use href="#i-plus"/></svg>
                        Agregar otro componente instalado
                    </button>
                </fieldset>

                <!-- BLOQUE DE REPUESTOS / INSUMOS USADOS -->
                <fieldset class="tec-bloque-piezas">
                    <legend>Repuestos o insumos utilizados <span class="opcional">(opcional)</span></legend>
                    <div id="lista-repuestos" class="tec-filas-dinamicas">
                        <div class="tec-fila-rep">
                            <select name="rep_id[]">
                                <option value="">— Elegir repuesto del catálogo —</option>
                                <?php foreach ($catalogoRepuestos as $cr): ?>
                                    <option value="<?= (int) $cr['id'] ?>"><?= $e($cr['nombre'] . ' (' . $cr['codigo'] . ' · Stock: ' . $cr['stock'] . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="rep_nuevo[]" placeholder="O escribe un repuesto nuevo">
                            <input type="number" name="rep_cantidad[]" step="0.01" min="0.01" value="1" placeholder="Cant.">
                            <input type="number" name="rep_costo[]" step="0.01" min="0" value="0.00" placeholder="Costo unit. ($)">
                            <input type="text" name="rep_detalle[]" placeholder="Detalle (ej. reemplazo por desgaste)">
                        </div>
                    </div>
                    <button class="btn btn-outline" type="button" id="btn-agregar-rep" style="margin-top: var(--s-2);">
                        <svg class="icon" aria-hidden="true"><use href="#i-plus"/></svg>
                        Agregar otro repuesto
                    </button>
                </fieldset>

                <div class="qr-pin-grid">
                    <div class="field">
                        <label for="campo-costo">Costo mano de obra ($) <span class="opcional">(opcional)</span></label>
                        <input type="number" id="campo-costo" name="costo_mano_obra" step="0.01" min="0"
                               value="<?= $e((string) $valores['costo_mano_obra']) ?>">
                    </div>
                    <div class="field">
                        <label for="campo-proximo">Próximo mantenimiento <span class="opcional">(opcional)</span></label>
                        <input type="date" id="campo-proximo" name="proximo_mantenimiento"
                               value="<?= $e((string) $valores['proximo_mantenimiento']) ?>">
                    </div>
                </div>

                <div class="field">
                    <label for="campo-resultado">Resultado y entrega <span class="opcional">(opcional)</span></label>
                    <input type="text" id="campo-resultado" name="resultado" maxlength="500"
                           value="<?= $e((string) $valores['resultado']) ?>"
                           placeholder="Ej.: Queda funcionando correctamente tras cambio de repuesto.">
                </div>

                <div class="field">
                    <label for="campo-recomendaciones">Recomendaciones <span class="opcional">(opcional)</span></label>
                    <textarea id="campo-recomendaciones" name="recomendaciones" rows="2" maxlength="1000"><?= $e((string) $valores['recomendaciones']) ?></textarea>
                </div>

                <div class="field">
                    <label for="tec-fotos">Fotos del mantenimiento realizado <span class="opcional">(opcional, hasta 3 fotos)</span></label>
                    <input type="file" id="tec-fotos" name="fotos[]" accept="image/jpeg,image/png,image/webp" multiple>
                </div>

                <div class="field">
                    <label for="campo-estado-op">¿En qué estado operativo queda el equipo? <span class="req">*</span></label>
                    <select id="campo-estado-op" name="estado_operativo" required>
                        <?php foreach ($estadosSalida as $k => $v): ?>
                            <option value="<?= $e($k) ?>" <?= $valores['estado_operativo'] === $k ? 'selected' : '' ?>><?= $e($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-acciones">
                    <button class="btn btn-primary" type="submit">Guardar mantenimiento, piezas y estado</button>
                    <button class="btn btn-text" type="button" id="btn-cerrar-mantenimiento">Ocultar formulario</button>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <!-- 4. HISTORIAL TÉCNICO CON REPUESTOS USADOS (SIEMPRE VISIBLE) -->
    <section class="panel" aria-labelledby="t-hist-mant">
        <h2 class="section-title" id="t-hist-mant">Historial de mantenimientos</h2>
        <p class="section-note">Reportes QR, diagnósticos y piezas reemplazadas en <?= $e($equipo['codigo']) ?>.</p>

        <?php if (!$mantenimientos): ?>
            <p class="report-empty" style="margin-top: var(--s-4);">Este equipo aún no registra mantenimientos.</p>
        <?php else: ?>
            <ol class="timeline" style="margin-top: var(--s-5);">
                <?php foreach ($mantenimientos as $m): ?>
                    <?php
                    $mId      = (int) $m['id'];
                    $icono    = match ($m['estado']) { 'finalizado' => 'check', 'en_proceso' => 'wrench', default => 'alert' };
                    $variante = $m['estado'] === 'programado' && $m['tipo'] === 'diagnostico' ? 'warn' : '';
                    $fechaRef = $m['fecha_fin'] ?: $m['fecha_inicio'] ?: $m['creado_en'];
                    $reps     = $repuestosUsados[$mId] ?? [];
                    ?>
                    <li>
                        <span class="tl-icon<?= $variante ? ' tl-icon--' . $variante : '' ?>" aria-hidden="true">
                            <svg class="icon"><use href="#i-<?= $icono ?>"/></svg>
                        </span>
                        <div>
                            <p class="tl-what">
                                <?= $e(ucfirst(Formato::TIPOS_MANTENIMIENTO[$m['tipo']] ?? $m['tipo'])) ?>
                                · <?= $e(str_replace('_', ' ', $m['estado'])) ?>
                            </p>
                            <?php if ($m['problema_reportado']): ?>
                                <p class="tl-detail"><strong>Reporte:</strong> <?= $e($m['problema_reportado']) ?></p>
                            <?php endif; ?>
                            <?php if ($m['diagnostico']): ?>
                                <p class="tl-detail"><strong>Diagnóstico:</strong> <?= $e($m['diagnostico']) ?></p>
                            <?php endif; ?>
                            <?php if ($m['trabajo_realizado']): ?>
                                <p class="tl-detail"><strong>Trabajo:</strong> <?= $e($m['trabajo_realizado']) ?></p>
                            <?php endif; ?>
                            <?php if ($reps): ?>
                                <ul class="tec-hist-repuestos">
                                    <?php foreach ($reps as $r): ?>
                                        <li>
                                            Pieza/Repuesto: <strong><?= $e($r['nombre']) ?></strong>
                                            (×<?= (float) $r['cantidad'] ?><?= $r['detalle'] ? ' · ' . $e($r['detalle']) : '' ?>)
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <time class="tl-when">
                                <?= $e(Formato::fechaHora($fechaRef)) ?>
                                <?= $m['tecnico'] ? ' · Técnico: ' . $e($m['tecnico']) : '' ?>
                            </time>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>
</div>