<?php
/**
 * Arranque de la aplicación: configuración, autoload, sesión y despacho.
 */
final class App
{
    private static array $config = [];

    public static function run(): void
    {
        self::$config = require APP_PATH . '/config/config.php';
        date_default_timezone_set(self::config('app.timezone', 'UTC'));

        $debug = (bool) self::config('app.debug', false);
        ini_set('display_errors', $debug ? '1' : '0');
        error_reporting($debug ? E_ALL : 0);

        spl_autoload_register([self::class, 'autoload']);

        Session::start(self::config('session', []));

        $router = new Router();
        require APP_PATH . '/routes.php';
        $router->dispatch($_SERVER['REQUEST_METHOD'], self::path());
    }

    /** Lee un valor de configuración con notación de puntos: 'db.host'. */
    public static function config(string $key, mixed $default = null): mixed
    {
        $value = self::$config;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    /** Ruta base pública, p. ej. /inventario_tecnologico/inventariotecnologico/public */
    public static function basePath(): string
    {
        return rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    }

    /** Ruta solicitada relativa a la base: /login, /docente, ... */
    public static function path(): string
    {
        $uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
        $base = self::basePath();
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        if (str_starts_with($uri, '/index.php')) {
            $uri = substr($uri, strlen('/index.php'));
        }
        return '/' . trim($uri, '/');
    }

    private static function autoload(string $class): void
    {
        foreach (['core', 'controllers', 'models', 'helpers'] as $dir) {
            $file = APP_PATH . "/$dir/$class.php";
            if (is_file($file)) {
                require $file;
                return;
            }
        }
    }
}
