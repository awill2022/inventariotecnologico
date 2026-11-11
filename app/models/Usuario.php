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
        // Sin clave_hash: el listado no la necesita
        return "t.id, t.usuario, t.usuario AS nombre, t.rol, t.activo, t.ultimo_acceso, t.debe_cambiar_clave,
                CONCAT(p.nombres, ' ', p.apellidos) AS persona";
    }

    protected function desde(): string
    {
        return 'FROM usuarios t LEFT JOIN personas p ON p.id = t.persona_id';
    }

    /** Alta de una cuenta: guarda el hash de la clave temporal y obliga a cambiarla al entrar. */
    public function crear(array $datos): int
    {
        $datos['clave_hash']         = Clave::hash($datos['clave']);
        $datos['debe_cambiar_clave'] = 1;
        return parent::crear($datos);
    }

    /**
     * Usuario activo con persona activa (o sin persona vinculada).
     * Devuelve null si no existe o está desactivado.
     */
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

    /** Datos de la cuenta y de su persona para "Mi perfil". */
    public function perfil(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT u.id, u.usuario, u.clave_hash, u.rol, u.ultimo_acceso, u.debe_cambiar_clave,
                    p.identificacion, p.nombres, p.apellidos, p.cargo, p.correo, p.telefono,
                    a.nombre AS area
               FROM usuarios u
          LEFT JOIN personas p ON p.id = u.persona_id
          LEFT JOIN areas a ON a.id = p.area_id
              WHERE u.id = :id',
            [':id' => $id]
        );
    }

    /**
     * Verifica la clave. Acepta hashes de password_hash() y, por compatibilidad,
     * claves antiguas guardadas en texto plano: si coinciden, las cifra en ese momento.
     */
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

        // Clave heredada en texto plano: comparar en tiempo constante y migrar.
        if (!hash_equals($guardada, $clave)) {
            return false;
        }
        $this->guardarHash((int) $usuario['id'], $clave);
        return true;
    }

    /**
     * Cambia la clave. Con $temporal = true (restablecida por el administrador)
     * el usuario deberá cambiarla en su próximo inicio de sesión.
     */
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

    /**
     * Administradores que hoy pueden iniciar sesión (cuenta activa y persona activa),
     * sin contar la cuenta $exceptoId. Si da 0, esa cuenta es el último administrador.
     */
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

    /** Nombre de la persona dueña de la cuenta y si está activa. */
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
