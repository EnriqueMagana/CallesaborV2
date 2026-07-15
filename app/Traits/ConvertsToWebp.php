<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

trait ConvertsToWebp
{
    /**
     * Convert an uploaded image to WebP, compress it, and store it.
     *
     * @param  TemporaryUploadedFile|UploadedFile  $file
     * @param  string  $directory   e.g. 'products', 'addons'
     * @param  int     $quality     WebP quality 1–100
     * @param  int|null $maxWidth   Resize if wider than this (null = no resize)
     * @return string               Stored path relative to disk root (e.g. 'products/abc123.webp')
     */
    protected function storeAsWebp(
        TemporaryUploadedFile|UploadedFile $file,
        string $directory = 'images',
        int $quality = 82,
        ?int $maxWidth = 1200
    ): string {
        $sourcePath = $file->getRealPath();
        $mime       = $file->getMimeType();

        // Suppress libpng/libjpeg warnings (e.g. iCCP profile mismatch).
        // set_error_handler returning true prevents Laravel's handler from
        // converting the warning into an ErrorException.
        set_error_handler(fn() => true);
        try {
            $image = match (true) {
                str_contains($mime, 'jpeg') => imagecreatefromjpeg($sourcePath),
                str_contains($mime, 'png')  => $this->pngToTrueColor(imagecreatefrompng($sourcePath)),
                str_contains($mime, 'gif')  => imagecreatefromgif($sourcePath),
                str_contains($mime, 'webp') => imagecreatefromwebp($sourcePath),
                default                     => throw new \RuntimeException("Formato no soportado: {$mime}"),
            };
        } finally {
            restore_error_handler();
        }

        if ($image === false) {
            throw new \RuntimeException("GD no pudo leer la imagen: {$sourcePath}");
        }

        // Resize if needed
        if ($maxWidth !== null) {
            $image = $this->resizeIfWider($image, $maxWidth);
        }

        // Write WebP to a temp buffer
        $tempFile = tempnam(sys_get_temp_dir(), 'webp_') . '.webp';
        imagewebp($image, $tempFile, $quality);
        imagedestroy($image);

        // Store on the public disk
        $filename    = Str::uuid() . '.webp';
        $storedPath  = $directory . '/' . $filename;

        Storage::disk('public')->put(
            $storedPath,
            file_get_contents($tempFile)
        );

        @unlink($tempFile);

        return $storedPath;
    }

    // ── helpers ────────────────────────────────────────────────────────────────

    private function pngToTrueColor(\GdImage $src): \GdImage
    {
        $w   = imagesx($src);
        $h   = imagesy($src);
        $dst = imagecreatetruecolor($w, $h);

        // Keep transparency: fill with white background
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $white = imagecolorallocatealpha($dst, 255, 255, 255, 127);
        imagefilledrectangle($dst, 0, 0, $w, $h, $white);
        imagealphablending($dst, true);

        imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
        imagedestroy($src);

        return $dst;
    }

    private function resizeIfWider(\GdImage $src, int $maxWidth): \GdImage
    {
        $origW = imagesx($src);
        $origH = imagesy($src);

        if ($origW <= $maxWidth) {
            return $src;
        }

        $ratio  = $maxWidth / $origW;
        $newW   = $maxWidth;
        $newH   = (int) round($origH * $ratio);

        $dst = imagecreatetruecolor($newW, $newH);

        // Preserve alpha channel for PNG/WebP sources
        imagealphablending($dst, false);
        imagesavealpha($dst, true);

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
        imagedestroy($src);

        return $dst;
    }
}
