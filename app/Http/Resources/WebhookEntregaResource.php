<?php

namespace App\Http\Resources;

use App\Models\WebhookEntrega;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Entrega de webhook: registro consultable del resultado de cada
 * notificación (para depurar integraciones).
 *
 * @mixin WebhookEntrega
 */
class WebhookEntregaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'evento' => $this->evento,
            'estado' => $this->estado->value,
            'intentos' => $this->intentos,
            'codigoHttp' => $this->codigo_http,
            'error' => $this->error,
            'payload' => $this->payload,
            'entregadoEn' => $this->entregado_en?->toIso8601String(),
            'creadoEn' => $this->created_at?->toIso8601String(),
        ];
    }
}
