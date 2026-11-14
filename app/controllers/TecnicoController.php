<?php
/**
 * Módulo Técnico y flujo completo de QR:
 * 1. GET  /q/{token}                      -> Ficha resumen al escanear QR con 2 opciones: Reportar (PIN) o Mantenimiento (Técnico).
 * 2. POST /q/{token}/reportar             -> Reporte rápido con Cédula/Usuario + PIN de 4 dígitos y fotos de evidencia.
 * 3. GET  /q/{token}/mantenimiento        -> Lleva al técnico a la ficha completa (si no hay sesión, pasa por /login y vuelve aquí).
 * 4. GET  /tecnico                        -> Bandeja de alertas, buscador y lector QR con cámara.
 * 5. GET  /tecnico/equipos/{id}           -> Ficha técnica completa (custodio, fotos, cambio de piezas/componentes y mantenimiento).
 * 6. POST /tecnico/equipos/{id}/programar -> Programa mantenimiento preventivo.
 * 7. POST /tecnico/equipos/{id}/cancelar  -> Anula solicitud duplicada o falsa alarma.
 * 8. GET  /tecnico/equipos/{id}/etiqueta  -> Etiqueta patrimonial QR lista para imprimir.
 */
final class TecnicoController extends Controller
{
    private const FILTROS = ['abiertos', 'programado', 'en_proceso', 'finalizado'];
    private const USOS_REPORTE = ['con_fallas', 'inoperativo'];
    private const ESTADOS_SALIDA = [
        'operativo'   => 'Operativo (listo para usar)',
        'con_fallas'  => 'Con fallas (funciona con limitaciones)',
        'inoperativo' => 'Inoperativo (fuera de servicio)',
    ];

    /**
     * GET /q/{token} — Pantalla universal al escanear el QR del equipo.
     */
    public function qr(string $token): void
    {
        $equipo  = $this->buscarEquipoPorToken($token);
        $abierto = (new Mantenimiento())->abiertoDeEquipo((int)$equipo['id']);

        $this->view('tecnico/qr_publico', [
            'titulo'  => $equipo['codigo'] . ' · ' .$equipo['nombre'],
            'equipo'  => $equipo,
            'abierto' => $abierto,
            'ok'      => Session::flash('qr_ok'),
            'error'   => Session::flash('qr_error'),
            'errores' => Session::flash('qr_errores') ?? [],
            'old'     => Session::flash('qr_old') ?? [],
            'csrf'    => Session::csrfToken(),
            'estilos' => ['docente.css', 'tecnico.css'],
            'scripts' => ['vendor/qrcode.min.js', 'tecnico.js'],
        ], 'auth');
    }

    /**
     * POST /q/{token}/reportar — Reporte rápido con PIN de 4 dígitos + fotos de evidencia.
     */
    public function reportarQr(string $token): void
    {
        $this->requireCsrf();
        $equipoModel = new Equipo();$equipo      = $this->buscarEquipoPorToken($token);

        $identificador = trim((string) ($_POST['identificador'] ?? ''));
        $pin           = trim((string) ($_POST['pin'] ?? ''));
        $problema      = trim((string) ($_POST['problema'] ?? ''));
        $uso           = (string) ($_POST['uso'] ?? 'con_fallas');

        $old     = ['identificador' => $identificador, 'problema' =>$problema, 'uso' => $uso];$errores = [];

        $persona =$equipoModel->verificarPinReporte($identificador,$pin);
        if (!$persona) {$errores['pin'] = 'Cédula/usuario o PIN incorrecto. (Tu PIN por defecto son los últimos 4 dígitos de tu cédula).';
        }
        if (mb_strlen($problema) < 10 || mb_strlen($problema) > 2000) {$errores['problema'] = 'Describe qué ocurre con el equipo (entre 10 y 2000 caracteres).';
        }
        if (!in_array($uso, self::USOS_REPORTE, true)) {$errores['uso'] = 'Indica si el equipo se puede seguir usando.';
        }

        if ($errores) {
            Session::flash('qr_errores', $errores);
            Session::flash('qr_old', $old);
            $this->redirect('/q/' .$equipo['qr_token'] . '#form-qr-reportar');
        }

        $autorTexto       = "{$persona['nombre']} ({$persona['identificacion']})";
        $problemaConAutor = "[Reportado vía QR por $autorTexto] " . $problema;
        $usuarioId        = $persona['usuario_id'] ? (int)$persona['usuario_id'] : null;

        try {
            $mantModel    = new Mantenimiento();$archivoModel = new Archivo();

            $mantModel->transaccion(function () use (
                $mantModel,$equipoModel, $archivoModel,$equipo,
                $problemaConAutor,$uso, $usuarioId,$persona
            ) {
                $mantId =$mantModel->reportarFalla((int) $equipo['id'],$problemaConAutor, $usuarioId);$equipoModel->marcarFalla((int) $equipo['id'],$uso);

                if (!empty($_FILES['fotos'])) {
                    [, $errFoto] =$archivoModel->guardarFotosSubidas(
                        (int) $equipo['id'],$mantId,
                        $_FILES['fotos'],$usuarioId,
                        'reporte_qr',
                        'Evidencia subida en reporte QR por ' . $persona['nombre']
                    );
                    if ($errFoto !== null) {
                        throw new RuntimeException($errFoto);
                    }
                }

                (new Auditoria())->registrar($usuarioId, 'mantenimientos',$mantId, 'crear', [
                    'origen'     => 'qr_pin',
                    'equipo'     => $equipo['codigo'],
                    'persona_id' => (int) $persona['persona_id'],
                    'reportante' => $persona['nombre'],
                    'uso'        => $uso,
                ]);
            });
        } catch (RuntimeException $e) {
            Session::flash('qr_error', $e->getMessage());
            Session::flash('qr_old', $old);
            $this->redirect('/q/' .$equipo['qr_token'] . '#form-qr-reportar');
        } catch (Throwable $e) {
            error_log('[qr.reportar] ' . $e->getMessage());
            Session::flash('qr_error', 'No pudimos registrar el reporte en este momento. Inténtalo de nuevo.');
            Session::flash('qr_old', $old);
            $this->redirect('/q/' .$equipo['qr_token'] . '#form-qr-reportar');
        }

        Session::flash(
            'qr_ok',
            "Gracias, {$persona['nombre']}. Tu reporte sobre «{$equipo['codigo']}» fue enviado a Soporte Técnico y al Administrador."
        );
        $this->redirect('/q/' .$equipo['qr_token']);
    }

    /**
     * GET /q/{token}/mantenimiento — Botón "Realizar mantenimiento" desde el QR.
     */
    public function irAMantenimientoQr(string $token): void
    {
        $equipo  = $this->buscarEquipoPorToken($token);
        $destino = '/tecnico/equipos/' . (int)$equipo['id'] . '#intervencion';

        if (Auth::check() && in_array(Auth::rol(), ['tecnico', 'administrador', 'inventario'], true)) {
            $this->redirect($destino);
        }

        Session::set('url_post_login', $destino);
        Session::flash('error', "Inicia sesión como Técnico para registrar el mantenimiento del equipo {$equipo['codigo']}.");
        $this->redirect('/login');
    }

    /** GET /tecnico — Bandeja de alertas de mantenimiento, buscador y escáner QR. */
    public function index(): void
    {
        $q      = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);$estado = in_array($_GET['estado'] ?? '', self::FILTROS, true) ?$_GET['estado'] : 'abiertos';

        $mantModel          = new Mantenimiento();$equiposEncontrados = $q !== '' ? (new Equipo())->buscarParaTecnico($q) : [];

        $this->view('tecnico/inicio', [
            'titulo'  => 'Soporte y QR de equipos',
            'q'       => $q,
            'estado'  => $estado,
            'conteos' => $mantModel->resumenConteos(),
            'bandeja' => $mantModel->bandeja($estado,$q),
            'equipos' => $equiposEncontrados,
            'estilos' => ['docente.css', 'tecnico.css'],
            'scripts' => ['tecnico.js'],
        ], 'panel');
    }

    /** GET /tecnico/equipos/{id} — Ficha técnica completa con piezas, componentes y fotos. */
    public function equipo(int $id): void
    {
        $equipoModel = new Equipo();
        $equipo      =$equipoModel->ficha($id) ?? $this->notFound();
        $specs       =$equipoModel->especificaciones([$id])[$id] ?? [];

        $compModel          = new Componente();
        $componentes        =$compModel->deEquipo($id);$componentesActivos = $compModel->activosDeEquipo($id);

        $mantModel      = new Mantenimiento();$mantenimientos = $mantModel->deEquipos([$id])[$id] ?? [];$abierto        = $mantModel->abiertoDeEquipo($id);

        $mantIds         = array_map(fn ($m) => (int) $m['id'],$mantenimientos);
        $repModel        = new Repuesto();$repuestosUsados = $repModel->deMantenimientos($mantIds);
        $catalogoRep     =$repModel->activos();

        $archivos = (new Archivo())->deEquipo($id);

        $puedeIntervenir = in_array(Auth::rol(), ['tecnico', 'administrador'], true);

        $this->view('tecnico/equipo', [
            'titulo'             => $equipo['codigo'] . ' · ' .$equipo['nombre'],
            'equipo'             => $equipo,
            'specs'              => $specs,
            'componentes'        => $componentes,
            'componentesActivos' => $componentesActivos,
            'mantenimientos'     => $mantenimientos,
            'repuestosUsados'    => $repuestosUsados,
            'catalogoRepuestos'  => $catalogoRep,
            'archivos'           => $archivos,
            'abierto'            => $abierto,
            'tecnicos'           => $mantModel->tecnicosActivos(),
            'puedeIntervenir'    => $puedeIntervenir,
            'tiposMant'          => Formato::TIPOS_MANTENIMIENTO,
            'estadosSalida'      => self::ESTADOS_SALIDA,
            'old'                => Session::flash('form_old') ?? [],
            'errores'            => Session::flash('form_errores') ?? [],
            'csrf'               => Session::csrfToken(),
            'estilos'            => ['docente.css', 'tecnico.css'],
            'scripts'            => ['vendor/qrcode.min.js', 'panel.js', 'tecnico.js'],
        ], 'panel');
    }

    /** POST /tecnico/equipos/{id}/iniciar */
    public function iniciar(int $id): void
    {
        $this->requireCsrf();$equipoModel = new Equipo();
        $equipo      =$equipoModel->ficha($id) ?? $this->notFound();
        $mantModel   = new Mantenimiento();$abierto     = $mantModel->abiertoDeEquipo($id);

        $usuario   = Auth::user();
        $tecnicoId =$usuario['persona_id'] ?? null;

        try {
            $mantModel->transaccion(function () use ($mantModel, $equipoModel,$id, $equipo,$abierto, $tecnicoId,$usuario) {
                if ($abierto) {$mantModel->iniciar((int) $abierto['id'],$tecnicoId);
                    $mantId = (int)$abierto['id'];
                } else {
                    $mantId = $mantModel->registrarDirecto($id, [
                        'tipo'                  => 'diagnostico',
                        'problema_reportado'    => 'Revisión técnica iniciada al escanear QR.',
                        'diagnostico'           => null,
                        'trabajo_realizado'     => null,
                        'resultado'             => null,
                        'recomendaciones'       => null,
                        'costo_mano_obra'       => 0,
                        'proximo_mantenimiento' => null,
                    ], $tecnicoId, (int)$usuario['id'], 'en_proceso');
                }
                $equipoModel->cambiarEstadoOperativo($id, 'en_mantenimiento');
                (new Auditoria())->registrar((int) $usuario['id'], 'mantenimientos',$mantId, 'editar', [
                    'accion_tecnica' => 'iniciar_mantenimiento',
                    'equipo'         => $equipo['codigo'],
                ]);
            });
        } catch (PDOException $e) {
            error_log('[tecnico.iniciar] ' . $e->getMessage());
            Session::flash('error', 'No pudimos iniciar el mantenimiento. Inténtalo de nuevo.');
            $this->redirect("/tecnico/equipos/$id");
        }

        Session::flash('ok', "El equipo «{$equipo['codigo']}» quedó en mantenimiento a tu cargo.");
        $this->redirect("/tecnico/equipos/$id#intervencion");
    }

    /**
     * POST /tecnico/equipos/{id}/reporte
     * Guarda el mantenimiento + retiro/instalación de componentes + repuestos + fotos de evidencia.
     */
    public function guardarReporte(int $id): void
    {
        $this->requireCsrf();
        $equipoModel = new Equipo();
        $equipo      = $equipoModel->ficha($id) ?? $this->notFound();
        $mantModel   = new Mantenimiento();
        $abierto     = $mantModel->abiertoDeEquipo($id);

        $campos = [
            ['nombre' => 'tipo', 'etiqueta' => 'Tipo de intervención', 'tipo' => 'select', 'requerido' => true,
             'opciones' => Formato::TIPOS_MANTENIMIENTO],
            ['nombre' => 'problema_reportado', 'etiqueta' => 'Falla o motivo observado', 'tipo' => 'textarea',
             'requerido' => !$abierto, 'min' => 10, 'max' => 2000],
            ['nombre' => 'diagnostico', 'etiqueta' => 'Diagnóstico técnico', 'tipo' => 'textarea',
             'requerido' => true, 'min' => 10, 'max' => 2000],
            ['nombre' => 'trabajo_realizado', 'etiqueta' => 'Trabajo realizado', 'tipo' => 'textarea',
             'requerido' => true, 'min' => 10, 'max' => 2000],
            ['nombre' => 'resultado', 'etiqueta' => 'Resultado', 'tipo' => 'text', 'max' => 500],
            ['nombre' => 'recomendaciones', 'etiqueta' => 'Recomendaciones', 'tipo' => 'textarea', 'max' => 1000],
            ['nombre' => 'estado_operativo', 'etiqueta' => 'Estado en que queda el equipo', 'tipo' => 'select',
             'requerido' => true, 'opciones' => self::ESTADOS_SALIDA],
        ];

        [$datos, $errores] = Validador::validar($campos, $_POST);

        $costoManoObra            = max(0, (float) ($_POST['costo_mano_obra'] ?? 0));
        $datos['costo_mano_obra'] = $costoManoObra;

        $proximo = trim((string) ($_POST['proximo_mantenimiento'] ?? ''));
        if ($proximo !== '') {
            $fechaObj = DateTimeImmutable::createFromFormat('Y-m-d', $proximo);
            if (!$fechaObj || $fechaObj->format('Y-m-d') !== $proximo) {
                $errores['proximo_mantenimiento'] = 'Escribe una fecha válida (AAAA-MM-DD).';
            }
        }
        $datos['proximo_mantenimiento'] = $proximo !== '' ? $proximo : null;

        if ($errores) {
            Session::flash('form_old', $datos);
            Session::flash('form_errores', $errores);
            $this->redirect("/tecnico/equipos/$id#intervencion");
        }

        $retirarIds = is_array($_POST['retirar_componentes'] ?? null) ? $_POST['retirar_componentes'] : [];
        $nuevosComp = $this->leerNuevosComponentes($_POST);
        $repuestos  = $this->leerRepuestosUsados($_POST);

        $usuario   = Auth::user();
        $tecnicoId = $usuario['persona_id'] ?? null;

        try {
            $compModel    = new Componente();
            $repModel     = new Repuesto();
            $archivoModel = new Archivo();

            $mantModel->transaccion(function () use (
                $mantModel, $equipoModel, $compModel, $repModel, $archivoModel,
                $id, $equipo, $abierto, $datos, $tecnicoId, $usuario,
                $retirarIds, $nuevosComp, $repuestos
            ) {
                if ($abierto) {
                    $mantModel->finalizar((int) $abierto['id'], $datos, $tecnicoId);
                    $mantId    = (int) $abierto['id'];
                    $accionAud = 'cerrar';
                } else {
                    $mantId    = $mantModel->registrarDirecto($id, $datos, $tecnicoId, (int) $usuario['id'], 'finalizado');
                    $accionAud = 'crear';
                }

                // 1. Retirar componentes sustituidos
                $retirados = $compModel->retirar($id, $retirarIds, $mantId);

                // 2. Instalar nuevos componentes
                $instalados = 0;
                foreach ($nuevosComp as $nc) {
                    $compModel->instalar($id, $mantId, $nc);
                    $instalados++;
                }

                // 3. Registrar repuestos / piezas utilizadas y descontar stock
                $repuestosReg = 0;
                foreach ($repuestos as $r) {
                    $repId = $r['repuesto_id'] ?: $repModel->obtenerOCrearPorNombre($r['nombre_nuevo']);
                    $repModel->registrarUso($mantId, $repId, $r['cantidad'], $r['costo_unitario'], $r['detalle']);
                    $repuestosReg++;
                }

                // 4. Guardar fotos del mantenimiento si se adjuntaron
                if (!empty($_FILES['fotos'])) {
                    [, $errFoto] = $archivoModel->guardarFotosSubidas(
                        $id,
                        $mantId,
                        $_FILES['fotos'],
                        (int) $usuario['id'],
                        'mantenimiento',
                        'Evidencia técnica de mantenimiento'
                    );
                    if ($errFoto !== null) {
                        throw new RuntimeException($errFoto);
                    }
                }

                // 5. Actualizar estado operativo del equipo
                $equipoModel->cambiarEstadoOperativo($id, $datos['estado_operativo']);

                (new Auditoria())->registrar((int) $usuario['id'], 'mantenimientos', $mantId, $accionAud, [
                    'equipo'                 => $equipo['codigo'],
                    'tipo'                   => $datos['tipo'],
                    'estado_operativo'       => $datos['estado_operativo'],
                    'componentes_retirados'  => $retirados,
                    'componentes_instalados' => $instalados,
                    'repuestos_usados'       => $repuestosReg,
                ]);
            });
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            Session::flash('form_old', $datos);
            $this->redirect("/tecnico/equipos/$id#intervencion");
        } catch (Throwable $e) {
            error_log('[tecnico.reporte] ' . $e->getMessage());
            Session::flash('error', 'No pudimos guardar el informe técnico. Inténtalo de nuevo.');
            Session::flash('form_old', $datos);
            $this->redirect("/tecnico/equipos/$id#intervencion");
        }

        Session::flash('ok', "Se guardó la ficha técnica de «{$equipo['codigo']}» (incluyendo piezas y componentes) y su estado quedó en "
            . mb_strtolower(Formato::ESTADOS[$datos['estado_operativo']][0]) . '.');
        $this->redirect("/tecnico/equipos/$id");
    }

    /** POST /tecnico/equipos/{id}/programar — Agenda un mantenimiento preventivo. */
    public function programar(int $id): void
    {
        $this->requireCsrf();
        $equipo    = (new Equipo())->ficha($id) ?? $this->notFound();
        $mantModel = new Mantenimiento();

        $fecha     = trim((string) ($_POST['fecha_programada'] ?? ''));
        $tecnicoId = filter_var($_POST['tecnico_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
        $detalle   = trim((string) ($_POST['detalle_preventivo'] ?? ''));

        $fechaObj = DateTimeImmutable::createFromFormat('Y-m-d', $fecha);
        if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fecha) {
            Session::flash('error', 'Indica una fecha válida para programar el mantenimiento preventivo.');
            $this->redirect("/tecnico/equipos/$id");
        }

        $usuario = Auth::user();
        $mantModel->transaccion(function () use ($mantModel, $id, $equipo, $tecnicoId, $fecha, $detalle, $usuario) {
            $mantId = $mantModel->programarPreventivo($id, $tecnicoId, $fecha, $detalle, (int) $usuario['id']);
            (new Auditoria())->registrar((int) $usuario['id'], 'mantenimientos', $mantId, 'crear', [
                'accion_tecnica'   => 'programar_preventivo',
                'equipo'           => $equipo['codigo'],
                'fecha_programada' => $fecha,
            ]);
        });

        Session::flash('ok', 'Se programó el mantenimiento preventivo para el ' . Formato::fecha($fecha) . '.');
        $this->redirect("/tecnico/equipos/$id");
    }

    /** POST /tecnico/equipos/{id}/cancelar — Anula una solicitud duplicada o por falsa alarma. */
    public function cancelar(int $id): void
    {
        $this->requireCsrf();
        $equipoModel = new Equipo();
        $equipo      = $equipoModel->ficha($id) ?? $this->notFound();
        $mantModel   = new Mantenimiento();
        $abierto     = $mantModel->abiertoDeEquipo($id) ?? $this->redirect("/tecnico/equipos/$id");

        $motivo = trim((string) ($_POST['motivo_cancelacion'] ?? ''));
        if (mb_strlen($motivo) < 5) {
            Session::flash('error', 'Escribe el motivo de anulación (al menos 5 caracteres).');
            $this->redirect("/tecnico/equipos/$id");
        }

        $usuario = Auth::user();
        $mantModel->transaccion(function () use ($mantModel, $equipoModel, $id, $equipo, $abierto, $motivo, $usuario) {
            $mantModel->cancelar((int) $abierto['id'], $motivo);
            $equipoModel->cambiarEstadoOperativo($id, 'operativo');
            (new Auditoria())->registrar((int) $usuario['id'], 'mantenimientos', (int)$abierto['id'], 'anular', [
                'equipo' => $equipo['codigo'],
                'motivo' => $motivo,
            ]);
        });

        Session::flash('ok', "Se anuló la solicitud de «{$equipo['codigo']}» y el equipo volvió a estado operativo.");
        $this->redirect("/tecnico/equipos/$id");
    }

    /** GET /tecnico/equipos/{id}/etiqueta — Vista lista para imprimir la etiqueta patrimonial con QR. */
    public function etiqueta(int $id): void
    {
        $equipo = (new Equipo())->ficha($id) ?? $this->notFound();

        $this->view('tecnico/etiqueta', [
            'titulo'  => 'Imprimir etiqueta · ' . $equipo['codigo'],
            'equipo'  => $equipo,
            'estilos' => ['docente.css', 'tecnico.css'],
            'scripts' => ['vendor/qrcode.min.js', 'tecnico.js'],
        ], 'panel');
    }

    /** GET /archivos/{id} — Sirve fotografías de evidencia de forma segura. */
    public function archivo(int $id): void
    {
        $archivo = (new Archivo())->porId($id) ?? $this->notFound();
        $ruta    = Archivo::directorio() . '/' . basename($archivo['ruta_archivo']);
        if (!is_file($ruta)) {$this->notFound();
        }

        header('Content-Type: ' . $archivo['mime_type']);
        header('Content-Length: ' . filesize($ruta));
        header('Cache-Control: private, max-age=86400');
        readfile($ruta);
        exit;
    }

    private function buscarEquipoPorToken(string $token): array
    {
        if (!preg_match('/^[a-f0-9]{16,64}$/i', $token)) {$this->notFound();
        }
        return (new Equipo())->porQrToken($token) ?? $this->notFound();
    }

    private function leerNuevosComponentes(array $post): array
    {
        $tipos   =$post['comp_tipo'] ?? [];
        $marcas  =$post['comp_marca'] ?? [];
        $modelos =$post['comp_modelo'] ?? [];
        $series  =$post['comp_serie'] ?? [];
        $caps    =$post['comp_capacidad'] ?? [];
        $obs     =$post['comp_obs'] ?? [];

        if (!is_array($tipos)) {
            return [];
        }

        $lista = [];
        foreach ($tipos as $i =>$t) {
            $tipo = trim((string)$t);
            if ($tipo === '') {
                continue;
            }$lista[] = [
                'tipo'          => mb_substr($tipo, 0, 100),
                'marca'         => mb_substr(trim((string) ($marcas[$i] ?? '')), 0, 100),
                'modelo'        => mb_substr(trim((string) ($modelos[$i] ?? '')), 0, 120),
                'numero_serie'  => mb_substr(trim((string) ($series[$i] ?? '')), 0, 120),
                'capacidad'     => mb_substr(trim((string) ($caps[$i] ?? '')), 0, 100),
                'observaciones' => mb_substr(trim((string) ($obs[$i] ?? '')), 0, 500),
            ];
        }
        return $lista;
    }

    private function leerRepuestosUsados(array $post): array
    {
        $ids     =$post['rep_id'] ?? [];
        $nuevos  =$post['rep_nuevo'] ?? [];
        $cants   =$post['rep_cantidad'] ?? [];
        $costos  =$post['rep_costo'] ?? [];
        $detalle =$post['rep_detalle'] ?? [];

        if (!is_array($ids)) {
            return [];
        }

        $lista = [];
        foreach ($ids as $i =>$rawId) {
            $repId = (int)$rawId;
            $nuevo = mb_substr(trim((string) ($nuevos[$i] ?? '')), 0, 150);
            if ($repId <= 0 && $nuevo === '') {
                continue;
            }$lista[] = [
                'repuesto_id'    => $repId > 0 ?$repId : null,
                'nombre_nuevo'   => $nuevo,
                'cantidad'       => max(0.01, (float) ($cants[$i] ?? 1)),
                'costo_unitario' => max(0, (float) ($costos[$i] ?? 0)),
                'detalle'        => mb_substr(trim((string) ($detalle[$i] ?? '')), 0, 255),
            ];
        }
        return $lista;
    }
}