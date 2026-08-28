<?php

namespace App\Modules\GestionSistemas\Application\UseCases\EquiposComputo;

use App\Models\PcEquipo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class AutoRecuperarImagenesEquiposUseCase
{
    /**
     * Directorios base del servidor donde buscar imágenes heredadas
     */
    protected array $searchRoots = [
        '/home/u528159717/public_html',
        '/home/u528159717',
    ];

    public function execute(): array
    {
        $storageEquipos = storage_path('app/public/equipos');
        $storagePcEquipos = storage_path('app/public/pcEquipos');

        if (!file_exists($storageEquipos)) {
            @mkdir($storageEquipos, 0777, true);
        }
        if (!file_exists($storagePcEquipos)) {
            @mkdir($storagePcEquipos, 0777, true);
        }

        // Asegurar enlace simbólico public/storage si no existe
        if (!file_exists(public_path('storage'))) {
            try {
                \Illuminate\Support\Facades\Artisan::call('storage:link');
            } catch (\Throwable $e) {
                // Continuar si no se puede crear symlink
            }
        }

        $equipos = PcEquipo::whereNotNull('imagen_url')->where('imagen_url', '!=', '')->get();
        
        $stats = [
            'total_equipos_con_imagen' => $equipos->count(),
            'rutas_normalizadas' => 0,
            'archivos_recuperados' => 0,
            'archivos_existentes' => 0,
            'archivos_no_encontrados' => 0,
            'detalles' => []
        ];

        // Mapa de archivos encontrados en el servidor
        $indexedFiles = $this->indexServerImageFiles();

        foreach ($equipos as $equipo) {
            $raw = $equipo->getRawOriginal('imagen_url') ?? $equipo->attributes['imagen_url'] ?? null;
            if (!$raw) continue;

            $filename = basename($raw);
            $clean = ltrim($raw, '/');
            $nuevaRuta = $raw;

            // 1. Normalizar formato de la ruta
            if (!str_starts_with($raw, 'http://') && !str_starts_with($raw, 'https://') && !str_starts_with($raw, 'data:image')) {
                if (!str_starts_with($clean, 'storage/')) {
                    $nuevaRuta = 'storage/' . $clean;
                }
            }

            if ($nuevaRuta !== $raw) {
                DB::table('pc_equipos')->where('id', $equipo->id)->update([
                    'imagen_url' => $nuevaRuta
                ]);
                $stats['rutas_normalizadas']++;
            }

            // 2. Verificar si el archivo ya existe en storage
            $relativo = ltrim(preg_replace('#^(storage/|/storage/)#', '', $nuevaRuta), '/');
            $destPath = storage_path('app/public/' . $relativo);

            if (file_exists($destPath)) {
                $stats['archivos_existentes']++;
                continue;
            }

            // 3. Buscar si el archivo está indexado en alguna parte del servidor
            if (isset($indexedFiles[$filename])) {
                $sourcePath = $indexedFiles[$filename];
                $destDir = dirname($destPath);
                if (!file_exists($destDir)) {
                    @mkdir($destDir, 0777, true);
                }

                if (@copy($sourcePath, $destPath)) {
                    $stats['archivos_recuperados']++;
                    $stats['detalles'][] = [
                        'equipo_id' => $equipo->id,
                        'archivo' => $filename,
                        'origen' => $sourcePath,
                        'destino' => $destPath,
                        'estado' => 'RECUPERADO'
                    ];
                    continue;
                }
            }

            $stats['archivos_no_encontrados']++;
        }

        return $stats;
    }

    /**
     * Busca y mapea todos los archivos de imagen relevantes en las rutas del servidor
     */
    protected function indexServerImageFiles(): array
    {
        $map = [];
        $searchDirs = [];

        // Agregar rutas relativas locales
        $localBases = [
            base_path(),
            base_path('..'),
            base_path('../..'),
            base_path('../../..'),
            public_path(),
            storage_path('app'),
        ];

        foreach ($localBases as $b) {
            if (is_dir($b)) $searchDirs[] = realpath($b);
        }

        // Agregar rutas del servidor de producción
        foreach ($this->searchRoots as $root) {
            if (is_dir($root)) $searchDirs[] = $root;
        }

        $searchDirs = array_unique(array_filter($searchDirs));

        foreach ($searchDirs as $dir) {
            try {
                $this->scanDirectoryForImages($dir, $map, 0, 4); // Max depth 4
            } catch (\Throwable $e) {
                // Continuar si hay error de permisos
            }
        }

        return $map;
    }

    protected function scanDirectoryForImages(string $dir, array &$map, int $currentDepth, int $maxDepth): void
    {
        if ($currentDepth > $maxDepth || !is_dir($dir) || !is_readable($dir)) {
            return;
        }

        // Ignorar directorios pesados irrelevantes
        $baseName = basename($dir);
        if (in_array($baseName, ['node_modules', 'vendor', '.git', 'cache', 'sessions', 'logs'])) {
            return;
        }

        $items = @scandir($dir);
        if ($items === false) return;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;

            $fullPath = $dir . DIRECTORY_SEPARATOR . $item;

            if (is_dir($fullPath)) {
                $this->scanDirectoryForImages($fullPath, $map, $currentDepth + 1, $maxDepth);
            } elseif (is_file($fullPath)) {
                $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                    // Mapear por nombre de archivo
                    if (!isset($map[$item])) {
                        $map[$item] = $fullPath;
                    }
                }
            }
        }
    }
}
