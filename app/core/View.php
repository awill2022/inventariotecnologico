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

    /** URL de un recurso estático en public/assets. */
    public static function asset(string $path): string
    {
        return App::basePath() . '/assets/' . ltrim($path, '/');
    }
}
