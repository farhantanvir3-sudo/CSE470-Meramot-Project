<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class MediaUploadService
{
    /**
     * Encode an uploaded image or PDF into a compact, persistent Data URI (`data:<mime>;base64,...`).
     * Large camera images (> 350 KB) are automatically downscaled (max 1600px) and compressed via GD
     * when available so multi-document forms stay fast and lightweight in PostgreSQL.
     */
    public static function fromRequest(
        Request $request,
        string $inputKey,
        ?string $fallback = null,
        int $maxDimension = 1600,
        int $jpegQuality = 82
    ): ?string {
        if (!$request->hasFile($inputKey)) {
            return $fallback;
        }

        $file = $request->file($inputKey);
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return $fallback;
        }

        return self::encode($file, $maxDimension, $jpegQuality) ?? $fallback;
    }

    /**
     * Convert a validated UploadedFile into a compact Data URI.
     */
    public static function encode(
        UploadedFile $file,
        int $maxDimension = 1600,
        int $jpegQuality = 82
    ): ?string {
        if (!$file->isValid()) {
            return null;
        }

        $realPath = $file->getRealPath();
        if (!$realPath || !is_readable($realPath)) {
            return null;
        }

        $mime = strtolower((string) ($file->getMimeType() ?: 'application/octet-stream'));
        $bytes = (string) file_get_contents($realPath);
        if ($bytes === '') {
            return null;
        }

        // Keep PDFs and already-compact images (<= 350 KB, including unit-test fixtures) byte-exact
        $optimizableMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (
            strlen($bytes) > 350 * 1024
            && in_array($mime, $optimizableMimes, true)
            && function_exists('imagecreatefromstring')
        ) {
            $optimized = self::compressRasterBytes($bytes, $mime, $maxDimension, $jpegQuality);
            if ($optimized !== null) {
                return 'data:' . $optimized['mime'] . ';base64,' . base64_encode($optimized['bytes']);
            }
        }

        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }

    /**
     * Downscale and compress large raster image bytes using GD while preserving aspect ratio.
     *
     * @return array{mime: string, bytes: string}|null
     */
    private static function compressRasterBytes(
        string $bytes,
        string $originalMime,
        int $maxDimension,
        int $jpegQuality
    ): ?array {
        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            return null;
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        if ($srcW <= 0 || $srcH <= 0) {
            imagedestroy($src);
            return null;
        }

        $targetW = $srcW;
        $targetH = $srcH;
        if ($srcW > $maxDimension || $srcH > $maxDimension) {
            $ratio = min($maxDimension / $srcW, $maxDimension / $srcH);
            $targetW = max(1, (int) round($srcW * $ratio));
            $targetH = max(1, (int) round($srcH * $ratio));
        }

        $dst = imagecreatetruecolor($targetW, $targetH);
        if ($dst === false) {
            imagedestroy($src);
            return null;
        }

        // Fill white canvas so transparent PNGs flatten cleanly when converted to compact JPEG
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $targetW, $targetH, $white);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetW, $targetH, $srcW, $srcH);
        imagedestroy($src);

        ob_start();
        $ok = imagejpeg($dst, null, $jpegQuality);
        $compressed = (string) ob_get_clean();
        imagedestroy($dst);

        if (!$ok || $compressed === '' || strlen($compressed) >= strlen($bytes)) {
            return null;
        }

        return [
            'mime' => 'image/jpeg',
            'bytes' => $compressed,
        ];
    }
}
