<?php

namespace App\Modules\Autenticacion\Presentation\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Responses\ApiResponse;
use App\Http\Controllers\Controller;
use OpenApi\Attributes as OA;

use App\Modules\Autenticacion\Application\UseCases\Profile\UpdateProfileUseCase;
use App\Modules\Autenticacion\Application\UseCases\Profile\ChangePasswordUseCase;
use App\Modules\Autenticacion\Application\UseCases\Profile\UploadSignatureUseCase;
use App\Modules\Autenticacion\Application\UseCases\Profile\UploadPhotoUseCase;
use App\Modules\Autenticacion\Application\UseCases\Profile\DeletePhotoUseCase;

class ProfileController extends Controller
{
    public function __construct(
        protected UpdateProfileUseCase $updateProfileUseCase,
        protected ChangePasswordUseCase $changePasswordUseCase,
        protected UploadSignatureUseCase $uploadSignatureUseCase,
        protected UploadPhotoUseCase $uploadPhotoUseCase,
        protected DeletePhotoUseCase $deletePhotoUseCase
    ) {}

    #[OA\Post(
        path: '/api/profile/update',
        tags: ['Perfil'],
        summary: 'Actualizar perfil',
        description: 'Actualiza la información básica del usuario autenticado.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nombre_completo', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'telefono', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Perfil actualizado exitosamente',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')
            )
        ]
    )]
    public function update(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return ApiResponse::error('Usuario no autenticado', 401);
        }

        $validator = Validator::make($request->all(), [
            'nombre_completo' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:usuarios,correo,' . $user->id,
            'telefono' => 'sometimes|nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Error de validación', 422, $validator->errors());
        }

        $updatedUser = $this->updateProfileUseCase->execute($user, $request->all());

        return ApiResponse::success($updatedUser, 'Perfil actualizado exitosamente');
    }

    #[OA\Post(
        path: '/api/profile/change-password',
        tags: ['Perfil'],
        summary: 'Cambiar contraseña',
        description: 'Actualiza la contraseña del usuario autenticado.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password', 'new_password', 'new_password_confirmation'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string', format: 'password'),
                    new OA\Property(property: 'new_password', type: 'string', format: 'password'),
                    new OA\Property(property: 'new_password_confirmation', type: 'string', format: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Contraseña actualizada exitosamente',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')
            )
        ]
    )]
    public function changePassword(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return ApiResponse::error('Usuario no autenticado', 401);
        }

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Error de validación', 422, $validator->errors());
        }

        $result = $this->changePasswordUseCase->execute($user, $request->current_password, $request->new_password);

        if (!$result['success']) {
            return ApiResponse::error($result['message'], 400);
        }

        return ApiResponse::success([], 'Contraseña actualizada exitosamente');
    }

    #[OA\Post(
        path: '/api/profile/upload-signature',
        tags: ['Perfil'],
        summary: 'Subir firma',
        description: 'Sube y actualiza la firma del usuario autenticado.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'firma', type: 'string', format: 'binary')
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Firma actualizada exitosamente',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')
            )
        ]
    )]
    public function uploadSignature(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return ApiResponse::error('Usuario no autenticado', 401);
        }

        $validator = Validator::make($request->all(), [
            'firma' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Error de validación', 422, $validator->errors());
        }

        if ($request->hasFile('firma')) {
            $path = $this->uploadSignatureUseCase->execute($user, $request->file('firma'));
            $cleanPath = ltrim(str_replace(['storage/', 'public/', 'api/'], '', $path), '/');
            return ApiResponse::success(['firma_url' => url('storage/' . $cleanPath)], 'Firma actualizada exitosamente');
        }

        return ApiResponse::error('No se ha subido ningún archivo', 400);
    }

    #[OA\Post(
        path: '/api/profile/upload-photo',
        tags: ['Perfil'],
        summary: 'Subir foto de perfil',
        description: 'Sube y actualiza la foto de perfil del usuario autenticado.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'foto', type: 'string', format: 'binary')
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Foto actualizada exitosamente',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')
            )
        ]
    )]
    public function uploadPhoto(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return ApiResponse::error('Usuario no autenticado', 401);
        }

        $validator = Validator::make($request->all(), [
            'foto' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Error de validación', 422, $validator->errors());
        }

        if ($request->hasFile('foto')) {
            $path = $this->uploadPhotoUseCase->execute($user, $request->file('foto'));
            $cleanPath = ltrim(str_replace(['storage/', 'public/', 'api/'], '', $path), '/');
            return ApiResponse::success(['foto_url' => url('storage/' . $cleanPath)], 'Foto actualizada exitosamente');
        }

        return ApiResponse::error('No se ha subido ningún archivo', 400);
    }

    #[OA\Post(
        path: '/api/profile/delete-photo',
        tags: ['Perfil'],
        summary: 'Eliminar foto de perfil',
        description: 'Elimina la foto de perfil del usuario autenticado.',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Foto eliminada exitosamente',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')
            )
        ]
    )]
    public function deletePhoto(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return ApiResponse::error('Usuario no autenticado', 401);
        }

        if ($this->deletePhotoUseCase->execute($user)) {
            return ApiResponse::success([], 'Foto eliminada exitosamente');
        }

        return ApiResponse::error('El usuario no tiene foto de perfil', 404);
    }
}
