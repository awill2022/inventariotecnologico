<?php
/**
 * Recepción segura de archivos subidos (fotos de fallas, actas firmadas).
 *
 * - El tipo real se toma del contenido con finfo (no del nombre ni de lo que diga el navegador).
 * - Las imágenes además se comprueban con getimagesize() y los PDF por su cabecera.
 * - Se guardan con un nombre aleatorio en storage/archivos/AAAA/MM/, fuera de public/.
 * - Sin GD: no se redimensiona en el servidor; las fotos ya llegan reducidas desde el navegador.
 */
final class Subida
{
    /** Tipos admitidos: mime => extensión con la que se guarda. */
    public const FOTOS      = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    public const DOCUMENTOS = ['application/pdf' => 'pdf'];

    /**
     * Lista de archivos enviados en un campo, sea simple (name="acta") o múltiple (name="fotos[]").
     * Omite las casillas que llegaron vacías.
     * @return array<int, array{name: string, type: string, tmp_name: string, error: int, size: int}>
     */
    public static function lista(string $campo): array
    {
        $f = $_FILES[$campo] ?? null;
        if (!is_array($f) || !isset($f['error'])) {
            return [];
        }
        if (!is_array($f['error'])) {
            return $f['error'] === UPLOAD_ERR_NO_FILE ? [] : [$f];
        }

        $lista = [];
        foreach ($f['error'] as $i => $error) {
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $lista[] = [
                'name'     => (string) ($f['name'][$i] ?? ''),
                'type'     => (string) ($f['type'][$i] ?? ''),
                'tmp_name' => (string) ($f['tmp_name'][$i] ?? ''),
                'error'    => (int) $error,
                'size'     => (int) ($f['size'][$i] ?? 0),
            ];
        }
        return $lista;
    }

    /**
     * Revisa un archivo recibido contra los tipos permitidos.
     * @param array $permitidos mime => extensión (FOTOS, DOCUMENTOS o ambos unidos)
     * @return array{error: ?string, mime?: string, ext?: string, tamano?: int, nombre?: string}
     */
    public static function validar(array $f, array $permitidos): array
    {
        $nombre = self::nombreLimpio($f['name']);

        switch ($f['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['error' => "«{$nombre}» pesa más de lo permitido."];
            case UPLOAD_ERR_PARTIAL:
                return ['error' => "«{$nombre}» no llegó completo. Vuelve a adjuntarlo."];
            default:
                return ['error' => "No pudimos recibir «{$nombre}». Inténtalo de nuevo."];
        }
        if (!is_uploaded_file($f['tmp_name']) || $f['size'] <= 0) {
            return ['error' => "No pudimos recibir «{$nombre}». Inténtalo de nuevo."];
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
        if (!isset($permitidos[$mime])) {
            return ['error' => "«{$nombre}» no es un formato admitido (" . self::formatos($permitidos) . ').'];
        }

        $esFoto = isset(self::FOTOS[$mime]);
        $maximo = (int) App::config($esFoto ? 'archivos.max_foto' : 'archivos.max_documento');
        if ($f['size'] > $maximo) {
            return ['error' => "«{$nombre}» pesa " . Formato::bytes($f['size']) . '; el máximo es ' . Formato::bytes($maximo) . '.'];
        }

        if ($esFoto && @getimagesize($f['tmp_name']) === false) {
            return ['error' => "«{$nombre}» no es una imagen válida."];
        }
        if (!$esFoto && file_get_contents($f['tmp_name'], false, null, 0, 5) !== '%PDF-') {
            return ['error' => "«{$nombre}» no es un PDF válido."];
        }

        return ['error' => null, 'mime' => $mime, 'ext' => $permitidos[$mime], 'tamano' => (int) $f['size'], 'nombre' => $nombre];
    }

    /** Mueve el archivo a storage/archivos/AAAA/MM/{aleatorio}.{ext} y devuelve la ruta relativa que se guarda en la BD. */
    public static function guardar(array $f, string $ext): string
    {
        $subcarpeta = date('Y/m');
        $carpeta    = self::carpeta() . '/' . $subcarpeta;
        if (!is_dir($carpeta) && !mkdir($carpeta, 0775, true) && !is_dir($carpeta)) {
            throw new RuntimeException("No se pudo crear la carpeta $carpeta");
        }

        $relativa = $subcarpeta . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], self::carpeta() . '/' . $relativa)) {
            throw new RuntimeException('No se pudo guardar el archivo subido');
        }
        return $relativa;
    }

    /** Ruta en disco de un archivo guardado, o null si la ruta relativa no tiene el formato esperado. */
    public static function rutaAbsoluta(string $relativa): ?string
    {
        if (!preg_match('#^\d{4}/\d{2}/[a-f0-9]{32}\.(jpg|png|webp|pdf)$#', $relativa)) {
            return null;
        }
        return self::carpeta() . '/' . $relativa;
    }

    /** Borra del disco un archivo guardado (si existe). */
    public static function borrar(string $relativa): void
    {
        $ruta = self::rutaAbsoluta($relativa);
        if ($ruta !== null && is_file($ruta)) {
            @unlink($ruta);
        }
    }

    /** true si el último envío superó post_max_size: PHP descarta todo $_POST y $_FILES. */
    public static function envioDemasiadoGrande(): bool
    {
        $largo = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $largo > 0 && !$_POST && !$_FILES
            && $largo > self::enBytes((string) ini_get('post_max_size'));
    }

    public static function esFoto(string $mime): bool
    {
        return isset(self::FOTOS[$mime]);
    }

    /** "JPG, PNG o WebP" */
    public static function formatos(array $permitidos): string
    {
        $nombres = array_map(fn ($ext) => $ext === 'webp' ? 'WebP' : strtoupper($ext), array_values($permitidos));
        $ultimo  = array_pop($nombres);
        return $nombres ? implode(', ', $nombres) . ' o ' . $ultimo : $ultimo;
    }

    /** Nombre original sin carpetas ni caracteres de control, para mostrarlo y para la descarga. */
    private static function nombreLimpio(string $nombre): string
    {
        $nombre = basename(str_replace('\\', '/', $nombre));
        $nombre = preg_replace('/[\x00-\x1F\x7F"]+/u', '', $nombre) ?? '';
        $nombre = trim($nombre);
        return $nombre === '' ? 'archivo' : mb_substr($nombre, -200);
    }

    private static function carpeta(): string
    {
        return rtrim((string) App::config('archivos.carpeta'), '/\\');
    }

    /** "40M" → bytes */
    private static function enBytes(string $valor): int
    {
        $valor = trim($valor);
        $numero = (int) $valor;
        return match (strtoupper(substr($valor, -1))) {
            'G'     => $numero * 1024 ** 3,
            'M'     => $numero * 1024 ** 2,
            'K'     => $numero * 1024,
            default => $numero,
        };
    }
}
