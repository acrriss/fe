<?php

namespace App\Http\Controllers\Api\Partner\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContribuyenteResource;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Lista los contribuyentes gestionados por el partner, con su consumo del
 * mes y el estado del certificado. Filtro opcional por RUC exacto.
 */
class ListarContribuyentesController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'ruc' => ['sometimes', 'string'],
        ]);

        /** @var Partner $partner */
        $partner = $request->user();

        $contribuyentes = $partner->contribuyentes()
            ->when(
                $request->filled('ruc'),
                fn ($query) => $query->where('ruc', $request->string('ruc')->toString()),
            )
            ->latest()
            ->paginate(25);

        return ContribuyenteResource::collection($contribuyentes);
    }
}
