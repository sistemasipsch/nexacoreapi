<?php

namespace App\Modules\Autenticacion\Application\UseCases\Usuario;

use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class CrearUsuarioUseCase
{
    public function execute(array $data)
    {
        if (isset($data['contrasena'])) {
            $data['contrasena'] = Hash::make($data['contrasena']);
        }

        $usuario = new Usuario($data);

        if (isset($data['firma_file']) && $data['firma_file'] instanceof \Illuminate\Http\UploadedFile) {
            $usuario->firma_digital = $data['firma_file']->store('signatures', 'public');
            unset($data['firma_file']);
        } elseif (isset($data['firma_digital']) && is_string($data['firma_digital']) && str_starts_with($data['firma_digital'], 'data:image')) {
            $imageParts = explode(';base64,', $data['firma_digital']);
            if (count($imageParts) === 2) {
                $imageDecoded = base64_decode($imageParts[1]);
                $filename = 'signatures/' . uniqid('firma_', true) . '.png';
                Storage::disk('public')->put($filename, $imageDecoded);
                $usuario->firma_digital = $filename;
            }
        }

        $usuario->save();
        return $usuario;
    }
}
