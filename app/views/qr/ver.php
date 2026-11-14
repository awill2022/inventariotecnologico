<?php
/**
 * Página pública del QR (/q/{token}): ficha técnica del equipo con el aspecto de su etiqueta patrimonial.
 * No muestra número de serie, valor, proveedor ni historial.
 * @var array   $e         Equipo::porId()
 * @var string  $token
 * @var array   $specs     especificaciones (atributo, valor)
 * @var ?array  $ultimo    último mantenimiento finalizado
 * @var ?array  $abierto   mantenimiento programado o en proceso
 * @var string  $acceso    anonimo | a_cargo | docente | panel | baja
 * @var bool    $verFicha  el rol puede abrir la ficha completa del panel
 * @var ?string $ok        reporte enviado (al volver del formulario)
 * @var ?string $aviso     por qué no se pudo abrir el formulario
 */
$deBaja = $e['estado_inventario'] === 'de_baja';
$sellos = Formato::sellosEquipo($e);
$modelo = trim(($e['marca'] ?? '') . ' ' . ($e['modelo'] ?? ''));
$lugar  = implode(', ', array_filter([$e['edificio'], $e['piso'] ? 'piso ' . $e['piso'] : null]));
$ubicacion = $e['ubicacion'] ? $e['ubicacion'] . ($lugar ? " · $lugar" : '') : null;

$dato = function (string $icono, string $rotulo, ?string $valor, ?string $sub = null): void {
    ?>
    <div class="qr-dato">
        <svg class="icon" aria-hidden="true"><use href="#i-<?= $icono ?>"/></svg>
        <dt><?= View::e($rotulo) ?></dt>
        <dd>
            <?php if ($valor === null || $valor === ''): ?>
                <span class="qr-sin-dato">Sin dato</span>
            <?php else: ?>
                <?= View::e($valor) ?>
            <?php endif; ?>
            <?php if ($sub): ?><span class="qr-dato-sub"><?= View::e($sub) ?></span><?php endif; ?>
        </dd>
    </div>
    <?php
};

$trabajoAbierto = null;
if ($abierto) {
    $trabajoAbierto = $abierto['estado'] === 'en_proceso'
        ? 'Soporte TI está trabajando en este equipo desde ' . Formato::fecha($abierto['fecha_inicio']) . '.'
        : ($abierto['tipo'] === 'diagnostico' && $abierto['fecha_programada'] === null
            ? 'Ya hay una falla reportada el ' . Formato::fecha($abierto['creado_en']) . '; Soporte TI la revisará.'
            : 'Tiene un mantenimiento ' . Formato::TIPOS_MANTENIMIENTO[$abierto['tipo']] . ' programado para el '
              . Formato::fecha($abierto['fecha_programada']) . '.');
}
?>
<div class="wrap qr-pagina">
    <?php if ($ok): ?>
        <div class="notice notice--ok" role="status">
            <svg class="icon" aria-hidden="true"><use href="#i-check"/></svg>
            <p><strong>Reporte enviado.</strong> <?= View::e($ok) ?></p>
        </div>
    <?php endif; ?>
    <?php if ($aviso): ?>
        <div class="notice notice--warn" role="status">
            <svg class="icon" aria-hidden="true"><use href="#i-alert"/></svg>
            <p><?= View::e($aviso) ?></p>
        </div>
    <?php endif; ?>

    <article class="qr-etiqueta" aria-labelledby="qr-nombre">
        <div class="qr-banda"><span>ISTSGA</span><span>Propiedad del instituto</span></div>

        <header class="qr-cabecera">
            <p class="qr-codigo"><?= View::e($e['codigo']) ?></p>
            <?php if ($e['codigo_patrimonial']): ?>
                <p class="qr-patr">Código patrimonial <?= View::e($e['codigo_patrimonial']) ?></p>
            <?php endif; ?>
            <h1 class="qr-nombre" id="qr-nombre"><?= View::e($e['nombre']) ?></h1>
            <p class="qr-modelo"><?= View::e(implode(' · ', array_filter([$modelo, $e['categoria']]))) ?></p>
            <p class="qr-sellos">
                <span class="sr-only">Estado:</span>
                <?php foreach ($sellos as $s): ?>
                    <span class="qr-sello qr-sello--<?= View::e($s['clase']) ?>"><?= View::e($s['texto']) ?></span>
                <?php endforeach; ?>
            </p>
        </header>

        <?php if ($deBaja): ?>
            <p class="qr-nota qr-nota--bad">
                <svg class="icon" aria-hidden="true"><use href="#i-archive"/></svg>
                Dado de baja el <?= View::e(Formato::fecha($e['fecha_baja'])) ?>: ya no forma parte del inventario vigente.
            </p>
        <?php elseif ($trabajoAbierto): ?>
            <p class="qr-nota">
                <svg class="icon" aria-hidden="true"><use href="#i-wrench"/></svg>
                <?= View::e($trabajoAbierto) ?>
            </p>
        <?php endif; ?>

        <dl class="qr-datos">
            <?php
            if (!$deBaja) {
                $dato('pin', 'Ubicación', $ubicacion, $e['sede']);
                $dato('user', 'Responsable', $e['responsable'] ?? ($e['estado_inventario'] === 'en_bodega' ? 'Sin responsable: está en bodega' : null),
                      $e['fecha_entrega'] ? 'Desde el ' . Formato::fecha($e['fecha_entrega']) : null);
            }
            $dato('wrench', 'Último mantenimiento', $ultimo ? Formato::fecha($ultimo['fecha_fin'] ?? $ultimo['fecha_inicio']) : 'Sin mantenimientos registrados',
                  $ultimo ? 'Mantenimiento ' . Formato::TIPOS_MANTENIMIENTO[$ultimo['tipo']] : null);
            $dato('building', 'Área propietaria', $e['area_propietaria']);
            ?>
        </dl>

        <?php if ($specs): ?>
            <section class="qr-specs" aria-labelledby="qr-t-specs">
                <h2 class="qr-subtitulo" id="qr-t-specs">
                    <svg class="icon" aria-hidden="true"><use href="#i-cpu"/></svg>
                    Especificaciones
                </h2>
                <dl>
                    <?php foreach ($specs as $s): ?>
                        <div>
                            <dt><?= View::e($s['atributo']) ?></dt>
                            <dd><?= View::e($s['valor']) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </section>
        <?php endif; ?>
    </article>

    <section class="panel qr-acciones" aria-labelledby="qr-t-acciones">
        <?php if ($acceso === 'baja'): ?>
            <h2 class="qr-subtitulo" id="qr-t-acciones">Equipo fuera de servicio</h2>
            <p>Este equipo ya no recibe reportes de falla.</p>
        <?php elseif ($acceso === 'a_cargo'): ?>
            <h2 class="qr-subtitulo" id="qr-t-acciones">Este equipo está a tu cargo</h2>
            <p>¿Presenta una falla? Cuéntanos qué ocurre y, si puedes, adjunta fotos para que el técnico vea el problema.</p>
        <?php elseif ($acceso === 'docente'): ?>
            <h2 class="qr-subtitulo" id="qr-t-acciones">Este equipo está a cargo de otra persona</h2>
            <p>Solo su responsable puede reportar fallas. Si notas un problema, avísale o comunícate con Soporte TI.</p>
        <?php elseif ($acceso === 'panel'): ?>
            <h2 class="qr-subtitulo" id="qr-t-acciones">Gestión del equipo</h2>
            <p>Los reportes de falla los envía el docente que tiene el equipo. Desde el panel puedes ver la ficha completa y su historial.</p>
        <?php else: ?>
            <h2 class="qr-subtitulo" id="qr-t-acciones">¿Este equipo tiene una falla?</h2>
            <p>Si lo tienes a tu cargo, repórtala aquí. Por seguridad te pediremos iniciar sesión con tu cuenta institucional.</p>
        <?php endif; ?>

        <div class="qr-botones">
            <?php if (in_array($acceso, ['anonimo', 'a_cargo'], true)): ?>
                <a class="btn btn-primary" href="<?= View::url("/q/$token/reportar") ?>">
                    <svg class="icon" aria-hidden="true"><use href="#i-alert"/></svg>
                    Reportar una falla
                </a>
            <?php endif; ?>
            <?php if ($verFicha): ?>
                <a class="btn btn-primary" href="<?= View::url("/equipos/{$e['id']}") ?>">
                    <svg class="icon" aria-hidden="true"><use href="#i-laptop"/></svg>
                    Abrir ficha completa
                </a>
            <?php endif; ?>
            <?php if (in_array($acceso, ['a_cargo', 'docente'], true)): ?>
                <a class="btn btn-outline" href="<?= View::url('/docente') ?>">Ir a mis equipos</a>
            <?php endif; ?>
        </div>
    </section>
</div>
