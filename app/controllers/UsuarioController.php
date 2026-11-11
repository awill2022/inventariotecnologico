<?php
/**
 * Cuentas de usuario: vincular persona, rol, activar/desactivar y restablecer clave.
 * Solo el administrador (se define en routes.php).
 *
 * Protecciones: nadie se desactiva ni se quita el rol de administrador a sí mismo,
 * y el sistema nunca se queda sin un administrador activo.
 * Los cambios a una cuenta con sesión abierta se aplican en su próximo inicio de sesión.
 */
final class UsuarioController extends CatalogoController
{
    protected function modelo(): Catalogo
    {
        return new Usuario();
    }

    protected function def(): array
    {
        return [
            'ruta'        => '/usuarios',
            'entidad'     => 'usuarios',
            'titulo'      => 'Usuarios',
            'descripcion' => 'Cuentas para entrar al sistema y el rol de cada una.',
            'singular'    => 'usuario',
            'articulo'    => 'el',
            'nuevo'       => 'Nuevo usuario',
            'buscar'      => 'Buscar por usuario, nombre o cédula',
            'vacio'       => 'Todavía no hay cuentas de usuario.',
            'nota_creado' => 'Entrégale la clave temporal: deberá cambiarla al entrar.',
            'aviso_desactivar' => 'Ya no podrá iniciar sesión. Si ahora tiene la sesión abierta, '
                                . 'podrá seguir hasta que salga. Su historial se conserva.',
            'columnas'    => [
                ['clave' => 'usuario',       'titulo' => 'Usuario'],
                ['clave' => 'persona',       'titulo' => 'Persona'],
                ['clave' => 'rol',           'titulo' => 'Rol', 'mapa' => Usuario::ROLES],
                ['clave' => 'ultimo_acceso', 'titulo' => 'Último acceso', 'formato' => 'relativa'],
            ],
            'acciones'    => [
                ['texto' => 'Restablecer clave', 'ruta' => '/usuarios/{id}/clave', 'icono' => 'key'],
            ],
        ];
    }

    protected function nombreDe(array $fila): string
    {
        return (string) ($fila['usuario'] ?? '');
    }

    protected function sinSecretos(array $datos): array
    {
        unset($datos['clave'], $datos['clave_confirmacion']);
        return $datos;
    }

    protected function campos(?int $id): array
    {
        $usuario = [
            'nombre' => 'usuario', 'etiqueta' => 'Usuario', 'tipo' => 'text', 'requerido' => true,
            'min' => 3, 'max' => 80, 'formato' => 'usuario', 'autocomplete' => 'off',
            'ayuda' => 'Con este nombre iniciará sesión. Por ejemplo: dcaiza.',
        ];
        $rol = [
            'nombre' => 'rol', 'etiqueta' => 'Rol', 'tipo' => 'select', 'requerido' => true,
            'opciones' => Usuario::ROLES,
            'ayuda' => 'Docente · Consulta solo ve sus equipos asignados. Los demás roles entran al panel.',
        ];

        if ($id) {
            $persona = (new Usuario())->persona($id);
            return [
                ['nombre' => 'persona_id', 'etiqueta' => 'Persona', 'tipo' => 'fijo',
                 'valor' => $persona ? self::textoPersona($persona) : 'Sin persona vinculada',
                 'ayuda' => 'La persona de una cuenta no se cambia, para no mezclar su historial.'],
                $usuario,
                $rol,
            ];
        }

        return [
            ['nombre' => 'persona_id', 'etiqueta' => 'Persona', 'tipo' => 'select', 'requerido' => true,
             'entero' => true, 'opciones' => (new Persona())->opcionesSinCuenta(),
             'vacia' => 'Elige una persona',
             'ayuda' => 'Solo aparecen personas activas que aún no tienen cuenta. Si no está, regístrala primero en Personas.'],
            $usuario,
            $rol,
            ...self::camposClave('Clave temporal', true),
        ];
    }

    protected function validarExtra(array $datos, ?int $id): array
    {
        $errores = [];
        $modelo  = new Usuario();

        if ($datos['usuario'] !== null && $modelo->existe(['usuario' => $datos['usuario']], $id)) {
            $errores['usuario'] = 'Ese nombre de usuario ya está en uso.';
        }

        // Quitar el rol de administrador: nunca a sí mismo ni al último administrador activo
        if ($id && $datos['rol'] !== null && $datos['rol'] !== 'administrador') {
            $fila = $modelo->porId($id);
            if ($fila && $fila['rol'] === 'administrador') {
                if ($id === Auth::user()['id']) {
                    $errores['rol'] = 'No puedes quitarte a ti mismo el rol de administrador.';
                } elseif ($fila['activo'] && $modelo->administradoresActivos($id) === 0) {
                    $errores['rol'] = 'Es la única cuenta de administrador activa. Primero da ese rol a otra cuenta.';
                }
            }
        }
        return $errores;
    }

    protected function bloqueoCambioEstado(int $id, array $fila, bool $activar): ?string
    {
        $modelo = new Usuario();

        if ($activar) {
            $persona = $modelo->persona($id);
            if ($persona && !$persona['activo']) {
                return "Primero activa a {$persona['nombre']} en Personas: con su ficha inactiva, "
                     . "«{$fila['usuario']}» no podría iniciar sesión.";
            }
            return null;
        }

        if ($id === Auth::user()['id']) {
            return 'No puedes desactivar tu propia cuenta.';
        }
        if ($fila['rol'] === 'administrador' && $modelo->administradoresActivos($id) === 0) {
            return "No se puede desactivar «{$fila['usuario']}» porque es la única cuenta de administrador activa. "
                 . 'Primero da ese rol a otra cuenta.';
        }
        return null;
    }

    /** GET /usuarios/{id}/clave */
    public function clave(int $id): void
    {
        $fila = $this->modelo()->porId($id) ?? $this->notFound();
        if ($id === Auth::user()['id']) {
            Session::flash('ok', 'Tu propia clave se cambia aquí, en Mi perfil.');
            $this->redirect('/perfil');
        }

        $this->view('catalogos/form', [
            'titulo'     => 'Restablecer clave',
            'def'        => $this->def(),
            'id'         => $id,
            'accion'     => "/usuarios/$id/clave",
            'encabezado' => 'Restablecer clave',
            'intro'      => "Escribe una clave temporal para «{$fila['usuario']}» y entrégasela. "
                          . 'Deberá cambiarla en su próximo inicio de sesión; su clave actual deja de servir.',
            'boton'      => 'Restablecer clave',
            'campos'     => [
                ['nombre' => 'usuario', 'etiqueta' => 'Usuario', 'tipo' => 'fijo', 'valor' => $fila['usuario']],
                ...self::camposClave('Clave temporal', true),
            ],
            'valores'    => [],
            'errores'    => Session::flash('form_errores') ?? [],
            'csrf'       => Session::csrfToken(),
            'scripts'    => ['panel.js'],
        ], 'panel');
    }

    /** POST /usuarios/{id}/clave */
    public function restablecer(int $id): void
    {
        $this->requireCsrf();
        $modelo = new Usuario();
        $fila   = $modelo->porId($id) ?? $this->notFound();
        if ($id === Auth::user()['id']) {
            $this->redirect('/perfil');
        }

        // El usuario se agrega para que la política impida usarlo como clave
        [$datos, $errores] = Validador::validar(
            self::camposClave('Clave temporal', true),
            ['usuario' => $fila['usuario']] + $_POST
        );
        if ($errores) {
            $this->volverAlFormulario("/usuarios/$id/clave", [], $errores);
        }

        try {
            $modelo->transaccion(function () use ($modelo, $id, $datos, $fila) {
                $modelo->cambiarClave($id, $datos['clave'], true);
                (new Auditoria())->registrar(Auth::user()['id'], 'usuarios', $id, 'editar', [
                    'nota'    => 'Clave restablecida por el administrador',
                    'cambios' => ['debe_cambiar_clave' => [(int) $fila['debe_cambiar_clave'], 1]],
                ]);
            });
        } catch (PDOException $e) {
            error_log('[usuarios.restablecer] ' . $e->getMessage());
            Session::flash('error', 'No pudimos restablecer la clave. Inténtalo de nuevo en unos minutos.');
            $this->redirect("/usuarios/$id/clave");
        }

        Session::flash('ok', "Se restableció la clave de «{$fila['usuario']}». "
            . 'Entrégale la clave temporal: deberá cambiarla al entrar.');
        $this->redirect('/usuarios');
    }

    /** Campos de clave nueva + confirmación (alta, restablecer y Mi perfil). */
    public static function camposClave(string $etiqueta, bool $temporal): array
    {
        return [
            ['nombre' => 'clave', 'etiqueta' => $etiqueta, 'tipo' => 'password', 'requerido' => true,
             'politica' => true,
             'ayuda' => Clave::AYUDA . ($temporal ? ' Es temporal: deberá cambiarla al entrar.' : '')],
            ['nombre' => 'clave_confirmacion', 'etiqueta' => 'Repite la clave', 'tipo' => 'password',
             'requerido' => true, 'igual_a' => 'clave'],
        ];
    }

    private static function textoPersona(array $persona): string
    {
        return $persona['nombre'] . ($persona['identificacion'] ? ' · ' . $persona['identificacion'] : '')
             . ($persona['activo'] ? '' : ' (inactiva)');
    }
}
