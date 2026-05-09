<?php

namespace App\Services;

use App\Models\Contacto;
use App\Models\DocumentoPaciente;
use Illuminate\Support\Facades\Log;

/**
 * Servicio centralizado de storage para documentos del legajo.
 *
 * El path base es configurable desde /admin/legajo (o vía env LEGAJO_STORAGE_PATH).
 * Por defecto: storage/app/private/documentos.
 *
 * Layout: <basePath>/<contacto_id>/<año>/<hash>.<ext>
 */
class LegajoStorage
{
    /** Path base donde se guardan los documentos. */
    public static function basePath(): string
    {
        $configurado = config('app.legajo_storage_path');
        if ($configurado) return $configurado;
        return storage_path('app/private/documentos');
    }

    /**
     * Indexa un archivo en el legajo: copia el archivo al path final, crea el registro.
     *
     * @param string $sourceAbs Path absoluto del archivo origen.
     * @param array $meta {
     *   contacto_id?: int, conversacion_id?: int, mensaje_id?: int,
     *   direccion: 'entrante'|'saliente'|'manual',
     *   usuario_id?: int,
     *   mime: string, nombre_original: string,
     *   destacado?: bool, notas?: string,
     * }
     * @param bool $movePreserveSource Si true, copia (deja el original); si false, mueve.
     */
    public static function indexar(string $sourceAbs, array $meta, bool $movePreserveSource = true): ?DocumentoPaciente
    {
        if (!file_exists($sourceAbs) || !is_readable($sourceAbs)) {
            Log::warning('LegajoStorage::indexar source inválido', ['src' => $sourceAbs]);
            return null;
        }

        $contactoId = $meta['contacto_id'] ?? null;
        $mime       = $meta['mime'];
        $nombreOrig = $meta['nombre_original'];
        $tipo       = DocumentoPaciente::tipoDesdeMime($mime);

        // Hash del contenido para evitar colisiones y duplicar el mismo archivo bajo nombre distinto.
        $hash = substr(hash_file('sha256', $sourceAbs), 0, 24);
        $ext  = pathinfo($nombreOrig, PATHINFO_EXTENSION) ?: self::extDesdeMime($mime);
        $nombreStorage = $hash . ($ext ? '.' . $ext : '');

        $year = now()->format('Y');
        $subdir = ($contactoId ? (int) $contactoId : 'sin-contacto') . '/' . $year;
        $relPath = $subdir . '/' . $nombreStorage;

        $destDir = self::basePath() . '/' . $subdir;
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0775, true);
            self::asegurarOwnerWeb($destDir);
        }
        $destAbs = self::basePath() . '/' . $relPath;

        // Si ya existe el archivo (mismo hash) NO lo re-copiamos.
        if (!file_exists($destAbs)) {
            if ($movePreserveSource) {
                if (!@copy($sourceAbs, $destAbs)) {
                    Log::warning('LegajoStorage::indexar copy fallo', ['src' => $sourceAbs, 'dest' => $destAbs]);
                    return null;
                }
            } else {
                if (!@rename($sourceAbs, $destAbs)) {
                    // Si rename falla (cross-device link), copy + unlink
                    if (!@copy($sourceAbs, $destAbs)) return null;
                    @unlink($sourceAbs);
                }
            }
            self::asegurarOwnerWeb($destAbs);
        }

        // Si ya existe un DocumentoPaciente con el mismo hash + contacto_id, no duplicamos.
        $existing = DocumentoPaciente::where('contacto_id', $contactoId)
            ->where('nombre_storage', $nombreStorage)
            ->first();
        if ($existing) return $existing;

        $doc = DocumentoPaciente::create([
            'contacto_id'     => $contactoId,
            'conversacion_id' => $meta['conversacion_id'] ?? null,
            'mensaje_id'      => $meta['mensaje_id'] ?? null,
            'direccion'       => $meta['direccion'],
            'usuario_id'      => $meta['usuario_id'] ?? null,
            'tipo'            => $tipo,
            'mime'            => $mime,
            'nombre_original' => $nombreOrig,
            'nombre_storage'  => $nombreStorage,
            'path'            => $relPath,
            'tamanio_bytes'   => @filesize($destAbs) ?: 0,
            'destacado'       => $meta['destacado'] ?? false,
            'notas'           => $meta['notas'] ?? null,
        ]);

        // OCR sincrónico para PDFs e imágenes pequeños (< 5 MB) — los grandes los procesa el cron.
        if (in_array($tipo, ['imagen', 'documento'], true) && $doc->tamanio_bytes < 5 * 1024 * 1024) {
            try {
                $texto = OcrService::extraer($destAbs, $mime);
                if ($texto !== null) {
                    $doc->update(['texto_ocr' => $texto, 'ocr_at' => now()]);
                }
            } catch (\Exception $e) {
                Log::info('OCR async fallo silencioso', ['doc' => $doc->id, 'err' => $e->getMessage()]);
            }
        }

        return $doc;
    }

    /** Borra el archivo físico y el registro. */
    public static function eliminar(DocumentoPaciente $doc): bool
    {
        $abs = $doc->pathAbsoluto();
        if (file_exists($abs)) {
            @unlink($abs);
        }
        return (bool) $doc->delete();
    }

    /** Asegura que la carpeta base exista y sea escribible. */
    public static function asegurarBasePath(): bool
    {
        $base = self::basePath();
        if (!is_dir($base)) {
            @mkdir($base, 0775, true);
            self::asegurarOwnerWeb($base);
        }
        return is_dir($base) && is_writable($base);
    }

    /**
     * Si estamos corriendo como root (típico de comandos artisan vía `docker exec` sin --user),
     * los archivos/dirs creados quedan owner root y PHP-FPM (www-data) no puede tocarlos.
     * Acá los pasamos a www-data:www-data para evitar el bug histórico.
     *
     * Si ya corremos como www-data, los chown silenciosamente no hacen nada (no es root).
     */
    private static function asegurarOwnerWeb(string $path): void
    {
        if (!function_exists('posix_geteuid') || posix_geteuid() !== 0) return;
        if (!file_exists($path)) return;
        @chown($path, 'www-data');
        @chgrp($path, 'www-data');
    }

    private static function extDesdeMime(string $mime): string
    {
        return [
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'audio/mpeg' => 'mp3', 'audio/ogg' => 'ogg', 'audio/wav' => 'wav', 'audio/mp4' => 'm4a',
            'video/mp4' => 'mp4', 'video/webm' => 'webm',
            'text/plain' => 'txt',
        ][$mime] ?? '';
    }
}
