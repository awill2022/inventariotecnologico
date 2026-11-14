<?php
/**
 * Tabla `personas`: docentes, personal administrativo y técnico.
 * A ellas se les asignan equipos; algunas tienen además una cuenta de usuario.
 * No se borran: se desactivan con activo = 0.
 */
final class Persona extends Catalogo
{
    protected string $tabla     = 'personas';
    protected array  $columnas  = ['identificacion', 'nombres', 'apellidos', 'cargo', 'area_id', 'correo', 'telefono'];
    protected array  $buscables = ['t.identificacion', "CONCAT(t.nombres, ' ', t.apellidos)",
                                   "CONCAT(t.apellidos, ' ', t.nombres)", 't.cargo', 'a.nombre'];
    protected string $orden     = 't.apellidos, t.nombres';

    protected function seleccion(): string
    {
        // "nombre" es el texto que usan el listado y los mensajes
        return "t.*, CONCAT(t.nombres, ' ', t.apellidos) AS nombre, a.nombre AS area,
                (SELECT u.usuario FROM usuarios u WHERE u.persona_id = t.id ORDER BY u.id LIMIT 1) AS cuenta";
    }

    protected function desde(): string
    {
        return 'FROM personas t LEFT JOIN areas a ON a.id = t.area_id';
    }

    /**
     * Personas activas que todavía no tienen cuenta de usuario, para el alta de usuarios.
     * @return array [id => "Apellidos Nombres · cédula"]
     */
    public function opcionesSinCuenta(): array
    {
        $filas = $this->fetchAll(
            "SELECT p.id, CONCAT(p.apellidos, ' ', p.nombres, COALESCE(CONCAT(' · ', p.identificacion), '')) AS texto
               FROM personas p
              WHERE p.activo = 1
                AND NOT EXISTS (SELECT 1 FROM usuarios u WHERE u.persona_id = p.id)
              ORDER BY p.apellidos, p.nombres"
        );
        return array_column($filas, 'texto', 'id');
    }

    /** Persona con su nombre completo, área y cuenta (mismas columnas que el listado). */
    public function ficha(int $id): ?array
    {
        return $this->fetchOne("SELECT {$this->seleccion()} {$this->desde()} WHERE t.id = :id", [':id' => $id]);
    }

    /**
     * Personas activas a quienes se puede entregar un equipo o que autorizan un traslado.
     * @return array [id => "Apellidos Nombres · cédula"]
     */
    public function opcionesActivas(?int $incluirId = null): array
    {
        $filas = $this->fetchAll(
            "SELECT p.id, CONCAT(p.apellidos, ' ', p.nombres, COALESCE(CONCAT(' · ', p.identificacion), '')) AS texto
               FROM personas p
              WHERE p.activo = 1 OR p.id <=> :incluir
              ORDER BY p.apellidos, p.nombres",
            [':incluir' => $incluirId]
        );
        return array_column($filas, 'texto', 'id');
    }

    /**
     * Personas activas con una cuenta activa de técnico: quienes pueden atender un mantenimiento.
     * $incluirId agrega ese técnico aunque ya no cumpla (valor ya guardado).
     * @return array [id => "Apellidos Nombres"]
     */
    public function opcionesTecnicos(?int $incluirId = null): array
    {
        $filas = $this->fetchAll(
            "SELECT p.id, CONCAT(p.apellidos, ' ', p.nombres) AS texto
               FROM personas p
              WHERE (p.activo = 1 AND EXISTS (SELECT 1 FROM usuarios u
                                               WHERE u.persona_id = p.id AND u.rol = 'tecnico' AND u.activo = 1))
                 OR p.id <=> :incluir
              ORDER BY p.apellidos, p.nombres",
            [':incluir' => $incluirId]
        );
        return array_column($filas, 'texto', 'id');
    }

    public function dependencias(int $id): array
    {
        $frases = [];

        $n = $this->contar('SELECT COUNT(*) AS n FROM asignaciones WHERE persona_id = :id AND fecha_devolucion IS NULL', $id);
        if ($n) {
            $frases[] = Formato::plural($n, 'equipo asignado sin devolver', 'equipos asignados sin devolver');
        }
        return $frases;
    }

    /** true si la persona tiene la única cuenta de administrador activa del sistema. */
    public function esUltimoAdministrador(int $id): bool
    {
        $cuenta = $this->fetchOne(
            "SELECT id FROM usuarios WHERE persona_id = :id AND rol = 'administrador' AND activo = 1 LIMIT 1",
            [':id' => $id]
        );
        return $cuenta !== null && (new Usuario())->administradoresActivos((int) $cuenta['id']) === 0;
    }
}
