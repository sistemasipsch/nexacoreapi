<?php

namespace App\Modules\GestionSistemas\Application\UseCases\EquiposComputo;

use App\Modules\GestionSistemas\Domain\Contracts\PcEquipoRepositoryInterface;
use App\Models\PcEquipo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ActualizarPcEquipoUseCase
{
    private PcEquipoRepositoryInterface $repository;

    public function __construct(PcEquipoRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(int $id, array $data): ?PcEquipo
    {
        $equipo = PcEquipo::find($id);
        if (!$equipo) {
            return null;
        }

        // Handle image upload if provided as UploadedFile
        if (isset($data['imagen']) && $data['imagen'] instanceof UploadedFile) {
            $data['imagen_url'] = $this->handleImageUpload($equipo, $data['imagen']);
            unset($data['imagen']);
        } elseif (isset($data['imagen_file']) && $data['imagen_file'] instanceof UploadedFile) {
            $data['imagen_url'] = $this->handleImageUpload($equipo, $data['imagen_file']);
            unset($data['imagen_file']);
        }

        // Handle image removal if requested
        if (!empty($data['eliminar_imagen']) || !empty($data['remover_imagen'])) {
            $this->deleteOldImage($equipo->imagen_url);
            $data['imagen_url'] = null;
            unset($data['eliminar_imagen'], $data['remover_imagen']);
        }

        return $this->repository->update($id, $data);
    }

    protected function handleImageUpload(PcEquipo $equipo, UploadedFile $file): string
    {
        $this->deleteOldImage($equipo->imagen_url);
        $path = $file->store('pcEquipos', 'public');
        return 'storage/' . $path;
    }

    protected function deleteOldImage(?string $imageUrl): void
    {
        if (!$imageUrl) {
            return;
        }

        $cleanPath = preg_replace('#^(storage/|/storage/)#', '', $imageUrl);
        if ($cleanPath && Storage::disk('public')->exists($cleanPath)) {
            Storage::disk('public')->delete($cleanPath);
        }
    }
}
