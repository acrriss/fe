<?php

namespace App\Sri\Data\Concerns;

use App\Sri\Catalogos\TarifasIva;
use App\Sri\Exceptions\DatoInvalido;

/**
 * El `codigoPorcentaje` de un impuesto de IVA debe estar en la Tabla 17.
 *
 * Se valida **solo cuando el impuesto es IVA** (`codigo` 2): el ICE y el
 * IRBPNR tienen sus propias tablas de tarifas, y dejarlos pasar mantiene
 * abierta la puerta para cuando se soporten.
 *
 * Importa porque tres códigos de la tabla valen cero y no son lo mismo
 * —0 (0%), 6 (no objeto) y 7 (exento)—: el importe sale 0.00 en los tres,
 * así que el SRI autoriza igual y un código equivocado solo se descubre en
 * una auditoría. Aquí es un 422 al emitir.
 */
trait ValidaTarifaDeIva
{
    /**
     * @param  array<string, mixed>  $properties
     */
    protected static function validarTarifaDeIva(array $properties): void
    {
        $codigo = data_get($properties, 'codigo');
        $codigoPorcentaje = data_get($properties, 'codigoPorcentaje');

        if (! is_scalar($codigo) || (string) $codigo !== TarifasIva::CODIGO_IVA) {
            return;
        }

        if (! is_scalar($codigoPorcentaje) || ! TarifasIva::existe((string) $codigoPorcentaje)) {
            throw DatoInvalido::porFormato(
                'codigoPorcentaje',
                'un código de la Tabla 17 de la ficha ('.implode(', ', TarifasIva::todos()).')',
                is_scalar($codigoPorcentaje) ? (string) $codigoPorcentaje : 'vacío',
            );
        }
    }
}
