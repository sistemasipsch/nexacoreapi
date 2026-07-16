<?php

namespace App\Modules\GestionCompras\Application\UseCases\Pedidos;

use App\Modules\GestionCompras\Application\DTOs\ProgramarPedidoDTO;
use App\Modules\GestionCompras\Domain\Contracts\CpPedidoProgramadoRepositoryInterface;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ProgramarPedidoUseCase
{
    use AsignarHorarioHabilTrait;

    private CpPedidoProgramadoRepositoryInterface $repository;

    public function __construct(CpPedidoProgramadoRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(ProgramarPedidoDTO $dto): object
    {
        $rutaFirma = null;

        // Guardar firma si viene como archivo (multipart/form-data) o base64
        if ($dto->firmaFile) {
            $nombreArchivo = 'firma_' . $dto->creadoPor . '_' . time() . '_' . Str::random(5) . '.' . $dto->firmaFile->getClientOriginalExtension();
            $dto->firmaFile->storeAs('pedidos_firma', $nombreArchivo, 'public');
            $rutaFirma = 'storage/pedidos_firma/' . $nombreArchivo;
        } elseif ($dto->firmaBase64) {
            $image_parts = explode(";base64,", $dto->firmaBase64);
            $image_type_aux = explode("image/", $image_parts[0]);
            
            $image_type = $image_type_aux[1] ?? 'png';
            $image_base64 = base64_decode($image_parts[1]);
            
            $nombreArchivo = 'firma_' . $dto->creadoPor . '_' . time() . '_' . Str::random(5) . '.' . $image_type;
            Storage::disk('public')->put('pedidos_firma/' . $nombreArchivo, $image_base64);
            $rutaFirma = 'storage/pedidos_firma/' . $nombreArchivo;
        } elseif ($dto->useStoredSignature) {
            $user = \App\Models\Usuario::find($dto->creadoPor);
            if ($user && $user->firma_digital) {
                // Copy the user's signature to the FirmasProgramacionPedidos directory
                $extension = pathinfo($user->firma_digital, PATHINFO_EXTENSION);
                $nombreArchivo = 'firma_' . $dto->creadoPor . '_' . time() . '_' . Str::random(5) . '.' . ($extension ?: 'png');
                $rutaDestino = 'pedidos_firma/' . $nombreArchivo;
                if (Storage::disk('public')->exists($user->firma_digital)) {
                    Storage::disk('public')->copy($user->firma_digital, $rutaDestino);
                    $rutaFirma = 'storage/pedidos_firma/' . $nombreArchivo;
                } else {
                    $rutaFirma = $user->firma_digital; // Fallback if it's already a public URL or other path
                }
            } else {
                throw new \Exception("El usuario no tiene una firma guardada en su perfil.");
            }
        }

        $datos = [
            'datos_pedido' => $dto->datosPedido,
            'fecha_programada' => $this->calcularFechaYHoraHabil($dto->fechaProgramada),
            'firma_programador' => $rutaFirma,
            'creado_por' => $dto->creadoPor,
            'estado' => 'programado'
        ];

        return $this->repository->crear($datos);
    }
}
