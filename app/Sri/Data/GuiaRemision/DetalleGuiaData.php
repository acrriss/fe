<?php

namespace App\Sri\Data\GuiaRemision;

use App\Sri\Support\Payload;
use Spatie\LaravelData\Data;

/**
 * Ítem trasladado en una guía de remisión (<detalles><detalle>): a
 * diferencia de la factura, no lleva precios ni impuestos, solo la
 * descripción y la cantidad de lo transportado.
 */
final class DetalleGuiaData extends Data
{
    public function __construct(
        public string $descripcion,
        public string $cantidad,
        public ?string $codigoInterno = null,
        public ?string $codigoAdicional = null,
    ) {}

    /**
     * @return array<string, string>
     */
    public function xmlArray(): array
    {
        return Payload::sinNulos([
            'codigoInterno' => $this->codigoInterno,
            'codigoAdicional' => $this->codigoAdicional,
            'descripcion' => $this->descripcion,
            'cantidad' => $this->cantidad,
        ]);
    }
}
