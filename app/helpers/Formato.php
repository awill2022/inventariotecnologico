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
}
