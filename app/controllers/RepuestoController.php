<?php
/**
 * Catálogo de repuestos con su stock (migración 003).
 * Listar, crear, editar, activar/desactivar (flujo de CatalogoController) y «Agregar stock».
 *
 * El stock no se edita a mano: se fija al crear el repuesto, sube con «Agregar stock» y
 * baja o sube al usarlo en un mantenimiento (MantenimientoController).
 *
 * Ver: los tres roles del panel. Gestionar: administrador y técnico.
 */
final class RepuestoController extends CatalogoController
{
    public const VER       = ['administrador', 'inventario', 'tecnico'];
    public const GESTIONAR = ['administrador', 'tecnico'];

    /** Unidades frecuentes que se sugieren al escribir. */
    private const UNIDADES = ['unidad', 'par', 'juego', 'kit', 'caja', 'metro', 'rollo', 'litro', 'frasco'];

    protected function modelo(): Catalogo
    {
        return new Repuesto();
    }

    protected function rolesEditar(): array
    {
        return self::GESTIONAR;
    }

    protected function def(): array
    {
        return [
            'ruta'        => '/repuestos',
            'entidad'     => 'repuestos',
            'titulo'      => 'Repuestos',
            'descripcion' => 'Piezas e insumos para los mantenimientos. Al usarlos en un trabajo se descuentan del stock.',
            'singular'    => 'repuesto',
            'articulo'    => 'el',
            'nuevo'       => 'Nuevo repuesto',
            'buscar'      => 'Buscar por nombre, código o descripción',
            'vacio'       => 'Todavía no hay repuestos registrados.',
            'vacio_nota'  => 'El técnico o el administrador los registran con su stock inicial.',
            'aviso_desactivar' => 'Ya no se podrá usar en mantenimientos nuevos, pero su historial y su stock se conservan.',
            'acciones'    => [
                ['texto' => 'Agregar stock', 'ruta' => '/repuestos/{id}/stock', 'icono' => 'plus'],
            ],
            'columnas'    => [
                ['clave' => 'nombre',            'titulo' => 'Nombre'],
                ['clave' => 'codigo',            'titulo' => 'Código'],
                ['clave' => 'unidad',            'titulo' => 'Unidad'],
                ['clave' => 'stock',             'titulo' => 'Stock', 'numero' => true, 'formato' => 'cantidad',
                 'alerta' => ['clave' => 'bajo_minimo', 'texto' => 'en el mínimo, reponer']],
                ['clave' => 'stock_minimo',      'titulo' => 'Mínimo', 'numero' => true, 'formato' => 'cantidad'],
                ['clave' => 'costo_referencial', 'titulo' => 'Costo ref.', 'numero' => true, 'formato' => 'dinero'],
            ],
        ];
    }

    protected function campos(?int $id): array
    {
        $stock = $id
            ? ['nombre' => 'stock', 'etiqueta' => 'Stock actual', 'tipo' => 'fijo',
               'valor' => self::stockTexto($this->modelo()->porId($id) ?? []),
               'ayuda' => 'Cambia con «Agregar stock» y al usar el repuesto en un mantenimiento.']
            : ['nombre' => 'stock', 'etiqueta' => 'Stock inicial', 'tipo' => 'numero', 'max_valor' => 99999999,
               'ayuda' => 'Cuántos hay hoy en bodega. Si lo dejas vacío, empieza en 0.'];

        return [
            ['nombre' => 'nombre', 'etiqueta' => 'Nombre', 'tipo' => 'text', 'requerido' => true, 'min' => 3, 'max' => 150,
             'ayuda' => 'Por ejemplo: Memoria RAM DDR4 8 GB, Pasta térmica, Tóner HP 85A.'],
            ['nombre' => 'codigo', 'etiqueta' => 'Código', 'tipo' => 'text', 'max' => 80, 'formato' => 'codigo',
             'ayuda' => 'Código interno o de fabricante, sin espacios. Por ejemplo: RAM-DDR4-8.'],
            ['nombre' => 'unidad', 'etiqueta' => 'Unidad de medida', 'tipo' => 'text', 'requerido' => true, 'max' => 30,
             'sugerencias' => self::UNIDADES],
            $stock,
            ['nombre' => 'stock_minimo', 'etiqueta' => 'Stock mínimo', 'tipo' => 'numero', 'max_valor' => 99999999,
             'ayuda' => 'Al llegar a esta cantidad el listado avisa que toca reponer.'],
            ['nombre' => 'costo_referencial', 'etiqueta' => 'Costo de referencia por unidad (USD)', 'tipo' => 'numero',
             'max_valor' => 9999999999, 'ayuda' => 'Se propone como costo al usarlo en un mantenimiento.'],
            ['nombre' => 'descripcion', 'etiqueta' => 'Descripción', 'tipo' => 'textarea', 'max' => 1000],
        ];
    }

    protected function validarExtra(array $datos, ?int $id): array
    {
        $errores = [];
        if ($datos['nombre'] !== null && $this->modelo()->existe(['nombre' => $datos['nombre']], $id)) {
            $errores['nombre'] = 'Ya existe un repuesto con ese nombre.';
        }
        if ($datos['codigo'] !== null && $this->modelo()->existe(['codigo' => $datos['codigo']], $id)) {
            $errores['codigo'] = 'Otro repuesto ya usa ese código.';
        }
        return $errores;
    }

    /** GET /repuestos/{id}/stock */
    public function stock(int $id): void
    {
        $r = $this->modelo()->porId($id) ?? $this->notFound();
        $this->exigirActivo($r);

        $this->view('catalogos/form', [
            'titulo'     => 'Agregar stock',
            'def'        => $this->def(),
            'id'         => $id,
            'accion'     => "/repuestos/$id/stock",
            'encabezado' => "Agregar stock de «{$r['nombre']}»",
            'intro'      => 'Registra lo que llegó a bodega. Hoy hay ' . self::stockTexto($r) . '.',
            'boton'      => 'Agregar al stock',
            'campos'     => $this->camposStock($r),
            'valores'    => Session::flash('form_old') ?? ['costo_unitario' => $r['costo_referencial']],
            'errores'    => Session::flash('form_errores') ?? [],
            'csrf'       => Session::csrfToken(),
            'scripts'    => ['panel.js'],
        ], 'panel');
    }

    /** POST /repuestos/{id}/stock */
    public function agregarStock(int $id): void
    {
        $this->requireCsrf();
        $modelo = new Repuesto();
        $r      = $modelo->porId($id) ?? $this->notFound();
        $this->exigirActivo($r);

        [$datos, $errores] = Validador::validar($this->camposStock($r), $_POST);
        if ($datos['cantidad'] !== null && !isset($errores['cantidad']) && (float) $datos['cantidad'] <= 0) {
            $errores['cantidad'] = 'La cantidad debe ser mayor que 0.';
        }
        if ($errores) {
            $this->volverAlFormulario("/repuestos/$id/stock", $datos, $errores);
        }

        try {
            $despues = $modelo->transaccion(function () use ($modelo, $id, $datos) {
                $antes = $modelo->bloquear($id);
                $modelo->reponer($id, $datos['cantidad']);
                $cambios = ['stock' => [$antes['stock'], $modelo->bloquear($id)['stock']]];
                if ($datos['costo_unitario'] !== null && (string) $antes['costo_referencial'] !== $datos['costo_unitario']) {
                    $modelo->fijarCosto($id, $datos['costo_unitario']);
                    $cambios['costo_referencial'] = [$antes['costo_referencial'], $datos['costo_unitario']];
                }
                (new Auditoria())->registrar(Auth::user()['id'], 'repuestos', $id, 'editar', [
                    'ingreso' => $datos['cantidad'], 'nota' => $datos['nota'], 'cambios' => $cambios,
                ]);
                return $modelo->porId($id);
            });
        } catch (PDOException $e) {
            error_log('[repuestos.stock] ' . $e->getMessage());
            Session::flash('error', 'No pudimos agregar el stock. Inténtalo de nuevo en unos minutos.');
            $this->volverAlFormulario("/repuestos/$id/stock", $datos, []);
        }

        Session::flash('ok', 'Se agregaron ' . Formato::conUnidad($datos['cantidad'], $r['unidad']) . " de «{$r['nombre']}». "
            . 'Ahora hay ' . self::stockTexto($despues) . '.');
        $this->redirect('/repuestos');
    }

    // ------------------------------------------------------------------

    private function camposStock(array $r): array
    {
        return [
            ['nombre' => 'cantidad', 'etiqueta' => "Cantidad que llegó ({$r['unidad']})", 'tipo' => 'numero',
             'requerido' => true, 'max_valor' => 99999999],
            ['nombre' => 'costo_unitario', 'etiqueta' => 'Costo por unidad (USD)', 'tipo' => 'numero', 'max_valor' => 9999999999,
             'ayuda' => 'Si cambió, se guarda como nuevo costo de referencia.'],
            ['nombre' => 'nota', 'etiqueta' => 'Nota', 'tipo' => 'text', 'max' => 255,
             'ayuda' => 'Por ejemplo: número de factura u orden de compra, proveedor.'],
        ];
    }

    /** "4 unidades" / "1 frasco" */
    public static function stockTexto(array $r): string
    {
        return Formato::conUnidad($r['stock'] ?? '0', $r['unidad'] ?? '');
    }

    private function exigirActivo(array $r): void
    {
        if (!$r['activo']) {
            Session::flash('error', "«{$r['nombre']}» está inactivo: actívalo antes de agregar stock.");
            $this->redirect('/repuestos');
        }
    }
}
