<?php
/**
 * Controlador base: renderizado de vistas, redirecciones, JSON y CSRF.
 */
abstract class Controller
{
    protected function view(string $view, array $data = [], string $layout = 'main'): void
    {
        View::render($view, $data, $layout);
    }

    protected function redirect(string $path): never
    {
        header('Location: ' . View::url($path));
        exit;
    }

    protected function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Responde 404 cuando el registro pedido no existe. */
    protected function notFound(): never
    {
        http_response_code(404);
        View::render('errors/404', ['titulo' => 'Página no encontrada'], 'auth');
        exit;
    }

    /** Corta la petición si el token CSRF del formulario no es válido. */
    protected function requireCsrf(): void
    {
        if (!Session::validCsrf($_POST['_csrf'] ?? null)) {
            http_response_code(403); // Apache convierte el 419 (no estándar) en 500
            View::render('errors/403', [
                'titulo'  => 'Sesión expirada',
                'mensaje' => 'El formulario expiró. Vuelve a intentarlo.',
            ], 'auth');
            exit;
        }
    }
}
