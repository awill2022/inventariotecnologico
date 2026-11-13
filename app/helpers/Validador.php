<?php
/**
 * Valida y limpia los datos de un formulario a partir de la definición de sus campos.
 *
 * Cada campo es un arreglo:
 *   nombre    => nombre del input y de la columna
 *   etiqueta  => texto del <label>
 *   tipo      => 'text' | 'textarea' | 'select' | 'password' | 'fecha' | 'fechahora' | 'numero' | 'fijo'
 *                ('fijo' solo se muestra: no se lee del formulario ni se guarda;
 *                 'fecha' espera AAAA-MM-DD; 'fechahora' espera AAAA-MM-DDTHH:MM (datetime-local)
 *                 y devuelve 'AAAA-MM-DD HH:MM:00'; 'numero' es un decimal con hasta 2 decimales)
 *   min_valor, max_valor => rango permitido (opcional): fechas 'AAAA-MM-DD', fecha y hora
 *                'AAAA-MM-DD HH:MM' o números
 *   requerido => bool (opcional)
 *   min, max  => largo permitido en caracteres (opcional, para text/textarea/password)
 *   opciones  => [valor => texto] (obligatorio en select)
 *   entero    => bool: convierte el valor elegido a entero (select de llaves foráneas)
 *   formato   => 'cedula' | 'correo' | 'telefono' | 'usuario' | 'prefijo' | 'codigo' (opcional, para text)
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
        'prefijo'  => 'Usa solo letras sin tildes y números (2 a 6), sin espacios.',
        'codigo'   => 'Usa solo letras sin tildes, números y guiones, sin espacios.',
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

            if ($tipo === 'fecha' || $tipo === 'fechahora' || $tipo === 'numero') {
                [$limpio[$nombre], $error] = match ($tipo) {
                    'fecha'     => self::fecha($campo, $valor),
                    'fechahora' => self::fechaHora($campo, $valor),
                    default     => self::numero($campo, $valor),
                };
                if ($error !== null) {
                    $errores[$nombre] = $error;
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
            'prefijo', 'codigo'  => strtoupper(str_replace(' ', '', $valor)),
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
            'prefijo'  => (bool) preg_match('/^[A-Z0-9]{2,6}$/', $valor),
            'codigo'   => (bool) preg_match('/^[A-Z0-9]+(-[A-Z0-9]+)*$/', $valor),
            default    => true,
        };
    }

    /** Fecha AAAA-MM-DD real (el input type=date la envía así). @return array{0: ?string, 1: ?string} */
    private static function fecha(array $campo, string $valor): array
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
        if (!$d || $d->format('Y-m-d') !== $valor) {
            return [null, 'Escribe una fecha válida.'];
        }
        if (isset($campo['min_valor']) && $valor < $campo['min_valor']) {
            return [$valor, 'La fecha no puede ser anterior al ' . Formato::fecha($campo['min_valor']) . '.'];
        }
        if (isset($campo['max_valor']) && $valor > $campo['max_valor']) {
            return [$valor, 'La fecha no puede ser posterior al ' . Formato::fecha($campo['max_valor']) . '.'];
        }
        return [$valor, null];
    }

    /**
     * Fecha y hora del input datetime-local (AAAA-MM-DDTHH:MM, a veces con segundos).
     * Devuelve 'AAAA-MM-DD HH:MM:00', listo para una columna DATETIME. @return array{0: ?string, 1: ?string}
     */
    private static function fechaHora(array $campo, string $valor): array
    {
        $valor = substr(str_replace(' ', 'T', $valor), 0, 16);
        $d = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $valor);
        if (!$d || $d->format('Y-m-d\TH:i') !== $valor) {
            return [null, 'Escribe una fecha y hora válidas.'];
        }
        $normal = $d->format('Y-m-d H:i');
        if (isset($campo['min_valor']) && $normal < substr($campo['min_valor'], 0, 16)) {
            return [$normal . ':00', 'No puede ser anterior al ' . Formato::fechaHora($campo['min_valor']) . '.'];
        }
        if (isset($campo['max_valor']) && $normal > substr($campo['max_valor'], 0, 16)) {
            return [$normal . ':00', 'No puede ser posterior al ' . Formato::fechaHora($campo['max_valor']) . '.'];
        }
        return [$normal . ':00', null];
    }

    /** Decimal positivo con hasta 2 decimales; admite coma decimal. @return array{0: ?string, 1: ?string} */
    private static function numero(array $campo, string $valor): array
    {
        $valor = str_replace([' ', ','], ['', '.'], $valor);
        if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $valor)) {
            return [null, 'Escribe un número sin signos, con hasta 2 decimales (por ejemplo 850.50).'];
        }
        if (isset($campo['max_valor']) && (float) $valor > $campo['max_valor']) {
            return [$valor, 'El valor es demasiado grande.'];
        }
        return [$valor, null];
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
