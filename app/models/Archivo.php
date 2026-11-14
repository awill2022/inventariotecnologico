<?php
/**
 * Tabla `archivos`: fotografías de evidencia de reportes QR y mantenimientos.
 */
final class Archivo extends Model
{
    private const MAX_BYTES = 5 * 1024 * 1024; // 5 MB por foto
    private const MIMES     = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    public static function directorio(): string
    {
        return dirname(APP_PATH) . '/storage/archivos';
    }

    public function porId(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM archivos WHERE id = :id', [':id' => $id]);
    }

    public function deEquipo(int $equipoId): array
    {
        return $this->fetchAll(
            'SELECT * FROM archivos WHERE equipo_id = :equipo ORDER BY id DESC',
            [':equipo' => $equipoId]
        );
    }

    /**
     * Valida y guarda hasta 3 fotografías subidas en $_FILES['fotos'].
     * @return array{0: array<int>, 1: ?string} [ids creados, mensaje de error si falló alguna]
     */
    public function guardarFotosSubidas(
        int $equipoId,
        ?int $mantenimientoId,
        array $archivosSubidos,
        ?int $usuarioId,
        string $tipo = 'reporte_falla',
        ?string $descripcion = null
    ): array {
        if (empty($archivosSubidos['name']) || !is_array($archivosSubidos['name'])) {
            return [[], null];
        }

        $dir = self::directorio();
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return [[], 'No se pudo preparar la carpeta de almacenamiento de imágenes.'];
        }

        $finfo   = new finfo(FILEINFO_MIME_TYPE);
        $creados = [];
        $total   = min(3, count($archivosSubidos['name']));

        for ($i = 0; $i < $total; $i++) {
            $error = $archivosSubidos['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($error !== UPLOAD_ERR_OK) {
                return [$creados, 'Una de las fotografías no se pudo subir (máximo 5 MB por imagen).'];
            }

            $tmp  = $archivosSubidos['tmp_name'][$i];
            $size = (int) ($archivosSubidos['size'][$i] ?? 0);
            if ($size <= 0 || $size > self::MAX_BYTES) {
                return [$creados, 'Cada fotografía debe pesar menos de 5 MB.'];
            }

            $mime = $finfo->file($tmp) ?: '';
            if (!isset(self::MIMES[$mime])) {
                return [$creados, 'Formato de imagen no admitido. Usa fotos JPG, PNG o WebP.'];
            }

            $ext          = self::MIMES[$mime];
            $nombreSeguro = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $destino      = $dir . '/' . $nombreSeguro;

            if (!move_uploaded_file($tmp, $destino)) {
                return [$creados, 'No se pudo guardar la fotografía en el servidor.'];
            }

            $original = mb_substr(basename((string) $archivosSubidos['name'][$i]), 0, 255);
            $this->execute(
                "INSERT INTO archivos
                    (equipo_id, mantenimiento_id, clase, tipo, nombre_original,
                     ruta_archivo, mime_type, tamano_bytes, descripcion, subido_por)
                 VALUES
                    (:equipo, :mant, 'fotografia', :tipo, :orig,
                     :ruta, :mime, :bytes, :desc, :usuario)",
                [
                    ':equipo'  => $equipoId,
                    ':mant'    => $mantenimientoId,
                    ':tipo'    => $tipo,
                    ':orig'    => $original ?: $nombreSeguro,
                    ':ruta'    => $nombreSeguro,
                    ':mime'    => $mime,
                    ':bytes'   => $size,
                    ':desc'    => $descripcion,
                    ':usuario' => $usuarioId,
                ]
            );
            $creados[] = (int) $this->db->lastInsertId();
        }

        return [$creados, null];
    }
}