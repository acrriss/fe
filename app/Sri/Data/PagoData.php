<?php

namespace App\Sri\Data;

use App\Sri\Support\Payload;
use Spatie\LaravelData\Data;

/**
 * Forma de pago (<pagos><pago>), común a nota de débito y liquidación de
 * compra. `formaPago` sigue la tabla 24 de la ficha del SRI.
 */
final class PagoData extends Data
{
    public function __construct(
        public string $formaPago,
        public string $total,
        public ?string $plazo = null,
        public ?string $unidadTiempo = null,
    ) {}

    /**
     * @return array<string, string>
     */
    public function xmlArray(): array
    {
        return Payload::sinNulos([
            'formaPago' => $this->formaPago,
            'total' => $this->total,
            'plazo' => $this->plazo,
            'unidadTiempo' => $this->unidadTiempo,
        ]);
    }
}
