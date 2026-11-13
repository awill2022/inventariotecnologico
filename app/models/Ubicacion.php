<?php
/**
 * Tabla `ubicaciones`: lugares físicos (aulas, laboratorios, bodegas) dentro de una sede.
 */
final class Ubicacion extends Catalogo
{
    public const TIPOS = [
        'oficina'     => 'Oficina',
        'laboratorio' => 'Laboratorio',
        'aula'        => 'Aula',
        'bodega'      => 'Bodega',
        'sala'        => 'Sala',
        'otro'        => 'Otro',
    ];

    protected string $tabla     = 'ubicaciones';
    protected array  $columnas  = ['sede_id', 'area_id', 'nombre', 'tipo', 'edificio', 'piso', 'descripcion'];
    protected array  $buscables = ['t.nombre', 't.edificio', 's.nombre', 'a.nombre'];
    protected string $orden     = 's.nombre, t.nombre';

    protected function seleccion(): string
    {
        return "t.*, s.nombre AS sede, a.nombre AS area,
                NULLIF(CONCAT_WS(' · ', NULLIF(t.edificio, ''), IF(t.piso IS NULL OR t.piso = '', NULL, CONCAT('Piso ', t.piso))), '') AS lugar";
    }

    protected function desde(): string
    {
        return 'FROM ubicaciones t
                JOIN sedes s ON s.id = t.sede_id
           LEFT JOIN areas a ON a.id = t.area_id';
    }

    /**
     * Opciones [id => "Ubicación · Sede"] de las ubicaciones activas (las bodegas primero),
     * para elegir dónde está un equipo. $incluirId agrega esa aunque esté inactiva.
     */
    public function opcionesConSede(?int $incluirId = null): array
    {
        $filas = $this->fetchAll(
            "SELECT u.id, CONCAT(u.nombre, ' · ', s.nombre) AS texto
               FROM ubicaciones u
               JOIN sedes s ON s.id = u.sede_id
              WHERE u.activo = 1 OR u.id <=> :incluir
           ORDER BY u.tipo <> 'bodega', s.nombre, u.nombre",
            [':incluir' => $incluirId]
        );
        return array_column($filas, 'texto', 'id');
    }

    public function dependencias(int $id): array
    {
        $n = $this->contar(
            "SELECT COUNT(*) AS n FROM equipos WHERE ubicacion_actual_id = :id AND estado_inventario <> 'de_baja'",
            $id
        );
        return $n ? [Formato::plural($n, 'equipo ubicado aquí', 'equipos ubicados aquí')] : [];
    }

    /** true si la ubicación es de tipo bodega (el equipo que llega queda "En bodega"). */
    public function esBodega(int $id): bool
    {
        return $this->fetchOne("SELECT 1 FROM ubicaciones WHERE id = :id AND tipo = 'bodega'", [':id' => $id]) !== null;
    }

    /** Primera bodega activa: destino propuesto de una devolución. */
    public function bodegaPrincipal(): ?int
    {
        $fila = $this->fetchOne("SELECT id FROM ubicaciones WHERE tipo = 'bodega' AND activo = 1 ORDER BY id LIMIT 1");
        return $fila ? (int) $fila['id'] : null;
    }
}
