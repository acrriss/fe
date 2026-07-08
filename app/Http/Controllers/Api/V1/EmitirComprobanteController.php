<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ProcesaEmisiones;
use App\Http\Controllers\Controller;
use App\Http\Requests\EmitirComprobanteRequest;
use App\Sri\Pipeline\EmitirComprobante;
use App\Sri\Registro\RegistroDeEmision;
use Illuminate\Http\JsonResponse;

/**
 * Emisión de comprobantes. Ambas modalidades comparten el mismo pipeline:
 *
 *  - síncrona (por defecto): ejecuta la emisión completa y responde con el
 *    resultado (~segundos, depende del SRI).
 *  - asíncrona (?async=1): encola ProcesarComprobanteJob y responde 202
 *    con el id para consultar el estado.
 */
class EmitirComprobanteController extends Controller
{
    use ProcesaEmisiones;

    public function __invoke(
        EmitirComprobanteRequest $request,
        EmitirComprobante $pipeline,
        RegistroDeEmision $registroDeEmision,
    ): JsonResponse {
        $contribuyente = $this->contribuyenteHabilitado($request);

        abort_if(
            $contribuyente->agotoCuotaMensual(),
            429,
            'La cuota mensual del plan está agotada.',
        );

        $comprobante = $request->comprobante();
        $registro = $registroDeEmision->crear($comprobante, $contribuyente);

        return $this->procesarEmision($request, $comprobante, $registro, $contribuyente, $pipeline, $registroDeEmision);
    }
}
