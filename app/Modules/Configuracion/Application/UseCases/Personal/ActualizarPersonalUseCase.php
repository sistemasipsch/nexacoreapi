<?php
namespace App\Modules\Configuracion\Application\UseCases\Personal;

use App\Models\Personal;
use App\Models\PcEntrega;
use App\Services\SignatureHelper;

class ActualizarPersonalUseCase
{
    public function execute($id, array $data)
    {
        $item = Personal::find($id);
        if (!$item) {
            return null;
        }

        if (array_key_exists('firma', $data)) {
            if (!empty($data['firma']) && (str_contains($data['firma'], ';base64,') || $data['firma'] instanceof \Illuminate\Http\UploadedFile)) {
                if ($item->firma) {
                    SignatureHelper::deleteIfExists($item->firma);
                }
                $data['firma'] = SignatureHelper::processSignature($data['firma'], 'personal_firmas', 'firma_personal');
            } elseif (empty($data['firma'])) {
                if ($item->firma) {
                    SignatureHelper::deleteIfExists($item->firma);
                }
                $data['firma'] = null;
            } else {
                $data['firma'] = SignatureHelper::cleanRelativePath($data['firma']);
            }
        }

        $item->update($data);
        $item = $item->refresh();

        // Sincronizar automáticamente la firma en TODAS las actas de entrega de esa persona
        if (!empty($item->firma)) {
            SignatureHelper::syncPersonalSignatureToActas($item);
        }

        return $item;
    }
}