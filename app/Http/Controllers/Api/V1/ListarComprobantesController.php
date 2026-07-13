<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolverContribuyente;
use App\Http\Resources\ComprobanteResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Lista las emisiones del contribuyente actual. El filtro por external_id
 * permite al integrador reconciliar contra sus propios registros (p. ej.
 * recuperar el comprobante cuando perdió la respuesta de la emisión).
 */
class ListarComprobantesController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'external_id' => ['sometimes', 'string', 'max:255'],
            'estado' => ['sometimes', 'string'],
        ]);

        $contribuyente = ResolverContribuyente::de($request)
            ?? abort(403, 'El usuario no pertenece a ningún contribuyente.');

        $comprobantes = $contribuyente->comprobantes()
            ->when(
                $request->filled('external_id'),
                fn ($query) => $query->where('external_id', $request->string('external_id')->toString()),
            )
            ->when(
                $request->filled('estado'),
                fn ($query) => $query->where('estado', $request->string('estado')->toString()),
            )
            ->latest()
            ->paginate(25);

        return ComprobanteResource::collection($comprobantes);
    }
}
