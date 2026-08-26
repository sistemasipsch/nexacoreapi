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
        }

        $usuario->update($data);
        $usuario = $usuario->refresh();
        \App\Services\SignatureHelper::syncUserSignatureToPersonal($usuario);
        return $usuario;
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
