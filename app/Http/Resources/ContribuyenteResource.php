<?php

namespace App\Http\Resources;

use App\Models\Contribuyente;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación de un contribuyente gestionado en el plano de partner.
 * El id público (uuid) es el valor de la cabecera X-Contribuyente con la
 * que el partner actúa on-behalf en la API v1.
 *
 * @mixin Contribuyente
 */
class ContribuyenteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'ruc' => $this->ruc,
            'razonSocial' => $this->razon_social,
            'nombreComercial' => $this->nombre_comercial,
            'dirMatriz' => $this->dir_matriz,
            'certificado' => [
                'configurado' => $this->tieneCertificado(),
                'titular' => $this->certificado_titular,
                'validoHasta' => $this->certificado_valido_hasta?->toIso8601String(),
            ],
            'emisionesDelMes' => $this->emisionesDelMes(),
            'creadoEn' => $this->created_at?->toIso8601String(),
        ];
    }
}
