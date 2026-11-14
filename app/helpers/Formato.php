<?php
/**
 * Formato de fechas en español y vocabulario de estados del esquema.
 */
final class Formato
{
    private const MESES_CORTOS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    private const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
                           'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    private const DIAS  = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

    /** Etiqueta y clase de sello para cada estado_operativo / estado_inventario. */
    public const ESTADOS = [
        'operativo'        => ['Operativo', 'ok'],
        'con_fallas'       => ['Con fallas', 'warn'],
        'en_mantenimiento' => ['En mantenimiento', 'maint'],
        'inoperativo'      => ['Inoperativo', 'bad'],
        'en_bodega'        => ['En bodega', 'muted'],
        'de_baja'          => ['De baja', 'bad'],
    ];

    public const TIPOS_MANTENIMIENTO = [
        'preventivo'    => 'preventivo',
        'correctivo'    => 'correctivo',
        'diagnostico'   => 'diagnóstico',
        'actualizacion' => 'actualización',
    ];

    public const ORIGENES = [
        'compra' => 'Compra', 'donacion' => 'Donación', 'traspaso' => 'Traspaso', 'otro' => 'Otro',
    ];

    /**
     * Sellos de estado de un equipo para listados y fichas: el operativo y, si no está en uso,
     * el de inventario. Un equipo de baja solo muestra "De baja".
     * @return array<array{texto: string, clase: string}>
     */
    public static function sellosEquipo(array $e): array
    {
        if ($e['estado_inventario'] === 'de_baja') {
            return [['texto' => self::ESTADOS['de_baja'][0], 'clase' => self::ESTADOS['de_baja'][1]]];
        }
        [$texto, $clase] = self::ESTADOS[$e['estado_operativo']];
        $sellos = [['texto' => $texto, 'clase' => $clase]];
        if ($e['estado_inventario'] === 'en_bodega') {
            $sellos[] = ['texto' => self::ESTADOS['en_bodega'][0], 'clase' => self::ESTADOS['en_bodega'][1]];
        }
        return $sellos;
    }

    /** "$ 1.250,50" (formato de Ecuador: dólares, punto de miles y coma decimal). */
    public static function dinero(?string $valor): string
    {
        return $valor === null || $valor === '' ? '' : '$ ' . number_format((float) $valor, 2, ',', '.');
    }

    /** Cantidad sin ceros de sobra: "5", "2,5", "0,25". */
    public static function cantidad(?string $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }
        return rtrim(rtrim(number_format((float) $valor, 2, ',', '.'), '0'), ',');
    }

    /**
     * Cantidad con su unidad en plural cuando corresponde: "1 unidad", "2 unidades", "0,5 frascos".
     * Plural simple del español: vocal + s; d, r, l, n + es; el resto + s.
     */
    public static function conUnidad(?string $cantidad, string $unidad): string
    {
        $texto = self::cantidad($cantidad ?? '0');
        if ((float) $cantidad === 1.0 || $unidad === '') {
            return trim("$texto $unidad");
        }
        $plural = preg_match('/[aeiouáéíóú]$/iu', $unidad) ? $unidad . 's'
                : (preg_match('/[drln]$/i', $unidad) ? $unidad . 'es' : $unidad . 's');
        return "$texto $plural";
    }

    /** "12 nov 2026" */
    public static function fecha(?string $valor): string
    {
        if (!$valor) {
            return '';
        }
        $d = new DateTimeImmutable($valor);
        return $d->format('j') . ' ' . self::MESES_CORTOS[$d->format('n') - 1] . ' ' . $d->format('Y');
    }

    /** "5 nov 2026 · 14:10" */
    public static function fechaHora(?string $valor): string
    {
        if (!$valor) {
            return '';
        }
        return self::fecha($valor) . ' · ' . (new DateTimeImmutable($valor))->format('H:i');
    }

    /** "Domingo 8 de noviembre de 2026" */
    public static function fechaLarga(DateTimeInterface $d): string
    {
        return self::DIAS[(int) $d->format('w')] . ' ' . $d->format('j') . ' de '
             . self::MESES[$d->format('n') - 1] . ' de ' . $d->format('Y');
    }

    /** "Hoy, 14:10" · "Ayer, 09:00" · "4 nov, 09:00" · "4 nov 2025, 09:00" */
    public static function relativa(?string $valor): string
    {
        if (!$valor) {
            return '';
        }
        $d    = new DateTimeImmutable($valor);
        $hoy  = new DateTimeImmutable('today');
        $hora = $d->format('H:i');

        if ($d >= $hoy) {
            return "Hoy, $hora";
        }
        if ($d >= $hoy->modify('-1 day')) {
            return "Ayer, $hora";
        }
        $dia = $d->format('j') . ' ' . self::MESES_CORTOS[$d->format('n') - 1];
        if ($d->format('Y') !== $hoy->format('Y')) {
            $dia .= ' ' . $d->format('Y');
        }
        return "$dia, $hora";
    }

    /** "Buenos días" / "Buenas tardes" / "Buenas noches" según la hora local. */
    public static function saludo(?DateTimeInterface $d = null): string
    {
        $h = (int) ($d ?? new DateTimeImmutable())->format('G');
        return $h < 12 ? 'Buenos días' : ($h < 19 ? 'Buenas tardes' : 'Buenas noches');
    }

    /** Recorta un texto largo para una línea de resumen. */
    public static function resumen(?string $texto, int $max = 70): string
    {
        $texto = trim(preg_replace('/\s+/', ' ', (string) $texto));
        return mb_strimwidth($texto, 0, $max, '…');
    }

    /** "1 equipo" / "3 equipos" */
    public static function plural(int $n, string $singular, string $plural): string
    {
        return $n . ' ' . ($n === 1 ? $singular : $plural);
    }

    /** Tamaño de archivo legible: "820 KB", "2,4 MB". */
    public static function bytes(int $bytes): string
    {
        if ($bytes < 1024 * 1024) {
            return max(1, (int) round($bytes / 1024)) . ' KB';
        }
        return str_replace(',0', '', number_format($bytes / (1024 * 1024), 1, ',', '.')) . ' MB';
    }
}
