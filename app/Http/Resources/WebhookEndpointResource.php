<?php

namespace App\Http\Resources;

use App\Models\WebhookEndpoint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Endpoint de webhook. El secreto de firma NO se incluye: solo viaja una
 * vez, en la respuesta de creación.
 *
 * @mixin WebhookEndpoint
 */
class WebhookEndpointResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'url' => $this->url,
            'eventos' => $this->eventos,
            'activo' => $this->activo,
            'creadoEn' => $this->created_at?->toIso8601String(),
        ];
    }
}
