<?php
/**
 * Convierte una fila de Historial::deEquiposDe() en un evento de la línea de tiempo.
 */
final class EventoHistorial
{
    public static function desde(array $r): array
    {
        [$icono, $variante, $titulo, $detalle] = match ($r['clase']) {
            'asignacion'    => ['user-check', '', 'Equipo entregado a tu cargo', $r['detalle'] ?: $r['equipo']],
            'movimiento'    => self::movimiento($r),
            'mantenimiento' => self::mantenimiento($r),
        };

        return [
            'icono'     => $icono,
            'variante'  => $variante,
            'titulo'    => $titulo,
            'codigo'    => $r['codigo'],
            'detalle'   => Formato::resumen($detalle, 80),
            'fecha_iso' => (new DateTimeImmutable($r['fecha']))->format('Y-m-d\TH:i'),
            'fecha'     => Formato::relativa($r['fecha']),
        ];
    }

    private static function movimiento(array $r): array
    {
        $titulo  = $r['origen'] ? "Movido de {$r['origen']} a {$r['destino']}" : "Ubicado en {$r['destino']}";
        $detalle = $r['persona'] ? 'Autorizó: ' . $r['persona'] : ($r['detalle'] ?: $r['equipo']);
        return ['move', '', $titulo, $detalle];
    }

    private static function mantenimiento(array $r): array
    {
        [$tipo, $estado] = explode(':', $r['subtipo']);
        $nombre  = Formato::TIPOS_MANTENIMIENTO[$tipo];
        $detalle = $r['detalle'] ?: $r['equipo'];

        return match (true) {
            $estado === 'finalizado'  => ['check', '', "Mantenimiento $nombre finalizado", $detalle],
            $estado === 'en_proceso'  => ['wrench', '', "Mantenimiento $nombre iniciado", $detalle],
            $tipo === 'diagnostico'   => ['alert', 'warn', 'Falla reportada', $detalle],
            default                   => ['calendar', '', "Mantenimiento $nombre programado", $detalle],
        };
    }
}
