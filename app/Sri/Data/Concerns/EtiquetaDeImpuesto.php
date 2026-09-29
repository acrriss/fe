<?php

namespace App\Sri\Data\Concerns;

use App\Sri\Catalogos\TarifasIva;

/**
 * Etiqueta legible de un impuesto para el RIDE, común al total de cabecera
 * (TotalImpuestoData) y al impuesto de la nota de débito (ImpuestoData).
 *
 * @property-read string $codigo
 * @property-read string $codigoPorcentaje
 */
trait EtiquetaDeImpuesto
{
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
}
