<?php
/**
 * Estado de autenticación y control de acceso por rol.
 */
final class Auth
{
    /** Guarda en sesión los datos mínimos del usuario autenticado. */
    public static function login(array $usuario): void
    {
        Session::regenerate();
        $nombres   = trim($usuario['nombres'] ?? '');
        $apellidos = trim($usuario['apellidos'] ?? '');
        $nombre    = trim("$nombres $apellidos") ?: $usuario['usuario'];
        $primero   = strtok($nombres ?: $usuario['usuario'], ' ');
        $iniciales = mb_strtoupper(
            mb_substr($nombres ?: $usuario['usuario'], 0, 1) . mb_substr($apellidos, 0, 1)
        );

        Session::set('auth', [
            'id'         => (int) $usuario['id'],
            'usuario'    => $usuario['usuario'],
            'rol'        => $usuario['rol'],
            'persona_id' => isset($usuario['persona_id']) ? (int) $usuario['persona_id'] : null,
            'nombre'     => $nombre,
            'primer_nombre' => $primero,
            'iniciales'  => $iniciales,
            'cargo'      => $usuario['cargo'] ?? null,
            // Clave temporal (cuenta nueva o restablecida): debe cambiarla antes de usar el sistema
            'debe_cambiar_clave' => !empty($usuario['debe_cambiar_clave']),
        ]);
    }

    public static function debeCambiarClave(): bool
    {
        return !empty(self::user()['debe_cambiar_clave']);
    }

    /** Tras cambiar la clave: nueva sesión (otro id) y sin la marca de clave temporal. */
    public static function claveCambiada(): void
    {
        Session::regenerate();
        $auth = self::user();
        $auth['debe_cambiar_clave'] = false;
        Session::set('auth', $auth);
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    public static function check(): bool
    {
        return Session::get('auth') !== null;
    }

    public static function user(): ?array
    {
        return Session::get('auth');
    }

    public static function rol(): ?string
    {
        return self::user()['rol'] ?? null;
    }

    /** Ruta de inicio según el rol del usuario (config: home_por_rol). */
    public static function home(): ?string
    {
        return App::config('home_por_rol', [])[self::rol()] ?? null;
    }

    /**
     * Envía al login recordando la página pedida (solo GET), para volver a ella al entrar.
     * La ruta se guarda en la sesión del servidor: no viaja en la URL y no sirve para redirigir afuera.
     */
    public static function pedirLogin(string $mensaje, ?string $volverA = null): never
    {
        if ($volverA === null && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            $consulta = (string) ($_SERVER['QUERY_STRING'] ?? '');
            $volverA  = App::path() . ($consulta !== '' ? "?$consulta" : '');
        }
        if ($volverA !== null) {
            Session::set('tras_login', $volverA);
        }
        Session::flash('error', $mensaje);
        header('Location: ' . View::url('/login'));
        exit;
    }

    /** Página guardada por pedirLogin(), si es una ruta interna válida. Se borra al leerla. */
    public static function tomarDestino(): ?string
    {
        $ruta = Session::pull('tras_login');
        if (!is_string($ruta) || !str_starts_with($ruta, '/') || str_starts_with($ruta, '//')
            || str_contains($ruta, '\\') || in_array(strtok($ruta, '?'), ['/login', '/logout', '/'], true)) {
            return null;
        }
        return $ruta;
    }

    /** Rutas que se pueden usar mientras la clave sea temporal. */
    private const RUTAS_CLAVE_TEMPORAL = ['/perfil', '/perfil/clave', '/logout'];

    /**
     * Exige sesión iniciada y, si se indican, uno de los roles permitidos.
     * ['*'] permite cualquier rol autenticado.
     * Con clave temporal solo deja entrar a Mi perfil (para cambiarla) y a cerrar sesión.
     */
    public static function requireRole(array $roles): void
    {
        if (!self::check()) {
            self::pedirLogin('Inicia sesión para continuar.');
        }
        if (self::debeCambiarClave() && !in_array(App::path(), self::RUTAS_CLAVE_TEMPORAL, true)) {
            header('Location: ' . View::url('/perfil'));
            exit;
        }
        if ($roles !== ['*'] && !in_array(self::rol(), $roles, true)) {
            http_response_code(403);
            View::render('errors/403', [
                'titulo'  => 'Acceso denegado',
                'mensaje' => 'Tu rol no tiene permiso para ver esta sección.',
            ], 'auth');
            exit;
        }
    }
}
