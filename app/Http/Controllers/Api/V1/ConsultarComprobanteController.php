<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ComprobanteResource;
use App\Models\Comprobante;

/**
 * Consulta del estado y resultado de una emisión (polling del flujo
 * asíncrono, o re-consulta de cualquier emisión por su id público).
 */
class ConsultarComprobanteController extends Controller
{
    public function __invoke(Comprobante $comprobante): ComprobanteResource
    {
        return new ComprobanteResource($comprobante);
    }
}
