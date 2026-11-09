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
        Session::set('auth', [
            'id'         => (int) $usuario['id'],
            'usuario'    => $usuario['usuario'],
            'rol'        => $usuario['rol'],
            'persona_id' => isset($usuario['persona_id']) ? (int) $usuario['persona_id'] : null,
            'nombre'     => $usuario['nombre'] ?? $usuario['usuario'],
        ]);
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
     * Exige sesión iniciada y, si se indican, uno de los roles permitidos.
     * ['*'] permite cualquier rol autenticado.
     */
    public static function requireRole(array $roles): void
    {
        if (!self::check()) {
            Session::flash('error', 'Inicia sesión para continuar.');
            header('Location: ' . View::url('/login'));
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
