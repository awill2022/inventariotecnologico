<?php
/**
 * Bandeja del técnico: escáner QR, buscador rápido de equipos y cola de mantenimientos/reportes QR.
 * @var string $q
 * @var string $estado
 * @var array  $conteos  pendientes, en_proceso, finalizados
 * @var array  $bandeja  filas de Mantenimiento::bandeja()
 * @var array  $equipos  filas de Equipo::buscarParaTecnico() cuando hay búsqueda
 */
$e = [View::class, 'e'];
$chips = [
    'abiertos'   => 'Por atender (' . ($conteos['pendientes'] + $conteos['en_proceso']) . ')',
    'programado' => 'Pendientes (' . $conteos['pendientes'] . ')',
    'en_proceso' => 'En proceso (' . $conteos['en_proceso'] . ')',
    'finalizado' => 'Finalizados (' . $conteos['finalizados'] . ')',
];
?>
<div class="page-head">
    <div>
        <h1 class="section-title">Soporte técnico y lectura QR</h1>
        <p class="section-note">Escanea el código QR de cualquier equipo para saber a quién pertenece y registrar su mantenimiento o cambio de piezas.</p>
    </div>
    <button class="btn btn-gold" type="button" id="btn-abrir-escaner">
        <svg class="icon" aria-hidden="true"><use href="#i-qr"/></svg>
        Escanear QR de equipo
    </button>
</div>

<?php require APP_PATH . '/views/partials/flash.php'; ?>

<div class="filtros">
    <form class="buscar" method="GET" action="<?= View::url('/tecnico') ?>" role="search">
        <label class="sr-only" for="q">Buscar por código, serie o custodio</label>
        <div class="buscar-campo">
            <svg class="icon" aria-hidden="true"><use href="#i-search"/></svg>
            <input type="text" id="q" name="q" value="<?= $e($q) ?>" maxlength="100"
                   placeholder="Código (EQ-…), serie, cédula o nombre del custodio" autocomplete="off">
        </div>
        <?php if ($estado !== 'abiertos'): ?>
            <input type="hidden" name="estado" value="<?= $e($estado) ?>">
        <?php endif; ?>
        <button class="btn btn-primary" type="submit">Buscar</button>
    </form>

    <ul class="chips" aria-label="Filtrar mantenimientos">
        <?php foreach ($chips as $valor => $texto): ?>
            <?php $href = View::url('/tecnico') . '?' . http_build_query(array_filter([
                'q'      => $q,
                'estado' => $valor === 'abiertos' ? '' : $valor,
            ], fn ($v) => $v !== '')); ?>
            <li>
                <a class="chip" href="<?= $e(rtrim($href, '?')) ?>" <?= $estado === $valor ? 'aria-current="true"' : '' ?>>
                    <?= $e($texto) ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<?php if ($q !== ''): ?>
    <section class="tec-seccion" aria-labelledby="t-equipos-busq">
        <h2 class="sub-title" id="t-equipos-busq">Equipos que coinciden con «<?= $e($q) ?>»</h2>
        <?php if (!$equipos): ?>
            <p class="section-note">No se encontraron equipos con ese código, serie o custodio.</p>
        <?php else: ?>
            <div class="tabla-caja">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th scope="col">Código</th>
                            <th scope="col">Equipo</th>
                            <th scope="col">Custodio actual (De quién es)</th>
                            <th scope="col">Ubicación</th>
                            <th scope="col">Estado</th>
                            <th scope="col" class="acciones-col">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($equipos as $eq): ?>
                            <?php [$estTexto, $estClase] = Formato::ESTADOS[$eq['estado_operativo']]; ?>
                            <tr>
                                <th scope="row" class="principal">
                                    <a class="tag-ref" href="<?= View::url('/tecnico/equipos/' . (int) $eq['id']) ?>">
                                        <?= $e($eq['codigo']) ?>
                                    </a>
                                </th>
                                <td data-label="Equipo">
                                    <?= $e($eq['nombre']) ?>
                                    <span class="tec-sub"><?= $e(trim($eq['marca'] . ' ' . $eq['modelo'])) ?></span>
                                </td>
                                <td data-label="Custodio">
                                    <?php if ($eq['custodio']): ?>
                                        <strong><?= $e($eq['custodio']) ?></strong>
                                        <span class="tec-sub"><?= $e($eq['custodio_cedula'] . ($eq['custodio_area'] ? ' · ' . $eq['custodio_area'] : '')) ?></span>
                                    <?php else: ?>
                                        <span class="sin-dato">Sin custodio asignado</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Ubicación"><?= $e($eq['ubicacion'] ?? '—') ?></td>
                                <td data-label="Estado">
                                    <span class="stamp stamp--<?= $e($estClase) ?>"><?= $e($estTexto) ?></span>
                                </td>
                                <td class="acciones">
                                    <a class="btn-link" href="<?= View::url('/tecnico/equipos/' . (int) $eq['id']) ?>">
                                        <svg class="icon" aria-hidden="true"><use href="#i-wrench"/></svg>
                                        Ver ficha y mantenimiento
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<section class="tec-seccion" aria-labelledby="t-bandeja">
    <h2 class="sub-title" id="t-bandeja">Bandeja de mantenimientos y fallas reportadas por QR</h2>

    <?php if (!$bandeja): ?>
        <div class="empty">
            <svg class="icon" aria-hidden="true"><use href="#i-check"/></svg>
            <div>
                <p class="empty-title">No hay mantenimientos en esta vista.</p>
                <p>Puedes escanear el QR de cualquier equipo o usar el buscador superior para abrir su ficha técnica.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="tabla-caja">
            <table class="tabla">
                <thead>
                    <tr>
                        <th scope="col">Equipo</th>
                        <th scope="col">Custodio / Ubicación</th>
                        <th scope="col">Tipo y detalle</th>
                        <th scope="col">Fecha</th>
                        <th scope="col">Estado</th>
                        <th scope="col" class="acciones-col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bandeja as $m): ?>
                        <?php
                        [$estTexto, $estClase] = Formato::ESTADOS[$m['estado_operativo']];
                        $tipoTexto = ucfirst(Formato::TIPOS_MANTENIMIENTO[$m['tipo']] ?? $m['tipo']);
                        $detalle   = $m['trabajo_realizado'] ?: $m['diagnostico'] ?: $m['problema_reportado'] ?: 'Mantenimiento programado';
                        $fotos     = (int) ($m['fotos'] ?? 0);
                        ?>
                        <tr>
                            <th scope="row" class="principal">
                                <a class="tag-ref" href="<?= View::url('/tecnico/equipos/' . (int) $m['equipo_id']) ?>">
                                    <?= $e($m['codigo']) ?>
                                </a>
                                <span class="tec-sub"><?= $e($m['equipo_nombre']) ?></span>
                            </th>
                            <td data-label="Custodio">
                                <strong><?= $e($m['custodio'] ?: 'Sin custodio asignado') ?></strong>
                                <span class="tec-sub"><?= $e($m['ubicacion'] ?: 'Sin ubicación') ?></span>
                            </td>
                            <td data-label="Detalle">
                                <strong><?= $e($tipoTexto) ?>:</strong>
                                <?= $e(Formato::resumen($detalle, 85)) ?>
                                <?php if ($fotos > 0): ?>
                                    <span class="tec-sub">
                                        📷 <?= $e(Formato::plural($fotos, 'foto adjunta', 'fotos adjuntas')) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Fecha" class="num">
                                <?= $e(Formato::relativa($m['fecha_inicio'] ?: $m['creado_en'])) ?>
                            </td>
                            <td data-label="Estado">
                                <span class="stamp stamp--<?= $e($estClase) ?>"><?= $e($estTexto) ?></span>
                            </td>
                            <td class="acciones">
                                <a class="btn-link" href="<?= View::url('/tecnico/equipos/' . (int) $m['equipo_id']) ?>">
                                    <svg class="icon" aria-hidden="true"><use href="#i-wrench"/></svg>
                                    Atender equipo
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<!-- Ventana de escáner QR con cámara -->
<dialog class="qr-dialog tec-qr-dialog" id="dialog-escaner" aria-labelledby="escaner-titulo">
    <div class="qr-dialog-inner">
        <div class="tag-band"><span>ISTSGA</span><span>Escáner Técnico QR</span></div>
        <div class="qr-dialog-body">
            <h2 class="qr-dialog-code" id="escaner-titulo">Apunta al QR de la etiqueta</h2>
            <p class="qr-dialog-help">Al detectar el código se abrirá la ficha del equipo con su custodio e historial.</p>

            <div class="tec-video-wrap" id="caja-video">
                <video id="qr-video" playsinline muted></video>
            </div>
            <p class="field-error" id="qr-error" hidden></p>

            <button class="btn btn-outline btn-block" type="button" id="btn-cerrar-escaner">Cancelar</button>
        </div>
    </div>
</dialog>