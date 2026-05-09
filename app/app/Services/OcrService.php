<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Extracción de texto de documentos para búsqueda full-text:
 *   - PDFs: primero intentamos pdftotext (instantáneo si ya tienen texto embebido).
 *           Si devuelve <50 chars, asumimos que es PDF escaneado y caemos a tesseract.
 *   - Imágenes (jpg/png/gif/webp): tesseract directo en español.
 *   - Otros mimes: null.
 *
 * Requiere en el container: tesseract-ocr, tesseract-ocr-spa, poppler-utils.
 */
class OcrService
{
    public const MAX_CHARS = 100_000;  // tope para no inflar la DB con PDFs gigantes

    public static function extraer(string $pathAbs, string $mime): ?string
    {
        if (!file_exists($pathAbs)) return null;

        try {
            if ($mime === 'application/pdf') {
                return self::desdePdf($pathAbs);
            }
            if (str_starts_with($mime, 'image/')) {
                return self::desdeImagen($pathAbs);
            }
            return null;
        } catch (\Throwable $e) {
            Log::warning('OcrService::extraer fallo', ['path' => $pathAbs, 'err' => $e->getMessage()]);
            return null;
        }
    }

    private static function desdePdf(string $pdf): ?string
    {
        // 1) pdftotext — extrae texto embebido. Si es PDF nativo, sale al toque.
        $tmpTxt = tempnam(sys_get_temp_dir(), 'pdftxt_') . '.txt';
        $cmd = sprintf('pdftotext -layout %s %s 2>&1', escapeshellarg($pdf), escapeshellarg($tmpTxt));
        @exec($cmd, $out, $rc);
        $texto = '';
        if ($rc === 0 && file_exists($tmpTxt)) {
            $texto = (string) @file_get_contents($tmpTxt);
            @unlink($tmpTxt);
        }
        if (mb_strlen(trim($texto)) >= 50) {
            return self::truncar($texto);
        }

        // 2) PDF probablemente escaneado — convertir cada página a imagen y tesseract.
        // Usamos pdftoppm para sacar PNGs de cada página.
        $tmpDir = sys_get_temp_dir() . '/ocr_' . uniqid();
        @mkdir($tmpDir, 0775, true);
        $prefix = $tmpDir . '/page';
        // -r 150 (DPI razonable, balance velocidad/precisión); limitamos a 10 páginas para no demorar
        $cmdImg = sprintf('pdftoppm -r 150 -l 10 -png %s %s 2>&1', escapeshellarg($pdf), escapeshellarg($prefix));
        @exec($cmdImg, $oImg, $rcImg);

        $textoOcr = '';
        foreach (glob($tmpDir . '/page-*.png') as $pageImg) {
            $textoOcr .= "\n" . self::tesseract($pageImg);
            @unlink($pageImg);
        }
        @rmdir($tmpDir);

        return self::truncar($textoOcr);
    }

    private static function desdeImagen(string $img): ?string
    {
        $texto = self::tesseract($img);
        return $texto ? self::truncar($texto) : null;
    }

    private static function tesseract(string $img): string
    {
        $tmpBase = tempnam(sys_get_temp_dir(), 'ocr_');
        $cmd = sprintf('tesseract %s %s -l spa 2>/dev/null', escapeshellarg($img), escapeshellarg($tmpBase));
        @exec($cmd, $out, $rc);
        $txt = $tmpBase . '.txt';
        $contenido = '';
        if ($rc === 0 && file_exists($txt)) {
            $contenido = (string) @file_get_contents($txt);
            @unlink($txt);
        }
        @unlink($tmpBase);
        return $contenido;
    }

    private static function truncar(string $s): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s));
        return mb_substr($s, 0, self::MAX_CHARS);
    }
}
