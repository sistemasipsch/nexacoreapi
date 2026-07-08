<?php

namespace App\Modules\GestionCompras\Application\UseCases\Pedidos;

use App\Modules\GestionCompras\Application\DTOs\ActualizarPedidoProgramadoDTO;
use App\Modules\GestionCompras\Domain\Contracts\CpPedidoProgramadoRepositoryInterface;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ActualizarPedidoProgramadoUseCase
{
    use AsignarHorarioHabilTrait;

    private CpPedidoProgramadoRepositoryInterface $repository;

    public function __construct(CpPedidoProgramadoRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(ActualizarPedidoProgramadoDTO $dto): bool
    {
        $pedido = $this->repository->obtenerPorId($dto->id);
        if (!$pedido) {
            throw new \Exception("Pedido programado no encontrado.");
        }

        $datosActualizar = [];

        if ($dto->datosPedido !== null) {
            $datosActualizar['datos_pedido'] = $dto->datosPedido;
        }

        if ($dto->fechaProgramada !== null) {
            $datosActualizar['fecha_programada'] = $this->calcularFechaYHoraHabil($dto->fechaProgramada);
        }

        if ($dto->firmaFile) {
            $nombreArchivo = 'firma_' . $pedido->creado_por . '_' . time() . '_' . Str::random(5) . '.' . $dto->firmaFile->getClientOriginalExtension();
            $dto->firmaFile->storeAs('pedidos_firma', $nombreArchivo, 'public');
            $datosActualizar['firma_programador'] = 'storage/pedidos_firma/' . $nombreArchivo;
        } elseif ($dto->firmaBase64) {
            $image_parts = explode(";base64,", $dto->firmaBase64);
            $image_type_aux = explode("image/", $image_parts[0]);
            $image_type = $image_type_aux[1] ?? 'png';
            $image_base64 = base64_decode($image_parts[1]);
            
            $nombreArchivo = 'firma_' . $pedido->creado_por . '_' . time() . '_' . Str::random(5) . '.' . $image_type;
            Storage::disk('public')->put('pedidos_firma/' . $nombreArchivo, $image_base64);
            $datosActualizar['firma_programador'] = 'storage/pedidos_firma/' . $nombreArchivo;
        } elseif ($dto->useStoredSignature) {
            $user = \App\Models\Usuario::find($pedido->creado_por);
            if ($user && $user->firma_digital) {
                $extension = pathinfo($user->firma_digital, PATHINFO_EXTENSION);
                $nombreArchivo = 'firma_' . $pedido->creado_por . '_' . time() . '_' . Str::random(5) . '.' . ($extension ?: 'png');
                $rutaDestino = 'pedidos_firma/' . $nombreArchivo;
                if (Storage::disk('public')->exists($user->firma_digital)) {
                    Storage::disk('public')->copy($user->firma_digital, $rutaDestino);
                    $datosActualizar['firma_programador'] = 'storage/pedidos_firma/' . $nombreArchivo;
                } else {
                    $datosActualizar['firma_programador'] = $user->firma_digital;
                }
            } else {
                throw new \Exception("El usuario no tiene una firma guardada en su perfil.");
            }
        }

        if (empty($datosActualizar)) {
            return true; // No hay nada que actualizar
        }

        return $this->repository->actualizar($dto->id, $datosActualizar);
    }
}
