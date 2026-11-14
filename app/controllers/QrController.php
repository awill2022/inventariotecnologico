<?php
/**
 * Página pública del QR de la etiqueta: /q/{qr_token}.
 *
 * - Sin sesión: ficha técnica del equipo (datos, estado, ubicación, especificaciones, responsable y
 *   último mantenimiento). Nunca muestra serie, valor, proveedor ni historial.
 * - «Reportar una falla» pide iniciar sesión y solo lo puede enviar el docente que tiene el equipo a su cargo.
 * - Los roles del panel ven además el enlace a la ficha completa.
 */
final class QrController extends Controller
{
    /** El token es el qr_token del equipo: 32 caracteres hexadecimales (bin2hex de 16 bytes). */
    public static function tokenValido(string $token): bool
    {
        return (bool) preg_match('/^[a-f0-9]{32}$/', $token);
    }

    /** GET /q/{token} */
    public function ver(string $token): void
    {
        $e = $this->equipo($token);
        $id = (int) $e['id'];
        $mants = (new Mantenimiento())->deEquipos([$id])[$id] ?? [];

        $ultimo  = null;
        $abierto = null;
        foreach ($mants as $m) {
            if (!$abierto && in_array($m['estado'], ['programado', 'en_proceso'], true)) {
                $abierto = $m;
            }
            if (!$ultimo && $m['estado'] === 'finalizado') {
                $ultimo = $m;
            }
        }

        $this->view('qr/ver', [
            'titulo'    => "{$e['codigo']} · {$e['nombre']}",
            'e'         => $e,
            'token'     => $token,
            'specs'     => (new Especificacion())->deEquipo($id),
            'ultimo'    => $ultimo,
            'abierto'   => $abierto,
            'acceso'    => self::acceso($e),
            'verFicha'  => Auth::check() && in_array(Auth::rol(), EquipoController::VER, true),
            'ok'        => Session::flash('reporte_ok'),
            'aviso'     => Session::flash('qr_aviso'),
            'noindex'   => true,
            'estilos'   => ['qr.css'],
        ]);
    }

    /** GET /q/{token}/reportar — formulario de falla de este equipo (solo el docente a cargo). */
    public function reportar(string $token): void
    {
        $e = $this->equipo($token);

        if (!Auth::check()) {
            Auth::pedirLogin("Inicia sesión para reportar la falla de {$e['codigo']}.", "/q/$token/reportar");
        }
        Auth::requireRole(['*']); // clave temporal → primero Mi perfil

        $acceso = self::acceso($e);
        if ($acceso !== 'a_cargo') {
            Session::flash('qr_aviso', match ($acceso) {
                'baja'  => 'Este equipo fue dado de baja: ya no recibe reportes.',
                'panel' => 'Los reportes de falla los envía el docente que tiene el equipo. Desde el panel puedes programar un diagnóstico.',
                default => 'Este equipo está a cargo de otra persona: solo su responsable puede reportar fallas. Si notas un problema, avísale o comunícate con Soporte TI.',
            });
            $this->redirect("/q/$token");
        }

        $this->view('qr/reportar', [
            'titulo'   => "Reportar falla de {$e['codigo']}",
            'e'        => $e,
            'token'    => $token,
            'reporte'  => DocenteController::datosFormulario(),
            'csrf'     => Session::csrfToken(),
            'noindex'  => true,
            'estilos'  => ['docente.css', 'archivos.css', 'qr.css'],
            'scripts'  => ['reporte.js'],
        ]);
    }

    /** Equipo del token o 404 (también si el token no tiene el formato esperado). */
    private function equipo(string $token): array
    {
        if (!self::tokenValido($token)) {
            $this->notFound();
        }
        return (new Equipo())->porToken($token) ?? $this->notFound();
    }

    /**
     * Qué puede hacer quien mira la página:
     * anonimo | a_cargo (docente responsable) | docente (otro docente) | panel (roles del panel) | baja (equipo de baja)
     */
    private static function acceso(array $e): string
    {
        if ($e['estado_inventario'] === 'de_baja') {
            return 'baja';
        }
        $u = Auth::user();
        if (!$u) {
            return 'anonimo';
        }
        if ($u['rol'] === 'consulta') {
            return $u['persona_id'] && (int) $e['responsable_id'] === (int) $u['persona_id'] ? 'a_cargo' : 'docente';
        }
        return in_array($u['rol'], EquipoController::VER, true) ? 'panel' : 'docente';
    }
}
