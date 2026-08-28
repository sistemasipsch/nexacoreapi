<?php

namespace App\Modules\GestionSistemas\Application\UseCases\ActasEntrega;

use App\Models\PcEntrega;
use App\Models\Usuario;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class SincronizarFirmaAdminActasEntregaUseCase
{
    /**
     * Revisa todas las actas de entrega de PC (pc_entregas).
     * Si un acta no tiene firma de emisor (firma_entrega) o si el archivo referenciado
     * no existe en almacenamiento, le asigna la firma digital del perfil administrador.
     * Aquellas actas que ya cuentan con una firma válida en disco se conservan INTACTAS.
     *
     * @param int|null $usuarioId ID opcional del usuario administrador a utilizar
     * @return array Resumen de resultados de la operación
     */
    public function execute(?int $usuarioId = null): array
    {
        // 1. Obtener usuario administrador con firma digital
        $admin = null;
        if ($usuarioId) {
            $admin = Usuario::find($usuarioId);
        }

        if (!$admin || empty($admin->getRawOriginal('firma_digital'))) {
            $admin = Usuario::whereNotNull('firma_digital')
                ->where('firma_digital', '!=', '')
                ->where(function ($q) {
                    $q->whereHas('rol', function ($r) {
                        $r->where('nombre', 'LIKE', '%admin%')
                          ->orWhere('nombre', 'LIKE', '%sistema%')
                          ->orWhere('nombre', 'LIKE', '%super%');
                    })
                    ->orWhere('usuario', 'LIKE', '%admin%')
                    ->orWhere('rol_id', 1);
                })
                ->first();
        }

        if (!$admin || empty($admin->getRawOriginal('firma_digital'))) {
            $admin = Usuario::whereNotNull('firma_digital')
                ->where('firma_digital', '!=', '')
                ->first();
        }

        if (!$admin) {
            throw new \Exception("No se encontró ningún usuario con firma digital registrada en el sistema.");
        }

        $adminFirmaRaw = $admin->getRawOriginal('firma_digital');
        $adminFirmaPath = ltrim(str_replace(['public/', 'storage/', 'api/'], '', $adminFirmaRaw), '/');

        // 2. Revisar TODAS las actas de entrega de PC
        $actas = PcEntrega::all();
        $totalActas = $actas->count();
        $actualizadas = 0;
        $intactas = 0;
        $detallesActualizadas = [];

        foreach ($actas as $acta) {
            $firmaActualRaw = $acta->getRawOriginal('firma_entrega') ?? $acta->attributes['firma_entrega'] ?? null;
            $debeAsignarFirma = false;

            if (empty($firmaActualRaw)) {
                $debeAsignarFirma = true;
            } else {
                $cleanActual = ltrim(str_replace(['public/', 'storage/', 'api/'], '', $firmaActualRaw), '/');
                
                // Si no es URL externa ni base64, verificar si el archivo existe
                if (!str_starts_with($cleanActual, 'data:image') && !str_starts_with($cleanActual, 'http://') && !str_starts_with($cleanActual, 'https://')) {
                    $existeStorage = Storage::disk('public')->exists($cleanActual);
                    $existePublic = file_exists(public_path('storage/' . $cleanActual));
                    $existeAppPublic = file_exists(storage_path('app/public/' . $cleanActual));

                    if (!$existeStorage && !$existePublic && !$existeAppPublic) {
                        $debeAsignarFirma = true;
                    }
                }
            }

            if ($debeAsignarFirma) {
                $acta->firma_entrega = $adminFirmaPath;
                $acta->save();
                $actualizadas++;
                $detallesActualizadas[] = [
                    'acta_id' => $acta->id,
                    'equipo_id' => $acta->equipo_id,
                    'funcionario_id' => $acta->funcionario_id,
                    'motivo' => empty($firmaActualRaw) ? 'Firma vacía o nula' : 'Archivo no encontrado en almacenamiento'
                ];
            } else {
                $intactas++;
            }
        }

        Log::info("Sincronización de firma de entrega completada: {$actualizadas} actualizadas, {$intactas} conservadas intactas de un total de {$totalActas}.");

        return [
            'total_actas' => $totalActas,
            'actas_actualizadas' => $actualizadas,
            'actas_intactas' => $intactas,
            'admin_usuario' => [
                'id' => $admin->id,
                'nombre' => $admin->nombre_completo,
                'usuario' => $admin->usuario,
                'firma_asignada' => $adminFirmaPath,
                'firma_url' => $admin->firma_digital
            ],
            'detalles_actualizadas' => $detallesActualizadas
        ];
    }
}
