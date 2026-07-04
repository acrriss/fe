<?php

namespace App\Sri\Data;

use Spatie\LaravelData\Data;

/**
 * Impuesto agregado de la cabecera (<totalConImpuestos><totalImpuesto>).
 * La tarifa es opcional (la nota de crédito no la incluye).
 */
final class TotalImpuestoData extends Data
{
    public function __construct(
        public string $codigo,
        public string $codigoPorcentaje,
        public string $baseImponible,
        public string $valor,
        public ?string $tarifa = null,
    ) {}
}
