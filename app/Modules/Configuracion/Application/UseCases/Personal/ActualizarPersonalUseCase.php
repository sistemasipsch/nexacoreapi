<?php

namespace App\Modules\Configuracion\Application\UseCases\Personal;

use App\Models\Personal;
use App\Services\ProcesarFirmaService;

class ActualizarPersonalUseCase
{
    public function __construct(
        protected ?ProcesarFirmaService $procesarFirmaService = null,
        protected ?SincronizarFirmasPersonalActasUseCase $sincronizarUseCase = null
    ) {
        $this->procesarFirmaService = $procesarFirmaService ?: new ProcesarFirmaService();
        $this->sincronizarUseCase = $sincronizarUseCase ?: new SincronizarFirmasPersonalActasUseCase();
    }

    public function execute($id, array $data)
    {
        $item = Personal::find($id);
        if (!$item) {
            return null;
        }

        $antiguaFirma = $item->getRawOriginal('firma') ?? $item->firma;
        $firmaCambio = false;
        $nuevaFirmaPath = $antiguaFirma;

        // 1. Caso eliminación de firma
        if (!empty($data['eliminar_firma']) && $data['eliminar_firma'] !== 'false') {
            $this->procesarFirmaService->eliminarFirmaAntigua($antiguaFirma);
            $data['firma'] = null;
            $nuevaFirmaPath = null;
            $firmaCambio = true;
            unset($data['eliminar_firma']);
        }
        // 2. Caso nueva firma subida o dibujada
        else {
            $firmaSource = $data['firma_file'] ?? $data['firma'] ?? null;

            // Si se envió un archivo o un base64 nuevo (distinto de la ruta existente)
            if ($firmaSource && ($firmaSource instanceof \Illuminate\Http\UploadedFile || (is_string($firmaSource) && str_starts_with($firmaSource, 'data:image')))) {
                $this->procesarFirmaService->eliminarFirmaAntigua($antiguaFirma);
                $nuevaFirmaPath = $this->procesarFirmaService->procesar($firmaSource, 'personal_firmas', 'personal_' . $id);
                $data['firma'] = $nuevaFirmaPath;
                $firmaCambio = true;
                unset($data['firma_file']);
            }
        }

        if (!$firmaCambio) {
            unset($data['firma']);
            unset($data['firma_file']);
        }

        $item->update($data);

        // 3. Si la firma cambió (se agregó, reemplazó o eliminó), sincronizar en cascada con todas las actas vinculadas
        if ($firmaCambio) {
            $this->sincronizarUseCase->execute($item->id, $nuevaFirmaPath, $antiguaFirma);
        }

        return $item->fresh()->load('cargo');
    }
}