<?php

namespace App\Sri\Data;

use App\Sri\Catalogos\TarifasIva;
use App\Sri\Data\Concerns\RechazaClavesDesconocidas;
use App\Sri\Data\Concerns\ValidaTarifaDeIva;
use App\Sri\Support\Payload;
use Spatie\LaravelData\Data;

/**
 * Impuesto agregado de la cabecera (<totalConImpuestos><totalImpuesto>).
 * La tarifa es opcional (la nota de crédito no la incluye).
 */
final class TotalImpuestoData extends Data
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
        public string $baseImponible,
        public string $valor,
        public ?string $tarifa = null,
    ) {}

    /**
     * Etiqueta legible para el RIDE: el cliente final no entiende
     * "Impuesto 2 (4)". El nombre del IVA sale del catálogo de la Tabla 17,
     * que es la misma fuente que valida el código y que alimenta el
     * selector del integrador; combinaciones desconocidas caen al formato
     * crudo para no ocultar información.
     */
    public function etiqueta(): string
    {
        if ($this->codigo === TarifasIva::CODIGO_IVA) {
            $nombreTarifa = TarifasIva::nombre($this->codigoPorcentaje);

            if ($nombreTarifa !== null) {
                return $nombreTarifa;
            }
        }

        $nombre = match ($this->codigo) {
            '3' => 'ICE',
            '5' => 'IRBPNR',
            default => null,
        };

        if ($nombre !== null) {
            return $nombre;
        }

        return "Impuesto {$this->codigo} ({$this->codigoPorcentaje})";
    }

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
