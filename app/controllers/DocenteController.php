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
            'reporte'   => [
                'ok'      => Session::flash('reporte_ok'),
                'error'   => Session::flash('reporte_error'),
                'errores' => Session::flash('reporte_errores') ?? [],
                'old'     => Session::flash('reporte_old') ?? [],
            ],
            'csrf'      => Session::csrfToken(),
            'nav'       => true,
            'estilos'   => ['docente.css'],
            'scripts'   => ['vendor/qrcode.min.js', 'docente.js'],
        ]);
    }

    /** POST /docente/reportar — crea la solicitud de diagnóstico y marca el estado del equipo. */
    public function reportar(): void
    {
        $this->requireCsrf();

        $usuario   = Auth::user();
        $equipoId  = filter_var($_POST['equipo'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $problema  = trim((string) ($_POST['problema'] ?? ''));
        $uso       = (string) ($_POST['uso'] ?? '');
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

        if ($errores) {
            Session::flash('reporte_errores', $errores);
            Session::flash('reporte_old', $old);
            $this->redirect('/docente#reportar');
        }

        try {
            $mant = new Mantenimiento();
            $mantId = $mant->transaccion(function () use ($mant, $equipo, $problema, $uso, $usuario) {
                $id = $mant->reportarFalla((int) $equipo['id'], $problema, $usuario['id']);
                (new Equipo())->marcarFalla((int) $equipo['id'], $uso);
                return $id;
            });
            (new Auditoria())->registrar($usuario['id'], 'mantenimientos', $mantId, 'crear', [
                'origen' => 'reporte_docente', 'equipo' => $equipo['codigo'], 'uso' => $uso,
            ]);
        } catch (PDOException $e) {
            error_log('[reportar] ' . $e->getMessage());
            Session::flash('reporte_error', 'No pudimos guardar tu reporte. Inténtalo de nuevo en unos minutos.');
            Session::flash('reporte_old', $old);
            $this->redirect('/docente#reportar');
        }

        $comoQueda = $uso === 'inoperativo' ? 'como equipo inoperativo' : 'como equipo con fallas';
        if ($equipo['estado_operativo'] === 'en_mantenimiento') {
            $comoQueda = 'y la sumamos a su mantenimiento en curso';
        }
        Session::flash('reporte_ok', "Registramos la falla de {$equipo['codigo']} $comoQueda. "
            . 'Soporte TI revisará tu solicitud de diagnóstico.');
        $this->redirect('/docente#reportar');
    }
}
