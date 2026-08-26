<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImageOptimizationService
{
    /**
     * Procesa, optimiza, mejora la claridad y comprime una imagen de equipo de computo.
     *
     * @param UploadedFile|string $input
     * @param string $directory (ej. 'pcEquipos')
     * @param int $maxWidth (default: 1600px)
     * @param int $maxHeight (default: 1200px)
     * @param int $quality (default: 85)
     * @return string|null Ruta relativa guardada en storage/app/public o null
     */
    public static function optimizeAndStore($input, string $directory = 'pcEquipos', int $maxWidth = 1600, int $maxHeight = 1200, int $quality = 85): ?string
    {
        if (empty($input)) {
            return null;
        }

        try {
            $sourceImage = null;
            $exifOrientation = 1;
            $originalPath = null;

            if ($input instanceof UploadedFile) {
                $originalPath = $input->getRealPath();
            } elseif (is_string($input) && file_exists($input)) {
                $originalPath = $input;
            } elseif (is_string($input) && str_contains($input, ';base64,')) {
                // Si es base64 data-url
                [$meta, $data] = explode(';', $input);
                [, $data] = explode(',', $data);
                $decoded = base64_decode($data);
                if ($decoded === false) {
                    return null;
                }
                $sourceImage = @imagecreatefromstring($decoded);
                if (!$sourceImage) {
                    return null;
                }
            } else {
                // Es una ruta ya existente
                return SignatureHelper::cleanRelativePath($input);
            }

            if ($originalPath && !$sourceImage) {
                // Leer orientación EXIF para corregir fotos de smartphones
                if (function_exists('exif_read_data')) {
                    $exif = @exif_read_data($originalPath);
                    if ($exif && !empty($exif['Orientation'])) {
                        $exifOrientation = (int) $exif['Orientation'];
                    }
                }

                $sourceData = file_get_contents($originalPath);
                $sourceImage = @imagecreatefromstring($sourceData);
            }

            if (!$sourceImage) {
                if ($input instanceof UploadedFile) {
                    return $input->store($directory, 'public');
                }
                return null;
            }

            // 1. Corregir orientación EXIF si proviene de teléfono celular
            if ($exifOrientation !== 1) {
                switch ($exifOrientation) {
                    case 3:
                        $sourceImage = imagerotate($sourceImage, 180, 0);
                        break;
                    case 6:
                        $sourceImage = imagerotate($sourceImage, -90, 0);
                        break;
                    case 8:
                        $sourceImage = imagerotate($sourceImage, 90, 0);
                        break;
                }
            }

            $origWidth = imagesx($sourceImage);
            $origHeight = imagesy($sourceImage);

            // 2. Redimensionar preservando proporción si excede los límites HD
            $newWidth = $origWidth;
            $newHeight = $origHeight;

            if ($origWidth > $maxWidth || $origHeight > $maxHeight) {
                $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
                $newWidth = (int) round($origWidth * $ratio);
                $newHeight = (int) round($origHeight * $ratio);
            }

            $targetImage = imagecreatetruecolor($newWidth, $newHeight);

            // Manejar canales alfa / transparencia
            imagealphablending($targetImage, false);
            imagesavealpha($targetImage, true);
            $white = imagecolorallocate($targetImage, 255, 255, 255);
            imagefilledrectangle($targetImage, 0, 0, $newWidth, $newHeight, $white);
            imagealphablending($targetImage, true);

            // Remuestreo de alta calidad (bicúbico suave)
            imagecopyresampled(
                $targetImage,
                $sourceImage,
                0, 0, 0, 0,
                $newWidth,
                $newHeight,
                $origWidth,
                $origHeight
            );

            // 3. Filtro para mejorar y aclarar la imagen:
            // Aclarado sutil (+10 de brillo para destacar detalles en gabinetes o componentes oscuros)
            if (function_exists('imagefilter')) {
                imagefilter($targetImage, IMG_FILTER_BRIGHTNESS, 10);
                // Contraste sutil (-5 en GD incrementa contraste para mayor nitidez)
                imagefilter($targetImage, IMG_FILTER_CONTRAST, -5);
            }

            // 4. Filtro de enfoque suave (Sharpening matrix para resaltar etiquetas, seriales, puertos y botones)
            if (function_exists('imageconvolution')) {
                $sharpenMatrix = [
                    [-0.05, -0.05, -0.05],
                    [-0.05,  1.40, -0.05],
                    [-0.05, -0.05, -0.05]
                ];
                $divisor = 1.0;
                $offset = 0.0;
                @imageconvolution($targetImage, $sharpenMatrix, $divisor, $offset);
            }

            // 5. Guardar optimizado en storage
            Storage::disk('public')->makeDirectory($directory);

            $filename = 'pc_' . Str::random(20) . '_' . time();
            
            // Preferir WebP si está disponible para máxima compresión y calidad; si no, JPEG
            if (function_exists('imagewebp')) {
                $relativePath = $directory . '/' . $filename . '.webp';
                $fullDiskPath = storage_path('app/public/' . $relativePath);
                imagewebp($targetImage, $fullDiskPath, $quality);
            } else {
                $relativePath = $directory . '/' . $filename . '.jpg';
                $fullDiskPath = storage_path('app/public/' . $relativePath);
                imagejpeg($targetImage, $fullDiskPath, $quality);
            }

            // Liberar memoria
            imagedestroy($sourceImage);
            imagedestroy($targetImage);

            return $relativePath;
        } catch (\Exception $e) {
            Log::error('Error optimizing equipment image: ' . $e->getMessage());
            if ($input instanceof UploadedFile) {
                return $input->store($directory, 'public');
            }
            return null;
        }
    }
}
