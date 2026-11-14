<?php
/**
 * Inicio del panel.
 * - Administrador e inventario: dashboard (indicadores, equipos por estado, agenda de mantenimientos
 *   y, solo el administrador, actividad reciente) y debajo los accesos a los módulos.
 * - Técnico: solo da soporte, así que lo primero que ve son los reportes de falla por atender
 *   y sus trabajos abiertos.
 */
final class PanelController extends Controller
{
    /** Días hacia adelante que cubre la agenda de mantenimientos del dashboard. */
    private const DIAS_AGENDA = 30;
    /** Acciones que muestra la actividad reciente. */
    private const ACTIVIDAD = 6;

    /** GET /panel */
    public function inicio(): void
    {
        if (Auth::rol() === 'tecnico') {
            $this->inicioTecnico();
            return;
        }

        $ahora         = new DateTimeImmutable();
        $mantenimiento = new Mantenimiento();
        $resumen       = $mantenimiento->resumen(Auth::user()['persona_id']);
        $bajos         = (new Repuesto())->contarBajoMinimo();
        $esAdmin       = Auth::rol() === 'administrador';

        // Actividad reciente: solo el administrador (es quien ve la auditoría)
        $actividad = [];
        if ($esAdmin) {
            $auditoria = new Auditoria();
            $filas     = $auditoria->recientes(self::ACTIVIDAD);
            $refs      = $auditoria->referencias($filas);
            $actividad = array_map(fn ($r) => EventoAuditoria::desde($r, $refs), $filas);
        }

        $this->view('panel/inicio', [
            'titulo'      => 'Inicio',
            'usuario'     => Auth::user(),
            'saludo'      => Formato::saludo($ahora),
            'hoyIso'      => $ahora->format('Y-m-d'),
            'hoyTexto'    => Formato::fechaLarga($ahora),
            'puedeEditar' => $esAdmin,
            // Dashboard (Sprint 7)
            'resumen'     => $resumen,
            'bajos'       => $bajos,
            'conteos'     => (new Equipo())->conteosDashboard(),
            'agenda'      => $mantenimiento->agenda(self::DIAS_AGENDA, 6),
            'diasAgenda'  => self::DIAS_AGENDA,
            'actividad'   => $actividad,
            'verActividad'=> $esAdmin,
            'estilos'     => ['dashboard.css', 'auditoria.css'],
            'inventario'  => [
                ['texto' => 'Equipos', 'ruta' => '/equipos', 'icono' => 'laptop', 'unidad' => ['vigente', 'vigentes'],
                 'total' => (new Equipo())->contarVigentes(), 'nota' => 'Bienes tecnológicos, fichas y etiquetas QR'],
            ],
            'soporte'     => [
                ['texto' => 'Mantenimientos', 'ruta' => '/mantenimientos', 'icono' => 'wrench', 'unidad' => ['por atender', 'por atender'],
                 'total' => $resumen['por_atender'],
                 'nota' => Formato::plural($resumen['reportes'], 'reporte de falla', 'reportes de falla')
                         . ' · ' . $resumen['en_proceso'] . ' en proceso'
                         . ($resumen['vencidos'] ? ' · ' . Formato::plural($resumen['vencidos'], 'atrasado', 'atrasados') : '')],
                ['texto' => 'Repuestos', 'ruta' => '/repuestos', 'icono' => 'package', 'unidad' => ['por reponer', 'por reponer'],
                 'total' => $bajos, 'nota' => 'Stock de piezas e insumos'],
            ],
            'catalogos'   => [
                ['texto' => 'Categorías',  'ruta' => '/categorias',  'icono' => 'tag',
                 'total' => (new Categoria())->contarActivos(), 'nota' => 'Clases de equipo'],
                ['texto' => 'Sedes',       'ruta' => '/sedes',       'icono' => 'building',
                 'total' => (new Sede())->contarActivos(),      'nota' => 'Campus y locales'],
                ['texto' => 'Áreas',       'ruta' => '/areas',       'icono' => 'network',
                 'total' => (new Area())->contarActivos(),      'nota' => 'Departamentos, carreras y unidades'],
                ['texto' => 'Ubicaciones', 'ruta' => '/ubicaciones', 'icono' => 'pin',
                 'total' => (new Ubicacion())->contarActivos(), 'nota' => 'Aulas, laboratorios y bodegas'],
            ],
        ], 'panel');
    }

    /** Inicio del técnico: reportes de falla por atender y sus trabajos abiertos. */
    private function inicioTecnico(): void
    {
        $ahora     = new DateTimeImmutable();
        $personaId = Auth::user()['persona_id'];
        $modelo    = new Mantenimiento();

        $this->view('panel/tecnico', [
            'titulo'    => 'Inicio',
            'usuario'   => Auth::user(),
            'saludo'    => Formato::saludo($ahora),
            'hoyIso'    => $ahora->format('Y-m-d'),
            'hoyTexto'  => Formato::fechaLarga($ahora),
            'reportes'  => $modelo->reportesPorAtender($personaId),
            'trabajos'  => $personaId ? $modelo->abiertosDe($personaId) : [],
            'resumen'   => $modelo->resumen($personaId),
            'sinPersona' => !$personaId,
            'csrf'      => Session::csrfToken(),
            'estilos'   => ['equipos.css', 'asignaciones.css', 'mantenimientos.css'],
        ], 'panel');
    }
}
