<?php
/**
 * Valida y limpia los datos de un formulario a partir de la definición de sus campos.
 *
 * Cada campo es un arreglo:
 *   nombre    => nombre del input y de la columna
 *   etiqueta  => texto del <label>
 *   tipo      => 'text' | 'textarea' | 'select' | 'password' | 'fijo'
 *                ('fijo' solo se muestra: no se lee del formulario ni se guarda)
 *   requerido => bool (opcional)
 *   min, max  => largo permitido en caracteres (opcional, para text/textarea/password)
 *   opciones  => [valor => texto] (obligatorio en select)
 *   entero    => bool: convierte el valor elegido a entero (select de llaves foráneas)
 *   formato   => 'cedula' | 'correo' | 'telefono' | 'usuario' (opcional, para text)
 *   politica  => bool: aplica la política de claves (password)
 *   igual_a   => nombre de otro campo password que debe coincidir (confirmación)
 */
final class Validador
{
    /** Mensaje de error de cada formato. */
    private const MENSAJES_FORMATO = [
        'cedula'   => 'Escribe una cédula ecuatoriana válida de 10 dígitos.',
        'correo'   => 'Escribe un correo válido, por ejemplo nombre@dominio.com.',
        'telefono' => 'Escribe solo números (7 a 15), con + al inicio si es internacional.',
        'usuario'  => 'Usa solo letras sin tildes, números y . _ - @ (sin espacios).',
    ];

    /**
     * @return array{0: array, 1: array} [datos limpios, errores por campo]
     */
    public static function validar(array $campos, array $entrada): array
    {
        $limpio  = [];
        $errores = [];

        foreach ($campos as $campo) {
            $nombre = $campo['nombre'];
            $tipo   = $campo['tipo'];
            if ($tipo === 'fijo') {
                continue;
            }

            $crudo = $entrada[$nombre] ?? '';
            // mb_scrub descarta bytes que no son UTF-8 válido (romperían el JSON de auditoría)
            $valor = is_string($crudo) ? mb_scrub($crudo, 'UTF-8') : '';

            if ($tipo === 'password') {
                // La clave se guarda tal cual: los espacios también cuentan
                [$limpio[$nombre], $error] = self::clave($campo, $valor, $entrada);
                if ($error !== null) {
                    $errores[$nombre] = $error;
                }
                continue;
            }

            $valor = trim($valor);
            if ($tipo !== 'textarea') {
                $valor = preg_replace('/\s+/u', ' ', $valor);
            }

            if ($valor === '') {
                $limpio[$nombre] = null;
                if (!empty($campo['requerido'])) {
                    $errores[$nombre] = $tipo === 'select' ? 'Elige una opción.' : 'Este campo es obligatorio.';
                }
                continue;
            }

            if ($tipo === 'select') {
                if (!array_key_exists($valor, $campo['opciones'])) {
                    $limpio[$nombre]  = null;
                    $errores[$nombre] = 'Elige una opción de la lista.';
                    continue;
                }
                $limpio[$nombre] = !empty($campo['entero']) ? (int) $valor : $valor;
                continue;
            }

            if (isset($campo['formato'])) {
                $valor = self::normalizar($campo['formato'], $valor);
            }
            $limpio[$nombre] = $valor;

            $largo = mb_strlen($valor);
            if (isset($campo['min']) && $largo < $campo['min']) {
                $errores[$nombre] = "Escribe al menos {$campo['min']} caracteres.";
            } elseif (isset($campo['max']) && $largo > $campo['max']) {
                $errores[$nombre] = "No puede pasar de {$campo['max']} caracteres.";
            } elseif (isset($campo['formato']) && !self::cumpleFormato($campo['formato'], $valor)) {
                $errores[$nombre] = self::MENSAJES_FORMATO[$campo['formato']];
            }
        }

        return [$limpio, $errores];
    }

    /** Cédula ecuatoriana: 10 dígitos, provincia 01–24 o 30, tercer dígito < 6 y dígito verificador (módulo 10). */
    public static function esCedula(string $cedula): bool
    {
        if (!preg_match('/^\d{10}$/', $cedula)) {
            return false;
        }
        $provincia = (int) substr($cedula, 0, 2);
        if (!(($provincia >= 1 && $provincia <= 24) || $provincia === 30) || (int) $cedula[2] >= 6) {
            return false;
        }

        $suma = 0;
        for ($i = 0; $i < 9; $i++) {
            $producto = (int) $cedula[$i] * ($i % 2 === 0 ? 2 : 1);
            $suma    += $producto > 9 ? $producto - 9 : $producto;
        }
        return (10 - $suma % 10) % 10 === (int) $cedula[9];
    }

    /** Ajustes antes de validar: sin espacios en cédula y teléfono, correo en minúsculas. */
    private static function normalizar(string $formato, string $valor): string
    {
        return match ($formato) {
            'cedula', 'telefono' => preg_replace('/[\s-]/', '', $valor),
            'correo'             => mb_strtolower($valor),
            default              => $valor,
        };
    }

    private static function cumpleFormato(string $formato, string $valor): bool
    {
        return match ($formato) {
            'cedula'   => self::esCedula($valor),
            'correo'   => filter_var($valor, FILTER_VALIDATE_EMAIL) !== false,
            'telefono' => (bool) preg_match('/^\+?\d{7,15}$/', $valor),
            'usuario'  => (bool) preg_match('/^[A-Za-z0-9._@-]+$/', $valor),
            default    => true,
        };
    }

    /** @return array{0: ?string, 1: ?string} [valor, error] */
    private static function clave(array $campo, string $valor, array $entrada): array
    {
        if ($valor === '') {
            return [null, !empty($campo['requerido']) ? 'Este campo es obligatorio.' : null];
        }
        if (isset($campo['igual_a'])) {
            $otra = $entrada[$campo['igual_a']] ?? '';
            return [$valor, is_string($otra) && hash_equals($otra, $valor) ? null : 'Las claves no coinciden.'];
        }
        if (!empty($campo['politica'])) {
            $usuario = isset($entrada['usuario']) && is_string($entrada['usuario']) ? trim($entrada['usuario']) : null;
            return [$valor, Clave::problema($valor, $usuario)];
        }
        if (isset($campo['max']) && strlen($valor) > $campo['max']) {
            return [$valor, "No puede pasar de {$campo['max']} caracteres."];
        }
        return [$valor, null];
    }
}
