<?php

namespace App\Sri\Data;

use App\Sri\Support\Payload;
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

    /**
     * @return array<string, string>
     */
    public function xmlArray(): array
    {
        return Payload::sinNulos([
            'codigo' => $this->codigo,
            'codigoPorcentaje' => $this->codigoPorcentaje,
            'baseImponible' => $this->baseImponible,
            'tarifa' => $this->tarifa,
            'valor' => $this->valor,
        ]);
    }
}
