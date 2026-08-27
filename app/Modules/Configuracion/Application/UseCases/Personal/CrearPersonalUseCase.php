<?php

namespace App\Modules\Configuracion\Application\UseCases\Personal;

use App\Models\Personal;
use App\Services\ProcesarFirmaService;

class CrearPersonalUseCase
{
    public function __construct(
        protected ?ProcesarFirmaService $procesarFirmaService = null,
        protected ?SincronizarFirmasPersonalActasUseCase $sincronizarUseCase = null
    ) {
        $this->procesarFirmaService = $procesarFirmaService ?: new ProcesarFirmaService();
        $this->sincronizarUseCase = $sincronizarUseCase ?: new SincronizarFirmasPersonalActasUseCase();
    }

    public function execute(array $data)
    {
        $firmaSource = $data['firma_file'] ?? $data['firma'] ?? null;
        if ($firmaSource) {
            $data['firma'] = $this->procesarFirmaService->procesar($firmaSource, 'personal_firmas', 'personal');
            unset($data['firma_file']);
        }

        $personal = Personal::create($data);

        if (!empty($data['firma'])) {
            $this->sincronizarUseCase->execute($personal->id, $data['firma'], null);
        }

        return $personal->load('cargo');
    }
}