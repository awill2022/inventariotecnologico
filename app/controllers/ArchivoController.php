<?php
/**
 * Archivos adjuntos: descarga con control de acceso y adjuntos de entregas y traslados.
 *
 * - Ver/descargar: según a qué está enlazado el archivo (mantenimiento, asignación, movimiento
 *   o solo el equipo), con los permisos de ese módulo. Quien lo subió siempre puede verlo.
 * - Subir y eliminar adjuntos de entregas y traslados: administrador e inventario.
 * - Las fotos de un reporte de falla solo las elimina el administrador.
 */
final class ArchivoController extends Controller
{
    /** Tipos que se pueden elegir al subir, según dónde. */
    private const TIPOS_ASIGNACION = ['acta_entrega', 'acta_devolucion', 'otro'];
    private const TIPOS_MOVIMIENTO = ['respaldo_traslado', 'otro'];
    private const DESCRIPCION_MAX  = 255;

    /** GET /archivos/{id} — muestra el archivo (?descargar=1 lo baja). */
    public function descargar(int $id): void
    {
        $a = (new Archivo())->porId($id) ?? $this->notFound();
        if (!self::puedeVer($a)) {
            $this->prohibido();
        }

        $ruta = Subida::rutaAbsoluta($a['ruta_archivo']);
        if ($ruta === null || !is_file($ruta)) {
            error_log("[archivos] falta el archivo físico del id $id");
            $this->notFound();
        }

        // Solo se sirven en línea los tipos que admitimos al subir; cualquier otro se fuerza a descarga.
        $conocido = isset((Subida::FOTOS + Subida::DOCUMENTOS)[$a['mime_type']]);
        $enLinea  = $conocido && !isset($_GET['descargar']);
        $nombre   = $a['nombre_original'];
        $ascii    = preg_replace('/[^A-Za-z0-9._-]+/', '_', $nombre) ?: 'archivo';

        header('Content-Type: ' . ($conocido ? $a['mime_type'] : 'application/octet-stream'));
        header('Content-Length: ' . filesize($ruta));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=86400');
        header(sprintf('Content-Disposition: %s; filename="%s"; filename*=UTF-8\'\'%s',
            $enLinea ? 'inline' : 'attachment', $ascii, rawurlencode($nombre)));
        session_write_close(); // no bloquear la sesión mientras se envía
        readfile($ruta);
        exit;
    }

    /** POST /archivos/{id}/eliminar */
    public function eliminar(int $id): void
    {
        $this->requireCsrf();
        $modelo = new Archivo();
        $a      = $modelo->porId($id) ?? $this->notFound();
        if (!self::puedeEliminar($a)) {
            $this->prohibido();
        }
        $volver = self::paginaDe($a);

        try {
            $modelo->eliminar($id);
            (new Auditoria())->registrar(Auth::user()['id'], 'archivos', $id, 'anular', [
                'nombre' => $a['nombre_original'], 'tipo' => $a['tipo'],
                'asignacion' => $a['asignacion_id'], 'movimiento' => $a['movimiento_id'],
                'mantenimiento' => $a['mantenimiento_id'],
            ]);
        } catch (PDOException $e) {
            error_log('[archivos/eliminar] ' . $e->getMessage());
            Session::flash('error', 'No pudimos eliminar el archivo. Inténtalo de nuevo.');
            $this->redirect($volver);
        }
        Subida::borrar($a['ruta_archivo']);

        Session::flash('ok', "Se eliminó «{$a['nombre_original']}».");
        $this->redirect($volver);
    }

    /* ---------- Adjuntos de una entrega ---------- */

    /** GET /asignaciones/{id}/adjuntos */
    public function asignacion(int $id): void
    {
        $as = (new Asignacion())->porId($id) ?? $this->notFound();
        $devuelta = $as['fecha_devolucion'] !== null;

        $contexto = [
            'Equipo'    => ['texto' => "{$as['codigo']} · {$as['equipo']}", 'url' => "/equipos/{$as['equipo_id']}"],
            'Persona'   => ['texto' => $as['persona'], 'url' => "/personas/{$as['persona_id']}"],
            'Entregado' => ['texto' => Formato::fechaHora($as['fecha_entrega'])],
            'Devuelto'  => ['texto' => $devuelta ? Formato::fechaHora($as['fecha_devolucion']) : 'Sigue a su cargo'],
        ];
        $tipos = $devuelta ? self::TIPOS_ASIGNACION : array_diff(self::TIPOS_ASIGNACION, ['acta_devolucion']);

        $this->pagina([
            'titulo'      => "Adjuntos de la entrega de {$as['codigo']}",
            'intro'       => 'Sube el acta firmada (escaneada en PDF o como foto) para que quede junto a la entrega.',
            'volver'      => ['url' => '/asignaciones', 'texto' => 'Volver a asignaciones'],
            'contexto'    => $contexto,
            'enlaceExtra' => ['url' => "/asignaciones/$id/acta", 'texto' => 'Imprimir el acta para firmar'],
            'archivos'    => (new Archivo())->de('asignacion_id', $id),
            'puedeSubir'  => in_array(Auth::rol(), AsignacionController::GESTIONAR, true),
            'accion'      => "/asignaciones/$id/adjuntos",
            'tipos'       => array_intersect_key(Archivo::TIPOS, array_flip($tipos)),
            'tipoDefecto' => $devuelta ? 'acta_devolucion' : 'acta_entrega',
        ]);
    }

    /** POST /asignaciones/{id}/adjuntos */
    public function subirAsignacion(int $id): void
    {
        $this->requireCsrf();
        $as = (new Asignacion())->porId($id) ?? $this->notFound();
        $tipos = $as['fecha_devolucion'] !== null ? self::TIPOS_ASIGNACION : array_diff(self::TIPOS_ASIGNACION, ['acta_devolucion']);

        $this->subir("/asignaciones/$id/adjuntos", $tipos, [
            'equipo_id' => (int) $as['equipo_id'], 'asignacion_id' => $id,
        ], ['asignacion' => $id, 'equipo' => $as['codigo']]);
    }

    /* ---------- Adjuntos de un movimiento ---------- */

    /** GET /movimientos/{id}/adjuntos */
    public function movimiento(int $id): void
    {
        $m = (new Movimiento())->porId($id) ?? $this->notFound();

        $contexto = [
            'Equipo'   => ['texto' => "{$m['codigo']} · {$m['equipo']}", 'url' => "/equipos/{$m['equipo_id']}"],
            'Fecha'    => ['texto' => Formato::fechaHora($m['fecha'])],
            'Traslado' => ['texto' => $m['origen'] ? "{$m['origen']} → {$m['destino']}" : "Ingresó a {$m['destino']}"],
            'Motivo'   => ['texto' => (string) $m['motivo']],
        ];
        if ($m['autorizado']) {
            $contexto['Autorizó'] = ['texto' => $m['autorizado']];
        }

        $this->pagina([
            'titulo'      => "Adjuntos del traslado de {$m['codigo']}",
            'intro'       => 'Sube el documento que respalda el traslado: memorando, orden o acta firmada.',
            'volver'      => ['url' => '/movimientos?equipo=' . (int) $m['equipo_id'], 'texto' => "Volver a los movimientos de {$m['codigo']}"],
            'contexto'    => $contexto,
            'enlaceExtra' => null,
            'archivos'    => (new Archivo())->de('movimiento_id', $id),
            'puedeSubir'  => in_array(Auth::rol(), MovimientoController::GESTIONAR, true),
            'accion'      => "/movimientos/$id/adjuntos",
            'tipos'       => array_intersect_key(Archivo::TIPOS, array_flip(self::TIPOS_MOVIMIENTO)),
            'tipoDefecto' => 'respaldo_traslado',
        ]);
    }

    /** POST /movimientos/{id}/adjuntos */
    public function subirMovimiento(int $id): void
    {
        $this->requireCsrf();
        $m = (new Movimiento())->porId($id) ?? $this->notFound();

        $this->subir("/movimientos/$id/adjuntos", self::TIPOS_MOVIMIENTO, [
            'equipo_id' => (int) $m['equipo_id'], 'movimiento_id' => $id,
        ], ['movimiento' => $id, 'equipo' => $m['codigo']]);
    }

    /* ---------- Comunes ---------- */

    private function pagina(array $datos): void
    {
        $this->view('archivos/adjuntos', $datos + [
            'formatos' => Subida::formatos(Subida::DOCUMENTOS + Subida::FOTOS),
            'maxDoc'   => Formato::bytes((int) App::config('archivos.max_documento')),
            'maxFoto'  => Formato::bytes((int) App::config('archivos.max_foto')),
            'errores'  => Session::flash('adjunto_errores') ?? [],
            'old'      => Session::flash('adjunto_old') ?? [],
            'csrf'     => Session::csrfToken(),
            'estilos'  => ['equipos.css', 'archivos.css'],
            'scripts'  => ['panel.js'],
        ], 'panel');
    }

    /** Valida y guarda un adjunto (PDF o foto) enlazado a $enlace; vuelve a $volver. */
    private function subir(string $volver, array $tipos, array $enlace, array $detalle): never
    {
        $tipo        = (string) ($_POST['tipo'] ?? '');
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
        $archivos    = Subida::lista('archivo');
        $errores     = [];
        $datos       = null;

        if (!in_array($tipo, $tipos, true)) {
            $errores['tipo'] = 'Elige qué documento es.';
        }
        if (mb_strlen($descripcion) > self::DESCRIPCION_MAX) {
            $errores['descripcion'] = 'La descripción no puede pasar de ' . self::DESCRIPCION_MAX . ' caracteres.';
        }
        if (count($archivos) !== 1) {
            $errores['archivo'] = $archivos ? 'Sube un archivo a la vez.' : 'Elige el archivo que quieres subir.';
        } else {
            $datos = Subida::validar($archivos[0], Subida::DOCUMENTOS + Subida::FOTOS);
            if ($datos['error']) {
                $errores['archivo'] = $datos['error'];
            }
        }

        if ($errores) {
            Session::flash('adjunto_errores', $errores);
            Session::flash('adjunto_old', ['tipo' => $tipo, 'descripcion' => $descripcion]);
            $this->redirect($volver);
        }

        $usuario  = Auth::user();
        $relativa = null;
        try {
            $relativa = Subida::guardar($archivos[0], $datos['ext']);
            $esActa   = str_starts_with($tipo, 'acta_') || $tipo === 'respaldo_traslado';
            $id = (new Archivo())->crear($enlace + [
                'clase'           => $esActa || !Subida::esFoto($datos['mime']) ? 'documento' : 'fotografia',
                'tipo'            => $tipo,
                'nombre_original' => $datos['nombre'],
                'ruta_archivo'    => $relativa,
                'mime_type'       => $datos['mime'],
                'tamano_bytes'    => $datos['tamano'],
                'descripcion'     => $descripcion !== '' ? $descripcion : null,
                'subido_por'      => $usuario['id'],
            ]);
            (new Auditoria())->registrar($usuario['id'], 'archivos', $id, 'crear',
                $detalle + ['nombre' => $datos['nombre'], 'tipo' => $tipo, 'bytes' => $datos['tamano']]);
        } catch (Throwable $e) {
            if ($relativa) {
                Subida::borrar($relativa);
            }
            error_log('[archivos/subir] ' . $e->getMessage());
            Session::flash('error', 'No pudimos guardar el archivo. Inténtalo de nuevo en unos minutos.');
            Session::flash('adjunto_old', ['tipo' => $tipo, 'descripcion' => $descripcion]);
            $this->redirect($volver);
        }

        Session::flash('ok', "Se subió «{$datos['nombre']}».");
        $this->redirect($volver);
    }

    /** Quién puede abrir un archivo: quien lo subió o los roles que ven aquello a lo que está enlazado. */
    private static function puedeVer(array $a): bool
    {
        $usuario = Auth::user();
        if ((int) $a['subido_por'] === $usuario['id']) {
            return true;
        }
        return in_array($usuario['rol'], match (true) {
            $a['mantenimiento_id'] !== null => MantenimientoController::VER,
            $a['asignacion_id'] !== null    => AsignacionController::VER,
            $a['movimiento_id'] !== null    => MovimientoController::VER,
            default                         => EquipoController::VER,
        }, true);
    }

    private static function puedeEliminar(array $a): bool
    {
        return in_array(Auth::rol(), match (true) {
            $a['mantenimiento_id'] !== null => ['administrador'],
            $a['asignacion_id'] !== null    => AsignacionController::GESTIONAR,
            $a['movimiento_id'] !== null    => MovimientoController::GESTIONAR,
            default                         => EquipoController::GESTIONAR,
        }, true);
    }

    /** Página a la que se vuelve después de eliminar. */
    private static function paginaDe(array $a): string
    {
        return match (true) {
            $a['mantenimiento_id'] !== null => "/mantenimientos/{$a['mantenimiento_id']}",
            $a['asignacion_id'] !== null    => "/asignaciones/{$a['asignacion_id']}/adjuntos",
            $a['movimiento_id'] !== null    => "/movimientos/{$a['movimiento_id']}/adjuntos",
            default                         => "/equipos/{$a['equipo_id']}",
        };
    }

    private function prohibido(): never
    {
        http_response_code(403);
        View::render('errors/403', [
            'titulo'  => 'Acceso denegado',
            'mensaje' => 'Tu rol no tiene permiso para ver este archivo.',
        ], 'auth');
        exit;
    }
}
