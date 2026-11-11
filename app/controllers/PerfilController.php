<?php
/**
 * Mi perfil: datos de la cuenta y cambio de la clave propia. Lo usan todos los roles.
 * Si la clave es temporal (cuenta nueva o restablecida), es la única página
 * disponible hasta cambiarla (ver Auth::requireRole).
 */
final class PerfilController extends Controller
{
    /** GET /perfil */
    public function inicio(): void
    {
        $perfil = (new Usuario())->perfil(Auth::user()['id']);
        if ($perfil === null) {
            // La cuenta ya no existe: se cierra la sesión
            Auth::logout();
            $this->redirect('/login');
        }

        $forzado = Auth::debeCambiarClave();
        $docente = Auth::rol() === 'consulta';

        $this->view('perfil/inicio', [
            'titulo'  => 'Mi perfil',
            'perfil'  => $perfil,
            'rol'     => Usuario::ROLES[$perfil['rol']] ?? $perfil['rol'],
            'forzado' => $forzado,
            'suelto'  => $docente, // layout main: la página pone su propio contenedor
            'volver'  => $docente && !$forzado ? Auth::home() : null,
            'campos'  => self::campos($forzado),
            'errores' => Session::flash('form_errores') ?? [],
            'csrf'    => Session::csrfToken(),
            'estilos' => ['perfil.css'],
            'scripts' => ['panel.js'],
        ], $docente ? 'main' : 'panel');
    }

    /** POST /perfil/clave */
    public function cambiarClave(): void
    {
        $this->requireCsrf();
        $modelo = new Usuario();
        $id     = Auth::user()['id'];
        $perfil = $modelo->perfil($id) ?? $this->redirect('/login');

        // El usuario se agrega para que la política impida usarlo como clave
        [$datos, $errores] = Validador::validar(self::campos(Auth::debeCambiarClave()), ['usuario' => $perfil['usuario']] + $_POST);

        if (!isset($errores['clave_actual']) && !$modelo->verificarClave($perfil, $datos['clave_actual'])) {
            $errores['clave_actual'] = Auth::debeCambiarClave()
                ? 'La clave temporal no es correcta.'
                : 'La clave actual no es correcta.';
        }
        if (!$errores && hash_equals($datos['clave_actual'], $datos['clave'])) {
            $errores['clave'] = 'La clave nueva debe ser distinta de la actual.';
        }
        if ($errores) {
            Session::flash('form_errores', $errores);
            $this->redirect('/perfil');
        }

        try {
            $modelo->transaccion(function () use ($modelo, $id, $datos, $perfil) {
                $modelo->cambiarClave($id, $datos['clave'], false);
                (new Auditoria())->registrar($id, 'usuarios', $id, 'editar', [
                    'nota'    => 'Cambio de clave propia',
                    'cambios' => ['debe_cambiar_clave' => [(int) $perfil['debe_cambiar_clave'], 0]],
                ]);
            });
        } catch (PDOException $e) {
            error_log('[perfil.clave] ' . $e->getMessage());
            Session::flash('error', 'No pudimos cambiar tu clave. Inténtalo de nuevo en unos minutos.');
            $this->redirect('/perfil');
        }

        $eraTemporal = Auth::debeCambiarClave();
        Auth::claveCambiada();

        if ($eraTemporal) {
            Session::flash('ok', 'Listo, tu clave quedó guardada. Ya puedes usar el sistema.');
            $this->redirect(Auth::home() ?? '/');
        }
        Session::flash('ok', 'Tu clave se cambió correctamente.');
        $this->redirect('/perfil');
    }

    /** Campos del cambio de clave. Con clave temporal, la "actual" es la que entregó el administrador. */
    private static function campos(bool $temporal): array
    {
        return [
            ['nombre' => 'clave_actual', 'etiqueta' => $temporal ? 'Clave temporal' : 'Clave actual',
             'tipo' => 'password', 'requerido' => true, 'autocomplete' => 'current-password', 'max' => 255,
             'ayuda' => $temporal ? 'La que te entregó el administrador para entrar.' : null],
            ...UsuarioController::camposClave('Clave nueva', false),
        ];
    }
}
