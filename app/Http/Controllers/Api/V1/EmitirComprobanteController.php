<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmitirComprobanteRequest;
use App\Sri\Exceptions\EmisionFallida;
use App\Sri\Pipeline\EmisionComprobante;
use App\Sri\Pipeline\EmitirComprobante;
use Illuminate\Http\JsonResponse;

/**
 * Emisión síncrona: ejecuta el pipeline completo y devuelve el resultado.
 * (La modalidad asíncrona llegará en la fase 5 reutilizando el pipeline.)
 */
class EmitirComprobanteController extends Controller
{
    public function __invoke(EmitirComprobanteRequest $request, EmitirComprobante $pipeline): JsonResponse
    {
        $emision = new EmisionComprobante(
            comprobante: $request->comprobante(),
            certificado: $request->certificado(),
        );

        try {
            $emision = $pipeline->emitir($emision);
        } catch (EmisionFallida $fallo) {
            return response()->json([
                'emitido' => false,
                'etapa' => $fallo->etapa,
                'error' => $fallo->getMessage(),
                'mensajes' => array_map(strval(...), $fallo->mensajes),
                'claveAcceso' => $emision->claveAcceso?->value,
            ], 422);
        }

        return response()->json([
            'emitido' => true,
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
