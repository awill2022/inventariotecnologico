<?php
/**
 * Página principal del docente (rol: consulta).
 * Muestra los equipos a su cargo, el historial reciente y permite reportar fallas.
 */
final class DocenteController extends Controller
{
    private const PROBLEMA_MIN = 15;
    private const PROBLEMA_MAX = 2000;
    private const USOS = ['con_fallas', 'inoperativo'];

    /** GET /docente */
    public function inicio(): void
    {
        $usuario   = Auth::user();
        $personaId = $usuario['persona_id'];

        $etiquetas = [];
        $historial = [];

        if ($personaId) {
            $equipoModel = new Equipo();
            $equipos     = $equipoModel->asignadosA($personaId);
            $ids         = array_map(fn ($e) => (int) $e['id'], $equipos);
            $mants       = (new Mantenimiento())->deEquipos($ids);
            $specs       = $equipoModel->especificaciones($ids);

            foreach ($equipos as $e) {
                $id          = (int) $e['id'];
                $etiquetas[] = EtiquetaEquipo::desde($e, $mants[$id] ?? [], $specs[$id] ?? []);
            }

            $historial = array_map(
                [EventoHistorial::class, 'desde'],
                (new Historial())->deEquiposDe($personaId, 10)
            );
        }

        $atencion = count(array_filter($etiquetas, fn ($t) => $t['estado'] !== 'operativo'));
        $ahora    = new DateTimeImmutable();

        $this->view('docente/inicio', [
            'titulo'    => 'Inicio',
            'usuario'   => $usuario,
            'saludo'    => Formato::saludo($ahora),
            'hoyIso'    => $ahora->format('Y-m-d'),
            'hoyTexto'  => Formato::fechaLarga($ahora),
            'etiquetas' => $etiquetas,
            'atencion'  => $atencion,
            'historial' => $historial,
            'reporte'   => self::datosFormulario(),
            'csrf'      => Session::csrfToken(),
            'nav'       => true,
            'estilos'   => ['docente.css', 'archivos.css'],
            'scripts'   => ['vendor/qrcode.min.js', 'vendor/jsQR.min.js', 'docente.js', 'reporte.js', 'lector-qr.js'],
        ]);
    }

    /**
     * POST /docente/reportar — crea la solicitud de diagnóstico, guarda las fotos y marca el estado del equipo.
     * Llega desde /docente o desde la página del QR (campo oculto qr = token): los errores vuelven a la página de origen.
     */
    public function reportar(): void
    {
        $this->requireCsrf();

        $usuario   = Auth::user();
        $equipoId  = filter_var($_POST['equipo'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $problema  = trim((string) ($_POST['problema'] ?? ''));
        $uso       = (string) ($_POST['uso'] ?? '');
        $token     = (string) ($_POST['qr'] ?? '');
        $desdeQr   = QrController::tokenValido($token);
        $volver    = $desdeQr ? "/q/$token/reportar" : '/docente#reportar';
        $old       = ['equipo' => $equipoId ?: '', 'problema' => $problema, 'uso' => $uso];

        $errores = [];
        $equipo  = null;

        if (!$equipoId || !$usuario['persona_id']
            || !($equipo = (new Asignacion())->equipoAbiertoDe($equipoId, $usuario['persona_id']))) {
            $errores['equipo'] = 'Elige uno de los equipos que tienes a tu cargo.';
        }
        $largo = mb_strlen($problema);
        if ($largo < self::PROBLEMA_MIN) {
            $errores['problema'] = 'Cuéntanos qué ocurre (al menos ' . self::PROBLEMA_MIN . ' caracteres).';
        } elseif ($largo > self::PROBLEMA_MAX) {
            $errores['problema'] = 'La descripción no puede pasar de ' . self::PROBLEMA_MAX . ' caracteres.';
        }
        if (!in_array($uso, self::USOS, true)) {
            $errores['uso'] = 'Indica si todavía puedes usar el equipo.';
        }

        // Fotos de la falla: opcionales, hasta archivos.fotos_reporte
        $fotos  = Subida::lista('fotos');
        $maximo = self::maxFotos();
        $listas = [];
        if (count($fotos) > $maximo) {
            $errores['fotos'] = 'Puedes adjuntar hasta ' . Formato::plural($maximo, 'foto', 'fotos') . '.';
        } else {
            foreach ($fotos as $foto) {
                $datos = Subida::validar($foto, Subida::FOTOS);
                if ($datos['error']) {
                    $errores['fotos'] = $datos['error'];
                    break;
                }
                $listas[] = [$foto, $datos];
            }
        }

        if ($errores) {
            // El navegador no conserva los archivos elegidos: hay que volver a adjuntarlos
            if ($fotos && !isset($errores['fotos'])) {
                $errores['fotos'] = 'Vuelve a adjuntar las fotos antes de enviar.';
            }
            Session::flash('reporte_errores', $errores);
            Session::flash('reporte_old', $old);
            $this->redirect($volver);
        }

        $guardadas = [];
        try {
            $mant   = new Mantenimiento();
            $mantId = $mant->transaccion(function () use ($mant, $equipo, $problema, $uso, $usuario, $listas, &$guardadas) {
                $id = $mant->reportarFalla((int) $equipo['id'], $problema, $usuario['id']);
                (new Equipo())->marcarFalla((int) $equipo['id'], $uso);

                $archivo = new Archivo();
                foreach ($listas as [$foto, $datos]) {
                    $ruta        = Subida::guardar($foto, $datos['ext']);
                    $guardadas[] = $ruta;
                    $archivo->crear([
                        'equipo_id'        => (int) $equipo['id'],
                        'mantenimiento_id' => $id,
                        'clase'            => 'fotografia',
                        'tipo'             => 'falla',
                        'nombre_original'  => $datos['nombre'],
                        'ruta_archivo'     => $ruta,
                        'mime_type'        => $datos['mime'],
                        'tamano_bytes'     => $datos['tamano'],
                        'subido_por'       => $usuario['id'],
                    ]);
                }
                return $id;
            });
            (new Auditoria())->registrar($usuario['id'], 'mantenimientos', $mantId, 'crear', [
                'origen' => $desdeQr ? 'reporte_docente_qr' : 'reporte_docente',
                'equipo' => $equipo['codigo'], 'uso' => $uso, 'fotos' => count($listas),
            ]);
        } catch (Throwable $e) {
            // Si algo falló, no deja fotos sueltas en el disco
            foreach ($guardadas as $ruta) {
                Subida::borrar($ruta);
            }
            error_log('[reportar] ' . $e->getMessage());
            Session::flash('reporte_error', 'No pudimos guardar tu reporte. Inténtalo de nuevo en unos minutos.');
            Session::flash('reporte_old', $old);
            $this->redirect($volver);
        }

        $comoQueda = $uso === 'inoperativo' ? 'como equipo inoperativo' : 'como equipo con fallas';
        if ($equipo['estado_operativo'] === 'en_mantenimiento') {
            $comoQueda = 'y la sumamos a su mantenimiento en curso';
        }
        $conFotos = $listas ? ' con ' . Formato::plural(count($listas), 'foto', 'fotos') : '';
        Session::flash('reporte_ok', "Registramos la falla de {$equipo['codigo']}$conFotos $comoQueda. "
            . 'Soporte TI revisará tu solicitud de diagnóstico.');
        $this->redirect($desdeQr ? "/q/$token" : '/docente#reportar');
    }

    /** Cuántas fotos admite un reporte de falla (config archivos.fotos_reporte). */
    public static function maxFotos(): int
    {
        return max(1, (int) App::config('archivos.fotos_reporte', 3));
    }

    /** Datos que necesita el formulario de reporte (partials/reporte_falla.php). */
    public static function datosFormulario(): array
    {
        return [
            'ok'       => Session::flash('reporte_ok'),
            'error'    => Session::flash('reporte_error'),
            'errores'  => Session::flash('reporte_errores') ?? [],
            'old'      => Session::flash('reporte_old') ?? [],
            'maxFotos' => self::maxFotos(),
            'maxBytes' => (int) App::config('archivos.max_foto'),
        ];
    }
}
