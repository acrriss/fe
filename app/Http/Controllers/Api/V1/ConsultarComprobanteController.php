<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolverContribuyente;
use App\Http\Resources\ComprobanteResource;
use App\Models\Comprobante;
use Illuminate\Http\Request;

/**
 * Consulta del estado y resultado de una emisión (polling del flujo
 * asíncrono, o re-consulta de cualquier emisión por su id público).
 */
class ConsultarComprobanteController extends Controller
{
    public function __invoke(Request $request, Comprobante $comprobante): ComprobanteResource
    {
        // aislamiento entre contribuyentes: un id ajeno "no existe"
        abort_unless(
            $comprobante->contribuyente_id === ResolverContribuyente::de($request)?->id,
            404,
        );

        return new ComprobanteResource($comprobante);
    }
}
