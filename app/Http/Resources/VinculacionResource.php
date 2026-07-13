<?php

namespace App\Http\Resources;

use App\Models\Vinculacion;
use App\Sri\Enums\EstadoVinculacion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Solicitud de vinculación de un RUC existente, vista por el partner.
 *
 * @mixin Vinculacion
 */
class VinculacionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'ruc' => $this->contribuyente?->ruc,
            'razonSocial' => $this->contribuyente?->razon_social,
            'estado' => $this->estado->value,
            'contribuyente' => $this->when(
                $this->estado === EstadoVinculacion::Aprobada,
                fn (): ?string => $this->contribuyente?->uuid,
            ),
            'solicitadaEn' => $this->created_at?->toIso8601String(),
            'resueltaEn' => $this->resuelta_en?->toIso8601String(),
        ];
    }
}
