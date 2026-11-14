<?php
/**
 * Enrutador mínimo: asocia método + ruta a una acción de controlador
 * y aplica el control de acceso por rol antes de ejecutarla.
 *
 * Admite parámetros entre llaves: '/categorias/{id}/editar'.
 * {id} y los nombres que terminan en _id ({componente_id}) solo aceptan números;
 * cualquier otro nombre acepta un tramo sin '/'.
 * Los valores llegan a la acción como argumentos, en el orden de la ruta.
 */
final class Router
{
    /** Rutas fijas: [método][ruta] => definición. */
    private array $routes = [];

    /** Rutas con parámetros: [método][] => definición + expresión regular. */
    private array $dynamic = [];

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
        $path  = '/' . trim($path, '/');
        $route = ['action' => $action, 'roles' => $roles];

        if (!str_contains($path, '{')) {
            $this->routes[$method][$path] = $route;
            return;
        }

        $regex = preg_replace_callback(
            '/\\\\\{(\w+)\\\\\}/',
            fn ($m) => $m[1] === 'id' || str_ends_with($m[1], '_id') ? '(\d+)' : '([^/]+)',
            preg_quote($path, '#')
        );
        $this->dynamic[$method][] = $route + ['regex' => '#^' . $regex . '$#'];
    }

    public function dispatch(string $method, string $path): void
    {
        $route  = $this->routes[$method][$path] ?? null;
        $params = [];

        if ($route === null) {
            foreach ($this->dynamic[$method] ?? [] as $candidata) {
                if (preg_match($candidata['regex'], $path, $m)) {
                    $route  = $candidata;
                    $params = array_slice($m, 1);
                    break;
                }
            }
        }

        if ($route === null) {
            http_response_code(404);
            View::render('errors/404', ['titulo' => 'Página no encontrada'], 'auth');
            return;
        }

        if ($route['roles']) {
            Auth::requireRole($route['roles']);
        }

        [$class, $action] = $route['action'];
        (new $class())->$action(...$params);
    }
}
