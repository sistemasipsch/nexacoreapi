<?php

namespace App\Modules\Autenticacion\Application\UseCases\Usuario;

use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ActualizarUsuarioUseCase
{
    public function execute($id, array $data)
    {
        $usuario = Usuario::findOrFail($id);

        if (isset($data['contrasena']) && !empty($data['contrasena'])) {
            $data['contrasena'] = Hash::make($data['contrasena']);
        } else {
            unset($data['contrasena']);
        }

        if (isset($data['firma_file']) && $data['firma_file'] instanceof \Illuminate\Http\UploadedFile) {
            $data['firma_digital'] = $this->handleSignatureUpload($usuario, $data['firma_file']);
            unset($data['firma_file']);
        } elseif (isset($data['firma_digital']) && is_string($data['firma_digital']) && str_starts_with($data['firma_digital'], 'data:image')) {
            $this->deleteOldFile($usuario->getRawOriginal('firma_digital'));
            $imageParts = explode(';base64,', $data['firma_digital']);
            if (count($imageParts) === 2) {
                $imageDecoded = base64_decode($imageParts[1]);
                $filename = 'signatures/' . uniqid('firma_', true) . '.png';
                Storage::disk('public')->put($filename, $imageDecoded);
                $data['firma_digital'] = $filename;
            }
        }

        $usuario->update($data);
        return $usuario->refresh();
    }

    protected function handleSignatureUpload(Usuario $usuario, $file)
    {
        $this->deleteOldFile($usuario->getRawOriginal('firma_digital'));
        return $file->store('signatures', 'public');
    }

    protected function deleteOldFile($path)
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
