<?php

namespace App\Modules\Autenticacion\Application\UseCases\Usuario;

use App\Models\Usuario;

class ListarUsuariosUseCase
{
    public function execute()
    {
        return Usuario::with(['rol', 'sede'])->orderBy('id', 'desc')->get();
    }
}
