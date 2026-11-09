<?php
/**
 * Inicio y cierre de sesión.
 */
final class AuthController extends Controller
{
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

        $this->view('auth/login', [
            'titulo' => 'Iniciar sesión',
            'error'  => Session::flash('error'),
            'csrf'   => Session::csrfToken(),
        ], 'auth');
    }

    /** POST /login — se implementa en el siguiente paso del sprint. */
    public function login(): void
    {
        $this->requireCsrf();
        // TODO: validar campos, Usuario::buscarActivo(), verificar clave, Auth::login().
        Session::flash('error', 'El inicio de sesión aún no está implementado.');
        $this->redirect('/login');
    }

    /** POST /logout */
    public function logout(): void
    {
        $this->requireCsrf();
        Auth::logout();
        $this->redirect('/login');
    }
}
