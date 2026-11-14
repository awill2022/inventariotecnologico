<?php
/**
 * Inicio del panel para administrador, inventario y técnico.
 * Por ahora da acceso a los catálogos; los indicadores llegan con el dashboard.
 */
final class PanelController extends Controller
{
    /** GET /panel */
    public function inicio(): void
    {
        $ahora = new DateTimeImmutable();

        $this->view('panel/inicio', [
            'titulo'      => 'Inicio',
            'usuario'     => Auth::user(),
            'saludo'      => Formato::saludo($ahora),
            'hoyIso'      => $ahora->format('Y-m-d'),
            'hoyTexto'    => Formato::fechaLarga($ahora),
            'puedeEditar' => Auth::rol() === 'administrador',
            'catalogos'   => [
                ['texto' => 'Categorías',  'ruta' => '/categorias',  'icono' => 'tag',
                 'total' => (new Categoria())->contarActivos(), 'nota' => 'Clases de equipo'],
                ['texto' => 'Sedes',       'ruta' => '/sedes',       'icono' => 'building',
                 'total' => (new Sede())->contarActivos(),      'nota' => 'Campus y locales'],
                ['texto' => 'Áreas',       'ruta' => '/areas',       'icono' => 'network',
                 'total' => (new Area())->contarActivos(),      'nota' => 'Departamentos, carreras y unidades'],
                ['texto' => 'Ubicaciones', 'ruta' => '/ubicaciones', 'icono' => 'pin',
                 'total' => (new Ubicacion())->contarActivos(), 'nota' => 'Aulas, laboratorios y bodegas'],
                 ['texto' => 'Soporte y QR', 'ruta' => '/tecnico', 'icono' => 'wrench',
                 'total' => (new Mantenimiento())->resumenConteos()['pendientes'], 'nota' => 'Reportes QR y fallas pendientes'],
            ],
        ], 'panel');
    }
}
