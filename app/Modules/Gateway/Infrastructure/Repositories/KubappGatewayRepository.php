<?php

namespace App\Modules\Gateway\Infrastructure\Repositories;

use App\Modules\Gateway\Domain\Contracts\GatewayRepositoryInterface;
use App\Modules\Gateway\Infrastructure\Clients\KubappClient;
use App\Modules\Gateway\Application\DTOs\ArticuloGatewayDTO;
use Illuminate\Support\Collection;

class KubappGatewayRepository implements GatewayRepositoryInterface
{
    public function __construct(protected KubappClient $client) {}

    public function obtenerArticulos(array $filtros = []): Collection
    {
        $response = $this->client->obtenerArticulos($filtros);

        // KubApp devuelve un JSON paginado donde los items están en 'content'
        $data = $response['content'] ?? $response['data'] ?? $response;

        return collect($data)->map(function ($item) {
            return ArticuloGatewayDTO::fromArray($item);
        });
    }

    public function obtenerTerceros(array $filtros = []): Collection
    {
        $response = $this->client->obtenerTerceros($filtros);

        $data = $response['content'] ?? $response['data'] ?? $response;

        return collect($data)->map(function ($item) {
            return \App\Modules\Gateway\Application\DTOs\TerceroGatewayDTO::fromArray($item);
        });
    }
}
