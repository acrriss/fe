<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Http\Requests\EmitirComprobanteRequest;
use App\Http\Resources\ComprobanteResource;
use App\Jobs\ProcesarComprobanteJob;
use App\Models\Comprobante;
use App\Models\Contribuyente;
use App\Sri\Data\ComprobanteData;
use App\Sri\Exceptions\EmisionFallida;
use App\Sri\Pipeline\EmisionEnCurso;
use App\Sri\Pipeline\EmitirComprobante;
use App\Sri\Registro\RegistroDeEmision;
use App\Sri\ValueObjects\ClaveAcceso;
use Illuminate\Http\JsonResponse;

/**
 * Ejecución compartida de una emisión (síncrona o asíncrona) y su
 * traducción a respuesta HTTP. La emisión inicial y el reintento solo
 * difieren en cómo obtienen el registro y en la clave a reutilizar.
 */
trait ProcesaEmisiones
{
    protected function procesarEmision(
        EmitirComprobanteRequest $request,
        ComprobanteData $comprobante,
        Comprobante $registro,
        Contribuyente $contribuyente,
        EmitirComprobante $pipeline,
        RegistroDeEmision $registroDeEmision,
        ?ClaveAcceso $claveAcceso = null,
    ): JsonResponse {
        if ($request->boolean('async')) {
            /** @var array<string, mixed> $payloadComprobante */
            $payloadComprobante = (array) $request->validated('comprobante');

            ProcesarComprobanteJob::dispatch(
                $registro,
                $comprobante::class,
                $payloadComprobante,
                $claveAcceso?->value,
            );

            return (new ComprobanteResource($registro))
                ->response()
                ->setStatusCode(202);
        }

        $emision = new EmisionEnCurso(
            comprobante: $comprobante,
            certificado: $contribuyente->certificadoFirma(),
        );
        $emision->claveAcceso = $claveAcceso;

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

    protected function contribuyenteHabilitado(EmitirComprobanteRequest $request): Contribuyente
    {
        $contribuyente = $request->contribuyente()
            ?? abort(403, 'El usuario no pertenece a ningún contribuyente.');

        abort_unless(
            $contribuyente->tieneCertificado(),
            409,
            'El contribuyente no tiene un certificado de firma configurado.',
        );

        return $contribuyente;
    }
}
