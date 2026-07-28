<?php

namespace App\Modules\Gateway\Infrastructure\Clients;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

class KubappClient
{
    protected string $baseUrl;
    protected string $clientId;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = env('KUBAPP_SERVICES_GATEWAY_URL', 'http://localhost:8001/api');
        $this->clientId = env('KUBAPP_SERVICES_GATEWAY_CLIENT_ID', '');
        $this->apiKey = env('KUBAPP_SERVICES_GATEWAY_API_KEY', '');
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'X-Client-ID' => $this->clientId,
                'X-API-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ]);
    }

    public function obtenerArticulos(array $filtros = [])
    {
        $response = $this->client()->get('/articulos/buscar', $filtros);

        if ($response->failed()) {
            $response->throw();
        }

        return $response->json();
    }

    public function obtenerTerceros(array $filtros = [])
    {
        $response = $this->client()->get('/terceros/buscar', $filtros);

        if ($response->failed()) {
            $response->throw();
        }

        return $response->json();
    }
}
