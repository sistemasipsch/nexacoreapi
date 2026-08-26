<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SignatureHelper
{
    /**
     * Procesa y almacena una firma (UploadedFile, Base64 data-url o ruta existente).
     *
     * @param mixed $input
     * @param string $directory
     * @param string $prefix
     * @return string|null Ruta relativa guardada en el disco 'public' o null
     */
    public static function processSignature($input, string $directory = 'firmas', string $prefix = 'firma'): ?string
    {
        if (empty($input)) {
            return null;
        }

        // Caso 1: Archivo UploadedFile
        if ($input instanceof UploadedFile) {
            $extension = $input->getClientOriginalExtension() ?: 'png';
            $filename = $prefix . '_' . Str::random(20) . '_' . time() . '.' . $extension;
            return $input->storeAs($directory, $filename, 'public');
        }

        // Caso 2: String
        if (is_string($input)) {
            $input = trim($input);

            // Si es base64 data-url
            if (str_contains($input, ';base64,')) {
                try {
                    [$meta, $data] = explode(';', $input);
                    [, $data] = explode(',', $data);

                    if (!$data) {
                        return null;
                    }

                    $decoded = base64_decode($data);
                    if ($decoded === false) {
                        return null;
                    }

                    $extension = 'png';
                    if (str_contains($meta, 'image/')) {
                        $ext = explode('image/', $meta)[1];
                        $extension = strtolower($ext) === 'jpeg' ? 'jpg' : strtolower($ext);
                    }

                    $filename = $prefix . '_' . Str::random(20) . '_' . time() . '.' . $extension;
                    $path = $directory . '/' . $filename;

                    Storage::disk('public')->put($path, $decoded);
                    return $path;
                } catch (\Exception $e) {
                    Log::error('Error al guardar firma base64: ' . $e->getMessage());
                    return null;
                }
            }

            // Si es una URL o ruta relativa existente, limpiamos
            return self::cleanRelativePath($input);
        }

        return null;
    }

    /**
     * Limpia URLs y prefijos para obtener la ruta relativa pura en storage/app/public.
     */
    public static function cleanRelativePath(?string $pathOrUrl): ?string
    {
        if (empty($pathOrUrl)) {
            return null;
        }

        $path = $pathOrUrl;

        // Remover protocolo y host
        $path = preg_replace('#^https?://[^/]+/#i', '', $path);

        // Remover prefijos comunes como 'api/storage/', 'storage/', 'public/'
        $path = preg_replace('#^(api/)?storage/#i', '', $path);
        $path = preg_replace('#^public/#i', '', $path);

        return ltrim($path, '/');
    }

    /**
     * Elimina un archivo de firma si existe en storage.
     */
    public static function deleteIfExists(?string $path): void
    {
        $cleanPath = self::cleanRelativePath($path);
        if ($cleanPath && Storage::disk('public')->exists($cleanPath)) {
            try {
                Storage::disk('public')->delete($cleanPath);
            } catch (\Exception $e) {
                Log::warning('No se pudo eliminar firma: ' . $e->getMessage());
            }
        }
    }

    /**
     * Sincroniza la firma de un Colaborador (Personal) con sus actas de entrega y su usuario correspondiente:
     * - Actualiza actas sin firma o que usen firmas guardadas (signatures/ o personal_firmas/)
     * - PRESERVA y NO sobreescribe firmas dibujadas en el momento en el acta (ActasEntregaEquipos/)
     */
    public static function syncPersonalSignatureToActas(\App\Models\Personal $personal): int
    {
        $rawFirma = self::cleanRelativePath($personal->getRawOriginal('firma') ?? $personal->firma);
        if (empty($rawFirma)) {
            return 0;
        }

        // Actualizar actas de entrega de este funcionario que no tengan firma o usen firma guardada anterior.
        // Si el acta fue dibujada en el momento (ActasEntregaEquipos/), se respeta la firma dibujada.
        $totalUpdated = \App\Models\PcEntrega::where('funcionario_id', $personal->id)
            ->where(function ($q) {
                $q->whereNull('firma_recibe')
                  ->orWhere('firma_recibe', '')
                  ->orWhere('firma_recibe', 'like', 'signatures/%')
                  ->orWhere('firma_recibe', 'like', 'personal_firmas/%');
            })
            ->update(['firma_recibe' => $rawFirma]);

        // Sincronizar también con Usuario del sistema si existe
        $nombreParts = explode(' ', trim($personal->nombre ?? ''));
        $query = \App\Models\Usuario::where('nombre_completo', 'like', '%' . $personal->nombre . '%');
        if (count($nombreParts) >= 2) {
            $query->orWhere(function($q) use ($nombreParts) {
                $q->where('nombre_completo', 'like', '%' . $nombreParts[0] . '%')
                  ->where('nombre_completo', 'like', '%' . $nombreParts[1] . '%');
            });
        }
        $usuarios = $query->get();
        foreach ($usuarios as $u) {
            $u->update(['firma_digital' => $rawFirma]);
        }

        return $totalUpdated;
    }

    /**
     * Sincroniza la firma de un Usuario con los registros de Personal y sus actas de entrega:
     * - PRESERVA y NO sobreescribe firmas dibujadas en el momento en el acta (ActasEntregaEquipos/)
     */
    public static function syncUserSignatureToPersonal(\App\Models\Usuario $usuario): int
    {
        $rawFirma = self::cleanRelativePath($usuario->getRawOriginal('firma_digital') ?? $usuario->firma_digital);
        if (empty($rawFirma)) {
            return 0;
        }

        $nombreParts = explode(' ', trim($usuario->nombre_completo ?? ''));
        $query = \App\Models\Personal::where('nombre', 'like', '%' . ($usuario->nombre_completo ?? $usuario->usuario) . '%');
        if (count($nombreParts) >= 2) {
            $query->orWhere(function($q) use ($nombreParts) {
                $q->where('nombre', 'like', '%' . $nombreParts[0] . '%')
                  ->where('nombre', 'like', '%' . $nombreParts[1] . '%');
            });
        }

        $personales = $query->get();
        $totalActas = 0;
        foreach ($personales as $p) {
            $p->update(['firma' => $rawFirma]);
            // Actualizar actas de entrega que no tengan firma o usen firmas guardadas (respetando las dibujadas)
            $totalActas += \App\Models\PcEntrega::where('funcionario_id', $p->id)
                ->where(function ($q) {
                    $q->whereNull('firma_recibe')
                      ->orWhere('firma_recibe', '')
                      ->orWhere('firma_recibe', 'like', 'signatures/%')
                      ->orWhere('firma_recibe', 'like', 'personal_firmas/%');
                })
                ->update(['firma_recibe' => $rawFirma]);
        }

        return $totalActas;
    }
}
