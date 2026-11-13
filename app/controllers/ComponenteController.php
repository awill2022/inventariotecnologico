<?php
/**
 * Componentes de un equipo: instalar, corregir sus datos y retirar.
 * Un componente retirado no se borra: queda en el historial del equipo.
 * Si se llega desde un mantenimiento en proceso (?mantenimiento=ID), la pieza queda enlazada
 * a ese trabajo y al guardar se vuelve a su ficha.
 * Roles: EquipoController::COMPONENTES (administrador y técnico).
 */
final class ComponenteController extends Controller
{
    /** Sugerencias para el tipo, además de los ya usados. */
    private const TIPOS_COMUNES = ['Memoria RAM', 'Almacenamiento', 'Procesador', 'Tarjeta de video', 'Fuente de poder',
                                   'Batería', 'Cargador', 'Tarjeta de red', 'Teclado', 'Mouse', 'Lámpara', 'Tóner'];

    /** GET /equipos/{id}/componentes/nuevo */
    public function nuevo(int $id): void
    {
        $equipo = $this->cargarEquipo($id);
        $this->formulario($equipo, null, ['fecha_instalacion' => date('Y-m-d')], $this->mantenimiento($id));
    }

    /** POST /equipos/{id}/componentes */
    public function guardar(int $id): void
    {
        $this->requireCsrf();
        $equipo = $this->cargarEquipo($id);
        $mant   = $this->mantenimiento($id);
        $mantId = $mant ? (int) $mant['id'] : null;
        $modelo = new Componente();
        $form   = "/equipos/$id/componentes/nuevo" . ($mant ? "?mantenimiento=$mantId" : '');

        [$datos, $errores] = Validador::validar($this->campos($equipo), $_POST);
        if ($errores) {
            $this->volver($form, $datos, $errores);
        }

        try {
            $modelo->transaccion(function () use ($modelo, $id, $datos, $mantId) {
                $cid = $modelo->crear($id, $datos, $mantId);
                (new Auditoria())->registrar(Auth::user()['id'], 'componentes', $cid, 'crear', [
                    'equipo_id' => $id, 'mantenimiento_id' => $mantId, 'datos' => $datos,
                ]);
            });
        } catch (PDOException $e) {
            error_log('[componentes.crear] ' . $e->getMessage());
            Session::flash('error', 'No pudimos guardar. Inténtalo de nuevo en unos minutos.');
            $this->volver($form, $datos, []);
        }

        Session::flash('ok', "Se registró «{$datos['tipo']}» como instalado en {$equipo['codigo']}"
            . ($mant ? ' durante este mantenimiento.' : '.'));
        $this->redirect($mant ? "/mantenimientos/$mantId" : "/equipos/$id");
    }

    /** GET /equipos/{id}/componentes/{componente_id}/editar */
    public function editar(int $id, int $componenteId): void
    {
        $equipo     = $this->cargarEquipo($id);
        $componente = $this->cargarInstalado($id, $componenteId);
        $this->formulario($equipo, $componente, $componente);
    }

    /** POST /equipos/{id}/componentes/{componente_id} */
    public function actualizar(int $id, int $componenteId): void
    {
        $this->requireCsrf();
        $equipo     = $this->cargarEquipo($id);
        $componente = $this->cargarInstalado($id, $componenteId);
        $modelo     = new Componente();
        $ruta       = "/equipos/$id/componentes/$componenteId/editar";

        [$datos, $errores] = Validador::validar($this->campos($equipo), $_POST);
        if ($errores) {
            $this->volver($ruta, $datos, $errores);
        }

        $cambios = [];
        foreach ($datos as $campo => $valor) {
            if ((string) ($componente[$campo] ?? '') !== (string) ($valor ?? '')) {
                $cambios[$campo] = [$componente[$campo] ?? null, $valor];
            }
        }
        if ($cambios) {
            try {
                $modelo->transaccion(function () use ($modelo, $componenteId, $datos, $cambios, $id) {
                    $modelo->actualizar($componenteId, $datos);
                    (new Auditoria())->registrar(Auth::user()['id'], 'componentes', $componenteId, 'editar', [
                        'equipo_id' => $id, 'cambios' => $cambios,
                    ]);
                });
            } catch (PDOException $e) {
                error_log('[componentes.actualizar] ' . $e->getMessage());
                Session::flash('error', 'No pudimos guardar. Inténtalo de nuevo en unos minutos.');
                $this->volver($ruta, $datos, []);
            }
        }

        Session::flash('ok', $cambios ? "Se guardaron los cambios de «{$datos['tipo']}»." : 'No había cambios que guardar.');
        $this->redirect("/equipos/$id");
    }

    /** GET /equipos/{id}/componentes/{componente_id}/retirar */
    public function retiro(int $id, int $componenteId): void
    {
        $equipo     = $this->cargarEquipo($id);
        $componente = $this->cargarInstalado($id, $componenteId);
        $mant       = $this->mantenimiento($id);

        $this->view('catalogos/form', [
            'titulo'     => 'Retirar componente',
            'def'        => $this->defVolver($id, $mant),
            'ocultos'    => $mant ? ['mantenimiento' => $mant['id']] : [],
            'aviso'      => $mant ? 'El retiro queda enlazado al mantenimiento ' . Mantenimiento::numero((int) $mant['id']) . '.' : null,
            'id'         => $componenteId,
            'accion'     => "/equipos/$id/componentes/$componenteId/retirar",
            'encabezado' => "Retirar «{$componente['tipo']}» de {$equipo['codigo']}",
            'intro'      => 'El componente deja de figurar como instalado y pasa al historial del equipo.',
            'boton'      => 'Retirar componente',
            'campos'     => $this->camposRetiro($componente),
            'valores'    => Session::flash('form_old') ?? ['fecha_retiro' => date('Y-m-d'), 'observaciones' => $componente['observaciones']],
            'errores'    => Session::flash('form_errores') ?? [],
            'csrf'       => Session::csrfToken(),
            'scripts'    => ['panel.js'],
        ], 'panel');
    }

    /** POST /equipos/{id}/componentes/{componente_id}/retirar */
    public function retirar(int $id, int $componenteId): void
    {
        $this->requireCsrf();
        $this->cargarEquipo($id);
        $componente = $this->cargarInstalado($id, $componenteId);
        $mant       = $this->mantenimiento($id);
        $mantId     = $mant ? (int) $mant['id'] : null;
        $modelo     = new Componente();
        $ruta       = "/equipos/$id/componentes/$componenteId/retirar" . ($mant ? "?mantenimiento=$mantId" : '');

        [$datos, $errores] = Validador::validar($this->camposRetiro($componente), $_POST);
        if ($errores) {
            $this->volver($ruta, $datos, $errores);
        }

        try {
            $modelo->transaccion(function () use ($modelo, $componenteId, $datos, $id, $mantId) {
                $modelo->retirar($componenteId, $datos['fecha_retiro'], $datos['observaciones'], $mantId);
                (new Auditoria())->registrar(Auth::user()['id'], 'componentes', $componenteId, 'cerrar', [
                    'equipo_id' => $id, 'mantenimiento_id' => $mantId, 'retiro' => $datos,
                ]);
            });
        } catch (PDOException $e) {
            error_log('[componentes.retirar] ' . $e->getMessage());
            Session::flash('error', 'No pudimos registrar el retiro. Inténtalo de nuevo en unos minutos.');
            $this->volver($ruta, $datos, []);
        }

        Session::flash('ok', "Se retiró «{$componente['tipo']}». Queda en el historial del equipo.");
        $this->redirect($mant ? "/mantenimientos/$mantId" : "/equipos/$id");
    }

    // ------------------------------------------------------------------

    private function campos(array $equipo): array
    {
        $tipos = array_values(array_unique(array_merge(self::TIPOS_COMUNES, (new Componente())->tiposConocidos())));
        sort($tipos);

        return [
            ['nombre' => 'tipo', 'etiqueta' => 'Tipo de componente', 'tipo' => 'text', 'requerido' => true,
             'min' => 2, 'max' => 100, 'sugerencias' => $tipos, 'ayuda' => 'Por ejemplo: Memoria RAM, Almacenamiento, Batería.'],
            ['nombre' => 'capacidad', 'etiqueta' => 'Capacidad', 'tipo' => 'text', 'max' => 100,
             'ayuda' => 'Por ejemplo: 8 GB, 512 GB, 65 W.'],
            ['nombre' => 'marca', 'etiqueta' => 'Marca', 'tipo' => 'text', 'max' => 100],
            ['nombre' => 'modelo', 'etiqueta' => 'Modelo', 'tipo' => 'text', 'max' => 120],
            ['nombre' => 'numero_serie', 'etiqueta' => 'Número de serie', 'tipo' => 'text', 'max' => 120],
            ['nombre' => 'fecha_instalacion', 'etiqueta' => 'Fecha de instalación', 'tipo' => 'fecha',
             'max_valor' => date('Y-m-d'), 'min_valor' => $equipo['fecha_adquisicion'] ?: null],
            ['nombre' => 'observaciones', 'etiqueta' => 'Observaciones', 'tipo' => 'textarea', 'max' => 500],
        ];
    }

    private function camposRetiro(array $componente): array
    {
        return [
            ['nombre' => 'fecha_retiro', 'etiqueta' => 'Fecha de retiro', 'tipo' => 'fecha', 'requerido' => true,
             'max_valor' => date('Y-m-d'), 'min_valor' => $componente['fecha_instalacion'] ?: null],
            ['nombre' => 'observaciones', 'etiqueta' => 'Observaciones', 'tipo' => 'textarea', 'max' => 500,
             'ayuda' => 'Por qué se retiró y a dónde fue la pieza (por ejemplo: dañada, se reemplazó por una de mayor capacidad).'],
        ];
    }

    private function formulario(array $equipo, ?array $componente, array $valores, ?array $mant = null): void
    {
        $id  = (int) $equipo['id'];
        $cid = $componente ? (int) $componente['id'] : null;

        $this->view('catalogos/form', [
            'titulo'     => $cid ? 'Editar componente' : 'Instalar componente',
            'def'        => $this->defVolver($id, $mant),
            'ocultos'    => $mant ? ['mantenimiento' => $mant['id']] : [],
            'aviso'      => $mant ? 'La instalación queda enlazada al mantenimiento ' . Mantenimiento::numero((int) $mant['id']) . '.' : null,
            'id'         => $cid,
            'accion'     => $cid ? "/equipos/$id/componentes/$cid" : "/equipos/$id/componentes",
            'encabezado' => $cid ? "Editar «{$componente['tipo']}» de {$equipo['codigo']}" : "Instalar componente en {$equipo['codigo']}",
            'intro'      => $cid ? 'Corrige los datos del componente. Si se sacó del equipo, usa «Retirar».'
                                 : "{$equipo['nombre']}. Registra una pieza instalada en el equipo.",
            'boton'      => $cid ? 'Guardar cambios' : 'Registrar componente',
            'campos'     => $this->campos($equipo),
            'valores'    => Session::flash('form_old') ?? $valores,
            'errores'    => Session::flash('form_errores') ?? [],
            'csrf'       => Session::csrfToken(),
            'scripts'    => ['panel.js'],
        ], 'panel');
    }

    /**
     * Mantenimiento en proceso indicado en ?mantenimiento= o en el formulario, o null si no se indicó.
     * Si es de otro equipo da 404; si ya no está en proceso vuelve a su ficha con el aviso.
     */
    private function mantenimiento(int $equipoId): ?array
    {
        $v = $_POST['mantenimiento'] ?? $_GET['mantenimiento'] ?? null;
        if (!is_string($v) || !ctype_digit($v)) {
            return null;
        }
        $m = (new Mantenimiento())->porId((int) $v);
        if (!$m || (int) $m['equipo_id'] !== $equipoId) {
            $this->notFound();
        }
        if ($m['estado'] !== 'en_proceso') {
            Session::flash('error', 'El mantenimiento ' . Mantenimiento::numero((int) $m['id'])
                . ' ya no está en proceso: sus componentes no se pueden cambiar.');
            $this->redirect("/mantenimientos/{$m['id']}");
        }
        return $m;
    }

    /** A dónde vuelven «Volver» y «Cancelar» del formulario. */
    private function defVolver(int $equipoId, ?array $mant): array
    {
        return $mant
            ? ['ruta' => "/mantenimientos/{$mant['id']}", 'titulo' => 'el mantenimiento', 'singular' => 'componente', 'nuevo' => '']
            : ['ruta' => "/equipos/$equipoId", 'titulo' => 'la ficha', 'singular' => 'componente', 'nuevo' => ''];
    }

    private function cargarEquipo(int $id): array
    {
        $equipo = (new Equipo())->porId($id) ?? $this->notFound();
        if ($equipo['estado_inventario'] === 'de_baja') {
            Session::flash('error', "{$equipo['codigo']} está dado de baja: sus componentes ya no se pueden cambiar.");
            $this->redirect("/equipos/$id");
        }
        return $equipo;
    }

    /** Componente instalado de este equipo, o 404 (uno retirado ya no se edita). */
    private function cargarInstalado(int $equipoId, int $componenteId): array
    {
        $componente = (new Componente())->deEquipoPorId($equipoId, $componenteId);
        if (!$componente || $componente['fecha_retiro'] !== null) {
            $this->notFound();
        }
        return $componente;
    }

    private function volver(string $ruta, array $datos, array $errores): never
    {
        Session::flash('form_old', $datos);
        if ($errores) {
            Session::flash('form_errores', $errores);
        }
        $this->redirect($ruta);
    }
}
