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

    public function dependencias(int $id): array
    {
        $n = $this->contar(
            "SELECT COUNT(*) AS n FROM equipos WHERE ubicacion_actual_id = :id AND estado_inventario <> 'de_baja'",
            $id
        );
        return $n ? [Formato::plural($n, 'equipo ubicado aquí', 'equipos ubicados aquí')] : [];
    }
}
