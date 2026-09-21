<?php

namespace App\Http\Controllers\Api\Partner\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AprovisionarContribuyenteRequest;
use App\Http\Resources\ContribuyenteResource;
use App\Models\Contribuyente;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;

/**
 * Aprovisiona un contribuyente gestionado (§11): el partner da de alta a
 * su cliente final sin que este pase por nuestro panel. Idempotente por
 * (partner, ruc): repetir la llamada devuelve el contribuyente existente
 * (200) en vez de crear otro (201).
 *
 * El contribuyente nace sin certificado (se carga después, on-behalf) y
 * sin plan propio: consume la cuota pool del partner.
 */
class AprovisionarContribuyenteController extends Controller
{
    public function __invoke(AprovisionarContribuyenteRequest $request): JsonResponse
    {
        $ruc = $request->ruc();

        /** @var Partner $partner */
        $partner = $request->user();

        $existente = $partner->contribuyentes()->where('ruc', $ruc)->first();

        if ($existente !== null) {
            return (new ContribuyenteResource($existente))->response();
        }

        // vinculación de un RUC ya registrado en otra cuenta: fase 7d
        abort_if(
            Contribuyente::where('ruc', $ruc)->exists(),
            409,
            'El RUC ya está registrado en otra cuenta.',
        );

        $contribuyente = $partner->contribuyentes()->create($request->datosContribuyente());

        return (new ContribuyenteResource($contribuyente))
            ->response()
            ->setStatusCode(201);
    }
}
