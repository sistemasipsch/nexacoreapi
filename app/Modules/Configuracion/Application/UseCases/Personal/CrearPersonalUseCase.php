<?php
namespace App\Modules\Configuracion\Application\UseCases\Personal;

use App\Models\Personal;
use App\Models\PcEntrega;
use App\Services\SignatureHelper;

class CrearPersonalUseCase
{
    public function execute(array $data)
    {
        if (array_key_exists('firma', $data)) {
            $data['firma'] = SignatureHelper::processSignature($data['firma'], 'personal_firmas', 'firma_personal');
        }

        $personal = Personal::create($data);

        // Sincronizar firma con TODAS las actas de entrega de sistemas de esa persona
        if (!empty($personal->firma)) {
            SignatureHelper::syncPersonalSignatureToActas($personal);
        }

        return $personal;
    }
}