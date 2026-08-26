<?php

namespace App\Modules\Autenticacion\Application\UseCases\Profile;

use App\Models\Usuario;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class UploadSignatureUseCase
{
    public function execute(Usuario $user, UploadedFile $file)
    {
        $oldSignature = $user->getRawOriginal('firma_digital');

        if ($oldSignature && Storage::disk('public')->exists($oldSignature)) {
            Storage::disk('public')->delete($oldSignature);
        }

        $path = $file->store('signatures', 'public');
        $user->firma_digital = $path;
        $user->save();

        \App\Services\SignatureHelper::syncUserSignatureToPersonal($user);

        return $path;
    }
}
