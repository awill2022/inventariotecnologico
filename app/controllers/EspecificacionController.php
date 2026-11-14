<?php
/**
 * Especificaciones de un equipo (pares atributo–valor), editadas todas juntas en una página.
 * Una fila con atributo y valor vacíos no se guarda (si ya existía, se quita).
 * Roles: EquipoController::ESPECIFICAR (administrador, inventario y técnico).
 */
final class EspecificacionController extends Controller
{
    private const FILAS_NUEVAS = 2;
    private const MAX_FILAS    = 40;

    /** GET /equipos/{id}/especificaciones */
    public function editar(int $id): void
    {
        $equipo = $this->cargar($id);
        $modelo = new Especificacion();

        $filas = Session::flash('form_old') ?? array_map(
            fn ($s) => ['id' => (string) $s['id'], 'atributo' => $s['atributo'], 'valor' => $s['valor']],
            $modelo->deEquipo($id)
        );
        for ($i = 0; $i < self::FILAS_NUEVAS; $i++) {
            $filas[] = ['id' => '', 'atributo' => '', 'valor' => ''];
        }

        $this->view('equipos/especificaciones', [
            'titulo'     => 'Especificaciones de ' . $equipo['codigo'],
            'e'          => $equipo,
            'filas'      => $filas,
            'errores'    => Session::flash('form_errores') ?? [],
            'sugerencias' => $modelo->atributosConocidos(),
            'csrf'       => Session::csrfToken(),
            'estilos'    => ['equipos.css'],
            'scripts'    => ['panel.js', 'especificaciones.js'],
        ], 'panel');
    }

    /** POST /equipos/{id}/especificaciones */
    public function guardar(int $id): void
    {
        $this->requireCsrf();
        $equipo = $this->cargar($id);
        $modelo = new Especificacion();

        $actuales = [];
        foreach ($modelo->deEquipo($id) as $s) {
            $actuales[(int) $s['id']] = $s;
        }

        [$filas, $errores] = $this->leerFilas($actuales);
        if ($errores) {
            // Se quitan las filas nuevas vacías del final (el formulario agrega otras); los índices
            // de los errores no cambian porque los errores solo están en filas con contenido
            while ($filas && end($filas) === ['id' => '', 'atributo' => '', 'valor' => '']) {
                array_pop($filas);
            }
            $this->volver($id, $filas, $errores);
        }

        // Qué cambia: [id => fila] para editar, [fila] para crear, [id] para quitar
        $editar = $crear = $quitar = [];
        foreach ($filas as $f) {
            $vacia = $f['atributo'] === '' && $f['valor'] === '';
            if ($f['id'] === '') {
                if (!$vacia) {
                    $crear[] = $f;
                }
                continue;
            }
            $actual = $actuales[(int) $f['id']];
            if ($vacia) {
                $quitar[] = (int) $f['id'];
            } elseif ($actual['atributo'] !== $f['atributo'] || $actual['valor'] !== $f['valor']) {
                $editar[(int) $f['id']] = $f;
            }
        }

        if (!$editar && !$crear && !$quitar) {
            Session::flash('ok', "No había cambios en las especificaciones de {$equipo['codigo']}.");
            $this->redirect("/equipos/$id");
        }

        try {
            $modelo->transaccion(function () use ($modelo, $id, $actuales, $editar, $crear, $quitar) {
                // Primero se quitan, para que un atributo quitado pueda volver a escribirse en otra fila
                foreach ($quitar as $sid) {
                    $modelo->eliminar($sid);
                }
                foreach ($editar as $sid => $f) {
                    $modelo->actualizar($sid, $f['atributo'], $f['valor']);
                }
                foreach ($crear as $f) {
                    $modelo->crear($id, $f['atributo'], $f['valor']);
                }

                $detalle = ['especificaciones' => array_filter([
                    'agregadas' => array_map(fn ($f) => [$f['atributo'] => $f['valor']], $crear),
                    'cambiadas' => array_map(fn ($sid, $f) => [
                        'antes' => [$actuales[$sid]['atributo'] => $actuales[$sid]['valor']],
                        'despues' => [$f['atributo'] => $f['valor']],
                    ], array_keys($editar), $editar),
                    'quitadas'  => array_map(fn ($sid) => [$actuales[$sid]['atributo'] => $actuales[$sid]['valor']], $quitar),
                ])];
                (new Auditoria())->registrar(Auth::user()['id'], 'equipos', $id, 'editar', $detalle);
            });
        } catch (PDOException $e) {
            error_log('[especificaciones.guardar] ' . $e->getMessage());
            $mensaje = ($e->errorInfo[1] ?? null) === 1062
                ? 'Hay un atributo repetido. Cada atributo puede aparecer una sola vez.'
                : 'No pudimos guardar. Inténtalo de nuevo en unos minutos.';
            Session::flash('error', $mensaje);
            $this->volver($id, $filas, []);
        }

        Session::flash('ok', "Se guardaron las especificaciones de {$equipo['codigo']}.");
        $this->redirect("/equipos/$id");
    }

    /**
     * Lee las filas del formulario y las valida.
     * @return array{0: array, 1: array} [filas limpias, errores "i.campo" => mensaje]
     */
    private function leerFilas(array $actuales): array
    {
        $entrada = is_array($_POST['filas'] ?? null) ? array_slice(array_values($_POST['filas']), 0, self::MAX_FILAS) : [];
        $filas   = [];
        $errores = [];
        $vistos  = [];

        foreach ($entrada as $i => $crudo) {
            $crudo = is_array($crudo) ? $crudo : [];
            $texto = fn (string $k) => is_string($crudo[$k] ?? null)
                ? trim(preg_replace('/\s+/u', ' ', mb_scrub($crudo[$k], 'UTF-8'))) : '';

            $sid = is_string($crudo['id'] ?? null) && ctype_digit($crudo['id']) && isset($actuales[(int) $crudo['id']])
                ? $crudo['id'] : '';   // un id ajeno a este equipo se trata como fila nueva
            $f = ['id' => $sid, 'atributo' => $texto('atributo'), 'valor' => $texto('valor')];
            $filas[] = $f;

            if ($f['atributo'] === '' && $f['valor'] === '') {
                continue;
            }
            if ($f['atributo'] === '') {
                $errores["$i.atributo"] = 'Escribe el atributo (por ejemplo: Memoria RAM).';
            } elseif (mb_strlen($f['atributo']) > Especificacion::MAX_ATRIBUTO) {
                $errores["$i.atributo"] = 'No puede pasar de ' . Especificacion::MAX_ATRIBUTO . ' caracteres.';
            } else {
                $clave = mb_strtolower($f['atributo']);
                if (isset($vistos[$clave])) {
                    $errores["$i.atributo"] = 'Este atributo ya está en otra fila.';
                }
                $vistos[$clave] = true;
            }
            if ($f['valor'] === '') {
                $errores["$i.valor"] = 'Escribe el valor (por ejemplo: 16 GB).';
            } elseif (mb_strlen($f['valor']) > Especificacion::MAX_VALOR) {
                $errores["$i.valor"] = 'No puede pasar de ' . Especificacion::MAX_VALOR . ' caracteres.';
            }
        }
        return [$filas, $errores];
    }

    private function cargar(int $id): array
    {
        $equipo = (new Equipo())->porId($id) ?? $this->notFound();
        if ($equipo['estado_inventario'] === 'de_baja') {
            Session::flash('error', "{$equipo['codigo']} está dado de baja: sus datos ya no se pueden cambiar.");
            $this->redirect("/equipos/$id");
        }
        return $equipo;
    }

    private function volver(int $id, array $filas, array $errores): never
    {
        Session::flash('form_old', $filas);
        if ($errores) {
            Session::flash('form_errores', $errores);
        }
        $this->redirect("/equipos/$id/especificaciones");
    }
}
