<?php

namespace App\Modules\Auditoria\Infrastructure\Repositories;

use App\Models\CpPedidoProgramado;
use App\Modules\Auditoria\Application\DTOs\FiltroPedidosProgramadosDTO;
use App\Modules\Auditoria\Domain\Contracts\PedidoProgramadoAuditoriaRepositoryInterface;
use App\Modules\Auditoria\Domain\Entities\PedidoProgramadoAuditoriaEntity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PedidoProgramadoAuditoriaRepository implements PedidoProgramadoAuditoriaRepositoryInterface
{
    public function listarPaginado(FiltroPedidosProgramadosDTO $filtro): LengthAwarePaginator
    {
        $query = CpPedidoProgramado::with(['creador.rol', 'creador.sede', 'pedido:id,consecutivo']);

        // Filtro por rango de fechas (evaluado sobre fecha_programada)
        if (!empty($filtro->fechaInicio)) {
            $desde = strlen($filtro->fechaInicio) === 10 
                ? "{$filtro->fechaInicio} 00:00:00" 
                : $filtro->fechaInicio;
            $query->where('fecha_programada', '>=', $desde);
        }

        if (!empty($filtro->fechaFin)) {
            $hasta = strlen($filtro->fechaFin) === 10 
                ? "{$filtro->fechaFin} 23:59:59" 
                : $filtro->fechaFin;
            $query->where('fecha_programada', '<=', $hasta);
        }

        // Filtro por estado del pedido programado
        if (!empty($filtro->estado)) {
            $query->where('estado', $filtro->estado);
        }

        // Filtro por creador específico
        if (!empty($filtro->creadoPor)) {
            $query->where('creado_por', $filtro->creadoPor);
        }

        // Búsqueda por texto (en el JSON de datos del pedido o en el nombre del creador)
        if (!empty($filtro->buscar)) {
            $term = $filtro->buscar;
            $query->where(function ($q) use ($term) {
                $q->where('datos_pedido', 'LIKE', "%{$term}%")
                  ->orWhereHas('creador', function ($qu) use ($term) {
                      $qu->where('nombre_completo', 'LIKE', "%{$term}%")
                         ->orWhere('usuario', 'LIKE', "%{$term}%")
                         ->orWhere('correo', 'LIKE', "%{$term}%");
                  });
            });
        }

        // Orden cronológico descendente
        $query->orderBy('fecha_programada', 'desc')->orderBy('id', 'desc');

        $paginator = $query->paginate($filtro->perPage, ['*'], 'page', $filtro->page);
        $paginator->getCollection()->each(fn ($m) => $m->datos_pedido = $this->enriquecerDatosPedido($m));

        return $paginator;
    }

    public function obtenerPorId(int $id): ?PedidoProgramadoAuditoriaEntity
    {
        $pedido = CpPedidoProgramado::with(['creador.rol', 'creador.sede', 'pedido:id,consecutivo'])->find($id);

        if (!$pedido) {
            return null;
        }

        return $this->mapToEntity($pedido);
    }

    private function mapToEntity(CpPedidoProgramado $model): PedidoProgramadoAuditoriaEntity
    {
        $creador = null;
        if ($model->creador) {
            $creador = [
                'id' => $model->creador->id,
                'nombre_completo' => $model->creador->nombre_completo,
                'usuario' => $model->creador->usuario,
                'correo' => $model->creador->correo,
                'rol' => $model->creador->rol ? $model->creador->rol->nombre : null,
                'sede' => $model->creador->sede ? $model->creador->sede->nombre : null,
            ];
        }

        return PedidoProgramadoAuditoriaEntity::fromArray([
            'id' => $model->id,
            'datos_pedido' => $this->enriquecerDatosPedido($model),
            'fecha_programada' => $model->getRawOriginal('fecha_programada'),
            'firma_programador' => $model->firma_programador,
            'creado_por' => $model->creado_por,
            'estado' => $model->estado,
            'created_at' => $model->created_at ? $model->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $model->updated_at ? $model->updated_at->format('Y-m-d H:i:s') : null,
            'creador' => $creador,
        ]);
    }

    /**
     * Agrega al payload el consecutivo del pedido generado y los nombres de proceso y sede
     * (el payload original solo guarda los ids).
     */
    private function enriquecerDatosPedido(CpPedidoProgramado $model): array
    {
        $datos = $model->datos_pedido;
        if (is_string($datos)) {
            $datos = json_decode($datos, true);
        }
        $datos = is_array($datos) ? $datos : [];

        $datos['consecutivo_pedido'] = $model->pedido?->consecutivo;

        if (empty($datos['proceso_nombre']) && !empty($datos['proceso_solicitante'])) {
            $datos['proceso_nombre'] = \App\Models\DependenciaSede::find($datos['proceso_solicitante'])?->nombre;
        }
        if (empty($datos['sede_nombre']) && !empty($datos['sede_id'])) {
            $datos['sede_nombre'] = \App\Models\Sede::find($datos['sede_id'])?->nombre;
        }

        return $datos;
    }
}