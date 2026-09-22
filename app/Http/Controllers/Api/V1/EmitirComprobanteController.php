<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ProcesaEmisiones;
use App\Http\Controllers\Controller;
use App\Http\Requests\EmitirComprobanteRequest;
use App\Sri\Actions\GenerarClaveAcceso;
use App\Sri\Pipeline\EmitirComprobante;
use App\Sri\Registro\RegistroDeEmision;
use Illuminate\Http\JsonResponse;

/**
 * Emisión de comprobantes. Ambas modalidades comparten el mismo pipeline:
 *
 *  - síncrona (por defecto): ejecuta la emisión completa y responde con el
 *    resultado (~segundos, depende del SRI).
 *  - asíncrona (?async=1): encola ProcesarComprobanteJob y responde 202
 *    con el id y la clave de acceso, que ya está calculada. El cliente
 *    puede imprimir su RIDE sin esperar al SRI.
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

        // La clave se calcula ANTES de encolar: no depende de la red ni del
        // certificado, y el cliente la necesita en el acto para imprimir el
        // RIDE de la venta sin esperar al SRI.
        $claveAcceso = GenerarClaveAcceso::para($comprobante);

        $registro = $registroDeEmision->crear(
            $comprobante,
            $contribuyente,
            $claveAcceso,
            $request->externalId(),
            $request->metadata(),
        );

        return $this->procesarEmision($request, $comprobante, $registro, $contribuyente, $pipeline, $registroDeEmision, $claveAcceso);
    }
}
