<?php
/**
 * Convierte un equipo (con sus mantenimientos y especificaciones) en los datos
 * que dibuja la etiqueta patrimonial de la vista docente/inicio.
 */
final class EtiquetaEquipo
{
    private const DIAS_AVISO_GARANTIA = 60;

    /**
     * @param array $equipo          fila de Equipo::asignadosA()
     * @param array $mantenimientos  filas de Mantenimiento::deEquipos() de este equipo (recientes primero)
     * @param array $specs           filas de Equipo::especificaciones() de este equipo
     */
    public static function desde(array $equipo, array $mantenimientos, array $specs): array
    {
        $hoy     = new DateTimeImmutable('today');
        $abierto = self::primero($mantenimientos, fn ($m) => in_array($m['estado'], ['programado', 'en_proceso'], true));
        $ultimo  = self::primero($mantenimientos, fn ($m) => $m['estado'] === 'finalizado');

        [$estadoTexto, $estadoClase] = Formato::ESTADOS[$equipo['estado_operativo']];

        return [
            'id'          => (int) $equipo['id'],
            'dom_id'      => 'eq-' . $equipo['codigo'],
            'codigo'      => $equipo['codigo'],
            'patrimonial' => $equipo['codigo_patrimonial'],
            'qr_url'      => View::absoluteUrl('/q/' . $equipo['qr_token']),
            'nombre'      => $equipo['nombre'],
            'modelo'      => self::lineaModelo($equipo),
            'estado'      => $equipo['estado_operativo'],
            'buscar'      => implode(' ', array_filter([
                $equipo['codigo'], $equipo['codigo_patrimonial'], $equipo['numero_serie'],
                $equipo['marca'], $equipo['modelo'], $equipo['nombre'], $equipo['categoria'],
            ])),
            'meta'        => self::meta($equipo, $abierto, $ultimo, $hoy),
            'sellos'      => self::sellos($equipo, $estadoTexto, $estadoClase),
            'ficha'       => self::ficha($equipo, $abierto, $ultimo, $specs, $hoy),
        ];
    }

    private static function lineaModelo(array $e): string
    {
        $modelo = trim(($e['marca'] ?? '') . ' ' . ($e['modelo'] ?? ''));
        $serie  = $e['numero_serie'] ? 'Serie ' . $e['numero_serie'] : '';
        return implode(' · ', array_filter([$modelo, $serie]));
    }

    /** Líneas con icono bajo el nombre: ubicación y lo más relevante de su mantenimiento. */
    private static function meta(array $e, ?array $abierto, ?array $ultimo, DateTimeImmutable $hoy): array
    {
        $meta = [];

        if ($e['ubicacion']) {
            $lugar = implode(', ', array_filter([$e['edificio'], $e['piso'] ? 'piso ' . $e['piso'] : null]));
            $meta[] = ['icono' => 'pin', 'texto' => $e['ubicacion'] . ($lugar ? " · $lugar" : ''), 'alerta' => false];
        }

        if ($abierto) {
            $tipo = Formato::TIPOS_MANTENIMIENTO[$abierto['tipo']];
            if ($abierto['estado'] === 'en_proceso') {
                $detalle = $abierto['diagnostico'] ?: $abierto['problema_reportado'];
                $meta[]  = ['icono' => 'wrench', 'alerta' => false,
                            'texto' => ucfirst($tipo) . ' en proceso' . ($detalle ? ': ' . Formato::resumen($detalle, 60) : '')];
            } elseif ($abierto['problema_reportado']) {
                $meta[] = ['icono' => 'alert', 'alerta' => true,
                           'texto' => 'Reportado: ' . Formato::resumen($abierto['problema_reportado'], 60)];
            } elseif ($abierto['fecha_programada']) {
                $meta[] = ['icono' => 'calendar', 'alerta' => false,
                           'texto' => 'Próximo mantenimiento: ' . Formato::fecha($abierto['fecha_programada'])];
            }
        } elseif ($ultimo && $ultimo['proximo_mantenimiento'] && new DateTimeImmutable($ultimo['proximo_mantenimiento']) >= $hoy) {
            $meta[] = ['icono' => 'calendar', 'alerta' => false,
                       'texto' => 'Próximo mantenimiento: ' . Formato::fecha($ultimo['proximo_mantenimiento'])];
        }

        if ($e['fecha_fin_garantia']) {
            $fin = new DateTimeImmutable($e['fecha_fin_garantia']);
            if ($fin >= $hoy && $fin <= $hoy->modify('+' . self::DIAS_AVISO_GARANTIA . ' days')) {
                $meta[] = ['icono' => 'shield', 'alerta' => false,
                           'texto' => 'Garantía hasta: ' . Formato::fecha($e['fecha_fin_garantia'])];
            }
        }

        return $meta;
    }

    private static function sellos(array $e, string $texto, string $clase): array
    {
        $sellos = [['texto' => $texto, 'clase' => $clase]];
        if ($e['estado_inventario'] === 'en_bodega') {
            [$t, $c] = Formato::ESTADOS['en_bodega'];
            $sellos[] = ['texto' => $t, 'clase' => $c];
        }
        return $sellos;
    }

    /** Pares rótulo/valor de la ficha desplegable. */
    private static function ficha(array $e, ?array $abierto, ?array $ultimo, array $specs, DateTimeImmutable $hoy): array
    {
        $ficha = [['Categoría', $e['categoria']]];

        foreach ($specs as $s) {
            $ficha[] = [$s['atributo'], $s['valor']];
        }

        if ($abierto) {
            $tipo  = ucfirst(Formato::TIPOS_MANTENIMIENTO[$abierto['tipo']]);
            $valor = $abierto['estado'] === 'en_proceso'
                ? "$tipo · en proceso" . ($abierto['fecha_inicio'] ? ' desde ' . Formato::fechaHora($abierto['fecha_inicio']) : '')
                : "$tipo · programado" . ($abierto['fecha_programada'] ? ' para el ' . Formato::fecha($abierto['fecha_programada']) : '');
            $ficha[] = ['Mantenimiento', $valor];
            if ($abierto['tecnico']) {
                $ficha[] = ['Técnico', $abierto['tecnico']];
            }
        }

        if ($ultimo) {
            $fecha   = $ultimo['fecha_fin'] ?: $ultimo['fecha_inicio'] ?: $ultimo['creado_en'];
            $ficha[] = ['Último mantenimiento', Formato::fecha($fecha) . ' · ' . Formato::TIPOS_MANTENIMIENTO[$ultimo['tipo']]];
        }

        if ($e['area_propietaria']) {
            $ficha[] = ['Área propietaria', $e['area_propietaria']];
        }
        if (isset(Formato::ORIGENES[$e['origen']])) {
            $anio    = $e['fecha_adquisicion'] ? ' · ' . substr($e['fecha_adquisicion'], 0, 4) : '';
            $ficha[] = ['Origen', Formato::ORIGENES[$e['origen']] . $anio];
        }

        $ficha[] = ['Asignado desde', Formato::fecha($e['fecha_entrega'])];
        if ($e['estado_entrega']) {
            $ficha[] = ['Estado de entrega', $e['estado_entrega']];
        }

        if ($e['fecha_fin_garantia']) {
            $vencida = new DateTimeImmutable($e['fecha_fin_garantia']) < $hoy;
            $ficha[] = ['Garantía hasta', Formato::fecha($e['fecha_fin_garantia']) . ($vencida ? ' (vencida)' : '')];
        }

        return $ficha;
    }

    private static function primero(array $filas, callable $condicion): ?array
    {
        foreach ($filas as $f) {
            if ($condicion($f)) {
                return $f;
            }
        }
        return null;
    }
}
