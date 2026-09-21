<?php

namespace App\Sri\Data;

use Spatie\LaravelData\Data;

/**
 * Base del bloque info* de cada tipo (infoFactura, infoNotaCredito…).
 *
 * Reúne lo que el esquema del SRI repite en todos ellos pero coloca en
 * posiciones distintas: cada subtipo decide dónde va en su xmlArray().
 */
abstract class BloqueInfoData extends Data
{
    /**
     * Número de resolución de contribuyente especial (Tabla 11, fila 8).
     * Lo inyecta el pipeline desde la configuración del emisor; en el
     * payload se rechaza.
     */
    public ?string $contribuyenteEspecial = null;
}
