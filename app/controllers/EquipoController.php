<?php
/**
 * Equipos: listado con filtros, alta (con movimiento de ingreso), edición, ficha,
 * baja (y su reversión) y etiquetas QR imprimibles.
 *
 * Listado y etiquetas: administrador e inventario. Ficha: también el técnico (solo lectura, más
 * especificaciones y componentes). Alta, edición y baja: administrador e inventario.
 * Revertir una baja: solo administrador. Los roles se aplican en routes.php con estas constantes.
 */
final class EquipoController extends Controller
{
    public const VER        = ['administrador', 'inventario', 'tecnico'];   // ficha del equipo
    public const LISTAR     = ['administrador', 'inventario'];              // listado y etiquetas (el técnico no)
    public const GESTIONAR  = ['administrador', 'inventario'];
    public const REVERTIR   = ['administrador'];
    /** Especificaciones y componentes (ver EspecificacionController y ComponenteController). */
    public const ESPECIFICAR = ['administrador', 'inventario', 'tecnico'];
    public const COMPONENTES = ['administrador', 'tecnico'];

    private const POR_PAGINA        = 15;
    private const MAX_ETIQUETAS     = 60;
    private const MOTIVO_INGRESO    = 'Ingreso al inventario';
    /** Estados operativos que se pueden elegir al dar de alta (el mantenimiento lo abre el técnico). */
    private const OPERATIVOS_ALTA   = ['operativo', 'con_fallas', 'inoperativo'];

    /** GET /equipos */
    public function index(): void
    {
        $filtros = $this->filtros();
        $pagina  = max(1, (int) ($_GET['pagina'] ?? 1));

        $this->view('equipos/lista', [
            'titulo'        => 'Equipos',
            'filtros'       => $filtros,
            'resultado'     => (new Equipo())->listar($filtros, $pagina, self::POR_PAGINA),
            'categorias'    => (new Categoria())->opciones(),
            'ubicaciones'   => (new Ubicacion())->opcionesConSede(),
            'areas'         => (new Area())->opciones(),
            'puedeGestionar' => self::puede(self::GESTIONAR),
            'estilos'       => ['equipos.css'],
            'scripts'       => ['equipos.js'],
        ], 'panel');
    }

    /** GET /equipos/nuevo */
    public function nuevo(): void
    {
        $this->formulario(null, ['estado_operativo' => 'operativo']);
    }

    /** POST /equipos */
    public function guardar(): void
    {
        $this->requireCsrf();
        $modelo = new Equipo();

        [$datos, $errores] = Validador::validar($this->camposPlanos(null), $_POST);
        $errores += $this->validarExtra($modelo, $datos, null);
        if ($errores) {
            $this->volverAlFormulario('/equipos/nuevo', $datos, $errores);
        }

        $datos['origen'] ??= 'no_especificado';
        $ubicacionId = $datos['ubicacion_id'];
        $usuarioId   = Auth::user()['id'];

        try {
            // Equipo, movimiento de ingreso y auditoría: todo o nada
            $id = $modelo->transaccion(function () use ($modelo, $datos, $ubicacionId, $usuarioId) {
                $id    = $modelo->crear($datos, $datos['estado_operativo'], $usuarioId);
                $movId = (new Movimiento())->registrar($id, null, $ubicacionId, self::MOTIVO_INGRESO, $usuarioId);

                $auditoria = new Auditoria();
                $auditoria->registrar($usuarioId, 'equipos', $id, 'crear', ['datos' => $datos]);
                $auditoria->registrar($usuarioId, 'movimientos', $movId, 'crear', [
                    'equipo_id' => $id, 'destino' => $ubicacionId, 'motivo' => self::MOTIVO_INGRESO,
                ]);
                return $id;
            });
        } catch (PDOException $e) {
            error_log('[equipos.crear] ' . $e->getMessage());
            // Otro usuario pudo tomar el mismo código entre la validación y el guardado
            if ($this->esDuplicado($e)) {
                $this->volverAlFormulario('/equipos/nuevo', $datos, ['codigo' => 'Ese código se acaba de usar. Revisa el código propuesto.']);
            }
            Session::flash('error', 'No pudimos guardar. Inténtalo de nuevo en unos minutos.');
            $this->volverAlFormulario('/equipos/nuevo', $datos, []);
        }

        Session::flash('ok', "Se registró el equipo {$datos['codigo']} en bodega. Ya puedes imprimir su etiqueta.");
        $this->redirect("/equipos/$id");
    }

    /** GET /equipos/{id} */
    public function ver(int $id): void
    {
        $equipo = $this->cargar($id);

        $this->view('equipos/ver', [
            'titulo'           => $equipo['codigo'],
            'e'                => $equipo,
            'especificaciones' => (new Especificacion())->deEquipo($id),
            'componentes'      => (new Componente())->deEquipo($id),
            'movimientos'      => (new Movimiento())->deEquipo($id, 5),
            'totalMovimientos' => (new Movimiento())->contarDe($id),
            'asignaciones'     => (new Asignacion())->deEquipo($id),
            'noEntregable'     => AsignacionController::motivoNoEntregable($equipo),
            'enProceso'        => (new Mantenimiento())->enProcesoDe($id),
            'mantenimientos'   => (new Mantenimiento())->deEquipo($id, 5),
            'totalMantenimientos' => (new Mantenimiento())->contarDe($id),
            'puedeMantener'    => self::puede(MantenimientoController::GESTIONAR),
            'verInventario'    => self::puede(self::LISTAR),
            'puedeGestionar'   => self::puede(self::GESTIONAR),
            'puedeRevertir'    => self::puede(self::REVERTIR),
            'verAuditoria'     => self::puede(AuditoriaController::VER),
            'puedeEspecificar' => self::puede(self::ESPECIFICAR),
            'puedeComponentes' => self::puede(self::COMPONENTES),
            'csrf'             => Session::csrfToken(),
            'estilos'          => ['equipos.css', 'asignaciones.css'],
            'scripts'          => ['panel.js'],
        ], 'panel');
    }

    /** GET /equipos/{id}/editar */
    public function editar(int $id): void
    {
        $equipo = $this->cargar($id);
        $this->exigirVigente($equipo);
        $this->formulario($equipo, $equipo);
    }

    /** POST /equipos/{id} */
    public function actualizar(int $id): void
    {
        $this->requireCsrf();
        $modelo = new Equipo();
        $equipo = $this->cargar($id);
        $this->exigirVigente($equipo);

        [$datos, $errores] = Validador::validar($this->camposPlanos($equipo), $_POST);
        $errores += $this->validarExtra($modelo, $datos, $id);
        if ($errores) {
            $this->volverAlFormulario("/equipos/$id/editar", $datos, $errores);
        }
        $datos['origen'] ??= 'no_especificado';

        // Solo se audita lo que cambió: [campo => [antes, después]]
        $cambios = [];
        foreach ($datos as $campo => $valor) {
            if ((string) ($equipo[$campo] ?? '') !== (string) ($valor ?? '')) {
                $cambios[$campo] = [$equipo[$campo] ?? null, $valor];
            }
        }
        if (!$cambios) {
            Session::flash('ok', "No había cambios que guardar en {$equipo['codigo']}.");
            $this->redirect("/equipos/$id");
        }

        try {
            $modelo->transaccion(function () use ($modelo, $id, $datos, $cambios) {
                $modelo->actualizar($id, $datos);
                (new Auditoria())->registrar(Auth::user()['id'], 'equipos', $id, 'editar', ['cambios' => $cambios]);
            });
        } catch (PDOException $e) {
            error_log('[equipos.actualizar] ' . $e->getMessage());
            Session::flash('error', 'No pudimos guardar. Inténtalo de nuevo en unos minutos.');
            $this->volverAlFormulario("/equipos/$id/editar", $datos, []);
        }

        Session::flash('ok', "Se guardaron los cambios de {$datos['codigo']}.");
        $this->redirect("/equipos/$id");
    }

    /** GET /equipos/{id}/baja */
    public function baja(int $id): void
    {
        $equipo = $this->cargar($id);
        $this->exigirBajaPosible($equipo);

        $this->view('catalogos/form', [
            'titulo'     => 'Dar de baja ' . $equipo['codigo'],
            'def'        => ['ruta' => "/equipos/$id", 'titulo' => 'la ficha', 'singular' => 'equipo', 'nuevo' => ''],
            'id'         => $id,
            'accion'     => "/equipos/$id/baja",
            'encabezado' => "Dar de baja {$equipo['codigo']}",
            'intro'      => "«{$equipo['nombre']}» saldrá del inventario vigente: ya no se podrá entregar, mover ni editar. "
                          . 'Su historial se conserva y el administrador puede revertir la baja si fue un error.',
            'boton'      => 'Dar de baja',
            'campos'     => $this->camposBaja($equipo),
            'valores'    => Session::flash('form_old') ?? ['fecha_baja' => date('Y-m-d')],
            'errores'    => Session::flash('form_errores') ?? [],
            'csrf'       => Session::csrfToken(),
            'scripts'    => ['panel.js'],
        ], 'panel');
    }

    /** POST /equipos/{id}/baja */
    public function darDeBaja(int $id): void
    {
        $this->requireCsrf();
        $modelo = new Equipo();
        $equipo = $this->cargar($id);
        $this->exigirBajaPosible($equipo);

        [$datos, $errores] = Validador::validar($this->camposBaja($equipo), $_POST);
        if ($errores) {
            $this->volverAlFormulario("/equipos/$id/baja", $datos, $errores);
        }

        try {
            $modelo->transaccion(function () use ($modelo, $id, $equipo, $datos) {
                $modelo->darDeBaja($id, $datos['fecha_baja'], $datos['motivo_baja']);
                (new Auditoria())->registrar(Auth::user()['id'], 'equipos', $id, 'cerrar', [
                    'baja'    => $datos,
                    'cambios' => ['estado_inventario' => [$equipo['estado_inventario'], 'de_baja']],
                ]);
            });
        } catch (PDOException $e) {
            error_log('[equipos.baja] ' . $e->getMessage());
            Session::flash('error', 'No pudimos registrar la baja. Inténtalo de nuevo en unos minutos.');
            $this->volverAlFormulario("/equipos/$id/baja", $datos, []);
        }

        Session::flash('ok', "Se dio de baja {$equipo['codigo']}.");
        $this->redirect("/equipos/$id");
    }

    /** POST /equipos/{id}/revertir-baja (solo administrador) */
    public function revertirBaja(int $id): void
    {
        $this->requireCsrf();
        $modelo = new Equipo();
        $equipo = $this->cargar($id);

        if ($equipo['estado_inventario'] !== 'de_baja') {
            Session::flash('error', "{$equipo['codigo']} no está dado de baja.");
            $this->redirect("/equipos/$id");
        }

        try {
            $modelo->transaccion(function () use ($modelo, $id, $equipo) {
                $modelo->revertirBaja($id);
                (new Auditoria())->registrar(Auth::user()['id'], 'equipos', $id, 'anular', [
                    'baja_revertida' => ['fecha_baja' => $equipo['fecha_baja'], 'motivo_baja' => $equipo['motivo_baja']],
                    'cambios'        => ['estado_inventario' => ['de_baja', 'en_bodega']],
                ]);
            });
        } catch (PDOException $e) {
            error_log('[equipos.revertir] ' . $e->getMessage());
            Session::flash('error', 'No pudimos revertir la baja. Inténtalo de nuevo en unos minutos.');
            $this->redirect("/equipos/$id");
        }

        Session::flash('ok', "Se revirtió la baja de {$equipo['codigo']}: vuelve al inventario en bodega.");
        $this->redirect("/equipos/$id");
    }

    /** GET /equipos/etiquetas?ids[]=1&ids[]=2 — hoja de etiquetas para imprimir. */
    public function etiquetas(): void
    {
        $ids = array_filter((array) ($_GET['ids'] ?? []), fn ($v) => is_string($v) && ctype_digit($v));
        $ids = array_slice(array_values($ids), 0, self::MAX_ETIQUETAS);

        if (!$ids) {
            Session::flash('error', 'Elige al menos un equipo para imprimir su etiqueta.');
            $this->redirect('/equipos');
        }

        $equipos = (new Equipo())->paraEtiquetas($ids);
        if (!$equipos) {
            $this->notFound();
        }

        $this->view('equipos/etiquetas', [
            'titulo'  => count($equipos) === 1 ? 'Etiqueta ' . $equipos[0]['codigo'] : 'Etiquetas de equipos',
            'equipos' => $equipos,
            'volver'  => count($equipos) === 1 ? '/equipos/' . $equipos[0]['id'] : '/equipos',
        ], 'impresion');
    }

    // ------------------------------------------------------------------

    /** Lee y limpia los filtros del listado. */
    private function filtros(): array
    {
        $id = fn (string $clave) => isset($_GET[$clave]) && is_string($_GET[$clave]) && ctype_digit($_GET[$clave])
            ? (int) $_GET[$clave] : null;

        return [
            'q'          => mb_substr(trim((string) (is_string($_GET['q'] ?? null) ? $_GET['q'] : '')), 0, 100),
            'categoria'  => $id('categoria'),
            'ubicacion'  => $id('ubicacion'),
            'area'       => $id('area'),
            'operativo'  => array_key_exists($_GET['operativo'] ?? '', Equipo::OPERATIVOS) ? $_GET['operativo'] : '',
            'inventario' => array_key_exists($_GET['inventario'] ?? '', Equipo::INVENTARIO) ? $_GET['inventario'] : 'vigentes',
        ];
    }

    /** Los campos de todos los grupos en una sola lista, como los espera Validador. */
    private function camposPlanos(?array $equipo): array
    {
        return array_merge(...array_values($this->campos($equipo)));
    }

    /**
     * Campos del formulario agrupados por sección: [título => campos].
     * $equipo es null al crear: entonces se eligen la ubicación inicial
     * y el estado; al editar ambos se muestran como datos fijos (los cambian movimientos y mantenimientos).
     */
    private function campos(?array $equipo): array
    {
        $hoy        = date('Y-m-d');
        $categorias = (new Categoria())->opciones($equipo ? (int) $equipo['categoria_id'] : null);

        $codigo = ['nombre' => 'codigo', 'etiqueta' => 'Código interno', 'tipo' => 'text', 'requerido' => true,
                   'max' => 60, 'formato' => 'codigo', 'autocomplete' => 'off',
                   'ayuda' => 'Va impreso en la etiqueta. Letras, números y guiones.'];
        $categoria = ['nombre' => 'categoria_id', 'etiqueta' => 'Categoría', 'tipo' => 'select', 'requerido' => true,
                      'entero' => true, 'opciones' => $categorias];

        if (!$equipo) {
            // Código propuesto por categoría: el JS lo cambia al elegir otra mientras no se haya escrito a mano
            $modelo    = new Equipo();
            $prefijos  = (new Categoria())->prefijos();
            $propuestos = [];
            foreach (array_keys($categorias) as $catId) {
                $propuestos[$catId] = $modelo->siguienteCodigo($prefijos[$catId] ?? null);
            }
            $categoria['data'] = ['codigos' => json_encode($propuestos)];
            $codigo['ayuda']   = 'Se propone según la categoría (EQ-PREFIJO-número); puedes cambiarlo.';
        }

        $ubicacionEstado = $equipo
            ? [
                ['nombre' => 'ubicacion_fija', 'etiqueta' => 'Ubicación actual', 'tipo' => 'fijo',
                 'valor' => $equipo['ubicacion'] ? "{$equipo['ubicacion']} · {$equipo['sede']}" : 'Sin ubicación',
                 'ayuda' => 'Cambia al registrar un movimiento o una entrega.'],
                ['nombre' => 'estado_fijo', 'etiqueta' => 'Estado operativo', 'tipo' => 'fijo',
                 'valor' => Equipo::OPERATIVOS[$equipo['estado_operativo']],
                 'ayuda' => 'Cambia con los reportes de falla y los mantenimientos.'],
            ]
            : [
                ['nombre' => 'ubicacion_id', 'etiqueta' => 'Ubicación inicial', 'tipo' => 'select', 'requerido' => true,
                 'entero' => true, 'opciones' => (new Ubicacion())->opcionesConSede(),
                 'ayuda' => 'Se registra como ingreso al inventario. Después la ubicación cambia con un movimiento.'],
                ['nombre' => 'estado_operativo', 'etiqueta' => 'Estado operativo', 'tipo' => 'select', 'requerido' => true,
                 'opciones' => array_intersect_key(Equipo::OPERATIVOS, array_flip(self::OPERATIVOS_ALTA))],
            ];

        $grupos = [
            'Identificación' => [
                $categoria,
                $codigo,
                ['nombre' => 'nombre', 'etiqueta' => 'Nombre del equipo', 'tipo' => 'text', 'requerido' => true,
                 'min' => 3, 'max' => 150, 'ayuda' => 'Por ejemplo: Laptop, Proyector, Impresora láser.'],
                ['nombre' => 'marca', 'etiqueta' => 'Marca', 'tipo' => 'text', 'max' => 100],
                ['nombre' => 'modelo', 'etiqueta' => 'Modelo', 'tipo' => 'text', 'max' => 120],
                ['nombre' => 'numero_serie', 'etiqueta' => 'Número de serie', 'tipo' => 'text', 'max' => 120],
                ['nombre' => 'codigo_patrimonial', 'etiqueta' => 'Código patrimonial', 'tipo' => 'text', 'max' => 80,
                 'ayuda' => 'El que asigna la institución, si ya lo tiene.'],
            ],
            'Ubicación y estado' => array_merge($ubicacionEstado, [
                ['nombre' => 'area_propietaria_id', 'etiqueta' => 'Área propietaria', 'tipo' => 'select', 'entero' => true,
                 'opciones' => (new Area())->opciones($equipo ? ((int) $equipo['area_propietaria_id'] ?: null) : null),
                 'vacia' => 'Sin área'],
            ]),
            'Adquisición y garantía' => [
                ['nombre' => 'origen', 'etiqueta' => 'Origen', 'tipo' => 'select', 'opciones' => Formato::ORIGENES,
                 'vacia' => 'No especificado'],
                ['nombre' => 'fecha_adquisicion', 'etiqueta' => 'Fecha de adquisición', 'tipo' => 'fecha', 'max_valor' => $hoy],
                ['nombre' => 'valor_adquisicion', 'etiqueta' => 'Valor de adquisición (USD)', 'tipo' => 'numero',
                 'max_valor' => 9999999999],
                ['nombre' => 'proveedor', 'etiqueta' => 'Proveedor', 'tipo' => 'text', 'max' => 190],
                ['nombre' => 'fecha_fin_garantia', 'etiqueta' => 'Garantía hasta', 'tipo' => 'fecha'],
            ],
            'Observaciones' => [
                ['nombre' => 'observaciones', 'etiqueta' => 'Observaciones', 'tipo' => 'textarea', 'max' => 1000],
            ],
        ];

        return $grupos;
    }

    /** Reglas que cruzan campos o consultan la base. */
    private function validarExtra(Equipo $modelo, array $datos, ?int $id): array
    {
        $errores = [];
        $mensajes = [
            'codigo'             => 'Otro equipo ya tiene ese código.',
            'codigo_patrimonial' => 'Otro equipo ya tiene ese código patrimonial.',
            'numero_serie'       => 'Otro equipo ya tiene ese número de serie.',
        ];
        foreach ($mensajes as $campo => $mensaje) {
            if ($modelo->existe($campo, $datos[$campo] ?? null, $id)) {
                $errores[$campo] = $mensaje;
            }
        }
        if (($datos['fecha_adquisicion'] ?? null) && ($datos['fecha_fin_garantia'] ?? null)
            && $datos['fecha_fin_garantia'] < $datos['fecha_adquisicion']) {
            $errores['fecha_fin_garantia'] = 'La garantía no puede terminar antes de la fecha de adquisición.';
        }
        return $errores;
    }

    private function camposBaja(array $equipo): array
    {
        return [
            ['nombre' => 'fecha_baja', 'etiqueta' => 'Fecha de baja', 'tipo' => 'fecha', 'requerido' => true,
             'max_valor' => date('Y-m-d'), 'min_valor' => $equipo['fecha_adquisicion'] ?: null],
            ['nombre' => 'motivo_baja', 'etiqueta' => 'Motivo', 'tipo' => 'textarea', 'requerido' => true,
             'min' => 10, 'max' => 255,
             'ayuda' => 'Por ejemplo: daño irreparable, obsolescencia, pérdida o robo con denuncia.'],
        ];
    }

    /** Formulario de alta ($equipo null) o edición. */
    private function formulario(?array $equipo, array $valores): void
    {
        $id = $equipo ? (int) $equipo['id'] : null;

        $this->view('equipos/form', [
            'titulo'  => $equipo ? 'Editar ' . $equipo['codigo'] : 'Nuevo equipo',
            'id'      => $id,
            'equipo'  => $equipo,
            'grupos'  => $this->campos($equipo),
            'valores' => Session::flash('form_old') ?? $valores,
            'errores' => Session::flash('form_errores') ?? [],
            'csrf'    => Session::csrfToken(),
            'estilos' => ['equipos.css'],
            'scripts' => ['panel.js', 'equipos.js'],
        ], 'panel');
    }

    /** Equipo o 404. */
    private function cargar(int $id): array
    {
        return (new Equipo())->porId($id) ?? $this->notFound();
    }

    /** Un equipo de baja es de solo lectura. */
    private function exigirVigente(array $equipo): void
    {
        if ($equipo['estado_inventario'] === 'de_baja') {
            Session::flash('error', "{$equipo['codigo']} está dado de baja: sus datos ya no se pueden cambiar.");
            $this->redirect("/equipos/{$equipo['id']}");
        }
    }

    /** La baja exige que no esté entregado a nadie ni en un mantenimiento en curso. */
    private function exigirBajaPosible(array $equipo): void
    {
        $this->exigirVigente($equipo);

        $motivo = match (true) {
            $equipo['asignacion_id'] !== null =>
                "No se puede dar de baja {$equipo['codigo']}: está entregado a {$equipo['responsable']}. Primero registra la devolución.",
            (new Equipo())->tieneMantenimientoEnProceso((int) $equipo['id']) =>
                "No se puede dar de baja {$equipo['codigo']}: tiene un mantenimiento en proceso. Primero debe finalizarse o cancelarse.",
            default => null,
        };
        if ($motivo) {
            Session::flash('error', $motivo);
            $this->redirect("/equipos/{$equipo['id']}");
        }
    }

    private function volverAlFormulario(string $ruta, array $datos, array $errores): never
    {
        Session::flash('form_old', $datos);
        if ($errores) {
            Session::flash('form_errores', $errores);
        }
        $this->redirect($ruta);
    }

    private function esDuplicado(PDOException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062;
    }

    private static function puede(array $roles): bool
    {
        return in_array(Auth::rol(), $roles, true);
    }
}
