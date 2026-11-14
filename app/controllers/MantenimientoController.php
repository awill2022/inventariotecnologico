<?php
/**
 * Mantenimientos: bandeja del técnico, programación, inicio, finalización, cancelación
 * y repuestos usados (Sprint 5).
 *
 * Reglas:
 * - Un reporte de falla del docente llega como diagnóstico programado sin técnico.
 * - El técnico «Toma e inicia» un trabajo para sí; el administrador puede asignarlo a cualquier técnico.
 *   Un técnico solo opera los trabajos sin asignar o asignados a él.
 * - Solo un mantenimiento en proceso por equipo; al iniciar, el equipo queda «En mantenimiento».
 * - Al finalizar se elige el estado en que queda el equipo y se cierran los reportes pendientes
 *   del mismo equipo. Con «próximo mantenimiento» se programa un preventivo para esa fecha.
 * - Los repuestos se descuentan del stock al agregarlos y vuelven al stock si se quitan
 *   antes de finalizar. Un trabajo con repuestos no se cancela: primero se quitan.
 * - Todo cambio va en una transacción con su auditoría (cancelar = 'anular').
 *
 * Ver: los tres roles del panel. Gestionar: administrador y técnico. Asignar técnico: administrador.
 */
final class MantenimientoController extends Controller
{
    public const VER       = ['administrador', 'inventario', 'tecnico'];
    public const GESTIONAR = ['administrador', 'tecnico'];
    public const ASIGNAR   = ['administrador'];

    private const POR_PAGINA = 15;

    /** Estados en que puede quedar el equipo al terminar (o cancelar) un trabajo. */
    private const ESTADOS_FINALES = [
        'operativo'   => 'Operativo: funciona con normalidad',
        'con_fallas'  => 'Con fallas: funciona con limitaciones',
        'inoperativo' => 'Inoperativo: no funciona',
    ];

    /** GET /mantenimientos */
    public function index(): void
    {
        $texto = fn (string $k) => is_string($_GET[$k] ?? null) ? trim($_GET[$k]) : '';
        $tipos = self::tipos();

        $tecnico = $texto('tecnico');
        $filtros = [
            'pestana' => array_key_exists($texto('pestana'), Mantenimiento::PESTANAS) ? $texto('pestana') : 'por_atender',
            'q'       => mb_substr($texto('q'), 0, 100),
            'tipo'    => array_key_exists($texto('tipo'), $tipos) ? $texto('tipo') : '',
            'tecnico' => in_array($tecnico, ['sin', 'mios'], true) || ctype_digit($tecnico) ? $tecnico : '',
            'equipo'  => ctype_digit($texto('equipo')) ? (int) $texto('equipo') : null,
        ];
        $equipo = $filtros['equipo'] ? (new Equipo())->porId($filtros['equipo']) : null;
        if (!$equipo) {
            $filtros['equipo'] = null;
        }

        // El modelo recibe el técnico como id: 0 = sin asignar, null = todos
        $personaId = Auth::user()['persona_id'];
        $consulta  = ['tecnico' => match (true) {
            $filtros['tecnico'] === 'sin'  => 0,
            $filtros['tecnico'] === 'mios' => $personaId ?? -1,
            $filtros['tecnico'] !== ''     => (int) $filtros['tecnico'],
            default                        => null,
        }] + $filtros;

        $modelo = new Mantenimiento();
        $this->view('mantenimientos/lista', [
            'titulo'         => 'Mantenimientos',
            'filtros'        => $filtros,
            'equipo'         => $equipo,
            'tipos'          => $tipos,
            'tecnicos'       => (new Persona())->opcionesTecnicos(),
            'resultado'      => $modelo->listar($consulta, max(1, (int) ($_GET['pagina'] ?? 1)), self::POR_PAGINA),
            'resumen'        => $modelo->resumen($personaId),
            'esTecnico'      => Auth::rol() === 'tecnico',
            'puedeGestionar' => self::puede(self::GESTIONAR),
            'estilos'        => ['equipos.css', 'asignaciones.css', 'mantenimientos.css'],
        ], 'panel');
    }

    /** GET /mantenimientos/nuevo?equipo=ID */
    public function nuevo(): void
    {
        $equipoId = self::idDeQuery('equipo');
        if ($equipoId) {
            $equipo = (new Equipo())->porId($equipoId) ?? $this->notFound();
            if ($equipo['estado_inventario'] === 'de_baja') {
                Session::flash('error', "{$equipo['codigo']} está dado de baja: ya no se le programan mantenimientos.");
                $this->redirect("/equipos/$equipoId");
            }
        }
        $volver = $equipoId ? "/equipos/$equipoId" : '/mantenimientos';

        $this->formulario('/mantenimientos', 'Programar mantenimiento',
            'Registra un trabajo pendiente: un preventivo con fecha, una revisión o una actualización. '
            . 'El equipo no cambia de estado hasta que el técnico inicie el trabajo.',
            'Programar', $this->campos(null),
            ['equipo_id' => $equipoId, 'tipo' => 'preventivo', 'tecnico_id' => self::esTecnico() ? Auth::user()['persona_id'] : null],
            $volver, null, ['volver' => $volver]);
    }

    /** POST /mantenimientos */
    public function guardar(): void
    {
        $this->requireCsrf();
        $volver = $this->volver('/mantenimientos');
        $form   = '/mantenimientos/nuevo' . (str_starts_with($volver, '/equipos/') ? '?equipo=' . substr($volver, 9) : '');

        [$datos, $errores] = Validador::validar($this->campos(null), $_POST);
        $errores += self::reglasProgramacion($datos);
        if ($errores) {
            $this->volverAlFormulario($form, $datos, $errores);
        }

        $usuarioId = Auth::user()['id'];
        $modelo    = new Mantenimiento();
        try {
            $id = $modelo->transaccion(function () use ($modelo, $datos, $usuarioId) {
                $equipo = (new Equipo())->bloquear($datos['equipo_id']);
                if ($equipo['estado_inventario'] === 'de_baja') {
                    throw new DomainException("{$equipo['codigo']} está dado de baja: ya no se le programan mantenimientos.");
                }
                $id = $modelo->crear($datos, $usuarioId);
                (new Auditoria())->registrar($usuarioId, 'mantenimientos', $id, 'crear', ['datos' => $datos]);
                return $id;
            });
        } catch (DomainException $e) {
            Session::flash('error', $e->getMessage());
            $this->volverAlFormulario($form, $datos, []);
        } catch (PDOException $e) {
            error_log('[mantenimientos.crear] ' . $e->getMessage());
            Session::flash('error', 'No pudimos programar el mantenimiento. Inténtalo de nuevo en unos minutos.');
            $this->volverAlFormulario($form, $datos, []);
        }

        Session::flash('ok', 'Se programó el mantenimiento ' . Mantenimiento::numero($id) . '.');
        $this->redirect("/mantenimientos/$id");
    }

    /** GET /mantenimientos/{id} */
    public function ver(int $id): void
    {
        $modelo = new Mantenimiento();
        $m      = $modelo->porId($id) ?? $this->notFound();
        $abierto = in_array($m['estado'], ['programado', 'en_proceso'], true);
        $repuestos = $modelo->repuestos($id);
        $puedeOperar = self::puedeOperar($m);
        $reportes = $abierto ? $modelo->reportesPendientes((int) $m['equipo_id'], $id) : [];
        // Fotos que adjuntó el docente al reportar (de este y de los otros reportes pendientes)
        $fotos = (new Archivo())->deMantenimientos(array_merge([$id], array_column($reportes, 'id')));

        $this->view('mantenimientos/ver', [
            'titulo'          => Mantenimiento::numero($id),
            'm'               => $m,
            'numero'          => Mantenimiento::numero($id),
            'reportes'        => $reportes,
            'fotos'           => $fotos,
            'enCurso'         => $m['estado'] === 'programado' ? $modelo->enProcesoDe((int) $m['equipo_id'], $id) : null,
            'repuestos'       => $repuestos,
            'totalRepuestos'  => array_sum(array_map(fn ($r) => (float) $r['subtotal'], $repuestos)),
            'componentes'     => (new Componente())->deMantenimiento($id),
            'instalados'      => $m['estado'] === 'en_proceso'
                                 ? array_values(array_filter((new Componente())->deEquipo((int) $m['equipo_id']), fn ($c) => $c['fecha_retiro'] === null))
                                 : [],
            'puedeOperar'     => $puedeOperar,
            'puedeAsignar'    => self::puede(self::ASIGNAR) && $abierto,
            'puedeComponentes' => $puedeOperar && self::puede(EquipoController::COMPONENTES),
            'tecnicos'        => self::puede(self::ASIGNAR) ? (new Persona())->opcionesTecnicos($m['tecnico_id'] ? (int) $m['tecnico_id'] : null) : [],
            'camposRepuesto'  => $this->camposRepuesto(),
            'repuestoValores' => Session::flash('repuesto_old') ?? ['cantidad' => '1'],
            'repuestoErrores' => Session::flash('repuesto_errores') ?? [],
            'csrf'            => Session::csrfToken(),
            'estilos'         => ['equipos.css', 'asignaciones.css', 'mantenimientos.css', 'archivos.css'],
            'scripts'         => ['panel.js'],
        ], 'panel');
    }

    /** GET /mantenimientos/{id}/editar */
    public function editar(int $id): void
    {
        $m = $this->cargarParaOperar($id, ['programado']);

        $this->formulario("/mantenimientos/$id", 'Editar ' . Mantenimiento::numero($id),
            "{$m['codigo']} · {$m['equipo']}. Cambia el tipo, la fecha, la descripción o el técnico mientras el trabajo no se inicie.",
            'Guardar cambios', $this->campos($m), $m, "/mantenimientos/$id", null, []);
    }

    /** POST /mantenimientos/{id} */
    public function actualizar(int $id): void
    {
        $this->requireCsrf();
        $m    = $this->cargarParaOperar($id, ['programado']);
        $form = "/mantenimientos/$id/editar";

        [$datos, $errores] = Validador::validar($this->campos($m), $_POST);
        $datos['equipo_id'] = (int) $m['equipo_id'];
        $errores += self::reglasProgramacion($datos);
        if ($errores) {
            $this->volverAlFormulario($form, $datos, $errores);
        }

        $cambios = [];
        foreach (['tipo', 'fecha_programada', 'problema_reportado', 'tecnico_id'] as $campo) {
            if ((string) ($m[$campo] ?? '') !== (string) ($datos[$campo] ?? '')) {
                $cambios[$campo] = [$m[$campo], $datos[$campo]];
            }
        }
        if ($cambios) {
            $modelo = new Mantenimiento();
            try {
                $modelo->transaccion(function () use ($modelo, $id, $datos, $cambios) {
                    if (!$modelo->reprogramar($id, $datos)) {
                        throw new DomainException('El trabajo ya se inició o se cerró: no se puede editar.');
                    }
                    (new Auditoria())->registrar(Auth::user()['id'], 'mantenimientos', $id, 'editar', ['cambios' => $cambios]);
                });
            } catch (DomainException $e) {
                Session::flash('error', $e->getMessage());
                $this->redirect("/mantenimientos/$id");
            } catch (PDOException $e) {
                error_log('[mantenimientos.editar] ' . $e->getMessage());
                Session::flash('error', 'No pudimos guardar. Inténtalo de nuevo en unos minutos.');
                $this->volverAlFormulario($form, $datos, []);
            }
        }

        Session::flash('ok', $cambios ? 'Se guardaron los cambios de ' . Mantenimiento::numero($id) . '.' : 'No había cambios que guardar.');
        $this->redirect("/mantenimientos/$id");
    }

    /** POST /mantenimientos/{id}/iniciar — el técnico lo toma para sí; el administrador elige el técnico. */
    public function iniciar(int $id): void
    {
        $this->requireCsrf();
        $m = $this->cargarParaOperar($id, ['programado']);

        $tecnicos = (new Persona())->opcionesTecnicos();
        if (self::esTecnico()) {
            $tecnicoId = Auth::user()['persona_id'];
            if (!$tecnicoId || !isset($tecnicos[$tecnicoId])) {
                Session::flash('error', 'Tu cuenta no está enlazada a una persona activa: pide al administrador que la revise.');
                $this->redirect("/mantenimientos/$id");
            }
        } else {
            $enviado   = $_POST['tecnico_id'] ?? '';
            $tecnicoId = is_string($enviado) && ctype_digit($enviado) ? (int) $enviado : null;
            if (!$tecnicoId || !isset($tecnicos[$tecnicoId])) {
                Session::flash('error', 'Elige qué técnico atiende el trabajo antes de iniciarlo.');
                $this->redirect("/mantenimientos/$id#acciones");
            }
        }

        $usuarioId = Auth::user()['id'];
        $modelo    = new Mantenimiento();
        try {
            $modelo->transaccion(function () use ($modelo, $m, $id, $tecnicoId, $usuarioId) {
                $equipos = new Equipo();
                $equipo  = $equipos->bloquear((int) $m['equipo_id']);
                if ($equipo['estado_inventario'] === 'de_baja') {
                    throw new DomainException("{$equipo['codigo']} está dado de baja: el trabajo ya no se puede iniciar. Cancélalo.");
                }
                if ($otro = $modelo->enProcesoDe((int) $m['equipo_id'], $id)) {
                    throw new DomainException("{$equipo['codigo']} ya tiene en proceso el mantenimiento "
                        . Mantenimiento::numero((int) $otro['id']) . '. Finalízalo antes de iniciar otro.');
                }
                if (!$modelo->iniciar($id, $tecnicoId, date('Y-m-d H:i:s'))) {
                    throw new DomainException('Este trabajo ya se había iniciado o cerrado.');
                }

                $auditoria = new Auditoria();
                $auditoria->registrar($usuarioId, 'mantenimientos', $id, 'editar', [
                    'cambios' => ['estado' => ['programado', 'en_proceso'], 'tecnico_id' => [$m['tecnico_id'], $tecnicoId]],
                ]);
                if ($equipo['estado_operativo'] !== 'en_mantenimiento') {
                    $equipos->fijarOperativo((int) $m['equipo_id'], 'en_mantenimiento');
                    $auditoria->registrar($usuarioId, 'equipos', (int) $m['equipo_id'], 'editar', [
                        'cambios' => ['estado_operativo' => [$equipo['estado_operativo'], 'en_mantenimiento']],
                        'mantenimiento_id' => $id,
                    ]);
                }
            });
        } catch (DomainException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect("/mantenimientos/$id");
        } catch (PDOException $e) {
            error_log('[mantenimientos.iniciar] ' . $e->getMessage());
            Session::flash('error', 'No pudimos iniciar el trabajo. Inténtalo de nuevo en unos minutos.');
            $this->redirect("/mantenimientos/$id");
        }

        Session::flash('ok', "Se inició el trabajo. {$m['codigo']} queda «En mantenimiento» hasta que lo finalices.");
        $this->redirect("/mantenimientos/$id");
    }

    /** POST /mantenimientos/{id}/asignar — solo administrador. */
    public function asignar(int $id): void
    {
        $this->requireCsrf();
        $m = (new Mantenimiento())->porId($id) ?? $this->notFound();
        if (!in_array($m['estado'], ['programado', 'en_proceso'], true)) {
            Session::flash('error', 'El trabajo ya está cerrado: no se puede cambiar el técnico.');
            $this->redirect("/mantenimientos/$id");
        }

        $tecnicos  = (new Persona())->opcionesTecnicos();
        $enviado   = $_POST['tecnico_id'] ?? '';
        $tecnicoId = is_string($enviado) && ctype_digit($enviado) ? (int) $enviado : null;
        if (!$tecnicoId || !isset($tecnicos[$tecnicoId])) {
            Session::flash('error', 'Elige un técnico de la lista.');
            $this->redirect("/mantenimientos/$id#acciones");
        }
        if ($tecnicoId === (int) $m['tecnico_id']) {
            Session::flash('ok', 'El trabajo ya estaba asignado a ese técnico.');
            $this->redirect("/mantenimientos/$id");
        }

        $modelo = new Mantenimiento();
        try {
            $modelo->transaccion(function () use ($modelo, $id, $m, $tecnicoId) {
                if (!$modelo->asignar($id, $tecnicoId)) {
                    throw new DomainException('El trabajo se cerró mientras tanto: no se puede cambiar el técnico.');
                }
                (new Auditoria())->registrar(Auth::user()['id'], 'mantenimientos', $id, 'editar', [
                    'cambios' => ['tecnico_id' => [$m['tecnico_id'], $tecnicoId]],
                ]);
            });
        } catch (DomainException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect("/mantenimientos/$id");
        } catch (PDOException $e) {
            error_log('[mantenimientos.asignar] ' . $e->getMessage());
            Session::flash('error', 'No pudimos asignar el técnico. Inténtalo de nuevo en unos minutos.');
            $this->redirect("/mantenimientos/$id");
        }

        Session::flash('ok', "Se asignó el trabajo a {$tecnicos[$tecnicoId]}.");
        $this->redirect("/mantenimientos/$id");
    }

    /** GET /mantenimientos/{id}/finalizar */
    public function finalizacion(int $id): void
    {
        $m = $this->cargarParaOperar($id, ['en_proceso']);

        $this->formulario("/mantenimientos/$id/finalizar", 'Finalizar ' . Mantenimiento::numero($id),
            "{$m['codigo']} · {$m['equipo']}. Registra el informe del trabajo y en qué estado queda el equipo.",
            'Finalizar trabajo', $this->camposFinalizar($m),
            ['fecha_fin' => date('Y-m-d H:i'), 'estado_final' => 'operativo'],
            "/mantenimientos/$id", $this->avisoFinalizar($m), []);
    }

    /** POST /mantenimientos/{id}/finalizar */
    public function finalizar(int $id): void
    {
        $this->requireCsrf();
        $m    = $this->cargarParaOperar($id, ['en_proceso']);
        $form = "/mantenimientos/$id/finalizar";

        [$datos, $errores] = Validador::validar($this->camposFinalizar($m), $_POST);
        if ($errores) {
            $this->volverAlFormulario($form, $datos, $errores);
        }

        $usuarioId = Auth::user()['id'];
        $numero    = Mantenimiento::numero($id);
        $modelo    = new Mantenimiento();
        try {
            [$cerrados, $proximoId] = $modelo->transaccion(function () use ($modelo, $m, $id, $datos, $usuarioId, $numero) {
                $actual = $modelo->bloquear($id);
                $equipos = new Equipo();
                $equipo  = $equipos->bloquear((int) $m['equipo_id']);
                if (!$modelo->finalizar($id, $datos)) {
                    throw new DomainException('Este trabajo ya se había finalizado o cancelado.');
                }

                $auditoria = new Auditoria();
                $auditoria->registrar($usuarioId, 'mantenimientos', $id, 'cerrar', ['datos' => $datos]);

                if ($equipo['estado_operativo'] !== $datos['estado_final']) {
                    $equipos->fijarOperativo((int) $m['equipo_id'], $datos['estado_final']);
                    $auditoria->registrar($usuarioId, 'equipos', (int) $m['equipo_id'], 'editar', [
                        'cambios' => ['estado_operativo' => [$equipo['estado_operativo'], $datos['estado_final']]],
                        'mantenimiento_id' => $id,
                    ]);
                }

                // Los reportes pendientes del equipo (el formulario los anunció) se atendieron con este trabajo.
                // Un reporte que llegó minutos después de la hora de cierre escrita se cierra a su propia hora.
                $cerrados = 0;
                foreach ($modelo->reportesPendientes((int) $m['equipo_id'], $id) as $r) {
                    $texto = "Atendido en el mantenimiento $numero.";
                    $modelo->cerrarReporte((int) $r['id'], (int) $actual['tecnico_id'], max($datos['fecha_fin'], $r['creado_en']), $texto);
                    $auditoria->registrar($usuarioId, 'mantenimientos', (int) $r['id'], 'cerrar', [
                        'atendido_en' => $id, 'resultado' => $texto,
                    ]);
                    $cerrados++;
                }

                $proximoId = null;
                if ($datos['proximo_mantenimiento']) {
                    $proximo = [
                        'equipo_id'          => (int) $m['equipo_id'],
                        'tecnico_id'         => (int) $actual['tecnico_id'],
                        'tipo'               => 'preventivo',
                        'fecha_programada'   => $datos['proximo_mantenimiento'],
                        'problema_reportado' => "Mantenimiento preventivo sugerido al finalizar $numero."
                                              . ($datos['recomendaciones'] ? ' ' . $datos['recomendaciones'] : ''),
                    ];
                    $proximo['problema_reportado'] = mb_substr($proximo['problema_reportado'], 0, 1000);
                    $proximoId = $modelo->crear($proximo, $usuarioId);
                    $auditoria->registrar($usuarioId, 'mantenimientos', $proximoId, 'crear', [
                        'origen' => 'proximo_mantenimiento', 'de' => $id, 'datos' => $proximo,
                    ]);
                }
                return [$cerrados, $proximoId];
            });
        } catch (DomainException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect("/mantenimientos/$id");
        } catch (PDOException $e) {
            error_log('[mantenimientos.finalizar] ' . $e->getMessage());
            Session::flash('error', 'No pudimos finalizar el trabajo. Inténtalo de nuevo en unos minutos.');
            $this->volverAlFormulario($form, $datos, []);
        }

        $mensaje = "Se finalizó $numero. {$m['codigo']} queda «" . Equipo::OPERATIVOS[$datos['estado_final']] . '».';
        if ($cerrados) {
            $mensaje .= ' También se cerr' . ($cerrados === 1 ? 'ó 1 reporte de falla pendiente.' : "aron $cerrados reportes de falla pendientes.");
        }
        if ($proximoId) {
            $mensaje .= ' Se programó el próximo preventivo para el ' . Formato::fecha($datos['proximo_mantenimiento']) . '.';
        }
        Session::flash('ok', $mensaje);
        $this->redirect("/mantenimientos/$id");
    }

    /** GET /mantenimientos/{id}/cancelar */
    public function cancelacion(int $id): void
    {
        $m = $this->cargarParaOperar($id, ['programado', 'en_proceso']);
        $this->exigirSinRepuestos($m);

        $this->formulario("/mantenimientos/$id/cancelar", 'Cancelar ' . Mantenimiento::numero($id),
            "{$m['codigo']} · {$m['equipo']}. El trabajo queda anulado con su motivo; no se borra.",
            'Cancelar trabajo', $this->camposCancelar($m), [], "/mantenimientos/$id",
            $m['estado'] === 'en_proceso' ? 'El equipo está «En mantenimiento»: elige en qué estado queda.' : null, []);
    }

    /** POST /mantenimientos/{id}/cancelar */
    public function cancelar(int $id): void
    {
        $this->requireCsrf();
        $m    = $this->cargarParaOperar($id, ['programado', 'en_proceso']);
        $form = "/mantenimientos/$id/cancelar";
        $this->exigirSinRepuestos($m);

        [$datos, $errores] = Validador::validar($this->camposCancelar($m), $_POST);
        if ($errores) {
            $this->volverAlFormulario($form, $datos, $errores);
        }

        $usuarioId = Auth::user()['id'];
        $modelo    = new Mantenimiento();
        try {
            $modelo->transaccion(function () use ($modelo, $m, $id, $datos, $usuarioId) {
                $actual = $modelo->bloquear($id);
                if ($modelo->contarRepuestos($id)) {
                    throw new DomainException('El trabajo tiene repuestos registrados: quítalos antes de cancelarlo.');
                }
                $equipos = new Equipo();
                $equipo  = $equipos->bloquear((int) $m['equipo_id']);
                if (!$modelo->cancelar($id, $datos['motivo'])) {
                    throw new DomainException('Este trabajo ya se había finalizado o cancelado.');
                }

                $auditoria = new Auditoria();
                $auditoria->registrar($usuarioId, 'mantenimientos', $id, 'anular', [
                    'motivo' => $datos['motivo'], 'estado_anterior' => $actual['estado'],
                ]);
                $estado = $datos['estado_equipo'] ?? null;
                if ($estado && $estado !== $equipo['estado_operativo']) {
                    $equipos->fijarOperativo((int) $m['equipo_id'], $estado);
                    $auditoria->registrar($usuarioId, 'equipos', (int) $m['equipo_id'], 'editar', [
                        'cambios' => ['estado_operativo' => [$equipo['estado_operativo'], $estado]],
                        'mantenimiento_id' => $id,
                    ]);
                }
            });
        } catch (DomainException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect("/mantenimientos/$id");
        } catch (PDOException $e) {
            error_log('[mantenimientos.cancelar] ' . $e->getMessage());
            Session::flash('error', 'No pudimos cancelar el trabajo. Inténtalo de nuevo en unos minutos.');
            $this->volverAlFormulario($form, $datos, []);
        }

        Session::flash('ok', 'Se canceló ' . Mantenimiento::numero($id) . '.');
        $this->redirect("/mantenimientos/$id");
    }

    /** POST /mantenimientos/{id}/repuestos — descuenta el stock en el mismo momento. */
    public function agregarRepuesto(int $id): void
    {
        $this->requireCsrf();
        $m     = $this->cargarParaOperar($id, ['en_proceso']);
        $vuelta = "/mantenimientos/$id#repuestos";

        [$datos, $errores] = Validador::validar($this->camposRepuesto(), $_POST);
        if ($datos['cantidad'] !== null && !isset($errores['cantidad']) && (float) $datos['cantidad'] <= 0) {
            $errores['cantidad'] = 'La cantidad debe ser mayor que 0.';
        }
        if ($errores) {
            Session::flash('repuesto_old', $datos);
            Session::flash('repuesto_errores', $errores);
            $this->redirect($vuelta);
        }

        $repuestos = new Repuesto();
        $usuarioId = Auth::user()['id'];
        try {
            $r = $repuestos->transaccion(function () use ($repuestos, $id, $datos, $usuarioId) {
                $mant = (new Mantenimiento())->bloquear($id);
                if ($mant['estado'] !== 'en_proceso') {
                    throw new DomainException('El trabajo ya no está en proceso: no se le pueden agregar repuestos.');
                }
                $antes = $repuestos->bloquear($datos['repuesto_id']);
                if (!$repuestos->descontar($datos['repuesto_id'], $datos['cantidad'])) {
                    throw new DomainException("No hay stock suficiente de «{$antes['nombre']}»: quedan "
                        . RepuestoController::stockTexto($antes) . '.');
                }
                $datos['costo_unitario'] ??= $antes['costo_referencial'] ?? '0';
                $lineaId = (new Mantenimiento())->agregarRepuesto($id, $datos);
                $despues = $repuestos->bloquear($datos['repuesto_id']);
                (new Auditoria())->registrar($usuarioId, 'mantenimiento_repuestos', $lineaId, 'crear', [
                    'mantenimiento_id' => $id, 'datos' => $datos, 'stock' => [$antes['stock'], $despues['stock']],
                ]);
                return $despues;
            });
        } catch (DomainException $e) {
            Session::flash('repuesto_old', $datos);
            Session::flash('repuesto_errores', ['cantidad' => $e->getMessage()]);
            $this->redirect($vuelta);
        } catch (PDOException $e) {
            error_log('[mantenimientos.repuesto] ' . $e->getMessage());
            Session::flash('error', 'No pudimos agregar el repuesto. Inténtalo de nuevo en unos minutos.');
            Session::flash('repuesto_old', $datos);
            $this->redirect($vuelta);
        }

        Session::flash('ok', 'Se agregó «' . $r['nombre'] . '» (' . Formato::conUnidad($datos['cantidad'], $r['unidad']) . '). '
            . 'En bodega quedan ' . RepuestoController::stockTexto($r) . '.');
        $this->redirect($vuelta);
    }

    /** POST /mantenimientos/{id}/repuestos/{linea_id}/quitar — devuelve la cantidad al stock. */
    public function quitarRepuesto(int $id, int $lineaId): void
    {
        $this->requireCsrf();
        $this->cargarParaOperar($id, ['en_proceso']);
        $modelo = new Mantenimiento();
        $linea  = $modelo->lineaRepuesto($id, $lineaId) ?? $this->notFound();

        try {
            $modelo->transaccion(function () use ($modelo, $id, $linea, $lineaId) {
                if ($modelo->bloquear($id)['estado'] !== 'en_proceso') {
                    throw new DomainException('El trabajo ya no está en proceso: sus repuestos no se pueden cambiar.');
                }
                $modelo->quitarRepuesto($lineaId);
                (new Repuesto())->reponer((int) $linea['repuesto_id'], $linea['cantidad']);
                (new Auditoria())->registrar(Auth::user()['id'], 'mantenimiento_repuestos', $lineaId, 'anular', [
                    'mantenimiento_id' => $id, 'repuesto_id' => (int) $linea['repuesto_id'],
                    'cantidad' => $linea['cantidad'], 'costo_unitario' => $linea['costo_unitario'],
                ]);
            });
        } catch (DomainException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect("/mantenimientos/$id");
        } catch (PDOException $e) {
            error_log('[mantenimientos.quitar_repuesto] ' . $e->getMessage());
            Session::flash('error', 'No pudimos quitar el repuesto. Inténtalo de nuevo en unos minutos.');
            $this->redirect("/mantenimientos/$id#repuestos");
        }

        Session::flash('ok', 'Se quitó «' . $linea['nombre'] . '»: ' . Formato::conUnidad($linea['cantidad'], $linea['unidad'])
            . ' volvieron al stock.');
        $this->redirect("/mantenimientos/$id#repuestos");
    }

    // ------------------------------------------------------------------

    /** Tipos con su etiqueta para un <select>: ['preventivo' => 'Preventivo', ...]. */
    public static function tipos(): array
    {
        return array_map(fn ($t) => mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1), Formato::TIPOS_MANTENIMIENTO);
    }

    /** true si el usuario puede operar ese trabajo: administrador, o técnico con el trabajo libre o a su nombre. */
    public static function puedeOperar(array $m): bool
    {
        return match (Auth::rol()) {
            'administrador' => true,
            'tecnico'       => $m['tecnico_id'] === null || (int) $m['tecnico_id'] === (int) Auth::user()['persona_id'],
            default         => false,
        };
    }

    /** Campos de programar (sin $m) y editar un mantenimiento programado. */
    private function campos(?array $m): array
    {
        $hoy     = date('Y-m-d');
        $minimo  = $m && $m['fecha_programada'] && $m['fecha_programada'] < $hoy ? $m['fecha_programada'] : $hoy;
        $actual  = $m && $m['tecnico_id'] ? (int) $m['tecnico_id'] : null;
        $tecnicos = (new Persona())->opcionesTecnicos($actual);
        if (self::esTecnico()) {
            // El técnico solo se lo asigna a sí mismo (o lo deja libre); asignar a otro es del administrador
            $propio   = (int) Auth::user()['persona_id'];
            $tecnicos = array_intersect_key($tecnicos, [$propio => true] + ($actual ? [$actual => true] : []));
        }

        $equipo = $m
            ? ['nombre' => 'equipo', 'etiqueta' => 'Equipo', 'tipo' => 'fijo', 'valor' => "{$m['codigo']} · {$m['equipo']}"]
            : ['nombre' => 'equipo_id', 'etiqueta' => 'Equipo', 'tipo' => 'select', 'requerido' => true, 'entero' => true,
               'opciones' => (new Equipo())->opciones(false)];

        return [
            $equipo,
            ['nombre' => 'tipo', 'etiqueta' => 'Tipo de mantenimiento', 'tipo' => 'select', 'requerido' => true,
             'opciones' => self::tipos(),
             'ayuda' => 'Preventivo: limpieza y revisión periódica. Correctivo: reparar una falla. Actualización: software o piezas.'],
            ['nombre' => 'fecha_programada', 'etiqueta' => 'Fecha programada', 'tipo' => 'fecha', 'min_valor' => $minimo,
             'ayuda' => 'Obligatoria para los preventivos. Sin fecha, el trabajo queda para atenderlo cuanto antes.'],
            ['nombre' => 'problema_reportado', 'etiqueta' => 'Qué hay que hacer', 'tipo' => 'textarea', 'requerido' => true,
             'min' => 5, 'max' => 1000, 'ayuda' => 'El problema a revisar o el trabajo previsto.'],
            ['nombre' => 'tecnico_id', 'etiqueta' => 'Técnico', 'tipo' => 'select', 'entero' => true,
             'opciones' => $tecnicos, 'vacia' => 'Sin asignar',
             'ayuda' => self::esTecnico() ? 'Asígnatelo o déjalo libre para que lo tome otro técnico.' : 'Puedes dejarlo libre: el técnico lo toma desde la bandeja.'],
        ];
    }

    private static function reglasProgramacion(array $datos): array
    {
        $errores = [];
        if (($datos['tipo'] ?? null) === 'preventivo' && empty($datos['fecha_programada'])) {
            $errores['fecha_programada'] = 'Un mantenimiento preventivo necesita fecha programada.';
        }
        return $errores;
    }

    private function camposFinalizar(array $m): array
    {
        $esReporte = Mantenimiento::esReporte($m);
        return [
            ['nombre' => 'fecha_fin', 'etiqueta' => 'Fecha y hora de finalización', 'tipo' => 'fechahora', 'requerido' => true,
             'min_valor' => $m['fecha_inicio'], 'max_valor' => date('Y-m-d H:i')],
            ['nombre' => 'diagnostico', 'etiqueta' => 'Diagnóstico', 'tipo' => 'textarea', 'requerido' => true, 'min' => 5, 'max' => 2000,
             'ayuda' => $esReporte ? 'Qué causaba la falla reportada.' : 'Qué se encontró al revisar el equipo.'],
            ['nombre' => 'trabajo_realizado', 'etiqueta' => 'Trabajo realizado', 'tipo' => 'textarea', 'requerido' => true,
             'min' => 5, 'max' => 2000],
            ['nombre' => 'resultado', 'etiqueta' => 'Resultado', 'tipo' => 'textarea', 'requerido' => true, 'min' => 3, 'max' => 1000,
             'ayuda' => 'Cómo quedó el equipo y qué pruebas se hicieron.'],
            ['nombre' => 'estado_final', 'etiqueta' => 'Estado en que queda el equipo', 'tipo' => 'select', 'requerido' => true,
             'opciones' => self::ESTADOS_FINALES],
            ['nombre' => 'costo_mano_obra', 'etiqueta' => 'Costo de mano de obra (USD)', 'tipo' => 'numero', 'max_valor' => 9999999999,
             'ayuda' => 'Déjalo vacío si lo hizo el personal de la institución sin costo.'],
            ['nombre' => 'recomendaciones', 'etiqueta' => 'Recomendaciones', 'tipo' => 'textarea', 'max' => 1000],
            ['nombre' => 'proximo_mantenimiento', 'etiqueta' => 'Próximo mantenimiento', 'tipo' => 'fecha',
             'min_valor' => date('Y-m-d', strtotime('+1 day')),
             'ayuda' => 'Si pones una fecha, se programa automáticamente un preventivo para ese día.'],
        ];
    }

    private function avisoFinalizar(array $m): ?string
    {
        $modelo    = new Mantenimiento();
        $repuestos = $modelo->repuestos((int) $m['id']);
        $reportes  = count($modelo->reportesPendientes((int) $m['equipo_id'], (int) $m['id']));

        $partes = [];
        if ($repuestos) {
            $total    = array_sum(array_map(fn ($r) => (float) $r['subtotal'], $repuestos));
            $partes[] = 'Repuestos usados: ' . Formato::plural(count($repuestos), 'línea', 'líneas') . ' por '
                      . Formato::dinero((string) $total) . '; se suman a la mano de obra en el costo total.';
        }
        if ($reportes) {
            $partes[] = 'Se cerrará' . ($reportes === 1 ? ' también 1 reporte de falla pendiente' : "n también $reportes reportes de falla pendientes")
                      . ' de este equipo.';
        }
        return $partes ? implode(' ', $partes) : null;
    }

    private function camposCancelar(array $m): array
    {
        $enProceso = $m['estado'] === 'en_proceso';
        $estado = ['nombre' => 'estado_equipo', 'etiqueta' => 'Estado en que queda el equipo', 'tipo' => 'select',
                   'requerido' => $enProceso, 'opciones' => self::ESTADOS_FINALES];
        if (!$enProceso) {
            $estado['vacia'] = 'No cambiar (sigue «' . Equipo::OPERATIVOS[$m['estado_operativo']] . '»)';
            $estado['ayuda'] = 'Por ejemplo, si el reporte era una falsa alarma, márcalo como operativo.';
        }
        return [
            ['nombre' => 'motivo', 'etiqueta' => 'Motivo de la cancelación', 'tipo' => 'textarea', 'requerido' => true,
             'min' => 5, 'max' => 1000, 'ayuda' => 'Por ejemplo: reporte duplicado, el equipo se dio de baja, lo atiende el proveedor por garantía.'],
            $estado,
        ];
    }

    private function camposRepuesto(): array
    {
        return [
            ['nombre' => 'repuesto_id', 'etiqueta' => 'Repuesto', 'tipo' => 'select', 'requerido' => true, 'entero' => true,
             'opciones' => (new Repuesto())->opcionesConStock()],
            ['nombre' => 'cantidad', 'etiqueta' => 'Cantidad', 'tipo' => 'numero', 'requerido' => true, 'max_valor' => 99999999],
            ['nombre' => 'costo_unitario', 'etiqueta' => 'Costo unitario (USD)', 'tipo' => 'numero', 'max_valor' => 9999999999,
             'ayuda' => 'Vacío = el costo de referencia del repuesto.'],
            ['nombre' => 'detalle', 'etiqueta' => 'Detalle', 'tipo' => 'text', 'max' => 255,
             'ayuda' => 'Por ejemplo: se reemplazó la memoria dañada del banco 2.'],
        ];
    }

    /**
     * Mantenimiento en uno de los estados indicados que el usuario puede operar.
     * Si no, vuelve a su ficha con el motivo.
     */
    private function cargarParaOperar(int $id, array $estados): array
    {
        $m = (new Mantenimiento())->porId($id) ?? $this->notFound();
        if (!self::puedeOperar($m)) {
            Session::flash('error', "Este trabajo está asignado a {$m['tecnico']}: solo esa persona o el administrador pueden cambiarlo.");
            $this->redirect("/mantenimientos/$id");
        }
        if (!in_array($m['estado'], $estados, true)) {
            Session::flash('error', 'Este trabajo está «' . Mantenimiento::ESTADOS[$m['estado']][0] . '»: esa acción ya no está disponible.');
            $this->redirect("/mantenimientos/$id");
        }
        return $m;
    }

    private function exigirSinRepuestos(array $m): void
    {
        if ((new Mantenimiento())->contarRepuestos((int) $m['id'])) {
            Session::flash('error', 'El trabajo tiene repuestos registrados: quítalos antes de cancelarlo (vuelven al stock) o finaliza el trabajo.');
            $this->redirect("/mantenimientos/{$m['id']}#repuestos");
        }
    }

    /** Formulario de una sola sección (vista genérica de catálogos). */
    private function formulario(string $accion, string $encabezado, string $intro, string $boton, array $campos,
                                array $valores, string $volver, ?string $aviso, array $ocultos): void
    {
        $this->view('catalogos/form', [
            'titulo'     => $encabezado,
            'def'        => ['ruta' => $volver, 'singular' => '', 'nuevo' => '',
                             'titulo' => str_starts_with($volver, '/equipos/') ? 'la ficha del equipo'
                                       : ($volver === '/mantenimientos' ? 'mantenimientos' : 'el mantenimiento')],
            'id'         => null,
            'accion'     => $accion,
            'encabezado' => $encabezado,
            'intro'      => $intro,
            'aviso'      => $aviso,
            'boton'      => $boton,
            'campos'     => $campos,
            'ocultos'    => $ocultos,
            'valores'    => Session::flash('form_old') ?? $valores,
            'errores'    => Session::flash('form_errores') ?? [],
            'csrf'       => Session::csrfToken(),
            'scripts'    => ['panel.js'],
        ], 'panel');
    }

    /** Ruta de regreso enviada por el formulario o la URL; solo rutas internas conocidas. */
    private function volver(string $porDefecto): string
    {
        $v = $_POST['volver'] ?? $_GET['volver'] ?? '';
        return is_string($v) && preg_match('#^/equipos/\d+$|^/mantenimientos$#', $v) ? $v : $porDefecto;
    }

    private function volverAlFormulario(string $ruta, array $datos, array $errores): never
    {
        Session::flash('form_old', $datos);
        if ($errores) {
            Session::flash('form_errores', $errores);
        }
        $this->redirect($ruta);
    }

    private static function idDeQuery(string $clave): ?int
    {
        $v = $_GET[$clave] ?? null;
        return is_string($v) && ctype_digit($v) ? (int) $v : null;
    }

    private static function esTecnico(): bool
    {
        return Auth::rol() === 'tecnico';
    }

    private static function puede(array $roles): bool
    {
        return in_array(Auth::rol(), $roles, true);
    }
}
