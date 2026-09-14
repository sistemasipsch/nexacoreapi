<?php

namespace App\Modules\GestionCompras\Application\UseCases\Inventario;

use App\Exports\CpInventarioExport;
use App\Models\Inventario;
use App\Models\Personal;
use App\Models\Sede;
use App\Modules\Shared\Domain\Contracts\ExcelToPdfConverterInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportarInventarioPdfUseCase
{
    public function __construct(
        protected ExcelToPdfConverterInterface $pdfConverter
    ) {}

    public function execute(
        ?string $search = null,
        mixed $sede_id = null,
        mixed $responsable_id = null,
        mixed $coordinador_id = null,
        string $format = 'stream'
    ): StreamedResponse|array {
        $query = Inventario::query();

        // 1. Filtro de búsqueda
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('codigo', 'like', "%{$search}%")
                    ->orWhere('nombre', 'like', "%{$search}%")
                    ->orWhere('marca', 'like', "%{$search}%")
                    ->orWhere('modelo', 'like', "%{$search}%")
                    ->orWhere('serial', 'like', "%{$search}%")
                    ->orWhere('codigo_barras', 'like', "%{$search}%");
            });
        }

        // 2. Filtro por Sede
        $sedeNombre = null;
        if (!empty($sede_id) && $sede_id !== 'todas' && $sede_id !== 'all') {
            $query->where('sede_id', $sede_id);
            $sede = Sede::find($sede_id);
            if ($sede) {
                $sedeNombre = $sede->nombre;
            }
        }

        // 3. Filtro por Responsable
        $extraFilters = [];
        if (!empty($responsable_id) && $responsable_id !== 'todos' && $responsable_id !== 'all') {
            $query->where('responsable_id', $responsable_id);
            $resp = Personal::find($responsable_id);
            if ($resp) {
                $extraFilters[] = "Responsable: {$resp->nombre}";
            }
        }

        // 4. Filtro por Coordinador
        if (!empty($coordinador_id) && $coordinador_id !== 'todos' && $coordinador_id !== 'all') {
            $query->where('coordinador_id', $coordinador_id);
            $coord = Personal::find($coordinador_id);
            if ($coord) {
                $extraFilters[] = "Coordinador: {$coord->nombre}";
            }
        }

        if (!empty($search)) {
            $extraFilters[] = "Búsqueda: \"{$search}\"";
        }

        $extraInfo = count($extraFilters) > 0 ? implode(' | ', $extraFilters) : null;

        $items = $query->with(['responsablePersonal', 'coordinadorPersonal', 'sede', 'proceso'])
            ->orderBy('id', 'desc')
            ->get();

        $export = new CpInventarioExport($this->pdfConverter);
        $returnUrl = ($format === 'url');

        return $export->generatePdf($items, $sedeNombre, $extraInfo, $returnUrl);
    }
}
