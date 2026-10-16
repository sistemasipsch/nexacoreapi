<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'API Departamento de Sistemas',
    description: 'Documentación de la API para el sistema de gestión del Departamento de Sistemas.',
    contact: new OA\Contact(
        email: 'soporte@example.com'
    )
)]
#[OA\Server(
    url: '/',
    description: 'API Server'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    name: 'Authorization',
    in: 'header',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Ingrese el token JWT en el formato: Bearer {token}'
)]
#[OA\Schema(
    schema: 'ApiResponse',
    title: 'Respuesta Estándar',
    description: 'Estructura de respuesta estándar para la API',
    properties: [
        new OA\Property(property: 'mensaje', type: 'string', example: 'Operación exitosa'),
        new OA\Property(property: 'objeto', type: 'object', example: []),
        new OA\Property(property: 'status', type: 'integer', example: 200)
    ]
)]
class OpenApiController extends Controller
{
    // Controller vacio, solo para definiciones globales de Swagger
}
