<?php

namespace App\Sri\Data;

use App\Sri\Data\Concerns\RechazaClavesDesconocidas;
use App\Sri\Data\Concerns\ValidaTarifaDeIva;
use Spatie\LaravelData\Data;

/**
 * Impuesto aplicado a una línea de detalle (<impuestos><impuesto>).
 *
 * Los importes se mantienen como string: son valores ya formateados por el
 * emisor que viajan tal cual al XML (evita problemas de precisión float).
 */
final class ImpuestoData extends Data
{
    use RechazaClavesDesconocidas;
    use ValidaTarifaDeIva;

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $properties): array
    {
        self::validarTarifaDeIva($properties);

        return self::soloClavesConocidas($properties);
    }

    public function __construct(
        public string $codigo,
        public string $codigoPorcentaje,
        public string $tarifa,
        public string $baseImponible,
        public string $valor,
    ) {}

    /**
     * @return array<string, string>
     */
    public function xmlArray(): array
    {
        return [
            'codigo' => $this->codigo,
            'codigoPorcentaje' => $this->codigoPorcentaje,
            'tarifa' => $this->tarifa,
            'baseImponible' => $this->baseImponible,
            'valor' => $this->valor,
        ];
    }
}
