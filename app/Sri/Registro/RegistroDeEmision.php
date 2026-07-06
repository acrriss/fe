<?php

namespace App\Sri\Registro;

use App\Models\Comprobante;
use App\Sri\Data\ComprobanteData;
use App\Sri\Enums\EstadoComprobante;
use App\Sri\Exceptions\EmisionFallida;
use App\Sri\Pipeline\EmisionComprobante;
use Illuminate\Support\Facades\Storage;

/**
 * Persistencia del ciclo de vida de una emisión: se crea pendiente, y se
 * completa o falla según el resultado del pipeline. Lo comparten el flujo
 * síncrono (controller) y el asíncrono (job).
 */
class RegistroDeEmision
{
    public function crear(ComprobanteData $comprobante): Comprobante
    {
        return Comprobante::create([
            'tipo' => $comprobante::tipo(),
            'estado' => EstadoComprobante::Pendiente,
            'ambiente' => $comprobante->infoTributaria->ambiente,
            'ruc' => (string) $comprobante->infoTributaria->ruc,
            'razon_social' => $comprobante->infoTributaria->razonSocial,
            'secuencial' => (string) $comprobante->infoTributaria->secuencial,
            'importe_total' => $comprobante->importeTotal(),
            'emitido_en' => $comprobante->fechaEmision()->toDateString(),
        ]);
    }

    public function completar(Comprobante $registro, EmisionComprobante $emision): Comprobante
    {
        $claveAcceso = (string) $emision->claveAcceso();

        $xmlPath = "comprobantes/{$claveAcceso}.xml";
        Storage::put($xmlPath, $emision->xmlFirmado());

        $registro->update([
            'estado' => EstadoComprobante::Autorizado,
            'clave_acceso' => $claveAcceso,
            'numero_autorizacion' => $emision->autorizacion?->numeroAutorizacion,
            'autorizado_en' => $emision->autorizacion?->fechaAutorizacion,
            'mensajes' => array_map(strval(...), $emision->autorizacion->mensajes ?? []),
            'xml_path' => $xmlPath,
        ]);

        return $registro;
    }

    public function fallar(Comprobante $registro, EmisionFallida $fallo, EmisionComprobante $emision): Comprobante
    {
        $registro->update([
            'estado' => $this->estadoSegunEtapa($fallo->etapa),
            'clave_acceso' => $emision->claveAcceso?->value,
            'mensajes' => [$fallo->getMessage(), ...array_map(strval(...), $fallo->mensajes)],
        ]);

        return $registro;
    }

    /**
     * La etapa donde falló la emisión determina el estado final. Si el SRI
     * no resolvió la autorización a tiempo, el comprobante queda RECIBIDO
     * (estado no final: puede consultarse de nuevo con la clave de acceso).
     */
    private function estadoSegunEtapa(string $etapa): EstadoComprobante
    {
        return match ($etapa) {
            'recepcion' => EstadoComprobante::Devuelto,
            'autorizacion' => EstadoComprobante::NoAutorizado,
            'autorizacion_pendiente' => EstadoComprobante::Recibido,
            default => EstadoComprobante::Fallido,
        };
    }
}
