<?php

namespace App\Http\Controllers\Api\Partner\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActualizarContribuyenteRequest;
use App\Http\Resources\ContribuyenteResource;
use App\Models\Partner;

/**
 * Actualiza los datos editables de un contribuyente gestionado, incluido
 * su sublímite mensual dentro de la cuota pool (§11, 7d). El RUC no se
 * edita: identifica al emisor ante el SRI.
 */
class ActualizarContribuyenteController extends Controller
{
    public function __invoke(ActualizarContribuyenteRequest $request, string $uuid): ContribuyenteResource
    {
        /** @var Partner $partner */
        $partner = $request->user();

        $contribuyente = $partner->contribuyentes()->where('uuid', $uuid)->first()
            ?? abort(404);

        // solo las claves presentes en el request (incluido null explícito,
        // que quita el sublímite o una designación); las ausentes no se tocan
        $contribuyente->update($request->datosPresentes());

        return new ContribuyenteResource($contribuyente);
    }
}
