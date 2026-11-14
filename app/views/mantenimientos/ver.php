<?php
/**
 * Ficha de un mantenimiento: acciones según su estado, datos del trabajo y del equipo,
 * reportes pendientes del mismo equipo, informe final, repuestos usados y componentes.
 * @var array  $m                Mantenimiento::porId()
 * @var string $numero           "MNT-000030"
 * @var array  $reportes         reportes pendientes del mismo equipo (se cierran al finalizar)
 * @var array  $fotos            [mantenimiento_id => archivos] fotos que adjuntó el docente al reportar
 * @var ?array $enCurso          otro mantenimiento en proceso del mismo equipo
 * @var array  $repuestos        líneas de repuestos usados
 * @var float  $totalRepuestos
 * @var array  $componentes      instalados o retirados durante este trabajo
 * @var array  $instalados       componentes instalados del equipo (solo en proceso)
 * @var bool   $puedeOperar, $puedeAsignar, $puedeComponentes
 * @var array  $tecnicos         [persona_id => nombre] (solo administrador)
 * @var array  $camposRepuesto, $repuestoValores, $repuestoErrores
 * @var string $csrf
 */
$id         = (int) $m['id'];
$equipoId   = (int) $m['equipo_id'];
$estado     = $m['estado'];
$programado = $estado === 'programado';
$enProceso  = $estado === 'en_proceso';
$abierto    = $programado || $enProceso;
$deBaja     = $m['estado_inventario'] === 'de_baja';
$esReporte  = Mantenimiento::esReporte($m);
$tipo       = MantenimientoController::tipos()[$m['tipo']];
$esAdmin    = Auth::rol() === 'administrador';
$esMio      = $m['tecnico_id'] !== null && (int) $m['tecnico_id'] === (int) Auth::user()['persona_id'];
[$estadoTexto, $estadoClase] = Mantenimiento::ESTADOS[$estado];
[$opTexto, $opClase]         = Formato::ESTADOS[$m['estado_operativo']];
$costoTotal = (float) $m['costo_mano_obra'] + $totalRepuestos;

$dato = function (string $rotulo, ?string $valor): void {
    ?>
    <div>
        <dt><?= View::e($rotulo) ?></dt>
        <dd><?php if ($valor === null || $valor === ''): ?><span class="sin-dato" aria-label="Sin dato">—</span><?php else: ?><?= View::e($valor) ?><?php endif; ?></dd>
    </div>
    <?php
};
$texto = function (string $titulo, ?string $valor): void {
    if ($valor === null || $valor === '') {
        return;
    }
    ?>
    <div class="informe-bloque">
        <h3 class="informe-titulo"><?= View::e($titulo) ?></h3>
        <p class="texto-libre"><?= nl2br(View::e($valor)) ?></p>
    </div>
    <?php
};
$registrado = $m['registrado'] ? $m['registrado'] . ($m['registrado_rol'] === 'consulta' ? ' (docente)' : '') : null;

// Galería de fotos de un reporte: cada miniatura abre la foto completa en otra pestaña
$galeria = function (array $lista, string $clase = ''): void {
    ?>
    <ul class="fotos-galeria <?= $clase ?>">
        <?php foreach ($lista as $i => $f): ?>
            <li>
                <a href="<?= View::url("/archivos/{$f['id']}") ?>" target="_blank">
                    <img src="<?= View::url("/archivos/{$f['id']}") ?>" loading="lazy"
                         alt="Foto <?= $i + 1 ?> de la falla, abrir en tamaño completo (otra pestaña)">
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
};
$fotosPropias = $fotos[$id] ?? [];
?>
<a class="volver" href="<?= View::url('/mantenimientos') ?>">
    <svg class="icon" aria-hidden="true"><use href="#i-arrow-left"/></svg>
    Volver a mantenimientos
</a>

<header class="ficha-head">
    <div class="ficha-id">
        <p class="ficha-codigo"><?= View::e($numero) ?></p>
        <h1 class="section-title"><?= $esReporte && $programado ? 'Reporte de falla' : 'Mantenimiento ' . View::e(Formato::TIPOS_MANTENIMIENTO[$m['tipo']]) ?></h1>
        <p class="section-note">
            <a href="<?= View::url("/equipos/$equipoId") ?>"><?= View::e($m['codigo']) ?></a> · <?= View::e($m['equipo']) ?>
        </p>
        <p class="sellos">
            <span class="sello sello--<?= $estadoClase ?>"><?= $estadoTexto ?></span>
            <?php if ($abierto && $m['fecha_programada'] && $m['fecha_programada'] < date('Y-m-d') && $programado): ?>
                <span class="sello sello--bad">Atrasado</span>
            <?php endif; ?>
        </p>
    </div>

    <?php if ($abierto && $puedeOperar && !$deBaja): ?>
        <div class="ficha-acciones" id="acciones">
            <?php if ($programado && !$enCurso): ?>
                <form class="form-iniciar" method="POST" action="<?= View::url("/mantenimientos/$id/iniciar") ?>">
                    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                    <?php if ($esAdmin): ?>
                        <div class="field">
                            <label for="iniciar-tecnico">Técnico que lo atiende</label>
                            <select id="iniciar-tecnico" name="tecnico_id" required>
                                <option value="">Elige un técnico</option>
                                <?php foreach ($tecnicos as $valor => $nombre): ?>
                                    <option value="<?= (int) $valor ?>" <?= (int) $valor === (int) $m['tecnico_id'] ? 'selected' : '' ?>><?= View::e($nombre) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <button class="btn btn-primary" type="submit">
                        <svg class="icon" aria-hidden="true"><use href="#i-play"/></svg>
                        <?= $esAdmin || $esMio ? 'Iniciar trabajo' : 'Tomar e iniciar' ?>
                    </button>
                </form>
            <?php elseif ($enProceso): ?>
                <a class="btn btn-primary" href="<?= View::url("/mantenimientos/$id/finalizar") ?>">
                    <svg class="icon" aria-hidden="true"><use href="#i-check"/></svg>
                    Finalizar trabajo
                </a>
            <?php endif; ?>
            <?php if ($programado): ?>
                <a class="btn btn-outline" href="<?= View::url("/mantenimientos/$id/editar") ?>">
                    <svg class="icon" aria-hidden="true"><use href="#i-pencil"/></svg>
                    Editar
                </a>
            <?php endif; ?>
            <a class="btn btn-text btn-text--danger" href="<?= View::url("/mantenimientos/$id/cancelar") ?>">
                <svg class="icon" aria-hidden="true"><use href="#i-x-circle"/></svg>
                Cancelar trabajo
            </a>
        </div>
    <?php elseif ($abierto && $deBaja && $puedeOperar): ?>
        <div class="ficha-acciones">
            <a class="btn btn-text btn-text--danger" href="<?= View::url("/mantenimientos/$id/cancelar") ?>">
                <svg class="icon" aria-hidden="true"><use href="#i-x-circle"/></svg>
                Cancelar trabajo
            </a>
        </div>
    <?php endif; ?>
</header>

<?php require APP_PATH . '/views/partials/flash.php'; ?>

<?php if ($abierto && $deBaja): ?>
    <div class="notice notice--error" role="note">
        <svg class="icon" aria-hidden="true"><use href="#i-archive"/></svg>
        <p><?= View::e($m['codigo']) ?> está dado de baja: este trabajo ya no se puede iniciar ni continuar. Cancélalo con su motivo.</p>
    </div>
<?php elseif ($programado && $enCurso): ?>
    <div class="notice notice--warn" role="note">
        <svg class="icon" aria-hidden="true"><use href="#i-wrench"/></svg>
        <p>
            <?= View::e($m['codigo']) ?> ya tiene un trabajo en proceso:
            <a href="<?= View::url("/mantenimientos/{$enCurso['id']}") ?>"><?= Mantenimiento::numero((int) $enCurso['id']) ?></a>.
            <?= $esReporte ? 'Este reporte se cerrará cuando ese trabajo se finalice.' : 'Este trabajo se podrá iniciar cuando aquel termine.' ?>
        </p>
    </div>
<?php elseif ($abierto && !$puedeOperar && Auth::rol() === 'tecnico'): ?>
    <div class="notice notice--warn" role="note">
        <svg class="icon" aria-hidden="true"><use href="#i-user-check"/></svg>
        <p>Este trabajo está asignado a <?= View::e($m['tecnico']) ?>. Solo esa persona o el administrador pueden cambiarlo.</p>
    </div>
<?php endif; ?>

<div class="ficha">
    <section class="panel ficha-seccion" aria-labelledby="t-trabajo">
        <h2 class="sub-title" id="t-trabajo">Trabajo</h2>
        <dl class="datos">
            <?php
            $dato('Tipo', $tipo);
            $dato('Fecha programada', $m['fecha_programada'] ? Formato::fecha($m['fecha_programada']) : ($esReporte ? 'Atender cuanto antes' : null));
            $dato('Inicio', Formato::fechaHora($m['fecha_inicio']));
            $dato('Fin', Formato::fechaHora($m['fecha_fin']));
            $dato('Registrado', trim(Formato::fechaHora($m['creado_en']) . ($registrado ? " por $registrado" : '')));
            ?>
            <div>
                <dt>Técnico</dt>
                <dd><?= $m['tecnico'] ? View::e($m['tecnico']) : '<span class="sin-asignar">Sin asignar</span>' ?></dd>
            </div>
        </dl>
        <?php if ($puedeAsignar && $tecnicos): ?>
            <form class="form-asignar" method="POST" action="<?= View::url("/mantenimientos/$id/asignar") ?>">
                <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                <div class="field">
                    <label for="asignar-tecnico"><?= $m['tecnico'] ? 'Cambiar de técnico' : 'Asignar a un técnico' ?></label>
                    <select id="asignar-tecnico" name="tecnico_id" required>
                        <option value="">Elige un técnico</option>
                        <?php foreach ($tecnicos as $valor => $nombre): ?>
                            <option value="<?= (int) $valor ?>" <?= (int) $valor === (int) $m['tecnico_id'] ? 'selected' : '' ?>><?= View::e($nombre) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-outline" type="submit">Asignar</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="panel ficha-seccion" aria-labelledby="t-equipo">
        <div class="seccion-head">
            <h2 class="sub-title" id="t-equipo">Equipo</h2>
            <a class="btn-link" href="<?= View::url("/equipos/$equipoId") ?>">Ver ficha<span class="sr-only"> de <?= View::e($m['codigo']) ?></span></a>
        </div>
        <dl class="datos">
            <?php
            $dato('Código', $m['codigo']);
            $dato('Nombre', $m['equipo']);
            $dato('Marca y modelo', trim(($m['marca'] ?? '') . ' ' . ($m['modelo'] ?? '')));
            $dato('Ubicación', $m['ubicacion']);
            $dato('A cargo de', $m['responsable'] ?? 'Sin asignar');
            ?>
            <div>
                <dt>Estado del equipo</dt>
                <dd>
                    <?php if ($deBaja): ?>
                        <span class="sello sello--bad">De baja</span>
                    <?php else: ?>
                        <span class="sello sello--<?= $opClase ?>"><?= $opTexto ?></span>
                    <?php endif; ?>
                </dd>
            </div>
        </dl>
    </section>

    <section class="panel ficha-seccion ficha-ancha" aria-labelledby="t-problema">
        <h2 class="sub-title" id="t-problema"><?= $esReporte ? 'Problema reportado' : 'Qué hay que hacer' ?></h2>
        <?php if ($m['problema_reportado']): ?>
            <p class="texto-libre"><?= nl2br(View::e($m['problema_reportado'])) ?></p>
        <?php else: ?>
            <p class="section-note">Sin descripción.</p>
        <?php endif; ?>

        <?php if ($fotosPropias): ?>
            <div class="fotos-reporte">
                <h3 class="fotos-titulo">
                    <svg class="icon" aria-hidden="true"><use href="#i-camera"/></svg>
                    <?= Formato::plural(count($fotosPropias), 'foto adjunta', 'fotos adjuntas') ?> por quien reportó
                </h3>
                <?php $galeria($fotosPropias); ?>
            </div>
        <?php endif; ?>

        <?php if ($reportes): ?>
            <div class="otros-reportes">
                <h3 class="informe-titulo">
                    <?= Formato::plural(count($reportes), 'otro reporte pendiente', 'otros reportes pendientes') ?> de este equipo
                </h3>
                <p class="section-note">
                    <?= $enProceso ? 'Se cerrarán al finalizar este trabajo.' : 'Se cerrarán cuando este trabajo se inicie y se finalice.' ?>
                </p>
                <ul class="reportes">
                    <?php foreach ($reportes as $r): ?>
                        <li>
                            <a href="<?= View::url("/mantenimientos/{$r['id']}") ?>"><?= Mantenimiento::numero((int) $r['id']) ?></a>
                            <span class="dato-sub">
                                <?= View::e(Formato::relativa($r['creado_en'])) ?><?= $r['registrado'] ? ' · ' . View::e($r['registrado']) : '' ?>
                            </span>
                            <span class="reporte-texto"><?= View::e(Formato::resumen($r['problema_reportado'], 160)) ?></span>
                            <?php if (!empty($fotos[(int) $r['id']])): ?>
                                <?php $galeria($fotos[(int) $r['id']], 'fotos-galeria--mini'); ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($estado === 'finalizado'): ?>
        <section class="panel ficha-seccion ficha-ancha" aria-labelledby="t-informe">
            <h2 class="sub-title" id="t-informe">Informe del trabajo</h2>
            <div class="informe">
                <?php
                $texto('Diagnóstico', $m['diagnostico']);
                $texto('Trabajo realizado', $m['trabajo_realizado']);
                $texto('Resultado', $m['resultado']);
                $texto('Recomendaciones', $m['recomendaciones']);
                ?>
            </div>
            <?php if ($m['proximo_mantenimiento']): ?>
                <p class="ficha-nota">
                    <svg class="icon" aria-hidden="true"><use href="#i-calendar"/></svg>
                    Próximo mantenimiento sugerido: <?= View::e(Formato::fecha($m['proximo_mantenimiento'])) ?>
                </p>
            <?php endif; ?>
        </section>
    <?php elseif ($estado === 'cancelado'): ?>
        <section class="panel ficha-seccion ficha-ancha" aria-labelledby="t-cancelado">
            <h2 class="sub-title" id="t-cancelado">Motivo de la cancelación</h2>
            <p class="texto-libre"><?= nl2br(View::e((string) $m['resultado'])) ?></p>
        </section>
    <?php endif; ?>

    <section class="panel ficha-seccion ficha-ancha" id="repuestos" aria-labelledby="t-repuestos">
        <h2 class="sub-title" id="t-repuestos">Repuestos y costos</h2>

        <?php if ($repuestos): ?>
            <div class="tabla-caja tabla-caja--plana">
                <table class="tabla tabla-repuestos">
                    <caption class="sr-only">Repuestos usados</caption>
                    <thead>
                        <tr>
                            <th scope="col">Repuesto</th>
                            <th scope="col" class="num">Cantidad</th>
                            <th scope="col" class="num">Costo unitario</th>
                            <th scope="col" class="num">Subtotal</th>
                            <?php if ($enProceso && $puedeOperar): ?><th scope="col" class="acciones-col"><span class="sr-only">Acciones</span></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($repuestos as $r): ?>
                            <tr>
                                <th scope="row" class="principal">
                                    <?= View::e($r['nombre']) ?>
                                    <?php if ($r['detalle']): ?><span class="sub"><?= View::e($r['detalle']) ?></span><?php endif; ?>
                                </th>
                                <td data-label="Cantidad" class="num"><?= View::e(Formato::conUnidad($r['cantidad'], $r['unidad'])) ?></td>
                                <td data-label="Costo unitario" class="num"><?= View::e(Formato::dinero($r['costo_unitario'])) ?></td>
                                <td data-label="Subtotal" class="num"><?= View::e(Formato::dinero((string) $r['subtotal'])) ?></td>
                                <?php if ($enProceso && $puedeOperar): ?>
                                    <td class="acciones">
                                        <form method="POST" action="<?= View::url("/mantenimientos/$id/repuestos/{$r['id']}/quitar") ?>"
                                              data-confirmar="¿Quitar «<?= View::e($r['nombre']) ?>» de este trabajo? <?= View::e(Formato::conUnidad($r['cantidad'], $r['unidad'])) ?> vuelven al stock."
                                              data-confirmar-boton="Quitar">
                                            <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                                            <button class="btn-link btn-link--muted" type="submit">
                                                <svg class="icon" aria-hidden="true"><use href="#i-trash"/></svg>
                                                Quitar<span class="sr-only"> <?= View::e($r['nombre']) ?></span>
                                            </button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($programado): ?>
            <p class="section-note">Los repuestos se registran cuando el trabajo esté en proceso.</p>
        <?php elseif ($enProceso): ?>
            <p class="section-note">Todavía no hay repuestos registrados en este trabajo.</p>
        <?php else: ?>
            <p class="section-note">No se usaron repuestos en este trabajo.</p>
        <?php endif; ?>

        <dl class="totales">
            <div><dt>Repuestos</dt><dd><?= View::e(Formato::dinero((string) $totalRepuestos)) ?></dd></div>
            <div><dt>Mano de obra</dt><dd><?= $estado === 'finalizado' ? View::e(Formato::dinero($m['costo_mano_obra'])) : '<span class="sin-dato" aria-label="Se registra al finalizar">—</span>' ?></dd></div>
            <div class="totales-total"><dt>Costo total</dt><dd><?= View::e(Formato::dinero((string) $costoTotal)) ?></dd></div>
        </dl>

        <?php if ($enProceso && $puedeOperar && !$deBaja): ?>
            <?php if (!$camposRepuesto[0]['opciones']): ?>
                <p class="section-note">
                    No hay repuestos activos en el catálogo.
                    <a href="<?= View::url('/repuestos/nueva') ?>">Registra uno</a> para poder usarlo aquí.
                </p>
            <?php else: ?>
                <form class="form-repuesto" method="POST" action="<?= View::url("/mantenimientos/$id/repuestos") ?>" novalidate data-form-validar>
                    <h3 class="informe-titulo">Agregar un repuesto</h3>
                    <p class="section-note">Se descuenta del stock en el momento. Si lo quitas antes de finalizar, vuelve al stock.</p>
                    <?php if ($repuestoErrores): ?>
                        <div class="notice notice--error" role="alert">
                            <svg class="icon" aria-hidden="true"><use href="#i-alert"/></svg>
                            <p>Revisa el repuesto antes de agregarlo.</p>
                        </div>
                    <?php endif; ?>
                    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                    <div class="form-repuesto-campos">
                        <?php
                        $campos  = $camposRepuesto;
                        $valores = $repuestoValores;
                        $errores = $repuestoErrores;
                        require APP_PATH . '/views/partials/campos.php';
                        ?>
                    </div>
                    <div class="form-acciones">
                        <button class="btn btn-primary" type="submit">
                            <svg class="icon" aria-hidden="true"><use href="#i-plus"/></svg>
                            Agregar repuesto
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="panel ficha-seccion ficha-ancha" aria-labelledby="t-componentes">
        <div class="seccion-head">
            <h2 class="sub-title" id="t-componentes">Componentes</h2>
            <?php if ($enProceso && $puedeComponentes && !$deBaja): ?>
                <a class="btn-link" href="<?= View::url("/equipos/$equipoId/componentes/nuevo?mantenimiento=$id") ?>">
                    <svg class="icon" aria-hidden="true"><use href="#i-plus"/></svg>
                    Instalar componente
                </a>
            <?php endif; ?>
        </div>

        <?php if ($componentes): ?>
            <ul class="componentes">
                <?php foreach ($componentes as $c): ?>
                    <?php $retiradoAqui = (int) $c['mantenimiento_retiro_id'] === $id; ?>
                    <li class="componente <?= $retiradoAqui ? 'is-retirado' : '' ?>">
                        <div>
                            <p class="componente-tipo">
                                <?= View::e($c['tipo']) ?><?php if ($c['capacidad']): ?> · <?= View::e($c['capacidad']) ?><?php endif; ?>
                            </p>
                            <p class="componente-detalle">
                                <?= $retiradoAqui ? 'Retirado en este trabajo' : 'Instalado en este trabajo' ?>
                                <?php $marca = trim(($c['marca'] ?? '') . ' ' . ($c['modelo'] ?? '')); ?>
                                <?php if ($marca): ?> · <?= View::e($marca) ?><?php endif; ?>
                                <?php if ($c['numero_serie']): ?> · Serie <?= View::e($c['numero_serie']) ?><?php endif; ?>
                            </p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="section-note">
                <?= $enProceso ? 'Si cambias una pieza del equipo, regístrala aquí: queda enlazada a este trabajo.'
                               : 'No se instalaron ni retiraron componentes en este trabajo.' ?>
            </p>
        <?php endif; ?>

        <?php $retirables = array_filter($instalados, fn ($c) => (int) $c['mantenimiento_instalacion_id'] !== $id); ?>
        <?php if ($enProceso && $puedeComponentes && !$deBaja && $retirables): ?>
            <details class="retirados">
                <summary>Retirar un componente instalado (<?= count($retirables) ?>)</summary>
                <ul class="componentes">
                    <?php foreach ($retirables as $c): ?>
                        <li class="componente">
                            <div>
                                <p class="componente-tipo"><?= View::e($c['tipo']) ?><?php if ($c['capacidad']): ?> · <?= View::e($c['capacidad']) ?><?php endif; ?></p>
                                <p class="componente-detalle"><?= View::e(trim(($c['marca'] ?? '') . ' ' . ($c['modelo'] ?? ''))) ?></p>
                            </div>
                            <div class="componente-acciones">
                                <a class="btn-link btn-link--muted" href="<?= View::url("/equipos/$equipoId/componentes/{$c['id']}/retirar?mantenimiento=$id") ?>">
                                    Retirar<span class="sr-only"> <?= View::e($c['tipo']) ?></span>
                                </a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endif; ?>
    </section>
</div>
