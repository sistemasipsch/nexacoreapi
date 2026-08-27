<?php

namespace App\Modules\Configuracion\Application\UseCases\Personal;

use App\Models\PcEntrega;
use App\Models\CpEntregaActivosFijos;
use Illuminate\Support\Facades\Log;

class SincronizarFirmasPersonalActasUseCase
{
    /**
     * Sincroniza la firma del perfil de personal con todas sus actas de entrega vinculadas,
     * respetando aquellas actas que tengan una firma manual dibujada específicamente en el momento.
     *
     * @param int $personalId
     * @param string_null $nuevaFirmaPath Ruta relativa de la nueva firma (ej: personal_firmas/firma_123.png)
     * @param string_null $antiguaFirmaPath Ruta relativa de la firma anterior
     * @return array Resumen de actas actualizadas
     */
    public function execute(int $personalId, ?string $nuevaFirmaPath, ?string $antiguaFirmaPath = null): array
    {
        $pcEntregasActualizadas = 0;
        $activosActualizados = 0;

        // 1. Sincronizar actas de PC Equipos (pc_entregas)
        $pcEntregas = PcEntrega::where('funcionario_id', $personalId)->get();
        foreach ($pcEntregas as $acta) {
            if ($this->debeActualizarFirmaPc($acta->firma_recibe, $antiguaFirmaPath)) {
                $acta->firma_recibe = $nuevaFirmaPath;
                $acta->save();
                $pcEntregasActualizadas++;
            }
        }

        // 2. Sincronizar actas de Activos Fijos (cp_entrega_activos_fijos)
        $cpEntregas = CpEntregaActivosFijos::where('personal_id', $personalId)->get();
        $nuevaFirmaStorage = $nuevaFirmaPath ? 'storage/' . ltrim(str_replace('storage/', '', $nuevaFirmaPath), '/') : null;
        foreach ($cpEntregas as $acta) {
            if ($this->debeActualizarFirmaActivos($acta->firma_quien_recibe, $antiguaFirmaPath)) {
                $acta->firma_quien_recibe = $nuevaFirmaStorage;
                $acta->save();
                $activosActualizados++;
            }
        }

        Log::info("Firmas sincronizadas para Personal ID {$personalId}: {$pcEntregasActualizadas} actas PC, {$activosActualizados} actas activos fijos.");

        return [
            'pc_entregas_actualizadas' => $pcEntregasActualizadas,
            'activos_actualizados' => $activosActualizados
        ];
    }

    /**
     * Determina si el campo firma_recibe de pc_entregas debe ser actualizado por la firma de perfil.
     */
    protected function debeActualizarFirmaPc(?string $firmaActual, ?string $antiguaFirma): bool
    {
        // Si no tiene firma, se debe actualizar
        if (empty($firmaActual)) {
            return true;
        }

        $cleanActual = ltrim(str_replace('storage/', '', $firmaActual), '/');
        $cleanAntigua = $antiguaFirma ? ltrim(str_replace('storage/', '', $antiguaFirma), '/') : null;

        // Si la firma actual era exactamente la firma anterior de perfil
        if ($cleanAntigua && $cleanActual === $cleanAntigua) {
            return true;
        }

        // Si la firma actual proviene del directorio del perfil de personal
        if (str_starts_with($cleanActual, 'personal_firmas/')) {
            return true;
        }

        // Si fue una firma manual o específica de entrega, NO se sobreescribe
        if (str_contains($cleanActual, 'ActasEntrega') || str_contains($cleanActual, 'actas_firmas') || str_contains($cleanActual, 'manual_') || str_contains($cleanActual, 'firmas/recibe_')) {
            return false;
        }

        return true;
    }

    /**
     * Determina si el campo firma_quien_recibe de cp_entrega_activos_fijos debe ser actualizado.
     */
    protected function debeActualizarFirmaActivos(?string $firmaActual, ?string $antiguaFirma): bool
    {
        if (empty($firmaActual)) {
            return true;
        }

        $cleanActual = ltrim(str_replace('storage/', '', $firmaActual), '/');
        $cleanAntigua = $antiguaFirma ? ltrim(str_replace('storage/', '', $antiguaFirma), '/') : null;

        if ($cleanAntigua && $cleanActual === $cleanAntigua) {
            return true;
        }

        if (str_starts_with($cleanActual, 'personal_firmas/')) {
            return true;
        }

        if (str_contains($cleanActual, 'entrega_activos_firma') || str_contains($cleanActual, 'manual_')) {
            return false;
        }

        return true;
    }
}