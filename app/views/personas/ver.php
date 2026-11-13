<?php
/**
 * Ficha de una persona: sus datos, los equipos que tiene a cargo y el historial de entregas.
 * @var array $p               Persona::porId() (nombre, area, cuenta)
 * @var array $abiertas        entregas sin devolver (Asignacion::dePersona)
 * @var array $devueltas       entregas ya devueltas
 * @var bool  $puedeGestionar  administrador e inventario (editar y entregar)
 * @var bool  $verCuenta       solo el administrador ve el usuario del sistema
 * @var bool  $verActas        administrador e inventario (el técnico no abre las actas)
 */
$id     = (int) $p['id'];
$activa = (bool) $p['activo'];
$volver = rawurlencode("/personas/$id");

$dato = function (string $rotulo, ?string $valor): void {
    ?>
    <div>
        <dt><?= View::e($rotulo) ?></dt>
        <dd><?php if ($valor === null || $valor === ''): ?><span class="sin-dato" aria-label="Sin dato">—</span><?php else: ?><?= View::e($valor) ?><?php endif; ?></dd>
    </div>
    <?php
};
?>
<a class="volver" href="<?= View::url('/personas') ?>">
    <svg class="icon" aria-hidden="true"><use href="#i-arrow-left"/></svg>
    Volver a personas
</a>

<header class="ficha-head">
    <div class="ficha-id">
        <?php if ($p['cargo']): ?><p class="ficha-codigo"><?= View::e(mb_strtoupper(mb_substr($p['cargo'], 0, 1)) . mb_substr($p['cargo'], 1)) ?></p><?php endif; ?>
        <h1 class="section-title"><?= View::e($p['nombre']) ?></h1>
        <p class="sellos">
            <span class="sello sello--<?= $activa ? 'activo' : 'inactivo' ?>"><?= $activa ? 'Activa' : 'Inactiva' ?></span>
            <span class="sello sello--muted"><?= Formato::plural(count($abiertas), 'equipo a cargo', 'equipos a cargo') ?></span>
        </p>
    </div>

    <?php if ($puedeGestionar): ?>
        <div class="ficha-acciones">
            <?php if ($activa): ?>
                <a class="btn btn-primary" href="<?= View::url("/asignaciones/nueva?persona=$id&volver=$volver") ?>">
                    <svg class="icon" aria-hidden="true"><use href="#i-user-check"/></svg>
                    Entregar un equipo
                </a>
            <?php endif; ?>
            <a class="btn btn-outline" href="<?= View::url("/personas/$id/editar") ?>">
                <svg class="icon" aria-hidden="true"><use href="#i-pencil"/></svg>
                Editar datos
            </a>
        </div>
    <?php endif; ?>
</header>

<?php require APP_PATH . '/views/partials/flash.php'; ?>

<?php if (!$activa): ?>
    <div class="notice notice--warn" role="note">
        <svg class="icon" aria-hidden="true"><use href="#i-alert"/></svg>
        <p>Esta persona está inactiva: no se le pueden entregar equipos. Su historial se conserva.</p>
    </div>
<?php endif; ?>

<div class="ficha">
    <section class="panel ficha-seccion ficha-ancha" aria-labelledby="t-datos">
        <h2 class="sub-title" id="t-datos">Datos</h2>
        <dl class="datos datos--2">
            <?php
            $dato('Cédula', $p['identificacion']);
            $dato('Área', $p['area']);
            $dato('Correo', $p['correo']);
            $dato('Teléfono', $p['telefono']);
            if ($verCuenta) {
                $dato('Usuario del sistema', $p['cuenta'] ?: 'Sin cuenta');
            }
            ?>
        </dl>
    </section>

    <section class="panel ficha-seccion ficha-ancha" aria-labelledby="t-cargo">
        <h2 class="sub-title" id="t-cargo">Equipos a su cargo</h2>
        <?php if (!$abiertas): ?>
            <p class="section-note">No tiene equipos entregados en este momento.</p>
        <?php else: ?>
            <ul class="entregas">
                <?php foreach ($abiertas as $a): ?>
                    <li class="entrega">
                        <div>
                            <p class="entrega-titulo">
                                <a href="<?= View::url("/equipos/{$a['equipo_id']}") ?>"><?= View::e($a['codigo']) ?></a>
                                · <?= View::e($a['equipo']) ?>
                            </p>
                            <p class="entrega-detalle">
                                Desde el <?= View::e(Formato::fechaHora($a['fecha_entrega'])) ?>
                                <?php if ($a['ubicacion_actual']): ?> · En <?= View::e($a['ubicacion_actual']) ?><?php endif; ?>
                            </p>
                            <?php if ($a['estado_operativo'] !== 'operativo'): ?>
                                <p class="sellos">
                                    <?php foreach (Formato::sellosEquipo($a) as $s): ?>
                                        <span class="sello sello--<?= $s['clase'] ?>"><?= View::e($s['texto']) ?></span>
                                    <?php endforeach; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="entrega-acciones">
                            <?php if ($puedeGestionar): ?>
                                <a class="btn-link" href="<?= View::url("/asignaciones/{$a['id']}/devolucion?volver=$volver") ?>">
                                    Registrar devolución<span class="sr-only"> de <?= View::e($a['codigo']) ?></span>
                                </a>
                            <?php endif; ?>
                            <?php if ($verActas): ?>
                                <a class="btn-link" href="<?= View::url("/asignaciones/{$a['id']}/acta") ?>" target="_blank">
                                    Acta<span class="sr-only"> de <?= View::e($a['codigo']) ?> (se abre en otra pestaña)</span>
                                </a>
                                <a class="btn-link" href="<?= View::url("/asignaciones/{$a['id']}/adjuntos") ?>">
                                    Adjuntos<?= (int) $a['adjuntos'] ? ' (' . (int) $a['adjuntos'] . ')' : '' ?><span class="sr-only"> de <?= View::e($a['codigo']) ?></span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="panel ficha-seccion ficha-ancha" aria-labelledby="t-historial">
        <h2 class="sub-title" id="t-historial">Equipos que ya devolvió</h2>
        <?php if (!$devueltas): ?>
            <p class="section-note">Todavía no ha devuelto ningún equipo.</p>
        <?php else: ?>
            <ul class="entregas">
                <?php foreach ($devueltas as $a): ?>
                    <li class="entrega is-cerrada">
                        <div>
                            <p class="entrega-titulo">
                                <a href="<?= View::url("/equipos/{$a['equipo_id']}") ?>"><?= View::e($a['codigo']) ?></a>
                                · <?= View::e($a['equipo']) ?>
                            </p>
                            <p class="entrega-detalle">
                                Del <?= View::e(Formato::fecha($a['fecha_entrega'])) ?> al <?= View::e(Formato::fecha($a['fecha_devolucion'])) ?>
                                <?php if ($a['estado_devolucion']): ?> · <?= View::e($a['estado_devolucion']) ?><?php endif; ?>
                            </p>
                        </div>
                        <div class="entrega-acciones">
                            <?php if ($verActas): ?>
                                <a class="btn-link" href="<?= View::url("/asignaciones/{$a['id']}/acta") ?>" target="_blank">
                                    Acta<span class="sr-only"> de <?= View::e($a['codigo']) ?> (se abre en otra pestaña)</span>
                                </a>
                                <a class="btn-link" href="<?= View::url("/asignaciones/{$a['id']}/adjuntos") ?>">
                                    Adjuntos<?= (int) $a['adjuntos'] ? ' (' . (int) $a['adjuntos'] . ')' : '' ?><span class="sr-only"> de <?= View::e($a['codigo']) ?></span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
