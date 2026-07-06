<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmitirComprobanteRequest;
use App\Http\Resources\ComprobanteResource;
use App\Jobs\ProcesarComprobanteJob;
use App\Sri\Exceptions\EmisionFallida;
use App\Sri\Pipeline\EmisionComprobante;
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
    public function __invoke(
        EmitirComprobanteRequest $request,
        EmitirComprobante $pipeline,
        RegistroDeEmision $registroDeEmision,
    ): JsonResponse {
        $comprobante = $request->comprobante();
        $certificado = $request->certificado(); // valida el base64 antes de encolar
        $registro = $registroDeEmision->crear($comprobante);

        if ($request->boolean('async')) {
            /** @var array<string, mixed> $payloadComprobante */
            $payloadComprobante = (array) $request->validated('comprobante');

            ProcesarComprobanteJob::dispatch(
                $registro,
                $comprobante::class,
                $payloadComprobante,
                $request->string('info.p12')->toString(),
                $request->string('info.clavep12')->toString(),
            );

            return (new ComprobanteResource($registro))
                ->response()
                ->setStatusCode(202);
        }

        $emision = new EmisionComprobante(comprobante: $comprobante, certificado: $certificado);

        try {
            $registroDeEmision->completar($registro, $pipeline->emitir($emision));
        } catch (EmisionFallida $fallo) {
            $registroDeEmision->fallar($registro, $fallo, $emision);

            return response()->json([
                'emitido' => false,
                'id' => $registro->uuid,
                'etapa' => $fallo->etapa,
                'error' => $fallo->getMessage(),
                'mensajes' => array_map(strval(...), $fallo->mensajes),
                'claveAcceso' => $emision->claveAcceso?->value,
            ], 422);
        }

        return response()->json([
            'emitido' => true,
            'id' => $registro->uuid,
            'tipo' => $request->tipoComprobante()->rootElement(),
            'claveAcceso' => $emision->claveAcceso()->value,
            'autorizacion' => [
                'estado' => $emision->autorizacion?->estado,
                'numero' => $emision->autorizacion?->numeroAutorizacion,
                'fecha' => $emision->autorizacion?->fechaAutorizacion?->toIso8601String(),
                'mensajes' => array_map(strval(...), $emision->autorizacion->mensajes ?? []),
            ],
            'xmlFirmado' => base64_encode($emision->xmlFirmado()),
        ]);
    }
}
