<?php
/**
 * Controlador base de los catálogos. Cada catálogo (categorías, sedes, áreas,
 * ubicaciones) solo describe sus textos, columnas y campos; aquí vive el flujo
 * común: listar, crear, editar y activar/desactivar, con CSRF y auditoría.
 *
 * Ver: todos los roles del panel. Escribir: solo administrador (se define en routes.php).
 */
abstract class CatalogoController extends Controller
{
    private const POR_PAGINA = 10;
    private const ESTADOS    = ['todos', 'activos', 'inactivos'];

    /** Modelo del catálogo. */
    abstract protected function modelo(): Catalogo;

    /**
     * Textos y columnas del catálogo:
     *   ruta, entidad (tabla, para auditoría), titulo, descripcion, singular,
     *   articulo (opcional, 'la' por defecto), nuevo (texto del botón),
     *   columnas [clave, titulo, mapa?, formato? (método de Formato), numero?], vacio.
     *   Opcionales: buscar (placeholder del buscador), nota_creado (se agrega al aviso del alta),
     *   aviso_desactivar (texto de la confirmación), acciones [texto, ruta con {id}, icono]
     *   (enlaces extra por fila para quien puede editar).
     */
    abstract protected function def(): array;

    /** Campos del formulario (formato de Validador). $id es null al crear. */
    abstract protected function campos(?int $id): array;

    /** Reglas propias del catálogo (nombres repetidos, ciclos, ...). Devuelve errores por campo. */
    protected function validarExtra(array $datos, ?int $id): array
    {
        return [];
    }

    /** Roles que pueden crear, editar y activar/desactivar (deben coincidir con routes.php). */
    protected function rolesEditar(): array
    {
        return ['administrador'];
    }

    /** Nombre visible del registro en los mensajes. */
    protected function nombreDe(array $fila): string
    {
        return (string) ($fila['nombre'] ?? '');
    }

    /** Datos sin secretos (claves): lo que se audita y lo que vuelve al formulario por la sesión. */
    protected function sinSecretos(array $datos): array
    {
        return $datos;
    }

    /**
     * Motivo por el que no se puede activar o desactivar el registro, o null si se puede.
     * Por defecto impide desactivar si hay registros vigentes que dependen de él.
     */
    protected function bloqueoCambioEstado(int $id, array $fila, bool $activar): ?string
    {
        if ($activar || !($dependencias = $this->modelo()->dependencias($id))) {
            return null;
        }
        return "No se puede desactivar «{$this->nombreDe($fila)}» porque tiene "
            . implode(', ', $dependencias) . '. Primero reasigna o desactiva esos registros.';
    }

    /** GET /{catalogo} */
    public function index(): void
    {
        $def    = $this->def();
        $q      = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
        $estado = in_array($_GET['estado'] ?? '', self::ESTADOS, true) ? $_GET['estado'] : 'todos';
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));

        $resultado = $this->modelo()->listar($q, $estado, $pagina, self::POR_PAGINA);

        $this->view('catalogos/lista', [
            'titulo'       => $def['titulo'],
            'def'          => $def,
            'q'            => $q,
            'estado'       => $estado,
            'resultado'    => $resultado,
            'puedeEditar'  => in_array(Auth::rol(), $this->rolesEditar(), true),
            'csrf'         => Session::csrfToken(),
            'scripts'      => ['panel.js'],
        ], 'panel');
    }

    /** GET /{catalogo}/nueva */
    public function nueva(): void
    {
        $this->formulario(null, []);
    }

    /** POST /{catalogo} */
    public function guardar(): void
    {
        $this->requireCsrf();
        $def = $this->def();

        [$datos, $errores] = Validador::validar($this->campos(null), $_POST);
        $errores += $this->validarExtra($datos, null);

        if ($errores) {
            $this->volverAlFormulario($def['ruta'] . '/nueva', $datos, $errores);
        }

        try {
            // El registro y su auditoría se guardan juntos o no se guarda ninguno
            $modelo = $this->modelo();
            $modelo->transaccion(function () use ($modelo, $def, $datos) {
                $id = $modelo->crear($datos);
                (new Auditoria())->registrar(Auth::user()['id'], $def['entidad'], $id, 'crear', ['datos' => $this->sinSecretos($datos)]);
            });
        } catch (PDOException $e) {
            error_log("[{$def['entidad']}.crear] " . $e->getMessage());
            Session::flash('error', 'No pudimos guardar. Inténtalo de nuevo en unos minutos.');
            $this->volverAlFormulario($def['ruta'] . '/nueva', $datos, []);
        }

        $articulo = $def['articulo'] ?? 'la';
        Session::flash('ok', trim("Se creó $articulo {$def['singular']} «{$this->nombreDe($datos)}». " . ($def['nota_creado'] ?? '')));
        $this->redirect($def['ruta']);
    }

    /** GET /{catalogo}/{id}/editar */
    public function editar(int $id): void
    {
        $fila = $this->modelo()->porId($id) ?? $this->notFound();
        $this->formulario($id, $fila);
    }

    /** POST /{catalogo}/{id} */
    public function actualizar(int $id): void
    {
        $this->requireCsrf();
        $def    = $this->def();
        $modelo = $this->modelo();
        $fila   = $modelo->porId($id) ?? $this->notFound();

        [$datos, $errores] = Validador::validar($this->campos($id), $_POST);
        $errores += $this->validarExtra($datos, $id);

        if ($errores) {
            $this->volverAlFormulario("{$def['ruta']}/$id/editar", $datos, $errores);
        }

        // Solo se audita lo que cambió: [campo => [antes, después]]
        $cambios = [];
        foreach ($datos as $campo => $valor) {
            if ((string) ($fila[$campo] ?? '') !== (string) ($valor ?? '')) {
                $cambios[$campo] = [$fila[$campo] ?? null, $valor];
            }
        }

        if ($cambios) {
            try {
                $modelo->transaccion(function () use ($modelo, $def, $id, $datos, $cambios) {
                    $modelo->actualizar($id, $datos);
                    (new Auditoria())->registrar(Auth::user()['id'], $def['entidad'], $id, 'editar', ['cambios' => $cambios]);
                });
            } catch (PDOException $e) {
                error_log("[{$def['entidad']}.actualizar] " . $e->getMessage());
                Session::flash('error', 'No pudimos guardar. Inténtalo de nuevo en unos minutos.');
                $this->volverAlFormulario("{$def['ruta']}/$id/editar", $datos, []);
            }
            Session::flash('ok', "Se guardaron los cambios de «{$this->nombreDe($datos)}».");
        } else {
            Session::flash('ok', "No había cambios que guardar en «{$this->nombreDe($datos + $fila)}».");
        }

        $this->redirect($def['ruta']);
    }

    /** POST /{catalogo}/{id}/estado — activa o desactiva (no se borra). */
    public function estado(int $id): void
    {
        $this->requireCsrf();
        $def    = $this->def();
        $modelo = $this->modelo();
        $fila   = $modelo->porId($id) ?? $this->notFound();
        $activar = !$fila['activo'];

        // Vuelve al mismo listado (búsqueda, filtro y página) desde donde se pulsó
        $volver  = (string) ($_POST['volver'] ?? '');
        $destino = $def['ruta'] . (preg_match('/^\?[\w=&%.+-]*$/', $volver) ? $volver : '');

        if ($bloqueo = $this->bloqueoCambioEstado($id, $fila, $activar)) {
            Session::flash('error', $bloqueo);
            $this->redirect($destino);
        }

        try {
            $modelo->transaccion(function () use ($modelo, $def, $id, $fila, $activar) {
                $modelo->cambiarEstado($id, $activar);
                (new Auditoria())->registrar(Auth::user()['id'], $def['entidad'], $id, 'editar', [
                    'cambios' => ['activo' => [(int) $fila['activo'], (int) $activar]],
                ]);
            });
        } catch (PDOException $e) {
            error_log("[{$def['entidad']}.estado] " . $e->getMessage());
            Session::flash('error', 'No pudimos cambiar el estado. Inténtalo de nuevo en unos minutos.');
            $this->redirect($destino);
        }

        Session::flash('ok', ($activar ? 'Se activó' : 'Se desactivó') . " «{$this->nombreDe($fila)}».");
        $this->redirect($destino);
    }

    /** Muestra el formulario de alta ($id null) o edición. */
    private function formulario(?int $id, array $fila): void
    {
        $def = $this->def();

        $this->view('catalogos/form', [
            'titulo'  => ($id ? 'Editar ' : 'Crear ') . $def['singular'],
            'def'     => $def,
            'id'      => $id,
            'campos'  => $this->campos($id),
            'valores' => Session::flash('form_old') ?? $fila,
            'errores' => Session::flash('form_errores') ?? [],
            'csrf'    => Session::csrfToken(),
            'scripts' => ['panel.js'],
        ], 'panel');
    }

    protected function volverAlFormulario(string $ruta, array $datos, array $errores): never
    {
        Session::flash('form_old', $this->sinSecretos($datos));
        if ($errores) {
            Session::flash('form_errores', $errores);
        }
        $this->redirect($ruta);
    }
}
