<?php
/**
 * Convierte una fila de `auditoria` en texto legible para el visor y la actividad reciente:
 * «Editó el equipo EQ-LAB-0142», con los cambios como «Campo: antes → después».
 */
final class EventoAuditoria
{
    /** entidad => [singular con artículo, nombre del filtro] */
    public const ENTIDADES = [
        'equipos'                 => ['el equipo', 'Equipos'],
        'componentes'             => ['el componente', 'Componentes'],
        'asignaciones'            => ['la entrega', 'Entregas y devoluciones'],
        'movimientos'             => ['el traslado', 'Movimientos'],
        'mantenimientos'          => ['el mantenimiento', 'Mantenimientos'],
        'mantenimiento_repuestos' => ['el repuesto usado', 'Repuestos usados'],
        'repuestos'               => ['el repuesto', 'Repuestos'],
        'archivos'                => ['el archivo', 'Archivos'],
        'personas'                => ['la persona', 'Personas'],
        'usuarios'                => ['el usuario', 'Usuarios'],
        'categorias'              => ['la categoría', 'Categorías'],
        'sedes'                   => ['la sede', 'Sedes'],
        'areas'                   => ['el área', 'Áreas'],
        'ubicaciones'             => ['la ubicación', 'Ubicaciones'],
    ];

    /** accion => [verbo, nombre del filtro, clase de sello, texto del sello] */
    public const ACCIONES = [
        'crear'   => ['Registró', 'Altas', 'ok', 'Alta'],
        'editar'  => ['Modificó', 'Cambios', 'maint', 'Cambio'],
        'cerrar'  => ['Cerró', 'Cierres', 'muted', 'Cierre'],
        'anular'  => ['Anuló', 'Anulaciones y bajas', 'bad', 'Anulación'],
        'acceder' => ['Inició sesión', 'Inicios de sesión', 'muted', 'Acceso'],
    ];

    /** Verbo más preciso para algunas acciones. */
    private const VERBOS = [
        'asignaciones:crear'   => 'Entregó',
        'asignaciones:cerrar'  => 'Recibió la devolución de',
        'equipos:cerrar'       => 'Dio de baja',
        'equipos:anular'       => 'Revirtió la baja de',
        'componentes:cerrar'   => 'Retiró',
        'mantenimientos:cerrar'=> 'Finalizó',
        'mantenimientos:anular'=> 'Canceló',
        'mantenimiento_repuestos:anular' => 'Quitó',
        'archivos:crear'       => 'Subió',
        'archivos:anular'      => 'Eliminó',
    ];

    /** Nombres de campo que no salen bien con solo cambiar "_" por espacio. */
    private const CAMPOS = [
        'identificacion'       => 'Identificación',
        'codigo_patrimonial'   => 'Código patrimonial',
        'numero_serie'         => 'Número de serie',
        'fecha_adquisicion'    => 'Fecha de adquisición',
        'valor_adquisicion'    => 'Valor de adquisición',
        'fecha_fin_garantia'   => 'Fin de garantía',
        'area_propietaria_id'  => 'Área propietaria',
        'ubicacion_entrega_id' => 'Ubicación de entrega',
        'ubicacion_actual_id'  => 'Ubicación',
        'tecnico_id'           => 'Técnico',
        'debe_cambiar_clave'   => 'Debe cambiar la clave',
        'stock_minimo'         => 'Stock mínimo',
        'costo_referencial'    => 'Costo referencial',
        'costo_mano_obra'      => 'Costo de mano de obra',
        'proximo_mantenimiento'=> 'Próximo mantenimiento',
        'trabajo_realizado'    => 'Trabajo realizado',
        'diagnostico'          => 'Diagnóstico',
        'descripcion'          => 'Descripción',
        'direccion'            => 'Dirección',
        'telefono'             => 'Teléfono',
        'origen'               => 'Origen',
        'destino'              => 'Destino',
        'user_agent'           => 'Navegador',
        'ip'                   => 'Dirección IP',
    ];

    /** Claves del detalle que se muestran aparte, como datos técnicos. */
    private const TECNICOS = ['ip', 'user_agent'];

    /**
     * @param array $r    fila de Auditoria::listar() / recientes()
     * @param array $refs Auditoria::referencias()
     * @return array{titulo: string, frase: string, nombre: string, ruta: ?string, autor: string,
     *               accion: string, clase: string, fecha: string, fecha_iso: string,
     *               lineas: array<array{0: string, 1: string}>, tecnicos: array<array{0: string, 1: string}>}
     */
    public static function desde(array $r, array $refs): array
    {
        $clave   = "{$r['entidad']}:{$r['entidad_id']}";
        $ref     = $refs[$clave] ?? null;
        $articulo = self::ENTIDADES[$r['entidad']][0] ?? $r['entidad'];
        $verbo   = self::VERBOS["{$r['entidad']}:{$r['accion']}"] ?? self::ACCIONES[$r['accion']][0];
        $detalle = json_decode((string) $r['detalle'], true);
        $detalle = is_array($detalle) ? $detalle : [];

        // El docente no «registra un mantenimiento»: reporta una falla (Sprint 0 / Sprint 6)
        if ($r['entidad'] === 'mantenimientos' && $r['accion'] === 'crear' && ($detalle['origen'] ?? '') === 'reporte_docente') {
            [$verbo, $articulo] = ['Reportó', 'la falla'];
        }

        if ($r['accion'] === 'acceder') {
            $frase  = $verbo;
            $nombre = '';
            $ruta   = null;
        } else {
            // «Dio de baja el equipo» · «Revirtió la baja del equipo» (de + el = del)
            $frase  = str_replace(' de el ', ' del ', "$verbo $articulo");
            // Sin referencia: el registro ya no existe (p. ej. un repuesto quitado de un trabajo)
            $nombre = $ref['texto'] ?? 'n.º ' . $r['entidad_id'];
            $ruta   = $ref['ruta'] ?? null;
        }

        [$lineas, $tecnicos] = self::lineas($detalle);

        return [
            'titulo'    => trim("$frase $nombre"),
            'frase'     => $frase,
            'nombre'    => $nombre,
            'ruta'      => $ruta,
            'autor'     => $r['autor'] ?: ($r['usuario'] ?? 'Sistema'),
            'accion'    => self::ACCIONES[$r['accion']][3] ?? $r['accion'],
            'clase'     => self::ACCIONES[$r['accion']][2] ?? 'muted',
            'fecha'     => Formato::relativa($r['fecha']),
            'fecha_iso' => str_replace(' ', 'T', $r['fecha']),
            'lineas'    => $lineas,
            'tecnicos'  => $tecnicos,
        ];
    }

    // ------------------------------------------------------------------

    /** Valores de enum que se muestran con su nombre (estados, tipos y orígenes). */
    private const VALORES = [
        'operativo' => 'Operativo', 'con_fallas' => 'Con fallas', 'en_mantenimiento' => 'En mantenimiento',
        'inoperativo' => 'Inoperativo', 'en_bodega' => 'En bodega', 'de_baja' => 'De baja', 'prestado' => 'Prestado',
        'programado' => 'Programado', 'en_proceso' => 'En proceso', 'finalizado' => 'Finalizado', 'cancelado' => 'Cancelado',
        'preventivo' => 'Preventivo', 'correctivo' => 'Correctivo', 'diagnostico' => 'Diagnóstico', 'actualizacion' => 'Actualización',
        'reporte_docente' => 'Reporte del docente', 'administrador' => 'Administrador', 'tecnico' => 'Técnico',
    ];

    /**
     * Detalle en líneas «Campo: valor» o «Campo: antes → después». Los bloques con nombre
     * (cambios, datos, baja...) se abren en sus campos; en los datos de un alta se omiten los vacíos.
     * @return array{0: array, 1: array} [líneas, datos técnicos]
     */
    private static function lineas(array $detalle): array
    {
        $lineas   = [];
        $tecnicos = [];

        foreach ($detalle as $clave => $valor) {
            $clave = (string) $clave;
            if (in_array($clave, self::TECNICOS, true)) {
                if ($valor !== null && $valor !== '') {
                    $tecnicos[] = [self::campo($clave), (string) $valor];
                }
                continue;
            }
            if (is_array($valor) && $valor && !array_is_list($valor)) {
                foreach ($valor as $campo => $v) {
                    if ($clave !== 'cambios' && ($v === null || $v === '')) {
                        continue;
                    }
                    $lineas[] = [self::campo((string) $campo), self::valor((string) $campo, $v)];
                }
                continue;
            }
            $lineas[] = [self::campo($clave), self::valor($clave, $valor)];
        }
        return [$lineas, $tecnicos];
    }

    private static function campo(string $clave): string
    {
        if (isset(self::CAMPOS[$clave])) {
            return self::CAMPOS[$clave];
        }
        $texto = str_ends_with($clave, '_id') ? substr($clave, 0, -3) . ' (n.º)' : $clave;
        return ucfirst(str_replace('_', ' ', $texto));
    }

    /** Un par [antes, después] se muestra como «antes → después». */
    private static function valor(string $campo, mixed $v): string
    {
        if (is_array($v) && array_is_list($v) && count($v) === 2 && !is_array($v[0]) && !is_array($v[1])) {
            return self::simple($campo, $v[0]) . ' → ' . self::simple($campo, $v[1]);
        }
        if (is_array($v)) {
            return Formato::resumen(json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 200);
        }
        return self::simple($campo, $v);
    }

    private static function simple(string $campo, mixed $v): string
    {
        if ($v === null || $v === '') {
            return '—';
        }
        if (is_bool($v) || (in_array($campo, ['activo', 'debe_cambiar_clave'], true) && in_array($v, [0, 1, '0', '1'], true))) {
            return $v ? 'Sí' : 'No';
        }
        if (is_string($v) && isset(self::VALORES[$v])) {
            return self::VALORES[$v];
        }
        if ($v === 'activo' && $campo === 'estado_inventario') {
            return 'En uso';
        }
        if (is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $v)) {
            return strlen($v) === 10 ? Formato::fecha($v) : Formato::fechaHora($v);
        }
        return Formato::resumen((string) $v, 200);
    }
}
