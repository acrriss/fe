<?php

namespace App\Http\Resources;

use App\Models\Comprobante;
use App\Sri\Enums\EstadoComprobante;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Representación pública de una emisión. El XML firmado solo viaja cuando
 * el comprobante está autorizado.
 *
 * @mixin Comprobante
 */
class ComprobanteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $autorizado = $this->estado === EstadoComprobante::Autorizado;

        return [
            'id' => $this->uuid,
            'tipo' => $this->tipo->rootElement(),
            'estado' => $this->estado->value,
            'estadoFinal' => $this->estado->esFinal(),
            'ambiente' => $this->ambiente->value,
            'secuencial' => $this->secuencial,
            'claveAcceso' => $this->clave_acceso,
            'externalId' => $this->external_id,
            'metadata' => $this->metadata,
            'importeTotal' => $this->importe_total,
            'emitidoEn' => $this->emitido_en?->toDateString(),
            'autorizacion' => $this->when($autorizado, fn (): array => [
                'numero' => $this->numero_autorizacion,
                'fecha' => $this->autorizado_en?->toIso8601String(),
            ]),
            'mensajes' => $this->mensajes ?? [],
            'xmlFirmado' => $this->when(
                $autorizado && $this->xml_path !== null && Storage::exists($this->xml_path),
                fn (): string => base64_encode((string) Storage::get((string) $this->xml_path)),
            ),
        ];
    }
}
