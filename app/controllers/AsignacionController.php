<?php
/**
 * Asignaciones: entrega de un equipo a una persona, su devolución, el listado y el acta imprimible.
 *
 * Reglas (Sprint 4):
 * - Se entrega un equipo vigente, sin otra entrega abierta y operativo o con fallas.
 * - Si la ubicación de entrega o el destino de la devolución difiere de la actual, se registra
 *   el movimiento en la misma transacción (Movimiento::registrar es la única vía para mover).
 * - Al devolver: a una bodega queda "En bodega"; a otro lugar, "En uso". Si vuelve con fallas
 *   o sin funcionar, cambia su estado operativo.
 *
 * Ver, entregar y devolver: administrador e inventario (el técnico no).
 */
final class AsignacionController extends Controller
{
    public const VER       = ['administrador', 'inventario'];
    public const GESTIONAR = ['administrador', 'inventario'];

    private const POR_PAGINA = 15;

    /** GET /asignaciones */
    public function index(): void
    {
        $filtros = [
            'q'      => mb_substr(trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : ''), 0, 100),
            'estado' => array_key_exists($_GET['estado'] ?? '', Asignacion::ESTADOS) ? $_GET['estado'] : 'abiertas',
        ];
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));

        $this->view('asignaciones/lista', [
            'titulo'         => 'Asignaciones',
            'filtros'        => $filtros,
            'resultado'      => (new Asignacion())->listar($filtros, $pagina, self::POR_PAGINA),
            'puedeGestionar' => self::puede(self::GESTIONAR),
            'estilos'        => ['equipos.css', 'asignaciones.css'],
        ], 'panel');
    }

    /** GET /asignaciones/nueva?equipo=ID o ?persona=ID */
    public function nueva(): void
    {
        $equipoId  = self::idDeQuery('equipo');
        $personaId = self::idDeQuery('persona');
        $volver    = $this->volver($equipoId ? "/equipos/$equipoId" : ($personaId ? "/personas/$personaId" : '/asignaciones'));
        $aviso     = null;

        if ($equipoId) {
            $equipo = (new Equipo())->porId($equipoId) ?? $this->notFound();
            if ($motivo = self::motivoNoEntregable($equipo)) {
                Session::flash('error', $motivo);
                $this->redirect("/equipos/$equipoId");
            }
            if ($equipo['estado_operativo'] === 'con_fallas') {
                $aviso = "{$equipo['codigo']} tiene fallas reportadas. Se puede entregar, pero quedará anotado en el estado de entrega.";
            }
        }
        if ($personaId) {
            $persona = (new Persona())->ficha($personaId) ?? $this->notFound();
            if (!$persona['activo']) {
                Session::flash('error', "«{$persona['nombre']}» está inactiva: no se le pueden entregar equipos.");
                $this->redirect("/personas/$personaId");
            }
        }

        $this->formulario('/asignaciones', 'Entregar un equipo',
            'Registra a quién se entrega y dónde lo usará. El equipo queda «En uso» y a cargo de esa persona.',
            'Registrar entrega', $this->camposEntrega($equipoId),
            ['equipo_id' => $equipoId, 'persona_id' => $personaId, 'fecha_entrega' => date('Y-m-d H:i')],
            $volver, $aviso);
    }

    /** POST /asignaciones */
    public function guardar(): void
    {
        $this->requireCsrf();
        $volver = $this->volver('/asignaciones');
        $form   = '/asignaciones/nueva?volver=' . rawurlencode($volver);

        [$datos, $errores] = Validador::validar($this->camposEntrega(null), $_POST);

        // Un equipo que existe pero ya no está en la lista (otro lo entregó mientras tanto): decir por qué
        $enviado = $_POST['equipo_id'] ?? null;
        if (isset($errores['equipo_id']) && is_string($enviado) && ctype_digit($enviado)
            && ($otro = (new Equipo())->porId((int) $enviado)) && ($motivo = self::motivoNoEntregable($otro))) {
            $errores['equipo_id'] = $motivo;
        }

        $equipo = $datos['equipo_id'] ? (new Equipo())->porId($datos['equipo_id']) : null;
        if ($equipo && !isset($errores['fecha_entrega']) && ($ultima = (new Equipo())->ultimaFecha($equipo['id']))
            && $datos['fecha_entrega'] < $ultima) {
            $errores['fecha_entrega'] = 'El equipo tiene un registro del ' . Formato::fechaHora($ultima) . ': la entrega no puede ser anterior.';
        }
        if ($errores) {
            $this->volverAlFormulario($form, $datos, $errores);
        }

        $persona   = (new Persona())->ficha($datos['persona_id']);
        $usuarioId = Auth::user()['id'];
        $estadoEntrega = $datos['estado_entrega'];
        if ($equipo['estado_operativo'] === 'con_fallas' && !str_starts_with($estadoEntrega, 'Con fallas reportadas')) {
            $estadoEntrega = "Con fallas reportadas. $estadoEntrega";
        }

        $modelo = new Asignacion();
        try {
            $id = $modelo->transaccion(function () use ($modelo, $datos, $persona, $usuarioId, $estadoEntrega) {
                $equipos = new Equipo();
                $actual  = $equipos->bloquear($datos['equipo_id']);
                if ($motivo = self::motivoNoEntregable($actual + ['responsable' => null])) {
                    throw new DomainException($motivo);
                }

                $id = $modelo->crear($datos['equipo_id'], $datos['persona_id'], $datos['ubicacion_entrega_id'],
                    $datos['fecha_entrega'], $estadoEntrega, $datos['observaciones'], $usuarioId);

                $auditoria = new Auditoria();
                $auditoria->registrar($usuarioId, 'asignaciones', $id, 'crear', ['datos' => $datos + ['estado_entrega' => $estadoEntrega]]);

                // Si se usará en otro lugar, el traslado queda registrado con la misma fecha
                if ((int) $actual['ubicacion_actual_id'] !== $datos['ubicacion_entrega_id']) {
                    $motivo = 'Entrega a ' . $persona['nombre'];
                    $movId  = (new Movimiento())->registrar($datos['equipo_id'], $actual['ubicacion_actual_id'] ? (int) $actual['ubicacion_actual_id'] : null,
                        $datos['ubicacion_entrega_id'], $motivo, $usuarioId, null, null, $datos['fecha_entrega']);
                    $auditoria->registrar($usuarioId, 'movimientos', $movId, 'crear', [
                        'equipo_id' => $datos['equipo_id'], 'origen' => $actual['ubicacion_actual_id'],
                        'destino' => $datos['ubicacion_entrega_id'], 'motivo' => $motivo,
                    ]);
                }
                if ($actual['estado_inventario'] !== 'activo') {
                    $equipos->fijarInventario($datos['equipo_id'], 'activo');
                    $auditoria->registrar($usuarioId, 'equipos', $datos['equipo_id'], 'editar', [
                        'cambios' => ['estado_inventario' => [$actual['estado_inventario'], 'activo']],
                    ]);
                }
                return $id;
            });
        } catch (DomainException $e) {
            Session::flash('error', $e->getMessage());
            $this->volverAlFormulario($form, $datos, []);
        } catch (PDOException $e) {
            error_log('[asignaciones.crear] ' . $e->getMessage());
            // El índice único uq_asignacion_abierta frena dos entregas abiertas del mismo equipo
            Session::flash('error', self::esDuplicado($e)
                ? "{$equipo['codigo']} se acaba de entregar a otra persona. Revisa su ficha antes de volver a intentarlo."
                : 'No pudimos registrar la entrega. Inténtalo de nuevo en unos minutos.');
            $this->volverAlFormulario($form, $datos, []);
        }

        Session::flash('ok', "Se entregó {$equipo['codigo']} a {$persona['nombre']}. Imprime el acta para firmarla y luego súbela escaneada en «Adjuntos».");
        $this->redirect($volver === '/asignaciones' ? "/equipos/{$equipo['id']}" : $volver);
    }

    /** GET /asignaciones/{id}/devolucion */
    public function devolucion(int $id): void
    {
        $a = $this->cargarAbierta($id);

        $this->formulario("/asignaciones/$id/devolucion", "Devolución de {$a['codigo']}",
            "{$a['persona']} devuelve «{$a['equipo']}», entregado el " . Formato::fechaHora($a['fecha_entrega'])
            . '. Si vuelve a una bodega queda «En bodega»; si vuelve con fallas, cambia su estado operativo.',
            'Registrar devolución', $this->camposDevolucion($a),
            ['fecha_devolucion' => date('Y-m-d H:i'), 'estado' => 'bueno', 'destino_id' => (new Ubicacion())->bodegaPrincipal()],
            $this->volver("/equipos/{$a['equipo_id']}"), null);
    }

    /** POST /asignaciones/{id}/devolucion */
    public function devolver(int $id): void
    {
        $this->requireCsrf();
        $a      = $this->cargarAbierta($id);
        $volver = $this->volver("/equipos/{$a['equipo_id']}");
        $form   = "/asignaciones/$id/devolucion?volver=" . rawurlencode($volver);

        [$datos, $errores] = Validador::validar($this->camposDevolucion($a), $_POST);
        if (($datos['estado'] ?? null) && $datos['estado'] !== 'bueno' && mb_strlen((string) $datos['nota']) < 5) {
            $errores['nota'] = 'Describe qué falla tiene (al menos 5 caracteres): el técnico lo necesita para revisarlo.';
        }
        if ($errores) {
            $this->volverAlFormulario($form, $datos, $errores);
        }

        $usuarioId = Auth::user()['id'];
        $texto     = Asignacion::textoDevolucion($datos['estado'], $datos['nota']);
        $modelo    = new Asignacion();

        try {
            $modelo->transaccion(function () use ($modelo, $a, $datos, $texto, $usuarioId) {
                $equipos = new Equipo();
                $antes   = $equipos->bloquear($a['equipo_id']);
                if (!$modelo->cerrar($a['id'], $datos['fecha_devolucion'], $texto)) {
                    throw new DomainException("La devolución de {$a['codigo']} ya se había registrado.");
                }

                $auditoria = new Auditoria();
                $auditoria->registrar($usuarioId, 'asignaciones', $a['id'], 'cerrar', [
                    'fecha_devolucion' => $datos['fecha_devolucion'], 'estado_devolucion' => $texto,
                ]);

                if ((int) $antes['ubicacion_actual_id'] !== $datos['destino_id']) {
                    $motivo = 'Devolución de ' . $a['persona'];
                    $movId  = (new Movimiento())->registrar($a['equipo_id'], $antes['ubicacion_actual_id'] ? (int) $antes['ubicacion_actual_id'] : null,
                        $datos['destino_id'], $motivo, $usuarioId, null, null, $datos['fecha_devolucion']);
                    $auditoria->registrar($usuarioId, 'movimientos', $movId, 'crear', [
                        'equipo_id' => $a['equipo_id'], 'origen' => $antes['ubicacion_actual_id'],
                        'destino' => $datos['destino_id'], 'motivo' => $motivo,
                    ]);
                }

                $inventario = (new Ubicacion())->esBodega($datos['destino_id']) ? 'en_bodega' : 'activo';
                if ($inventario !== $antes['estado_inventario']) {
                    $equipos->fijarInventario($a['equipo_id'], $inventario);
                }
                if ($operativo = Asignacion::DEVOLUCION[$datos['estado']][1]) {
                    $equipos->marcarFalla($a['equipo_id'], $operativo); // no "mejora" un estado peor
                }

                $despues = $equipos->bloquear($a['equipo_id']);
                $cambios = [];
                foreach (['estado_inventario', 'estado_operativo'] as $campo) {
                    if ($antes[$campo] !== $despues[$campo]) {
                        $cambios[$campo] = [$antes[$campo], $despues[$campo]];
                    }
                }
                if ($cambios) {
                    $auditoria->registrar($usuarioId, 'equipos', $a['equipo_id'], 'editar', ['cambios' => $cambios]);
                }
            });
        } catch (DomainException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect($volver);
        } catch (PDOException $e) {
            error_log('[asignaciones.devolver] ' . $e->getMessage());
            Session::flash('error', 'No pudimos registrar la devolución. Inténtalo de nuevo en unos minutos.');
            $this->volverAlFormulario($form, $datos, []);
        }

        Session::flash('ok', "Se registró la devolución de {$a['codigo']} por {$a['persona']}.");
        $this->redirect($volver);
    }

    /** GET /asignaciones/{id}/acta — acta de entrega (y de devolución, si ya se devolvió) para imprimir y firmar. */
    public function acta(int $id): void
    {
        $a = (new Asignacion())->porId($id) ?? $this->notFound();

        $this->view('asignaciones/acta', [
            'titulo'           => 'Acta ' . self::numeroActa($id),
            'a'                => $a,
            'numero'           => self::numeroActa($id),
            'especificaciones' => (new Especificacion())->deEquipo((int) $a['equipo_id']),
            'volver'           => $this->volver("/equipos/{$a['equipo_id']}"),
            'estilos'          => ['acta.css'],
        ], 'impresion');
    }

    // ------------------------------------------------------------------

    /** "ACT-000042" */
    public static function numeroActa(int $id): string
    {
        return 'ACT-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    /** Motivo por el que no se puede entregar el equipo, o null si se puede. */
    public static function motivoNoEntregable(array $e): ?string
    {
        return match (true) {
            $e['estado_inventario'] === 'de_baja' =>
                "{$e['codigo']} está dado de baja: no se puede entregar.",
            $e['asignacion_id'] !== null =>
                "{$e['codigo']} ya está entregado" . (!empty($e['responsable']) ? " a {$e['responsable']}" : '')
                . '. Primero registra su devolución.',
            !in_array($e['estado_operativo'], Equipo::ENTREGABLES, true) =>
                "{$e['codigo']} está «" . Equipo::OPERATIVOS[$e['estado_operativo']] . '»: no se puede entregar hasta que el técnico lo deje operativo.',
            default => null,
        };
    }

    private function camposEntrega(?int $equipoId): array
    {
        return [
            ['nombre' => 'equipo_id', 'etiqueta' => 'Equipo', 'tipo' => 'select', 'requerido' => true, 'entero' => true,
             'opciones' => (new Equipo())->opciones(true),
             'ayuda' => 'Solo aparecen los equipos libres, operativos o con fallas.'],
            ['nombre' => 'persona_id', 'etiqueta' => 'Se entrega a', 'tipo' => 'select', 'requerido' => true, 'entero' => true,
             'opciones' => (new Persona())->opcionesActivas()],
            ['nombre' => 'ubicacion_entrega_id', 'etiqueta' => 'Dónde lo usará', 'tipo' => 'select', 'requerido' => true,
             'entero' => true, 'opciones' => (new Ubicacion())->opcionesConSede(),
             'ayuda' => 'Si es distinta de la ubicación actual, el traslado se registra solo.'],
            ['nombre' => 'fecha_entrega', 'etiqueta' => 'Fecha y hora de entrega', 'tipo' => 'fechahora', 'requerido' => true,
             'max_valor' => date('Y-m-d H:i')],
            ['nombre' => 'estado_entrega', 'etiqueta' => 'Estado en que se entrega', 'tipo' => 'textarea', 'requerido' => true,
             'min' => 3, 'max' => 500,
             'ayuda' => 'Estado físico y accesorios: cargador, cable HDMI, control, maletín…'],
            ['nombre' => 'observaciones', 'etiqueta' => 'Observaciones', 'tipo' => 'textarea', 'max' => 1000],
        ];
    }

    private function camposDevolucion(array $a): array
    {
        $ultima = (new Equipo())->ultimaFecha((int) $a['equipo_id']);
        return [
            ['nombre' => 'fecha_devolucion', 'etiqueta' => 'Fecha y hora de devolución', 'tipo' => 'fechahora', 'requerido' => true,
             'min_valor' => max($a['fecha_entrega'], (string) $ultima), 'max_valor' => date('Y-m-d H:i')],
            ['nombre' => 'estado', 'etiqueta' => 'Cómo vuelve el equipo', 'tipo' => 'select', 'requerido' => true,
             'opciones' => array_map(fn ($d) => $d[0], Asignacion::DEVOLUCION),
             'ayuda' => 'Con fallas o sin funcionar, el equipo queda marcado para que el técnico lo revise.'],
            ['nombre' => 'nota', 'etiqueta' => 'Nota', 'tipo' => 'textarea', 'max' => 400,
             'ayuda' => 'Obligatoria si vuelve con fallas o no funciona: describe qué tiene y qué accesorios faltan.'],
            ['nombre' => 'destino_id', 'etiqueta' => 'A dónde vuelve', 'tipo' => 'select', 'requerido' => true, 'entero' => true,
             'opciones' => (new Ubicacion())->opcionesConSede(),
             'ayuda' => 'Las bodegas aparecen primero.'],
        ];
    }

    /** Asignación abierta o redirige a la ficha del equipo con el aviso. */
    private function cargarAbierta(int $id): array
    {
        $a = (new Asignacion())->porId($id) ?? $this->notFound();
        if ($a['fecha_devolucion'] !== null) {
            Session::flash('error', "La devolución de {$a['codigo']} ya se registró el " . Formato::fechaHora($a['fecha_devolucion']) . '.');
            $this->redirect("/equipos/{$a['equipo_id']}");
        }
        return $a;
    }

    /** Formulario de una sola sección (vista genérica de catálogos). */
    private function formulario(string $accion, string $encabezado, string $intro, string $boton,
                                array $campos, array $valores, string $volver, ?string $aviso): void
    {
        $this->view('catalogos/form', [
            'titulo'     => $encabezado,
            'def'        => ['ruta' => $volver, 'titulo' => self::textoVolver($volver), 'singular' => '', 'nuevo' => ''],
            'id'         => null,
            'accion'     => $accion,
            'encabezado' => $encabezado,
            'intro'      => $intro,
            'aviso'      => $aviso,
            'boton'      => $boton,
            'campos'     => $campos,
            'ocultos'    => ['volver' => $volver],
            'valores'    => Session::flash('form_old') ?? $valores,
            'errores'    => Session::flash('form_errores') ?? [],
            'csrf'       => Session::csrfToken(),
            'scripts'    => ['panel.js'],
        ], 'panel');
    }

    /** Ruta de regreso enviada por el formulario o la URL; solo se aceptan rutas internas conocidas. */
    private function volver(string $porDefecto): string
    {
        $v = $_POST['volver'] ?? $_GET['volver'] ?? '';
        return is_string($v) && preg_match('#^/(equipos|personas)/\d+$|^/asignaciones$#', $v) ? $v : $porDefecto;
    }

    private static function textoVolver(string $ruta): string
    {
        return match (true) {
            str_starts_with($ruta, '/equipos/')  => 'la ficha del equipo',
            str_starts_with($ruta, '/personas/') => 'la ficha de la persona',
            default                              => 'asignaciones',
        };
    }

    private static function idDeQuery(string $clave): ?int
    {
        $v = $_GET[$clave] ?? null;
        return is_string($v) && ctype_digit($v) ? (int) $v : null;
    }

    private function volverAlFormulario(string $ruta, array $datos, array $errores): never
    {
        Session::flash('form_old', $datos);
        if ($errores) {
            Session::flash('form_errores', $errores);
        }
        $this->redirect($ruta);
    }

    private static function esDuplicado(PDOException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062;
    }

    private static function puede(array $roles): bool
    {
        return in_array(Auth::rol(), $roles, true);
    }
}
