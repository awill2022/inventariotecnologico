<?php
/**
 * Límite de intentos fallidos de inicio de sesión.
 * Tras MAX_INTENTOS fallos seguidos se bloquea el login BLOQUEO_SEG segundos.
 * Se guarda en la sesión (no requiere cambios en el esquema).
 */
final class LoginThrottle
{
    public const MAX_INTENTOS = 5;
    public const BLOQUEO_SEG  = 15 * 60;

    private const KEY = '_login_throttle';

    /** Segundos que faltan para desbloquear (0 = no bloqueado). */
    public static function segundosRestantes(): int
    {
        $hasta = $_SESSION[self::KEY]['bloqueado_hasta'] ?? 0;
        $resto = $hasta - time();
        if ($hasta && $resto <= 0) {
            self::reiniciar();
            return 0;
        }
        return max(0, $resto);
    }

    /** Registra un fallo y devuelve cuántos intentos quedan antes del bloqueo. */
    public static function registrarFallo(): int
    {
        $fallos = ($_SESSION[self::KEY]['fallos'] ?? 0) + 1;
        $_SESSION[self::KEY]['fallos'] = $fallos;

        if ($fallos >= self::MAX_INTENTOS) {
            $_SESSION[self::KEY]['bloqueado_hasta'] = time() + self::BLOQUEO_SEG;
            return 0;
        }
        return self::MAX_INTENTOS - $fallos;
    }

    public static function reiniciar(): void
    {
        unset($_SESSION[self::KEY]);
    }
}
