<?php
/**
 * Tabla `especificaciones`: pares atributo–valor de un equipo (RAM, procesador, IP...).
 * El atributo no se repite en un mismo equipo (uq_esp_atributo).
 */
final class Especificacion extends Model
{
    public const MAX_ATRIBUTO = 100;
    public const MAX_VALOR    = 500;

    /** Especificaciones del equipo en el orden en que se registraron. */
    public function deEquipo(int $equipoId): array
    {
        return $this->fetchAll(
            'SELECT id, atributo, valor FROM especificaciones WHERE equipo_id = :id ORDER BY id',
            [':id' => $equipoId]
        );
    }

    /** Atributos ya usados en otros equipos, para sugerirlos al escribir. */
    public function atributosConocidos(): array
    {
        return array_column(
            $this->fetchAll('SELECT DISTINCT atributo FROM especificaciones ORDER BY atributo LIMIT 200'),
            'atributo'
        );
    }

    public function crear(int $equipoId, string $atributo, string $valor): int
    {
        $this->execute(
            'INSERT INTO especificaciones (equipo_id, atributo, valor) VALUES (:e, :a, :v)',
            [':e' => $equipoId, ':a' => $atributo, ':v' => $valor]
        );
        return (int) $this->db->lastInsertId();
    }

    public function actualizar(int $id, string $atributo, string $valor): void
    {
        $this->execute(
            'UPDATE especificaciones SET atributo = :a, valor = :v WHERE id = :id',
            [':a' => $atributo, ':v' => $valor, ':id' => $id]
        );
    }

    public function eliminar(int $id): void
    {
        $this->execute('DELETE FROM especificaciones WHERE id = :id', [':id' => $id]);
    }
}
