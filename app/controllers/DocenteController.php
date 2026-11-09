<?php
/**
 * Página principal del docente (rol: consulta).
 * Solo lectura: agenda, equipos a su cargo y equipos que ya no están a su cargo.
 */
final class DocenteController extends Controller
{
    /** GET /docente */
    public function inicio(): void
    {
        // TODO: cargar datos desde Equipo, Asignacion y Mantenimiento con Auth::user()['persona_id'].
        $this->view('docente/inicio', [
            'titulo'  => 'Mis equipos',
            'usuario' => Auth::user(),
        ]);
    }
}
