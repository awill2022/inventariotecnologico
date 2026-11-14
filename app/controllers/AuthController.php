<?php
/**
 * Inicio y cierre de sesión.
 */
final class AuthController extends Controller
{
    private const USUARIO_MIN = 3;
    private const USUARIO_MAX = 80;
    private const CLAVE_MAX   = 255;

    /** GET / → envía al inicio del rol o al login. */
    public function home(): void
    {
        $this->redirect(Auth::check() && Auth::home() ? Auth::home() : '/login');
    }

    /** GET /login */
    public function showLogin(): void
    {
        if (Auth::check() && Auth::home()) {
            $this->redirect(Auth::home());
        }

        $espera = LoginThrottle::segundosRestantes();

        $this->view('auth/login', [
            'titulo'    => 'Iniciar sesión',
            'error'     => $espera ? self::mensajeBloqueo($espera) : Session::flash('error'),
            'bloqueado' => $espera > 0,
            'errores'   => Session::flash('errores') ?? [],
            'usuario'   => Session::flash('old_usuario') ?? '',
            'csrf'      => Session::csrfToken(),
        ], 'auth');
    }

    /** POST /login */
    public function login(): void
    {
        $this->requireCsrf();

        if ($espera = LoginThrottle::segundosRestantes()) {
            $this->volverAlLogin(self::mensajeBloqueo($espera));
        }

        $usuario = trim((string) ($_POST['usuario'] ?? ''));
        $clave   = (string) ($_POST['clave'] ?? '');

        $errores = $this->validar($usuario, $clave);
        if ($errores) {
            $this->volverAlLogin(null, $errores, $usuario);
        }

        try {
            $modelo = new Usuario();
            $cuenta = $modelo->buscarActivo($usuario);

            if (!$cuenta || !$modelo->verificarClave($cuenta, $clave)) {
                $quedan  = LoginThrottle::registrarFallo();
                $mensaje = 'Usuario o contraseña incorrectos.';
                if ($quedan === 0) {
                    $mensaje = self::mensajeBloqueo(LoginThrottle::BLOQUEO_SEG);
                } elseif ($quedan <= 2) {
                    $mensaje .= ' ' . ($quedan === 1 ? 'Te queda 1 intento' : "Te quedan $quedan intentos")
                              . ' antes de un bloqueo temporal.';
                }
                $this->volverAlLogin($mensaje, [], $usuario);
            }

            $modelo->registrarAcceso((int) $cuenta['id']);
            (new Auditoria())->registrar((int) $cuenta['id'], 'usuarios', (int) $cuenta['id'], 'acceder');
        } catch (PDOException $e) {
            error_log('[login] ' . $e->getMessage());
            $this->volverAlLogin('No pudimos conectar con la base de datos. Inténtalo en unos minutos.', [], $usuario);
        }

        LoginThrottle::reiniciar();
        Auth::login($cuenta);

        $destino = Auth::home();
        if ($destino === null) {
            Auth::logout();
            Session::start(App::config('session', []));
            $this->volverAlLogin('Tu rol aún no tiene una página asignada en el sistema.');
        }
        // Con clave temporal, primero tiene que cambiarla; si no, vuelve a la página que pidió (p. ej. el QR)
        $pedida = Auth::tomarDestino();
        $this->redirect(Auth::debeCambiarClave() ? '/perfil' : ($pedida ?? $destino));
    }

    /** POST /logout */
    public function logout(): void
    {
        $this->requireCsrf();
        Auth::logout();
        $this->redirect('/login');
    }

    /** Reglas de formato de los campos; devuelve [campo => mensaje]. */
    private function validar(string $usuario, string $clave): array
    {
        $errores = [];

        if ($usuario === '') {
            $errores['usuario'] = 'Escribe tu usuario.';
        } elseif (mb_strlen($usuario) < self::USUARIO_MIN || mb_strlen($usuario) > self::USUARIO_MAX) {
            $errores['usuario'] = 'El usuario debe tener entre ' . self::USUARIO_MIN . ' y ' . self::USUARIO_MAX . ' caracteres.';
        } elseif (!preg_match('/^[A-Za-z0-9._@-]+$/', $usuario)) {
            $errores['usuario'] = 'El usuario solo admite letras, números y . _ - @ (sin espacios).';
        }

        if ($clave === '') {
            $errores['clave'] = 'Escribe tu contraseña.';
        } elseif (strlen($clave) > self::CLAVE_MAX) {
            $errores['clave'] = 'La contraseña es demasiado larga.';
        }

        return $errores;
    }

    private function volverAlLogin(?string $error, array $errores = [], string $usuario = ''): never
    {
        if ($error !== null) {
            Session::flash('error', $error);
        }
        if ($errores) {
            Session::flash('errores', $errores);
        }
        if ($usuario !== '') {
            Session::flash('old_usuario', $usuario);
        }
        $this->redirect('/login');
    }

    private static function mensajeBloqueo(int $segundos): string
    {
        $min = (int) ceil($segundos / 60);
        return 'Demasiados intentos fallidos. Por seguridad, espera '
             . Formato::plural($min, 'minuto', 'minutos') . ' antes de volver a intentarlo.';
    }
}
