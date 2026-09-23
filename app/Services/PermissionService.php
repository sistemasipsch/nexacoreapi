<?php

namespace App\Services;

use App\Models\Usuario;
use Illuminate\Support\Facades\Log;

class PermissionService
{
    /**
     * Check if the user has the required permission.
     *
     * @param Usuario $user
     * @param string $permissionName
     * @return bool
     */
    public function check(Usuario $user, string $permissionName): bool
    {
        // Must load relation if not loaded
        if (!$user->relationLoaded('rol')) {
            $user->load('rol.permisos');
        }

        if (!$user->rol) {
            return false;
        }

    // Check if the role has the permission
        return $user->rol->permisos->contains('nombre', $permissionName);
    }

    /**
     * Valida si el usuario puede crear pedidos prioritarios (únicamente quienes tengan el permiso cp_pedido.realizar_pedido_prioritario).
     *
     * @param Usuario $user
     * @return bool
     */
    public function canCreatePriorityOrder(Usuario $user): bool
    {
        return $this->check($user, 'cp_pedido.realizar_pedido_prioritario');
    }

    /**
     * Authorize the user for a specific permission or throw 403.
     *
     * @param string $permissionName
     * @return void
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    public function authorize(string $permissionName): void
    {
        $user = \Illuminate\Support\Facades\Auth::guard('api')->user();

        if (!$user || !($user instanceof Usuario)) {
             abort(401, 'Unauthenticated.');
        }

        if (!$this->check($user, $permissionName)) {
            abort(403, 'No tienes permisos para realizar esta acción.');
        }
    }
}
