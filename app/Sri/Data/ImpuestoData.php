<?php

namespace App\Sri\Data;

use Spatie\LaravelData\Data;

/**
 * Impuesto aplicado a una línea de detalle (<impuestos><impuesto>).
 *
 * Los importes se mantienen como string: son valores ya formateados por el
 * emisor que viajan tal cual al XML (evita problemas de precisión float).
 */
final class ImpuestoData extends Data
{
    public function __construct(
        public string $codigo,
        public string $codigoPorcentaje,
        public string $tarifa,
        public string $baseImponible,
        public string $valor,
    ) {}
}
