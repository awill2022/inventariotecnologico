<?php
/**
 * Movimientos: historial general de traslados y registro de un traslado sin entrega.
 *
 * Reglas (Sprint 4):
 * - Se traslada cualquier equipo vigente; si está entregado, sigue a cargo de la misma persona.
 * - estado_inventario según el destino: bodega → "En bodega"; otro lugar → "En uso".
 *   Con entrega abierta sigue "En uso".
 * - Un traslado no puede tener fecha anterior al último evento del equipo.
 *
 * Ver y registrar: administrador e inventario (el técnico no).
 */
final class MovimientoController extends Controller
{
    public const VER       = ['administrador', 'inventario'];
    public const GESTIONAR = ['administrador', 'inventario'];

    private const POR_PAGINA = 20;

    /** Motivos frecuentes que se sugieren al escribir. */
    private const MOTIVOS = [
        'Cambio de aula', 'Reorganización del laboratorio', 'Cambio de oficina',
        'Envío a revisión técnica', 'Retorno a bodega', 'Uso en evento institucional',
    ];

    /** GET /movimientos */
    public function index(): void
    {
        $id    = fn (string $k) => is_string($_GET[$k] ?? null) && ctype_digit($_GET[$k]) ? (int) $_GET[$k] : null;
        $fecha = function (string $k): ?string {
            $v = $_GET[$k] ?? null;
            $d = is_string($v) ? DateTimeImmutable::createFromFormat('!Y-m-d', $v) : false;
            return $d && $d->format('Y-m-d') === $v ? $v : null;
        };

        $filtros = [
            'q'         => mb_substr(trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : ''), 0, 100),
            'equipo'    => $id('equipo'),
            'ubicacion' => $id('ubicacion'),
            'desde'     => $fecha('desde'),
            'hasta'     => $fecha('hasta'),
        ];
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $equipo = $filtros['equipo'] ? (new Equipo())->porId($filtros['equipo']) : null;
        if ($filtros['equipo'] && !$equipo) {
            $filtros['equipo'] = null;
        }

        $this->view('movimientos/lista', [
            'titulo'         => 'Movimientos',
            'filtros'        => $filtros,
            'equipo'         => $equipo,
            'resultado'      => (new Movimiento())->listar($filtros, $pagina, self::POR_PAGINA),
            'ubicaciones'    => (new Ubicacion())->opcionesConSede($filtros['ubicacion']),
            'puedeGestionar' => self::puede(self::GESTIONAR),
            'estilos'        => ['equipos.css', 'asignaciones.css'],
        ], 'panel');
    }

    /** GET /movimientos/nuevo?equipo=ID */
    public function nuevo(): void
    {
        $equipoId = is_string($_GET['equipo'] ?? null) && ctype_digit($_GET['equipo']) ? (int) $_GET['equipo'] : null;
        $aviso    = null;

        if ($equipoId) {
            $equipo = (new Equipo())->porId($equipoId) ?? $this->notFound();
            if ($equipo['estado_inventario'] === 'de_baja') {
                Session::flash('error', "{$equipo['codigo']} está dado de baja: ya no se puede trasladar.");
                $this->redirect("/equipos/$equipoId");
            }
            $aviso = "{$equipo['codigo']} está en " . ($equipo['ubicacion'] ?? 'una ubicación sin registrar')
                   . ($equipo['responsable'] ? " y entregado a {$equipo['responsable']}: seguirá a su cargo en el nuevo lugar." : '.');
        }

        $volver = $this->volver($equipoId ? "/equipos/$equipoId" : '/movimientos');
        $this->view('catalogos/form', [
            'titulo'     => 'Registrar traslado',
            'def'        => ['ruta' => $volver, 'titulo' => str_starts_with($volver, '/equipos/') ? 'la ficha del equipo' : 'movimientos',
                             'singular' => '', 'nuevo' => ''],
            'id'         => null,
            'accion'     => '/movimientos',
            'encabezado' => 'Registrar traslado',
            'intro'      => 'Cambia la ubicación de un equipo sin entregarlo a nadie. Si llega a una bodega queda «En bodega»; a otro lugar, «En uso».',
            'aviso'      => $aviso,
            'boton'      => 'Registrar traslado',
            'campos'     => $this->campos(),
            'ocultos'    => ['volver' => $volver],
            'valores'    => Session::flash('form_old') ?? ['equipo_id' => $equipoId, 'fecha' => date('Y-m-d H:i')],
            'errores'    => Session::flash('form_errores') ?? [],
            'csrf'       => Session::csrfToken(),
            'scripts'    => ['panel.js'],
        ], 'panel');
    }

    /** POST /movimientos */
    public function guardar(): void
    {
        $this->requireCsrf();
        $volver = $this->volver('/movimientos');
        $form   = '/movimientos/nuevo?volver=' . rawurlencode($volver);
        $equipos = new Equipo();

        [$datos, $errores] = Validador::validar($this->campos(), $_POST);
        $equipo = $datos['equipo_id'] ? $equipos->porId($datos['equipo_id']) : null;
        if ($equipo && $datos['destino_id'] && (int) $equipo['ubicacion_actual_id'] === $datos['destino_id']) {
            $errores['destino_id'] = "{$equipo['codigo']} ya está en esa ubicación. Elige otro destino.";
        }
        if ($equipo && $datos['fecha'] && !isset($errores['fecha'])
            && ($ultima = $equipos->ultimaFecha($equipo['id'])) && $datos['fecha'] < $ultima) {
            $errores['fecha'] = 'El equipo tiene un registro del ' . Formato::fechaHora($ultima) . ': el traslado no puede ser anterior.';
        }
        if ($errores) {
            $this->volverAlFormulario($form, $datos, $errores);
        }

        $usuarioId = Auth::user()['id'];
        try {
            $equipos->transaccion(function () use ($equipos, $datos, $usuarioId) {
                $actual = $equipos->bloquear($datos['equipo_id']);
                if ($actual['estado_inventario'] === 'de_baja') {
                    throw new DomainException("{$actual['codigo']} está dado de baja: ya no se puede trasladar.");
                }
                if ((int) $actual['ubicacion_actual_id'] === $datos['destino_id']) {
                    throw new DomainException("{$actual['codigo']} ya está en esa ubicación.");
                }

                $origen = $actual['ubicacion_actual_id'] ? (int) $actual['ubicacion_actual_id'] : null;
                $movId  = (new Movimiento())->registrar($datos['equipo_id'], $origen, $datos['destino_id'], $datos['motivo'],
                    $usuarioId, $datos['autorizado_por'], $datos['observaciones'], $datos['fecha']);

                $auditoria = new Auditoria();
                $auditoria->registrar($usuarioId, 'movimientos', $movId, 'crear', ['datos' => $datos, 'origen' => $origen]);

                // Entregado: sigue en uso. Libre: según el tipo del destino.
                $inventario = $actual['asignacion_id'] !== null || !(new Ubicacion())->esBodega($datos['destino_id'])
                    ? 'activo' : 'en_bodega';
                if ($inventario !== $actual['estado_inventario']) {
                    $equipos->fijarInventario($datos['equipo_id'], $inventario);
                    $auditoria->registrar($usuarioId, 'equipos', $datos['equipo_id'], 'editar', [
                        'cambios' => ['estado_inventario' => [$actual['estado_inventario'], $inventario]],
                    ]);
                }
            });
        } catch (DomainException $e) {
            Session::flash('error', $e->getMessage());
            $this->volverAlFormulario($form, $datos, []);
        } catch (PDOException $e) {
            error_log('[movimientos.crear] ' . $e->getMessage());
            Session::flash('error', 'No pudimos registrar el traslado. Inténtalo de nuevo en unos minutos.');
            $this->volverAlFormulario($form, $datos, []);
        }

        Session::flash('ok', "Se registró el traslado de {$equipo['codigo']}.");
        $this->redirect($volver === '/movimientos' ? "/equipos/{$equipo['id']}" : $volver);
    }

    // ------------------------------------------------------------------

    private function campos(): array
    {
        return [
            ['nombre' => 'equipo_id', 'etiqueta' => 'Equipo', 'tipo' => 'select', 'requerido' => true, 'entero' => true,
             'opciones' => (new Equipo())->opciones(false),
             'ayuda' => 'Junto a cada equipo se ve dónde está ahora.'],
            ['nombre' => 'destino_id', 'etiqueta' => 'Nueva ubicación', 'tipo' => 'select', 'requerido' => true, 'entero' => true,
             'opciones' => (new Ubicacion())->opcionesConSede()],
            ['nombre' => 'fecha', 'etiqueta' => 'Fecha y hora del traslado', 'tipo' => 'fechahora', 'requerido' => true,
             'max_valor' => date('Y-m-d H:i')],
            ['nombre' => 'motivo', 'etiqueta' => 'Motivo', 'tipo' => 'text', 'requerido' => true, 'min' => 3, 'max' => 255,
             'sugerencias' => self::MOTIVOS],
            ['nombre' => 'autorizado_por', 'etiqueta' => 'Autorizado por', 'tipo' => 'select', 'entero' => true,
             'opciones' => (new Persona())->opcionesActivas(), 'vacia' => 'Nadie en particular'],
            ['nombre' => 'observaciones', 'etiqueta' => 'Observaciones', 'tipo' => 'textarea', 'max' => 1000],
        ];
    }

    /** Ruta de regreso enviada por el formulario o la URL; solo rutas internas conocidas. */
    private function volver(string $porDefecto): string
    {
        $v = $_POST['volver'] ?? $_GET['volver'] ?? '';
        return is_string($v) && preg_match('#^/equipos/\d+$|^/movimientos$#', $v) ? $v : $porDefecto;
    }

    private function volverAlFormulario(string $ruta, array $datos, array $errores): never
    {
        Session::flash('form_old', $datos);
        if ($errores) {
            Session::flash('form_errores', $errores);
        }
        $this->redirect($ruta);
    }

    private static function puede(array $roles): bool
    {
        return in_array(Auth::rol(), $roles, true);
    }
}
