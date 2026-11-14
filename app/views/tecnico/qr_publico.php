<?php
/**
 * Pantalla universal al escanear el código QR de un equipo (/q/{token}).
 * Muestra código, estado, de quién es (custodio) y los 2 botones:
 * 1) Reportar falla (con PIN de 4 dígitos y fotos, sin login completo)
 * 2) Realizar mantenimiento (Técnico: pide login si no está autenticado y abre la ficha técnica)
 *
 * @var array  $equipo
 * @var ?array $abierto
 * @var ?string $ok
 * @var ?string $error
 * @var array  $errores
 * @var array  $old
 * @var string $csrf
 */
$e = [View::class, 'e'];
[$estadoTexto, $estadoClase] = Formato::ESTADOS[$equipo['estado_operativo']];
$modelo = implode(' · ', array_filter([
    trim(($equipo['marca'] ?? '') . ' ' . ($equipo['modelo'] ?? '')),
    $equipo['numero_serie'] ? 'Serie ' . $equipo['numero_serie'] : '',
]));
$old = $old + ['identificador' => '', 'problema' => '', 'uso' => 'con_fallas'];
$abrirReporte = !empty($errores) || !empty($error);
?>
<main class="qr-landing" id="contenido">
    <div class="qr-landing-wrap">
        <?php if ($ok): ?>
            <div class="notice notice--ok" role="status">
                <svg class="icon" aria-hidden="true"><use href="#i-check"/></svg>
                <p><?= $e($ok) ?></p>
            </div>
        <?php endif; ?>

        <!-- LA PLACA PATRIMONIAL CON DATOS DEL EQUIPO Y CUSTODIO -->
        <article class="tag qr-landing-card">
            <div class="tag-band">
                <span>ISTSGA · Inventario Tecnológico</span>
                <span>Etiqueta QR Verificada</span>
            </div>

            <div class="tag-main">
                <div class="tag-id">
                    <p class="tag-code"><?= $e($equipo['codigo']) ?></p>
                    <?php if ($equipo['codigo_patrimonial']): ?>
                        <p class="tag-patr">Patrimonial <?= $e($equipo['codigo_patrimonial']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="stamps" style="margin-top: 0; padding-inline: 0;">
                    <p class="stamp stamp--<?= $e($estadoClase) ?>"><?= $e($estadoTexto) ?></p>
                </div>
            </div>

            <h1 class="tag-name"><?= $e($equipo['nombre']) ?></h1>
            <?php if ($modelo): ?>
                <p class="tag-model"><?= $e($modelo) ?></p>
            <?php endif; ?>

            <div class="qr-custodio-box">
                <p class="qr-custodio-label">¿De quién es este equipo? (Custodio actual)</p>
                <?php if ($equipo['custodio_id']): ?>
                    <p class="qr-custodio-name"><?= $e($equipo['custodio_nombre']) ?></p>
                    <p class="qr-custodio-meta">
                        <?= $e($equipo['custodio_cargo'] ?: 'Personal institucional') ?>
                        <?= $equipo['custodio_area'] ? ' · ' . $e($equipo['custodio_area']) : '' ?>
                    </p>
                <?php else: ?>
                    <p class="qr-custodio-name">Sin custodio individual asignado</p>
                    <p class="qr-custodio-meta">A cargo de la ubicación o en bodega</p>
                <?php endif; ?>

                <p class="qr-custodio-ubic">
                    <svg class="icon" aria-hidden="true"><use href="#i-pin"/></svg>
                    <span>
                        <strong>Ubicación:</strong>
                        <?= $e(($equipo['sede'] ? $equipo['sede'] . ' · ' : '') . ($equipo['ubicacion'] ?: 'Sin ubicación')) ?>
                        <?= $equipo['edificio'] ? ' (' . $e($equipo['edificio']) . ($equipo['piso'] ? ', piso ' . $e($equipo['piso']) : '') . ')' : '' ?>
                    </span>
                </p>
            </div>

            <?php if ($abierto): ?>
                <div class="tec-alerta-abierta" style="margin-inline: var(--s-4);">
                    <p><strong>Atención en curso:</strong> Este equipo ya tiene un registro de soporte (<?= $e($abierto['estado'] === 'en_proceso' ? 'en mantenimiento' : 'pendiente de revisión') ?>).</p>
                </div>
            <?php endif; ?>

            <!-- LOS 2 BOTONES PRINCIPALES AL ESCANEAR -->
            <div class="qr-acciones-principales">
                <button class="btn btn-gold btn-block" type="button" id="btn-mostrar-reporte"
                        aria-expanded="<?= $abrirReporte ? 'true' : 'false' ?>" aria-controls="form-qr-reportar">
                    <svg class="icon" aria-hidden="true"><use href="#i-alert"/></svg>
                    Reportar falla (con PIN rápido)
                </button>

                <a class="btn btn-primary btn-block" href="<?= View::url('/q/' . $equipo['qr_token'] . '/mantenimiento') ?>">
                    <svg class="icon" aria-hidden="true"><use href="#i-wrench"/></svg>
                    Realizar mantenimiento (Técnico)
                </a>
            </div>
        </article>

        <!-- FORMULARIO DESPLEGABLE: REPORTAR FALLA CON PIN + FOTOS -->
        <section class="panel qr-reporte-panel" id="form-qr-reportar" <?= $abrirReporte ? '' : 'hidden' ?>>
            <h2 class="section-title">Reportar falla del equipo</h2>
            <p class="section-note">
                Ingresa tu cédula (o usuario) y tu <strong>PIN de 4 dígitos</strong> (por defecto son los <strong>últimos 4 dígitos de tu cédula</strong>) para enviar la alerta al Técnico y al Administrador sin tener que iniciar sesión.
            </p>

            <?php if ($error): ?>
                <div class="notice notice--error" role="alert" style="margin-top: var(--s-4);">
                    <svg class="icon" aria-hidden="true"><use href="#i-alert"/></svg>
                    <p><?= $e($error) ?></p>
                </div>
            <?php endif; ?>

            <form class="form" style="margin-top: var(--s-4); max-width: none;" method="POST"
                  action="<?= View::url('/q/' . $equipo['qr_token'] . '/reportar') ?>"
                  enctype="multipart/form-data" novalidate>
                <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">

                <div class="qr-pin-grid">
                    <div class="field">
                        <label for="qr-identificador">Tu Cédula o Usuario <span class="req">*</span></label>
                        <input type="text" id="qr-identificador" name="identificador" required
                               value="<?= $e($old['identificador']) ?>" placeholder="Ej.: 0602345670 o daniel" autocomplete="off">
                    </div>

                    <div class="field">
                        <label for="qr-pin">PIN rápido (4 dígitos) <span class="req">*</span></label>
                        <input type="password" id="qr-pin" name="pin" required maxlength="4"
                               inputmode="numeric" pattern="\d{4}" placeholder="Últimos 4 de tu cédula" autocomplete="off"
                               <?= isset($errores['pin']) ? 'aria-invalid="true"' : '' ?>>
                    </div>
                </div>
                <?php if (isset($errores['pin'])): ?>
                    <p class="field-error"><?= $e($errores['pin']) ?></p>
                <?php endif; ?>

                <div class="field">
                    <label for="qr-problema">¿Qué falla presenta el equipo? <span class="req">*</span></label>
                    <textarea id="qr-problema" name="problema" rows="3" required minlength="10" maxlength="2000"
                              placeholder="Describe qué ocurrió, si muestra algún mensaje o ruido…"><?= $e($old['problema']) ?></textarea>
                    <?php if (isset($errores['problema'])): ?>
                        <p class="field-error"><?= $e($errores['problema']) ?></p>
                    <?php endif; ?>
                </div>

                <fieldset class="field">
                    <legend>¿Se puede seguir usando el equipo?</legend>
                    <div class="radios">
                        <label class="radio">
                            <input type="radio" name="uso" value="con_fallas" <?= $old['uso'] !== 'inoperativo' ? 'checked' : '' ?>>
                            Sí, funciona con fallas
                        </label>
                        <label class="radio">
                            <input type="radio" name="uso" value="inoperativo" <?= $old['uso'] === 'inoperativo' ? 'checked' : '' ?>>
                            No, quedó inoperativo
                        </label>
                    </div>
                </fieldset>

                <div class="field">
                    <label for="qr-fotos">Fotos de evidencia <span class="opcional">(opcional, hasta 3 fotos)</span></label>
                    <input type="file" id="qr-fotos" name="fotos[]" accept="image/jpeg,image/png,image/webp" capture="environment" multiple>
                    <p class="field-help">Puedes tomar una foto directa del daño o de la pantalla con tu celular.</p>
                </div>

                <div class="form-acciones">
                    <button class="btn btn-primary btn-block" type="submit">Enviar reporte al Técnico y Administrador</button>
                </div>
            </form>
        </section>
    </div>
</main>