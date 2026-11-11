<?php
/**
 * Motor de vistas: inserta la vista dentro de un layout y ofrece helpers.
 */
final class View
{
    public static function render(string $view, array $data = [], string $layout = 'main'): void
    {
        $file = APP_PATH . "/views/$view.php";
        if (!is_file($file)) {
            throw new RuntimeException("Vista no encontrada: $view");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        $contenido = ob_get_clean();

        require APP_PATH . "/views/layouts/$layout.php";
    }

    /** Escapa texto para HTML. */
    public static function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }

    /** URL de una ruta de la app: View::url('/login'). */
    public static function url(string $path = '/'): string
    {
        return App::basePath() . '/' . ltrim($path, '/');
    }

    /** URL absoluta (con esquema y host), p. ej. para codificarla en un QR. */
    public static function absoluteUrl(string $path = '/'): string
    {
        $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . self::url($path);
    }

    /**
     * URL de un recurso estático en public/assets. Lleva la fecha de modificación
     * del archivo (?v=) para que el navegador descargue la versión nueva tras un cambio.
     */
    public static function asset(string $path): string
    {
        $path    = ltrim($path, '/');
        $archivo = dirname(APP_PATH) . '/public/assets/' . $path;
        $version = is_file($archivo) ? '?v=' . filemtime($archivo) : '';
        return App::basePath() . '/assets/' . $path . $version;
    }
}
