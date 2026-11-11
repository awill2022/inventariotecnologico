<?php
/**
 * Tabla `areas`: departamentos, carreras y unidades. Es jerárquica:
 * cada área puede depender de otra (area_padre_id).
 */
final class Area extends Catalogo
{
    public const TIPOS = [
        'departamento' => 'Departamento',
        'carrera'      => 'Carrera',
        'unidad'       => 'Unidad',
        'otro'         => 'Otro',
    ];

    protected string $tabla     = 'areas';
    protected array  $columnas  = ['nombre', 'tipo', 'area_padre_id'];
    protected array  $buscables = ['t.nombre', 'p.nombre'];

    protected function seleccion(): string
    {
        return 't.*, p.nombre AS padre';
    }

    protected function desde(): string
    {
        return 'FROM areas t LEFT JOIN areas p ON p.id = t.area_padre_id';
    }

    /**
     * Ids de todas las áreas que cuelgan de $id (hijas, nietas, ...).
     * Sirve para impedir ciclos al elegir el área padre.
     */
    public function descendientes(int $id): array
    {
        $hijasDe = [];
        foreach ($this->fetchAll('SELECT id, area_padre_id FROM areas WHERE area_padre_id IS NOT NULL') as $fila) {
            $hijasDe[(int) $fila['area_padre_id']][] = (int) $fila['id'];
        }

        $encontradas = [];
        $pendientes  = [$id];
        while ($pendientes) {
            $actual = array_pop($pendientes);
            foreach ($hijasDe[$actual] ?? [] as $hija) {
                if (!isset($encontradas[$hija])) {
                    $encontradas[$hija] = true;
                    $pendientes[]       = $hija;
                }
            }
        }
        return array_keys($encontradas);
    }

    public function dependencias(int $id): array
    {
        $frases = [];

        $n = $this->contar('SELECT COUNT(*) AS n FROM areas WHERE area_padre_id = :id AND activo = 1', $id);
        if ($n) {
            $frases[] = Formato::plural($n, 'área dependiente activa', 'áreas dependientes activas');
        }
        $n = $this->contar('SELECT COUNT(*) AS n FROM ubicaciones WHERE area_id = :id AND activo = 1', $id);
        if ($n) {
            $frases[] = Formato::plural($n, 'ubicación activa', 'ubicaciones activas');
        }
        $n = $this->contar('SELECT COUNT(*) AS n FROM personas WHERE area_id = :id AND activo = 1', $id);
        if ($n) {
            $frases[] = Formato::plural($n, 'persona activa', 'personas activas');
        }
        $n = $this->contar(
            "SELECT COUNT(*) AS n FROM equipos WHERE area_propietaria_id = :id AND estado_inventario <> 'de_baja'",
            $id
        );
        if ($n) {
            $frases[] = Formato::plural($n, 'equipo vigente', 'equipos vigentes');
        }
        return $frases;
    }
}
