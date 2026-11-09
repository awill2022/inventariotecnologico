<?php
/**
 * Enrutador mínimo: asocia método + ruta a una acción de controlador
 * y aplica el control de acceso por rol antes de ejecutarla.
 */
final class Router
{
    private array $routes = [];

    public function get(string $path, array $action, array $roles = []): void
    {
        $this->add('GET', $path, $action, $roles);
    }

    public function post(string $path, array $action, array $roles = []): void
    {
        $this->add('POST', $path, $action, $roles);
    }

    private function add(string $method, string $path, array $action, array $roles): void
    {
        $this->routes[$method]['/' . trim($path, '/')] = ['action' => $action, 'roles' => $roles];
    }

    public function dispatch(string $method, string $path): void
    {
        $route = $this->routes[$method][$path] ?? null;

        if ($route === null) {
            http_response_code(404);
            View::render('errors/404', ['titulo' => 'Página no encontrada'], 'auth');
            return;
        }

        if ($route['roles']) {
            Auth::requireRole($route['roles']);
        }

        [$class, $action] = $route['action'];
        (new $class())->$action();
    }
}
