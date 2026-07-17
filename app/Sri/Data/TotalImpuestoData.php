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
     * Etiqueta legible para el RIDE: el cliente final no entiende
     * "Impuesto 2 (4)". Mapea los códigos de la ficha del SRI (tabla de
     * impuestos y tarifas de IVA); combinaciones desconocidas caen al
     * formato crudo para no ocultar información.
     */
    public function etiqueta(): string
    {
        if ($this->codigo === '2') {
            $tarifas = [
                '0' => 'IVA 0%',
                '2' => 'IVA 12%',
                '3' => 'IVA 14%',
                '4' => 'IVA 15%',
                '5' => 'IVA 5%',
                '6' => 'No objeto de IVA',
                '7' => 'Exento de IVA',
                '8' => 'IVA 8%',
                '10' => 'IVA 13%',
            ];

            if (isset($tarifas[$this->codigoPorcentaje])) {
                return $tarifas[$this->codigoPorcentaje];
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
