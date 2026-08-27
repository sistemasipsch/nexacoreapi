<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcesarFirmaService
{
    /**
     * Procesa y optimiza una firma digital desde un archivo o string Base64.
     * Guarda la firma en el disco public y retorna la ruta relativa.
     *
     * @param UploadedFile|string|null $source
     * @param string $directory
     * @param string|null $prefix
     * @return string|null
     */
    public function procesar($source, string $directory = 'personal_firmas', ?string $prefix = 'firma'): ?string
    {
        if (!$source) {
            return null;
        }

        // Caso 1: Archivo UploadedFile
        if ($source instanceof UploadedFile) {
            return $this->procesarArchivo($source, $directory, $prefix);
        }

        // Caso 2: String Base64 (data:image/png;base64,...)
        if (is_string($source) && str_starts_with($source, 'data:image')) {
            return $this->procesarBase64($source, $directory, $prefix);
        }

        // Caso 3: Ya es una ruta existente
        if (is_string($source) && !empty($source)) {
            return ltrim(str_replace('storage/', '', $source), '/');
        }

        return null;
    }

    /**
     * Procesa un archivo subido optimizándolo con recorte de bordes transparentes.
     */
    protected function procesarArchivo(UploadedFile $file, string $directory, ?string $prefix): string
    {
        $filename = ($prefix ?: 'firma') . '_' . time() . '_' . Str::random(8) . '.png';
        $fullDirectory = storage_path('app/public/' . $directory);

        if (!file_exists($fullDirectory)) {
            mkdir($fullDirectory, 0777, true);
        }

        $targetPath = $fullDirectory . '/' . $filename;

        // Intentar optimizar con GD si es imagen
        $imageContent = file_get_contents($file->getRealPath());
        $optimized = $this->optimizarImagenPng($imageContent);

        if ($optimized) {
            file_put_contents($targetPath, $optimized);
        } else {
            $file->storeAs($directory, $filename, 'public');
        }

        return $directory . '/' . $filename;
    }

    /**
     * Procesa una cadena base64 de firma digital.
     */
    protected function procesarBase64(string $base64String, string $directory, ?string $prefix): string
    {
        $data = explode(',', $base64String);
        $binaryData = base64_decode(count($data) > 1 ? $data[1] : $data[0]);

        $filename = ($prefix ?: 'firma') . '_' . time() . '_' . Str::random(8) . '.png';
        $fullDirectory = storage_path('app/public/' . $directory);

        if (!file_exists($fullDirectory)) {
            mkdir($fullDirectory, 0777, true);
        }

        $targetPath = $fullDirectory . '/' . $filename;

        $optimized = $this->optimizarImagenPng($binaryData);
        if ($optimized) {
            file_put_contents($targetPath, $optimized);
        } else {
            file_put_contents($targetPath, $binaryData);
        }

        return $directory . '/' . $filename;
    }

    /**
     * Recorta bordes transparentes sobrantes, limpia fondos de papel y preserva canal alfa en PNG de alta definición.
     */
    protected function optimizarImagenPng(string $binaryData): ?string
    {
        if (!extension_loaded('gd')) {
            return null;
        }

        $raw = @imagecreatefromstring($binaryData);
        if (!$raw) {
            return null;
        }

        $w = imagesx($raw);
        $h = imagesy($raw);

        // Crear lienzo con canal alfa activado
        $src = imagecreatetruecolor($w, $h);
        imagealphablending($src, false);
        imagesavealpha($src, true);
        $transparent = imagecolorallocatealpha($src, 0, 0, 0, 127);
        imagefilledrectangle($src, 0, 0, $w, $h, $transparent);
        imagecopy($src, $raw, 0, 0, 0, 0, $w, $h);
        imagedestroy($raw);

        // Verificar si la imagen ya cuenta con transparencia
        $hasTransparency = false;
        for ($y = 0; $y < min($h, 30); $y++) {
            for ($x = 0; $x < min($w, 30); $x++) {
                $rgba = imagecolorat($src, $x, $y);
                $alpha = ($rgba & 0x7F000000) >> 24;
                if ($alpha > 50) {
                    $hasTransparency = true;
                    break 2;
                }
            }
        }

        // Si la imagen es una foto opaca (sin canal alfa), convertimos el fondo claro a transparente
        if (!$hasTransparency) {
            for ($x = 0; $x < $w; $x++) {
                for ($y = 0; $y < $h; $y++) {
                    $rgb = imagecolorat($src, $x, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;
                    // Luminancia BT.709
                    $lum = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

                    if ($lum >= 205) {
                        imagesetpixel($src, $x, $y, $transparent);
                    } elseif ($lum > 165) {
                        $factor = ($lum - 165) / 40.0;
                        $alphaVal = min(127, (int)($factor * 127));
                        $col = imagecolorallocatealpha($src, (int)($r * 0.35), (int)($g * 0.35), (int)($b * 0.35), $alphaVal);
                        imagesetpixel($src, $x, $y, $col);
                    } else {
                        // Resaltar tinta
                        $col = imagecolorallocatealpha($src, (int)($r * 0.2), (int)($g * 0.2), (int)($b * 0.2), 0);
                        imagesetpixel($src, $x, $y, $col);
                    }
                }
            }
        }

        // Recorte automático de transparencia sobrante (auto-crop)
        $cropped = @imagecropauto($src, IMG_CROP_TRANSPARENT);
        if ($cropped !== false) {
            imagedestroy($src);
            $src = $cropped;
        }

        ob_start();
        imagealphablending($src, false);
        imagesavealpha($src, true);
        imagepng($src, null, 8); // Compresión 8 (alta compresión, lossless)
        $output = ob_get_clean();
        imagedestroy($src);

        return $output ?: null;
    }

    /**
     * Elimina el archivo físico de firma anterior si existe.
     */
    public function eliminarFirmaAntigua(?string $path): void
    {
        if (!$path) {
            return;
        }

        $cleanPath = ltrim(str_replace('storage/', '', $path), '/');
        if (Storage::disk('public')->exists($cleanPath)) {
            Storage::disk('public')->delete($cleanPath);
        }
    }
}