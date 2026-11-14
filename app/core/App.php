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

        // Los errores siempre quedan en el log de PHP; solo se muestran en pantalla con debug
        $debug = (bool) self::config('app.debug', false);
        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');

        spl_autoload_register([self::class, 'autoload']);
        set_exception_handler(fn (Throwable $e) => self::errorInesperado($e, $debug));

        self::cabecerasSeguridad();
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

    /**
     * Cabeceras de seguridad para todas las respuestas:
     * - nosniff: el navegador no adivina el tipo de un archivo (importa en las descargas).
     * - SAMEORIGIN: otras páginas no pueden incrustar el sistema en un marco (clickjacking).
     * - same-origin: la URL interna no viaja a sitios externos (p. ej. el escudo del instituto).
     */
    private static function cabecerasSeguridad(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: same-origin');
        header_remove('X-Powered-By');
    }

    /** Excepción no controlada: se registra en el log y el usuario ve una página de error sin detalles. */
    private static function errorInesperado(Throwable $e, bool $debug): void
    {
        error_log('[app] ' . $e);
        if (!headers_sent()) {
            http_response_code(500);
        }
        if ($debug) {
            echo '<pre style="white-space:pre-wrap">' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
            return;
        }
        try {
            View::render('errors/403', [
                'titulo'  => 'Algo salió mal',
                'mensaje' => 'No pudimos completar lo que pediste. Inténtalo de nuevo en unos minutos; si sigue pasando, avisa al administrador.',
            ], 'auth');
        } catch (Throwable) {
            echo 'Ocurrió un error inesperado. Inténtalo de nuevo en unos minutos.';
        }
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
