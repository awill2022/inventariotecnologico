<?php
/**
 * Tabla `auditoria`: registro de acciones de los usuarios.
 */
final class Auditoria extends Model
{
    /** @param string $accion crear | editar | cerrar | anular | acceder */
    public function registrar(?int $usuarioId, string $entidad, int $entidadId, string $accion, array $detalle = []): void
    {
        $detalle += [
            'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ];

        $this->execute(
            'INSERT INTO auditoria (usuario_id, entidad, entidad_id, accion, detalle)
             VALUES (:usuario, :entidad, :entidad_id, :accion, :detalle)',
            [
                ':usuario'    => $usuarioId,
                ':entidad'    => $entidad,
                ':entidad_id' => $entidadId,
                ':accion'     => $accion,
                ':detalle'    => json_encode($detalle, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            ]
        );
    }
}
