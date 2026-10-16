<?php

namespace App\Repositories;

use Illuminate\Support\Facades\Auth;

class AuthRepository
{
    /**
     * Attempt to authenticate using the given credentials.
     *
     * @param array $credentials
     * @return string|bool Token or false
     */
    public function attempt(array $credentials)
    {
        if (! $token = Auth::guard('api')->attempt($credentials)) {
            return false;
        }

        return $token;
    }

    /**
     * Get the authenticated user.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function user()
    {
        return Auth::guard('api')->user();
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return void
     */
    public function logout()
    {
        try {
            Auth::guard('api')->logout();
        } catch (\Throwable $e) {
            // Si el token ya expiró o no es válido, se ignora la excepción para completar el logout
        }
    }
}
