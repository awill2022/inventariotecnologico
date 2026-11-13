<?php
/**
 * Visor de auditoría (Sprint 7): solo administrador.
 * Filtros por usuario, entidad (y registro), acción y fechas. Los inicios de sesión
 * no se listan salvo que se elija la acción «Inicios de sesión».
 */
final class AuditoriaController extends Controller
{
    public const VER = ['administrador'];

    private const POR_PAGINA = 25;

    /** GET /auditoria */
    public function index(): void
    {
        $modelo    = new Auditoria();
        $entidades = $modelo->entidades();
        $autores   = $modelo->autores();

        $id    = fn (string $k) => is_string($_GET[$k] ?? null) && ctype_digit($_GET[$k]) ? (int) $_GET[$k] : null;
        $fecha = function (string $k): ?string {
            $v = $_GET[$k] ?? null;
            $d = is_string($v) ? DateTimeImmutable::createFromFormat('!Y-m-d', $v) : false;
            return $d && $d->format('Y-m-d') === $v ? $v : null;
        };
        $entidad = is_string($_GET['entidad'] ?? null) && in_array($_GET['entidad'], $entidades, true) ? $_GET['entidad'] : '';

        $filtros = [
            'usuario'  => isset($autores[$id('usuario') ?? 0]) ? $id('usuario') : null,
            'entidad'  => $entidad,
            'registro' => $entidad !== '' ? $id('registro') : null,
            'accion'   => in_array($_GET['accion'] ?? '', Auditoria::ACCIONES, true) ? $_GET['accion'] : '',
            'desde'    => $fecha('desde'),
            'hasta'    => $fecha('hasta'),
        ];
        // Un rango al revés se corrige en vez de no mostrar nada
        if ($filtros['desde'] && $filtros['hasta'] && $filtros['desde'] > $filtros['hasta']) {
            [$filtros['desde'], $filtros['hasta']] = [$filtros['hasta'], $filtros['desde']];
        }

        $resultado = $modelo->listar($filtros, max(1, (int) ($_GET['pagina'] ?? 1)), self::POR_PAGINA);
        $refs      = $modelo->referencias($resultado['filas']);
        $eventos   = array_map(fn ($r) => EventoAuditoria::desde($r, $refs) + ['usuario_id' => $r['usuario_id']], $resultado['filas']);

        // Nombre del registro filtrado (cuando se llega desde una ficha)
        $registro = null;
        if ($filtros['registro'] !== null) {
            $ref      = $modelo->referencias([['entidad' => $entidad, 'entidad_id' => $filtros['registro']]]);
            $registro = $ref["$entidad:{$filtros['registro']}"] ?? ['texto' => 'n.º ' . $filtros['registro'], 'ruta' => null];
        }

        $opcionesEntidad = [];
        foreach ($entidades as $e) {
            $opcionesEntidad[$e] = EventoAuditoria::ENTIDADES[$e][1] ?? $e;
        }
        asort($opcionesEntidad);

        $this->view('auditoria/lista', [
            'titulo'    => 'Auditoría',
            'filtros'   => $filtros,
            'resultado' => $resultado,
            'eventos'   => $eventos,
            'registro'  => $registro,
            'autores'   => $autores,
            'entidades' => $opcionesEntidad,
            'acciones'  => array_map(fn ($a) => $a[1], EventoAuditoria::ACCIONES),
            'estilos'   => ['equipos.css', 'auditoria.css'],
        ], 'panel');
    }
}
