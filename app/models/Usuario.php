<?php
/**
     * Tabla `usuarios` (+ `personas` para el nombre visible).
     * Cada cuenta pertenece a una persona; una persona tiene como máximo una cuenta.
     * No se borran: se desactivan con activo = 0.
     */
    
final class Usuario extends Catalogo
{
    public const ROLES = [
        'administrador' => 'Administrador',
        'inventario'    => 'Unidad de Inventario',
        'tecnico'       => 'Técnico',
        'consulta'      => 'Docente · Consulta',
    ];

    protected string $tabla     = 'usuarios';
    protected array  $columnas  = ['persona_id', 'usuario', 'rol', 'clave_hash', 'debe_cambiar_clave'];
    protected array  $buscables = ['t.usuario', "CONCAT(p.nombres, ' ', p.apellidos)",
                                   "CONCAT(p.apellidos, ' ', p.nombres)", 'p.identificacion'];
    protected string $orden     = 't.usuario';

    protected function seleccion(): string
    {
        return "t.id, t.usuario, t.usuario AS nombre, t.rol, t.activo, t.ultimo_acceso, t.debe_cambiar_clave,
                CONCAT(p.nombres, ' ', p.apellidos) AS persona";
    }

    protected function desde(): string
    {
        return 'FROM usuarios t LEFT JOIN personas p ON p.id = t.persona_id';
    }

    public function crear(array $datos): int
    {
        $datos['clave_hash']         = Clave::hash($datos['clave']);
        $datos['debe_cambiar_clave'] = 1;
        return parent::crear($datos);
    }

    public function buscarActivo(string $usuario): ?array
    {
        return $this->fetchOne(
            'SELECT u.id, u.usuario, u.clave_hash, u.rol, u.persona_id, u.debe_cambiar_clave,
                    p.nombres, p.apellidos, p.cargo
               FROM usuarios u
          LEFT JOIN personas p ON p.id = u.persona_id
              WHERE u.usuario = :usuario
                AND u.activo = 1
                AND (u.persona_id IS NULL OR p.activo = 1)
              LIMIT 1',
            [':usuario' => $usuario]
        );
    }

    public function perfil(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT u.id, u.usuario, u.clave_hash, u.rol, u.ultimo_acceso, u.debe_cambiar_clave,
                    p.id AS persona_id, p.identificacion, p.nombres, p.apellidos,
                    p.cargo, p.correo, p.telefono, p.pin_reporte,
                    a.nombre AS area
               FROM usuarios u
          LEFT JOIN personas p ON p.id = u.persona_id
          LEFT JOIN areas a ON a.id = p.area_id
              WHERE u.id = :id',
            [':id' => $id]
        );
    }

    public function cambiarPin(int $personaId, ?string $pin): void
    {
        $this->execute(
            'UPDATE personas SET pin_reporte = :pin WHERE id = :id',
            [':pin' => $pin, ':id' => $personaId]
        );
    }

    public function verificarClave(array $usuario, string $clave): bool
    {
        $guardada = $usuario['clave_hash'];
        $esHash   = password_get_info($guardada)['algoName'] !== 'unknown';

        if ($esHash) {
            if (!password_verify($clave, $guardada)) {
                return false;
            }
            if (password_needs_rehash($guardada, PASSWORD_DEFAULT)) {
                $this->guardarHash((int) $usuario['id'], $clave);
            }
            return true;
        }

        if (!hash_equals($guardada, $clave)) {
            return false;
        }
        $this->guardarHash((int) $usuario['id'], $clave);
        return true;
    }

    public function cambiarClave(int $id, string $clave, bool $temporal): void
    {
        $this->execute(
            'UPDATE usuarios SET clave_hash = :hash, debe_cambiar_clave = :temporal WHERE id = :id',
            [':hash' => Clave::hash($clave), ':temporal' => (int) $temporal, ':id' => $id]
        );
    }

    public function registrarAcceso(int $id): void
    {
        $this->execute('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id', [':id' => $id]);
    }

    public function administradoresActivos(?int $exceptoId = null): int
    {
        return (int) $this->fetchOne(
            "SELECT COUNT(*) AS n
               FROM usuarios u
          LEFT JOIN personas p ON p.id = u.persona_id
              WHERE u.rol = 'administrador' AND u.activo = 1
                AND (u.persona_id IS NULL OR p.activo = 1)
                AND NOT (u.id <=> :excepto)",
            [':excepto' => $exceptoId]
        )['n'];
    }

    public function persona(int $id): ?array
    {
        return $this->fetchOne(
            "SELECT p.id, CONCAT(p.nombres, ' ', p.apellidos) AS nombre, p.identificacion, p.activo
               FROM usuarios u JOIN personas p ON p.id = u.persona_id
              WHERE u.id = :id",
            [':id' => $id]
        );
    }

    private function guardarHash(int $id, string $clave): void
    {
        $this->execute(
            'UPDATE usuarios SET clave_hash = :hash WHERE id = :id',
            [':hash' => Clave::hash($clave), ':id' => $id]
        );
    }
}