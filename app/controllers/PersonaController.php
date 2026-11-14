<?php
/**
 * Personas: docentes, administrativos y técnicos a quienes se asignan equipos.
 * Ver: todos los roles del panel. Escribir: administrador e inventario.
 */
final class PersonaController extends CatalogoController
{
    protected function modelo(): Catalogo
    {
        return new Persona();
    }

    protected function rolesEditar(): array
    {
        return ['administrador', 'inventario'];
    }

    protected function def(): array
    {
        $columnas = [
            ['clave' => 'nombre',         'titulo' => 'Nombre'],
            ['clave' => 'identificacion', 'titulo' => 'Cédula', 'numero' => true],
            ['clave' => 'area',           'titulo' => 'Área'],
            ['clave' => 'cargo',          'titulo' => 'Cargo'],
        ];
        // Qué persona tiene cuenta solo le interesa a quien administra las cuentas
        if (Auth::rol() === 'administrador') {
            $columnas[] = ['clave' => 'cuenta', 'titulo' => 'Usuario'];
        }

        return [
            'ruta'        => '/personas',
            'entidad'     => 'personas',
            'titulo'      => 'Personas',
            'descripcion' => 'Docentes, personal administrativo y técnico a quienes se entregan equipos.',
            'singular'    => 'persona',
            'nuevo'       => 'Nueva persona',
            'buscar'      => 'Buscar por nombre, cédula o área',
            'vacio'       => 'Todavía no hay personas registradas.',
            'aviso_desactivar' => 'Ya no se le podrán entregar equipos y, si tiene cuenta de usuario, '
                                . 'no podrá iniciar sesión. Su historial se conserva.',
            'columnas'    => $columnas,
            'enlace'      => '/personas/{id}',
        ];
    }

    /** GET /personas/{id} — ficha con los equipos a cargo y el historial de entregas. */
    public function ver(int $id): void
    {
        $persona = (new Persona())->ficha($id) ?? $this->notFound();
        $asignaciones = (new Asignacion())->dePersona($id);
        $puedeGestionar = in_array(Auth::rol(), $this->rolesEditar(), true);

        $this->view('personas/ver', [
            'titulo'         => $persona['nombre'],
            'p'              => $persona,
            'abiertas'       => array_values(array_filter($asignaciones, fn ($a) => $a['fecha_devolucion'] === null)),
            'devueltas'      => array_values(array_filter($asignaciones, fn ($a) => $a['fecha_devolucion'] !== null)),
            'puedeGestionar' => $puedeGestionar,
            'verCuenta'      => Auth::rol() === 'administrador',
            'verActas'       => in_array(Auth::rol(), AsignacionController::VER, true),
            'estilos'        => ['equipos.css', 'asignaciones.css'],
        ], 'panel');
    }

    protected function nombreDe(array $fila): string
    {
        return trim(($fila['nombres'] ?? '') . ' ' . ($fila['apellidos'] ?? ''));
    }

    protected function campos(?int $id): array
    {
        $actual = $id ? $this->modelo()->porId($id) : null;

        return [
            ['nombre' => 'identificacion', 'etiqueta' => 'Cédula', 'tipo' => 'text', 'requerido' => true,
             'max' => 10, 'formato' => 'cedula', 'inputmode' => 'numeric',
             'ayuda' => '10 dígitos, sin guion.'],
            ['nombre' => 'nombres', 'etiqueta' => 'Nombres', 'tipo' => 'text', 'requerido' => true,
             'min' => 2, 'max' => 150, 'autocomplete' => 'off'],
            ['nombre' => 'apellidos', 'etiqueta' => 'Apellidos', 'tipo' => 'text', 'requerido' => true,
             'min' => 2, 'max' => 150],
            ['nombre' => 'cargo', 'etiqueta' => 'Cargo', 'tipo' => 'text', 'max' => 120,
             'ayuda' => 'Por ejemplo: Docente, Coordinador de carrera, Técnico de soporte.'],
            ['nombre' => 'area_id', 'etiqueta' => 'Área', 'tipo' => 'select', 'entero' => true,
             'opciones' => (new Area())->opciones($actual ? ((int) $actual['area_id'] ?: null) : null),
             'vacia' => 'Sin área'],
            ['nombre' => 'correo', 'etiqueta' => 'Correo', 'tipo' => 'text', 'max' => 190,
             'formato' => 'correo', 'inputmode' => 'email'],
            ['nombre' => 'telefono', 'etiqueta' => 'Teléfono', 'tipo' => 'text', 'max' => 30,
             'formato' => 'telefono', 'inputmode' => 'tel'],
        ];
    }

    protected function validarExtra(array $datos, ?int $id): array
    {
        if ($datos['identificacion'] !== null
            && $this->modelo()->existe(['identificacion' => $datos['identificacion']], $id)) {
            return ['identificacion' => 'Ya hay otra persona registrada con esa cédula.'];
        }
        return [];
    }

    protected function bloqueoCambioEstado(int $id, array $fila, bool $activar): ?string
    {
        if (!$activar) {
            if ($id === Auth::user()['persona_id']) {
                return 'No puedes desactivar tu propia ficha: perderías el acceso al sistema.';
            }
            if ((new Persona())->esUltimoAdministrador($id)) {
                return "No se puede desactivar a «{$this->nombreDe($fila)}» porque tiene la única cuenta "
                     . 'de administrador activa. Primero da el rol de administrador a otra cuenta.';
            }
            if ($dependencias = $this->modelo()->dependencias($id)) {
                return "No se puede desactivar a «{$this->nombreDe($fila)}» porque tiene "
                     . implode(', ', $dependencias) . '. Primero registra la devolución de esos equipos.';
            }
        }
        return null;
    }
}
