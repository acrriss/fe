<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ProcesaEmisiones;
use App\Http\Controllers\Controller;
use App\Http\Requests\EmitirComprobanteRequest;
use App\Models\Comprobante;
use App\Sri\Enums\EstadoComprobante;
use App\Sri\Pipeline\EmitirComprobante;
use App\Sri\Registro\RegistroDeEmision;
use App\Sri\ValueObjects\ClaveAcceso;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Reintento de una emisión rechazada, conforme a §5.10 de la ficha del
 * SRI: el comprobante corregido se reenvía con LA MISMA clave de acceso
 * y secuencial. El registro original se reutiliza (mismo id público), por
 * lo que el reintento no consume cuota adicional.
 */
class ReintentarComprobanteController extends Controller
{
    use ProcesaEmisiones;

    /**
     * Estados desde los que se puede reintentar.
     */
    private const array REINTENTABLES = [
        EstadoComprobante::Devuelto,
        EstadoComprobante::NoAutorizado,
        EstadoComprobante::Fallido,
    ];

    public function __invoke(
        EmitirComprobanteRequest $request,
        Comprobante $comprobante,
        EmitirComprobante $pipeline,
        RegistroDeEmision $registroDeEmision,
    ): JsonResponse {
        $contribuyente = $this->contribuyenteHabilitado($request);

        // aislamiento entre contribuyentes: un id ajeno "no existe"
        abort_unless($comprobante->contribuyente_id === $contribuyente->id, 404);

        abort_unless(
            in_array($comprobante->estado, self::REINTENTABLES, true),
            409,
            "Solo se reintentan emisiones devueltas, no autorizadas o fallidas (estado actual: {$comprobante->estado->value}).",
        );

        $comprobanteData = $request->comprobante();

        if ($comprobanteData::tipo() !== $comprobante->tipo) {
            throw ValidationException::withMessages([
                'tipo' => 'El tipo del payload no coincide con el del comprobante a reintentar.',
            ]);
        }

        $registro = $registroDeEmision->reintentar($comprobante, $comprobanteData);

        // §5.10: se reutiliza la clave del intento anterior si existe
        // (una emisión fallida antes de generar clave simplemente recibe una nueva)
        $claveAcceso = $registro->clave_acceso !== null
            ? ClaveAcceso::fromString($registro->clave_acceso)
            : null;

        return $this->procesarEmision(
            $request,
            $comprobanteData,
            $registro,
            $contribuyente,
            $pipeline,
            $registroDeEmision,
            $claveAcceso,
        );
    }
}
