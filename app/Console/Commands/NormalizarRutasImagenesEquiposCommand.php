<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PcEquipo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class NormalizarRutasImagenesEquiposCommand extends Command
{
    protected $signature = 'equipos:normalizar-rutas-imagenes {--sede_id= : Filtrar por ID de sede}';
    protected $description = 'Revisa y normaliza las rutas de imagenes de pc_equipos en todas las sedes (equipos/ -> storage/equipos/) y verifica archivos físicos';

    public function handle()
    {
        $this->info('=== Revisión y Normalización de Rutas de Imágenes de Equipos ===');

        $query = PcEquipo::with('sede')->orderBy('id', 'asc');
        if ($this->option('sede_id')) {
            $query->where('sede_id', $this->option('sede_id'));
        }

        $equipos = $query->get();
        $total = $equipos->count();
        $actualizados = 0;
        $conImagen = 0;
        $archivosEncontrados = 0;
        $archivosFaltantes = 0;

        $rows = [];

        // Asegurar que exista la carpeta storage/app/public/equipos y pcEquipos
        $storageEquiposPath = storage_path('app/public/equipos');
        $storagePcEquiposPath = storage_path('app/public/pcEquipos');
        if (!file_exists($storageEquiposPath)) {
            @mkdir($storageEquiposPath, 0777, true);
        }
        if (!file_exists($storagePcEquiposPath)) {
            @mkdir($storagePcEquiposPath, 0777, true);
        }

        // Rutas posibles donde podrían estar físicamente las imágenes legacy
        $posiblesDirectorios = [
            storage_path('app/public/equipos'),
            storage_path('app/public/pcEquipos'),
            storage_path('app/public'),
            public_path('equipos'),
            public_path('storage/equipos'),
            public_path('storage/pcEquipos'),
            base_path('../equipos'),
            base_path('../../equipos'),
            '/home/u528159717/public_html/equipos',
            '/home/u528159717/public_html/nexacoreapi/storage/app/public/equipos',
        ];

        foreach ($equipos as $equipo) {
            $raw = $equipo->getRawOriginal('imagen_url') ?? $equipo->attributes['imagen_url'] ?? null;
            if (!$raw) {
                continue;
            }

            $conImagen++;
            $sedeNombre = $equipo->sede ? $equipo->sede->nombre : 'Sin Sede';
            $nuevaRuta = $raw;

            // 1. Normalizar ruta si empieza con equipos/ o pcEquipos/ sin storage/
            if (!str_starts_with($raw, 'http://') && !str_starts_with($raw, 'https://') && !str_starts_with($raw, 'data:image')) {
                $clean = ltrim($raw, '/');
                if (!str_starts_with($clean, 'storage/')) {
                    $nuevaRuta = 'storage/' . $clean;
                }
            }

            // Si cambió la ruta, actualizar en la base de datos
            if ($nuevaRuta !== $raw) {
                DB::table('pc_equipos')->where('id', $equipo->id)->update([
                    'imagen_url' => $nuevaRuta
                ]);
                $actualizados++;
            }

            // 2. Comprobar existencia física del archivo
            $relativo = ltrim(preg_replace('#^(storage/|/storage/)#', '', $nuevaRuta), '/');
            $archivoExiste = false;
            $ubicacionEncontrada = null;

            // Revisar en storage público estándar
            if (Storage::disk('public')->exists($relativo)) {
                $archivoExiste = true;
                $ubicacionEncontrada = 'storage/app/public/' . $relativo;
            } else {
                // Buscar el nombre del archivo en los directorios legacy
                $nombreArchivo = basename($relativo);
                foreach ($posiblesDirectorios as $dir) {
                    if (is_dir($dir)) {
                        $testFile = $dir . '/' . $nombreArchivo;
                        if (file_exists($testFile)) {
                            // Copiar al storage/app/public/equipos para que sea accesible vía storage
                            $destPath = storage_path('app/public/' . $relativo);
                            $destDir = dirname($destPath);
                            if (!file_exists($destDir)) {
                                @mkdir($destDir, 0777, true);
                            }
                            @copy($testFile, $destPath);
                            $archivoExiste = true;
                            $ubicacionEncontrada = 'Copiado desde ' . $dir;
                            break;
                        }
                    }
                }
            }

            if ($archivoExiste) {
                $archivosEncontrados++;
                $estadoFisico = '<info>✓ Encontrado</info>';
            } else {
                $archivosFaltantes++;
                $estadoFisico = '<comment>⚠ Falta archivo físico</comment>';
            }

            $rows[] = [
                $equipo->id,
                substr($sedeNombre, 0, 18),
                $equipo->serial ?? $equipo->numero_inventario ?? 'S/N',
                $raw,
                $nuevaRuta,
                $estadoFisico
            ];
        }

        $this->table(
            ['ID', 'Sede', 'Serial / Inv', 'Ruta Original BD', 'Ruta Normalizada BD', 'Archivo en Disco'],
            $rows
        );

        $this->newLine();
        $this->info("Total equipos evaluados: {$total}");
        $this->info("Equipos con imagen registrada: {$conImagen}");
        $this->info("Rutas normalizadas en BD a 'storage/...': {$actualizados}");
        $this->info("Archivos físicos encontrados y accesibles: {$archivosEncontrados}");
        if ($archivosFaltantes > 0) {
            $this->warn("Archivos pendientes de copiar a 'storage/app/public/equipos': {$archivosFaltantes}");
        }

        return 0;
    }
}
