<?php
/**
 * Política de claves del sistema: la misma regla para crear cuentas,
 * restablecer claves y cambiar la clave propia.
 */
final class Clave
{
    public const MIN = 8;
    /** bcrypt solo usa los primeros 72 bytes; más allá se ignorarían en silencio. */
    public const MAX = 72;

    /** Texto de ayuda para mostrar junto al campo. */
    public const AYUDA = 'Mínimo 8 caracteres, con al menos una letra y un número.';

    /** Devuelve el problema de la clave o null si cumple la política. */
    public static function problema(string $clave, ?string $usuario = null): ?string
    {
        if (mb_strlen($clave) < self::MIN) {
            return 'La clave debe tener al menos ' . self::MIN . ' caracteres.';
        }
        if (strlen($clave) > self::MAX) {
            return 'La clave no puede pasar de ' . self::MAX . ' caracteres.';
        }
        if (!preg_match('/\pL/u', $clave) || !preg_match('/\d/', $clave)) {
            return 'Incluye al menos una letra y un número.';
        }
        if ($usuario !== null && mb_strtolower($clave) === mb_strtolower($usuario)) {
            return 'La clave no puede ser igual al nombre de usuario.';
        }
        return null;
    }

    public static function hash(string $clave): string
    {
        return password_hash($clave, PASSWORD_DEFAULT);
    }
}
